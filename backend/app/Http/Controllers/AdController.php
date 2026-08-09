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
            ->sortByDesc(function ($campaign) use ($context, $district, $category) {
                $score = 0;
                if ($context && $campaign->matchesContext($context)) $score += 3;
                if ($district && $campaign->target_district && strcasecmp($campaign->target_district, $district) === 0) $score += 2;
                if ($category && $campaign->target_category && strcasecmp($campaign->target_category, $category) === 0) $score += 2;
                if (!$campaign->target_district && !$campaign->target_category && empty($campaign->contexts)) $score += 1;
                return $score;
            })
            ->take($limit)
            ->values()
            ->map(function ($campaign) {
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
            });

        return response()->json(['data' => $campaigns]);
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