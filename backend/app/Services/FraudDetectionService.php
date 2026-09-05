<?php

namespace App\Services;

use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\Report;
use App\Models\PlaceReview;
use App\Models\User;
use App\Models\UserFraudProfile;
use App\Models\CoinSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FraudDetectionService
{
    private const BOT_SIGNATURES = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'googlebot', 'bingbot', 'yandexbot', 'baiduspider',
        'facebookexternalhit', 'twitterbot', 'linkedinbot',
        'headless', 'phantom', 'selenium', 'puppeteer', 'playwright',
        'curl', 'wget', 'python-requests', 'go-http-client',
        'java/', 'perl', 'ruby', 'php/', 'python/',
        'httpclient', 'okhttp', 'libwww', 'lwp-trivial',
        'seo', 'audit', 'check', 'scan', 'monitor',
    ];

    private const AD_VELOCITY_LIMITS = [
        'impressions_per_hour' => 20,
        'clicks_per_hour' => 10,
        'impressions_per_day' => 100,
        'clicks_per_day' => 50,
    ];

    private const REPORT_VELOCITY_LIMITS = [
        'per_hour' => 5,
        'per_day' => 15,
    ];

    private const REVIEW_VELOCITY_LIMITS = [
        'per_hour' => 3,
        'per_day' => 10,
    ];

    private const CTR_ANOMALY_THRESHOLD = 15.0;
    private const CTR_MIN_IMPRESSIONS = 100;
    private const BURST_WINDOW_MINUTES = 2;
    private const BURST_LIMIT = 5;
    private const FRAUD_SCORE_THRESHOLD = 80;
    private const USER_FRAUD_THRESHOLD = 80;
    private const SCORE_DECAY_RATE = 0.10;

    public function checkImpression(Request $request, AdCampaign $campaign): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';
        $userId = Auth::id();

        $reasons = [];

        if ($this->isBot($userAgent)) {
            $reasons[] = 'bot_detected';
        }

        if ($this->isBurst($campaign->id, $ip, $userId, 'impression')) {
            $reasons[] = 'burst_detected';
        }

        if ($this->isVelocityBreach($campaign->id, $ip, $userId, 'impression')) {
            $reasons[] = 'velocity_breach';
        }

        if ($this->isSelfClick($campaign, $userId)) {
            $reasons[] = 'self_click';
        }

        if (!empty($reasons)) {
            $this->logFraud($campaign, 'impression', $reasons, $ip, $userAgent);
            $this->updateFraudScore($campaign, $reasons);
            return ['blocked' => true, 'reasons' => $reasons];
        }

        return ['blocked' => false];
    }

    public function checkClick(Request $request, AdCampaign $campaign): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';
        $userId = Auth::id();

        $reasons = [];

        if ($this->isBot($userAgent)) {
            $reasons[] = 'bot_detected';
        }

        if ($this->isBurst($campaign->id, $ip, $userId, 'click')) {
            $reasons[] = 'burst_detected';
        }

        if ($this->isVelocityBreach($campaign->id, $ip, $userId, 'click')) {
            $reasons[] = 'velocity_breach';
        }

        if ($this->isSelfClick($campaign, $userId)) {
            $reasons[] = 'self_click';
        }

        if ($this->isCTRFraud($campaign)) {
            $reasons[] = 'ctr_anomaly';
        }

        if (!empty($reasons)) {
            $this->logFraud($campaign, 'click', $reasons, $ip, $userAgent);
            $this->updateFraudScore($campaign, $reasons);
            return ['blocked' => true, 'reasons' => $reasons];
        }

        return ['blocked' => false];
    }

    public function checkReport(Request $request, User $user): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        $reasons = [];

        if ($this->isBot($userAgent)) {
            $reasons[] = 'bot_detected';
        }

        if ($this->isReportVelocityBreach($user->id, $ip)) {
            $reasons[] = 'velocity_breach';
        }

        if ($this->isReportDuplicate($user->id, $request->input('description', ''))) {
            $reasons[] = 'duplicate_content';
        }

        if ($this->isMultiAccountSuspect($ip, 'report')) {
            $reasons[] = 'multi_account';
        }

        if (!empty($reasons)) {
            $this->logUserFraud($user, 'report', $reasons, $ip, $userAgent);
            return ['blocked' => true, 'reasons' => $reasons];
        }

        return ['blocked' => false];
    }

    public function checkReview(Request $request, int $placeId, User $user): array
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent() ?? '';

        $reasons = [];

        if ($this->isBot($userAgent)) {
            $reasons[] = 'bot_detected';
        }

        if ($this->isReviewVelocityBreach($user->id, $ip)) {
            $reasons[] = 'velocity_breach';
        }

        if ($this->isSelfReview($user->id, $placeId)) {
            $reasons[] = 'self_review';
        }

        if ($this->isReviewTooShort($request->input('description', ''))) {
            $reasons[] = 'quality_gate';
        }

        if ($this->isReviewDuplicateText($user->id, $request->input('description', ''))) {
            $reasons[] = 'duplicate_content';
        }

        if ($this->isMultiAccountSuspect($ip, 'review')) {
            $reasons[] = 'multi_account';
        }

        if (!empty($reasons)) {
            $this->logUserFraud($user, 'review', $reasons, $ip, $userAgent);
            return ['blocked' => true, 'reasons' => $reasons];
        }

        return ['blocked' => false];
    }

    public function isUserSuspicious(int $userId): bool
    {
        $profile = UserFraudProfile::where('user_id', $userId)->first();
        return $profile && $profile->fraud_score >= self::USER_FRAUD_THRESHOLD;
    }

    private function isBot(string $userAgent): bool
    {
        $ua = strtolower($userAgent);
        foreach (self::BOT_SIGNATURES as $sig) {
            if (str_contains($ua, $sig)) {
                return true;
            }
        }
        return false;
    }

    private function isBurst(int $campaignId, ?string $ip, ?int $userId, string $type): bool
    {
        $table = $type === 'impression' ? 'ad_impressions' : 'ad_clicks';
        $column = $type === 'impression' ? 'viewed_at' : 'clicked_at';

        $count = DB::table($table)
            ->where('ad_campaign_id', $campaignId)
            ->where($column, '>=', now()->subMinutes(self::BURST_WINDOW_MINUTES))
            ->when($userId, fn($q) => $q->where('user_id', $userId), fn($q) => $q->where('ip_address', $ip))
            ->count();

        return $count >= self::BURST_LIMIT;
    }

    private function isVelocityBreach(int $campaignId, ?string $ip, ?int $userId, string $type): bool
    {
        $table = $type === 'impression' ? 'ad_impressions' : 'ad_clicks';
        $column = $type === 'impression' ? 'viewed_at' : 'clicked_at';
        $hourKey = $type === 'impression' ? 'impressions_per_hour' : 'clicks_per_hour';
        $dayKey = $type === 'impression' ? 'impressions_per_day' : 'clicks_per_day';

        $hourCount = DB::table($table)
            ->where('ad_campaign_id', $campaignId)
            ->where($column, '>=', now()->subHour())
            ->when($userId, fn($q) => $q->where('user_id', $userId), fn($q) => $q->where('ip_address', $ip))
            ->count();

        if ($hourCount >= self::AD_VELOCITY_LIMITS[$hourKey]) {
            return true;
        }

        $dayCount = DB::table($table)
            ->where('ad_campaign_id', $campaignId)
            ->where($column, '>=', now()->startOfDay())
            ->when($userId, fn($q) => $q->where('user_id', $userId), fn($q) => $q->where('ip_address', $ip))
            ->count();

        if ($dayCount >= self::AD_VELOCITY_LIMITS[$dayKey]) {
            return true;
        }

        return false;
    }

    private function isReportVelocityBreach(int $userId, ?string $ip): bool
    {
        $hourCount = Report::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourCount >= self::REPORT_VELOCITY_LIMITS['per_hour']) {
            return true;
        }

        $dayCount = Report::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($dayCount >= self::REPORT_VELOCITY_LIMITS['per_day']) {
            return true;
        }

        return false;
    }

    private function isReviewVelocityBreach(int $userId, ?string $ip): bool
    {
        $hourCount = PlaceReview::where('user_id', $userId)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($hourCount >= self::REVIEW_VELOCITY_LIMITS['per_hour']) {
            return true;
        }

        $dayCount = PlaceReview::where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        if ($dayCount >= self::REVIEW_VELOCITY_LIMITS['per_day']) {
            return true;
        }

        return false;
    }

    private function isSelfClick(AdCampaign $campaign, ?int $userId): bool
    {
        if (!$userId || !$campaign->business_id) {
            return false;
        }

        $partner = $campaign->business;
        if (!$partner) {
            return false;
        }

        return $partner->users()->where('users.id', $userId)->exists();
    }

    private function isSelfReview(int $userId, int $placeId): bool
    {
        $place = \App\Models\Place::find($placeId);
        if (!$place) {
            return false;
        }

        return $place->created_by === $userId;
    }

    private function isReviewTooShort(string $description): bool
    {
        return mb_strlen(trim($description)) < 10;
    }

    private function isReviewDuplicateText(int $userId, string $description): bool
    {
        if (mb_strlen(trim($description)) < 20) {
            return false;
        }

        return PlaceReview::where('user_id', $userId)
            ->where('description', trim($description))
            ->exists();
    }

    private function isReportDuplicate(int $userId, string $description): bool
    {
        if (mb_strlen(trim($description)) < 20) {
            return false;
        }

        return Report::where('user_id', $userId)
            ->where('description', trim($description))
            ->where('created_at', '>=', now()->subHours(24))
            ->exists();
    }

    private function isMultiAccountSuspect(?string $ip, string $type): bool
    {
        if (!$ip) {
            return false;
        }

        $table = $type === 'report' ? 'reports' : 'place_reviews';
        $recentUsers = DB::table($table)
            ->where('ip_address', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->distinct()
            ->count('user_id');

        return $recentUsers >= 3;
    }

    private function isCTRFraud(AdCampaign $campaign): bool
    {
        if ($campaign->current_impressions < self::CTR_MIN_IMPRESSIONS) {
            return false;
        }

        return $campaign->ctr() > self::CTR_ANOMALY_THRESHOLD;
    }

    private function logFraud(AdCampaign $campaign, string $type, array $reasons, ?string $ip, ?string $userAgent): void
    {
        DB::table('ad_fraud_logs')->insert([
            'ad_campaign_id' => $campaign->id,
            'type' => $type,
            'reason' => implode(',', $reasons),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'metadata' => json_encode([
                'user_id' => Auth::id(),
                'current_fraud_score' => $campaign->fraud_score,
                'current_impressions' => $campaign->current_impressions,
                'current_clicks' => $campaign->current_clicks,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function logUserFraud(User $user, string $type, array $reasons, ?string $ip, ?string $userAgent): void
    {
        $profile = UserFraudProfile::getForUser($user->id);

        $pointsMap = [
            'bot_detected' => 30,
            'velocity_breach' => 20,
            'duplicate_content' => 25,
            'self_review' => 20,
            'quality_gate' => 10,
            'multi_account' => 35,
        ];

        $addPoints = 0;
        foreach ($reasons as $reason) {
            $addPoints += $pointsMap[$reason] ?? 10;
        }

        $newScore = min(100, $profile->fraud_score + $addPoints);
        $flags = $profile->fraud_flags ?? [];
        foreach ($reasons as $reason) {
            $flags[] = [
                'reason' => $reason,
                'type' => $type,
                'at' => now()->toIso8601String(),
                'ip' => $ip,
            ];
        }

        $profile->update([
            'fraud_score' => $newScore,
            'fraud_flags' => $flags,
            'is_suspicious' => $newScore >= self::USER_FRAUD_THRESHOLD,
            'suspicious_reason' => $newScore >= self::USER_FRAUD_THRESHOLD
                ? 'Auto-flagged: score ' . $newScore . ' (' . implode(', ', $reasons) . ')'
                : $profile->suspicious_reason,
        ]);
    }

    private function updateFraudScore(AdCampaign $campaign, array $reasons): void
    {
        $pointsMap = [
            'bot_detected' => 30,
            'velocity_breach' => 20,
            'ctr_anomaly' => 25,
            'self_click' => 15,
            'burst_detected' => 20,
        ];

        $addPoints = 0;
        foreach ($reasons as $reason) {
            $addPoints += $pointsMap[$reason] ?? 10;
        }

        $newScore = min(100, $campaign->fraud_score + $addPoints);
        $flags = $campaign->fraud_flags ?? [];
        $flags = array_merge($flags, array_map(fn($r) => [
            'reason' => $r,
            'at' => now()->toIso8601String(),
            'type' => $reasons[0] ?? 'unknown',
        ], $reasons));

        $campaign->update([
            'fraud_score' => $newScore,
            'fraud_flags' => $flags,
        ]);

        if ($newScore >= self::FRAUD_SCORE_THRESHOLD && $campaign->status === 'active') {
            $campaign->update([
                'status' => 'paused',
                'paused_by' => 'system',
            ]);
        }
    }

    /**
     * Check for coin earning fraud (self-view, bot, rapid repeated views).
     */
    public function checkCoinEarning(int $userId, int $reportId, int $campaignId): array
    {
        $ip = request()->ip();
        $userAgent = request()->userAgent() ?? '';
        $reasons = [];

        // Bot detection
        if ($this->isBot($userAgent)) {
            $reasons[] = 'bot_detected';
        }

        // Self-view: user viewing own report's ad repeatedly
        $report = Report::find($reportId);
        if ($report && $report->user_id === $userId) {
            $recentSelfViews = DB::table('coin_transactions')
                ->where('user_id', $userId)
                ->where('report_id', $reportId)
                ->where('ad_campaign_id', $campaignId)
                ->where('type', 'impression_earning')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
            if ($recentSelfViews >= 10) {
                $reasons[] = 'excessive_self_view';
            }
        }

        // Rapid repeated impressions from same IP
        $recentImpressions = DB::table('coin_transactions')
            ->where('report_id', $reportId)
            ->where('type', 'impression_earning')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
        if ($recentImpressions >= 50) {
            $reasons[] = 'velocity_breach';
        }

        // Daily earning cap check
        $dailyCap = (float) CoinSetting::getValue('daily_earning_cap', 500);
        $todayEarned = DB::table('coin_transactions')
            ->where('user_id', $userId)
            ->whereIn('type', ['impression_earning', 'click_earning'])
            ->whereDate('created_at', today())
            ->sum('amount');
        if ((float) $todayEarned >= $dailyCap) {
            $reasons[] = 'daily_cap_reached';
        }

        return [
            'blocked' => !empty($reasons),
            'reasons' => $reasons,
        ];
    }

    public function decayScores(): int
    {
        AdCampaign::where('fraud_score', '>', 0)
            ->where('status', '!=', 'paused')
            ->update([
                'fraud_score' => DB::raw('GREATEST(0, FLOOR(fraud_score * ' . (1 - self::SCORE_DECAY_RATE) . '))'),
            ]);

        return UserFraudProfile::where('fraud_score', '>', 0)
            ->update([
                'fraud_score' => DB::raw('GREATEST(0, FLOOR(fraud_score * ' . (1 - self::SCORE_DECAY_RATE) . '))'),
            ]);
    }
}
