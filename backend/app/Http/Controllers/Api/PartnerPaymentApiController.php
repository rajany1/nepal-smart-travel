<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayment;
use App\Models\PartnerWallet;
use App\Models\TravelPartner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerPaymentApiController extends Controller
{
    // List partners available for payment
    public function partners()
    {
        $partners = TravelPartner::where('verification_status', 'verified')
            ->where('is_active', true)
            ->select('id', 'name', 'type', 'district', 'address', 'phone')
            ->get();

        return response()->json(['success' => true, 'data' => $partners]);
    }

    // Initiate payment to a partner
    public function initiate(Request $request)
    {
        $request->validate([
            'partner_id' => 'required|exists:travel_partners,id',
            'amount' => 'required|numeric|min:10',
            'payment_method' => 'required|in:esewa,khalti',
            'description' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $partner = TravelPartner::where('id', $request->partner_id)
            ->where('verification_status', 'verified')
            ->firstOrFail();

        $amount = (float) $request->amount;
        $commissionPercent = (float) (setting('partner_commission_percent', 10));
        $commissionAmount = round($amount * $commissionPercent / 100, 2);
        $partnerAmount = $amount - $commissionAmount;

        $payment = PartnerPayment::create([
            'partner_id' => $partner->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'payment_method' => $request->payment_method,
            'commission_percent' => $commissionPercent,
            'commission_amount' => $commissionAmount,
            'partner_amount' => $partnerAmount,
            'description' => $request->description,
            'status' => 'pending',
        ]);

        // Payment stays pending until gateway callback confirms it
        // Partner wallet is credited ONLY after verified callback
        return response()->json([
            'success' => true,
            'message' => 'Payment initiated. Complete payment via gateway.',
            'data' => [
                'payment_id' => $payment->id,
                'redeem_code' => $payment->redeem_code,
                'qr_data' => $payment->qr_data,
                'amount' => $payment->amount,
                'partner_amount' => $payment->partner_amount,
                'partner_name' => $partner->name,
                'expires_at' => $payment->expires_at,
                'payment_method' => $payment->payment_method,
                'status' => 'pending',
            ],
        ]);
    }

    // My payment history
    public function myPayments(Request $request)
    {
        $payments = PartnerPayment::where('user_id', Auth::id())
            ->with('partner:id,name,type')
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $payments]);
    }

    // Get single payment detail with QR
    public function show(PartnerPayment $payment)
    {
        if ($payment->user_id !== Auth::id()) abort(403);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'redeem_code' => $payment->redeem_code,
                'qr_data' => $payment->qr_data,
                'amount' => $payment->amount,
                'partner_amount' => $payment->partner_amount,
                'status' => $payment->status,
                'partner_name' => $payment->partner->name,
                'expires_at' => $payment->expires_at,
                'created_at' => $payment->created_at,
            ],
        ]);
    }
}
