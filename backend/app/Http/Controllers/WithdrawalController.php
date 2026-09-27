<?php

namespace App\Http\Controllers;

use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\Withdrawal;
use App\Models\CoinSetting;
use App\Models\PaymentTransaction;
use App\Services\CoinService;
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
     * Get user wallet info.
     */
    public function wallet(Request $request): JsonResponse
    {
        $user = Auth::user();
        $coinService = app(CoinService::class);

        $balance = $coinService->getBalance($user);
        $todayEarnings = $coinService->getTodayEarnings($user);

        $withdrawalMethods = [
            'esewa' => [
                'min_withdrawal' => (float) CoinSetting::getValue('min_withdrawal_esewa', 100),
                'max_withdrawal' => (float) CoinSetting::getValue('max_withdrawal_esewa', 50000),
                'label' => 'eSewa',
            ],
            'khalti' => [
                'min_withdrawal' => (float) CoinSetting::getValue('min_withdrawal_khalti', 100),
                'max_withdrawal' => (float) CoinSetting::getValue('max_withdrawal_khalti', 50000),
                'label' => 'Khalti',
            ],
            'bank' => [
                'min_withdrawal' => (float) CoinSetting::getValue('min_withdrawal_bank', 500),
                'max_withdrawal' => (float) CoinSetting::getValue('max_withdrawal_bank', 500000),
                'label' => 'Bank Transfer',
            ],
        ];

        // Recent withdrawals with pagination
        $page = (int) $request->get('page', 1);
        $perPage = min((int) $request->get('per_page', 20), 50);
        $recentWithdrawals = Withdrawal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage, ['id', 'amount', 'method', 'status', 'created_at', 'processed_at'], 'page', $page)
            ->toArray();

        return response()->json([
            'wallet' => $balance,
            'today_earnings' => $todayEarnings,
            'withdrawal_methods' => $withdrawalMethods,
            'withdrawals' => $recentWithdrawals,
            'coin_to_npr_rate' => (float) CoinSetting::getValue('coin_to_npr_rate', 1),
        ]);
    }

    /**
     * Get user transaction history.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $coinService = app(CoinService::class);

        $limit = min((int) $request->get('limit', 20), 50);
        $offset = (int) $request->get('offset', 0);

        $transactions = $coinService->getTransactions($user, $limit, $offset);

        return response()->json([
            'data' => $transactions,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    /**
     * Request withdrawal.
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:esewa,khalti,bank',
            'account_details' => 'required|array',
            '_idempotency_key' => 'sometimes|string|max:100',
        ]);

        $user = Auth::user();
        $coinService = app(CoinService::class);
        $wallet = OriporiCoinWallet::getForUser($user->id);

        $amountCoins = (float) $request->amount;
        $method = $request->method;

        // Check minimum withdrawal (in Coins)
        $minKey = "min_withdrawal_{$method}";
        $minWithdrawal = (float) CoinSetting::getValue($minKey, 100);
        $maxKey = "max_withdrawal_{$method}";
        $maxWithdrawal = (float) CoinSetting::getValue($maxKey, 50000);
        $coinToNpr = (float) CoinSetting::getValue('coin_to_npr_rate', 1);

        if ($amountCoins < $minWithdrawal) {
            return response()->json([
                'success' => false,
                'error' => "Minimum withdrawal for {$method} is {$minWithdrawal} Coins",
            ], 422);
        }

        if ($amountCoins > $maxWithdrawal) {
            return response()->json([
                'success' => false,
                'error' => "Maximum withdrawal for {$method} is {$maxWithdrawal} Coins",
            ], 422);
        }

        // Check balance
        if (!$wallet->canWithdraw($amountCoins)) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient balance',
            ], 422);
        }

        // Validate account details based on method
        $accountValidation = $this->validateAccountDetails($method, $request->account_details);
        if (!$accountValidation['valid']) {
            return response()->json([
                'success' => false,
                'error' => $accountValidation['error'],
            ], 422);
        }

        // Check for pending withdrawals (allow configurable max)
        $maxPending = (int) CoinSetting::getValue('max_pending_withdrawals', 3);
        $pendingCount = Withdrawal::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount >= $maxPending) {
            return response()->json([
                'success' => false,
                'error' => "You have {$maxPending} pending withdrawals. Please wait for them to complete.",
            ], 422);
        }

        // Convert to NPR for limit checks
        $amountNpr = $this->paymentProcessor->coinsToNpr($amountCoins);

        // Check user daily/monthly limits
        $limitCheck = $this->paymentProcessor->checkUserLimits($user->id, $method, $amountNpr);
        if (!$limitCheck['allowed']) {
            return response()->json([
                'success' => false,
                'error' => $limitCheck['message'],
            ], 422);
        }

        // Generate idempotency key
        $idempotencyKey = $request->input('_idempotency_key')
            ?? $request->header('Idempotency-Key')
            ?? 'withdrawal-' . $user->id . '-' . $method . '-' . $amountCoins . '-' . now()->format('YmdHis');

        // Check if this exact request was already processed
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)
            ->where('user_id', $user->id)
            ->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request already submitted',
                'withdrawal' => [
                    'id' => $existing->withdrawal_id,
                    'status' => $existing->status,
                ],
            ]);
        }

        return DB::transaction(function () use ($user, $wallet, $request, $amountCoins, $method, $amountNpr, $idempotencyKey) {
            // Debit wallet via FinancialLedgerService (atomic + ledger-backed)
            $ledger = app(\App\Services\FinancialLedgerService::class);
            $coinTxn = $ledger->debitCoins(
                userId: $user->id,
                amount: $amountCoins,
                type: 'withdrawal',
                description: "Withdrawal via {$method} (Rs. " . number_format($amountNpr, 2) . ")",
                metadata: [
                    'method' => $method,
                    'amount_npr' => $amountNpr,
                ],
                idempotencyKey: $idempotencyKey . '-coin',
            );

            // Create withdrawal request
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amountCoins,
                'method' => $method,
                'account_details' => $request->account_details,
                'status' => 'pending',
            ]);

            // Create payment transaction record (pending)
            PaymentTransaction::create([
                'reference_id' => 'WTH-' . $withdrawal->id . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
                'method' => $method,
                'amount_npr' => $amountNpr,
                'account_details' => $request->account_details,
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user->id,
                'withdrawal_id' => $withdrawal->id,
                'metadata' => [
                    'amount_coins' => $amountCoins,
                    'coin_to_npr_rate' => \App\Services\FinancialLedgerService::getCoinToNprRate(),
                ],
            ]);

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                $user,
                'withdrawal.requested',
                'withdrawal',
                $withdrawal->id,
                "User requested withdrawal of {$amountCoins} Coins (Rs. " . number_format($amountNpr, 2) . ") via {$method}",
                [
                    'amount_coins' => $amountCoins,
                    'amount_npr' => $amountNpr,
                    'method' => $method,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted. It will be processed within 24-48 hours.',
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
                    'amount_npr' => $amountNpr,
                    'method' => $withdrawal->method,
                    'status' => $withdrawal->status,
                ],
                'wallet' => [
                    'balance' => $wallet->fresh()->balance,
                ],
            ]);
        });
    }

    /**
     * Validate account details based on withdrawal method.
     */
    private function validateAccountDetails(string $method, array $details): array
    {
        switch ($method) {
            case 'esewa':
                if (empty($details['phone'])) {
                    return ['valid' => false, 'error' => 'eSewa phone number is required'];
                }
                if (!preg_match('/^(984|985|986|987|988|989)\d{8}$/', $details['phone'])) {
                    return ['valid' => false, 'error' => 'Invalid eSewa phone number'];
                }
                break;

            case 'khalti':
                if (empty($details['phone'])) {
                    return ['valid' => false, 'error' => 'Khalti phone number is required'];
                }
                if (!preg_match('/^(984|985|986|987|988|989)\d{8}$/', $details['phone'])) {
                    return ['valid' => false, 'error' => 'Invalid Khalti phone number'];
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

    /**
     * Cancel pending withdrawal.
     */
    public function cancel(int $id): JsonResponse
    {
        $user = Auth::user();

        $withdrawal = Withdrawal::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'error' => 'Withdrawal not found or cannot be cancelled',
            ], 404);
        }

        return DB::transaction(function () use ($withdrawal, $user) {
            // Credit back via FinancialLedgerService (atomic + ledger-backed)
            $ledger = app(\App\Services\FinancialLedgerService::class);
            $amountNpr = $this->paymentProcessor->coinsToNpr((float) $withdrawal->amount);

            $ledger->creditCoins(
                userId: $user->id,
                amount: (float) $withdrawal->amount,
                type: 'admin_adjustment',
                description: "Withdrawal cancelled - amount refunded",
                metadata: [
                    'withdrawal_id' => $withdrawal->id,
                    'action' => 'cancel',
                    'amount_npr' => $amountNpr,
                ],
                idempotencyKey: "wth-cancel:{$withdrawal->id}",
            );

            $withdrawal->update(['status' => 'cancelled']);

            // Update payment transaction
            PaymentTransaction::where('withdrawal_id', $withdrawal->id)
                ->where('user_id', $user->id)
                ->update([
                    'status' => 'rejected',
                    'failure_reason' => 'Cancelled by user',
                    'completed_at' => now(),
                ]);

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                $user,
                'withdrawal.cancelled',
                'withdrawal',
                $withdrawal->id,
                "User cancelled withdrawal of {$withdrawal->amount} Coins (Rs. " . number_format($amountNpr, 2) . ")",
                [
                    'amount_coins' => $withdrawal->amount,
                    'amount_npr' => $amountNpr,
                    'method' => $withdrawal->method,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal cancelled. Amount refunded to wallet.',
                'wallet' => [
                    'balance' => $wallet->fresh()->balance,
                ],
            ]);
        });
    }

    /**
     * Get withdrawal details (for user).
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $withdrawal = Withdrawal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'error' => 'Withdrawal not found',
            ], 404);
        }

        $paymentTxn = PaymentTransaction::where('withdrawal_id', $withdrawal->id)->first();

        return response()->json([
            'withdrawal' => $withdrawal,
            'payment_transaction' => $paymentTxn,
            'amount_npr' => $this->paymentProcessor->coinsToNpr((float) $withdrawal->amount),
        ]);
    }
}