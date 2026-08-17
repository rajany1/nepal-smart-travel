<?php

namespace App\Services;

use App\Models\GameSetting;
use Illuminate\Support\Facades\Http;

class PaymentGatewayService
{
    /**
     * Gateway configs come from game_settings (admin-editable) with config() fallback.
     * Switching sandbox/live or changing keys requires NO code changes.
     */
    private function eSewaConfig(): array
    {
        $sandbox = (int) GameSetting::getValue('gateway_esewa_sandbox', config('payments.esewa.sandbox')) === 1;
        return [
            'sandbox' => $sandbox,
            'merchant_code' => (string) GameSetting::getValue('gateway_esewa_merchant_code', config('payments.esewa.merchant_code')),
            'secret_key' => (string) GameSetting::getValue('gateway_esewa_secret_key', config('payments.esewa.secret_key')),
            'base_url' => $sandbox ? 'https://rc-epay.esewa.com.np' : 'https://epay.esewa.com.np',
            'form_path' => '/api/epay/main/v2/form',
            'status_path' => '/api/epay/transaction/status/',
        ];
    }

    private function khaltiConfig(): array
    {
        $sandbox = (int) GameSetting::getValue('gateway_khalti_sandbox', config('payments.khalti.sandbox')) === 1;
        return [
            'sandbox' => $sandbox,
            'secret_key' => (string) GameSetting::getValue('gateway_khalti_secret_key', config('payments.khalti.secret_key')),
            'public_key' => (string) GameSetting::getValue('gateway_khalti_public_key', config('payments.khalti.public_key')),
            'initiate_url' => $sandbox ? 'https://dev.khalti.com/api/v2/epayment/initiate/' : 'https://khalti.com/api/v2/epayment/initiate/',
            'verify_url' => $sandbox ? 'https://dev.khalti.com/api/v2/epayment/verify/' : 'https://khalti.com/api/v2/epayment/verify/',
        ];
    }

    public function eSewaForm(float $amount, string $reference, string $successUrl, string $failureUrl): string
    {
        $config = $this->eSewaConfig();
        $data = [
            'amount' => number_format($amount, 2, '.', ''),
            'tax_amount' => '0',
            'total_amount' => number_format($amount, 2, '.', ''),
            'transaction_uuid' => $reference,
            'product_code' => $config['merchant_code'],
            'product_service_charge' => '0',
            'product_delivery_charge' => '0',
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'signed_field_names' => 'total_amount,transaction_uuid,product_code',
        ];
        $data['signature'] = $this->eSewaSignature($data);

        $form = '<form id="esewa-pay" method="POST" action="' . $config['base_url'] . $config['form_path'] . '">';
        foreach ($data as $name => $value) {
            $form .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
        }
        $form .= '</form><script>document.getElementById("esewa-pay").submit();</script>';
        return $form;
    }

    public function eSewaSignature(array $data): string
    {
        $fields = explode(',', $data['signed_field_names']);
        $string = implode(',', array_map(fn($f) => $f . '=' . ($data[$f] ?? ''), $fields));
        return base64_encode(hash_hmac('sha256', $string, $this->eSewaConfig()['secret_key'], true));
    }

    public function verifyESewaSignature(array $data): bool
    {
        if (empty($data['signature'])) return false;
        $fields = explode(',', $data['signed_field_names'] ?? '');
        $string = implode(',', array_map(fn($f) => $f . '=' . ($data[$f] ?? ''), $fields));
        $expected = base64_encode(hash_hmac('sha256', $string, $this->eSewaConfig()['secret_key'], true));
        return hash_equals($expected, $data['signature']);
    }

    public function verifyESewa(string $productCode, float $amount, string $transactionId, string $transactionUuid = ''): array
    {
        $config = $this->eSewaConfig();
        $resp = Http::timeout(15)->get($config['base_url'] . $config['status_path'], array_filter([
            'product_code' => $productCode,
            'total_amount' => number_format($amount, 2, '.', ''),
            'transaction_id' => $transactionId,
            'transaction_uuid' => $transactionUuid,
        ]));
        if (!$resp->ok()) {
            return ['success' => false, 'message' => 'eSewa verification failed (' . $resp->status() . ')'];
        }
        $data = $resp->json();
        if (!is_array($data) || ($data['status'] ?? '') !== 'COMPLETE') {
            return ['success' => false, 'message' => 'eSewa transaction not complete.'];
        }
        return ['success' => true, 'transaction_id' => $data['transaction_code'] ?? $transactionId];
    }

    public function eSewaProductCode(): string
    {
        return $this->eSewaConfig()['merchant_code'];
    }

    public function initiateKhalti(float $amount, string $reference, string $returnUrl, string $purchaseOrderName = 'Payment'): array
    {
        $config = $this->khaltiConfig();
        $resp = Http::timeout(15)->withToken($config['secret_key'])
            ->post($config['initiate_url'], [
                'return_url' => $returnUrl,
                'website_url' => config('app.url'),
                'amount' => (int) round($amount * 100),
                'purchase_order_id' => $reference,
                'purchase_order_name' => $purchaseOrderName,
            ]);
        if (!$resp->ok() || !$resp->json('pidx')) {
            return ['success' => false, 'message' => 'Khalti initiation failed: ' . $resp->body()];
        }
        return [
            'success' => true,
            'pidx' => $resp->json('pidx'),
            'payment_url' => $resp->json('payment_url'),
        ];
    }

    public function verifyKhalti(string $pidx): array
    {
        $config = $this->khaltiConfig();
        $resp = Http::timeout(15)->withToken($config['secret_key'])
            ->post($config['verify_url'], ['pidx' => $pidx]);
        if (!$resp->ok()) {
            return ['success' => false, 'message' => 'Khalti verification failed (' . $resp->status() . ')'];
        }
        $data = $resp->json();
        if (strtolower($data['status'] ?? '') !== 'completed') {
            return ['success' => false, 'message' => 'Khalti transaction not completed.'];
        }
        return [
            'success' => true,
            'transaction_id' => $data['transaction_id'] ?? $pidx,
        ];
    }
}
