<?php

/**
 * BIPAD hazard skip-policy: flood + inundation must NOT be skipped — they
 * transform into approved reports (Safety & Hazards) and publish alerts.
 * Weather-condition types (heavy_rainfall, storm, thunderbolt, wind_storm)
 * remain skipped. Earthquake/landslide mappings must stay unchanged.
 *
 * Runs against the local `backend` MySQL DB. Per-test transaction rollback
 * proved ineffective in this environment (framework/PDO quirk), so every row
 * these tests create is tracked and deleted explicitly in afterEach — same
 * convention as AdminReportImageIntegrityTest. No migrations run.
 */

use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\User;
use App\Services\AlertPublisherService;
use App\Services\BipadApiService;
use App\Services\BipadSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$GLOBALS['bipad_hazard_cleanup'] = ['user_id' => null, 'category_id' => null, 'log_ids' => []];

function bipadHazardService(?Mockery\MockInterface $publisher = null): BipadSyncService
{
    // Real BipadApiService is safe here: getHazardMapping() reads config and
    // parseDate() is pure Carbon — neither performs HTTP.
    return new BipadSyncService(new BipadApiService(), $publisher ?? Mockery::mock(AlertPublisherService::class));
}

function bipadHazardIncident(int $hazardId, int $bipadId, array $overrides = []): array
{
    return array_merge([
        'id' => $bipadId,
        'hazard' => $hazardId,
        'title' => "Bipad hazard filter test {$bipadId}",
        'point' => ['coordinates' => [83.98, 28.21]],
        'createdOn' => now()->toIso8601String(),
        'verified' => true,
        'public' => true,
    ], $overrides);
}

function bipadHazardTransform(BipadSyncService $service, array $item): array
{
    $method = new ReflectionMethod($service, 'transformToReport');
    $method->setAccessible(true);

    return $method->invoke($service, $item, 'incident');
}

function bipadHazardProcess(BipadSyncService $service, array $item): string
{
    $method = new ReflectionMethod($service, 'processItem');
    $method->setAccessible(true);

    return $method->invoke($service, $item, 'incident');
}

function bipadHazardSafetyCategory(): ReportCategorie
{
    $existing = ReportCategorie::where('name', 'Safety & Hazards')->first();
    if ($existing) {
        return $existing;
    }

    $category = ReportCategorie::create([
        'name' => 'Safety & Hazards',
        'slug' => 'safety-hazards-'.Str::lower(Str::random(6)),
    ]);
    $GLOBALS['bipad_hazard_cleanup']['category_id'] = $category->id;

    return $category;
}

function bipadHazardSystemUser(): User
{
    $user = User::create([
        'name' => 'BIPAD Hazard Test System',
        'email' => 'bipad_hazard_test_'.Str::lower(Str::random(10)).'@localhost.test',
        'phone' => '98'.mt_rand(10000000, 99999999),
        'password' => Str::random(20),
    ]);
    User::whereKey($user->id)->update(['is_system' => true]);
    $GLOBALS['bipad_hazard_cleanup']['user_id'] = $user->id;

    return $user->fresh();
}

afterEach(function () {
    // Plain autocommit deletes — reliable regardless of the transaction-layer
    // quirk that made begin/rollBack a no-op in this environment.
    DB::table('reports')
        ->where('source', 'bipad')
        ->where('bipad_id', '>=', 990000)
        ->where('bipad_id', '<', 995000)
        ->delete();

    $userId = $GLOBALS['bipad_hazard_cleanup']['user_id'] ?? null;
    if ($userId) {
        DB::table('user_subscriptions')->where('user_id', $userId)->delete();
        DB::table('users')->where('id', $userId)->delete();
        $GLOBALS['bipad_hazard_cleanup']['user_id'] = null;
    }

    $categoryId = $GLOBALS['bipad_hazard_cleanup']['category_id'] ?? null;
    if ($categoryId) {
        DB::table('report_categories')->where('id', $categoryId)->delete();
        $GLOBALS['bipad_hazard_cleanup']['category_id'] = null;
    }

    $logIds = $GLOBALS['bipad_hazard_cleanup']['log_ids'] ?? [];
    if ($logIds) {
        DB::table('bipad_sync_logs')->whereIn('id', $logIds)->delete();
        $GLOBALS['bipad_hazard_cleanup']['log_ids'] = [];
    }
});

