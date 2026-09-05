<?php

namespace App\Http\Controllers;

use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\Withdrawal;
use App\Models\CoinSetting;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
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
                'label' => 'eSewa',
            ],
            'khalti' => [
                'min_withdrawal' => (float) CoinSetting::getValue('min_withdrawal_khalti', 100),
                'label' => 'Khalti',
            ],
            'bank' => [
                'min_withdrawal' => (float) CoinSetting::getValue('min_withdrawal_bank', 500),
                'label' => 'Bank Transfer',
            ],
        ];

        // Recent withdrawals
        $recentWithdrawals = Withdrawal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'amount', 'method', 'status', 'created_at'])
            ->toArray();

        return response()->json([
            'wallet' => $balance,
            'today_earnings' => $todayEarnings,
            'withdrawal_methods' => $withdrawalMethods,
            'recent_withdrawals' => $recentWithdrawals,
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
        ]);

        $user = Auth::user();
        $coinService = app(CoinService::class);
        $wallet = OriporiCoinWallet::getForUser($user->id);

        // Check minimum withdrawal (in Coins)
        $minKey = "min_withdrawal_{$request->method}";
        $minWithdrawal = (float) CoinSetting::getValue($minKey, 100);
        $coinToNpr = (float) CoinSetting::getValue('coin_to_npr_rate', 1);

        if ((float) $request->amount < $minWithdrawal) {
            return response()->json([
                'success' => false,
                'error' => "Minimum withdrawal for {$request->method} is {$minWithdrawal} Coins",
            ], 422);
        }

        // Check balance
        if (!$wallet->canWithdraw((float) $request->amount)) {
            return response()->json([
                'success' => false,
                'error' => 'Insufficient balance',
            ], 422);
        }

        // Validate account details based on method
        $accountValidation = $this->validateAccountDetails($request->method, $request->account_details);
        if (!$accountValidation['valid']) {
            return response()->json([
                'success' => false,
                'error' => $accountValidation['error'],
            ], 422);
        }

        // Check for pending withdrawals
        $pendingCount = Withdrawal::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'error' => 'You already have a pending withdrawal request',
            ], 422);
        }

        return DB::transaction(function () use ($user, $wallet, $request) {
            // Debit wallet
            $wallet->debit((float) $request->amount);

            // Create withdrawal request
            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'method' => $request->method,
                'account_details' => $request->account_details,
                'status' => 'pending',
            ]);

            // Create coin transaction record
            CoinTransaction::create([
                'user_id' => $user->id,
                'type' => 'withdrawal',
                'amount' => -$request->amount,
                'description' => "Withdrawal via {$request->method}",
                'metadata' => [
                    'withdrawal_id' => $withdrawal->id,
                    'method' => $request->method,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request submitted. It will be processed within 24-48 hours.',
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'amount' => $withdrawal->amount,
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
            $wallet = OriporiCoinWallet::getForUser($user->id);
            $wallet->credit((float) $withdrawal->amount);

            $withdrawal->update(['status' => 'cancelled']);

            CoinTransaction::create([
                'user_id' => $user->id,
                'type' => 'admin_adjustment',
                'amount' => $withdrawal->amount,
                'description' => "Withdrawal cancelled - amount refunded",
                'metadata' => [
                    'withdrawal_id' => $withdrawal->id,
                    'action' => 'cancel',
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal cancelled. Amount refunded to wallet.',
                'wallet' => [
                    'balance' => $wallet->fresh()->balance,
                ],
            ]);
        });
    }
}
