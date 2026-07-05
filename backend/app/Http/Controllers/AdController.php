<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Models\AdImpression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdController extends Controller
{
    public function active(): JsonResponse
    {
        $campaigns = AdCampaign::with('business')
            ->active()
            ->where(function ($q) {
                $q->where('max_impressions', 0)
                    ->orWhereColumn('current_impressions', '<', 'max_impressions');
            })
            ->get()
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
                    'business_name' => $campaign->business?->name,
                ];
            });

        return response()->json(['data' => $campaigns]);
    }

    public function trackImpression(Request $request): JsonResponse
    {
        $request->validate(['ad_campaign_id' => 'required|exists:ad_campaigns,id']);

        $campaign = AdCampaign::findOrFail($request->ad_campaign_id);

        AdImpression::create([
            'ad_campaign_id' => $campaign->id,
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'viewed_at' => now(),
        ]);

        $campaign->increment('current_impressions');

        return response()->json(['success' => true]);
    }
}
