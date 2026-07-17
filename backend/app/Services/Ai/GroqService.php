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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($url, $payload);

        if ($response->failed()) {
            Log::error('Groq API error: ' . $response->body());
            throw new \RuntimeException('Groq API request failed: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';

        return trim($text);
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        $options['response_format'] = ['type' => 'json_object'];
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

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(60)->post($url, $payload);

        if ($response->failed()) {
            Log::error('Groq API error: ' . $response->body());
            throw new \RuntimeException('Groq API request failed: ' . $response->body());
        }

        $data = $response->json();
        $text = $data['choices'][0]['message']['content'] ?? '';
        $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));

        return json_decode($text, true) ?? [];
    }
}
