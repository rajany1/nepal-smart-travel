<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\CommissionTransaction;
use App\Models\OfferRedemption;
use App\Models\TravelPartner;
use App\Services\ShopService;
use Illuminate\Http\Request;

class ConsumerController extends Controller
{
    // ==================== PARTNERS ====================

    function partners(Request $request)
    {
        $query = TravelPartner::where('is_active', true);
        if ($request->filled('district')) $query->where('district', $request->district);
        if ($request->filled('type')) $query->where('type', $request->type);
        $partners = $query->withCount('bookings')->orderBy('name')->paginate(20);
        return response()->json(['success' => true, 'data' => $partners]);
    }

    function partnerDetail($id)
    {
        $partner = TravelPartner::withCount('bookings')->findOrFail($id);
        abort_if(!$partner->is_active, 404, 'Partner not found');
        return response()->json(['success' => true, 'data' => $partner]);
    }

    // ==================== BOOKINGS (User-facing) ====================

    public function __construct(
        private ShopService $shopService,
    ) {}

    function createBooking(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'travel_partner_id' => 'required|exists:travel_partners,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'booked_at' => 'nullable|date',
            'offer_code' => 'nullable|string|max:32',
        ]);

        $partner = TravelPartner::findOrFail($data['travel_partner_id']);
        abort_if(!$partner->is_active, 400, 'Partner is not active');

        $data['user_id'] = $user->id;
        $data['status'] = 'pending';
        $data['booked_at'] = $data['booked_at'] ?? now();

        $booking = Booking::create($data);

        if (!empty($data['offer_code'])) {
            try {
                $this->applyOfferToBooking($user, $data['offer_code'], $booking);
            } catch (\RuntimeException $e) {
                $booking->delete();
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        // Commission on post-discount amount, canonical split: 25% reward pool
        $commission = ($booking->amount * $partner->commission_rate / 100) + ($partner->commission_fixed ?? 0);
        $rewardShare = $commission * 0.25;
        $platformShare = $commission - $rewardShare;

        $booking->update([
            'commission_earned' => $commission,
            'reward_pool_share' => $rewardShare,
        ]);

        CommissionTransaction::create([
            'booking_id' => $booking->id,
            'total_commission' => $commission,
            'reward_pool_contribution' => $rewardShare,
            'platform_revenue' => $platformShare,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'data' => $booking->load('travelPartner', 'offerRedemption.offer.business'),
        ], 201);
    }

    private function applyOfferToBooking(Booking $booking, string $code): void
    {
        $redemption = OfferRedemption::with('offer')
            ->where('code', strtoupper(trim($code)))
            ->where('user_id', $booking->user_id)
            ->where('status', 'claimed')
            ->whereNull('booking_id')
            ->whereNull('consumed_at')
            ->first();

        if (!$redemption) {
            throw new \RuntimeException('Invalid, used, or not-yours offer code.');
        }

        $offer = $redemption->offer;
        if (!$offer || !$offer->isActive()) {
            throw new \RuntimeException('This offer is no longer valid.');
        }

        $discount = 0;
        if ($offer->offer_type === 'percentage_off' && $offer->discount_value > 0) {
            $discountValue = (float)$offer->discount_value;
            if ($discountValue <= 100) {
                $discount = $booking->amount * $discountValue / 100;
            }
        } elseif ($offer->offer_type === 'fixed_off' && $offer->discount_value > 0) {
            $discount = (float)$offer->discount_value;
        }
        $discount = min($discount, $booking->amount);
        $discount = round($discount, 2);

        $redemption->update([
            'booking_id' => $booking->id,
            'discount_amount' => $discount,
            'applied_at' => now(),
        ]);

        $booking->update([
            'discount_amount' => $discount,
            'amount' => $booking->amount - $discount,
        ]);
    }

    function myBookings(Request $request)
    {
        $bookings = Booking::with('travelPartner', 'offerRedemption.offer.business')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json(['success' => true, 'data' => $bookings]);
    }

    function cancelBooking(Request $request, Booking $booking)
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id) {
            abort(403, 'Not your booking.');
        }
        if (!$booking->isPending()) {
            return response()->json(['message' => 'Only pending bookings can be cancelled.'], 422);
        }

        $booking->update(['status' => 'cancelled']);

        $this->releaseOfferFromBooking($booking);
        if ($booking->shopCode) {
            $this->shopService->releaseFromBooking($booking->shopCode);
        }

        return response()->json(['success' => true, 'message' => 'Booking cancelled.']);
    }

    function removeCoupon(Request $request, Booking $booking)
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id) {
            abort(403, 'Not your booking.');
        }
        if (!$booking->isPending()) {
            return response()->json(['message' => 'Only pending bookings can have their code removed.'], 422);
        }

        if ($booking->offerRedemption) {
            $this->releaseOfferFromBooking($booking);
            return response()->json(['success' => true, 'message' => 'Offer code removed from booking.']);
        }

        if ($booking->shopCode) {
            $this->shopService->releaseFromBooking($booking->shopCode);
            return response()->json(['success' => true, 'message' => 'Coupon removed.']);
        }

        return response()->json(['message' => 'No code applied to this booking.'], 422);
    }

    private function releaseOfferFromBooking(Booking $booking): void
    {
        $redemption = OfferRedemption::where('booking_id', $booking->id)->first();
        if (!$redemption) return;

        if ($booking->discount_amount > 0) {
            $booking->update([
                'amount' => $booking->amount + $booking->discount_amount,
                'discount_amount' => 0,
            ]);
        }

        $redemption->update([
            'booking_id' => null,
            'discount_amount' => null,
            'applied_at' => null,
        ]);
    }
}