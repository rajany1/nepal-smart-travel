<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdController extends Controller
{
    private function partner()
    {
        return auth()->user()->business;
    }

    public function index(Request $request)
    {
        $partner = $this->partner();
        $status = $request->get('status');
        $query = $partner->adCampaigns()->withCount('impressions', 'clicks');
        if ($status) $query->where('status', $status);
        $campaigns = $query->orderBy('created_at', 'desc')->paginate(15);

        $stats = [
            'total' => $partner->adCampaigns()->count(),
            'active' => $partner->adCampaigns()->where('status', 'active')->count(),
            'pending' => $partner->adCampaigns()->where('status', 'pending')->count(),
            'impressions' => (int) $partner->adCampaigns()->sum('current_impressions'),
            'clicks' => (int) $partner->adCampaigns()->sum('current_clicks'),
            'spent' => (float) $partner->adCampaigns()->sum('spent_amount'),
            'paid' => (float) $partner->adCampaigns()->sum('paid_amount'),
            'unpaid' => $partner->adCampaigns()->where('payment_status', '!=', 'paid')->count(),
        ];
        $stats['spent'] = round($stats['spent'], 2);
        $stats['paid'] = round($stats['paid'], 2);
        $stats['ctr'] = $stats['impressions'] > 0 ? round($stats['clicks'] / $stats['impressions'] * 100, 2) : 0;

        return view('partner.ads', compact('campaigns', 'status', 'stats'));
    }

    public function create()
    {
        return view('partner.ad_form', ['adCampaign' => new AdCampaign(), 'partner' => $this->partner()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['cost_per_view'] = $data['cost_per_view'] ?? 0;
        $data['cost_per_click'] = $data['cost_per_click'] ?? 0;

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('ad-images/' . $this->partner()->id, 'public');
        }
        unset($data['image_file']);

        $campaign = $this->partner()->adCampaigns()->create($data + [
            'paid_amount' => 0,
            'spent_amount' => 0,
            'payment_status' => 'unpaid',
            'current_impressions' => 0,
            'current_clicks' => 0,
            'status' => 'pending',
        ]);

        return $this->safetyRespond($request, $campaign, $data, 'Ad campaign created. Add a budget and pay to start running it.');
    }

    public function edit(AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        return view('partner.ad_form', ['adCampaign' => $adCampaign, 'partner' => $this->partner()]);
    }

    public function update(Request $request, AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        if ($adCampaign->payment_status === 'paid') {
            return back()->withErrors(['status' => 'Paid campaigns cannot be edited. Pause or delete it first.']);
        }
        if ($adCampaign->status === 'active') {
            return back()->withErrors(['status' => 'Active campaigns cannot be edited. Pause or delete it first.']);
        }
        $data = $this->validated($request);

        if ($request->hasFile('image_file')) {
            if ($adCampaign->image && Storage::disk('public')->exists($adCampaign->image)) {
                Storage::disk('public')->delete($adCampaign->image);
            }
            $data['image'] = $request->file('image_file')->store('ad-images/' . $this->partner()->id, 'public');
        }
        unset($data['image_file']);

        $adCampaign->update($data);
        return $this->safetyRespond($request, $adCampaign, $data, 'Ad campaign updated.');
    }

    /**
     * Review AI agent guard for partner content. Censors in place, records the
     * violation and escalates warn -> suspend -> block on repeat offenses.
     */
    private function safetyRespond($request, $entity, array $data, string $successMessage)
    {
        $safety = app(\App\Services\ContentSafetyService::class);
        $payloads = [];
        foreach (['name', 'content'] as $field) {
            $text = (string) ($data[$field] ?? '');
            $g = $safety->guard(auth()->user(), $text, 'ad_campaign', $entity->id, $field, 'realtime');
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

        $response = redirect()->route('partner.ads')->with('success', $successMessage);
        if ($payload['censored']) {
            $response->with('warning', $payload['warning']);
        }
        return $response;
    }

    public function pause(AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        $adCampaign->update(['status' => 'paused', 'paused_by' => 'partner']);
        return back()->with('success', 'Campaign paused.');
    }

    public function resume(AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        if ($adCampaign->paused_by === 'admin') {
            return back()->withErrors(['status' => 'This campaign was paused by the admin. Contact admin to resume.']);
        }
        if ($adCampaign->payment_status !== 'paid' && (float) $adCampaign->budget > 0) {
            return back()->withErrors(['status' => 'Pay the campaign budget first — free campaigns are created by the admin only.']);
        }
        if (!$adCampaign->hasBudget()) {
            return back()->withErrors(['status' => 'The budget is exhausted. Create a new campaign to run more ads.']);
        }
        if ($adCampaign->ends_at && $adCampaign->ends_at->lte(now())) {
            return back()->withErrors(['status' => 'The campaign has ended and cannot be resumed.']);
        }
        $adCampaign->update(['status' => 'active', 'paused_by' => null]);
        return back()->with('success', 'Campaign resumed and is live again.');
    }

    public function destroy(AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        if ((float) $adCampaign->paid_amount > (float) $adCampaign->spent_amount) {
            return back()->withErrors(['status' => 'This campaign has paid money remaining (Rs. ' . number_format(max((float) $adCampaign->paid_amount - (float) $adCampaign->spent_amount, 0), 2) . '). Contact admin to delete it.']);
        }
        if ($adCampaign->image && Storage::disk('public')->exists($adCampaign->image)) {
            Storage::disk('public')->delete($adCampaign->image);
        }
        $adCampaign->delete();
        return redirect()->route('partner.ads')->with('success', 'Campaign deleted.');
    }

    public function pay(AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        if ($adCampaign->payment_status === 'paid' && (float) $adCampaign->paid_amount > 0) {
            return redirect()->route('partner.ads')->with('success', 'This campaign is already paid.');
        }
        if ((float) $adCampaign->budget <= 0) {
            return redirect()->route('partner.ads.edit', $adCampaign)->withErrors(['budget' => 'Set a budget for this campaign before paying.']);
        }
        return view('partner.ad_pay', compact('adCampaign'));
    }

    public function initiatePayment(Request $request, AdCampaign $adCampaign)
    {
        $this->authorizeAd($adCampaign);
        if ($adCampaign->payment_status === 'paid') {
            return back()->withErrors(['payment' => 'This campaign is already paid.']);
        }

        $gateway = $request->validate(['gateway' => 'required|in:esewa,khalti'])['gateway'];
        $amount = (float) $adCampaign->budget;
        if ($amount <= 0) {
            return back()->withErrors(['budget' => 'Set a budget before paying.']);
        }

        $payment = $adCampaign->payments()->create([
            'business_id' => $this->partner()->id,
            'gateway' => $gateway,
            'amount' => $amount,
            'status' => 'pending',
            'reference' => 'adpay-' . $adCampaign->id . '-' . strtoupper(Str::random(6)),
        ]);

        $service = new \App\Services\PaymentGatewayService();

        if ($gateway === 'esewa') {
            $form = $service->eSewaForm($amount, $payment->reference, route('partner.payments.esewa.callback'), route('partner.payments.esewa.callback'));
            return view('partner.gateway_redirect', ['html' => $form]);
        }

        $result = $service->initiateKhalti($amount, $payment->reference, route('partner.payments.khalti.callback'));
        if (!$result['success']) {
            $payment->update(['status' => 'failed', 'metadata' => ['error' => $result['message']]]);
            return back()->withErrors(['payment' => $result['message']]);
        }

        $payment->update(['transaction_id' => $result['pidx'], 'metadata' => ['pidx' => $result['pidx']]]);
        return redirect()->away($result['payment_url']);
    }

    public function esewaCallback(Request $request)
    {
        try {
            $data = json_decode(base64_decode($request->get('data', '')), true);
        } catch (\Throwable $e) {
            return redirect()->route('partner.ads')->withErrors(['payment' => 'Invalid payment callback.']);
        }

        if (!is_array($data)) {
            return redirect()->route('partner.ads')->withErrors(['payment' => 'Invalid payment callback.']);
        }

        $payment = \App\Models\AdPayment::where('reference', $data['transaction_uuid'] ?? '')->first();
        if (!$payment || $payment->status === 'success') {
            return redirect()->route('partner.ads');
        }

        $service = app(\App\Services\PaymentGatewayService::class);
        $verified = $service->verifyESewa(
            $data['product_code'] ?? '',
            (float) ($data['total_amount'] ?? 0),
            $data['transaction_id'] ?? '',
            $data['transaction_uuid'] ?? '',
        );

        if (!$verified['success']) {
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['verify_error' => $verified['message']])]);
            return redirect()->route('partner.ads')->withErrors(['payment' => $verified['message']]);
        }

        $this->markPaid($payment, $verified['transaction_id']);
        return redirect()->route('partner.ads')->with('success', 'Payment received! Your campaign is now live.');
    }

    public function khaltiCallback(Request $request)
    {
        $pidx = $request->get('pidx');
        $payment = \App\Models\AdPayment::where('transaction_id', $pidx)->first();
        if (!$payment || $payment->status === 'success') {
            return redirect()->route('partner.ads');
        }

        $service = app(\App\Services\PaymentGatewayService::class);
        try {
            $verified = $service->verifyKhalti($pidx);
        } catch (\Throwable $e) {
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $e->getMessage()])]);
            return redirect()->route('partner.ads')->withErrors(['payment' => 'Khalti verification failed.']);
        }

        if (!$verified['success']) {
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $verified['message']])]);
            return redirect()->route('partner.ads')->withErrors(['payment' => $verified['message']]);
        }

        $this->markPaid($payment, $verified['transaction_id']);
        return redirect()->route('partner.ads')->with('success', 'Payment received! Your campaign is now live.');
    }

    public function markPaid(\App\Models\AdPayment $payment, string $transactionId): void
    {
        $payment->update([
            'status' => 'success',
            'transaction_id' => $transactionId,
            'paid_at' => now(),
        ]);
        $payment->campaign?->update([
            'payment_status' => 'paid',
            'paid_amount' => $payment->amount,
            'gateway' => $payment->gateway,
            'gateway_ref' => $transactionId,
            'status' => 'active',
            'starts_at' => $payment->campaign->starts_at ?? now(),
        ]);
    }

    private function authorizeAd(AdCampaign $adCampaign): void
    {
        if ($adCampaign->business_id !== $this->partner()->id) abort(403);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'content' => 'nullable|string|max:2000',
            'image_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'target_url' => 'nullable|string|max:255',
            'target_district' => 'nullable|string|max:100',
            'target_category' => 'nullable|string|max:100',
            'contexts' => 'nullable|array',
            'contexts.*' => 'in:home,explore,nearby,place_detail,report,hotels,restaurants,attractions,cafes,activities',
            'budget' => 'required|numeric|min:100',
            'max_impressions' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'ad_type' => 'nullable|in:banner,promoted_place,sponsored_card',
        ]);

        $data['contexts'] = $request->has('contexts') ? array_values($request->input('contexts', [])) : null;
        $data['ad_type'] = $data['ad_type'] ?? 'banner';
        $data['max_impressions'] = $data['max_impressions'] ?? 0;
        return $data;
    }
}
