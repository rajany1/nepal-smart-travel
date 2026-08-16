<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AiRateLimitException;

class CloudflareService implements AiProviderInterface
{
    protected string $accountId;
    protected string $apiToken;
    protected string $model;

    public function __construct(?string $model = null, ?string $accountId = null, ?string $apiToken = null)
    {
        $this->accountId = $accountId ?: config('services.cloudflare.account_id');
        $this->apiToken = $apiToken ?: config('services.cloudflare.api_token');
        $this->model = $model;
    }

    protected function endpoint(string $model): string
    {
        return "https://api.cloudflare.com/client/v4/accounts/{$this->accountId}/ai/run/{$model}";
    }

    protected function post(string $model, array $payload, int $attempts = 0): string
    {
        $attempts++;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($this->endpoint($model), $payload);

        if ($response->successful()) {
            $data = $response->json();

            return trim((string) ($data['result']['response'] ?? $data['result'] ?? ''));
        }

        $status = $response->status();
        $body = $response->body();

        if ($status === 429) {
            if ($attempts <= 1) {
                Log::warning('Cloudflare rate limit hit, sleeping 10s: ' . substr($body, 0, 300));
                sleep(10);

                return $this->post($model, $payload, $attempts);
            }

            throw new AiRateLimitException('Cloudflare rate limit exhausted: ' . substr($body, 0, 300));
        }

        Log::error('Cloudflare API error: ' . $body);
        throw new \RuntimeException('Cloudflare API request failed: ' . $body);
    }

    public function generate(string $prompt, array $options = []): string
    {
        $model = $this->model ?: config('services.cloudflare.text_model', '@cf/meta/llama-3.3-70b-instruct-fp8-fast');

        return $this->post($model, [
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_tokens' => $options['maxOutputTokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        return $this->generateJsonWithRetry(
            fn () => $this->generate(
                "Respond with valid JSON only, no markdown, no explanation.\n\n{$prompt}",
                array_merge($options, ['maxOutputTokens' => $options['maxOutputTokens'] ?? 2048])
            )
        );
    }

    public function generateWithImages(string $prompt, array $imagePaths, array $options = []): string
    {
        $model = $this->model ?: config('services.cloudflare.vision_model', '@cf/meta/llama-3.2-11b-vision-instruct');

        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($imagePaths as $path) {
            if (!is_file($path) || filesize($path) === 0) continue;
            if (filesize($path) > (4 * 1024 * 1024)) continue;

            $content[] = [
                'type' => 'image',
                'image' => base64_encode(file_get_contents($path)),
            ];
        }

        if (count($content) === 1) {
            return $this->generate($prompt, $options);
        }

        return $this->post($model, [
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'max_tokens' => $options['maxOutputTokens'] ?? 2048,
            'temperature' => $options['temperature'] ?? 0.7,
        ]);
    }

    public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array
    {
        return $this->generateJsonWithRetry(
            fn () => $this->generateWithImages(
                "Respond with valid JSON only, no markdown, no explanation.\n\n{$prompt}",
                $imagePaths,
                array_merge($options, ['maxOutputTokens' => $options['maxOutputTokens'] ?? 2048])
            )
        );
    }

    protected function generateJsonWithRetry(callable $call): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $text = $call();
            $decoded = $this->decodeJsonText($text);
            if ($decoded !== []) {
                return $decoded;
            }
            if ($attempt < 3) {
                Log::warning("Cloudflare JSON decode failed (attempt {$attempt}): " . substr($text, 0, 400));
                usleep(500000);
            }
        }

        return [];
    }

    protected function decodeJsonText(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        return json_decode($text, true) ?? [];
    }
}
