<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingPaymentController extends Controller
{
    // ==================== API (authenticated) ====================

    /**
     * POST /api/v1/bookings/{booking}/payment/initiate  {gateway: esewa|khalti}
     * Creates (or re-creates) a pending payment and returns everything the
     * mobile app needs to open the gateway page.
     */
    public function initiate(Request $request, Booking $booking)
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id) {
            abort(403, 'Not your booking.');
        }
        if (!$booking->isPending()) {
            return response()->json(['success' => false, 'message' => 'Only pending bookings can be paid.'], 422);
        }
        if ((float) $booking->amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Booking amount must be greater than zero.'], 422);
        }
        $gateway = $request->validate(['gateway' => 'required|in:esewa,khalti'])['gateway'];

        // Invalidate any stale pending payment for this booking
        BookingPayment::where('booking_id', $booking->id)
            ->where('status', 'pending')
            ->update(['status' => 'failed']);

        $payment = BookingPayment::create([
            'booking_id' => $booking->id,
            'gateway' => $gateway,
            'amount' => $booking->amount,
            'status' => 'pending',
            'reference' => 'bk-' . $booking->id . '-' . strtoupper(Str::random(6)),
        ]);

        $service = app(PaymentGatewayService::class);

        if ($gateway === 'esewa') {
            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'form_html' => $service->eSewaForm(
                        (float) $booking->amount,
                        $payment->reference,
                        route('api.payments.esewa.callback'),
                        route('api.payments.esewa.callback'),
                    ),
                ],
            ]);
        }

        $result = $service->initiateKhalti((float) $booking->amount, $payment->reference, route('api.payments.khalti.callback'), 'Booking Payment');
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
     * POST /api/v1/bookings/{booking}/payment/verify  {reference?}
     * Called by the app after the gateway page closes. Does a live gateway
     * check when possible and confirms the booking on success.
     */
    public function verify(Request $request, Booking $booking)
    {
        $user = $request->user();
        if ($booking->user_id !== $user->id) {
            abort(403, 'Not your booking.');
        }

        $query = BookingPayment::where('booking_id', $booking->id);
        if ($request->filled('reference')) {
            $query->where('reference', $request->reference);
        }
        $payment = $query->latest('id')->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'No payment found for this booking.'], 404);
        }

        if ($payment->status === 'success') {
            return response()->json(['success' => true, 'data' => ['payment' => $payment, 'booking_status' => $booking->status]]);
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
            // eSewa status API works with transaction_uuid + amount alone;
            // transaction_id is only known from the gateway redirect (callback).
            $verified = $service->verifyESewa(
                $service->eSewaProductCode(),
                (float) $payment->amount,
                $payment->transaction_id ?? '',
                $payment->reference,
            );
        }

        if ($verified['success']) {
            $this->markPaid($payment, $verified['transaction_id'] ?? '');
            return response()->json([
                'success' => true,
                'data' => ['payment' => $payment->refresh(), 'booking_status' => $booking->refresh()->status],
                'message' => 'Payment successful. Booking confirmed.',
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
            return $this->callbackPage(false, 'Invalid eSewa callback.');
        }

        $payment = BookingPayment::where('reference', $data['transaction_uuid'] ?? '')->first();
        if (!$payment) {
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
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['verify_error' => $verified['message']])]);
            return $this->callbackPage(false, $verified['message']);
        }

        $this->markPaid($payment, $verified['transaction_id']);
        return $this->callbackPage(true, 'Payment successful. Booking confirmed.');
    }

    public function khaltiCallback(Request $request)
    {
        $pidx = $request->get('pidx');
        $payment = BookingPayment::where('transaction_id', $pidx)->first();
        if (!$payment) {
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
            $payment->update(['status' => 'failed', 'metadata' => array_merge($payment->metadata ?? [], ['error' => $verified['message'] ?? 'Verification failed'])]);
            return $this->callbackPage(false, $verified['message'] ?? 'Khalti verification failed.');
        }

        $this->markPaid($payment, $verified['transaction_id'] ?? $pidx);
        return $this->callbackPage(true, 'Payment successful. Booking confirmed.');
    }

    // ==================== Internals ====================

    protected function markPaid(BookingPayment $payment, string $transactionId): void
    {
        DB::transaction(function () use ($payment, $transactionId) {
            $payment->update([
                'status' => 'success',
                'transaction_id' => $transactionId ?: $payment->transaction_id,
                'paid_at' => now(),
            ]);

            $booking = $payment->booking;
            $booking->update(['status' => 'confirmed', 'confirmed_at' => now()]);

            if ($booking->offerRedemption && $booking->offerRedemption->status === 'claimed') {
                $booking->offerRedemption->update(['status' => 'used', 'consumed_at' => now()]);
            }
        });
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
