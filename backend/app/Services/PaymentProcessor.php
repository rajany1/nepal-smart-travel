<?php

namespace App\Services;

use App\Models\GameSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentProcessor
{
    public const METHOD_ESEWA = 'esewa';
    public const METHOD_KHALTI = 'khalti';
    public const METHOD_BANK = 'bank';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Process a payout/withdrawal to user/partner.
     * In production, this would call actual eSewa/Khalti/Bank APIs.
     * Currently simulates the flow with configurable success rates.
     */
    public function processPayout(array $params): array
    {
        $method = $params['method'];
        $amountNpr = $params['amount_npr'];
        $accountDetails = $params['account_details'];
        $referenceId = $params['reference_id'] ?? 'PAY-' . Str::upper(Str::random(8));
        $idempotencyKey = $params['idempotency_key'] ?? null;

        // Check idempotency
        if ($idempotencyKey) {
            $existing = \App\Models\PaymentTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return [
                    'success' => true,
                    'already_processed' => true,
                    'transaction_id' => $existing->gateway_transaction_id,
                    'status' => $existing->status,
                    'message' => 'Duplicate request - returning existing result',
                ];
            }
        }

        // Create transaction record
        $transaction = \App\Models\PaymentTransaction::create([
            'reference_id' => $referenceId,
            'method' => $method,
            'amount_npr' => $amountNpr,
            'account_details' => $accountDetails,
            'status' => self::STATUS_PROCESSING,
            'idempotency_key' => $idempotencyKey,
            'metadata' => $params['metadata'] ?? [],
        ]);

        try {
            $result = match ($method) {
                self::METHOD_ESEWA => $this->processESewaPayout($amountNpr, $accountDetails, $referenceId),
                self::METHOD_KHALTI => $this->processKhaltiPayout($amountNpr, $accountDetails, $referenceId),
                self::METHOD_BANK => $this->processBankPayout($amountNpr, $accountDetails, $referenceId),
                default => ['success' => false, 'message' => 'Unknown payment method'],
            };

            $transaction->update([
                'status' => $result['success'] ? self::STATUS_COMPLETED : self::STATUS_FAILED,
                'gateway_transaction_id' => $result['transaction_id'] ?? null,
                'gateway_response' => $result,
                'completed_at' => $result['success'] ? now() : null,
                'failure_reason' => $result['success'] ? null : ($result['message'] ?? 'Unknown error'),
            ]);

            return array_merge($result, [
                'transaction_id' => $transaction->id,
                'reference_id' => $referenceId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment processing failed', [
                'reference_id' => $referenceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $transaction->update([
                'status' => self::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'gateway_response' => ['exception' => $e->getMessage()],
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage(),
                'transaction_id' => $transaction->id,
            ];
        }
    }

    /**
     * Simulate eSewa payout (in production: call eSewa disbursement API)
     */
    private function processESewaPayout(float $amount, array $accountDetails, string $referenceId): array
    {
        $phone = $accountDetails['phone'] ?? null;

        if (!$phone || !preg_match('/^(984|985|986|987|988|989)\d{8}$/', $phone)) {
            return ['success' => false, 'message' => 'Invalid eSewa phone number'];
        }

        // Simulate API call delay
        $this->simulateNetworkDelay();

        // Check if simulation mode is enabled
        $simulateFailure = (int) GameSetting::getValue('payment_simulate_failure_rate', 0) > 0;
        $failureRate = (float) GameSetting::getValue('payment_simulate_failure_rate', 0) / 100;

        if ($simulateFailure && mt_rand() / mt_getrandmax() < $failureRate) {
            return [
                'success' => false,
                'message' => 'eSewa payout failed (simulated)',
                'error_code' => 'INSUFFICIENT_FUNDS',
            ];
        }

        // Simulate success
        $gatewayTxnId = 'ESW-' . strtoupper(Str::random(10));

        Log::info('eSewa payout processed (simulated)', [
            'reference_id' => $referenceId,
            'gateway_txn_id' => $gatewayTxnId,
            'amount' => $amount,
            'phone' => $phone,
        ]);

        return [
            'success' => true,
            'transaction_id' => $gatewayTxnId,
            'message' => 'eSewa payout successful',
            'gateway' => 'esewa',
        ];
    }

    /**
     * Simulate Khalti payout (in production: call Khalti disbursement API)
     */
    private function processKhaltiPayout(float $amount, array $accountDetails, string $referenceId): array
    {
        $phone = $accountDetails['phone'] ?? null;

        if (!$phone || !preg_match('/^(984|985|986|987|988|989)\d{8}$/', $phone)) {
            return ['success' => false, 'message' => 'Invalid Khalti phone number'];
        }

        $this->simulateNetworkDelay();

        $simulateFailure = (int) GameSetting::getValue('payment_simulate_failure_rate', 0) > 0;
        $failureRate = (float) GameSetting::getValue('payment_simulate_failure_rate', 0) / 100;

        if ($simulateFailure && mt_rand() / mt_getrandmax() < $failureRate) {
            return [
                'success' => false,
                'message' => 'Khalti payout failed (simulated)',
                'error_code' => 'WALLET_LIMIT_EXCEEDED',
            ];
        }

        $gatewayTxnId = 'KHT-' . strtoupper(Str::random(10));

        Log::info('Khalti payout processed (simulated)', [
            'reference_id' => $referenceId,
            'gateway_txn_id' => $gatewayTxnId,
            'amount' => $amount,
            'phone' => $phone,
        ]);

        return [
            'success' => true,
            'transaction_id' => $gatewayTxnId,
            'message' => 'Khalti payout successful',
            'gateway' => 'khalti',
        ];
    }

    /**
     * Simulate Bank transfer (in production: integrate with bank API or generate batch file)
     */
    private function processBankPayout(float $amount, array $accountDetails, string $referenceId): array
    {
        $bankName = $accountDetails['bank_name'] ?? null;
        $accountNumber = $accountDetails['account_number'] ?? null;
        $accountName = $accountDetails['account_name'] ?? null;

        if (!$bankName || !$accountNumber || !$accountName) {
            return ['success' => false, 'message' => 'Bank details incomplete'];
        }

        if (strlen($accountNumber) < 10) {
            return ['success' => false, 'message' => 'Invalid bank account number'];
        }

        $this->simulateNetworkDelay(500, 1500); // Bank transfers take longer

        $simulateFailure = (int) GameSetting::getValue('payment_simulate_failure_rate', 0) > 0;
        $failureRate = (float) GameSetting::getValue('payment_simulate_failure_rate', 0) / 100;

        if ($simulateFailure && mt_rand() / mt_getrandmax() < $failureRate) {
            return [
                'success' => false,
                'message' => 'Bank transfer failed (simulated)',
                'error_code' => 'INVALID_ACCOUNT',
            ];
        }

        $gatewayTxnId = 'BNK-' . strtoupper(Str::random(12));

        Log::info('Bank payout processed (simulated)', [
            'reference_id' => $referenceId,
            'gateway_txn_id' => $gatewayTxnId,
            'amount' => $amount,
            'bank' => $bankName,
            'account' => substr($accountNumber, -4),
        ]);

        return [
            'success' => true,
            'transaction_id' => $gatewayTxnId,
            'message' => 'Bank transfer initiated (typically 1-2 business days)',
            'gateway' => 'bank',
            'estimated_settlement' => now()->addBusinessDays(2)->format('Y-m-d'),
        ];
    }

    /**
     * Verify incoming payment callback (for top-ups)
     */
    public function verifyCallback(string $gateway, array $data): array
    {
        return match ($gateway) {
            'esewa' => $this->verifyESewaCallback($data),
            'khalti' => $this->verifyKhaltiCallback($data),
            default => ['success' => false, 'message' => 'Unknown gateway'],
        };
    }

    private function verifyESewaCallback(array $data): array
    {
        // In production: verify signature with eSewa
        // For simulation: check required fields
        $required = ['transaction_id', 'total_amount', 'transaction_uuid', 'status'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Missing field: $field"];
            }
        }

        if (($data['status'] ?? '') !== 'COMPLETE') {
            return ['success' => false, 'message' => 'Transaction not complete'];
        }

        return [
            'success' => true,
            'transaction_id' => $data['transaction_id'],
            'amount' => (float) $data['total_amount'],
            'reference' => $data['transaction_uuid'],
        ];
    }

    private function verifyKhaltiCallback(array $data): array
    {
        // In production: call Khalti verification API with pidx
        // For simulation: check pidx exists
        if (empty($data['pidx'])) {
            return ['success' => false, 'message' => 'Missing pidx'];
        }

        // Simulate verification
        $this->simulateNetworkDelay(200, 500);

        return [
            'success' => true,
            'transaction_id' => 'KHT-VER-' . strtoupper(Str::random(8)),
            'amount' => (float) ($data['amount'] ?? 0) / 100, // Khalti returns paisa
            'reference' => $data['purchase_order_id'] ?? $data['pidx'],
        ];
    }

    /**
     * Convert coins to NPR using current rate
     */
    public function coinsToNpr(float $coins): float
    {
        $rate = (float) \App\Models\CoinSetting::getValue('coin_to_npr_rate', 1);
        return round($coins * $rate, 2);
    }

    /**
     * Convert NPR to coins
     */
    public function nprToCoins(float $npr): float
    {
        $rate = (float) \App\Models\CoinSetting::getValue('coin_to_npr_rate', 1);
        if ($rate <= 0) return 0;
        return round($npr / $rate, 2);
    }

    /**
     * Get withdrawal limits for method
     */
    public function getLimits(string $method): array
    {
        $prefix = 'withdrawal_';
        $minKey = $prefix . 'min_' . $method;
        $maxKey = $prefix . 'max_' . $method;

        return [
            'min' => (float) \App\Models\CoinSetting::getValue($minKey, $this->getDefaultMin($method)),
            'max' => (float) \App\Models\CoinSetting::getValue($maxKey, $this->getDefaultMax($method)),
            'daily_limit' => (float) \App\Models\CoinSetting::getValue($prefix . 'daily_limit_' . $method, 50000),
            'monthly_limit' => (float) \App\Models\CoinSetting::getValue($prefix . 'monthly_limit_' . $method, 200000),
        ];
    }

    private function getDefaultMin(string $method): float
    {
        return match ($method) {
            self::METHOD_ESEWA => 100,
            self::METHOD_KHALTI => 100,
            self::METHOD_BANK => 500,
            default => 100,
        };
    }

    private function getDefaultMax(string $method): float
    {
        return match ($method) {
            self::METHOD_ESEWA => 50000,
            self::METHOD_KHALTI => 50000,
            self::METHOD_BANK => 500000,
            default => 50000,
        };
    }

    /**
     * Check user's withdrawal limits (daily/monthly)
     */
    public function checkUserLimits(int $userId, string $method, float $amountNpr): array
    {
        $limits = $this->getLimits($method);

        // Daily total
        $dailyTotal = \App\Models\PaymentTransaction::where('reference_id', 'like', 'WTH-%')
            ->where('method', $method)
            ->where('status', self::STATUS_COMPLETED)
            ->whereDate('created_at', today())
            ->whereHas('withdrawal', fn($q) => $q->where('user_id', $userId))
            ->sum('amount_npr');

        if ($dailyTotal + $amountNpr > $limits['daily_limit']) {
            return ['allowed' => false, 'message' => "Daily withdrawal limit exceeded (Rs. {$limits['daily_limit']})"];
        }

        // Monthly total
        $monthlyTotal = \App\Models\PaymentTransaction::where('reference_id', 'like', 'WTH-%')
            ->where('method', $method)
            ->where('status', self::STATUS_COMPLETED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereHas('withdrawal', fn($q) => $q->where('user_id', $userId))
            ->sum('amount_npr');

        if ($monthlyTotal + $amountNpr > $limits['monthly_limit']) {
            return ['allowed' => false, 'message' => "Monthly withdrawal limit exceeded (Rs. {$limits['monthly_limit']})"];
        }

        return ['allowed' => true];
    }

    /**
     * Check partner withdrawal limits
     */
    public function checkPartnerLimits(int $partnerId, string $method, float $amountNpr): array
    {
        $limits = $this->getLimits($method);
        // Partners have higher limits - multiply by 10
        $limits['daily_limit'] *= 10;
        $limits['monthly_limit'] *= 10;

        $dailyTotal = \App\Models\PaymentTransaction::where('reference_id', 'like', 'PTR-%')
            ->where('method', $method)
            ->where('status', self::STATUS_COMPLETED)
            ->whereDate('created_at', today())
            ->whereHas('partnerWithdrawal', fn($q) => $q->where('partner_id', $partnerId))
            ->sum('amount_npr');

        if ($dailyTotal + $amountNpr > $limits['daily_limit']) {
            return ['allowed' => false, 'message' => "Daily withdrawal limit exceeded (Rs. {$limits['daily_limit']})"];
        }

        $monthlyTotal = \App\Models\PaymentTransaction::where('reference_id', 'like', 'PTR-%')
            ->where('method', $method)
            ->where('status', self::STATUS_COMPLETED)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->whereHas('partnerWithdrawal', fn($q) => $q->where('partner_id', $partnerId))
            ->sum('amount_npr');

        if ($monthlyTotal + $amountNpr > $limits['monthly_limit']) {
            return ['allowed' => false, 'message' => "Monthly withdrawal limit exceeded (Rs. {$limits['monthly_limit']})"];
        }

        return ['allowed' => true];
    }

    private function simulateNetworkDelay(int $minMs = 300, int $maxMs = 800): void
    {
        if (app()->environment('testing')) return;
        usleep(mt_rand($minMs * 1000, $maxMs * 1000));
    }
}