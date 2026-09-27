<?php

namespace App\Services\TravelContext;

use App\Helpers\GeoHelper;

/**
 * Pure geometry math for a travel corridor (polyline).
 *
 * No I/O, no routing — only measurement helpers used by the
 * route-relevance layer. Point-to-segment distance uses a local
 * equirectangular projection (adequate at Nepal scale).
 */
class CorridorGeometry
{
    /** Meters per degree of latitude (mean). */
    private const M_PER_DEG_LAT = 110540.0;

    /**
     * Bounding box covering all points, expanded by bufferKm.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}|null
     */
    public static function bbox(array $points, float $bufferKm): ?array
    {
        $points = array_values(array_filter($points, fn ($p) => isset($p['lat'], $p['lng'])));
        if ($points === []) {
            return null;
        }

        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        $latPad = $bufferKm / 111.0;
        $midLat = ($minLat + $maxLat) / 2;
        $lngDiv = 111.0 * max(cos(deg2rad($midLat)), 0.01);
        $lngPad = $bufferKm / $lngDiv;

        return [
            'min_lat' => $minLat - $latPad,
            'max_lat' => $maxLat + $latPad,
            'min_lng' => $minLng - $lngPad,
            'max_lng' => $maxLng + $lngPad,
        ];
    }

    /**
     * Measure a point against the corridor.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array{distance_m: float, progress: float, segment_index: int}|null
     *         progress = position along the polyline in [0, 1]
     *         (0 = origin end, 1 = destination end)
     */
    public static function measure(array $points, float $lat, float $lng): ?array
    {
        $points = self::cleanPoints($points);
        $count = count($points);
        if ($count === 0) {
            return null;
        }
        if ($count === 1) {
            return [
                'distance_m' => GeoHelper::haversineMeters($lat, $lng, $points[0]['lat'], $points[0]['lng']),
                'progress' => 0.0,
                'segment_index' => 0,
            ];
        }

        $refLat = $points[0]['lat'];
        $mPerDegLng = 111320.0 * max(cos(deg2rad($refLat)), 0.01);

        $best = null;
        $cumulative = 0.0;
        $totalStraight = 0.0;
        $segLengths = [];

        for ($i = 0; $i < $count - 1; $i++) {
            $a = $points[$i];
            $b = $points[$i + 1];
            $ax = $a['lng'] * $mPerDegLng;
            $ay = $a['lat'] * self::M_PER_DEG_LAT;
            $bx = $b['lng'] * $mPerDegLng;
            $by = $b['lat'] * self::M_PER_DEG_LAT;
            $px = $lng * $mPerDegLng;
            $py = $lat * self::M_PER_DEG_LAT;

            $dx = $bx - $ax;
            $dy = $by - $ay;
            $lenSq = $dx * $dx + $dy * $dy;
            if ($lenSq < 1e-12) {
                $t = 0.0;
                $cx = $ax;
                $cy = $ay;
                $segLen = 0.0;
            } else {
                $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / $lenSq;
                $t = max(0.0, min(1.0, $t));
                $cx = $ax + $t * $dx;
                $cy = $ay + $t * $dy;
                $segLen = sqrt($lenSq);
            }

            $ex = $px - $cx;
            $ey = $py - $cy;
            $dist = sqrt($ex * $ex + $ey * $ey);

            if ($best === null || $dist < $best['distance_m']) {
                $best = [
                    'distance_m' => $dist,
                    'segment_index' => $i,
                    'along_segment' => $t * $segLen,
                ];
            }

            $segLengths[$i] = $segLen;
            $totalStraight += $segLen;
        }

        if ($best === null) {
            return null;
        }

        for ($i = 0; $i < $best['segment_index']; $i++) {
            $cumulative += $segLengths[$i] ?? 0.0;
        }
        $along = $cumulative + $best['along_segment'];
        $progress = $totalStraight > 0 ? max(0.0, min(1.0, $along / $totalStraight)) : 0.0;

        return [
            'distance_m' => $best['distance_m'],
            'progress' => $progress,
            'segment_index' => $best['segment_index'],
        ];
    }

    /**
     * Journey progress of the user's current position relative to the corridor.
     * Returns null when the position is too far from the route to trust.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     */
    public static function journeyProgress(array $points, float $lat, float $lng, float $maxSnapKm = 25.0): ?float
    {
        $m = self::measure($points, $lat, $lng);
        if ($m === null || $m['distance_m'] > $maxSnapKm * 1000) {
            return null;
        }
        return $m['progress'];
    }

    /**
     * Thin a dense polyline so scoring stays cheap on long corridors.
     * Always keeps first/last points.
     *
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function simplify(array $points, int $maxPoints = 400): array
    {
        $points = self::cleanPoints($points);
        $count = count($points);
        if ($count <= $maxPoints || $maxPoints < 3) {
            return $points;
        }

        $step = ($count - 1) / ($maxPoints - 1);
        $out = [];
        for ($i = 0; $i < $maxPoints; $i++) {
            $idx = (int) round($i * $step);
            $idx = max(0, min($count - 1, $idx));
            $out[] = $points[$idx];
        }
        $out[0] = $points[0];
        $out[count($out) - 1] = $points[$count - 1];

        // De-dupe consecutive identical points while preserving order.
        $deduped = [];
        foreach ($out as $p) {
            $last = $deduped === [] ? null : $deduped[count($deduped) - 1];
            if ($last === null || abs($last['lat'] - $p['lat']) > 1e-7 || abs($last['lng'] - $p['lng']) > 1e-7) {
                $deduped[] = $p;
            }
        }
        if (count($deduped) < 2 && $count >= 2) {
            return [$points[0], $points[$count - 1]];
        }
        return $deduped;
    }

    /**
     * @param  array<int, array{lat: float, lng: float}>  $points
     * @return array<int, array{lat: float, lng: float}>
     */
    private static function cleanPoints(array $points): array
    {
        $clean = [];
        foreach ($points as $p) {
            if (isset($p['lat'], $p['lng']) && is_numeric($p['lat']) && is_numeric($p['lng'])) {
                $clean[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
            }
        }
        return $clean;
    }
}
