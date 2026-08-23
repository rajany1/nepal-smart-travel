<?php

namespace App\Services\Ai;

use App\Exceptions\AiRateLimitException;
use Illuminate\Support\Facades\Log;

class AiFallbackRouter implements AiProviderInterface
{
    protected array $attempts;
    protected $validator;
    protected ?AiKeyPool $keyPool;

    /**
     * @param array $attempts  [['label' => string, 'provider' => AiProviderInterface, 'key' => ?string]]
     * @param callable|null $validator  fn(array $result): bool — JSON result usability gate
     */
    public function __construct(array $attempts, ?callable $validator = null, ?AiKeyPool $keyPool = null)
    {
        $this->attempts = array_values(array_filter($attempts, fn ($a) => isset($a['provider'])));
        $this->validator = $validator;
        $this->keyPool = $keyPool;
    }

    public function isEmpty(): bool
    {
        return empty($this->attempts);
    }

    public function labels(): array
    {
        return array_map(fn ($a) => $a['label'] ?? 'unknown', $this->attempts);
    }

    public function getAttempts(): array
    {
        return $this->attempts;
    }

    public function getKeyPool(): ?AiKeyPool
    {
        return $this->keyPool;
    }

    protected function run(callable $call): mixed
    {
        if ($this->isEmpty()) {
            throw new AiRateLimitException('No AI providers configured');
        }

        $errors = [];

        foreach ($this->attempts as $attempt) {
            $label = $attempt['label'] ?? 'provider';

            if ($this->keyPool && isset($attempt['key']) && $this->keyPool->isBlocked($attempt['key'])) {
                $errors[] = "{$label}: skipped (blocked)";
                continue;
            }

            try {
                $result = $call($attempt['provider'], $label);

                if ($result === null) {
                    continue;
                }

                if ($this->validator && is_array($result) && !($this->validator)($result)) {
                    $errors[] = "{$label}: unusable result";
                    continue;
                }

                if (is_array($result) && !isset($result['provider_used'])) {
                    $result['provider_used'] = $label;
                }

                return $result;
            } catch (AiRateLimitException $e) {
                $errors[] = "{$label}: rate-limited";
                if ($this->keyPool && isset($attempt['key'])) {
                    $this->keyPool->block($attempt['key'], 60);
                }
                if (str_contains($e->getMessage(), 'quota')) {
                    $this->blockQuotaKey($attempt);
                }
                continue;
            } catch (\Throwable $e) {
                $errors[] = "{$label}: " . $e->getMessage();
                continue;
            }
        }

        Log::warning('All AI providers failed: ' . implode(' | ', $errors));
        throw new AiRateLimitException('All AI providers failed: ' . implode(' | ', $errors));
    }

    protected function blockQuotaKey(array $attempt): void
    {
        if ($this->keyPool && isset($attempt['key'])) {
            $this->keyPool->blockUntilEndOfDay($attempt['key']);
        }
    }

    public function generate(string $prompt, array $options = []): string
    {
        $result = $this->run(function (AiProviderInterface $provider) use ($prompt, $options) {
            $text = $provider->generate($prompt, $options);

            return trim($text) !== '' ? $text : null;
        });

        return (string) $result;
    }

    public function generateJson(string $prompt, array $options = []): array
    {
        $result = $this->run(function (AiProviderInterface $provider) use ($prompt, $options) {
            $decoded = $provider->generateJson($prompt, $options);

            return $decoded !== [] ? $decoded : null;
        });

        return is_array($result) ? $result : [];
    }

    public function generateWithImages(string $prompt, array $imagePaths, array $options = []): string
    {
        $result = $this->run(function (AiProviderInterface $provider) use ($prompt, $imagePaths, $options) {
            $text = $provider->generateWithImages($prompt, $imagePaths, $options);

            return trim($text) !== '' ? $text : null;
        });

        return (string) $result;
    }

