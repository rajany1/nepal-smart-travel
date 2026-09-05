<?php

namespace App\Services;

use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\CoinSetting;
use App\Models\AdCampaign;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CoinService
{
    /**
     * Credit coins to user when ad impression happens on their report.
     */
    public function creditImpression(User $user, AdCampaign $campaign, Report $report): ?CoinTransaction
    {
        // Fraud checks
        if (!$this->canCredit($user, $campaign, $report, 'impression')) {
            return null;
        }

        $impressionValue = (float) CoinSetting::getValue('impression_value', 0.05);
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 70);
        $coins = round($impressionValue * ($userSharePercent / 100), 4);

        if ($coins <= 0) {
            return null;
        }

        return $this->credit($user, $coins, 'impression_earning', $campaign, $report, "Ad impression on report: {$report->title}", [
            'impression_value' => $impressionValue,
            'user_share_percent' => $userSharePercent,
        ]);
    }

    /**
     * Credit coins to user when ad click happens on their report.
     */
    public function creditClick(User $user, AdCampaign $campaign, Report $report): ?CoinTransaction
    {
        if (!$this->canCredit($user, $campaign, $report, 'click')) {
            return null;
        }

        $clickValue = (float) CoinSetting::getValue('click_value', 0.50);
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 70);
        $coins = round($clickValue * ($userSharePercent / 100), 4);

        if ($coins <= 0) {
            return null;
        }

        return $this->credit($user, $coins, 'click_earning', $campaign, $report, "Ad click on report: {$report->title}", [
            'click_value' => $clickValue,
            'user_share_percent' => $userSharePercent,
        ]);
    }

    /**
     * Check if user can earn from this ad interaction.
     */
    private function canCredit(User $user, AdCampaign $campaign, Report $report, string $type): bool
    {
        // Self-view check: user can't earn from their own report's ads
        // Actually, users SHOULD earn from their own reports (that's the point)
        // But they can't game it by viewing their own report repeatedly

        // Bot detection
        if ($this->isBot()) {
            return false;
        }

        // IP cooldown check (prevent rapid repeated views)
        if (!$this->checkCooldown($user->id, $campaign->id, $type)) {
            return false;
        }

        // Daily earning cap
        if (!$this->checkDailyEarningCap($user->id)) {
            return false;
        }

        // Daily impression cap for this report
        if (!$this->checkDailyReportCap($report->id)) {
            return false;
        }

        return true;
    }

    /**
     * Check cooldown between same user/ad interactions.
     */
    private function checkCooldown(int $userId, int $campaignId, string $type): bool
    {
        $cooldownMinutes = (int) CoinSetting::getValue('impression_cooldown_minutes', 10);
        $cacheKey = "coin_cooldown:{$userId}:{$campaignId}:{$type}";
        $lastInteraction = Cache::get($cacheKey);

        if ($lastInteraction) {
            return false;
        }

        Cache::put($cacheKey, now()->timestamp, $cooldownMinutes * 60);
        return true;
    }

    /**
     * Check daily earning cap for user.
     */
    private function checkDailyEarningCap(int $userId): bool
    {
        $dailyCap = (float) CoinSetting::getValue('daily_earning_cap', 500);
        $todayEarned = CoinTransaction::where('user_id', $userId)
            ->whereIn('type', ['impression_earning', 'click_earning'])
            ->whereDate('created_at', today())
            ->sum('amount');

        return (float) $todayEarned < $dailyCap;
    }

    /**
     * Check daily impression cap for report.
     */
    private function checkDailyReportCap(int $reportId): bool
    {
        $dailyCap = (int) CoinSetting::getValue('daily_impression_cap', 1000);
        $todayImpressions = CoinTransaction::where('report_id', $reportId)
            ->where('type', 'impression_earning')
            ->whereDate('created_at', today())
            ->count();

        return $todayImpressions < $dailyCap;
    }

    /**
     * Basic bot detection.
     */
    private function isBot(): bool
    {
        $userAgent = request()->userAgent() ?? '';
        $botPatterns = ['bot', 'spider', 'crawler', 'curl', 'wget', 'python', 'java'];
        foreach ($botPatterns as $pattern) {
            if (str_contains(strtolower($userAgent), $pattern)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Credit coins to user wallet.
     */
    private function credit(User $user, float $amount, string $type, AdCampaign $campaign, Report $report, string $description, array $settingsMeta = []): CoinTransaction
    {
        return DB::transaction(function () use ($user, $amount, $type, $campaign, $report, $description, $settingsMeta) {
            // Get or create wallet
            $wallet = OriporiCoinWallet::getForUser($user->id);

            // Credit wallet
            $wallet->credit($amount);

            // Create transaction record
            $transaction = CoinTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount' => $amount,
                'ad_campaign_id' => $campaign->id,
                'report_id' => $report->id,
                'description' => $description,
                'metadata' => array_merge($settingsMeta, [
                    'ip_address' => request()->ip(),
                ]),
            ]);

            return $transaction;
        });
    }

    /**
     * Get user wallet balance.
     */
    public function getBalance(User $user): array
    {
        $wallet = OriporiCoinWallet::getForUser($user->id);

        return [
            'balance' => (float) $wallet->balance,
            'total_earned' => (float) $wallet->total_earned,
            'total_withdrawn' => (float) $wallet->total_withdrawn,
            'formatted_balance' => number_format($wallet->balance, 2),
        ];
    }

    /**
     * Get user transaction history.
     */
    public function getTransactions(User $user, int $limit = 20, int $offset = 0): array
    {
        return CoinTransaction::where('user_id', $user->id)
            ->with(['adCampaign:id,name', 'report:id,title'])
            ->orderByDesc('created_at')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get today's earnings for user.
     */
    public function getTodayEarnings(User $user): array
    {
        $today = CoinTransaction::where('user_id', $user->id)
            ->whereIn('type', ['impression_earning', 'click_earning'])
            ->whereDate('created_at', today());

        return [
            'impressions' => (clone $today)->where('type', 'impression_earning')->count(),
            'clicks' => (clone $today)->where('type', 'click_earning')->count(),
            'earned' => (float) (clone $today)->sum('amount'),
            'daily_cap' => (float) CoinSetting::getValue('daily_earning_cap', 500),
        ];
    }
}
