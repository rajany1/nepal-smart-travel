<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    protected string $apiKey;
    protected string $model;

    public function __construct(string $model = 'gemini-2.0-flash')
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = $model;
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

        $response = Http::timeout(60)->post($url, $payload);

        if ($response->failed()) {
            Log::error('Gemini API error: ' . $response->body());
            throw new \RuntimeException('Gemini API request failed: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return trim($text);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        $options['response_mime_type'] = 'application/json';
        $text = $this->generate($prompt, $options);
        return json_decode($text, true) ?? [];
    }
}
