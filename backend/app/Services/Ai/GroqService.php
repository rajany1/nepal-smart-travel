<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqService
{
    protected string $apiKey;
    protected string $model;

    public function __construct(string $model = 'llama-3.3-70b-versatile')
    {
        $this->apiKey = config('services.groq.api_key');
        $this->model = $model;
    }

    public function generate(string $prompt, array $options = []): string
    {
        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['maxOutputTokens'] ?? 2048,
            'top_p' => $options['topP'] ?? 0.95,
        ];

        return $this->post($url, $payload);
    }

    public function generateWithImages(string $prompt, array $imagePaths, array $options = []): string
    {
        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($imagePaths as $path) {
            if (!is_file($path) || filesize($path) === 0 || filesize($path) > (4 * 1024 * 1024)) {
                continue;
            }

            $mime = mime_content_type($path) ?: 'image/jpeg';
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)),
                ],
            ];
        }

        if (count($content) === 1) {
            return $this->generate($prompt, $options);
        }

        $url = 'https://api.groq.com/openai/v1/chat/completions';

        return $this->post($url, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['maxOutputTokens'] ?? 2048,
            'top_p' => $options['topP'] ?? 0.95,
        ]);
    }

    public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array
    {
        $content = [['type' => 'text', 'text' => $prompt]];

        foreach ($imagePaths as $path) {
            if (!is_file($path) || filesize($path) === 0 || filesize($path) > (4 * 1024 * 1024)) {
                continue;
            }

            $mime = mime_content_type($path) ?: 'image/jpeg';
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path)),
                ],
            ];
        }

        if (count($content) === 1) {
            return $this->generateJson($prompt, $options);
        }

        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $text = $this->post($url, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a JSON-only assistant. Respond with valid JSON only, no markdown, no explanation.'],
                ['role' => 'user', 'content' => $content],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['maxOutputTokens'] ?? 2048,
            'response_format' => ['type' => 'json_object'],
        ]);

        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));

        return json_decode($text, true) ?? [];
    }

    protected function post(string $url, array $payload, bool $retryWithoutFormat = true): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($url, $payload);

        if ($response->failed()) {
            $body = $response->body();
            $isJsonValidation = str_contains($body, 'json_validate_failed') || str_contains($body, 'Failed to validate JSON');

            if ($retryWithoutFormat && $isJsonValidation && isset($payload['response_format'])) {
                unset($payload['response_format']);

                return $this->post($url, $payload, false);
            }

            if (str_contains($body, 'rate_limit_exceeded')) {
                Log::warning('Groq rate limit hit, retrying in 12s: ' . $body);
                sleep(12);

                return $this->post($url, $payload, false);
            }

            Log::error('Groq API error: ' . $body);
            throw new \RuntimeException('Groq API request failed: ' . $body);
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';
        $text = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $text);
        if (stripos($text, '<think') !== false) {
            $text = preg_replace('/<think[\s\S]*$/i', '', $text);
        }

        return trim($text);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a JSON-only assistant. Respond with valid JSON only, no markdown, no explanation.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['maxOutputTokens'] ?? 2048,
            'response_format' => ['type' => 'json_object'],
        ];

        $url = 'https://api.groq.com/openai/v1/chat/completions';

        $text = $this->post($url, $payload);
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));

        return json_decode($text, true) ?? [];
    }
}
