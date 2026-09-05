<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayment;
use App\Models\PartnerWallet;
use App\Models\PartnerWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerPaymentController extends Controller
{
    public function wallet()
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $wallet = PartnerWallet::getForPartner($partner->id);
        $payments = PartnerPayment::where('partner_id', $partner->id)
            ->where('status', 'completed')
            ->with('user')
            ->latest()
            ->paginate(15);

        $withdrawals = PartnerWithdrawal::where('partner_id', $partner->id)
            ->latest()
            ->paginate(10);

        $totalPending = PartnerWithdrawal::where('partner_id', $partner->id)->pending()->sum('amount');

        return view('partner.wallet', compact('wallet', 'payments', 'withdrawals', 'totalPending'));
    }

    public function scanPage()
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        return view('partner.payments.scan');
    }

    public function verifyCode(Request $request)
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $request->validate([
            'redeem_code' => 'required|string',
        ]);

        $code = strtoupper(trim($request->redeem_code));

        $payment = PartnerPayment::where('redeem_code', $code)
            ->where('partner_id', $partner->id)
            ->where('status', 'pending')
            ->first();

        if (!$payment) {
            return back()->withErrors(['redeem_code' => 'Invalid or already used code.']);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => 'expired']);
            return back()->withErrors(['redeem_code' => 'This code has expired.']);
        }

        DB::beginTransaction();
        try {
            $payment->markCompleted($user);
            DB::commit();

            return back()->with('success', "Rs. {$payment->partner_amount} credited to your wallet!");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['redeem_code' => 'Error processing payment. Try again.']);
        }
    }

    public function paymentHistory()
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $payments = PartnerPayment::where('partner_id', $partner->id)
            ->with('user')
            ->latest()
            ->paginate(20);

        return view('partner.payments.history', compact('payments'));
    }

    public function requestWithdrawal(Request $request)
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $request->validate([
            'amount' => 'required|numeric|min:100',
            'method' => 'required|in:esewa,khalti,bank',
            'account_detail' => 'required|string|max:100',
        ]);

        $wallet = PartnerWallet::getForPartner($partner->id);

        if (!$wallet->canWithdraw((float) $request->amount)) {
            return back()->withErrors(['amount' => 'Insufficient balance.']);
        }

        DB::beginTransaction();
        try {
            $wallet->debit((float) $request->amount);

            PartnerWithdrawal::create([
                'partner_id' => $partner->id,
                'amount' => $request->amount,
                'method' => $request->method,
                'account_detail' => $request->account_detail,
                'status' => 'pending',
            ]);

            DB::commit();
            return back()->with('success', 'Withdrawal request submitted.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['amount' => 'Error processing withdrawal.']);
        }
    }

    public function cancelWithdrawal(PartnerWithdrawal $withdrawal)
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner || $withdrawal->partner_id !== $partner->id) abort(403);

        if ($withdrawal->status !== 'pending') {
            return back()->withErrors(['error' => 'Cannot cancel.']);
        }

        DB::beginTransaction();
        try {
            $wallet = PartnerWallet::getForPartner($partner->id);
            $wallet->credit((float) $withdrawal->amount);

            $withdrawal->update(['status' => 'rejected']);
            DB::commit();

            return back()->with('success', 'Withdrawal cancelled. Amount refunded.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Error cancelling.']);
        }
    }
}
