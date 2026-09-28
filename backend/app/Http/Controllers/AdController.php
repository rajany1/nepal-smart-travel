<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\AdRevenueLedger;
use App\Models\AdRewardEvent;
use App\Models\Report;
use App\Models\CoinSetting;
use App\Services\FraudDetectionService;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        $context = $request->get('context');
        $district = $request->get('district');
        $category = $request->get('category');
        $limit = min((int) ($request->get('limit') ?: 3), 10);
        $persistent = $request->boolean('persistent', false);
        $cap = $persistent ? 0 : (int) \App\Models\GameSetting::getValue('ad_freq_cap', 3);
        $userId = Auth::id();
        $today = today()->startOfDay();

        // Block suspicious users from seeing ads (user-level fraud)
        if ($userId && app(FraudDetectionService::class)->isUserSuspicious($userId)) {
            return response()->json(['data' => []]);
        }

        $campaigns = AdCampaign::with('business')
            ->active()
            ->where(function ($q) {
                $q->where('max_impressions', 0)
                    ->orWhereColumn('current_impressions', '<', 'max_impressions');
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('budget')->orWhere('budget', '<=', 0);
                })->orWhereColumn('spent_amount', '<', 'budget');
            })
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('budget')->orWhere('budget', '<=', 0);
                    });
            })
            ->where('fraud_score', '<', 80)
            ->get()
            ->filter(function ($campaign) use ($context) {
                return $campaign->matchesContext($context);
            })
            ->values();

        if ($campaigns->isNotEmpty()) {
            $ids = $campaigns->pluck('id');

            $todayCounts = AdImpression::where('viewed_at', '>=', $today)
                ->whereIn('ad_campaign_id', $ids)
                ->selectRaw('ad_campaign_id, COUNT(*) as c')
                ->groupBy('ad_campaign_id')
                ->pluck('c', 'ad_campaign_id')
                ->map(fn ($v) => (int) $v);

            if ($cap > 0) {
                $seen = AdImpression::where('viewed_at', '>=', $today)
                    ->whereIn('ad_campaign_id', $ids)
                    ->when(
                        $userId,
                        fn ($q) => $q->where('user_id', $userId),
                        fn ($q) => $q->where('ip_address', $request->ip())
                    )
                    ->selectRaw('ad_campaign_id, COUNT(*) as c')
                    ->groupBy('ad_campaign_id')
                    ->get()
                    ->filter(fn ($r) => (int) $r->c >= $cap)
                    ->pluck('ad_campaign_id')
                    ->all();

                $campaigns = $campaigns->reject(fn ($c) => in_array($c->id, $seen))->values();
            }

            $campaigns = $this->weightedSample($campaigns, $limit, $context, $district, $category, $todayCounts);
        }

        return response()->json(['data' => $campaigns->map(function ($campaign) {
            return [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'ad_type' => $campaign->ad_type,
                'content' => $campaign->content,
                'image' => $campaign->image ? asset('storage/' . $campaign->image) : null,
                'target_url' => $campaign->target_url,
                'target_district' => $campaign->target_district,
                'target_category' => $campaign->target_category,
                'contexts' => $campaign->contexts ?? [],
                'business_name' => $campaign->business?->name,
            ];
        })->values()]);
    }

    /**
     * Get ad for specific report screen.
     * Returns ad + report owner info for coin crediting.
     */
    public function forReport(Request $request, int $reportId): JsonResponse
    {
        $report = Report::with('user:id,name')->find($reportId);

        if (!$report) {
            return response()->json(['error' => 'Report not found'], 404);
        }

        $context = 'report';
        $district = $report->district;
        $category = $report->category?->slug ?? null;
        $userId = Auth::id();

        // Block suspicious users from seeing ads
        if ($userId && app(FraudDetectionService::class)->isUserSuspicious($userId)) {
            return response()->json(['data' => null]);
        }

        // Get active campaigns — filter by context/district in PHP (not SQL LIKE on JSON)
        $campaigns = AdCampaign::with('business')
            ->active()
            ->where(function ($q) {
                $q->where('max_impressions', 0)
                    ->orWhereColumn('current_impressions', '<', 'max_impressions');
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('budget')->orWhere('budget', '<=', 0);
                })->orWhereColumn('spent_amount', '<', 'budget');
            })
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                    ->orWhere(function ($q2) {
                        $q2->whereNull('budget')->orWhere('budget', '<=', 0);
                    });
            })
            ->where('fraud_score', '<', 80)
            ->get()
            ->filter(fn ($c) => $c->matchesContext('report'))
            ->values();

        if ($campaigns->isEmpty()) {
            return response()->json(['data' => null]);
        }

        // Pick best match: prefer district match, then random
        $districtMatch = $campaigns->filter(fn ($c) => $district && $c->target_district && strcasecmp($c->target_district, $district) === 0);
        $campaign = $districtMatch->isNotEmpty() ? $districtMatch->random() : $campaigns->random();

        // Get coin settings for mobile app to display
        $impressionValue = (float) CoinSetting::getValue('impression_value', 0.05);
        $clickValue = (float) CoinSetting::getValue('click_value', 0.50);
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 47);

        return response()->json([
            'data' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'ad_type' => $campaign->ad_type,
                'content' => $campaign->content,
                'image' => $campaign->image ? asset('storage/' . $campaign->image) : null,
                'target_url' => $campaign->target_url,
                'business_name' => $campaign->business?->name,
                'contexts' => $campaign->contexts ?? [],
                'report_id' => $report->id,
                'report_owner_id' => $report->user_id,
                'report_owner_name' => $report->user->name,
                'coin_earning' => [
                    'impression_value' => round($impressionValue * ($userSharePercent / 100), 4),
                    'click_value' => round($clickValue * ($userSharePercent / 100), 4),
                    'user_share_percent' => $userSharePercent,
                ],
            ],
        ]);
    }

    /**
     * Track ad impression and credit coins to report owner.
     * report_id is optional - only coin credit on report screens.
     *
     * Event lifecycle:
     *   raw event → fraud evaluation → classification → validated → financial settlement
     *                                              └→ suspicious/rejected → audit only
     */
    public function trackImpression(Request $request): JsonResponse
    {
        $request->validate([
            'ad_campaign_id' => 'required|exists:ad_campaigns,id',
            'report_id' => 'nullable|exists:reports,id',
            'context' => 'nullable|string|max:50',
        ]);

        $campaign = AdCampaign::findOrFail($request->ad_campaign_id);
        $report = $request->report_id ? Report::findOrFail($request->report_id) : null;
        $context = $request->get('context') ?: ($report ? 'report' : 'unknown');
        $isReportScreen = ($report !== null && $context === 'report');

        if (!$this->isServable($campaign)) {
            return response()->json(['success' => false, 'error' => 'Campaign is not active'], 422);
        }

        $fraud = app(FraudDetectionService::class);
        $result = $fraud->checkImpression($request, $campaign);

        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        // Classify event based on fraud evaluation
        $eventStatus = $result['blocked'] ? 'rejected' : 'validated';

        // Database-level idempotency via canonical key
        $idempotencyKey = AdRewardEvent::generateIdempotencyKey('imp', $userId, $campaign->id);

        // Explicit check for idempotency (use raw query to avoid model/caching issues)
        $existingExists = DB::connection()->table('ad_reward_events')
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
        if ($existingExists) {
            return response()->json(['success' => false, 'error' => 'Impression already recorded'], 422);
        }

        // Atomic unit: reward event + raw impression + financial settlement all
        // commit or roll back together (ad/coin tables are InnoDB — see migration
        // 2026_09_28_000003). A crash anywhere (e.g. oversized user_agent) can no
        // longer leave an orphan validated reward event behind.
        try {
            $outcome = DB::transaction(function () use (
                $campaign, $userId, $report, $isReportScreen, $context,
                $request, $eventStatus, $idempotencyKey, $result
            ) {
                $rewardEvent = AdRewardEvent::create([
                    'ad_campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'report_id' => $report?->id,
                    'event_type' => 'impression',
                    'event_status' => $eventStatus,
                    'idempotency_key' => $idempotencyKey,
                    'event_time' => now(),
                    'gross_amount' => 0,
                    'user_share' => 0,
                    'admin_share' => 0,
                    'coins_credited' => 0,
                    'coin_to_npr_rate' => 1,
                    'user_share_percent' => 47,
                ]);

                // Always record raw impression for analytics.
                // The UA is persisted truncated to 500 chars (VARCHAR(500) column);
                // fraud/bot detection above already evaluated the FULL user agent.
                AdImpression::create([
                    'ad_campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'viewed_at' => now(),
                ]);

                // Rejected events: recorded for audit, but return 422 (backward compatible)
                if ($eventStatus === 'rejected') {
                    return ['rejected' => true, 'reasons' => $result['reasons'] ?? []];
                }

                // VALIDATED: financial settlement (same transaction as the event)
                $coinsCredited = 0;

                // 1. Campaign billing — increment counter and recalculate spend
                $campaign->increment('current_impressions');
                $campaign->refresh();
                $this->applySpend($campaign);

                // 2. Calculate gross amount
                $grossAmount = $this->calculateGrossAmount($campaign, 'impression');

                // 3. Credit coins to report owner (report screen only).
                // The authenticated actor ($userId) is passed so the reward layer can
                // enforce the ownership rule: an owner never earns from their own report.
                $coinTransaction = null;
                if ($isReportScreen && $report) {
                    $reportOwner = \App\Models\User::find($report->user_id);
                    if ($reportOwner) {
                        $coinTransaction = app(CoinService::class)->creditImpression($reportOwner, $campaign, $report, $userId);
                    }
                }

                // 4. Revenue ledger — user_share only when coins were actually credited
                $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 47);
                $coinsCredited = $coinTransaction ? (float) $coinTransaction->amount : 0;
                $userShare = ($isReportScreen && $coinsCredited > 0)
                    ? round($grossAmount * ($userSharePercent / 100), 4)
                    : 0;
                $adminShare = $grossAmount - $userShare;

                AdRevenueLedger::create([
                    'ad_campaign_id' => $campaign->id,
                    'report_id' => $report?->id,
                    'context' => $context,
                    'gross_amount' => $grossAmount,
                    'user_share' => $userShare,
                    'admin_share' => $adminShare,
                    'event_type' => 'impression',
                ]);

                // 5. Finalize reward event with financial data
                $coinToNprRate = (float) CoinSetting::getValue('coin_to_npr_rate', 1);
                $rewardEvent->update([
                    'gross_amount' => $grossAmount,
                    'user_share' => $userShare,
                    'admin_share' => $adminShare,
                    'coins_credited' => $coinsCredited,
                    'coin_to_npr_rate' => $coinToNprRate,
                    'user_share_percent' => $userSharePercent,
                    'metadata' => [
                        'ip_address' => $request->ip(),
                        'report_screen' => $isReportScreen,
                    ],
                ]);

                return ['rejected' => false, 'coins_earned' => $coinsCredited];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Concurrent duplicate (unique ad_reward_events.idempotency_key): the
            // transaction already rolled back, nothing was persisted — same 422 as
            // the explicit pre-check above, preserving idempotency semantics.
            return response()->json(['success' => false, 'error' => 'Impression already recorded'], 422);
        }

        if ($outcome['rejected']) {
            return response()->json([
                'success' => false,
                'error' => 'Event blocked',
                'reasons' => $outcome['reasons'],
            ], 422);
        }

        $coinsCredited = $outcome['coins_earned'];

        // Ownership rule: a report owner never earns coins from an ad reward
        // event on their own report. The ad itself was still tracked/billed —
        // only the coin reward is withheld, and the response stays a clean
        // non-error result for the client. (Authoritative enforcement lives in
        // CoinService / FinancialLedgerService — this is response labeling only.)
        $payload = ['success' => true, 'coins_earned' => $coinsCredited];
        if (
            $isReportScreen
            && $report !== null
            && $report->user_id !== null
            && (int) $report->user_id === (int) $userId
        ) {
            $payload['eligibility'] = 'not_eligible';
        }

        return response()->json($payload);
    }

    /**
     * Track ad click and credit coins to report owner.
     * report_id is optional - only coin credit on report screens.
     *
     * Event lifecycle:
     *   raw event → fraud evaluation → classification → validated → financial settlement
     *                                              └→ suspicious/rejected → audit only
     */
    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'ad_campaign_id' => 'required|exists:ad_campaigns,id',
            'report_id' => 'nullable|exists:reports,id',
            'context' => 'nullable|string|max:50',
        ]);

        $campaign = AdCampaign::findOrFail($request->ad_campaign_id);
        $report = $request->report_id ? Report::findOrFail($request->report_id) : null;
        $context = $request->get('context') ?: ($report ? 'report' : 'unknown');
        $isReportScreen = ($report !== null && $context === 'report');

        if (!$this->isServable($campaign)) {
            return response()->json(['success' => false, 'error' => 'Campaign is not active'], 422);
        }

        $fraud = app(FraudDetectionService::class);
        $result = $fraud->checkClick($request, $campaign);

        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated'], 401);
        }

        // Classify event based on fraud evaluation
        $eventStatus = $result['blocked'] ? 'rejected' : 'validated';

        // Database-level idempotency via canonical key
        $idempotencyKey = AdRewardEvent::generateIdempotencyKey('click', $userId, $campaign->id);

        // Explicit check for idempotency (use raw query to avoid model/caching issues)
        $existingExists = DB::connection()->table('ad_reward_events')
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
        if ($existingExists) {
            return response()->json(['success' => false, 'error' => 'Click already recorded'], 422);
        }

        // Atomic unit: reward event + raw click + financial settlement all commit
        // or roll back together (ad/coin tables are InnoDB — migration
        // 2026_09_28_000003). No orphan validated reward event on failure.
        try {
            $outcome = DB::transaction(function () use (
                $campaign, $userId, $report, $isReportScreen, $context,
                $request, $eventStatus, $idempotencyKey, $result
            ) {
                $rewardEvent = AdRewardEvent::create([
                    'ad_campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'report_id' => $report?->id,
                    'event_type' => 'click',
                    'event_status' => $eventStatus,
                    'idempotency_key' => $idempotencyKey,
                    'event_time' => now(),
                    'gross_amount' => 0,
                    'user_share' => 0,
                    'admin_share' => 0,
                    'coins_credited' => 0,
                    'coin_to_npr_rate' => 1,
                    'user_share_percent' => 47,
                ]);

                // Always record raw click for analytics (UA truncated to 500 chars;
                // fraud/bot detection above already evaluated the FULL user agent).
                AdClick::create([
                    'ad_campaign_id' => $campaign->id,
                    'user_id' => $userId,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'clicked_at' => now(),
                ]);

                // Rejected events: recorded for audit, but return 422 (backward compatible)
                if ($eventStatus === 'rejected') {
                    return ['rejected' => true, 'reasons' => $result['reasons'] ?? []];
                }

                // VALIDATED: financial settlement (same transaction as the event)
                $coinsCredited = 0;

                // 1. Ensure impression exists for this click
                $hasImpression = AdImpression::where('ad_campaign_id', $campaign->id)
                    ->when(
                        $userId,
                        fn($q) => $q->where('user_id', $userId),
                        fn($q) => $q->where('ip_address', $request->ip())
                    )
                    ->exists();

                if (!$hasImpression) {
                    // Synthetic impression: record for analytics and billing consistency
                    AdImpression::create([
                        'ad_campaign_id' => $campaign->id,
                        'user_id' => $userId,
                        'ip_address' => $request->ip(),
                        'user_agent' => substr((string) $request->userAgent(), 0, 500),
                        'viewed_at' => now(),
                    ]);
                    $campaign->increment('current_impressions');
                }

                // 2. Campaign billing — increment click counter and recalculate spend
                $campaign->increment('current_clicks');
                $campaign->refresh();
                $this->applySpend($campaign);

                // 3. Calculate gross amount for this click
                $grossAmount = $this->calculateGrossAmount($campaign, 'click');

                // 4. Credit coins to report owner (report screen only).
                // The authenticated actor ($userId) is passed so the reward layer can
                // enforce the ownership rule: an owner never earns from their own report.
                $coinTransaction = null;
                if ($isReportScreen && $report) {
                    $reportOwner = \App\Models\User::find($report->user_id);
                    if ($reportOwner) {
                        $coinTransaction = app(CoinService::class)->creditClick($reportOwner, $campaign, $report, $userId);
                    }
                }

                // 5. Revenue ledger — user_share only when coins were actually credited
                $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 47);
                $coinsCredited = $coinTransaction ? (float) $coinTransaction->amount : 0;
                $userShare = ($isReportScreen && $coinsCredited > 0)
                    ? round($grossAmount * ($userSharePercent / 100), 4)
                    : 0;
                $adminShare = $grossAmount - $userShare;

                AdRevenueLedger::create([
                    'ad_campaign_id' => $campaign->id,
                    'report_id' => $report?->id,
                    'context' => $context,
                    'gross_amount' => $grossAmount,
                    'user_share' => $userShare,
                    'admin_share' => $adminShare,
                    'event_type' => 'click',
                ]);

                // 6. Finalize reward event with financial data
                $coinToNprRate = (float) CoinSetting::getValue('coin_to_npr_rate', 1);
                $rewardEvent->update([
                    'gross_amount' => $grossAmount,
                    'user_share' => $userShare,
                    'admin_share' => $adminShare,
                    'coins_credited' => $coinsCredited,
                    'coin_to_npr_rate' => $coinToNprRate,
                    'user_share_percent' => $userSharePercent,
                    'metadata' => [
                        'ip_address' => $request->ip(),
                        'report_screen' => $isReportScreen,
                    ],
                ]);

                return ['rejected' => false, 'coins_earned' => $coinsCredited];
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Concurrent duplicate (unique ad_reward_events.idempotency_key):
            // transaction rolled back, nothing persisted — same 422 semantics.
            return response()->json(['success' => false, 'error' => 'Click already recorded'], 422);
        }

        if ($outcome['rejected']) {
            return response()->json([
                'success' => false,
                'error' => 'Event blocked',
                'reasons' => $outcome['reasons'],
            ], 422);
        }

        $coinsCredited = $outcome['coins_earned'];

        // Ownership rule: a report owner never earns coins from an ad reward
        // event on their own report. The ad itself was still tracked/billed —
        // only the coin reward is withheld, and the response stays a clean
        // non-error result for the client. (Authoritative enforcement lives in
        // CoinService / FinancialLedgerService — this is response labeling only.)
        $payload = ['success' => true, 'coins_earned' => $coinsCredited];
        if (
            $isReportScreen
            && $report !== null
            && $report->user_id !== null
            && (int) $report->user_id === (int) $userId
        ) {
            $payload['eligibility'] = 'not_eligible';
        }

        return response()->json($payload);
    }

    /**
     * Calculate gross NPR amount for an impression or click.
     */
    private function calculateGrossAmount(AdCampaign $campaign, string $type): float
    {
        if ($type === 'impression') {
            $cpm = (float) $campaign->cost_per_view > 0
                ? (float) $campaign->cost_per_view
                : (float) \App\Models\GameSetting::getValue('ad_cpm', 50);
            return round($cpm / 1000, 4);
        } else {
            $cpc = (float) $campaign->cost_per_click > 0
                ? (float) $campaign->cost_per_click
                : (float) \App\Models\GameSetting::getValue('ad_cpc', 0.50);
            return round($cpc, 4);
        }
    }

    private function weightedSample($campaigns, int $limit, ?string $context, ?string $district, ?string $category, $todayCounts): \Illuminate\Support\Collection
    {
        $pool = $campaigns->values();
        $result = collect();
        $counts = $todayCounts;

        while ($result->count() < $limit && $pool->isNotEmpty()) {
            $weights = [];
            $total = 0.0;
            foreach ($pool as $i => $campaign) {
                $w = $this->adWeight($campaign, $context, $district, $category, (int) ($counts[$campaign->id] ?? 0));
                $weights[$i] = $w;
                $total += $w;
            }
            if ($total <= 0) {
                break;
            }

            $r = mt_rand() / mt_getrandmax() * $total;
            $acc = 0.0;
            $pick = null;
            foreach ($weights as $i => $w) {
                $acc += $w;
                if ($r < $acc) {
                    $pick = $i;
                    break;
                }
            }
            if ($pick === null) {
                $pick = array_key_last($weights);
            }

            $chosen = $pool[$pick];
            $result->push($chosen);
            $counts[$chosen->id] = ((int) ($counts[$chosen->id] ?? 0)) + 1;
            $pool->forget($pick);
            $pool = $pool->values();
        }

        return $result;
    }

    private function adWeight(AdCampaign $campaign, ?string $context, ?string $district, ?string $category, int $todayCount): float
    {
        $score = 0;
        if ($context && $campaign->matchesContext($context)) {
            $score += 3;
        }
        if ($district && $campaign->target_district && strcasecmp($campaign->target_district, $district) === 0) {
            $score += 2;
        }
        if ($category && $campaign->target_category && strcasecmp($campaign->target_category, $category) === 0) {
            $score += 2;
        }
        if (!$campaign->target_district && !$campaign->target_category && empty($campaign->contexts)) {
            $score += 1;
        }

        $pacing = 1.0;
        if ((float) $campaign->budget > 0) {
            $cpm = (float) \App\Models\GameSetting::getValue('ad_cpm', 50);
            $remainingImps = (float) $campaign->budget > 0 && $cpm > 0
                ? (float) max(0, (float) $campaign->budget - (float) $campaign->spent_amount) / $cpm * 1000
                : PHP_INT_MAX;

            $daysLeft = 1;
            if ($campaign->ends_at) {
                $daysLeft = max(1, (int) ceil(now()->diffInDays($campaign->ends_at) ?: 1));
            } else {
                $daysLeft = max(1, (int) ceil(now()->diffInDays(($campaign->starts_at ?? now())->copy()->addDays(30)) ?: 1));
            }

            $quota = max(1, (int) floor($remainingImps / $daysLeft));
            if ($todayCount >= $quota) {
                $pacing = 0.0;
            }
        }

        $avg = $todayCount + 1;
        $fairness = max(0.5, min(2.0, 1 + ((1 - $todayCount) / $avg)));

        return (1 + $score) * $fairness * $pacing;
    }

    private function applySpend(AdCampaign $campaign): void
    {
        $campaign->update(['spent_amount' => $campaign->calculateSpend()]);

        $maxReached = $campaign->max_impressions > 0 && $campaign->current_impressions >= $campaign->max_impressions;
        if ($maxReached || ((float) $campaign->budget > 0 && (float) $campaign->spent_amount >= (float) $campaign->budget)) {
            $campaign->update([
                'status' => 'paused',
                'paused_by' => 'system',
                'rejection_reason' => null,
            ]);
        }
    }

    private function isServable(AdCampaign $campaign): bool
    {
        return $campaign->status === 'active'
            && (!$campaign->starts_at || $campaign->starts_at <= now())
            && (!$campaign->ends_at || $campaign->ends_at > now())
            && $campaign->hasBudget();
    }
}
