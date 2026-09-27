<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuratedRoute;
use App\Services\Routing\RouteGeometryService;
use Illuminate\Http\Request;

class RouteGeometryController extends Controller
{
    public function __construct(
        private RouteGeometryService $geometryService,
    ) {}

    /**
     * GET /routes/{id}/geometry
     *
     * Returns resolved route geometry with per-segment status, source,
     * and mode metadata.
     *
     * Response:
     * {
     *   "success": true,
     *   "geometry": {
     *     "route_id": 1,
     *     "total_distance_m": 130000,
     *     "geometry_version": "abc12345def67890",
     *     "segments": [
     *       {
     *         "from_name": "Lukla (2,860m)",
     *         "to_name": "Phakding (2,610m)",
     *         "mode": "trekking",
     *         "status": "approximate",
     *         "source": "curated_waypoints",
     *         "points": [{"lat": 27.688, "lng": 86.7313}, ...],
     *         "distance_m": 8500,
     *         "duration_s": 0
     *       },
     *       ...
     *     ]
     *   }
     * }
     */
    public function show($id)
    {
        $route = CuratedRoute::active()->find($id);
        if (!$route) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }

        $geometry = $this->geometryService->resolveRouteGeometry($route);
        if ($geometry === null) {
            return response()->json([
                'success' => true,
                'geometry' => null,
                'message' => 'Route has insufficient track data for geometry resolution',
            ]);
        }

        return response()->json(['success' => true, 'geometry' => $geometry]);
    }

    /**
     * POST /routes/{id}/geometry/invalidate
     *
     * Invalidates cached geometry for a route (e.g. after admin edits).
     */
    public function invalidate($id)
    {
        $route = CuratedRoute::find($id);
        if (!$route) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }

        $this->geometryService->invalidateCache($route);

        return response()->json(['success' => true, 'message' => 'Geometry cache invalidated']);
    }
}
