<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\TravelPartner;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdCampaignController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) abort(403, 'Unauthorized');
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $status = $request->get('status');
        $query = AdCampaign::with('business');
        if ($status) $query->where('status', $status);
        $campaigns = $query->orderBy('created_at', 'desc')->paginate(20);
        $partners = TravelPartner::active()->orderBy('name')->get();
        return view('admin.ad_campaigns', compact('campaigns', 'partners', 'status'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);
        $campaign = AdCampaign::create($request->validate([
            'name' => 'required|string|max:255',
            'business_id' => 'nullable|exists:travel_partners,id',
            'ad_type' => 'required|in:banner,promoted_place,sponsored_card',
            'content' => 'nullable|string',
            'target_url' => 'nullable|string|max:255',
            'target_district' => 'nullable|string|max:100',
            'target_category' => 'nullable|string|max:100',
            'budget' => 'required|numeric|min:0',
            'cost_per_view' => 'required|numeric|min:0',
            'max_impressions' => 'required|integer|min:0',
            'status' => 'required|in:pending,active,paused,rejected',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]));
        $this->moderatorService->log(Auth::user(), 'ad-campaign.created', 'ad_campaign', $campaign->id, 'Created campaign: ' . $campaign->name);
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign created.');
    }

    public function update(Request $request, AdCampaign $adCampaign)
    {
        $this->requireAdmin($request);
        $oldStatus = $adCampaign->status;
        $adCampaign->update($request->validate([
            'name' => 'required|string|max:255',
            'business_id' => 'nullable|exists:travel_partners,id',
            'ad_type' => 'required|in:banner,promoted_place,sponsored_card',
            'content' => 'nullable|string',
            'target_url' => 'nullable|string|max:255',
            'target_district' => 'nullable|string|max:100',
            'target_category' => 'nullable|string|max:100',
            'budget' => 'required|numeric|min:0',
            'cost_per_view' => 'required|numeric|min:0',
            'max_impressions' => 'required|integer|min:0',
            'status' => 'required|in:pending,active,paused,rejected',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]));
        $this->moderatorService->log(Auth::user(), 'ad-campaign.updated', 'ad_campaign', $adCampaign->id, 'Updated campaign: ' . $adCampaign->name . ' (status: ' . $oldStatus . ' → ' . $adCampaign->status . ')');
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign updated.');
    }

    public function destroy(Request $request, AdCampaign $adCampaign)
    {
        $this->requireAdmin($request);
        $name = $adCampaign->name;
        $adCampaign->delete();
        $this->moderatorService->log(Auth::user(), 'ad-campaign.deleted', 'ad_campaign', $adCampaign->id, 'Deleted campaign: ' . $name);
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign deleted.');
    }
}
