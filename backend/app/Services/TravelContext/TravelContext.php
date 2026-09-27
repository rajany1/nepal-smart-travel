<?php

namespace App\Services\TravelContext;

/**
 * Immutable travel context value object.
 *
 * Represents where the user is going (origin → checkpoints → destination),
 * the resolved corridor geometry, optional route alternatives when the
 * path is ambiguous, and optional journey progress from the user's position.
 *
 * Deliberately UI-agnostic: Flutter and future consumers receive the
 * array payload from toPayload().
 */
class TravelContext
{
    public const STATUS_ROUTED = 'routed';
    public const STATUS_APPROXIMATE = 'approximate';
    public const STATUS_MIXED = 'mixed';
    public const STATUS_UNROUTED = 'unrouted';

    /**
     * @param array{name: string, lat: float, lng: float, resolved_via?: string} $origin
     * @param array{name: string, lat: float, lng: float, resolved_via?: string} $destination
     * @param array<int, array{name: string, lat: float, lng: float, resolved_via?: string}> $checkpoints
     * @param array<int, array{
     *     from_name: string, to_name: string, points: array<int, array{lat: float, lng: float}>,
     *     status: string, source: string, distance_m: float, duration_s: float
     * }> $segments
     * @param array<int, array{name: string, lat: float, lng: float, kind: string}> $waypoints
     * @param array<int, array{lat: float, lng: float}> $geometry simplified corridor polyline
     * @param array<int, array{label: string, distance_m: float, duration_s: float, points: array<int, array{lat: float, lng: float}>}> $routeOptions
     * @param array{progress: float, distance_to_route_m: float, distance_along_m: float}|null $journey
     */
    public function __construct(
        public readonly array $origin,
        public readonly array $destination,
        public readonly array $checkpoints,
        public readonly array $segments,
        public readonly array $waypoints,
        public readonly array $geometry,
        public readonly string $status,
        public readonly bool $needsClarification,
        public readonly array $routeOptions,
        public readonly ?float $totalDistanceM,
        public readonly ?float $totalDurationS,
        public readonly ?array $journey = null,
        public readonly ?string $unresolved = null,
    ) {
    }

    public function hasCorridor(): bool
    {
        return count($this->geometry) >= 2;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(bool $includeGeometry = true): array
    {
        $payload = [
            'origin' => $this->origin,
            'destination' => $this->destination,
            'checkpoints' => $this->checkpoints,
            'waypoints' => $this->waypoints,
            'status' => $this->status,
            'is_approximate' => $this->status !== self::STATUS_ROUTED,
            'needs_clarification' => $this->needsClarification,
            'route_options' => $this->routeOptions,
            'total_distance_km' => $this->totalDistanceM !== null ? round($this->totalDistanceM / 1000, 1) : null,
            'total_duration_min' => $this->totalDurationS !== null ? (int) round($this->totalDurationS / 60) : null,
            'segment_statuses' => array_map(
                fn ($s) => [
                    'from' => $s['from_name'],
                    'to' => $s['to_name'],
                    'status' => $s['status'],
                    'source' => $s['source'],
                    'distance_km' => round($s['distance_m'] / 1000, 2),
                ],
                $this->segments
            ),
            'journey' => $this->journey,
            'unresolved' => $this->unresolved,
        ];

        if ($includeGeometry) {
            $payload['segments'] = array_map(fn ($s) => [
                'from_name' => $s['from_name'],
                'to_name' => $s['to_name'],
                'status' => $s['status'],
                'source' => $s['source'],
                'distance_m' => $s['distance_m'],
                'duration_s' => $s['duration_s'],
                'points' => $s['points'],
            ], $this->segments);
            $payload['geometry'] = $this->geometry;
        }

        return $payload;
    }

    /**
     * Rebuild a context from a client payload (for the intelligence endpoint)
     * without re-running routing when segments/geometry are supplied.
     */
    public static function fromPayload(array $payload): ?self
    {
        $origin = $payload['origin'] ?? null;
        $destination = $payload['destination'] ?? null;
        if (!is_array($origin) || !is_array($destination)) {
            return null;
        }
        if (!isset($origin['lat'], $origin['lng'], $destination['lat'], $destination['lng'])) {
            return null;
        }

        $geometry = [];
        foreach (($payload['geometry'] ?? []) as $p) {
            if (isset($p['lat'], $p['lng'])) {
                $geometry[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
            }
        }

        $segments = [];
        foreach (($payload['segments'] ?? []) as $s) {
            $points = [];
            foreach (($s['points'] ?? []) as $p) {
                if (isset($p['lat'], $p['lng'])) {
                    $points[] = ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']];
                }
            }
            $segments[] = [
                'from_name' => $s['from_name'] ?? '',
                'to_name' => $s['to_name'] ?? '',
                'points' => $points,
                'status' => $s['status'] ?? 'approximate',
                'source' => $s['source'] ?? 'client',
                'distance_m' => (float) ($s['distance_m'] ?? 0),
                'duration_s' => (float) ($s['duration_s'] ?? 0),
            ];
        }

        if ($geometry === [] && $segments !== []) {
            foreach ($segments as $s) {
                foreach ($s['points'] as $p) {
                    $geometry[] = $p;
                }
            }
            $geometry = CorridorGeometry::simplify($geometry);
        }

        $waypoints = [];
        foreach (($payload['waypoints'] ?? []) as $w) {
            if (isset($w['lat'], $w['lng'])) {
                $waypoints[] = [
                    'name' => (string) ($w['name'] ?? ''),
                    'lat' => (float) $w['lat'],
                    'lng' => (float) $w['lng'],
                    'kind' => (string) ($w['kind'] ?? 'point'),
                ];
            }
        }

        $checkpoints = [];
        foreach (($payload['checkpoints'] ?? []) as $c) {
            if (is_array($c) && isset($c['lat'], $c['lng'])) {
                $checkpoints[] = [
                    'name' => (string) ($c['name'] ?? ''),
                    'lat' => (float) $c['lat'],
                    'lng' => (float) $c['lng'],
                    'resolved_via' => $c['resolved_via'] ?? 'payload',
                ];
            }
        }

        return new self(
            origin: [
                'name' => (string) ($origin['name'] ?? 'Origin'),
                'lat' => (float) $origin['lat'],
                'lng' => (float) $origin['lng'],
                'resolved_via' => $origin['resolved_via'] ?? 'payload',
            ],
            destination: [
                'name' => (string) ($destination['name'] ?? 'Destination'),
                'lat' => (float) $destination['lat'],
                'lng' => (float) $destination['lng'],
                'resolved_via' => $destination['resolved_via'] ?? 'payload',
            ],
            checkpoints: $checkpoints,
            segments: $segments,
            waypoints: $waypoints,
            geometry: $geometry,
            status: (string) ($payload['status'] ?? self::STATUS_APPROXIMATE),
            needsClarification: (bool) ($payload['needs_clarification'] ?? false),
            routeOptions: [],
            totalDistanceM: isset($payload['total_distance_km']) && $payload['total_distance_km'] !== null
                ? ((float) $payload['total_distance_km']) * 1000
                : null,
            totalDurationS: isset($payload['total_duration_min']) && $payload['total_duration_min'] !== null
                ? ((float) $payload['total_duration_min']) * 60
                : null,
            journey: is_array($payload['journey'] ?? null) ? $payload['journey'] : null,
            unresolved: null,
        );
    }
}
