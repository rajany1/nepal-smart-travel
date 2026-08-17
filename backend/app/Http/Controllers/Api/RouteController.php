<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuratedRoute;
use App\Models\Place;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        $query = CuratedRoute::active()->orderBy('duration_days');

        if ($request->filled('type') && in_array($request->type, CuratedRoute::TYPES)) {
            $query->where('route_type', $request->type);
        }
        if ($request->filled('difficulty') && in_array($request->difficulty, CuratedRoute::DIFFICULTIES)) {
            $query->where('difficulty', $request->difficulty);
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%' . $request->q . '%');
        }

        $routes = $query->limit(min((int) ($request->get('limit') ?: 50), 100))->get();

        return response()->json([
            'success' => true,
            'routes' => $routes->map(fn($r) => $this->publicRoute($r)),
        ]);
    }

    public function show(Request $request, $id)
    {
        $route = CuratedRoute::active()->find($id);
        if (!$route) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }

        $data = $this->publicRoute($route);
        $data['waypoints'] = $route->waypoints ?? [];
        $data['track'] = $route->trackPoints();
        $data['places'] = array_map(
            fn(Place $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'district' => $p->district,
                'latitude' => (float) $p->latitude,
                'longitude' => (float) $p->longitude,
                'average_rating' => (float) $p->average_rating,
                'category' => $p->category?->name,
                'image' => $p->images()->first()?->image_url,
            ],
            $route->waypointPlaces()
        );

        return response()->json(['success' => true, 'route' => $data]);
    }

    private function publicRoute(CuratedRoute $route): array
    {
        return [
            'id' => $route->id,
            'title' => $route->title,
            'slug' => $route->slug,
            'route_type' => $route->route_type,
            'difficulty' => $route->difficulty,
            'difficulty_label' => $route->difficultyLabel(),
            'description' => $route->description,
            'image' => $route->image,
            'duration_days' => $route->duration_days,
            'best_season' => $route->best_season,
            'max_altitude_m' => $route->max_altitude_m,
            'total_distance_km' => $route->total_distance_km !== null ? (float) $route->total_distance_km : null,
            'elevation_gain_m' => $route->elevation_gain_m,
            'starting_point' => $route->starting_point,
            'ending_point' => $route->ending_point,
            'waypoint_count' => count($route->waypoints ?? []),
        ];
    }
}
