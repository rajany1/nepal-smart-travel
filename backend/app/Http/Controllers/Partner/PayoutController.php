<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\GameSetting;
use App\Models\Payout;
use App\Models\TravelPartner;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    private function partner(): TravelPartner
    {
        $partner = auth()->user()->business;
        abort_unless($partner, 403, 'Business profile required.');
        return $partner;
    }

    public function index()
    {
        $partner = $this->partner();
        $balance = $partner->payoutBalance();
        $earned = $partner->offerEarned();
        $paid = (float) $partner->payouts()->where('status', 'paid')->sum('amount');
        $pending = (float) $partner->payouts()->where('status', 'pending')->sum('amount');
        $payouts = $partner->payouts()->latest('id')->paginate(20);
        $minThresholds = $this->minThresholds();

        return view('partner.payouts', compact('partner', 'balance', 'earned', 'paid', 'pending', 'payouts', 'minThresholds'));
    }

    private function minThresholds(): array
    {
        return [
            'esewa' => (float) GameSetting::getValue('payout_min_esewa', 100),
            'khalti' => (float) GameSetting::getValue('payout_min_khalti', 100),
            'bank' => (float) GameSetting::getValue('payout_min_bank', 500),
        ];
    }

    public function store(Request $request)
    {
        $partner = $this->partner();
        $data = $request->validate([
            'payment_method' => 'required|in:esewa,khalti,bank',
        ]);

        $min = $this->minThresholds()[$data['payment_method']];
        $data += $request->validate([
            'amount' => 'required|numeric|min:' . $min . '|max:1000000',
            'payment_detail' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $balance = $partner->payoutBalance();
        if ((float) $data['amount'] > $balance) {
            return back()->withErrors(['amount' => 'Amount exceeds your available balance (Rs. ' . number_format($balance, 2) . ').'])
                ->withInput();
        }

        $partner->payouts()->create([
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'payment_detail' => $data['payment_detail'] ?? null,
            'note' => $data['note'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return redirect()->route('partner.payouts')->with('success', 'Payout requested. Admin will review it.');
    }

    public function cancel(Payout $payout)
    {
        $partner = $this->partner();
        abort_if($payout->travel_partner_id !== $partner->id, 403);
        abort_if($payout->status !== 'pending', 422, 'Only pending payouts can be cancelled.');

        $payout->delete();
        return back()->with('success', 'Payout request cancelled.');
    }
}
