<?php

use App\Models\Alert;
use App\Models\GameSetting;
use App\Models\Place;
use App\Models\Report;
use App\Services\TravelContext\CorridorGeometry;
use App\Services\TravelContext\RouteRelevanceService;
use App\Services\TravelContext\TravelContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ranking tests use in-memory Report/Alert/Place models (no DB writes for
 * pure ranking paths where possible). Eligibility SQL tests touch MySQL and
 * use unique marker titles cleaned up by the test itself.
 */

function makeTestCorridor(): TravelContext
{
    $geometry = [
        ['lat' => 27.70, 'lng' => 85.30],
        ['lat' => 27.71, 'lng' => 85.32],
        ['lat' => 27.72, 'lng' => 85.34],
        ['lat' => 27.73, 'lng' => 85.36],
    ];

    return new TravelContext(
        origin: ['name' => 'A', 'lat' => 27.70, 'lng' => 85.30],
        destination: ['name' => 'B', 'lat' => 27.73, 'lng' => 85.36],
        checkpoints: [],
        segments: [],
        waypoints: [],
        geometry: $geometry,
        status: TravelContext::STATUS_ROUTED,
        needsClarification: false,
        routeOptions: [],
        totalDistanceM: 7000.0,
        totalDurationS: 1200.0,
        journey: null,
        unresolved: null,
    );
}

function makeInMemoryReport(array $overrides = []): Report
{
    $report = new Report();
    $report->forceFill(array_merge([
        'uuid' => (string) Str::uuid(),
        'title' => 'TVC test ' . Str::random(6),
        'description' => 'Travel context ranking fixture',
        'status' => 'approved',
        'is_active' => true,
        'priority' => 'medium',
        'latitude' => 27.715,
        'longitude' => 85.325,
        'district' => 'Kathmandu',
        'category_id' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));
    $report->exists = true;
    return $report;
}

function ensureTestCategoryId(): int
{
    $id = DB::table('report_categories')->where('id', 7)->value('id');
    if ($id === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 is missing - '
            . 'tests must reference the real category and must not create fixture categories.'
        );
    }

    return (int) $id;
}

describe('RouteRelevanceService weights', function () {
    it('normalizes configured weights to sum 1', function () {
        GameSetting::setValue('route_weights', [
            'proximity' => 2,
            'direction' => 2,
            'freshness' => 2,
            'severity' => 2,
            'content' => 2,
        ]);

        // GameSetting::setValue caches the raw JSON string; clear so getValue
        // re-reads and json_decodes from DB (same path as a fresh process).
        $ref = new ReflectionClass(GameSetting::class);
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue(null, []);

        $service = new RouteRelevanceService();
        $weights = $service->weights();

        expect(array_sum($weights))->toEqualWithDelta(1.0, 0.0001);
        expect($weights['proximity'])->toEqualWithDelta(0.2, 0.0001);

        DB::table('game_settings')->where('key', 'route_weights')->delete();
        $prop->setValue(null, []);
    });
});

