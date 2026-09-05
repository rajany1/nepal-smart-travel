<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\GameSetting;
use App\Models\OfferRedemption;
use App\Models\PartnerWallet;
use App\Models\RewardOffer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends Controller
{
    private function partner()
    {
        return auth()->user()->business;
    }

    private function priceXp(?float $discountValue): int
    {
        $ratio = (float) GameSetting::getValue('xp_per_npr_ratio', 1);
        return max(1, (int) round((float) ($discountValue ?? 0) * $ratio));
    }

    public function index(Request $request)
    {
        RewardOffer::expireEnded();
        $partner = $this->partner();
        $status = $request->get('status');
        $query = $partner->offers()->withTrashed();
        if ($status) $query->where('status', $status);
        $offers = $query->withCount('redemptions')->orderBy('created_at', 'desc')->paginate(15);

        $stats = [
            'total' => $partner->offers()->withTrashed()->count(),
            'approved' => $partner->offers()->where('status', 'approved')->count(),
            'pending' => $partner->offers()->where('status', 'pending')->count(),
            'claims' => OfferRedemption::whereIn('offer_id', $partner->offers()->pluck('id'))->count(),
        ];

        return view('partner.offers', compact('offers', 'status', 'stats'));
    }

    public function create()
    {
        return view('partner.offer_form', ['offer' => new RewardOffer()]);
    }

public function store(Request $request)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('offer-images/' . $this->partner()->id, 'public');
        }

        $offer = $this->partner()->offers()->create($data + [
            'price_xp' => $this->priceXp($data['discount_value'] ?? null),
            'status' => 'approved',
            'used_count' => 0,
            'created_by' => auth()->id(),
        ]);

        return $this->safetyRespond($request, $offer, $data, 'Offer created and is now live!', 'reward_offer', ['title', 'description', 'terms'], 'partner.offers');
    }

    public function edit(RewardOffer $offer)
    {
        $this->authorizeOffer($offer);
        return view('partner.offer_form', compact('offer'));
    }

    public function update(Request $request, RewardOffer $offer)
    {
        $this->authorizeOffer($offer);
        if ($offer->isSystemLocked()) {
            return back()->withErrors(['status' => 'This offer has ended and is locked by the system. It cannot be changed.']);
        }
        if (in_array($offer->status, ['approved'])) {
            return back()->withErrors(['status' => 'Approved offers cannot be edited. Pause or delete it first.']);
        }
$data = $this->validated($request) + ['price_xp' => $this->priceXp($request->discount_value ?? null)];
        if ($offer->value_npr_locked) {
            $data['value_npr'] = $offer->value_npr;
        }
        if ($request->hasFile('image')) {
            if ($offer->image && \Storage::disk('public')->exists($offer->image)) {
                \Storage::disk('public')->delete($offer->image);
            }
            $data['image'] = $request->file('image')->store('offer-images/' . $this->partner()->id, 'public');
        }
        $offer->update($data);
        return $this->safetyRespond($request, $offer, $data, 'Offer updated.', 'reward_offer', ['title', 'description', 'terms'], 'partner.offers');
    }

    /**
     * Review AI agent guard for partner content. Censors in place, records the
     * violation and escalates warn -> suspend -> block on repeat offenses.
     */
    private function safetyRespond($request, $entity, array $data, string $successMessage, string $entityType, array $fields, string $redirectRoute)
    {
        $safety = app(\App\Services\ContentSafetyService::class);
        $payloads = [];
        foreach ($fields as $field) {
            $text = (string) ($data[$field] ?? '');
            $g = $safety->guard(auth()->user(), $text, $entityType, $entity->id, $field, 'realtime');
            if ($g['action'] === 'censored') {
                $entity->update([$field => $g['text']]);
            }
            $payloads[] = $g;
        }
        $payload = $safety->payload($payloads);

        if (in_array($payload['account'], ['suspended', 'banned'])) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect('/admin/login')->with('error', $payload['warning']);
        }

        $response = redirect()->route($redirectRoute)->with('success', $successMessage);
        if ($payload['censored']) {
            $response->with('warning', $payload['warning']);
        }
        return $response;
    }

    public function pause(RewardOffer $offer)
    {
        $this->authorizeOffer($offer);
        if ($offer->isSystemLocked()) {
            return back()->withErrors(['status' => 'This offer has ended and is locked by the system.']);
        }
        $offer->update(['status' => 'paused', 'paused_by' => 'partner']);
        return back()->with('success', 'Offer paused.');
    }

    public function resume(RewardOffer $offer)
    {
        $this->authorizeOffer($offer);
        if ($offer->isSystemLocked()) {
            return back()->withErrors(['status' => 'This offer has ended and is locked by the system.']);
        }
        $offer->update(['status' => 'approved', 'paused_by' => null]);
        return back()->with('success', 'Offer resumed.');
    }

    public function destroy(RewardOffer $offer)
    {
        $this->authorizeOffer($offer);
        if ($offer->trashed()) abort(404);
        $offer->delete();
        return redirect()->route('partner.offers')->with('success', 'Offer deleted.');
    }

    public function redemptions(RewardOffer $offer)
    {
        $this->authorizeOffer($offer);
        $redemptions = $offer->redemptions()->with('user')->orderBy('created_at', 'desc')->paginate(20);
        return view('partner.redemptions', compact('offer', 'redemptions'));
    }

    public function markUsed(Request $request, RewardOffer $offer, OfferRedemption $redemption)
    {
        $this->authorizeOffer($offer);
        if ($redemption->offer_id !== $offer->id) abort(404);
        if ($redemption->consumed_at || $redemption->status === 'used') {
            return back()->withErrors(['redemption' => 'Code already marked as used.']);
        }

        DB::beginTransaction();
        try {
            $redemption->update([
                'consumed_at' => now(),
                'used_at' => now(),
                'status' => 'used',
            ]);

            if ((float) ($redemption->partner_earnings ?? 0) > 0) {
                $wallet = PartnerWallet::getForPartner($this->partner()->id);
                $wallet->credit((float) $redemption->partner_earnings);
            }

            DB::commit();
            return back()->with('success', 'Code marked as used. Rs. ' . number_format($redemption->partner_earnings ?? 0, 2) . ' credited to wallet!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['redemption' => 'Error processing. Try again.']);
        }
    }

    private function authorizeOffer(RewardOffer $offer): void
    {
        if ($offer->business_id !== $this->partner()->id) abort(403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255',
            'offer_type' => 'required|in:percentage_off,fixed_off,free_item,buy_one_get_one',
            'discount_value' => 'nullable|numeric|min:0',
            'value_npr' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:2000',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);
    }
}
