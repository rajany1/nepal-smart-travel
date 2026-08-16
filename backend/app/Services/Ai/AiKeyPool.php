<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiKeyPool
{
    protected array $keys;
    protected int $cursor = 0;

    public function __construct(array $keys)
    {
        $this->keys = array_values(array_unique(array_filter(array_map('trim', $keys))));
    }

    public function isEmpty(): bool
    {
        return empty($this->keys);
    }

    public function count(): int
    {
        return count($this->keys);
    }

    public function keys(): array
    {
        return $this->keys;
    }

    public function next(): ?string
    {
        $n = count($this->keys);
        if ($n === 0) return null;

        for ($i = 0; $i < $n; $i++) {
            $key = $this->keys[($this->cursor + $i) % $n];
            if (!Cache::has($this->cacheKey($key))) {
                $this->cursor = ($this->cursor + $i + 1) % $n;

                return $key;
            }
        }

        return null;
    }

    public function isBlocked(string $key): bool
    {
        return Cache::has($this->cacheKey($key));
    }

    public function block(string $key, int $seconds): void
    {
        if ($seconds <= 0 || !in_array($key, $this->keys, true)) return;

        Cache::put($this->cacheKey($key), true, $seconds);
        Log::info('Gemini key blocked for ' . $seconds . 's: ' . substr($key, 0, 8) . '...');
    }

    public function blockUntilEndOfDay(string $key): void
    {
        $seconds = now()->diffInSeconds(now()->endOfDay()) + 60;
        $this->block($key, $seconds);
    }

    protected function cacheKey(string $key): string
    {
        return 'ai_gemini_key_blocked_' . md5($key);
    }
}
