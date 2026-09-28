<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\CoinSetting;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalController extends Controller
{
    private PaymentProcessor $paymentProcessor;

    public function __construct(PaymentProcessor $paymentProcessor)
    {
        $this->paymentProcessor = $paymentProcessor;
    }

    /**
     * List all withdrawals with stats.
     */
    public function index(Request $request)
    {
        $query = Withdrawal::with('user:id,name,email');

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by method
        if ($request->has('method') && $request->method !== 'all') {
            $query->where('method', $request->method);
        }

        // Search by user
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $withdrawals = $query->orderByDesc('created_at')->paginate(20);

        // Stats with NPR amounts
        $coinToNpr = (float) CoinSetting::getValue('coin_to_npr_rate', 1);
        $stats = [
            'total' => Withdrawal::count(),
            'pending' => Withdrawal::where('status', 'pending')->count(),
            'processing' => Withdrawal::where('status', 'processing')->count(),
            'completed' => Withdrawal::where('status', 'completed')->count(),
            'rejected' => Withdrawal::where('status', 'rejected')->count(),
            'cancelled' => Withdrawal::where('status', 'cancelled')->count(),
            'total_amount_coins' => Withdrawal::where('status', 'completed')->sum('amount'),
            'total_amount_npr' => Withdrawal::where('status', 'completed')->sum('amount') * $coinToNpr,
            'pending_amount_coins' => Withdrawal::where('status', 'pending')->sum('amount'),
            'pending_amount_npr' => Withdrawal::where('status', 'pending')->sum('amount') * $coinToNpr,
        ];

        return view('admin.withdrawals', compact('withdrawals', 'stats', 'coinToNpr'));
    }

    /**
     * Approve withdrawal (mark as processing).
     */
    public function approve(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'error' => 'Withdrawal is not pending'], 422);
        }

        $withdrawal->update([
            'status' => 'processing',
            'admin_note' => 'Approved by admin: ' . Auth::user()->name,
        ]);

        // Update payment transaction
        PaymentTransaction::where('withdrawal_id', $withdrawal->id)
            ->update(['status' => 'processing']);

        // Audit log
        app(\App\Services\ModeratorService::class)->log(
            Auth::user(),
            'withdrawal.approved',
            'withdrawal',
            $withdrawal->id,
            "Admin approved withdrawal of {$withdrawal->amount} Coins via {$withdrawal->method}",
            ['admin_user_id' => Auth::id()]
        );

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal approved. Payment processing initiated...',
        ]);
    }

    /**
     * Actually process the payment (complete with real/mock gateway).
     * State machine: processing → completed | failed
     */
    public function processPayment(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::with('user')->findOrFail($id);

        // Strict state machine: only 'processing' can be processed
        if ($withdrawal->status !== 'processing') {
            return response()->json(['success' => false, 'error' => 'Withdrawal must be approved before processing. Current status: ' . $withdrawal->status], 422);
        }

        $paymentTxn = PaymentTransaction::where('withdrawal_id', $withdrawal->id)
            ->where('user_id', $withdrawal->user_id)
            ->first();

        if (!$paymentTxn) {
            return response()->json(['success' => false, 'error' => 'Payment transaction not found'], 404);
        }

        // Idempotency: already completed
        if ($paymentTxn->status === 'completed') {
            return response()->json(['success' => false, 'error' => 'Payment already completed'], 422);
        }

        // Process the actual payment
        $amountNpr = $this->paymentProcessor->coinsToNpr((float) $withdrawal->amount);

        $result = $this->paymentProcessor->processPayout([
            'method' => $withdrawal->method,
            'amount_npr' => $amountNpr,
            'account_details' => $withdrawal->account_details,
            'reference_id' => $paymentTxn->reference_id,
            'idempotency_key' => $paymentTxn->idempotency_key,
            'metadata' => [
                'withdrawal_id' => $withdrawal->id,
                'user_id' => $withdrawal->user_id,
                'amount_coins' => $withdrawal->amount,
                'coin_to_npr_rate' => (float) CoinSetting::getValue('coin_to_npr_rate', 1),
            ],
        ]);

        if ($result['success']) {
            $withdrawal->update([
                'status' => 'completed',
                'processed_at' => now(),
                'admin_note' => 'Payment completed: ' . ($result['message'] ?? 'Success'),
            ]);

            $paymentTxn->update([
                'status' => 'completed',
                'gateway_transaction_id' => $result['transaction_id'] ?? null,
                'gateway_response' => $result,
                'completed_at' => now(),
            ]);

            app(\App\Services\ModeratorService::class)->log(
                Auth::user(),
                'withdrawal.completed',
                'withdrawal',
                $withdrawal->id,
                "Admin completed withdrawal: {$withdrawal->amount} Coins (Rs. " . number_format($amountNpr, 2) . ") via {$withdrawal->method}. Gateway TXN: " . ($result['transaction_id'] ?? 'N/A'),
                [
                    'amount_coins' => $withdrawal->amount,
                    'amount_npr' => $amountNpr,
                    'method' => $withdrawal->method,
                    'gateway_txn_id' => $result['transaction_id'] ?? null,
                    'admin_user_id' => Auth::id(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'gateway_transaction_id' => $result['transaction_id'],
                'amount_npr' => $amountNpr,
            ]);
        } else {
            // Payment failed — restore coins to user automatically
            $paymentTxn->update([
                'status' => 'failed',
                'failure_reason' => $result['message'] ?? 'Payment failed',
                'gateway_response' => $result,
            ]);

            $withdrawal->update([
                'status' => 'failed',
                'admin_note' => 'Payment failed: ' . ($result['message'] ?? 'Unknown error'),
            ]);

            // Auto-restore coins on failure
            $ledger = app(\App\Services\FinancialLedgerService::class);
            $ledger->creditCoins(
                userId: $withdrawal->user_id,
                amount: (float) $withdrawal->amount,
                type: 'withdrawal_failed',
                description: "Withdrawal payment failed — coins restored",
                metadata: [
                    'withdrawal_id' => $withdrawal->id,
                    'failure_reason' => $result['message'] ?? 'Unknown error',
                    'amount_npr' => $amountNpr,
                ],
                idempotencyKey: "wth-fail-restore:{$withdrawal->id}",
            );

            app(\App\Services\ModeratorService::class)->log(
                Auth::user(),
                'withdrawal.payment_failed',
                'withdrawal',
                $withdrawal->id,
                "Payment failed for withdrawal {$withdrawal->id}: " . ($result['message'] ?? 'Unknown error') . ". Coins restored to user.",
                [
                    'amount_coins' => $withdrawal->amount,
                    'amount_npr' => $amountNpr,
                    'method' => $withdrawal->method,
                    'error' => $result['message'],
                    'admin_user_id' => Auth::id(),
                ]
            );

            return response()->json([
                'success' => false,
                'error' => 'Payment failed: ' . ($result['message'] ?? 'Unknown error') . '. Coins have been restored to user.',
                'gateway_error_code' => $result['error_code'] ?? null,
            ], 422);
        }
    }

    /**
     * Mark withdrawal as completed (legacy - just marks without processing).
     * @deprecated Use processPayment instead
     */
    public function complete(int $id): JsonResponse
    {
        return $this->processPayment($id);
    }

    /**
     * Reject withdrawal and refund.
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $request->validate(['admin_note' => 'required|string']);

        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'rejected'])) {
            return response()->json(['success' => false, 'error' => 'Cannot reject this withdrawal'], 422);
        }

        return DB::transaction(function () use ($withdrawal, $request) {
            // Refund wallet via FinancialLedgerService (atomic + ledger-backed)
            $ledger = app(\App\Services\FinancialLedgerService::class);
            $amountNpr = $this->paymentProcessor->coinsToNpr((float) $withdrawal->amount);

            $ledger->creditCoins(
                userId: $withdrawal->user_id,
                amount: (float) $withdrawal->amount,
                type: 'admin_adjustment',
                description: "Withdrawal rejected - amount refunded",
                metadata: [
                    'withdrawal_id' => $withdrawal->id,
                    'action' => 'reject_refund',
                    'admin_note' => $request->admin_note,
                    'amount_npr' => $amountNpr,
                ],
                idempotencyKey: "wth-reject:{$withdrawal->id}",
                actorId: auth()->id(),
            );

            // Update withdrawal status
            $withdrawal->update([
                'status' => 'rejected',
                'admin_note' => $request->admin_note,
            ]);

            // Update payment transaction
            PaymentTransaction::where('withdrawal_id', $withdrawal->id)
                ->where('user_id', $withdrawal->user_id)
                ->update([
                    'status' => 'rejected',
                    'failure_reason' => 'Rejected by admin: ' . $request->admin_note,
                    'completed_at' => now(),
                ]);

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                Auth::user(),
                'withdrawal.rejected',
                'withdrawal',
                $withdrawal->id,
                "Admin rejected withdrawal of {$withdrawal->amount} Coins (Rs. " . number_format($amountNpr, 2) . "): " . $request->admin_note,
                [
                    'amount_coins' => $withdrawal->amount,
                    'amount_npr' => $amountNpr,
                    'method' => $withdrawal->method,
                    'reason' => $request->admin_note,
                    'admin_user_id' => Auth::id(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal rejected. Amount refunded to user wallet.',
            ]);
        });
    }

    /**
     * Get withdrawal details with payment transaction info.
     */
    public function show(int $id)
    {
        $withdrawal = Withdrawal::with('user:id,name,email')->findOrFail($id);
        $paymentTxn = PaymentTransaction::where('withdrawal_id', $withdrawal->id)->first();
        $amountNpr = $this->paymentProcessor->coinsToNpr((float) $withdrawal->amount);

        return response()->json([
            'withdrawal' => $withdrawal,
            'payment_transaction' => $paymentTxn,
            'amount_npr' => $amountNpr,
        ]);
    }

    /**
     * Coin settings page.
     */
    public function coinSettings()
    {
        $settings = CoinSetting::all();
        return view('admin.coin-settings', compact('settings'));
    }

    /**
     * Update coin settings.
     */
    public function updateCoinSettings(Request $request)
    {
        $settings = $request->validate([
            'ad_cpm' => 'required|numeric|min:0',
            'ad_cpc' => 'required|numeric|min:0',
            'user_share_percent' => 'required|numeric|min:0|max:100',
            'coin_to_npr_rate' => 'required|numeric|min:0.01',
            'min_withdrawal_esewa' => 'required|numeric|min:0',
            'max_withdrawal_esewa' => 'nullable|numeric|min:0',
            'min_withdrawal_khalti' => 'required|numeric|min:0',
            'max_withdrawal_khalti' => 'nullable|numeric|min:0',
            'min_withdrawal_bank' => 'required|numeric|min:0',
            'max_withdrawal_bank' => 'nullable|numeric|min:0',
            'daily_earning_cap' => 'required|numeric|min:0',
            'daily_impression_cap' => 'required|numeric|min:0',
            'impression_cooldown_minutes' => 'required|numeric|min:0',
            'max_pending_withdrawals' => 'nullable|integer|min:1|max:10',
            'withdrawal_daily_limit_esewa' => 'nullable|numeric|min:0',
            'withdrawal_monthly_limit_esewa' => 'nullable|numeric|min:0',
            'withdrawal_daily_limit_khalti' => 'nullable|numeric|min:0',
            'withdrawal_monthly_limit_khalti' => 'nullable|numeric|min:0',
            'withdrawal_daily_limit_bank' => 'nullable|numeric|min:0',
            'withdrawal_monthly_limit_bank' => 'nullable|numeric|min:0',
            'payment_simulate_failure_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        // Save ad_cpm/ad_cpc to game_settings (actual pricing)
        \App\Models\GameSetting::setValue('ad_cpm', $settings['ad_cpm']);
        \App\Models\GameSetting::setValue('ad_cpc', $settings['ad_cpc']);
        unset($settings['ad_cpm'], $settings['ad_cpc']);

        // Auto-calculate admin share from user share
        $adminShare = 100 - (float) $settings['user_share_percent'];
        $settings['admin_share_percent'] = $adminShare;

        foreach ($settings as $key => $value) {
            if ($value !== null) {
                CoinSetting::set($key, $value);
            }
        }

        return redirect()->route('admin.coin-settings')->with('success', 'Coin settings updated successfully!');
    }

    /**
     * Earnings report with NPR conversion.
     */
    public function earningsReport(Request $request)
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();
        $coinToNpr = (float) CoinSetting::getValue('coin_to_npr_rate', 1);

        $dateFrom = $request->query('from');
        $dateTo = $request->query('to');

        $stats = [
            'today' => [
                'impressions' => CoinTransaction::where('type', 'impression_earning')
                    ->whereDate('created_at', $today)->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')
                    ->whereDate('created_at', $today)->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->whereDate('created_at', $today)->sum('amount'),
                'npr_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->whereDate('created_at', $today)->sum('amount') * $coinToNpr,
                'offers_active' => \App\Models\RewardOffer::where('status', 'approved')->count(),
                'offers_claimed_today' => \App\Models\OfferRedemption::whereDate('claimed_at', $today)->count(),
                'offers_value_today' => \App\Models\OfferRedemption::whereDate('claimed_at', $today)->sum('value_npr'),
                'offers_commission_today' => \App\Models\OfferRedemption::whereDate('claimed_at', $today)->sum('admin_commission'),
                'ad_revenue_today' => \App\Models\AdRevenueLedger::whereDate('created_at', $today)->sum('gross_amount'),
                'ad_admin_share_today' => \App\Models\AdRevenueLedger::whereDate('created_at', $today)->sum('admin_share'),
                'ad_impressions_today' => \App\Models\AdImpression::whereDate('viewed_at', $today)->count(),
                'ad_clicks_today' => \App\Models\AdClick::whereDate('clicked_at', $today)->count(),
                'ad_payments_today' => \App\Models\AdPayment::where('status', 'success')
                    ->whereDate('paid_at', $today)->sum('amount'),
                'active_campaigns' => \App\Models\AdCampaign::where('status', 'active')->count(),
            ],
            'this_month' => [
                'impressions' => CoinTransaction::where('type', 'impression_earning')
                    ->where('created_at', '>=', $thisMonth)->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')
                    ->where('created_at', '>=', $thisMonth)->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->where('created_at', '>=', $thisMonth)->sum('amount'),
                'npr_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->where('created_at', '>=', $thisMonth)->sum('amount') * $coinToNpr,
                'offers_claimed_month' => \App\Models\OfferRedemption::where('claimed_at', '>=', $thisMonth)->count(),
                'offers_value_month' => \App\Models\OfferRedemption::where('claimed_at', '>=', $thisMonth)->sum('value_npr'),
                'offers_commission_month' => \App\Models\OfferRedemption::where('claimed_at', '>=', $thisMonth)->sum('admin_commission'),
                'ad_revenue_month' => \App\Models\AdRevenueLedger::where('created_at', '>=', $thisMonth)->sum('gross_amount'),
                'ad_admin_share_month' => \App\Models\AdRevenueLedger::where('created_at', '>=', $thisMonth)->sum('admin_share'),
                'ad_impressions_month' => \App\Models\AdImpression::where('viewed_at', '>=', $thisMonth)->count(),
                'ad_clicks_month' => \App\Models\AdClick::where('clicked_at', '>=', $thisMonth)->count(),
                'ad_payments_month' => \App\Models\AdPayment::where('status', 'success')
                    ->where('paid_at', '>=', $thisMonth)->sum('amount'),
            ],
            'total' => [
                'impressions' => CoinTransaction::where('type', 'impression_earning')->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])->sum('amount'),
                'npr_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])->sum('amount') * $coinToNpr,
                'offers_total' => \App\Models\RewardOffer::withTrashed()->count(),
                'offers_claimed_total' => \App\Models\OfferRedemption::count(),
                'offers_value_total' => \App\Models\OfferRedemption::sum('value_npr'),
                'offers_commission_total' => \App\Models\OfferRedemption::sum('admin_commission'),
                'offers_partner_total' => \App\Models\OfferRedemption::sum('partner_earnings'),
                'ad_revenue_total' => \App\Models\AdRevenueLedger::sum('gross_amount'),
                'ad_admin_share_total' => \App\Models\AdRevenueLedger::sum('admin_share'),
                'ad_user_share_total' => \App\Models\AdRevenueLedger::sum('user_share'),
                'ad_impressions_total' => \App\Models\AdImpression::count(),
                'ad_clicks_total' => \App\Models\AdClick::count(),
                'ad_payments_total' => \App\Models\AdPayment::where('status', 'success')->sum('amount'),
                'ad_campaigns_total' => \App\Models\AdCampaign::count(),
            ],
        ];

        // Custom date range stats
        $customStats = null;
        if ($dateFrom && $dateTo) {
            $from = \Carbon\Carbon::parse($dateFrom)->startOfDay();
            $to = \Carbon\Carbon::parse($dateTo)->endOfDay();

            $customStats = [
                'from' => $dateFrom,
                'to' => $dateTo,
                'label' => $from->format('M d, Y') . ' — ' . $to->format('M d, Y'),
                'impressions' => CoinTransaction::where('type', 'impression_earning')
                    ->whereBetween('created_at', [$from, $to])->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')
                    ->whereBetween('created_at', [$from, $to])->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->whereBetween('created_at', [$from, $to])->sum('amount'),
                'offers_claimed' => \App\Models\OfferRedemption::whereBetween('claimed_at', [$from, $to])->count(),
                'offers_value' => \App\Models\OfferRedemption::whereBetween('claimed_at', [$from, $to])->sum('value_npr'),
                'offers_commission' => \App\Models\OfferRedemption::whereBetween('claimed_at', [$from, $to])->sum('admin_commission'),
                'offers_partner' => \App\Models\OfferRedemption::whereBetween('claimed_at', [$from, $to])->sum('partner_earnings'),
                'ad_revenue' => \App\Models\AdRevenueLedger::whereBetween('created_at', [$from, $to])->sum('gross_amount'),
                'ad_admin_share' => \App\Models\AdRevenueLedger::whereBetween('created_at', [$from, $to])->sum('admin_share'),
                'ad_impressions' => \App\Models\AdImpression::whereBetween('viewed_at', [$from, $to])->count(),
                'ad_clicks' => \App\Models\AdClick::whereBetween('clicked_at', [$from, $to])->count(),
                'ad_payments' => \App\Models\AdPayment::where('status', 'success')
                    ->whereBetween('paid_at', [$from, $to])->sum('amount'),
            ];
        }

        // Top earners
        $topEarners = CoinTransaction::selectRaw('user_id, SUM(amount) as total_earned')
            ->whereIn('type', ['impression_earning', 'click_earning'])
            ->groupBy('user_id')
            ->orderByDesc('total_earned')
            ->limit(10)
            ->with('user:id,name')
            ->get();

        return view('admin.earnings-report', compact('stats', 'topEarners', 'coinToNpr', 'customStats', 'dateFrom', 'dateTo'));
    }

    /**
     * Money Flow Audit — shows exactly where money comes from and where it goes.
     */
    public function moneyFlowAudit()
    {
        $coinToNpr = (float) CoinSetting::getValue('coin_to_npr_rate', 1);
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 47);
        $adminSharePercent = (float) CoinSetting::getValue('admin_share_percent', 53);

        // ── MONEY IN (Real NPR entering the platform) ──
        $adPaymentsReceived = (float) \App\Models\AdPayment::where('status', 'success')->sum('amount');
        $bookingPaymentsReceived = (float) \App\Models\BookingPayment::where('status', 'success')->sum('amount');
        $subscriptionPaymentsReceived = (float) \App\Models\SubscriptionPayment::where('status', 'success')->sum('amount');
        $featuredPaymentsReceived = (float) \App\Models\FeaturedPayment::where('status', 'paid')->sum('amount');
        $partnerTopupsReceived = (float) \App\Models\PaymentTransaction::where('status', 'completed')
            ->where('metadata->type', 'topup')
            ->sum('amount_npr');
        $totalMoneyIn = $adPaymentsReceived + $bookingPaymentsReceived + $subscriptionPaymentsReceived
            + $featuredPaymentsReceived + $partnerTopupsReceived;

        // ── MONEY OUT (Real NPR leaving the platform) ──
        // Withdrawal::amount is in COINS — must convert to NPR
        $userWithdrawalsCoins = (float) Withdrawal::where('status', 'completed')->sum('amount');
        $userWithdrawalsPaid = $userWithdrawalsCoins * $coinToNpr;
        $partnerWithdrawalsPaid = (float) \App\Models\PartnerWithdrawal::where('status', 'paid')->sum('amount');
        $platformExpensesPaid = (float) \App\Models\PlatformExpense::where('status', 'paid')->sum('amount');
        $platformExpensesUnpaid = (float) \App\Models\PlatformExpense::where('status', '!=', 'paid')->sum('amount');
        $salariesPaid = (float) \App\Models\EmployeeSalary::where('payment_status', 'paid')->sum('net_salary');
        $salariesPending = (float) \App\Models\EmployeeSalary::where('payment_status', 'pending')->sum('net_salary');
        $totalMoneyOut = $userWithdrawalsPaid + $partnerWithdrawalsPaid + $platformExpensesPaid + $salariesPaid;

        // ── COIN ECONOMY ──
        $totalCoinsIssued = (float) \App\Models\OriporiCoinWallet::sum('balance');
        $totalCoinsEarned = (float) \App\Models\OriporiCoinWallet::sum('total_earned');
        $totalCoinsWithdrawn = (float) \App\Models\OriporiCoinWallet::sum('total_withdrawn');
        $coinLiabilityNpr = $totalCoinsIssued * $coinToNpr;

        // ── AD REVENUE vs COIN CREATION ──
        $totalImpressions = \App\Models\AdImpression::count();
        $totalClicks = \App\Models\AdClick::count();
        $adRevenueGross = (float) \App\Models\AdRevenueLedger::sum('gross_amount');
        $adRevenueAdminShare = (float) \App\Models\AdRevenueLedger::sum('admin_share');
        $adRevenueUserShare = (float) \App\Models\AdRevenueLedger::sum('user_share');

        // What the LEDGER says users should get
        $ledgerUserShareTotal = $adRevenueUserShare;

        // What users ACTUALLY get in coins
        $actualCoinsValue = $totalCoinsEarned * $coinToNpr;

        // ── COIN CREATION FORMULAS (canonical: gross × user_share%) ──
        $grossPerImpression = (float) \App\Models\GameSetting::getValue('ad_cpm', 50) / 1000;
        $userGetsPerImpression = $grossPerImpression * ($userSharePercent / 100);
        $adminKeepsPerImpression = $grossPerImpression * ($adminSharePercent / 100);

        $grossPerClick = (float) \App\Models\GameSetting::getValue('ad_cpc', 0.50);
        $userGetsPerClick = $grossPerClick * ($userSharePercent / 100);
        $adminKeepsPerClick = $grossPerClick * ($adminSharePercent / 100);

        // ── BACKING CHECK ──
        $totalAdRevenuePlusOther = $adRevenueGross + $bookingPaymentsReceived + $subscriptionPaymentsReceived + $featuredPaymentsReceived;
        $backingRatio = $totalAdRevenuePlusOther > 0 ? $coinLiabilityNpr / $totalAdRevenuePlusOther : 0;
        $isBacked = $coinLiabilityNpr <= $totalAdRevenuePlusOther;

        $data = compact(
            'coinToNpr', 'userSharePercent', 'adminSharePercent',
            'adPaymentsReceived', 'bookingPaymentsReceived', 'subscriptionPaymentsReceived',
            'featuredPaymentsReceived', 'partnerTopupsReceived', 'totalMoneyIn',
            'userWithdrawalsPaid', 'userWithdrawalsCoins', 'partnerWithdrawalsPaid',
            'platformExpensesPaid', 'platformExpensesUnpaid', 'salariesPaid', 'salariesPending',
            'totalMoneyOut',
            'totalCoinsIssued', 'totalCoinsEarned', 'totalCoinsWithdrawn', 'coinLiabilityNpr',
            'totalImpressions', 'totalClicks', 'adRevenueGross', 'adRevenueAdminShare', 'adRevenueUserShare',
            'ledgerUserShareTotal', 'actualCoinsValue',
            'grossPerImpression', 'userGetsPerImpression', 'adminKeepsPerImpression',
            'grossPerClick', 'userGetsPerClick', 'adminKeepsPerClick',
            'totalAdRevenuePlusOther', 'backingRatio', 'isBacked'
        );

        return view('admin.money-flow-audit', $data);
    }

    /**
     * Comprehensive financial audit statement — print-ready.
     */
    public function financialAuditStatement(Request $request)
    {
        $now = now();
        $coinToNpr = (float) CoinSetting::getValue('coin_to_npr_rate', 1);

        // ── Platform Overview ──
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalPartners = \App\Models\TravelPartner::count();
        $activePartners = \App\Models\TravelPartner::where('is_active', true)->count();
        $totalReports = \App\Models\Report::count();
        $approvedReports = \App\Models\Report::where('status', 'approved')->count();
        $totalPlaces = \App\Models\Place::count();

        // ── User Coin System ──
        $totalCoinWallets = \App\Models\OriporiCoinWallet::count();
        $totalCoinsIssued = (float) \App\Models\OriporiCoinWallet::sum('balance');
        $totalCoinsEarned = (float) \App\Models\OriporiCoinWallet::sum('total_earned');
        $totalCoinsWithdrawn = (float) \App\Models\OriporiCoinWallet::sum('total_withdrawn');
        $coinTransactionsCount = CoinTransaction::count();
        $impressionEarnings = (float) CoinTransaction::where('type', 'impression_earning')->sum('amount');
        $clickEarnings = (float) CoinTransaction::where('type', 'click_earning')->sum('amount');
        $adminAdjustments = (float) CoinTransaction::where('type', 'admin_adjustment')->sum('amount');
        $redemptionDeductions = (float) CoinTransaction::where('type', 'redemption')->sum('amount');
        $reversalCount = CoinTransaction::whereNotNull('reverses_transaction_id')->count();
        $reversalTotal = (float) CoinTransaction::whereNotNull('reverses_transaction_id')->sum('amount');
        $recentReversals = CoinTransaction::whereNotNull('reverses_transaction_id')
            ->with(['user:id,name', 'reverses:id,user_id,type,amount,description'])
            ->latest('id')
            ->limit(10)
            ->get();
        $totalCoinNprValue = $totalCoinsIssued * $coinToNpr;

        // ── Partner Wallets ──
        $totalPartnerWallets = \App\Models\PartnerWallet::count();
        $totalPartnerBalance = (float) \App\Models\PartnerWallet::sum('balance');
        $totalPartnerEarned = (float) \App\Models\PartnerWallet::sum('total_earned');
        $totalPartnerWithdrawn = (float) \App\Models\PartnerWallet::sum('total_withdrawn');
        $partnerPaymentsCount = \App\Models\PartnerPayment::count();
        $partnerPaymentsRevenue = (float) \App\Models\PartnerPayment::where('status', 'completed')->sum('amount');
        $partnerPaymentsCommission = (float) \App\Models\PartnerPayment::where('status', 'completed')->sum('commission_amount');
        $partnerPaymentsNet = (float) \App\Models\PartnerPayment::where('status', 'completed')->sum('partner_amount');

        // ── Withdrawals ──
        $totalWithdrawals = Withdrawal::count();
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->count();
        $processingWithdrawals = Withdrawal::where('status', 'processing')->count();
        $completedWithdrawals = Withdrawal::where('status', 'completed')->count();
        $rejectedWithdrawals = Withdrawal::where('status', 'rejected')->count();
        $totalWithdrawalAmount = (float) Withdrawal::sum('amount');
        $pendingWithdrawalAmount = (float) Withdrawal::where('status', 'pending')->sum('amount');
        $processingWithdrawalAmount = (float) Withdrawal::where('status', 'processing')->sum('amount');
        $completedWithdrawalAmount = (float) Withdrawal::where('status', 'completed')->sum('amount');
        $rejectedWithdrawalAmount = (float) Withdrawal::where('status', 'rejected')->sum('amount');

        // ── Partner Withdrawals ──
        $totalPartnerWithdrawals = \App\Models\PartnerWithdrawal::count();
        $pendingPartnerWithdrawals = \App\Models\PartnerWithdrawal::where('status', 'pending')->count();
        $paidPartnerWithdrawals = \App\Models\PartnerWithdrawal::where('status', 'paid')->count();
        $rejectedPartnerWithdrawals = \App\Models\PartnerWithdrawal::where('status', 'rejected')->count();
        $totalPartnerWithdrawalAmount = (float) \App\Models\PartnerWithdrawal::sum('amount');
        $paidPartnerWithdrawalAmount = (float) \App\Models\PartnerWithdrawal::where('status', 'paid')->sum('amount');

        // ── Ad Revenue ──
        $totalAdImpressions = \App\Models\AdImpression::count();
        $totalAdClicks = \App\Models\AdClick::count();
        $adRevenueGross = (float) \App\Models\AdRevenueLedger::sum('gross_amount');
        $adRevenueUserShare = (float) \App\Models\AdRevenueLedger::sum('user_share');
        $adRevenueAdminShare = (float) \App\Models\AdRevenueLedger::sum('admin_share');
        $activeAdCampaigns = \App\Models\AdCampaign::where('status', 'active')->count();
        $totalAdCampaigns = \App\Models\AdCampaign::count();
        $totalAdPayments = (float) \App\Models\AdPayment::where('status', 'success')->sum('amount');

        // ── Offer Redemptions ──
        $totalOffers = \App\Models\RewardOffer::withTrashed()->count();
        $activeOffers = \App\Models\RewardOffer::where('status', 'approved')->count();
        $totalRedemptions = \App\Models\OfferRedemption::count();
        $claimedRedemptions = \App\Models\OfferRedemption::where('status', 'claimed')->count();
        $usedRedemptions = \App\Models\OfferRedemption::where('status', 'used')->count();
        $totalOfferValue = (float) \App\Models\OfferRedemption::sum('value_npr');
        $totalAdminCommission = (float) \App\Models\OfferRedemption::sum('admin_commission');
        $totalPartnerEarnings = (float) \App\Models\OfferRedemption::sum('partner_earnings');

        // ── Bookings & Subscriptions ──
        $totalBookings = \App\Models\Booking::count();
        $confirmedBookings = \App\Models\Booking::where('status', 'confirmed')->count();
        $totalBookingAmount = (float) \App\Models\Booking::sum('amount');
        $totalBookingCommission = (float) \App\Models\Booking::sum('commission_earned');
        $bookingPaymentsSuccess = (float) \App\Models\BookingPayment::where('status', 'success')->sum('amount');
        $totalSubscriptions = \App\Models\UserSubscription::count();
        $activeSubscriptions = \App\Models\UserSubscription::where('status', 'active')->count();
        $subscriptionRevenue = (float) \App\Models\SubscriptionPayment::where('status', 'success')->sum('amount');

        // ── Platform Expenses ──
        $totalExpenses = (float) \App\Models\PlatformExpense::sum('amount');
        $paidExpenses = (float) \App\Models\PlatformExpense::where('status', 'paid')->sum('amount');
        $unpaidExpenses = (float) \App\Models\PlatformExpense::where('status', '!=', 'paid')->sum('amount');
        $totalSalaries = (float) \App\Models\EmployeeSalary::sum('net_salary');
        $paidSalaries = (float) \App\Models\EmployeeSalary::where('payment_status', 'paid')->sum('net_salary');
        $pendingSalaries = (float) \App\Models\EmployeeSalary::where('payment_status', 'pending')->sum('net_salary');

        // ── Featured & Sponsorship ──
        $featuredPayments = (float) \App\Models\FeaturedPayment::where('status', 'paid')->sum('amount');

        // ── Coin Settings ──
        $impressionValue = (float) CoinSetting::getValue('impression_value', 0.05);
        $clickValue = (float) CoinSetting::getValue('click_value', 0.50);
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 70);
        $adminSharePercent = (float) CoinSetting::getValue('admin_share_percent', 30);
        $minWithdrawal = (float) CoinSetting::getValue('min_withdrawal_amount', 100);
        $maxWithdrawal = (float) CoinSetting::getValue('max_withdrawal_amount', 10000);
        $dailyEarningCap = (float) CoinSetting::getValue('daily_earning_cap', 50);
        $impressionCooldown = (int) CoinSetting::getValue('impression_cooldown_minutes', 10);

        // ── Monthly Trend (last 6 months) ──
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyTrend[] = [
                'month' => $month->format('M Y'),
                'coins_earned' => (float) CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('amount'),
                'offers_claimed' => \App\Models\OfferRedemption::whereYear('claimed_at', $month->year)
                    ->whereMonth('claimed_at', $month->month)->count(),
                'offers_value' => (float) \App\Models\OfferRedemption::whereYear('claimed_at', $month->year)
                    ->whereMonth('claimed_at', $month->month)->sum('value_npr'),
                'withdrawals' => (float) Withdrawal::where('status', 'completed')
                    ->whereYear('processed_at', $month->year)
                    ->whereMonth('processed_at', $month->month)->sum('amount'),
                'ad_revenue' => (float) \App\Models\AdRevenueLedger::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)->sum('gross_amount'),
            ];
        }

        // ── Reconciliation Check ──
        $walletSumBalance = (float) \App\Models\OriporiCoinWallet::sum('balance');
        $coinTxnSum = (float) CoinTransaction::sum('amount');
        $coinDrift = round($walletSumBalance - $coinTxnSum, 4);

        $partnerWalletSum = (float) \App\Models\PartnerWallet::sum('balance');
        // partner ledger not yet created — mark as unreconcilable
        $partnerReconcilable = false;

        $data = compact(
            'now', 'coinToNpr',
            'totalUsers', 'activeUsers', 'totalPartners', 'activePartners',
            'totalReports', 'approvedReports', 'totalPlaces',
            'totalCoinWallets', 'totalCoinsIssued', 'totalCoinsEarned', 'totalCoinsWithdrawn',
            'coinTransactionsCount', 'impressionEarnings', 'clickEarnings', 'adminAdjustments',
            'redemptionDeductions', 'totalCoinNprValue',
            'reversalCount', 'reversalTotal', 'recentReversals',
            'totalPartnerWallets', 'totalPartnerBalance', 'totalPartnerEarned', 'totalPartnerWithdrawn',
            'partnerPaymentsCount', 'partnerPaymentsRevenue', 'partnerPaymentsCommission', 'partnerPaymentsNet',
            'totalWithdrawals', 'pendingWithdrawals', 'processingWithdrawals', 'completedWithdrawals',
            'rejectedWithdrawals', 'totalWithdrawalAmount', 'pendingWithdrawalAmount',
            'processingWithdrawalAmount', 'completedWithdrawalAmount', 'rejectedWithdrawalAmount',
            'totalPartnerWithdrawals', 'pendingPartnerWithdrawals', 'paidPartnerWithdrawals',
            'rejectedPartnerWithdrawals', 'totalPartnerWithdrawalAmount', 'paidPartnerWithdrawalAmount',
            'totalAdImpressions', 'totalAdClicks', 'adRevenueGross', 'adRevenueUserShare',
            'adRevenueAdminShare', 'activeAdCampaigns', 'totalAdCampaigns', 'totalAdPayments',
            'totalOffers', 'activeOffers', 'totalRedemptions', 'claimedRedemptions', 'usedRedemptions',
            'totalOfferValue', 'totalAdminCommission', 'totalPartnerEarnings',
            'totalBookings', 'confirmedBookings', 'totalBookingAmount', 'totalBookingCommission',
            'bookingPaymentsSuccess', 'totalSubscriptions', 'activeSubscriptions', 'subscriptionRevenue',
            'totalExpenses', 'paidExpenses', 'unpaidExpenses',
            'totalSalaries', 'paidSalaries', 'pendingSalaries', 'featuredPayments',
            'impressionValue', 'clickValue', 'userSharePercent', 'adminSharePercent',
            'minWithdrawal', 'maxWithdrawal', 'dailyEarningCap', 'impressionCooldown',
            'monthlyTrend', 'walletSumBalance', 'coinTxnSum', 'coinDrift',
            'partnerWalletSum', 'partnerReconcilable'
        );

        return view('admin.financial-audit-statement', $data);
    }

    /**
     * Retry failed payment. Re-approves and re-processes.
     */
    public function retryPayment(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'failed') {
            return response()->json(['success' => false, 'error' => 'Only failed withdrawals can be retried'], 422);
        }

        $paymentTxn = PaymentTransaction::where('withdrawal_id', $withdrawal->id)
            ->where('status', 'failed')
            ->first();

        if (!$paymentTxn) {
            return response()->json(['success' => false, 'error' => 'No failed payment to retry'], 404);
        }

        // Debit coins again before retry
        $ledger = app(\App\Services\FinancialLedgerService::class);
        $ledger->debitCoins(
            userId: $withdrawal->user_id,
            amount: (float) $withdrawal->amount,
            type: 'withdrawal',
            description: "Withdrawal retry — re-debiting coins",
            metadata: [
                'withdrawal_id' => $withdrawal->id,
                'retry' => true,
            ],
            idempotencyKey: "wth-retry:{$withdrawal->id}:" . now()->timestamp,
        );

        // Reset states for retry
        $paymentTxn->update([
            'status' => 'pending',
            'failure_reason' => null,
            'gateway_response' => null,
        ]);

        $withdrawal->update([
            'status' => 'processing',
            'admin_note' => 'Retrying payment',
        ]);

        return $this->processPayment($id);
    }
}