<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\OfferRedemption;
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

        $this->backfillOfferEarnings($partner, $wallet);

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

    private function backfillOfferEarnings($partner, $wallet): void
    {
        $totalUsedEarnings = (float) OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))
            ->where('status', 'used')
            ->whereHas('offer', fn($q) => $q->where('price_xp', '>', 0))
            ->sum('partner_earnings');

        $totalFromQrPayments = (float) PartnerPayment::where('partner_id', $partner->id)
            ->where('status', 'completed')
            ->sum('partner_amount');

        $expectedBalance = $totalUsedEarnings + $totalFromQrPayments;
        $currentBalance = (float) $wallet->balance;

        if (abs($expectedBalance - $currentBalance) > 0.01) {
            $wallet->update([
                'balance' => round($expectedBalance, 2),
                'total_earned' => round($expectedBalance, 2),
            ]);
        }
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

        if ($payment) {
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

        $offerIds = $partner->offers()->pluck('id');
        $redemption = OfferRedemption::whereIn('offer_id', $offerIds)
            ->where('code', $code)
            ->where('status', 'claimed')
            ->first();

        if (!$redemption) {
            return back()->withErrors(['redeem_code' => 'Invalid or already used code.']);
        }

        DB::beginTransaction();
        try {
            $redemption->update([
                'consumed_at' => now(),
                'used_at' => now(),
                'status' => 'used',
            ]);

            if ((float) ($redemption->partner_earnings ?? 0) > 0) {
                $wallet = PartnerWallet::getForPartner($partner->id);
                $wallet->credit((float) $redemption->partner_earnings);
            }

            DB::commit();
            return back()->with('success', 'Offer code redeemed! Rs. ' . number_format($redemption->partner_earnings ?? 0, 2) . ' credited to wallet!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['redeem_code' => 'Error processing. Try again.']);
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

    public function topUpPage()
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $wallet = PartnerWallet::getForPartner($partner->id);
        return view('partner.wallet_topup', compact('wallet'));
    }

    public function initiateTopUp(Request $request)
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $request->validate([
            'amount' => 'required|numeric|min:100|max:100000',
            'gateway' => 'required|in:esewa,khalti',
        ]);

        $amount = (float) $request->amount;
        $reference = 'topup-' . $partner->id . '-' . strtoupper(\Illuminate\Support\Str::random(6));

        $service = new \App\Services\PaymentGatewayService();

        if ($request->gateway === 'esewa') {
            $form = $service->eSewaForm($amount, $reference, route('partner.topup.callback', ['gateway' => 'esewa']), route('partner.topup.callback', ['gateway' => 'esewa']));
            return view('partner.gateway_redirect', ['html' => $form]);
        }

        $result = $service->initiateKhalti($amount, $reference, route('partner.topup.callback', ['gateway' => 'khalti']));
        if (!$result['success']) {
            return back()->withErrors(['payment' => $result['message']]);
        }

        return redirect()->away($result['payment_url']);
    }

    public function topUpCallback(Request $request)
    {
        $user = Auth::user();
        $partner = $user->business;
        if (!$partner) abort(403);

        $gateway = $request->route('gateway');

        if ($gateway === 'esewa') {
            try {
                $data = json_decode(base64_decode($request->get('data', '')), true);
            } catch (\Throwable $e) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => 'Invalid payment callback.']);
            }

            if (!is_array($data)) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => 'Invalid payment callback.']);
            }

            $service = app(\App\Services\PaymentGatewayService::class);
            $verified = $service->verifyESewa(
                $data['product_code'] ?? '',
                (float) ($data['total_amount'] ?? 0),
                $data['transaction_id'] ?? '',
                $data['transaction_uuid'] ?? '',
            );

            if (!$verified['success']) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => $verified['message']]);
            }

            $this->creditTopUp($partner->id, (float) $data['total_amount'], 'esewa', $verified['transaction_id']);
            return redirect()->route('partner.wallet')->with('success', 'Rs. ' . number_format((float) $data['total_amount'], 2) . ' added to your wallet!');
        }

        if ($gateway === 'khalti') {
            $pidx = $request->get('pidx');
            $service = app(\App\Services\PaymentGatewayService::class);
            try {
                $verified = $service->verifyKhalti($pidx);
            } catch (\Throwable $e) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => 'Khalti verification failed.']);
            }

            if (!$verified['success']) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => $verified['message']]);
            }

            $this->creditTopUp($partner->id, (float) $verified['amount'], 'khalti', $verified['transaction_id']);
            return redirect()->route('partner.wallet')->with('success', 'Rs. ' . number_format((float) $verified['amount'], 2) . ' added to your wallet!');
        }

        return redirect()->route('partner.wallet')->withErrors(['payment' => 'Invalid gateway.']);
    }

    private function creditTopUp(int $partnerId, float $amount, string $gateway, string $transactionId): void
    {
        $wallet = PartnerWallet::getForPartner($partnerId);
        $wallet->credit($amount);
    }
}
