<?php

/**
 * BIPAD ordering contract: the incident endpoint must request
 * ordering=-id (createdOn/incidentOn ordering params are verified to
 * return incorrectly ordered records). Alert endpoint keeps its
 * existing ordering=-createdOn.
 *
 * HTTP layer is faked — no real BIPAD calls, no DB writes.
 */

use App\Services\BipadApiService;
use Illuminate\Support\Facades\Http;

describe('BipadApiService request ordering', function () {
    it('requests incidents with ordering=-id', function () {
        Http::fake([
            '*' => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ]),
        ]);

        (new BipadApiService())->fetchIncidents(0, 100);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/incident/')
                && $request['ordering'] === '-id'
                && $request['limit'] == 100
                && $request['offset'] == 0
                && $request['format'] === 'json';
        });
    });

    it('requests alerts with the existing ordering=-createdOn', function () {
        Http::fake([
            '*' => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ]),
        ]);

        (new BipadApiService())->fetchAlerts(0, 100);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/alert/')
                && $request['ordering'] === '-createdOn'
                && $request['limit'] == 100
                && $request['offset'] == 0
                && $request['format'] === 'json';
        });
    });

    it('omits the ordering param for incidents when ordering is disabled', function () {
        Http::fake([
            '*' => Http::response([
                'count' => 0,
                'next' => null,
                'previous' => null,
                'results' => [],
            ]),
        ]);

        (new BipadApiService())->fetchIncidents(0, 100, false);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/incident/')
                && ! str_contains($request->url(), 'ordering=');
        });
    });

    it('pages through fetchRecentIncidents with ordering=-id on every request', function () {
        config(['bipad.sync.page_size' => 2]);

        // Page 1: full page + next link -> loop continues; page 2: short -> stops.
        Http::fakeSequence('*')
            ->push([
                'count' => 3,
                'next' => 'https://bipad.test/incident/?offset=2',
                'previous' => null,
                'results' => [['id' => 95724], ['id' => 95723]],
            ])
            ->push([
                'count' => 3,
                'next' => null,
                'previous' => null,
                'results' => [['id' => 95722]],
            ]);

        $seen = [];
        (new BipadApiService())->fetchRecentIncidents(function ($item) use (&$seen) {
            $seen[] = $item['id'];
        }, 6, 3);

        expect($seen)->toBe([95724, 95723, 95722]);

        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request, $response]) {
            expect(str_contains($request->url(), '/incident/'))->toBeTrue();
            expect($request['ordering'])->toBe('-id');
        }
        $offsets = collect(Http::recorded())
            ->map(fn ($pair) => (int) $pair[0]['offset'])
            ->all();
        expect($offsets)->toBe([0, 2]);
    });
});
