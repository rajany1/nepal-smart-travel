<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BipadApiService
{
    protected string $baseUrl;
    protected int $timeout;
    protected int $pageSize;
    protected int $maxPagesRecent;

    public function __construct()
    {
        $this->baseUrl = config('bipad.base_url');
        $this->timeout = config('bipad.sync.timeout_seconds', 60); // Increased timeout
        $this->pageSize = config('bipad.sync.page_size', 100);
        $this->maxPagesRecent = config('bipad.sync.max_pages_recent', 3); // Only 3 pages = 300 records
    }

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout($this->timeout)
            ->acceptJson()
            ->retry(3, 2000, throw: false); // Increased retry delay
    }

    public function fetchIncidents(int $offset = 0, ?int $limit = null, bool $orderByCreated = true): array
    {
        $limit = $limit ?? $this->pageSize;
        $url = "{$this->baseUrl}" . config('bipad.endpoints.incidents');

        $extraParams = $orderByCreated ? ['ordering' => '-createdOn'] : [];

        return $this->fetchPaginated($url, $offset, $limit, $extraParams);
    }

    public function fetchAlerts(int $offset = 0, ?int $limit = null, bool $orderByCreated = true): array
    {
        $limit = $limit ?? $this->pageSize;
        $url = "{$this->baseUrl}" . config('bipad.endpoints.alerts');

        $extraParams = $orderByCreated ? ['ordering' => '-createdOn'] : [];

        return $this->fetchPaginated($url, $offset, $limit, $extraParams);
    }

    public function fetchHazards(): array
    {
        $url = "{$this->baseUrl}" . config('bipad.endpoints.hazards');

        $cached = Cache::get('bipad:hazards');
        if ($cached) {
            return $cached;
        }

        $response = $this->client()->get($url, ['format' => 'json']);

        if (!$response->successful()) {
            Log::error('BIPAD hazards fetch failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $data = $response->json();
        $hazards = $data['results'] ?? $data ?? [];

        Cache::put('bipad:hazards', $hazards, now()->addHours(24));

        return $hazards;
    }

    public function fetchDistricts(): array
    {
        $url = "{$this->baseUrl}" . config('bipad.endpoints.districts');

        $cached = Cache::get('bipad:districts');
        if ($cached) {
            return $cached;
        }

        $response = $this->client()->get($url, ['format' => 'json', 'limit' => 100]);

        if (!$response->successful()) {
            Log::error('BIPAD districts fetch failed', [
                'status' => $response->status(),
            ]);
            return [];
        }

        $data = $response->json();
        $districts = $data['results'] ?? $data ?? [];

        Cache::put('bipad:districts', $districts, now()->addHours(24));

        return $districts;
    }

    public function getHazardInfo(int $hazardId): ?array
    {
        $hazards = $this->fetchHazards();

        foreach ($hazards as $hazard) {
            if (($hazard['id'] ?? null) == $hazardId) {
                return $hazard;
            }
        }

        return null;
    }

    public function getHazardMapping(?int $hazardId): array
    {
        if ($hazardId === null) {
            return [
                'type' => 'unknown',
                'severity' => 'medium',
                'category' => 'other',
            ];
        }
        return config("bipad.hazard_map.{$hazardId}", [
            'type' => 'unknown',
            'severity' => 'medium',
            'category' => 'other',
        ]);
    }

    protected function fetchPaginated(string $url, int $offset, int $limit, array $extraParams = []): array
    {
        $params = array_merge([
            'format' => 'json',
            'limit' => $limit,
            'offset' => $offset,
        ], $extraParams);

        $response = $this->client()->get($url, $params);

        if (!$response->successful()) {
            Log::error('BIPAD paginated fetch failed', [
                'url' => $url,
                'params' => $params,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ];
        }

        return $response->json();
    }

    /**
     * Fetch latest incidents (up to maxPages) without date filtering.
     * Just fetches the latest pages and lets dedup handle it.
     */
    public function fetchRecentIncidents(callable $callback, int $hours = 24, int $maxPages = null): void
    {
        $maxPages = $maxPages ?? $this->maxPagesRecent;
        $offset = 0;
        $hasMore = true;
        $page = 0;

        while ($hasMore && $page < $maxPages) {
            $data = $this->fetchIncidents($offset, $this->pageSize, true);
            $results = $data['results'] ?? [];

            foreach ($results as $incident) {
                $callback($incident);
            }

            $hasMore = !empty($data['next']) && !empty($results);
            $offset += $this->pageSize;
            $page++;

            if (count($results) < $this->pageSize) {
                $hasMore = false;
            }
        }

        Log::info('BIPAD incidents fetch complete', [
            'pages' => $page,
            'max_pages' => $maxPages,
        ]);
    }

    /**
     * Fetch latest alerts (up to maxPages) without date filtering.
     */
    public function fetchRecentAlerts(callable $callback, int $hours = 24, int $maxPages = null): void
    {
        $maxPages = $maxPages ?? $this->maxPagesRecent;
        $offset = 0;
        $hasMore = true;
        $page = 0;

        while ($hasMore && $page < $maxPages) {
            $data = $this->fetchAlerts($offset, $this->pageSize, true);
            $results = $data['results'] ?? [];

            foreach ($results as $alert) {
                $callback($alert);
            }

            $hasMore = !empty($data['next']) && !empty($results);
            $offset += $this->pageSize;
            $page++;

            if (count($results) < $this->pageSize) {
                $hasMore = false;
            }
        }

        Log::info('BIPAD alerts fetch complete', [
            'pages' => $page,
            'max_pages' => $maxPages,
        ]);
    }

    /**
     * Parse BIPAD date string (ISO 8601 with +05:45 offset) to Carbon in UTC
     */
    public function parseDate(?string $dateString): ?Carbon
    {
        if (!$dateString) {
            return null;
        }

        try {
            return Carbon::parse($dateString)->setTimezone('UTC');
        } catch (\Throwable $e) {
            Log::warning('BIPAD date parse failed', ['date' => $dateString, 'error' => $e->getMessage()]);
            return null;
        }
    }
}