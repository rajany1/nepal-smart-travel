<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Services\PaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    private PaymentProcessor $paymentProcessor;

    public function __construct(PaymentProcessor $paymentProcessor)
    {
        $this->paymentProcessor = $paymentProcessor;
    }

    /**
     * Generic webhook handler for all payment gateways
     * Can be used by eSewa, Khalti, Bank webhooks
     */
    public function handle(Request $request, string $gateway)
    {
        Log::info("Payment webhook received", [
            'gateway' => $gateway,
            'ip' => $request->ip(),
            'data' => $request->all(),
        ]);

        try {
            $result = $this->paymentProcessor->verifyCallback($gateway, $request->all());

            if (!$result['success']) {
                Log::warning("Payment webhook verification failed", [
                    'gateway' => $gateway,
                    'error' => $result['message'],
                ]);
                return response()->json(['success' => false, 'message' => $result['message']], 400);
            }

            // Find the payment transaction by reference
            $reference = $result['reference'] ?? $request->input('transaction_uuid') ?? $request->input('pidx');
            $txn = PaymentTransaction::where('reference_id', $reference)
                ->where('status', 'pending')
                ->first();

            if (!$txn) {
                // Try to find by gateway transaction ID
                $txn = PaymentTransaction::where('gateway_transaction_id', $result['transaction_id'] ?? '')
                    ->where('status', 'pending')
                    ->first();
            }

            if (!$txn) {
                Log::warning("Payment transaction not found for webhook", [
                    'gateway' => $gateway,
                    'reference' => $reference,
                    'gateway_txn_id' => $result['transaction_id'] ?? null,
                ]);
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            // Verify amount matches (critical security check)
            $receivedAmount = (float) $result['amount'];
            if (abs($txn->amount_npr - $receivedAmount) > 0.01) {
                Log::critical("Payment amount mismatch in webhook", [
                    'transaction_id' => $txn->id,
                    'expected' => $txn->amount_npr,
                    'received' => $receivedAmount,
                    'gateway' => $gateway,
                ]);
                return response()->json(['success' => false, 'message' => 'Amount mismatch'], 400);
            }

            // Update transaction
            $txn->update([
                'status' => 'completed',
                'gateway_transaction_id' => $result['transaction_id'],
                'gateway_response' => array_merge($txn->gateway_response ?? [], $result),
                'completed_at' => now(),
            ]);

            // Handle based on transaction type
            $this->handleTransactionCompletion($txn);

            Log::info("Payment webhook processed successfully", [
                'transaction_id' => $txn->id,
                'gateway_txn_id' => $result['transaction_id'],
                'amount' => $receivedAmount,
            ]);

            return response()->json(['success' => true, 'message' => 'Webhook processed']);

        } catch (\Throwable $e) {
            Log::error("Payment webhook error", [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle completion based on transaction type
     */
    private function handleTransactionCompletion(PaymentTransaction $txn): void
    {
        $metadata = $txn->metadata ?? [];
        $type = $metadata['type'] ?? 'unknown';

        switch ($type) {
            case 'topup':
                // Credit partner wallet via FinancialLedgerService (atomic + idempotent)
                if ($txn->partner_id) {
                    $ledger = app(\App\Services\FinancialLedgerService::class);
                    $ledger->creditPartnerWallet(
                        partnerId: $txn->partner_id,
                        amount: (float) $txn->amount_npr,
                        type: 'topup',
                        description: "Partner top-up via webhook",
                        referenceType: 'PaymentTransaction',
                        referenceId: $txn->id,
                        metadata: [
                            'webhook_id' => $txn->gateway_transaction_id,
                            'method' => $txn->method,
                        ],
                        idempotencyKey: "topup:{$txn->id}",
                    );
                }
                break;

            case 'withdrawal':
            case 'partner_withdrawal':
            case 'payout':
                // These are processed by admin, webhook just confirms
                // The actual completion is done by admin via processPayment
                break;

            default:
                // Check polymorphic relationships
                if ($txn->withdrawal_id) {
                    // User withdrawal - admin processes separately
                } elseif ($txn->partner_withdrawal_id) {
                    // Partner withdrawal - admin processes separately
                } elseif ($txn->payout_id) {
                    // Partner payout - admin processes separately
                }
                break;
        }
    }

    /**
     * eSewa specific webhook (for backward compatibility)
     */
    public function esewa(Request $request)
    {
        return $this->handle($request, 'esewa');
    }

    /**
     * Khalti specific webhook
     */
    public function khalti(Request $request)
    {
        return $this->handle($request, 'khalti');
    }

    /**
     * Bank webhook (for future use)
     */
    public function bank(Request $request)
    {
        return $this->handle($request, 'bank');
    }
}