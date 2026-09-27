<?php

namespace App\Services;

use App\Models\OriporiCoinWallet;
use App\Models\CoinTransaction;
use App\Models\CoinSetting;
use App\Models\AdCampaign;
use App\Models\AdRewardEvent;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CoinService
{
    protected FinancialLedgerService $ledger;

    public function __construct(FinancialLedgerService $ledger)
    {
        $this->ledger = $ledger;
    }

    /**
     * Credit coins to user when ad impression happens on their report.
     * Coins derived from: gross_amount (CPM/1000) × user_share_percent / coin_to_npr_rate
     * NOT from static impression_value — that's just a fallback.
     */
    public function creditImpression(User $user, AdCampaign $campaign, Report $report): ?CoinTransaction
    {
        if (!$this->canCredit($user, $campaign, $report, 'impression')) {
            return null;
        }

        $grossAmount = $this->calculateGrossAmount($campaign, 'impression');
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 47);
        $coinToNprRate = (float) CoinSetting::getValue('coin_to_npr_rate', 1);

        // Canonical: coins = gross × user_share_percent / coin_to_npr_rate
        $coins = round(($grossAmount * $userSharePercent / 100) / $coinToNprRate, 4);

        // Fallback: if calculation yields 0, use static impression_value
        if ($coins <= 0) {
            $coins = round((float) CoinSetting::getValue('impression_value', 0.0235), 4);
        }

        if ($coins <= 0) {
            return null;
        }

        $idempotencyKey = AdRewardEvent::generateIdempotencyKey('imp', $user->id, $campaign->id);

        $rateSnapshot = [
            'coin_to_npr_rate' => $coinToNprRate,
            'user_share_percent' => $userSharePercent,
            'gross_amount' => $grossAmount,
        ];

        return $this->ledger->creditCoins(
            userId: $user->id,
            amount: $coins,
            type: 'impression_earning',
            description: "Ad impression on report: {$report->title}",
            metadata: array_merge($rateSnapshot, [
                'ad_campaign_id' => $campaign->id,
                'report_id' => $report->id,
                'ip_address' => request()->ip(),
            ]),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Credit coins to user when ad click happens on their report.
     * Coins derived from: gross_amount (CPC) × user_share_percent / coin_to_npr_rate
     * NOT from static click_value — that's just a fallback.
     */
    public function creditClick(User $user, AdCampaign $campaign, Report $report): ?CoinTransaction
    {
        if (!$this->canCredit($user, $campaign, $report, 'click')) {
            return null;
        }

        $grossAmount = $this->calculateGrossAmount($campaign, 'click');
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 47);
        $coinToNprRate = (float) CoinSetting::getValue('coin_to_npr_rate', 1);

        // Canonical: coins = gross × user_share_percent / coin_to_npr_rate
        $coins = round(($grossAmount * $userSharePercent / 100) / $coinToNprRate, 4);

        // Fallback: if calculation yields 0, use static click_value
        if ($coins <= 0) {
            $coins = round((float) CoinSetting::getValue('click_value', 0.235), 4);
        }

        if ($coins <= 0) {
            return null;
        }

        $idempotencyKey = AdRewardEvent::generateIdempotencyKey('click', $user->id, $campaign->id);

        $rateSnapshot = [
            'coin_to_npr_rate' => $coinToNprRate,
            'user_share_percent' => $userSharePercent,
            'gross_amount' => $grossAmount,
        ];

        return $this->ledger->creditCoins(
            userId: $user->id,
            amount: $coins,
            type: 'click_earning',
            description: "Ad click on report: {$report->title}",
            metadata: array_merge($rateSnapshot, [
                'ad_campaign_id' => $campaign->id,
                'report_id' => $report->id,
                'ip_address' => request()->ip(),
            ]),
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Calculate gross NPR amount for an ad event (what the advertiser is charged).
     */
    private function calculateGrossAmount(AdCampaign $campaign, string $eventType): float
    {
        if ($eventType === 'impression') {
            $cpm = (float) $campaign->cost_per_view > 0
                ? (float) $campaign->cost_per_view
                : (float) \App\Models\GameSetting::getValue('ad_cpm', 50);
            return round($cpm / 1000, 4);
        }

        // Click
        $cpc = (float) $campaign->cost_per_click > 0
            ? (float) $campaign->cost_per_click
            : (float) \App\Models\GameSetting::getValue('ad_cpc', 0.50);
        return round($cpc, 4);
    }

    /**
     * Check if user can earn from this ad interaction.
     */
    private function canCredit(User $user, AdCampaign $campaign, Report $report, string $type): bool
    {
        if ($this->isBot()) {
            return false;
        }

        if (!$this->checkCooldown($user->id, $campaign->id, $type)) {
            return false;
        }

        if (!$this->checkDailyEarningCap($user->id)) {
            return false;
        }

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

        // Use Cache::lock for atomic check-and-set
        $lock = Cache::lock("coin_cooldown_lock:{$cacheKey}", $cooldownMinutes * 60);
        if (!$lock->get()) {
            return false;
        }

        // Check if cooldown already set (from concurrent request that won the lock first)
        if (Cache::get($cacheKey)) {
            $lock->release();
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