describe('config(bipad.skip_hazard_types) policy', function () {
    it('does not skip flood or inundation', function () {
        $skip = config('bipad.skip_hazard_types');

        expect($skip)->not->toContain('flood');
        expect($skip)->not->toContain('inundation');
    });

    it('no longer skips heavy_rainfall but keeps storm, thunderbolt and wind_storm skipped', function () {
        $skip = config('bipad.skip_hazard_types');

        expect($skip)->not->toContain('heavy_rainfall');
        foreach (['storm', 'thunderbolt', 'wind_storm'] as $type) {
            expect($skip)->toContain($type);
        }
    });
});

describe('BipadSyncService::transformToReport hazard gating', function () {
    it('transforms a flood incident (hazard 11) instead of skipping it', function () {
        $data = bipadHazardTransform(
            bipadHazardService(),
            bipadHazardIncident(11, 990011)
        );

        expect($data['report_subcategory'])->toBe('Flood');
        expect($data['status'])->toBe('approved');
        expect($data['priority'])->toBe('critical'); // verified + critical severity
        expect($data['ai_analysis']['bipad_hazard'])->toBe('flood');
        expect($data['bipad_hazard_id'])->toBe(11);
    });

    it('transforms an inundation incident (hazard 28) instead of skipping it', function () {
        $data = bipadHazardTransform(
            bipadHazardService(),
            bipadHazardIncident(28, 990028)
        );

        expect($data['report_subcategory'])->toBe('Inundation');
        expect($data['status'])->toBe('approved');
        expect($data['priority'])->toBe('critical'); // verified + high severity
        expect($data['ai_analysis']['bipad_hazard'])->toBe('inundation');
        expect($data['bipad_hazard_id'])->toBe(28);
    });

    it('maps flood and inundation to the Safety & Hazards category id', function () {
        $category = bipadHazardSafetyCategory();

        foreach ([11 => 'Flood', 28 => 'Inundation'] as $hazardId => $subcategory) {
            $data = bipadHazardTransform(
                bipadHazardService(),
                bipadHazardIncident((int) $hazardId, 990100 + $hazardId)
            );

            expect($data['category_id'])->toBe($category->id);
            expect($data['report_subcategory'])->toBe($subcategory);
        }
    });

    it('transforms a heavy_rainfall incident (hazard 14) instead of skipping it', function () {
        $data = bipadHazardTransform(
            bipadHazardService(),
            bipadHazardIncident(14, 990014)
        );

        expect($data['report_subcategory'])->toBeNull(); // no explicit subcategory mapping
        expect($data['status'])->toBe('approved');
        expect($data['priority'])->toBe('high'); // verified + medium severity
        expect($data['ai_analysis']['bipad_hazard'])->toBe('heavy_rainfall');
        expect($data['bipad_hazard_id'])->toBe(14);
    });

    it('still throws for storm, thunderbolt and wind_storm', function () {
        foreach ([22 => 'storm', 23 => 'thunderbolt', 24 => 'wind_storm'] as $hazardId => $type) {
            expect(fn () => bipadHazardTransform(
                bipadHazardService(),
                bipadHazardIncident($hazardId, 990200 + $hazardId)
            ))->toThrow(\Exception::class, "Skipping hazard type: {$type}");
        }
    });

    it('keeps earthquake (hazard 8) behaviour unchanged', function () {
        $data = bipadHazardTransform(
            bipadHazardService(),
            bipadHazardIncident(8, 990008)
        );

        expect($data['report_subcategory'])->toBe('Earthquake');
        expect($data['status'])->toBe('approved');
        expect($data['priority'])->toBe('critical');
        expect($data['ai_analysis']['bipad_hazard'])->toBe('earthquake');
    });

    it('keeps landslide (hazard 17) behaviour unchanged', function () {
        $data = bipadHazardTransform(
            bipadHazardService(),
            bipadHazardIncident(17, 990017, ['verified' => false])
        );

        expect($data['report_subcategory'])->toBe('Landslide');
        expect($data['status'])->toBe('approved');
        expect($data['priority'])->toBe('high'); // high severity, unverified
        expect($data['ai_analysis']['bipad_hazard'])->toBe('landslide');
    });
});

