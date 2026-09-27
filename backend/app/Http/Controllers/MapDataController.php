<?php

namespace App\Http\Controllers;

use App\Services\MapCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API endpoints that serve Nepal boundary GeoJSON from Redis cache.
 * Used by both the Flutter mobile app and optionally the admin web map.
 *
 * Boundary data: cached 3 days (configurable via GameSetting).
 * Places data: cached 10 minutes via PlacesCache.
 */
class MapDataController extends Controller
{
    /** GET /api/v1/map/boundary — Nepal outer boundary (ADM0). */
    public function boundary(): JsonResponse
    {
        $json = MapCacheService::getBoundary();
        if ($json === null) {
            return response()->json(['error' => 'Boundary data not available'], 404);
        }

        return response()->json(json_decode($json, true))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** GET /api/v1/map/provinces — 7 provinces (ADM1). */
    public function provinces(): JsonResponse
    {
        $json = MapCacheService::getProvinces();
        if ($json === null) {
            return response()->json(['error' => 'Province data not available'], 404);
        }

        return response()->json(json_decode($json, true))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** GET /api/v1/map/districts — 77 districts (ADM2). */
    public function districts(): JsonResponse
    {
        $json = MapCacheService::getDistricts();
        if ($json === null) {
            return response()->json(['error' => 'District data not available'], 404);
        }

        return response()->json(json_decode($json, true))
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** GET /api/v1/map/all — all boundary data in a single response (fewer HTTP calls). */
    public function all(): JsonResponse
    {
        $boundary = MapCacheService::getBoundary();
        $provinces = MapCacheService::getProvinces();
        $districts = MapCacheService::getDistricts();

        if ($boundary === null && $provinces === null && $districts === null) {
            return response()->json(['error' => 'Map data not available'], 404);
        }

        return response()->json([
            'boundary' => $boundary ? json_decode($boundary, true) : null,
            'provinces' => $provinces ? json_decode($provinces, true) : null,
            'districts' => $districts ? json_decode($districts, true) : null,
            'cached_at' => now()->toIso8601String(),
            'ttl_days' => (int) (\App\Models\GameSetting::getValue('map_boundary_ttl_days', 3) ?? 3),
        ])->header('Cache-Control', 'public, max-age=86400');
    }

    /** POST /api/v1/map/refresh — admin-only: force cache refresh. */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        MapCacheService::refreshAll();
        return response()->json([
            'success' => true,
            'message' => 'Map boundary cache refreshed',
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }
}
