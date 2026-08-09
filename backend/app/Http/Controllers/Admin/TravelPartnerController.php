<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelPartner;
use App\Models\Booking;
use App\Models\CommissionTransaction;
use App\Models\OfferRedemption;
use App\Models\User;
use App\Services\ModeratorService;
use App\Services\ShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TravelPartnerController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
        private ShopService $shopService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) abort(403, 'Unauthorized');
        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) abort(403);
        }
    }

    public function partners(Request $request)
    {
        $this->requireAdmin($request);
        $partners = TravelPartner::withCount('bookings')
            ->orderByRaw("CASE WHEN verification_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(20);
        return view('admin.travel_partners', compact('partners'));
    }

    public function verifyPartner(TravelPartner $travelPartner)
    {
        $this->requireAdmin(request());
        $travelPartner->update([
            'verification_status' => 'verified',
            'rejected_reason' => null,
        ]);
        $this->moderatorService->log(Auth::user(), 'business.verified', 'travel_partner', $travelPartner->id, 'Verified business: ' . $travelPartner->name);
        return back()->with('success', 'Business verified: ' . $travelPartner->name);
    }

    public function rejectPartner(Request $request, TravelPartner $travelPartner)
    {
        $this->requireAdmin($request);
        $travelPartner->update([
            'verification_status' => 'rejected',
            'rejected_reason' => $request->input('reason'),
        ]);
        $this->moderatorService->log(Auth::user(), 'business.rejected', 'travel_partner', $travelPartner->id, 'Rejected business: ' . $travelPartner->name . ' — ' . $request->input('reason'));
        return back()->with('success', 'Business rejected.');
    }

    private const BUSINESS_TYPES = 'hotel,restaurant,cafe,shop,vehicle_rental,guide,adventure,other';

    public function partnerStore(Request $request)
    {
        $this->requireAdmin($request);
        $partner = TravelPartner::create($request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . self::BUSINESS_TYPES,
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'value_npr' => 'nullable|numeric|min:0|max:9999999.99',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_fixed' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]));
        $this->moderatorService->log(Auth::user(), 'travel-partner.created', 'travel_partner', $partner->id, 'Created partner: ' . $partner->name);
        return redirect()->route('admin.travel-partners')->with('success', 'Partner created.');
    }

    public function partnerUpdate(Request $request, TravelPartner $travelPartner)
    {
        $this->requireAdmin($request);
        $travelPartner->update($request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . self::BUSINESS_TYPES,
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'value_npr' => 'nullable|numeric|min:0|max:9999999.99',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_fixed' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]));
        $this->moderatorService->log(Auth::user(), 'travel-partner.updated', 'travel_partner', $travelPartner->id, 'Updated partner: ' . $travelPartner->name);
        return redirect()->route('admin.travel-partners')->with('success', 'Partner updated.');
    }

    public function bookings(Request $request)
    {
        $this->requireAdmin($request);
        $status = $request->get('status');
        $search = $request->get('search');

        $query = Booking::with(['travelPartner', 'user', 'shopCode.shopItem', 'offerRedemption.offer.business', 'commissionTransaction']);

        if ($status) $query->where('status', $status);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhereHas('travelPartner', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        $stats = [
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'total_revenue' => Booking::whereIn('status', ['confirmed', 'completed'])->sum('amount'),
            'total_commission' => Booking::whereIn('status', ['confirmed', 'completed'])->sum('commission_earned'),
            'total_reward_pool' => Booking::whereIn('status', ['confirmed', 'completed'])->sum('reward_pool_share'),
            'total_platform_revenue' => CommissionTransaction::where('status', 'paid')->sum('platform_revenue'),
            'total_discounts' => Booking::sum('discount_amount'),
        ];

        $partners = TravelPartner::active()->orderBy('name')->get();
        $users = User::select('id', 'name', 'email', 'phone')->orderBy('name')->get();

        $userRedemptions = OfferRedemption::with('offer.business:id,name')
            ->where('status', 'claimed')
            ->whereNull('booking_id')
            ->whereNull('consumed_at')
            ->get()
            ->groupBy('user_id')
            ->map(fn($redemptions) => $redemptions->map(fn($r) => [
                'id' => $r->id,
                'code' => $r->code,
                'offer_id' => $r->offer_id,
                'offer_name' => $r->offer?->title,
                'offer_type' => $r->offer?->offer_type,
                'discount_value' => (float)($r->offer?->discount_value ?? 0),
                'price_xp' => (int)($r->offer?->price_xp ?? 0),
                'business_id' => $r->offer?->business_id,
            ])->values())
            ->toArray();

        return view('admin.bookings', compact('bookings', 'partners', 'users', 'userRedemptions', 'status', 'search', 'stats'));
    }

    public function bookingStore(Request $request)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'travel_partner_id' => 'required|exists:travel_partners,id',
            'user_id' => 'nullable|exists:users,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'amount' => 'required|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'booked_at' => 'nullable|date',
            'offer_redemption_id' => 'nullable|exists:offer_redemptions,id',
        ]);

        $finalAmount = $data['amount'];
        $discount = 0;

        if (!empty($data['offer_redemption_id'])) {
            $redemption = OfferRedemption::with('offer')->find($data['offer_redemption_id']);
            if ($redemption && $redemption->offer) {
                if ($redemption->booking_id || $redemption->consumed_at) {
                    return back()->with('error', 'Offer code is already applied or consumed.')
                        ->withInput();
                }
                $offer = $redemption->offer;
                if ($offer->offer_type === 'percentage_off' && $offer->discount_value > 0 && $offer->discount_value <= 100) {
                    $discount = $finalAmount * (float)$offer->discount_value / 100;
                } elseif ($offer->offer_type === 'fixed_off' && $offer->discount_value > 0) {
                    $discount = (float)$offer->discount_value;
                }
            }
        }

        $discount = min($discount, $finalAmount);
        $finalAmount -= $discount;

        $partner = TravelPartner::findOrFail($data['travel_partner_id']);
        $commission = ($finalAmount * $partner->commission_rate / 100) + $partner->commission_fixed;
        $rewardShare = $commission * 0.25;
        $platformShare = $commission - $rewardShare;

        $booking = Booking::create(array_merge($data, [
            'commission_earned' => $commission,
            'reward_pool_share' => $rewardShare,
            'discount_amount' => $discount,
            'booked_at' => $data['booked_at'] ?? now(),
            'status' => 'pending',
        ]));

        if (!empty($data['offer_redemption_id'])) {
            OfferRedemption::where('id', $data['offer_redemption_id'])->update([
                'booking_id' => $booking->id,
                'discount_amount' => $discount,
                'applied_at' => now(),
            ]);
        }

        CommissionTransaction::create([
            'booking_id' => $booking->id,
            'total_commission' => $commission,
            'reward_pool_contribution' => $rewardShare,
            'platform_revenue' => $platformShare,
            'status' => 'pending',
        ]);

        $this->moderatorService->log(Auth::user(), 'booking.created', 'booking', $booking->id, 'Created booking #' . $booking->id . ' for partner: ' . ($partner->name) . ', amount: Rs. ' . number_format($finalAmount, 2));
        return redirect()->route('admin.bookings')->with('success', 'Booking created. Commission: Rs. ' . number_format($commission, 2));
    }

    public function bookingConfirm(Request $request, Booking $booking)
    {
        $this->requireAdmin($request);
        $booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        if ($booking->offerRedemption) {
            $booking->offerRedemption->update(['consumed_at' => now(), 'status' => 'used']);
        }

        if ($booking->shopCode) {
            $booking->shopCode->update(['consumed_at' => now()]);
        }

        $this->moderatorService->log(Auth::user(), 'booking.confirmed', 'booking', $booking->id, 'Confirmed booking #' . $booking->id);
        return redirect()->route('admin.bookings')->with('success', 'Booking confirmed.');
    }

    public function bookingComplete(Request $request, Booking $booking)
    {
        $this->requireAdmin($request);
        $booking->update(['status' => 'completed', 'completed_at' => now()]);
        if ($booking->commissionTransaction) {
            $booking->commissionTransaction->update(['status' => 'paid', 'paid_at' => now()]);
        }
        $this->moderatorService->log(Auth::user(), 'booking.completed', 'booking', $booking->id, 'Completed booking #' . $booking->id . ' — commission released');
        return redirect()->route('admin.bookings')->with('success', 'Booking completed. Commission released.');
    }

    public function bookingCancel(Request $request, Booking $booking)
    {
        $this->requireAdmin($request);
        $booking->update(['status' => 'cancelled']);
        if ($booking->commissionTransaction) {
            $booking->commissionTransaction->update(['status' => 'cancelled']);
        }
        if ($booking->shopCode) {
            $this->shopService->releaseFromBooking($booking->shopCode);
        }
        if ($booking->offerRedemption) {
            $redemption = $booking->offerRedemption;
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
        $this->moderatorService->log(Auth::user(), 'booking.cancelled', 'booking', $booking->id, 'Cancelled booking #' . $booking->id);
        return redirect()->route('admin.bookings')->with('success', 'Booking cancelled.');
    }
}