describe('BipadSyncService::processItem end-to-end', function () {
    it('skips storm without creating a report or publishing', function () {
        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->never();

        $result = bipadHazardProcess(
            bipadHazardService($publisher),
            bipadHazardIncident(22, 990322)
        );

        expect($result)->toBe('skipped');
        expect(Report::where('bipad_id', 990322)->exists())->toBeFalse();
    });

    it('creates an approved flood report with Safety & Hazards category and publishes it', function () {
        $category = bipadHazardSafetyCategory();
        $user = bipadHazardSystemUser();

        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')
            ->once()
            ->with(Mockery::type(Report::class))
            ->andReturnNull();

        $result = bipadHazardProcess(
            bipadHazardService($publisher),
            bipadHazardIncident(11, 990411)
        );

        expect($result)->toBe('created');

        $report = Report::where('bipad_id', 990411)->first();
        expect($report)->not->toBeNull();
        expect($report->source)->toBe('bipad');
        expect($report->status)->toBe('approved');
        expect($report->user_id)->toBe($user->id);
        expect($report->category_id)->toBe($category->id);
        expect($report->report_subcategory)->toBe('Flood');
        expect($report->priority)->toBe('critical');
        expect($report->bipad_hazard_id)->toBe(11);
    });

    it('creates an approved heavy_rainfall report and publishes it', function () {
        bipadHazardSafetyCategory();
        bipadHazardSystemUser();

        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')
            ->once()
            ->with(Mockery::type(Report::class))
            ->andReturnNull();

        $result = bipadHazardProcess(
            bipadHazardService($publisher),
            bipadHazardIncident(14, 990414)
        );

        expect($result)->toBe('created');

        $report = Report::where('bipad_id', 990414)->first();
        expect($report)->not->toBeNull();
        expect($report->source)->toBe('bipad');
        expect($report->status)->toBe('approved');
        expect($report->report_subcategory)->toBeNull();
        expect($report->priority)->toBe('high');
        expect($report->bipad_hazard_id)->toBe(14);
    });
});