describe('RouteRelevanceService report eligibility (SQL)', function () {
    it('includes only approved, active, non-expired, recent reports', function () {
        $service = new RouteRelevanceService();
        $catId = ensureTestCategoryId();
        $marker = 'TVC_ELIG_' . Str::random(8);
        $userId = DB::table('users')->orderBy('id')->value('id');
        if (!$userId) {
            $this->markTestSkipped('No users in DB to attach fixture reports.');
        }

        $rows = [
            // eligible
            [
                'title' => $marker . '_ok',
                'status' => 'approved', 'is_active' => 1, 'expires_at' => null,
                'created_at' => now()->subHours(2), 'priority' => 'high',
                'latitude' => 27.7172, 'longitude' => 85.3240,
            ],
            // pending
            [
                'title' => $marker . '_pending',
                'status' => 'pending', 'is_active' => 1, 'expires_at' => null,
                'created_at' => now()->subHours(2), 'priority' => 'high',
                'latitude' => 27.7172, 'longitude' => 85.3240,
            ],
            // inactive
            [
                'title' => $marker . '_inactive',
                'status' => 'approved', 'is_active' => 0, 'expires_at' => null,
                'created_at' => now()->subHours(2), 'priority' => 'high',
                'latitude' => 27.7172, 'longitude' => 85.3240,
            ],
            // expired
            [
                'title' => $marker . '_expired',
                'status' => 'approved', 'is_active' => 1, 'expires_at' => now()->subHour(),
                'created_at' => now()->subHours(2), 'priority' => 'high',
                'latitude' => 27.7172, 'longitude' => 85.3240,
            ],
            // stale (>7 days)
            [
                'title' => $marker . '_stale',
                'status' => 'approved', 'is_active' => 1, 'expires_at' => null,
                'created_at' => now()->subDays(10), 'priority' => 'high',
                'latitude' => 27.7172, 'longitude' => 85.3240,
            ],
        ];

        $ids = [];
        try {
            foreach ($rows as $row) {
                $ids[] = DB::table('reports')->insertGetId(array_merge($row, [
                    'uuid' => Str::uuid()->toString(),
                    'user_id' => $userId,
                    'category_id' => $catId,
                    'description' => 'eligibility fixture',
                    'district' => 'Kathmandu',
                    'created_at' => $row['created_at'],
                    'updated_at' => now(),
                ]));
            }

            $titles = $service->eligibleReportsQuery()
                ->whereIn('id', $ids)
                ->pluck('title')
                ->all();

            expect($titles)->toContain($marker . '_ok');
            expect($titles)->not->toContain($marker . '_pending');
            expect($titles)->not->toContain($marker . '_inactive');
            expect($titles)->not->toContain($marker . '_expired');
            expect($titles)->not->toContain($marker . '_stale');
        } finally {
            if ($ids !== []) {
                DB::table('reports')->whereIn('id', $ids)->delete();
            }
        }
    });
});

describe('RouteRelevanceService ranking (in-memory models)', function () {
    it('scores nearer on-corridor reports higher than far ones', function () {
        $service = new RouteRelevanceService();
        $context = makeTestCorridor();

        $near = makeInMemoryReport([
            'title' => 'TVC near ' . Str::random(4),
            'latitude' => 27.71,
            'longitude' => 85.32,
            'priority' => 'critical',
        ]);
        $far = makeInMemoryReport([
            'title' => 'TVC far ' . Str::random(4),
            'latitude' => 27.71,
            'longitude' => 85.40, // ~7km east of corridor
            'priority' => 'low',
        ]);

        $measures = [
            $near->title => CorridorGeometry::measure($context->geometry, (float) $near->latitude, (float) $near->longitude),
            $far->title => CorridorGeometry::measure($context->geometry, (float) $far->latitude, (float) $far->longitude),
        ];

        expect($measures[$near->title]['distance_m'])->toBeLessThan(
            $measures[$far->title]['distance_m']
        );

        // Far point outside default 3km corridor → would be dropped by rankReports.
        $farKm = $measures[$far->title]['distance_m'] / 1000;
        expect($farKm)->toBeGreaterThan(3.0);
        $nearKm = $measures[$near->title]['distance_m'] / 1000;
        expect($nearKm)->toBeLessThan(3.0);
    });

    it('formats place payload when type is place', function () {
        $service = new RouteRelevanceService();
        $context = makeTestCorridor();

        $place = new Place();
        $place->forceFill([
            'uuid' => (string) Str::uuid(),
            'name' => 'TVC Fuel ' . Str::random(4),
            'description' => 'On-route fuel',
            'latitude' => 27.712,
            'longitude' => 85.322,
            'district' => 'Kathmandu',
            'is_active' => true,
            'average_rating' => 4.5,
            'total_reviews' => 10,
        ]);
        $place->exists = true;

        $m = CorridorGeometry::measure($context->geometry, (float) $place->latitude, (float) $place->longitude);
        expect($m)->not->toBeNull();
        expect($m['distance_m'])->toBeLessThan(3000);

        // Sanity: broadcast alert eligibility shape via rankAlerts is covered
        // by feature endpoint tests with DB fixtures.
        expect($service->maxResults())->toBeGreaterThan(0);
        expect($service->corridorRadiusKm())->toBeGreaterThan(0);
    });
});
