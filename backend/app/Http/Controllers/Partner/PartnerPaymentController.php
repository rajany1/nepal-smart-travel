<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\OfferRedemption;
use App\Models\PartnerPayment;
use App\Models\PartnerWallet;
use App\Models\PartnerWithdrawal;
use App\Models\PaymentTransaction;
use App\Models\GameSetting;
use App\Services\PaymentGatewayService;
use App\Services\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartnerPaymentController extends Controller
{
    private PaymentProcessor $paymentProcessor;

    public function __construct(PaymentProcessor $paymentProcessor)
    {
        $this->paymentProcessor = $paymentProcessor;
    }

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

        // Get withdrawal limits
        $limits = [
            'esewa' => [
                'min' => (float) GameSetting::getValue('payout_min_esewa', 100),
                'max' => (float) GameSetting::getValue('payout_max_esewa', 50000),
            ],
            'khalti' => [
                'min' => (float) GameSetting::getValue('payout_min_khalti', 100),
                'max' => (float) GameSetting::getValue('payout_max_khalti', 50000),
            ],
            'bank' => [
                'min' => (float) GameSetting::getValue('payout_min_bank', 500),
                'max' => (float) GameSetting::getValue('payout_max_bank', 500000),
            ],
        ];

        return view('partner.wallet', compact('wallet', 'payments', 'withdrawals', 'totalPending', 'limits'));
    }

    private function backfillOfferEarnings($partner, $wallet): void
    {
        // Use lock to prevent race conditions
        $lock = \Illuminate\Support\Facades\Cache::lock("backfill-partner-{$partner->id}", 10);
        if (!$lock->get()) {
            return; // Another process is doing it
        }

        try {
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
        } finally {
            $lock->release();
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
                Log::error('Partner payment verification failed', ['error' => $e->getMessage(), 'code' => $code]);
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
                $ledger = app(\App\Services\FinancialLedgerService::class);
                $ledger->creditPartnerWallet(
                    partnerId: $partner->id,
                    amount: (float) $redemption->partner_earnings,
                    type: 'offer_earning',
                    description: "Offer code redeemed by user",
                    referenceType: 'OfferRedemption',
                    referenceId: $redemption->id,
                    metadata: [
                        'offer_id' => $redemption->offer_id,
                        'user_id' => $redemption->user_id,
                        'code' => $code,
                    ],
                    idempotencyKey: "offer-redeem:{$redemption->id}",
                );
            }

            DB::commit();
            return back()->with('success', 'Offer code redeemed! Rs. ' . number_format($redemption->partner_earnings ?? 0, 2) . ' credited to wallet!');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Offer redemption failed', ['error' => $e->getMessage(), 'code' => $code]);
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
            'account_detail' => 'required|string|max:255', // Increased length for bank details
            '_idempotency_key' => 'sometimes|string|max:100',
        ]);

        $amount = (float) $request->amount;
        $method = $request->method;

        // Check limits from settings
        $minKey = 'payout_min_' . $method;
        $maxKey = 'payout_max_' . $method;
        $minAmount = (float) GameSetting::getValue($minKey, 100);
        $maxAmount = (float) GameSetting::getValue($maxKey, 50000);

        if ($amount < $minAmount) {
            return back()->withErrors(['amount' => "Minimum withdrawal for {$method} is Rs. {$minAmount}."]);
        }

        if ($amount > $maxAmount) {
            return back()->withErrors(['amount' => "Maximum withdrawal for {$method} is Rs. {$maxAmount}."]);
        }

        $wallet = PartnerWallet::getForPartner($partner->id);

        if (!$wallet->canWithdraw($amount)) {
            return back()->withErrors(['amount' => 'Insufficient balance.']);
        }

        // Check pending withdrawals limit
        $maxPending = (int) GameSetting::getValue('max_pending_partner_withdrawals', 3);
        $pendingCount = PartnerWithdrawal::where('partner_id', $partner->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount >= $maxPending) {
            return back()->withErrors(['amount' => "You have {$maxPending} pending withdrawals. Please wait for them to complete."]);
        }

        // Check partner daily/monthly limits
        $limitCheck = $this->paymentProcessor->checkPartnerLimits($partner->id, $method, $amount);
        if (!$limitCheck['allowed']) {
            return back()->withErrors(['amount' => $limitCheck['message']]);
        }

        // Parse account details based on method
        $accountDetails = $this->parseAccountDetails($method, $request->account_detail);

        // Validate account details
        $validation = $this->validateAccountDetails($method, $accountDetails);
        if (!$validation['valid']) {
            return back()->withErrors(['account_detail' => $validation['error']]);
        }

        // Generate idempotency key
        $idempotencyKey = $request->input('_idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'pwithdrawal-' . $partner->id . '-' . $method . '-' . $amount . '-' . now()->format('YmdHis');

        // Check if already processed
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)
            ->where('partner_id', $partner->id)
            ->first();
        if ($existing) {
            return back()->with('success', 'Withdrawal request already submitted.');
        }

        DB::beginTransaction();
        try {
            $ledger = app(\App\Services\FinancialLedgerService::class);
            $ledger->debitPartnerWallet(
                partnerId: $partner->id,
                amount: $amount,
                type: 'withdrawal',
                description: "Partner withdrawal request",
                idempotencyKey: $idempotencyKey . '-wallet',
            );

            $withdrawal = PartnerWithdrawal::create([
                'partner_id' => $partner->id,
                'amount' => $amount,
                'method' => $method,
                'account_detail' => $request->account_detail,
                'status' => 'pending',
            ]);

            // Create payment transaction record
            PaymentTransaction::create([
                'reference_id' => 'PTR-' . $withdrawal->id . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
                'method' => $method,
                'amount_npr' => $amount,
                'account_details' => $accountDetails,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'partner_id' => $partner->id,
                'partner_withdrawal_id' => $withdrawal->id,
                'metadata' => [
                    'account_detail_raw' => $request->account_detail,
                ],
            ]);

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                $user,
                'partner.withdrawal.requested',
                'partner_withdrawal',
                $withdrawal->id,
                "Partner requested withdrawal of Rs. " . number_format($amount, 2) . " via {$method}",
                [
                    'partner_id' => $partner->id,
                    'amount' => $amount,
                    'method' => $method,
                ]
            );

            DB::commit();
            return back()->with('success', 'Withdrawal request submitted. It will be processed within 24-48 hours.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner withdrawal request failed', ['error' => $e->getMessage(), 'partner_id' => $partner->id]);
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
            $ledger = app(\App\Services\FinancialLedgerService::class);
            $ledger->creditPartnerWallet(
                partnerId: $partner->id,
                amount: (float) $withdrawal->amount,
                type: 'adjustment',
                description: "Partner withdrawal cancelled - refund",
                referenceType: 'PartnerWithdrawal',
                referenceId: $withdrawal->id,
                idempotencyKey: "pwithdrawal-cancel:{$withdrawal->id}",
            );

            $withdrawal->update(['status' => 'rejected']);

            // Update payment transaction
            PaymentTransaction::where('partner_withdrawal_id', $withdrawal->id)
                ->where('partner_id', $partner->id)
                ->update([
                    'status' => 'rejected',
                    'failure_reason' => 'Cancelled by partner',
                    'completed_at' => now(),
                ]);

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                $user,
                'partner.withdrawal.cancelled',
                'partner_withdrawal',
                $withdrawal->id,
                "Partner cancelled withdrawal of Rs. " . number_format($withdrawal->amount, 2),
                ['partner_id' => $partner->id, 'amount' => $withdrawal->amount]
            );

            DB::commit();
            return back()->with('success', 'Withdrawal cancelled. Amount refunded.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner withdrawal cancel failed', ['error' => $e->getMessage()]);
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
            '_idempotency_key' => 'sometimes|string|max:100',
        ]);

        $amount = (float) $request->amount;
        $reference = 'topup-' . $partner->id . '-' . strtoupper(\Illuminate\Support\Str::random(6));
        $idempotencyKey = $request->input('_idempotency_key')
            ?? 'topup-' . $partner->id . '-' . $amount . '-' . now()->format('YmdHis');

        // Check if already processed
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)
            ->where('partner_id', $partner->id)
            ->first();
        if ($existing) {
            return redirect()->route('partner.wallet')->with('success', 'Top-up already processed.');
        }

        $service = new PaymentGatewayService();

        if ($request->gateway === 'esewa') {
            $form = $service->eSewaForm($amount, $reference, route('partner.topup.callback', ['gateway' => 'esewa']), route('partner.topup.callback', ['gateway' => 'esewa']));
            // Store pending top-up record
            PaymentTransaction::create([
                'reference_id' => $reference,
                'method' => 'esewa',
                'amount_npr' => $amount,
                'account_details' => [],
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'partner_id' => $partner->id,
                'metadata' => ['type' => 'topup'],
            ]);
            return view('partner.gateway_redirect', ['html' => $form]);
        }

        $result = $service->initiateKhalti($amount, $reference, route('partner.topup.callback', ['gateway' => 'khalti']));
        if (!$result['success']) {
            return back()->withErrors(['payment' => $result['message']]);
        }

        // Store pending top-up record
        PaymentTransaction::create([
            'reference_id' => $reference,
            'method' => 'khalti',
            'amount_npr' => $amount,
            'account_details' => [],
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'partner_id' => $partner->id,
            'metadata' => ['type' => 'topup', 'pidx' => $result['pidx']],
        ]);

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

            $service = app(PaymentGatewayService::class);
            $verified = $service->verifyESewa(
                $data['product_code'] ?? '',
                (float) ($data['total_amount'] ?? 0),
                $data['transaction_id'] ?? '',
                $data['transaction_uuid'] ?? '',
            );

            if (!$verified['success']) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => $verified['message']]);
            }

            // CRITICAL FIX: Verify amount matches initiated amount
            $initiatedAmount = (float) $data['total_amount'] ?? 0;
            $txn = PaymentTransaction::where('reference_id', $data['transaction_uuid'] ?? '')
                ->where('partner_id', $partner->id)
                ->where('status', 'pending')
                ->first();

            if ($txn && abs($txn->amount_npr - $initiatedAmount) > 0.01) {
                Log::warning('Top-up amount mismatch', [
                    'partner_id' => $partner->id,
                    'initiated' => $txn->amount_npr,
                    'received' => $initiatedAmount,
                    'reference' => $data['transaction_uuid'] ?? '',
                ]);
                return redirect()->route('partner.wallet')->withErrors(['payment' => 'Amount mismatch detected. Contact support.']);
            }

            $this->creditTopUp($partner->id, $initiatedAmount, 'esewa', $verified['transaction_id']);

            // Update payment transaction
            if ($txn) {
                $txn->update([
                    'status' => 'completed',
                    'gateway_transaction_id' => $verified['transaction_id'],
                    'gateway_response' => $verified,
                    'completed_at' => now(),
                ]);
            }

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                $user,
                'partner.topup.completed',
                'partner_topup',
                $partner->id,
                "Partner top-up of Rs. " . number_format($initiatedAmount, 2) . " via eSewa",
                ['partner_id' => $partner->id, 'amount' => $initiatedAmount, 'gateway' => 'esewa']
            );

            return redirect()->route('partner.wallet')->with('success', 'Rs. ' . number_format($initiatedAmount, 2) . ' added to your wallet!');
        }

        if ($gateway === 'khalti') {
            $pidx = $request->get('pidx');
            $service = app(PaymentGatewayService::class);
            try {
                $verified = $service->verifyKhalti($pidx);
            } catch (\Throwable $e) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => 'Khalti verification failed.']);
            }

            if (!$verified['success']) {
                return redirect()->route('partner.wallet')->withErrors(['payment' => $verified['message']]);
            }

            // CRITICAL FIX: Verify amount matches initiated amount
            $initiatedAmount = (float) ($verified['amount'] ?? 0);
            $txn = PaymentTransaction::where('metadata->pidx', $pidx)
                ->where('partner_id', $partner->id)
                ->where('status', 'pending')
                ->first();

            if ($txn && abs($txn->amount_npr - $initiatedAmount) > 0.01) {
                Log::warning('Top-up amount mismatch (Khalti)', [
                    'partner_id' => $partner->id,
                    'initiated' => $txn->amount_npr,
                    'received' => $initiatedAmount,
                    'pidx' => $pidx,
                ]);
                return redirect()->route('partner.wallet')->withErrors(['payment' => 'Amount mismatch detected. Contact support.']);
            }

            $this->creditTopUp($partner->id, $initiatedAmount, 'khalti', $verified['transaction_id']);

            // Update payment transaction
            if ($txn) {
                $txn->update([
                    'status' => 'completed',
                    'gateway_transaction_id' => $verified['transaction_id'],
                    'gateway_response' => $verified,
                    'completed_at' => now(),
                ]);
            }

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                $user,
                'partner.topup.completed',
                'partner_topup',
                $partner->id,
                "Partner top-up of Rs. " . number_format($initiatedAmount, 2) . " via Khalti",
                ['partner_id' => $partner->id, 'amount' => $initiatedAmount, 'gateway' => 'khalti']
            );

            return redirect()->route('partner.wallet')->with('success', 'Rs. ' . number_format($initiatedAmount, 2) . ' added to your wallet!');
        }

        return redirect()->route('partner.wallet')->withErrors(['payment' => 'Invalid gateway.']);
    }

    private function creditTopUp(int $partnerId, float $amount, string $gateway, string $transactionId): void
    {
        $ledger = app(\App\Services\FinancialLedgerService::class);
        $ledger->creditPartnerWallet(
            partnerId: $partnerId,
            amount: $amount,
            type: 'topup',
            description: "Partner top-up via {$gateway}",
            metadata: [
                'gateway' => $gateway,
                'gateway_transaction_id' => $transactionId,
            ],
            idempotencyKey: "topup:{$partnerId}:{$transactionId}",
        );
    }

    private function parseAccountDetails(string $method, string $rawDetail): array
    {
        // For eSewa/Khalti: expect phone number
        // For bank: expect "bank_name|account_number|account_name" or JSON
        if (in_array($method, ['esewa', 'khalti'])) {
            return ['phone' => $rawDetail];
        }

        // Try to parse as JSON first
        $decoded = json_decode($rawDetail, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Parse pipe-separated format
        $parts = explode('|', $rawDetail);
        if (count($parts) >= 3) {
            return [
                'bank_name' => trim($parts[0]),
                'account_number' => trim($parts[1]),
                'account_name' => trim($parts[2]),
            ];
        }

        // Fallback: treat as bank name only
        return ['bank_name' => $rawDetail];
    }

    private function validateAccountDetails(string $method, array $details): array
    {
        switch ($method) {
            case 'esewa':
            case 'khalti':
                if (empty($details['phone'])) {
                    return ['valid' => false, 'error' => ucfirst($method) . ' phone number is required'];
                }
                if (!preg_match('/^(984|985|986|987|988|989)\d{8}$/', $details['phone'])) {
                    return ['valid' => false, 'error' => 'Invalid ' . ucfirst($method) . ' phone number'];
                }
                break;

            case 'bank':
                if (empty($details['bank_name']) || empty($details['account_number']) || empty($details['account_name'])) {
                    return ['valid' => false, 'error' => 'Bank name, account number, and account name are required'];
                }
                if (strlen($details['account_number']) < 10) {
                    return ['valid' => false, 'error' => 'Invalid bank account number'];
                }
                break;
        }

        return ['valid' => true];
    }
}