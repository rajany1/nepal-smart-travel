<?php

use App\Services\TravelContext\CorridorGeometry;

describe('CorridorGeometry', function () {
    it('returns null bbox for empty geometry', function () {
        expect(CorridorGeometry::bbox([], 3.0))->toBeNull();
    });

    it('expands bbox by buffer', function () {
        $bbox = CorridorGeometry::bbox([
            ['lat' => 27.7, 'lng' => 85.3],
            ['lat' => 27.8, 'lng' => 85.4],
        ], 3.0);

        expect($bbox)->not->toBeNull();
        expect($bbox['min_lat'])->toBeLessThan(27.7);
        expect($bbox['max_lat'])->toBeGreaterThan(27.8);
        expect($bbox['min_lng'])->toBeLessThan(85.3);
        expect($bbox['max_lng'])->toBeGreaterThan(85.4);
    });

    it('measures distance and progress along a horizontal corridor', function () {
        $line = [
            ['lat' => 27.70, 'lng' => 85.30],
            ['lat' => 27.70, 'lng' => 85.40],
        ];

        // Point on the origin end.
        $atStart = CorridorGeometry::measure($line, 27.70, 85.30);
        expect($atStart['distance_m'])->toBeLessThan(50);
        expect($atStart['progress'])->toEqualWithDelta(0.0, 0.01);

        // Point mid-line.
        $mid = CorridorGeometry::measure($line, 27.70, 85.35);
        expect($mid['distance_m'])->toBeLessThan(50);
        expect($mid['progress'])->toEqualWithDelta(0.5, 0.05);

        // Point at destination end.
        $atEnd = CorridorGeometry::measure($line, 27.70, 85.40);
        expect($atEnd['progress'])->toEqualWithDelta(1.0, 0.01);
    });

    it('measures perpendicular distance off the corridor', function () {
        $line = [
            ['lat' => 27.70, 'lng' => 85.30],
            ['lat' => 27.70, 'lng' => 85.40],
        ];

        // ~1.1 km north of the line (1 degree lat ≈ 111 km).
        $m = CorridorGeometry::measure($line, 27.71, 85.35);
        expect($m['distance_m'])->toBeGreaterThan(900);
        expect($m['distance_m'])->toBeLessThan(1300);
    });

    it('returns null measure for empty geometry', function () {
        expect(CorridorGeometry::measure([], 27.7, 85.3))->toBeNull();
    });

    it('journeyProgress returns null when farther than maxSnapKm', function () {
        $line = [
            ['lat' => 27.70, 'lng' => 85.30],
            ['lat' => 27.70, 'lng' => 85.40],
        ];

        expect(CorridorGeometry::journeyProgress($line, 27.70, 85.35, 25.0))->not->toBeNull();
        expect(CorridorGeometry::journeyProgress($line, 28.70, 85.35, 25.0))->toBeNull();
    });

    it('simplifies dense polylines while keeping endpoints', function () {
        $dense = [];
        for ($i = 0; $i <= 500; $i++) {
            $dense[] = ['lat' => 27.70 + ($i * 0.0001), 'lng' => 85.30];
        }

        $out = CorridorGeometry::simplify($dense, 400);

        expect(count($out))->toBeLessThanOrEqual(400);
        expect(count($out))->toBeGreaterThanOrEqual(2);
        expect($out[0]['lat'])->toEqualWithDelta($dense[0]['lat'], 0.0000001);
        expect($out[count($out) - 1]['lat'])->toEqualWithDelta($dense[count($dense) - 1]['lat'], 0.0000001);
    });

    it('drops invalid points', function () {
        $out = CorridorGeometry::simplify([
            ['lat' => 27.7, 'lng' => 85.3],
            ['lat' => 'bad', 'lng' => 85.4],
            ['lng' => 85.5],
            ['lat' => 27.8, 'lng' => 85.5],
        ]);

        expect(count($out))->toBe(2);
        expect($out[0]['lng'])->toEqualWithDelta(85.3, 0.0001);
        expect($out[1]['lng'])->toEqualWithDelta(85.5, 0.0001);
    });
});
