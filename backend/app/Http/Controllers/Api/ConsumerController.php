<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelPartner;
use App\Models\Sponsor;
use App\Models\Booking;
use App\Models\ShopCode;
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

    // ==================== SPONSORS ====================

    function sponsors()
    {
        $sponsors = Sponsor::where('is_active', true)->withCount('shopItems')->orderBy('sort_order')->orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $sponsors]);
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
            'shop_code_id' => 'nullable|exists:shop_codes,id',
        ]);

        $partner = TravelPartner::findOrFail($data['travel_partner_id']);
        abort_if(!$partner->is_active, 400, 'Partner is not active');

        $data['user_id'] = $user->id;
        $data['status'] = 'pending';
        $commission = ($data['amount'] ?? 0) * ($partner->commission_rate / 100) + ($partner->commission_fixed ?? 0);
        $data['commission_earned'] = $commission;
        $data['reward_pool_share'] = $commission * 0.5;

        $booking = Booking::create($data);

        if (!empty($data['shop_code_id'])) {
            $code = ShopCode::findOrFail($data['shop_code_id']);
            try {
                $this->shopService->applyToBooking($user, $code, $booking);
            } catch (\RuntimeException $e) {
                $booking->delete();
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        return response()->json(['success' => true, 'data' => $booking->load('travelPartner', 'shopCode')], 201);
    }

    function myBookings(Request $request)
    {
        $bookings = Booking::with('travelPartner', 'shopCode')
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
            return response()->json(['message' => 'Only pending bookings can have their coupon removed.'], 422);
        }
        if (!$booking->shopCode) {
            return response()->json(['message' => 'No coupon applied to this booking.'], 422);
        }

        $this->shopService->releaseFromBooking($booking->shopCode);
        return response()->json(['success' => true, 'message' => 'Coupon removed.']);
    }
}
