<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RewardOffer;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OfferController extends Controller
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
        RewardOffer::expireEnded();
        $status = $request->get('status');
        $deleted = $request->get('deleted') === '1';
        $query = RewardOffer::with('business');
        if ($deleted) {
            $query->onlyTrashed();
        } elseif ($status) {
            $query->where('status', $status);
        }
        $offers = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $stats = [
            'total' => RewardOffer::count(),
            'pending' => RewardOffer::where('status', 'pending')->count(),
            'approved' => RewardOffer::where('status', 'approved')->count(),
            'rejected' => RewardOffer::where('status', 'rejected')->count(),
            'paused' => RewardOffer::where('status', 'paused')->count(),
            'claims' => \App\Models\OfferRedemption::count(),
            'deleted' => RewardOffer::onlyTrashed()->count(),
        ];

        return view('admin.offers', compact('offers', 'status', 'deleted', 'stats'));
    }

    public function updateValue(Request $request, RewardOffer $offer)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'value_npr' => 'required|numeric|min:0|max:1000000',
        ]);
        $offer->update([
            'value_npr' => $data['value_npr'],
            'value_npr_locked' => true,
        ]);
        $this->moderatorService->log(Auth::user(), 'offer.value-updated', 'reward_offer', $offer->id, 'Updated value of offer: ' . $offer->title . ' to Rs. ' . number_format($data['value_npr'], 2));
        return back()->with('success', 'Offer value updated.');
    }

    public function pause(RewardOffer $offer)
    {
        $this->requireAdmin(request());
        $offer->update(['status' => 'paused', 'paused_by' => 'admin']);
        $this->moderatorService->log(Auth::user(), 'offer.paused', 'reward_offer', $offer->id, 'Paused offer: ' . $offer->title);
        return back()->with('success', 'Offer paused.');
    }

    public function resume(RewardOffer $offer)
    {
        $this->requireAdmin(request());
        if (($reason = $this->cannotRunReason($offer)) !== null) {
            return back()->withErrors(['status' => 'Cannot resume: ' . $reason]);
        }
        $offer->update(['status' => 'approved', 'paused_by' => null]);
        $this->moderatorService->log(Auth::user(), 'offer.resumed', 'reward_offer', $offer->id, 'Resumed offer: ' . $offer->title);
        return back()->with('success', 'Offer resumed.');
    }

    public function destroy(Request $request, RewardOffer $offer)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'reason' => 'required|string|min:3|max:1000',
        ]);
        $offer->update(['admin_removed_reason' => $data['reason']]);
        $offer->delete();
        $this->moderatorService->log(Auth::user(), 'offer.deleted', 'reward_offer', $offer->id, 'Deleted offer: ' . $offer->title . ' — ' . $data['reason']);
        return back()->with('success', 'Offer deleted. The partner can see the removal reason on their dashboard.');
    }

    public function restore(RewardOffer $offer)
    {
        $this->requireAdmin(request());
        $offer->restore();
        $offer->update(['admin_removed_reason' => null]);
        $this->moderatorService->log(Auth::user(), 'offer.restored', 'reward_offer', $offer->id, 'Restored offer: ' . $offer->title);
        return back()->with('success', 'Offer restored.');
    }

    private function cannotRunReason(RewardOffer $offer): ?string
    {
        if ($offer->paused_by === 'system' || $offer->isEnded()) {
            return 'the offer has ended and is locked by the system.';
        }
        return null;
    }
}
