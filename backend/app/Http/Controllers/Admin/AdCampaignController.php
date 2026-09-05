<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdRevenueLedger;
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

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $status = $request->get('status');
        $query = AdCampaign::with('business');
        if ($status === 'flagged') {
            $query->where('fraud_score', '>=', 80);
        } elseif ($status) {
            $query->where('status', $status);
        }
        $campaigns = $query->orderBy('created_at', 'desc')->paginate(20);
        $partners = TravelPartner::active()->orderBy('name')->get();

        $all = AdCampaign::all();
        $totalAdminRevenue = AdRevenueLedger::where('admin_share', '>', 0)->sum('admin_share');
        $totalUserPayout = AdRevenueLedger::where('user_share', '>', 0)->sum('user_share');
        $stats = [
            'total' => $all->count(),
            'pending' => $all->where('status', 'pending')->count(),
            'active' => $all->where('status', 'active')->count(),
            'flagged' => $all->filter(fn($c) => $c->fraud_score >= 80)->count(),
            'impressions' => (int) $all->sum('current_impressions'),
            'clicks' => (int) $all->sum('current_clicks'),
            'revenue' => 0,
            'admin_revenue' => round($totalAdminRevenue, 2),
            'user_payout' => round($totalUserPayout, 2),
            'unpaid' => 0,
            'ctr' => 0,
        ];
        foreach ($all as $c) if ($c->payment_status === 'paid') $stats['revenue'] += (float) $c->paid_amount;
        $stats['revenue'] = round($stats['revenue'], 2);
        $stats['unpaid'] = $all->where('payment_status', 'unpaid')->count();
        if ($stats['impressions'] > 0) $stats['ctr'] = round($stats['clicks'] / $stats['impressions'] * 100, 2);
        return view('admin.ad_campaigns', compact('campaigns', 'partners', 'status', 'stats'));
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
            'cost_per_click' => 'nullable|numeric|min:0',
            'max_impressions' => 'required|integer|min:0',
            'status' => 'required|in:pending,active,paused',
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
            'cost_per_click' => 'nullable|numeric|min:0',
            'max_impressions' => 'required|integer|min:0',
            'status' => 'required|in:pending,active,paused',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ]));

        if ($adCampaign->status === 'active' && ($reason = $this->cannotRunReason($adCampaign)) !== null) {
            $adCampaign->update(['status' => $oldStatus === 'pending' ? 'pending' : 'paused']);
            return back()->withErrors(['status' => 'Cannot set active: ' . $reason]);
        }
        if ($adCampaign->status !== 'paused' && $adCampaign->paused_by) {
            $adCampaign->update(['paused_by' => null]);
        }

        $this->moderatorService->log(Auth::user(), 'ad-campaign.updated', 'ad_campaign', $adCampaign->id, 'Updated campaign: ' . $adCampaign->name . ' (status: ' . $oldStatus . ' → ' . $adCampaign->status . ')');
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign updated.');
    }

    public function destroy(Request $request, AdCampaign $adCampaign)
    {
        $this->requireAdmin($request);
        $name = $adCampaign->name;
        $adCampaign->delete();
        \App\Support\LiveFeed::bump('ad_campaigns', $adCampaign->id);
        $this->moderatorService->log(Auth::user(), 'ad-campaign.deleted', 'ad_campaign', $adCampaign->id, 'Deleted campaign: ' . $name);
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign deleted.');
    }

    public function pause(Request $request, AdCampaign $adCampaign)
    {
        $this->requireAdmin($request);
        $adCampaign->update(['status' => 'paused', 'paused_by' => 'admin']);
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign paused.');
    }

    public function resume(Request $request, AdCampaign $adCampaign)
    {
        $this->requireAdmin($request);
        if (($reason = $this->cannotRunReason($adCampaign)) !== null) {
            return back()->withErrors(['status' => 'Cannot resume: ' . $reason]);
        }
        $adCampaign->update(['status' => 'active', 'paused_by' => null]);
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign resumed.');
    }

    private function cannotRunReason(AdCampaign $adCampaign): ?string
    {
        if ($adCampaign->payment_status === 'refunded') {
            return 'the campaign was refunded.';
        }
        if ($adCampaign->payment_status !== 'paid' && (float) $adCampaign->budget > 0) {
            return 'payment is pending.';
        }
        if (!$adCampaign->hasBudget()) {
            return 'the budget is exhausted.';
        }
        if ($adCampaign->ends_at && $adCampaign->ends_at->lte(now())) {
            return 'the campaign has ended.';
        }
        return null;
    }

    public function refund(Request $request, AdCampaign $adCampaign)
    {
        $this->requireAdmin($request);
        if ($adCampaign->payment_status !== 'paid') {
            return back()->withErrors(['status' => 'Only paid campaigns can be refunded.']);
        }
        $paid = (float) $adCampaign->paid_amount;
        $spent = (float) $adCampaign->spent_amount;
        $refundAmount = round(max($paid - $spent, 0), 2);

        $adCampaign->payments()->where('status', 'success')->update(['status' => 'refunded']);
        $adCampaign->update([
            'payment_status' => 'refunded',
            'status' => 'paused',
            'paused_by' => 'admin',
        ]);
        $this->moderatorService->log(Auth::user(), 'ad-campaign.refunded', 'ad_campaign', $adCampaign->id, 'Refunded campaign: ' . $adCampaign->name . ' (Rs. ' . $refundAmount . ')');
        return redirect()->route('admin.ad-campaigns')->with('success', 'Campaign refunded (Rs. ' . $refundAmount . ') and paused.');
    }
}
