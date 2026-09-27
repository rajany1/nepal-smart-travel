<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionPaymentController extends Controller
{
    /**
     * POST /api/v1/subscriptions/{plan}/purchase  {gateway: esewa|khalti}
     * Creates a pending subscription payment and returns the gateway form/URL.
     */
    public function purchase(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();

        if (!$plan->is_active) {
            return response()->json(['success' => false, 'message' => 'This plan is not available.'], 422);
        }

        if ((float) $plan->price <= 0) {
            return response()->json(['success' => false, 'message' => 'Free plans cannot be purchased.'], 422);
        }

        // Check if user already has an active subscription to this plan
        $existing = UserSubscription::where('user_id', $user->id)
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'You already have an active subscription to this plan.'], 422);
        }

        $gateway = $request->validate(['gateway' => 'required|in:esewa,khalti'])['gateway'];

        // Invalidate any stale pending payment for this user + plan
        SubscriptionPayment::where('user_id', $user->id)
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'gateway' => $gateway,
            'amount' => $plan->price,
            'status' => 'pending',
            'reference' => 'sub-' . $user->id . '-' . $plan->id . '-' . strtoupper(Str::random(6)),
        ]);

        $service = app(PaymentGatewayService::class);

        if ($gateway === 'esewa') {
            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'form_html' => $service->eSewaForm(
                        (float) $plan->price,
                        $payment->reference,
                        route('api.payments.subscription.esewa.callback'),
                        route('api.payments.subscription.esewa.callback'),
                    ),
                ],
            ]);
        }

        // Khalti
        $result = $service->initiateKhalti(
            (float) $plan->price,
            $payment->reference,
            route('api.payments.subscription.khalti.callback'),
            'Subscription: ' . $plan->name,
        );

        if (!$result['success']) {
            $payment->update(['status' => 'failed', 'metadata' => ['error' => $result['message']]]);
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        $payment->update(['transaction_id' => $result['pidx'], 'metadata' => ['pidx' => $result['pidx']]]);

        return response()->json([
            'success' => true,
            'data' => [
                'payment' => $payment,
                'payment_url' => $result['payment_url'],
            ],
        ]);
    }

    /**
     * POST /api/v1/subscriptions/{plan}/verify  {reference?}
     * Called by the app after the gateway page closes.
     */
    public function verify(Request $request, SubscriptionPlan $plan)
    {
        $user = $request->user();

        $query = SubscriptionPayment::where('user_id', $user->id)
            ->where('subscription_plan_id', $plan->id);

        if ($request->filled('reference')) {
            $query->where('reference', $request->reference);
        }
        $payment = $query->latest('id')->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'No payment found for this subscription.'], 404);
        }

        if ($payment->status === 'success') {
            return response()->json([
                'success' => true,
                'data' => ['payment' => $payment, 'subscription' => $this->getActiveSubscription($user->id, $plan->id)],
            ]);
        }

        if ($payment->status === 'failed') {
            return response()->json(['success' => false, 'data' => ['payment' => $payment], 'message' => 'Payment failed or was not completed.']);
        }

        $service = app(PaymentGatewayService::class);
        $verified = ['success' => false, 'transaction_id' => null];

        if ($payment->gateway === 'khalti' && $payment->transaction_id) {
            try {
                $verified = $service->verifyKhalti($payment->transaction_id);
            } catch (\Throwable $e) {
                $verified = ['success' => false, 'transaction_id' => null, 'message' => $e->getMessage()];
            }
        } elseif ($payment->gateway === 'esewa') {
            $verified = $service->verifyESewa(
                $service->eSewaProductCode(),
                (float) $payment->amount,
                $payment->transaction_id ?? '',
                $payment->reference,
            );
        }

        if ($verified['success']) {
            $this->activateSubscription($user->id, $plan, $payment, $verified['transaction_id'] ?? '');
            return response()->json([
                'success' => true,
                'data' => ['payment' => $payment->refresh(), 'subscription' => $this->getActiveSubscription($user->id, $plan->id)],
                'message' => 'Subscription activated successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'data' => ['payment' => $payment],
            'message' => $verified['message'] ?? 'Payment not completed yet.',
        ]);
    }

    // ==================== Gateway callbacks (no auth) ====================

    public function esewaCallback(Request $request)
    {
        try {
            $data = json_decode(base64_decode($request->get('data', '')), true);
        } catch (\Throwable $e) {
            $data = null;
        }
        if (!is_array($data)) {
            \Illuminate\Support\Facades\Log::warning('eSewa subscription callback: Invalid data format', ['ip' => $request->ip()]);
            return $this->callbackPage(false, 'Invalid eSewa callback.');
        }

        $payment = SubscriptionPayment::where('reference', $data['transaction_uuid'] ?? '')->first();
        if (!$payment) {
            \Illuminate\Support\Facades\Log::warning('eSewa subscription callback: Unknown reference', [
                'reference' => $data['transaction_uuid'] ?? 'none',
                'ip' => $request->ip(),
            ]);
            return $this->callbackPage(false, 'Payment reference not found.');
        }
        if ($payment->status === 'success') {
            return $this->callbackPage(true, 'Payment already confirmed.');
        }

        $service = app(PaymentGatewayService::class);
        $verified = $service->verifyESewa(
            $data['product_code'] ?? '',
            (float) ($data['total_amount'] ?? 0),
            $data['transaction_id'] ?? '',
            $data['transaction_uuid'] ?? '',
        );

        if (!$verified['success']) {
            \Illuminate\Support\Facades\Log::warning('eSewa subscription callback: Verification failed', [
                'payment_id' => $payment->id,
                'error' => $verified['message'],
                'ip' => $request->ip(),
            ]);
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['verify_error' => $verified['message']])]);
            return $this->callbackPage(false, $verified['message']);
        }

        $plan = SubscriptionPlan::find($payment->subscription_plan_id);
        $this->activateSubscription($payment->user_id, $plan, $payment, $verified['transaction_id']);
        return $this->callbackPage(true, 'Subscription activated successfully.');
    }

    public function khaltiCallback(Request $request)
    {
        $pidx = $request->get('pidx');
        $payment = SubscriptionPayment::where('transaction_id', $pidx)->first();
        if (!$payment) {
            \Illuminate\Support\Facades\Log::warning('Khalti subscription callback: Unknown pidx', [
                'pidx' => $pidx,
                'ip' => $request->ip(),
            ]);
            return $this->callbackPage(false, 'Payment reference not found.');
        }
        if ($payment->status === 'success') {
            return $this->callbackPage(true, 'Payment already confirmed.');
        }

        $service = app(PaymentGatewayService::class);
        try {
            $verified = $service->verifyKhalti($pidx);
        } catch (\Throwable $e) {
            $verified = ['success' => false, 'message' => $e->getMessage()];
        }

        if (!$verified['success']) {
            \Illuminate\Support\Facades\Log::warning('Khalti subscription callback: Verification failed', [
                'payment_id' => $payment->id,
                'error' => $verified['message'] ?? 'Unknown',
                'ip' => $request->ip(),
            ]);
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $verified['message'] ?? 'Verification failed'])]);
            return $this->callbackPage(false, $verified['message'] ?? 'Khalti verification failed.');
        }

        $plan = SubscriptionPlan::find($payment->subscription_plan_id);
        $this->activateSubscription($payment->user_id, $plan, $payment, $verified['transaction_id'] ?? $pidx);
        return $this->callbackPage(true, 'Subscription activated successfully.');
    }

    // ==================== Internals ====================

    protected function activateSubscription(int $userId, SubscriptionPlan $plan, SubscriptionPayment $payment, string $transactionId): void
    {
        DB::transaction(function () use ($userId, $plan, $payment, $transactionId) {
            $payment->update([
                'status' => 'success',
                'transaction_id' => $transactionId ?: $payment->transaction_id,
                'paid_at' => now(),
            ]);

            // Cancel any existing active subscription for this plan
            UserSubscription::where('user_id', $userId)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            // Calculate billing period
            $startsAt = now();
            $endsAt = match ($plan->billing_interval) {
                'daily' => now()->addDay(),
                'weekly' => now()->addWeek(),
                'monthly' => now()->addMonth(),
                'yearly' => now()->addYear(),
                default => now()->addMonth(),
            };

            UserSubscription::create([
                'user_id' => $userId,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        });
    }

    protected function getActiveSubscription(int $userId, int $planId): ?UserSubscription
    {
        return UserSubscription::where('user_id', $userId)
            ->where('subscription_plan_id', $planId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })->first();
    }

    protected function callbackPage(bool $success, string $message): \Illuminate\Http\Response
    {
        $color = $success ? '#16a34a' : '#dc2626';
        $icon = $success ? '&#10003;' : '&#10007;';
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>'
            . '<body style="font-family:sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0">'
            . '<div style="text-align:center;padding:32px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:360px">'
            . '<div style="font-size:56px;color:' . $color . '">' . $icon . '</div>'
            . '<h2 style="margin:12px 0 4px;color:' . $color . '">' . ($success ? 'Payment Complete' : 'Payment Failed') . '</h2>'
            . '<p style="color:#64748b;margin:0">' . e($message) . '</p>'
            . '<p style="color:#94a3b8;font-size:13px;margin-top:16px">You can close this page now.</p>'
            . '<div id="result" style="display:none">' . ($success ? 'success' : 'failed') . '</div>'
            . '</div></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html']);
    }
}
