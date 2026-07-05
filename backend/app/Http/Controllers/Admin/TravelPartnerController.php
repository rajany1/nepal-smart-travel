<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelPartner;
use App\Models\Booking;
use App\Models\CommissionTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TravelPartnerController extends Controller
{
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
        $partners = TravelPartner::withCount('bookings')->orderBy('name')->paginate(20);
        return view('admin.travel_partners', compact('partners'));
    }

    public function partnerStore(Request $request)
    {
        $this->requireAdmin($request);
        TravelPartner::create($request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:hotel,vehicle_rental,guide,adventure',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_fixed' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]));
        return redirect()->route('admin.travel-partners')->with('success', 'Partner created.');
    }

    public function partnerUpdate(Request $request, TravelPartner $travelPartner)
    {
        $this->requireAdmin($request);
        $travelPartner->update($request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:hotel,vehicle_rental,guide,adventure',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'district' => 'nullable|string|max:100',
            'commission_rate' => 'required|numeric|min:0|max:100',
            'commission_fixed' => 'required|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]));
        return redirect()->route('admin.travel-partners')->with('success', 'Partner updated.');
    }

    public function bookings(Request $request)
    {
        $this->requireAdmin($request);
        $status = $request->get('status');
        $query = Booking::with(['travelPartner', 'user']);
        if ($status) $query->where('status', $status);
        $bookings = $query->orderBy('created_at', 'desc')->paginate(20);
        $partners = TravelPartner::active()->orderBy('name')->get();
        return view('admin.bookings', compact('bookings', 'partners', 'status'));
    }

    public function bookingStore(Request $request)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'travel_partner_id' => 'required|exists:travel_partners,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'customer_email' => 'nullable|email|max:255',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $partner = TravelPartner::findOrFail($data['travel_partner_id']);
        $commission = ($data['amount'] * $partner->commission_rate / 100) + $partner->commission_fixed;
        $rewardShare = $commission * 0.25;
        $platformShare = $commission - $rewardShare;

        $booking = Booking::create(array_merge($data, [
            'commission_earned' => $commission,
            'reward_pool_share' => $rewardShare,
            'booked_at' => now(),
            'status' => 'pending',
        ]));

        CommissionTransaction::create([
            'booking_id' => $booking->id,
            'total_commission' => $commission,
            'reward_pool_contribution' => $rewardShare,
            'platform_revenue' => $platformShare,
            'status' => 'pending',
        ]);

        return redirect()->route('admin.bookings')->with('success', 'Booking created. Commission: Rs. ' . number_format($commission, 2));
    }

    public function bookingConfirm(Request $request, Booking $booking)
    {
        $this->requireAdmin($request);
        $booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        return redirect()->route('admin.bookings')->with('success', 'Booking confirmed.');
    }

    public function bookingComplete(Request $request, Booking $booking)
    {
        $this->requireAdmin($request);
        $booking->update(['status' => 'completed', 'completed_at' => now()]);
        if ($booking->commissionTransaction) {
            $booking->commissionTransaction->update(['status' => 'paid', 'paid_at' => now()]);
        }
        return redirect()->route('admin.bookings')->with('success', 'Booking completed. Commission released.');
    }

    public function bookingCancel(Request $request, Booking $booking)
    {
        $this->requireAdmin($request);
        $booking->update(['status' => 'cancelled']);
        if ($booking->commissionTransaction) {
            $booking->commissionTransaction->update(['status' => 'cancelled']);
        }
        return redirect()->route('admin.bookings')->with('success', 'Booking cancelled.');
    }
}
