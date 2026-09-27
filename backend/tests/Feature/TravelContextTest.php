<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OSRM fake response - a simple straight-ish multi-point road geometry
 * between Kathmandu and Pokhara-ish coordinates.
 */
function osrmOkResponse(float $fromLat, float $fromLng, float $toLat, float $toLng): array
{
    $coords = [];
    $steps = 12;
    for ($i = 0; $i <= $steps; $i++) {
        $t = $i / $steps;
        $coords[] = [
            $fromLng + (($toLng - $fromLng) * $t),
            $fromLat + (($toLat - $fromLat) * $t),
        ];
    }

    return [
        'code' => 'Ok',
        'routes' => [[
            'distance' => 200000.0,
            'duration' => 18000.0,
            'geometry' => ['type' => 'LineString', 'coordinates' => $coords],
        ]],
        'waypoints' => [],
    ];
}

function fakeOsrm(): void
{
    Http::fake([
        'router.project-osrm.org/*' => function ($request) {
            // URL: .../driving/lng,lat;lng,lat?...
            $path = parse_url($request->url(), PHP_URL_PATH) ?: '';
            if (preg_match('#/driving/(-?\d+\.?\d*),(-?\d+\.?\d*);(-?\d+\.?\d*),(-?\d+\.?\d*)#', $path, $m)) {
                return Http::response(
                    osrmOkResponse((float) $m[2], (float) $m[1], (float) $m[4], (float) $m[3]),
                    200
                );
            }
            return Http::response(osrmOkResponse(27.7, 85.3, 27.7, 85.4), 200);
        },
    ]);
}