describe('time-window and timestamp eligibility (max_hours_old = 6)', function () {
    it('uses a 6-hour cutoff by default', function () {
        expect((int) config('bipad.max_hours_old'))->toBe(6);
    });

    it('rejects a flood older than 6 hours without report or publish', function () {
        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->never();

        $item = bipadHazardIncident(11, 990511, [
            'createdOn' => now()->subHours(7)->toIso8601String(),
        ]);

        expect(fn () => bipadHazardTransform(bipadHazardService($publisher), $item))
            ->toThrow(\Exception::class, 'Skipping old data');

        $result = bipadHazardProcess(bipadHazardService($publisher), $item);
        expect($result)->toBe('skipped');
        expect(Report::where('bipad_id', 990511)->exists())->toBeFalse();
    });

    it('rejects an inundation older than 6 hours without report or publish', function () {
        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->never();

        $item = bipadHazardIncident(28, 990528, [
            'createdOn' => now()->subHours(7)->toIso8601String(),
        ]);

        expect(fn () => bipadHazardTransform(bipadHazardService($publisher), $item))
            ->toThrow(\Exception::class, 'Skipping old data');

        $result = bipadHazardProcess(bipadHazardService($publisher), $item);
        expect($result)->toBe('skipped');
        expect(Report::where('bipad_id', 990528)->exists())->toBeFalse();
    });

    it('accepts flood within the window via the incidentOn fallback timestamp', function () {
        $data = bipadHazardTransform(
            bipadHazardService(),
            bipadHazardIncident(11, 990515, [
                'createdOn' => null,
                'incidentOn' => now()->subHours(2)->toIso8601String(),
            ])
        );

        expect($data['report_subcategory'])->toBe('Flood');
        expect($data['status'])->toBe('approved');
    });

    it('rejects a record with no timestamp at all', function () {
        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->never();

        $item = bipadHazardIncident(11, 990516, [
            'createdOn' => null,
            'incidentOn' => null,
            'startedOn' => null,
        ]);

        expect(fn () => bipadHazardTransform(bipadHazardService($publisher), $item))
            ->toThrow(\Exception::class, 'without a valid timestamp');

        $result = bipadHazardProcess(bipadHazardService($publisher), $item);
        expect($result)->toBe('skipped');
        expect(Report::where('bipad_id', 990516)->exists())->toBeFalse();
    });

    it('rejects a record with an invalid timestamp string', function () {
        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->never();

        $item = bipadHazardIncident(11, 990517, [
            'createdOn' => 'definitely-not-a-date',
        ]);

        expect(fn () => bipadHazardTransform(bipadHazardService($publisher), $item))
            ->toThrow(\Exception::class, 'without a valid timestamp');

        $result = bipadHazardProcess(bipadHazardService($publisher), $item);
        expect($result)->toBe('skipped');
        expect(Report::where('bipad_id', 990517)->exists())->toBeFalse();
    });
});

describe('dedup by BIPAD source and id', function () {
    it('creates only one report and publishes only once for a re-fetched incident', function () {
        bipadHazardSafetyCategory();
        bipadHazardSystemUser();

        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->once()->andReturnNull();

        $item = bipadHazardIncident(11, 990611);
        $first = bipadHazardProcess(bipadHazardService($publisher), $item);
        $second = bipadHazardProcess(bipadHazardService($publisher), $item);

        expect($first)->toBe('created');
        expect($second)->not->toBe('created'); // 'skipped' or 'updated', never a second create
        expect(Report::where('source', 'bipad')->where('bipad_id', 990611)->count())->toBe(1);
    });
});

describe('BipadSyncLog status and counters', function () {
    it('records success with accurate counters on normal completion', function () {
        bipadHazardSafetyCategory();
        bipadHazardSystemUser();

        $publisher = Mockery::mock(AlertPublisherService::class);
        $publisher->shouldReceive('publishFromReport')->once()->andReturnNull();
        $service = bipadHazardService($publisher);

        $log = $service->sync('incident', function ($callback) {
            $callback(bipadHazardIncident(11, 990711)); // flood -> created
            $callback(bipadHazardIncident(22, 990722)); // storm -> skipped
        });

        $GLOBALS['bipad_hazard_cleanup']['log_ids'][] = $log->id;

        expect($log->status)->toBe('success');
        expect($log->records_fetched)->toBe(2);
        expect($log->records_created)->toBe(1);
        expect($log->records_skipped)->toBe(1);
        expect($log->records_updated)->toBe(0);
        expect($log->completed_at)->not->toBeNull();
        expect($log->error_message)->toBeNull();
    });

    it('records failed with the error message when the fetcher throws', function () {
        $service = bipadHazardService();

        $log = $service->sync('incident', function ($callback) {
            throw new \RuntimeException('BIPAD API unreachable');
        });

        $GLOBALS['bipad_hazard_cleanup']['log_ids'][] = $log->id;

        expect($log->status)->toBe('failed');
        expect($log->error_message)->toContain('BIPAD API unreachable');
        expect($log->records_fetched)->toBe(0);
        expect($log->completed_at)->not->toBeNull();
    });
});
