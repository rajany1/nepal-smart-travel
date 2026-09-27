<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\GameSetting;
use App\Models\Payout;
use App\Models\PaymentTransaction;
use App\Models\TravelPartner;
use App\Services\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutController extends Controller
{
    private PaymentProcessor $paymentProcessor;

    public function __construct(PaymentProcessor $paymentProcessor)
    {
        $this->paymentProcessor = $paymentProcessor;
    }

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
        $pending = (float) $partner->payouts()->whereIn('status', ['pending', 'processing'])->sum('amount');
        $payouts = $partner->payouts()->latest('id')->paginate(20);

        $limits = [
            'esewa' => [
                'min' => (float) GameSetting::getValue('payout_min_esewa', 100),
                'max' => (float) GameSetting::getValue('payout_max_esewa', 50000),
            ],
            'khalti' => [
                'min' => (float) GameSetting::getValue('payout_min_khalti', 100),
                'max' => (float) GameSetting::getValue('payout_max_khalti', 50000),
            ],
            'bank' => [
                'min' => (float) GameSetting::getValue('payout_min_bank', 500),
                'max' => (float) GameSetting::getValue('payout_max_bank', 500000),
            ],
        ];

        return view('partner.payouts', compact('partner', 'balance', 'earned', 'paid', 'pending', 'payouts', 'limits'));
    }

    public function store(Request $request)
    {
        $partner = $this->partner();

        $request->validate([
            'payment_method' => 'required|in:esewa,khalti,bank',
            '_idempotency_key' => 'sometimes|string|max:100',
        ]);

        $method = $request->input('payment_method');
        $limits = [
            'esewa' => [
                'min' => (float) GameSetting::getValue('payout_min_esewa', 100),
                'max' => (float) GameSetting::getValue('payout_max_esewa', 50000),
            ],
            'khalti' => [
                'min' => (float) GameSetting::getValue('payout_min_khalti', 100),
                'max' => (float) GameSetting::getValue('payout_max_khalti', 50000),
            ],
            'bank' => [
                'min' => (float) GameSetting::getValue('payout_min_bank', 500),
                'max' => (float) GameSetting::getValue('payout_max_bank', 500000),
            ],
        ];

        $min = $limits[$method]['min'];
        $max = $limits[$method]['max'];

        $data = $request->validate([
            'amount' => 'required|numeric|min:' . $min . '|max:' . $max,
            'payment_detail' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        $balance = $partner->payoutBalance();
        $amount = (float) $data['amount'];

        if ($amount > $balance) {
            return back()->withErrors(['amount' => 'Amount exceeds your available balance (Rs. ' . number_format($balance, 2) . ').'])
                ->withInput();
        }

        // Check pending payouts limit
        $maxPending = (int) GameSetting::getValue('max_pending_partner_payouts', 3);
        $pendingCount = $partner->payouts()->whereIn('status', ['pending', 'processing'])->count();

        if ($pendingCount >= $maxPending) {
            return back()->withErrors(['amount' => "You have {$maxPending} pending payouts. Please wait for them to complete."])
                ->withInput();
        }

        // Check partner limits
        $limitCheck = $this->paymentProcessor->checkPartnerLimits($partner->id, $method, $amount);
        if (!$limitCheck['allowed']) {
            return back()->withErrors(['amount' => $limitCheck['message']])
                ->withInput();
        }

        // Parse payment detail
        $paymentDetail = $data['payment_detail'] ?? '';
        if ($method === 'bank' && empty($paymentDetail)) {
            return back()->withErrors(['payment_detail' => 'Bank details required for bank transfer'])
                ->withInput();
        }

        // Generate idempotency key
        $idempotencyKey = $request->input('_idempotency_key')
            ?? 'ppayout-' . $partner->id . '-' . $method . '-' . $amount . '-' . now()->format('YmdHis');

        // Check if already processed
        $existing = PaymentTransaction::where('idempotency_key', $idempotencyKey)
            ->where('partner_id', $partner->id)
            ->first();
        if ($existing) {
            return back()->with('success', 'Payout request already submitted.');
        }

        DB::beginTransaction();
        try {
            $payout = $partner->payouts()->create([
                'amount' => $amount,
                'payment_method' => $method,
                'payment_detail' => $paymentDetail,
                'note' => $data['note'] ?? null,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            // Create payment transaction record
            PaymentTransaction::create([
                'reference_id' => 'PPY-' . $payout->id . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
                'method' => $method,
                'amount_npr' => $amount,
                'account_details' => ['payment_detail' => $paymentDetail],
                'status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'partner_id' => $partner->id,
                'payout_id' => $payout->id,
                'metadata' => [
                    'partner_note' => $data['note'] ?? null,
                ],
            ]);

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                Auth::user(),
                'partner.payout.requested',
                'payout',
                $payout->id,
                "Partner requested payout of Rs. " . number_format($amount, 2) . " via {$method}",
                [
                    'partner_id' => $partner->id,
                    'amount' => $amount,
                    'method' => $method,
                ]
            );

            DB::commit();
            return redirect()->route('partner.wallet')->with('success', 'Payout requested. Admin will review it.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner payout request failed', ['error' => $e->getMessage(), 'partner_id' => $partner->id]);
            return back()->withErrors(['amount' => 'Error processing payout request.'])
                ->withInput();
        }
    }

    public function cancel(Payout $payout)
    {
        $partner = $this->partner();
        abort_if($payout->travel_partner_id !== $partner->id, 403);
        abort_if($payout->status !== 'pending', 422, 'Only pending payouts can be cancelled.');

        DB::beginTransaction();
        try {
            // Update payment transaction
            PaymentTransaction::where('payout_id', $payout->id)
                ->where('partner_id', $partner->id)
                ->update([
                    'status' => 'rejected',
                    'failure_reason' => 'Cancelled by partner',
                    'completed_at' => now(),
                ]);

            $payout->delete();

            // Audit log
            app(\App\Services\ModeratorService::class)->log(
                Auth::user(),
                'partner.payout.cancelled',
                'payout',
                $payout->id,
                "Partner cancelled payout of Rs. " . number_format($payout->amount, 2),
                ['partner_id' => $partner->id, 'amount' => $payout->amount]
            );

            DB::commit();
            return back()->with('success', 'Payout request cancelled.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Partner payout cancel failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Error cancelling payout.']);
        }
    }
}