function insertTvcUser(): int
{
    return \Illuminate\Support\Facades\DB::table('users')->insertGetId([
        'name' => 'TVC Test User',
        'email' => 'tvc_' . Str::random(8) . '@example.com',
        'email_verified_at' => now(),
        'phone' => '98' . mt_rand(10000000, 99999999),
        'password' => bcrypt('password'),
        'uuid' => Str::uuid()->toString(),
        'role_id' => \Illuminate\Support\Facades\DB::table('roles')->where('name', 'user')->value('id') ?? 1,
        'status' => 'active',
        'is_active' => 1,
        'badges' => json_encode([]),
        'expertise_regions' => json_encode([]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertTvcCategory(): int
{
    $id = \Illuminate\Support\Facades\DB::table('report_categories')->where('id', 7)->value('id');
    if ($id === null) {
        throw new RuntimeException(
            'Required existing production category report_categories.id=7 is missing - '
            . 'tests must reference the real category and must not create fixture categories.'
        );
    }

    return (int) $id;
}

describe('POST /api/v1/travel-context/resolve', function () {
    it('rejects missing origin/destination', function () {
        $res = $this->postJson('/api/v1/travel-context/resolve', []);
        $res->assertStatus(422);
    });

    it('resolves a corridor from coordinates without geocoding', function () {
        fakeOsrm();

        $res = $this->postJson('/api/v1/travel-context/resolve', [
            'origin' => '27.7172,85.3240',
            'destination' => '26.8167,83.4167',
            'detect_ambiguity' => false,
        ]);

        $res->assertOk()
            ->assertJsonPath('success', true);

        $tc = $res->json('data.travel_context');
        expect($tc)->not->toBeNull();
        expect($tc['status'])->toBe('routed');
        expect(count($tc['geometry']))->toBeGreaterThan(1);
        expect($tc['origin']['lat'])->toEqualWithDelta(27.7172, 0.001);
        expect($tc['destination']['lng'])->toEqualWithDelta(83.4167, 0.001);
    });

    it('returns 422 when origin cannot be resolved', function () {
        fakeOsrm();

        $res = $this->postJson('/api/v1/travel-context/resolve', [
            'origin' => 'ZZZ_NOT_A_PLACE_' . Str::random(8),
            'destination' => '27.7172,85.3240',
            'detect_ambiguity' => false,
        ]);

        $res->assertStatus(422);
        expect($res->json('success'))->toBeFalse();
    });
});

describe('GET|POST /api/v1/travel-context/intelligence', function () {
    it('rejects when neither travel_context nor origin/destination given', function () {
        $res = $this->getJson('/api/v1/travel-context/intelligence');
        $res->assertStatus(422);
    });

    it('accepts POST with nested travel_context (large corridor payloads)', function () {
        $travelContext = [
            'origin' => ['name' => 'A', 'lat' => 10.0, 'lng' => 10.0],
            'destination' => ['name' => 'B', 'lat' => 10.01, 'lng' => 10.01],
            'checkpoints' => [],
            'waypoints' => [],
            'status' => 'approximate',
            'needs_clarification' => false,
            'geometry' => [
                ['lat' => 10.0, 'lng' => 10.0],
                ['lat' => 10.01, 'lng' => 10.01],
            ],
            'segments' => [],
        ];

        $res = $this->postJson('/api/v1/travel-context/intelligence', [
            'travel_context' => $travelContext,
            'layers' => 'reports',
        ]);

        $res->assertOk()->assertJsonPath('success', true);
        expect($res->json('data.intelligence.summary.reports_count'))->toBe(0);
    });

    it('returns ranked intelligence for a provided travel_context payload', function () {
        fakeOsrm();

        $userId = insertTvcUser();
        $catId = insertTvcCategory();
        $marker = 'TVC_INTEL_' . Str::random(8);
        $reportId = null;

        try {
            $reportId = \Illuminate\Support\Facades\DB::table('reports')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $userId,
                'category_id' => $catId,
                'title' => $marker . ' landslide ahead',
                'description' => 'Approved on-corridor fixture',
                'status' => 'approved',
                'is_active' => 1,
                'priority' => 'high',
                'latitude' => 27.7172,
                'longitude' => 85.3240,
                'district' => 'Kathmandu',
                'created_at' => now()->subHour(),
                'updated_at' => now(),
            ]);

            // Build geometry client-side style payload (same shape as toPayload).
            $travelContext = [
                'origin' => ['name' => '27.7172,85.3240', 'lat' => 27.7172, 'lng' => 85.3240],
                'destination' => ['name' => '26.8167,83.4167', 'lat' => 26.8167, 'lng' => 83.4167],
                'checkpoints' => [],
                'waypoints' => [],
                'status' => 'routed',
                'needs_clarification' => false,
                'total_distance_km' => 200,
                'total_duration_min' => 300,
                'geometry' => [
                    ['lat' => 27.7172, 'lng' => 85.3240],
                    ['lat' => 27.30, 'lng' => 84.50],
                    ['lat' => 26.8167, 'lng' => 83.4167],
                ],
                'segments' => [[
                    'from_name' => 'A',
                    'to_name' => 'B',
                    'status' => 'routed',
                    'source' => 'osrm_driving',
                    'distance_m' => 200000,
                    'duration_s' => 18000,
                    'points' => [
                        ['lat' => 27.7172, 'lng' => 85.3240],
                        ['lat' => 27.30, 'lng' => 84.50],
                        ['lat' => 26.8167, 'lng' => 83.4167],
                    ],
                ]],
                'journey' => null,
            ];

            $res = $this->call(
                'GET',
                '/api/v1/travel-context/intelligence',
                ['travel_context' => json_encode($travelContext)]
            );

            $res->assertOk()->assertJsonPath('success', true);

            $reports = collect($res->json('data.intelligence.reports') ?? []);
            expect($reports->pluck('title')->all())->toContain($marker . ' landslide ahead');

            $hit = $reports->firstWhere('title', $marker . ' landslide ahead');
            expect($hit['type'])->toBe('report');
            expect($hit['relevance_score'])->toBeGreaterThan(0);
            expect($hit['direction'])->toBeIn(['ahead', 'passed', 'behind_severe']);

            expect($res->json('data.intelligence.summary.total'))->toBeGreaterThan(0);
            expect($res->json('meta.empty'))->toBeFalse();
        } finally {
            if ($reportId !== null) {
                \Illuminate\Support\Facades\DB::table('reports')->where('id', $reportId)->delete();
            }
            \Illuminate\Support\Facades\DB::table('users')->where('id', $userId)->delete();
        }
    });

    it('returns empty bundle without crashing for sparse corridor', function () {
        $travelContext = [
            'origin' => ['name' => 'A', 'lat' => 10.0, 'lng' => 10.0],
            'destination' => ['name' => 'B', 'lat' => 10.01, 'lng' => 10.01],
            'checkpoints' => [],
            'waypoints' => [],
            'status' => 'approximate',
            'needs_clarification' => false,
            'geometry' => [
                ['lat' => 10.0, 'lng' => 10.0],
                ['lat' => 10.01, 'lng' => 10.01],
            ],
            'segments' => [],
        ];

        $res = $this->call(
            'GET',
            '/api/v1/travel-context/intelligence',
            ['travel_context' => json_encode($travelContext), 'layers' => 'reports']
        );

        $res->assertOk()->assertJsonPath('success', true);
        expect($res->json('data.intelligence.summary.reports_count'))->toBe(0);
        expect($res->json('meta.empty'))->toBeTrue();
    });
});

describe('Partner ad context whitelist', function () {
    it('includes route context in validated contexts rule', function () {
        $path = base_path('app/Http/Controllers/Partner/AdController.php');
        $src = file_get_contents($path);
        expect($src)->toContain('in:home,explore,nearby,place_detail,report,hotels,restaurants,attractions,cafes,activities,route');
    });
});
