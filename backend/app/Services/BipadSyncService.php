<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\BipadSyncLog;
use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BipadSyncService
{
    protected BipadApiService $api;
    protected AlertPublisherService $alertPublisher;

    public function __construct(
        BipadApiService $api,
        AlertPublisherService $alertPublisher
    ) {
        $this->api = $api;
        $this->alertPublisher = $alertPublisher;
    }

    public function syncIncidents(): BipadSyncLog
    {
        return $this->sync('incident', function ($callback) {
            // Only fetch recent incidents (last 6 hours, max 3 pages)
            $this->api->fetchRecentIncidents($callback, config('bipad.max_hours_old', 6), config('bipad.sync.max_pages_recent', 3));
        });
    }

    public function syncAlerts(): BipadSyncLog
    {
        return $this->sync('alert', function ($callback) {
            // Only fetch recent alerts (last 6 hours, max 3 pages)
            $this->api->fetchRecentAlerts($callback, config('bipad.max_hours_old', 6), config('bipad.sync.max_pages_recent', 3));
        });
    }

    public function sync(string $endpoint, callable $fetcher): BipadSyncLog
    {
        $log = BipadSyncLog::create([
            'endpoint' => $endpoint,
            'status' => 'started',
            'started_at' => now(),
            'metadata' => ['page_size' => config('bipad.sync.page_size', 100)],
        ]);

        $stats = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        Log::info('BIPAD sync: starting fetcher', ['endpoint' => $endpoint]);
            try {
                $fetcher(function ($item) use ($endpoint, &$stats, $log) {
                    $stats['fetched']++;
                    Log::info('BIPAD sync: fetcher called', ['id' => $item['id'] ?? 'no-id']);
                    try {
                        $result = $this->processItem($item, $endpoint);
                        
                        match ($result) {
                            'created' => $stats['created']++,
                            'updated' => $stats['updated']++,
                            'skipped' => $stats['skipped']++,
                        };
                    } catch (\Throwable $e) {
                        Log::error('BIPAD processItem failed', [
                            'endpoint' => $endpoint,
                            'item_id' => $item['id'] ?? 'unknown',
                            'item_keys' => array_keys($item),
                            'error' => $e->getMessage(),
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $stats['skipped']++;
                    }

                    if ($stats['fetched'] % 50 === 0) {
                        $log->update([
                            'records_fetched' => $stats['fetched'],
                            'records_created' => $stats['created'],
                            'records_updated' => $stats['updated'],
                            'records_skipped' => $stats['skipped'],
                        ]);
                    }
                });
            } catch (\Throwable $e) {

            $log->update([
                'status' => 'success',
                'completed_at' => now(),
                'records_fetched' => $stats['fetched'],
                'records_created' => $stats['created'],
                'records_updated' => $stats['updated'],
                'records_skipped' => $stats['skipped'],
            ]);

        } catch (\Throwable $e) {
            Log::error('BIPAD sync failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $log->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
                'records_fetched' => $stats['fetched'],
                'records_created' => $stats['created'],
                'records_updated' => $stats['updated'],
                'records_skipped' => $stats['skipped'],
            ]);
        }

        return $log->fresh();
    }

    protected function processItem(array $item, string $endpoint): string
    {
        Log::info('BIPAD processItem called', ['id' => $item['id'] ?? 'no-id', 'endpoint' => $endpoint]);
        
        $bipadId = $item['id'] ?? null;
        if (!$bipadId) {
            Log::warning('BIPAD processItem: no bipadId', ['item' => $item]);
            return 'skipped';
        }

        // Check if report already exists for this BIPAD item
        $existing = Report::where('source', 'bipad')
            ->where('bipad_id', $bipadId)
            ->first();

        try {
            $reportData = $this->transformToReport($item, $endpoint);
        } catch (\Exception $e) {
            Log::info('BIPAD processItem: skipping', ['bipadId' => $bipadId, 'reason' => $e->getMessage()]);
            return 'skipped';
        }

        Log::info('BIPAD processItem: transformed', ['bipadId' => $bipadId, 'existing' => $existing ? 'yes' : 'no']);

        if ($existing) {
            if ($this->shouldUpdateReport($existing, $reportData)) {
                $existing->update($reportData);
                return 'updated';
            }
            Log::info('BIPAD processItem: skipped (no update needed)', ['bipadId' => $bipadId]);
            return 'skipped';
        }

        // Create system user if not cached
        $systemUser = $this->getSystemUser();
        if (!$systemUser) {
            Log::error('BIPAD: System user not found');
            return 'skipped';
        }

        Log::info('BIPAD processItem: creating report', ['bipadId' => $bipadId, 'user_id' => $systemUser->id]);

        $report = Report::create(array_merge($reportData, [
            'uuid' => (string) Str::uuid(),
            'user_id' => $systemUser->id,
            'source' => 'bipad',
            'provenance' => 'system',
            'bipad_id' => $bipadId,
        ]));

        // Trigger the same flow as admin approval - this creates Alert + sends push
        $this->alertPublisher->publishFromReport($report->fresh());

        return 'created';
    }

    /**
     * Transform BIPAD item to Report data
     */
    protected function transformToReport(array $item, string $endpoint): array
    {
        // Check if hazard type should be skipped
        $hazardId = $item['hazard'] ?? null;
        $hazardMapping = $this->api->getHazardMapping($hazardId);
        $hazardType = $hazardMapping['type'] ?? 'unknown';
        
        // Skip weather/condition types
        $skipTypes = config('bipad.skip_hazard_types', []);
        if (in_array($hazardType, $skipTypes)) {
            throw new \Exception("Skipping hazard type: {$hazardType}");
        }

        // Check if item is too old (real-time only)
        $maxHoursOld = config('bipad.max_hours_old', 6);
        $createdOn = $this->api->parseDate($item['createdOn'] ?? $item['incidentOn'] ?? $item['startedOn'] ?? null);
        if ($createdOn && $createdOn->lt(now()->subHours($maxHoursOld))) {
            throw new \Exception("Skipping old data: {$createdOn->diffForHumans()}");
        }

        $point = $item['point'] ?? null;
        $coordinates = $point['coordinates'] ?? [null, null];
        [$longitude, $latitude] = $coordinates;

        // Get human-readable title and description
        $title = $this->formatTitle($item, $hazardMapping);
        $titleNe = $item['titleNe'] ?? null;
        $description = $this->formatDescription($item, $hazardMapping);

        $startedOn = $item['startedOn'] ?? $item['incidentOn'] ?? $item['createdOn'] ?? null;
        $expireOn = $item['expireOn'] ?? $item['reportedOn'] ?? null;

        $expiresAt = $expireOn ? Carbon::parse($expireOn)->setTimezone('UTC') : now()->addHours(48);

        $isVerified = $item['verified'] ?? false;
        $isPublic = $item['public'] ?? false;

        $affectedDistrict = $this->resolveDistrict($item);

        $categoryId = $this->mapReportCategory($hazardId, $hazardMapping);
        $priority = $this->mapReportPriority($hazardMapping, $isVerified);
        $subCategory = $this->mapReportSubCategory($hazardId, $hazardMapping);

        return [
            'title' => $title,
            'description' => $description,
            'category_id' => $categoryId,
            'report_subcategory' => $subCategory,
            'priority' => $priority,
            'status' => 'approved',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'district' => $affectedDistrict,
            'location_name' => $this->extractLocationName($item),
            'verified_by' => $this->getSystemUser()?->id,
            'verified_at' => now(),
            'is_active' => true,
            'expires_at' => $expiresAt,
            'bipad_hazard_id' => $hazardId,
            'bipad_source' => $endpoint,
            'bipad_raw' => $item,
            'ai_analysis' => [
                'bipad_hazard' => $hazardType,
                'bipad_severity' => $hazardMapping['severity'] ?? 'medium',
                'bipad_verified' => $isVerified,
                'bipad_source_type' => $item['source'] ?? 'unknown',
            ],
            'ai_analyzed_at' => now(),
            'authenticity_score' => $isVerified ? 95 : 70,
        ];
    }

    /**
     * Format human-readable title from BIPAD data
     */
    protected function formatTitle(array $item, array $hazardMapping): string
    {
        $hazardType = $hazardMapping['type'] ?? 'unknown';
        $titleNe = $item['titleNe'] ?? null;
        
        // Prefer Nepali title if available
        if ($titleNe) {
            return $titleNe;
        }

        // Extract location from English title
        $title = $item['title'] ?? '';
        if (str_contains($title, ' at ')) {
            $parts = explode(' at ', $title);
            $hazardName = $parts[0];
            $location = $parts[1] ?? '';
            return "{$hazardName} at {$location}";
        }

        return $title ?: ucfirst($hazardType);
    }

    /**
     * Format human-readable description from BIPAD data
     */
    protected function formatDescription(array $item, array $hazardMapping): string
    {
        $hazardType = $hazardMapping['type'] ?? 'unknown';
        $rawDesc = $item['description'] ?? $item['detail'] ?? '';
        
        // Parse technical description
        $parts = [];
        
        // Extract key info from raw description
        if ($rawDesc) {
            // Basin, Elevation, Warning level, Water level, Brightness, Confidence, etc.
            $lines = preg_split('/\s*\n\s*/', $rawDesc);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parse key:value pairs
                if (preg_match('/^(\w+):\s*(.+)$/', $line, $m)) {
                    $key = strtolower($m[1]);
                    $val = trim($m[2]);
                    
                    $label = match ($key) {
                        'basin' => 'नदी बेसिन',
                        'elevation' => 'उचाइ',
                        'warning level' => 'चेतावनी स्तर',
                        'water level' => 'पानीको स्तर',
                        'brightness' => 'चम्किलो',
                        'confidence' => 'आत्मविश्वास',
                        'land cover' => 'भूमि प्रकार',
                        'scan' => 'स्क्यान',
                        'event_on' => 'घटना समय',
                        'magnitude' => 'म्याग्निचुड',
                        'aqi' => 'AQI',
                        default => ucfirst($key),
                    };
                    
                    $parts[] = "{$label}: {$val}";
                } elseif (str_starts_with($line, '[')) {
                    // Skip array-like data
                } else {
                    $parts[] = $line;
                }
            }
        }

        // Add location info
        $location = $this->extractLocationName($item);
        if ($location) {
            array_unshift($parts, "स्थान: {$location}");
        }

        // Add hazard type in Nepali
        $hazardNe = $this->getHazardNameNe($hazardMapping['type'] ?? '');
        if ($hazardNe) {
            array_unshift($parts, "प्रकार: {$hazardNe}");
        }

        return implode("\n", $parts) ?: 'विवरण उपलब्ध छैन';
    }

    /**
     * Get Nepali name for hazard type
     */
    protected function getHazardNameNe(string $type): string
    {
        $map = [
            'fire' => 'आगलागी',
            'forest_fire' => 'वन आगलागी',
            'flood' => 'बाढी',
            'inundation' => 'डुबान',
            'glacial_lake_outburst' => 'हिमताल विस्फोट',
            'landslide' => 'पहिरो',
            'avalanche' => 'हिमपहिरो',
            'earthquake' => 'भूकम्प',
            'road_accident' => 'सडक दुर्घटना',
            'snake_bite' => 'सर्पदंश',
            'animal_incident' => 'जनावर आक्रमण',
            'epidemic' => 'महामारी',
            'boat_capsize' => 'डुंगा पल्टिनु',
            'drowning' => 'डुबेर मर्नु',
            'bridge_collapse' => 'पुल भत्कीनु',
            'industrial_disaster' => 'औद्योगिक दुर्घटना',
            'gas_explosion' => 'ग्य्यास विष्फोट',
            'high_altitude' => 'उच्च ऊँचाई घटना',
        ];
        
        return $map[$type] ?? ucfirst($type);
    }

    protected function shouldUpdateReport(Report $existing, array $newData): bool
    {
        $fieldsToCompare = ['title', 'description', 'priority', 'latitude', 'longitude', 'expires_at', 'district'];
        
        foreach ($fieldsToCompare as $field) {
            $old = $existing->getAttribute($field);
            $new = $newData[$field] ?? null;
            
            if ($old != $new) {
                return true;
            }
        }

        return false;
    }

    protected function resolveDistrict(array $item): ?string
    {
        if (!empty($item['region'])) {
            return $item['region'];
        }

        if (!empty($item['wards']) && is_array($item['wards'])) {
            return 'Ward ' . implode(', ', $item['wards']);
        }

        $point = $item['point'] ?? null;
        if ($point && isset($point['coordinates'])) {
            [$lng, $lat] = $point['coordinates'];
            return "Lat: {$lat}, Lng: {$lng}";
        }

        return null;
    }

    protected function extractLocationName(array $item): ?string
    {
        if (!empty($item['streetAddress'])) {
            return $item['streetAddress'];
        }
        
        if (!empty($item['title'])) {
            // Try to extract location from title like "Fire at X, Municipality-Y"
            $parts = explode(' at ', $item['title']);
            if (count($parts) > 1) {
                return trim($parts[1]);
            }
        }
        
        return null;
    }

    /**
     * Map BIPAD hazard to Report category
     */
    protected function mapReportCategory(?int $hazardId, array $hazardMapping): int
    {
        $hazardType = $hazardMapping['type'] ?? 'unknown';
        
        // Category mapping from config
        $categoryMap = config('bipad.report_category_map', []);
        if (isset($categoryMap[$hazardType])) {
            $categoryName = $categoryMap[$hazardType];
            $category = ReportCategorie::where('name', $categoryName)->first();
            if ($category) {
                return $category->id;
            }
        }

        // Fallback: find or create generic category
        $fallbackCategory = ReportCategorie::where('name', 'General Information')->first();
        return $fallbackCategory?->id ?? 1;
    }

    /**
     * Map BIPAD hazard to Report sub-category
     */
    protected function mapReportSubCategory(?int $hazardId, array $hazardMapping): ?string
    {
        $hazardType = $hazardMapping['type'] ?? 'unknown';
        
        $subCategoryMap = config('bipad.report_subcategory_map', []);
        return $subCategoryMap[$hazardType] ?? null;
    }

    /**
     * Map hazard severity to Report priority
     */
    protected function mapReportPriority(array $hazardMapping, bool $isVerified): string
    {
        $baseSeverity = $hazardMapping['severity'] ?? 'medium';
        
        if ($isVerified) {
            return match ($baseSeverity) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'critical',
                'critical' => 'critical',
                default => 'high',
            };
        }

        return match ($baseSeverity) {
            'low' => 'low',
            'medium' => 'medium',
            'high' => 'high',
            'critical' => 'critical',
            default => 'medium',
        };
    }

    /**
     * Get or cache the system user
     */
    protected function getSystemUser(): ?User
    {
        static $systemUser = null;
        
        if ($systemUser !== null) {
            return $systemUser;
        }

        $systemUser = User::where('is_system', true)->first();
        
        // Cache even if null to avoid repeated queries
        return $systemUser;
    }
}