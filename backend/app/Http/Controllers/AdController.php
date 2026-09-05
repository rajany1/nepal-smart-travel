<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
use App\Models\AdRevenueLedger;
use App\Models\Report;
use App\Models\CoinSetting;
use App\Services\FraudDetectionService;
use App\Services\CoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 70);

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
        if ($result['blocked']) {
            return response()->json(['success' => false, 'error' => 'Event blocked', 'reasons' => $result['reasons']], 422);
        }

        $userId = Auth::id();

        $recent = AdImpression::where('ad_campaign_id', $campaign->id)
            ->where('viewed_at', '>=', now()->subMinutes(10))
            ->when(
                $userId,
                fn($q) => $q->where('user_id', $userId),
                fn($q) => $q->where('ip_address', $request->ip())
            )
            ->exists();

        if ($recent) {
            return response()->json(['success' => false, 'error' => 'Impression already recorded'], 422);
        }

        AdImpression::create([
            'ad_campaign_id' => $campaign->id,
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'viewed_at' => now(),
        ]);

        $campaign->increment('current_impressions');
        $this->applySpend($campaign);

        // Calculate gross amount for this impression
        $grossAmount = $this->calculateGrossAmount($campaign, 'impression');

        // Credit coins to report owner (report screen only)
        $coinService = app(CoinService::class);
        $coinTransaction = null;

        if ($isReportScreen && $report) {
            $reportOwner = \App\Models\User::find($report->user_id);
            if ($reportOwner) {
                $coinTransaction = $coinService->creditImpression($reportOwner, $campaign, $report);
            }
        }

        // Record revenue in ledger
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 70);
        $userShare = $isReportScreen ? round($grossAmount * ($userSharePercent / 100), 4) : 0;
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

        return response()->json([
            'success' => true,
            'coins_earned' => $coinTransaction ? (float) $coinTransaction->amount : 0,
        ]);
    }

    /**
     * Track ad click and credit coins to report owner.
     * report_id is optional - only coin credit on report screens.
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
        if ($result['blocked']) {
            return response()->json(['success' => false, 'error' => 'Event blocked', 'reasons' => $result['reasons']], 422);
        }

        $userId = Auth::id();

        // Ensure impression exists before recording click
        $hasImpression = AdImpression::where('ad_campaign_id', $campaign->id)
            ->when(
                $userId,
                fn($q) => $q->where('user_id', $userId),
                fn($q) => $q->where('ip_address', $request->ip())
            )
            ->exists();

        if (!$hasImpression) {
            // Auto-record impression first
            AdImpression::create([
                'ad_campaign_id' => $campaign->id,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'viewed_at' => now(),
            ]);
            $campaign->increment('current_impressions');
            $this->applySpend($campaign);
        }

        $recent = AdClick::where('ad_campaign_id', $campaign->id)
            ->where('clicked_at', '>=', now()->subMinutes(10))
            ->when(
                $userId,
                fn($q) => $q->where('user_id', $userId),
                fn($q) => $q->where('ip_address', $request->ip())
            )
            ->exists();

        if ($recent) {
            return response()->json(['success' => false, 'error' => 'Click already recorded'], 422);
        }

        AdClick::create([
            'ad_campaign_id' => $campaign->id,
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'clicked_at' => now(),
        ]);

        $campaign->increment('current_clicks');
        $this->applySpend($campaign);

        // Calculate gross amount for this click
        $grossAmount = $this->calculateGrossAmount($campaign, 'click');

        // Credit coins to report owner (report screen only)
        $coinService = app(CoinService::class);
        $coinTransaction = null;

        if ($isReportScreen && $report) {
            $reportOwner = \App\Models\User::find($report->user_id);
            if ($reportOwner) {
                $coinTransaction = $coinService->creditClick($reportOwner, $campaign, $report);
            }
        }

        // Record revenue in ledger
        $userSharePercent = (float) CoinSetting::getValue('user_share_percent', 70);
        $userShare = $isReportScreen ? round($grossAmount * ($userSharePercent / 100), 4) : 0;
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

        return response()->json([
            'success' => true,
            'coins_earned' => $coinTransaction ? (float) $coinTransaction->amount : 0,
        ]);
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
                : (float) \App\Models\GameSetting::getValue('ad_cpc', 10);
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
