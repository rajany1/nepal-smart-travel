<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\CoinSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
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

        // Stats
        $stats = [
            'total' => Withdrawal::count(),
            'pending' => Withdrawal::where('status', 'pending')->count(),
            'processing' => Withdrawal::where('status', 'processing')->count(),
            'completed' => Withdrawal::where('status', 'completed')->count(),
            'rejected' => Withdrawal::where('status', 'rejected')->count(),
            'total_amount' => Withdrawal::where('status', 'completed')->sum('amount'),
            'pending_amount' => Withdrawal::where('status', 'pending')->sum('amount'),
        ];

        return view('admin.withdrawals', compact('withdrawals', 'stats'));
    }

    /**
     * Approve withdrawal.
     */
    public function approve(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json(['success' => false, 'error' => 'Withdrawal is not pending'], 422);
        }

        $withdrawal->update([
            'status' => 'processing',
            'admin_note' => 'Approved by admin',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal approved. Processing payment...',
        ]);
    }

    /**
     * Mark withdrawal as completed (paid).
     */
    public function complete(int $id): JsonResponse
    {
        $withdrawal = Withdrawal::findOrFail($id);

        if (!in_array($withdrawal->status, ['pending', 'processing'])) {
            return response()->json(['success' => false, 'error' => 'Cannot complete this withdrawal'], 422);
        }

        $withdrawal->update([
            'status' => 'completed',
            'processed_at' => now(),
            'admin_note' => 'Payment completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal marked as completed',
        ]);
    }

    /**
     * Reject withdrawal.
     */
    public function reject(int $id, Request $request): JsonResponse
    {
        $request->validate(['admin_note' => 'required|string']);

        $withdrawal = Withdrawal::findOrFail($id);

        if (in_array($withdrawal->status, ['completed', 'rejected'])) {
            return response()->json(['success' => false, 'error' => 'Cannot reject this withdrawal'], 422);
        }

        return DB::transaction(function () use ($withdrawal, $request) {
            // Refund wallet
            $wallet = OriporiCoinWallet::getForUser($withdrawal->user_id);
            $wallet->credit((float) $withdrawal->amount);

            // Update withdrawal status
            $withdrawal->update([
                'status' => 'rejected',
                'admin_note' => $request->admin_note,
            ]);

            // Create refund transaction
            CoinTransaction::create([
                'user_id' => $withdrawal->user_id,
                'type' => 'admin_adjustment',
                'amount' => $withdrawal->amount,
                'description' => "Withdrawal rejected - amount refunded",
                'metadata' => [
                    'withdrawal_id' => $withdrawal->id,
                    'action' => 'reject_refund',
                    'admin_note' => $request->admin_note,
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal rejected. Amount refunded to user wallet.',
            ]);
        });
    }

    /**
     * Get withdrawal details.
     */
    public function show(int $id)
    {
        $withdrawal = Withdrawal::with('user:id,name,email')->findOrFail($id);

        return response()->json([
            'withdrawal' => $withdrawal,
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
            'impression_value' => 'required|numeric|min:0',
            'click_value' => 'required|numeric|min:0',
            'user_share_percent' => 'required|numeric|min:0|max:100',
            'coin_to_npr_rate' => 'required|numeric|min:0.01',
            'min_withdrawal_esewa' => 'required|numeric|min:0',
            'min_withdrawal_khalti' => 'required|numeric|min:0',
            'min_withdrawal_bank' => 'required|numeric|min:0',
            'daily_earning_cap' => 'required|numeric|min:0',
            'daily_impression_cap' => 'required|numeric|min:0',
            'impression_cooldown_minutes' => 'required|numeric|min:0',
        ]);

        // Auto-calculate admin share from user share
        $adminShare = 100 - (float) $settings['user_share_percent'];
        $settings['admin_share_percent'] = $adminShare;

        foreach ($settings as $key => $value) {
            CoinSetting::set($key, $value);
        }

        return redirect()->route('admin.coin-settings')->with('success', 'Coin settings updated successfully!');
    }

    /**
     * Earnings report.
     */
    public function earningsReport()
    {
        $today = now()->startOfDay();
        $thisMonth = now()->startOfMonth();

        $stats = [
            'today' => [
                'impressions' => CoinTransaction::where('type', 'impression_earning')
                    ->whereDate('created_at', $today)->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')
                    ->whereDate('created_at', $today)->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->whereDate('created_at', $today)->sum('amount'),
                'withdrawals' => Withdrawal::where('status', 'completed')
                    ->whereDate('processed_at', $today)->sum('amount'),
            ],
            'this_month' => [
                'impressions' => CoinTransaction::where('type', 'impression_earning')
                    ->where('created_at', '>=', $thisMonth)->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')
                    ->where('created_at', '>=', $thisMonth)->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])
                    ->where('created_at', '>=', $thisMonth)->sum('amount'),
                'withdrawals' => Withdrawal::where('status', 'completed')
                    ->where('processed_at', '>=', $thisMonth)->sum('amount'),
            ],
            'total' => [
                'impressions' => CoinTransaction::where('type', 'impression_earning')->count(),
                'clicks' => CoinTransaction::where('type', 'click_earning')->count(),
                'coins_earned' => CoinTransaction::whereIn('type', ['impression_earning', 'click_earning'])->sum('amount'),
                'withdrawals' => Withdrawal::where('status', 'completed')->sum('amount'),
            ],
        ];

        // Top earners
        $topEarners = CoinTransaction::selectRaw('user_id, SUM(amount) as total_earned')
            ->whereIn('type', ['impression_earning', 'click_earning'])
            ->groupBy('user_id')
            ->orderByDesc('total_earned')
            ->limit(10)
            ->with('user:id,name')
            ->get();

        return view('admin.earnings-report', compact('stats', 'topEarners'));
    }
}
