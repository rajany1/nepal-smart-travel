<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AiRateLimitException;

class GeminiService implements AiProviderInterface
{
    protected string $apiKey;
    protected string $model;

    public function __construct(?string $model = null, ?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?: config('services.gemini.api_key');
        $this->model = $model ?: config('services.ai.vision_model', 'gemini-2.0-flash');
    }

    protected function post(string $url, array $payload): array
    {
        $attempts = 0;

        while (true) {
            $attempts++;
            $response = Http::timeout(60)->post($url, $payload);

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $body = $response->body();
            $isRateLimit = $response->status() === 429
                || str_contains($body, 'RATE_LIMIT_EXCEEDED')
                || str_contains($body, 'RESOURCE_EXHAUSTED')
                || str_contains($body, 'quota');

            if ($isRateLimit && $attempts <= 2) {
                Log::warning("Gemini rate limit hit (attempt {$attempts}), sleeping 15s: " . substr($body, 0, 300));
                sleep(15);

                continue;
            }

            if ($isRateLimit) {
                throw new AiRateLimitException('Gemini rate limit/quota exhausted: ' . substr($body, 0, 300));
            }

            Log::error('Gemini API error: ' . $body);
            throw new \RuntimeException('Gemini API request failed: ' . $body);
        }
    }

    public function generate(string $prompt, array $options = []): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => array_merge([
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
                'topP' => 0.95,
                'topK' => 40,
            ], $options),
        ];

        $data = $this->post($url, $payload);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return trim($text);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        $options['response_mime_type'] = 'application/json';
        return $this->generateJsonWithRetry(fn () => $this->generate($prompt, $options));
    }

    public function generateWithImages(string $prompt, array $imagePaths, array $options = []): string
    {
        $parts = [['text' => $prompt]];

        foreach ($imagePaths as $path) {
            if (!is_file($path) || filesize($path) === 0) continue;
            if (filesize($path) > (4 * 1024 * 1024)) continue;

            $mime = mime_content_type($path) ?: 'image/jpeg';
            $parts[] = [
                'inline_data' => [
                    'mime_type' => $mime,
                    'data' => base64_encode(file_get_contents($path)),
                ],
            ];
        }

        if (count($parts) === 1) {
            return $this->generate($prompt, $options);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                ['parts' => $parts],
            ],
            'generationConfig' => array_merge([
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
                'topP' => 0.95,
                'topK' => 40,
            ], $options),
        ];

        $data = $this->post($url, $payload);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return trim($text);
    }

    public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array
    {
        $options['response_mime_type'] = 'application/json';
        return $this->generateJsonWithRetry(fn () => $this->generateWithImages($prompt, $imagePaths, $options));
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
                Log::warning("Gemini JSON decode failed (attempt {$attempt}): " . substr($text, 0, 400));
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
