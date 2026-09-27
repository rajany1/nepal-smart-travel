<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Payout;
use App\Models\PaymentTransaction;
use App\Models\TravelPartner;
use App\Services\ModeratorService;
use App\Services\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutController extends Controller
{
    private PaymentProcessor $paymentProcessor;

    public function __construct(
        ModeratorService $moderatorService,
        PaymentProcessor $paymentProcessor
    ) {
        $this->moderatorService = $moderatorService;
        $this->paymentProcessor = $paymentProcessor;
    }

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) abort(403, 'Unauthorized');

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $status = $request->get('status');
        $query = Payout::with('partner');
        if ($status) $query->where('status', $status);
        $payouts = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'rejected' THEN 2 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->paginate(20);

        $stats = [
            'pending' => Payout::where('status', 'pending')->count(),
            'pending_total' => (float) Payout::where('status', 'pending')->sum('amount'),
            'processing' => Payout::where('status', 'processing')->count(),
            'paid' => Payout::where('status', 'paid')->count(),
            'paid_total' => (float) Payout::where('status', 'paid')->sum('amount'),
            'rejected' => Payout::where('status', 'rejected')->count(),
        ];

        return view('admin.payouts', compact('payouts', 'status', 'stats'));
    }

    /**
     * Approve payout - mark as processing and initiate payment
     */
    public function approve(Request $request, Payout $payout)
    {
        $this->requireAdmin($request);
        abort_if($payout->status !== 'pending', 422, 'Only pending payouts can be approved.');

        $payout->update([
            'status' => 'processing',
            'admin_note' => $request->input('admin_note') ?: 'Approved by admin: ' . Auth::user()->name,
        ]);

        // Create payment transaction record
        $referenceId = 'PYT-' . $payout->id . '-' . \Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6));
        $idempotencyKey = 'payout-' . $payout->id . '-' . now()->format('YmdHis');

        PaymentTransaction::create([
            'reference_id' => $referenceId,
            'method' => $payout->payment_method,
            'amount_npr' => $payout->amount,
            'account_details' => ['payment_detail' => $payout->payment_detail],
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
            'partner_id' => $payout->travel_partner_id,
            'payout_id' => $payout->id,
            'metadata' => [
                'admin_note' => $payout->admin_note,
                'partner_note' => $payout->note,
            ],
        ]);

        $this->moderatorService->log(
            Auth::user(),
            'payout.approved',
            'payout',
            $payout->id,
            'Payout approved for payment: Rs. ' . number_format($payout->amount, 2) . ' to ' . ($payout->partner?->name ?? 'partner') . ' via ' . $payout->payment_method,
        );

        return back()->with('success', 'Payout approved. Payment processing initiated...');
    }

    /**
     * Actually process the payment through gateway
     */
    public function processPayment(Request $request, Payout $payout)
    {
        $this->requireAdmin($request);
        abort_if(!in_array($payout->status, ['pending', 'processing']), 422, 'Only pending/processing payouts can be processed.');

        $paymentTxn = PaymentTransaction::where('payout_id', $payout->id)
            ->where('partner_id', $payout->travel_partner_id)
            ->first();

        if (!$paymentTxn) {
            return back()->with('error', 'Payment transaction not found');
        }

        if ($paymentTxn->status === 'completed') {
            return back()->with('error', 'Payment already completed');
        }

        // Process the actual payment
        $result = $this->paymentProcessor->processPayout([
            'method' => $payout->payment_method,
            'amount_npr' => (float) $payout->amount,
            'account_details' => ['payment_detail' => $payout->payment_detail],
            'reference_id' => $paymentTxn->reference_id,
            'idempotency_key' => $paymentTxn->idempotency_key,
            'metadata' => [
                'payout_id' => $payout->id,
                'partner_id' => $payout->travel_partner_id,
                'admin_note' => $payout->admin_note,
            ],
        ]);

        if ($result['success']) {
            $payout->update([
                'status' => 'paid',
                'processed_at' => now(),
                'processed_by' => Auth::id(),
                'admin_note' => 'Payment completed: ' . ($result['message'] ?? 'Success'),
            ]);

            $paymentTxn->update([
                'status' => 'completed',
                'gateway_transaction_id' => $result['transaction_id'] ?? null,
                'gateway_response' => $result,
                'completed_at' => now(),
            ]);

            $this->moderatorService->log(
                Auth::user(),
                'payout.paid',
                'payout',
                $payout->id,
                'Payout paid: Rs. ' . number_format($payout->amount, 2) . ' to ' . ($payout->partner?->name ?? 'partner') . '. Gateway TXN: ' . ($result['transaction_id'] ?? 'N/A'),
            );

            return back()->with('success', 'Payment processed successfully. Gateway TXN: ' . ($result['transaction_id'] ?? 'N/A'));
        } else {
            // Payment failed
            $paymentTxn->update([
                'status' => 'failed',
                'failure_reason' => $result['message'] ?? 'Payment failed',
                'gateway_response' => $result,
            ]);

            $payout->update([
                'status' => 'processing', // Keep as processing for retry
                'admin_note' => 'Payment failed: ' . ($result['message'] ?? 'Unknown error'),
            ]);

            $this->moderatorService->log(
                Auth::user(),
                'payout.payment_failed',
                'payout',
                $payout->id,
                'Payment failed for payout #' . $payout->id . ': ' . ($result['message'] ?? 'Unknown error'),
            );

            return back()->with('error', 'Payment failed: ' . ($result['message'] ?? 'Unknown error'));
        }
    }

    /**
     * Legacy markPaid - now processes payment
     */
    public function markPaid(Request $request, Payout $payout)
    {
        return $this->processPayment($request, $payout);
    }

    /**
     * Reject payout
     */
    public function reject(Request $request, Payout $payout)
    {
        $this->requireAdmin($request);
        abort_if($payout->status !== 'pending', 422, 'Only pending payouts can be rejected.');

        $data = $request->validate(['admin_note' => 'required|string|max:1000']);

        // Update payment transaction if exists
        PaymentTransaction::where('payout_id', $payout->id)
            ->update([
                'status' => 'rejected',
                'failure_reason' => 'Rejected by admin: ' . $data['admin_note'],
                'completed_at' => now(),
            ]);

        $payout->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'processed_by' => Auth::id(),
            'admin_note' => $data['admin_note'],
        ]);

        $this->moderatorService->log(
            Auth::user(),
            'payout.rejected',
            'payout',
            $payout->id,
            'Payout rejected: ' . $data['admin_note'],
        );

        return back()->with('success', 'Payout rejected.');
    }

    /**
     * Retry failed payment
     */
    public function retryPayment(Request $request, Payout $payout)
    {
        $this->requireAdmin($request);
        abort_if($payout->status !== 'processing', 422, 'Only processing payouts can be retried.');

        $paymentTxn = PaymentTransaction::where('payout_id', $payout->id)
            ->where('status', 'failed')
            ->first();

        if (!$paymentTxn) {
            return back()->with('error', 'No failed payment to retry');
        }

        // Reset for retry
        $paymentTxn->update([
            'status' => 'pending',
            'failure_reason' => null,
            'gateway_response' => null,
        ]);

        return $this->processPayment($request, $payout);
    }

    /**
     * Get payout details
     */
    public function show(Payout $payout)
    {
        $this->requireAdmin(request());
        $payout->load('partner', 'processor');
        $paymentTxn = PaymentTransaction::where('payout_id', $payout->id)->first();

        return response()->json([
            'payout' => $payout,
            'payment_transaction' => $paymentTxn,
        ]);
    }
}