    public function generateJsonWithImages(string $prompt, array $imagePaths, array $options = []): array
    {
        $result = $this->run(function (AiProviderInterface $provider) use ($prompt, $imagePaths, $options) {
            $decoded = $provider->generateJsonWithImages($prompt, $imagePaths, $options);

            return $decoded !== [] ? $decoded : null;
        });

        return is_array($result) ? $result : [];
    }

    /**
     * Build the text-analysis chain from config:
     * text_chain (default: groq,gemini,cloudflare)
     */
    public static function textChain(?callable $validator = null): AiFallbackRouter
    {
        $attempts = [];
        $chain = array_filter(array_map('trim', explode(',', (string) config('services.ai.text_chain', 'groq,gemini,cloudflare'))));

        $keyPool = self::geminiKeyPool();

        foreach ($chain as $name) {
            switch ($name) {
                case 'groq':
                    $attempts[] = [
                        'label' => 'groq:' . config('services.ai.model', 'qwen/qwen3.6-27b'),
                        'provider' => new GroqService(config('services.ai.model', 'qwen/qwen3.6-27b')),
                    ];
                    break;

                case 'gemini':
                    foreach (self::geminiKeys($keyPool) as $key) {
                        $attempts[] = [
                            'label' => 'gemini:' . substr($key, 0, 8),
                            'provider' => new GeminiService(config('services.ai.gemini_text_model', 'gemini-3.6-flash'), $key),
                            'key' => $key,
                        ];
                    }
                    break;

                case 'cloudflare':
                    if (config('services.cloudflare.account_id') && config('services.cloudflare.api_token')) {
                        $attempts[] = [
                            'label' => 'cloudflare:' . config('services.cloudflare.text_model', '@cf/meta/llama-3.3-70b-instruct-fp8-fast'),
                            'provider' => new CloudflareService(),
                        ];
                    } else {
                        Log::warning('cloudflare in AI text chain but missing account_id/api_token — skipped');
                    }
                    break;
            }
        }

        return new AiFallbackRouter($attempts, $validator, $keyPool);
    }

    /**
     * Build the vision chain from config:
     * vision_chain (default: gemini,cloudflare)
     */
    public static function visionChain(?callable $validator = null): AiFallbackRouter
    {
        $attempts = [];
        $chain = array_filter(array_map('trim', explode(',', (string) config('services.ai.vision_chain', 'gemini,cloudflare'))));

        $keyPool = self::geminiKeyPool();

        foreach ($chain as $name) {
            switch ($name) {
                case 'gemini':
                    foreach (self::geminiKeys($keyPool) as $key) {
                        $attempts[] = [
                            'label' => 'gemini:' . substr($key, 0, 8),
                            'provider' => new GeminiService(config('services.ai.vision_model', 'gemini-flash-latest'), $key),
                            'key' => $key,
                        ];
                    }
                    break;

                case 'cloudflare':
                    if (config('services.cloudflare.account_id') && config('services.cloudflare.api_token')) {
                        $attempts[] = [
                            'label' => 'cloudflare:' . config('services.cloudflare.vision_model', '@cf/meta/llama-3.2-11b-vision-instruct'),
                            'provider' => new CloudflareService(),
                        ];
                    } else {
                        Log::warning('cloudflare in AI vision chain but missing account_id/api_token — skipped');
                    }
                    break;

                case 'groq':
                    $model = config('services.ai.vision_groq_model');
                    if ($model) {
                        $attempts[] = [
                            'label' => 'groq:' . $model,
                            'provider' => new GroqService($model),
                        ];
                    }
                    break;
            }
        }

        return new AiFallbackRouter($attempts, $validator, $keyPool);
    }

    protected static function geminiKeyPool(): AiKeyPool
    {
        $keys = config('services.gemini.api_keys', []);

        $single = config('services.gemini.api_key');
        if ($single && !in_array($single, $keys, true)) {
            $keys[] = $single;
        }

        return new AiKeyPool($keys);
    }

    protected static function geminiKeys(AiKeyPool $pool): array
    {
        if ($pool->isEmpty()) {
            Log::warning('Gemini selected in AI chain but no GEMINI_API_KEY / GEMINI_API_KEYS configured — skipped');
        }

        return $pool->keys();
    }
}
