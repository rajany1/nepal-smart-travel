<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdClick;
use App\Models\AdImpression;
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
        $cap = (int) \App\Models\GameSetting::getValue('ad_freq_cap', 3);
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
                'image' => $campaign->image,
                'target_url' => $campaign->target_url,
                'target_district' => $campaign->target_district,
                'target_category' => $campaign->target_category,
                'contexts' => $campaign->contexts ?? [],
                'business_name' => $campaign->business?->name,
            ];
        })->values()]);
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

    public function trackImpression(Request $request): JsonResponse
    {
        $request->validate(['ad_campaign_id' => 'required|exists:ad_campaigns,id']);

        $campaign = AdCampaign::findOrFail($request->ad_campaign_id);

        if (!$this->isServable($campaign)) {
            return response()->json(['success' => false, 'error' => 'Campaign is not active'], 422);
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

        return response()->json(['success' => true]);
    }

    public function trackClick(Request $request): JsonResponse
    {
        $request->validate(['ad_campaign_id' => 'required|exists:ad_campaigns,id']);

        $campaign = AdCampaign::findOrFail($request->ad_campaign_id);

        if (!$this->isServable($campaign)) {
            return response()->json(['success' => false, 'error' => 'Campaign is not active'], 422);
        }

        $userId = Auth::id();

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

        return response()->json(['success' => true]);
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