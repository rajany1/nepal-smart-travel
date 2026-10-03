<?php

namespace App\Services\Wikimedia;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Redis;

/**
 * Thin, polite client for the Wikimedia *APIs* (Commons / Wikidata /
 * Wikipedia). We never fetch or parse HTML pages — only documented
 * `action=query` style JSON endpoints.
 *
 * Responsibilities kept here so the discovery service stays readable:
 *  - descriptive User-Agent (Wikimedia policy) + timeout
 *  - a single Redis rate limit shared by every worker and job
 *  - 429/`Retry-After` handling
 *  - short-lived response caching (traffic saving only — never a record of
 *    what we stored, which lives in `place_images`)
 *  - a circuit breaker so a Wikimedia outage costs a handful of skipped
 *    jobs instead of thousands of doomed retries
 */
class WikimediaClient
{
    public const RATE_KEY = 'wikimedia:api';

    private const FAILURE_KEY = 'wikimedia:failures';
    private const CIRCUIT_KEY = 'wikimedia:circuit';
    private const REQUEST_COUNTER_KEY = 'wikimedia:requests';

    private int $liveRequests = 0;

    /**
     * Call a Wikimedia API endpoint and return the decoded payload, or null
     * when the request failed / was refused.
     *
     * @param string $endpoint config key: commons|wikidata|wikipedia|wikipedia_ne
     * @param array  $params   query parameters (action, format… are added)
     */
    public function api(string $endpoint, array $params): ?array
    {
        $base = config("images.endpoints.{$endpoint}");
        if (!$base) {
            Log::warning('WikimediaClient: unknown endpoint', ['endpoint' => $endpoint]);
            return null;
        }

        $params = array_filter([
            'format' => 'json',
            'formatversion' => '2',
        ] + $params, static fn ($value) => $value !== null && $value !== '');

        $url = $base . '?' . http_build_query($params);
        $cacheKey = 'wikimedia:resp:' . sha1($url);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if ($this->circuitOpen()) {
            return null;
        }

        if (!$this->awaitSlot()) {
            // Rate budget exhausted for now — caller decides whether to
            // release the job. Signalled distinctly from "Wikimedia is down".
            throw new RateLimitExceededException('Wikimedia API rate budget exhausted');
        }

        $payload = $this->getJson($url);
        if ($payload === null) {
            $this->recordFailure();
            return null;
        }

        $this->recordSuccess();
        Cache::put($cacheKey, $payload, (int) config('images.cache_ttl', 86400));

        return $payload;
    }

    /** Decode a JSON GET with the configured UA/timeout and 429 handling. */
    private function getJson(string $url): ?array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => (string) config('images.user_agent'),
                    'Accept' => 'application/json',
                ])
                    ->timeout((int) config('images.timeout', 20))
                    ->get($url);

                $this->liveRequests++;
                $this->incrementGlobalCounter();

                if ($response->status() === 429) {
                    $retryAfter = (int) $response->header('Retry-After');
                    $sleep = min(max($retryAfter, 1), 30);
                    Log::warning('Wikimedia API rate limited', ['sleep' => $sleep]);
                    sleep($sleep);
                    continue;
                }

                if ($response->failed()) {
                    Log::warning('Wikimedia API HTTP error', [
                        'status' => $response->status(),
                        'url' => substr($url, 0, 200),
                    ]);

                    return null;
                }

                $json = $response->json();
                if (!is_array($json)) {
                    Log::warning('Wikimedia API returned non-JSON payload');
                    return null;
                }

                return $json;
            } catch (ConnectionException $e) {
                $this->incrementGlobalCounter();
                Log::warning('Wikimedia API connection failed', ['error' => $e->getMessage()]);
                sleep(1);
            } catch (RequestException $e) {
                $this->incrementGlobalCounter();
                Log::warning('Wikimedia API request exception', ['error' => $e->getMessage()]);

                return null;
            }
        }

        return null;
    }

    /**
     * Block until a rate-limit slot is free.
     *
     * @return bool true when a slot was taken, false when the caller should
     *              give up for now (and release its job instead).
     */
    public function awaitSlot(int $maxWaitSeconds = 30): bool
    {
        $max = (int) config('images.rate_limit.max', 60);
        $decay = (int) config('images.rate_limit.seconds', 60);

        $waited = 0.0;
        while (RateLimiter::tooManyAttempts(self::RATE_KEY, $max)) {
            if ($waited >= $maxWaitSeconds) {
                return false;
            }
            usleep(250_000);
            $waited += 0.25;
        }

        RateLimiter::hit(self::RATE_KEY, $decay);

        return true;
    }

    /** Requests served to Wikimedia (cached hits excluded), process-local. */
    public function liveRequestCount(): int
    {
        return $this->liveRequests;
    }

    /** Persistent counter across worker processes (for run reports). */
    public function incrementGlobalCounter(): void
    {
        try {
            Redis::incr(self::REQUEST_COUNTER_KEY);
        } catch (\Throwable) {
            // Counting is best-effort — never fail a discovery over it.
        }
    }

    public function globalCounter(): int
    {
        try {
            return (int) Redis::get(self::REQUEST_COUNTER_KEY);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function resetGlobalCounter(): void
    {
        try {
            Redis::del(self::REQUEST_COUNTER_KEY);
        } catch (\Throwable) {
        }
    }

    private function recordFailure(): void
    {
        try {
            $failures = (int) Redis::incr(self::FAILURE_KEY);
            Redis::expire(self::FAILURE_KEY, 300);

            if ($failures >= (int) config('images.circuit_breaker.threshold', 5)) {
                Cache::put(self::CIRCUIT_KEY, 1, (int) config('images.circuit_breaker.cooldown', 600));
                Log::warning('Wikimedia circuit breaker opened', ['failures' => $failures]);
            }
        } catch (\Throwable) {
        }
    }

    private function recordSuccess(): void
    {
        try {
            Redis::del(self::FAILURE_KEY);
            Cache::forget(self::CIRCUIT_KEY);
        } catch (\Throwable) {
        }
    }

    public function circuitOpen(): bool
    {
        try {
            return Cache::has(self::CIRCUIT_KEY);
        } catch (\Throwable) {
            return false;
        }
    }
}
