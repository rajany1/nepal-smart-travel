<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TravelContext\CorridorGeometry;
use App\Services\TravelContext\RouteRelevanceService;
use App\Services\TravelContext\TravelContext;
use App\Services\TravelContext\TravelContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Route-aware / context-aware intelligence endpoints (guest-accessible).
 *
 * POST /v1/travel-context/resolve      → build TravelContext (corridor)
 * GET|POST /v1/travel-context/intelligence → ranked relevant Oripori items
 *   (POST preferred for large geometry payloads)
 *
 * Read-only over existing Reports / Alerts / Places. Ads, coins, wallet,
 * SOS, and moderation are intentionally not touched here — clients that
 * render ads along a route must use the existing /ads/active pipeline.
 */
class TravelContextController extends Controller
{
    public function __construct(
        private readonly TravelContextService $travelContextService,
        private readonly RouteRelevanceService $relevanceService,
    ) {
    }

    public function resolve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin' => 'required|string|max:120',
            'destination' => 'required|string|max:120',
            'checkpoints' => 'nullable|array|max:8',
            'checkpoints.*' => 'string|max:120',
            'current_lat' => 'nullable|numeric|between:-90,90',
            'current_lng' => 'nullable|numeric|between:-180,180',
            'detect_ambiguity' => 'nullable|boolean',
        ]);

        $context = $this->travelContextService->resolve(
            origin: $validated['origin'],
            destination: $validated['destination'],
            checkpoints: array_values($validated['checkpoints'] ?? []),
            userLat: isset($validated['current_lat']) ? (float) $validated['current_lat'] : null,
            userLng: isset($validated['current_lng']) ? (float) $validated['current_lng'] : null,
            detectAmbiguity: $request->boolean('detect_ambiguity', true),
        );

        if ($context->status === TravelContext::STATUS_UNROUTED && $context->unresolved !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resolve one or more locations',
                'error' => $context->unresolved,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'travel_context' => $context->toPayload(includeGeometry: true),
            ],
            'meta' => [
                'needs_clarification' => $context->needsClarification,
                'is_approximate' => $context->status !== TravelContext::STATUS_ROUTED,
                'status' => $context->status,
                'message' => $context->needsClarification
                    ? 'Which route are you taking?'
                    : null,
            ],
        ]);
    }

    public function intelligence(Request $request): JsonResponse
    {
        // Accept travel_context as nested array (POST) or JSON string (GET).
        if (is_string($request->input('travel_context'))) {
            $decoded = json_decode($request->input('travel_context'), true);
            if (is_array($decoded)) {
                $request->merge(['travel_context' => $decoded]);
            } else {
                $request->request->remove('travel_context');
            }
        }

        $validated = $request->validate([
            // Option A: send a resolved context payload (preferred, no re-routing).
            'travel_context' => 'nullable|array',
            // Option B: send names/coords and let the server resolve (cached).
            'origin' => 'required_without:travel_context|string|max:120',
            'destination' => 'required_without:travel_context|string|max:120',
            'checkpoints' => 'nullable|array|max:8',
            'checkpoints.*' => 'string|max:120',
            'current_lat' => 'nullable|numeric|between:-90,90',
            'current_lng' => 'nullable|numeric|between:-180,180',
            'layers' => 'nullable|string', // comma: reports,alerts,places
        ]);

        $layers = $validated['layers'] ?? null;
        $layers = $layers
            ? array_map('trim', explode(',', $layers))
            : ['reports', 'alerts', 'places'];
        $layers = array_values(array_intersect($layers, ['reports', 'alerts', 'places']));

        $userLat = isset($validated['current_lat']) ? (float) $validated['current_lat'] : null;
        $userLng = isset($validated['current_lng']) ? (float) $validated['current_lng'] : null;

        $context = null;
        $payloadOrigin = null;
        $payloadDestination = null;
        $payloadCheckpoints = [];
        if (!empty($validated['travel_context']) && is_array($validated['travel_context'])) {
            $context = TravelContext::fromPayload($validated['travel_context']);
            if ($context !== null) {
                $payloadOrigin = (string) ($context->origin['name'] ?? '');
                $payloadDestination = (string) ($context->destination['name'] ?? '');
                foreach ($context->checkpoints as $cp) {
                    if (($cp['name'] ?? '') !== '') {
                        $payloadCheckpoints[] = (string) $cp['name'];
                    }
                }
                // Journey progress needs live geometry + optional user position.
                if ($userLat !== null && $userLng !== null) {
                    $context = $this->reattachJourney($context, $userLat, $userLng);
                }
                // Payload without a usable corridor → re-resolve server-side.
                if (!$context->hasCorridor()) {
                    $context = null;
                }
            }
        }

        if ($context === null) {
            $origin = (string) ($validated['origin'] ?? $payloadOrigin ?? '');
            $destination = (string) ($validated['destination'] ?? $payloadDestination ?? '');
            $checkpoints = $validated['checkpoints'] ?? $payloadCheckpoints;
            $context = $this->travelContextService->resolve(
                origin: $origin,
                destination: $destination,
                checkpoints: array_values($checkpoints),
                userLat: $userLat,
                userLng: $userLng,
                detectAmbiguity: false,
            );
        }

        if (!$context->hasCorridor()) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resolve a travel corridor',
                'error' => $context->unresolved,
            ], 422);
        }

        $bundle = ['summary' => [], 'reports' => [], 'alerts' => [], 'places' => []];
        if (in_array('reports', $layers, true)) {
            $bundle['reports'] = $this->relevanceService->rankReports($context, $userLat, $userLng);
        }
        if (in_array('alerts', $layers, true)) {
            $bundle['alerts'] = $this->relevanceService->rankAlerts($context);
        }
        if (in_array('places', $layers, true)) {
            $bundle['places'] = $this->relevanceService->rankPlaces($context);
        }

        $max = $this->relevanceService->maxResults();
        $bundle['reports'] = array_slice($bundle['reports'], 0, $max);
        $bundle['alerts'] = array_slice($bundle['alerts'], 0, min(20, $max));
        $bundle['places'] = array_slice($bundle['places'], 0, min(20, $max));

        $bundle['summary'] = [
            'reports_count' => count($bundle['reports']),
            'alerts_count' => count($bundle['alerts']),
            'places_count' => count($bundle['places']),
            'total' => count($bundle['reports']) + count($bundle['alerts']) + count($bundle['places']),
            'corridor_radius_km' => $this->relevanceService->corridorRadiusKm(),
            'journey_progress' => $context->journey['progress'] ?? null,
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'travel_context' => $context->toPayload(includeGeometry: false),
                'intelligence' => $bundle,
            ],
            'meta' => [
                'total' => $bundle['summary']['total'],
                'corridor_radius_km' => $bundle['summary']['corridor_radius_km'],
                'is_approximate' => $context->status !== TravelContext::STATUS_ROUTED,
                'empty' => $bundle['summary']['total'] === 0,
            ],
        ]);
    }

    private function reattachJourney(TravelContext $context, float $userLat, float $userLng): TravelContext
    {
        if (!$context->hasCorridor()) {
            return $context;
        }
        $measure = CorridorGeometry::measure($context->geometry, $userLat, $userLng);
        if ($measure === null) {
            return $context;
        }
        $maxSnapM = (float) \App\Models\GameSetting::getValue('route_max_snap_km', 25) * 1000;
        if ($measure['distance_m'] > $maxSnapM) {
            return $context;
        }

        return new TravelContext(
            origin: $context->origin,
            destination: $context->destination,
            checkpoints: $context->checkpoints,
            segments: $context->segments,
            waypoints: $context->waypoints,
            geometry: $context->geometry,
            status: $context->status,
            needsClarification: $context->needsClarification,
            routeOptions: $context->routeOptions,
            totalDistanceM: $context->totalDistanceM,
            totalDurationS: $context->totalDurationS,
            journey: [
                'progress' => round($measure['progress'], 4),
                'distance_to_route_m' => (float) round($measure['distance_m']),
                'distance_along_m' => round($measure['progress'] * ($context->totalDistanceM ?: 0), 0),
            ],
            unresolved: $context->unresolved,
        );
    }
}
