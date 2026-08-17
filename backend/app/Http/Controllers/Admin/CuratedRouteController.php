<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CuratedRoute;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CuratedRouteController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) abort(403, 'Unauthorized');

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $routes = CuratedRoute::orderBy('created_at', 'desc')->paginate(15);
        return view('admin.curated_routes', compact('routes'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);
        $data = $this->validateData($request);
        $route = CuratedRoute::create($data);
        $this->moderatorService->log(Auth::user(), 'route.created', 'curated_route', $route->id, 'Created route: ' . $route->title);
        return redirect()->route('admin.routes')->with('success', 'Route created.');
    }

    public function update(Request $request, CuratedRoute $route)
    {
        $this->requireAdmin($request);
        $route->update($this->validateData($request));
        $this->moderatorService->log(Auth::user(), 'route.updated', 'curated_route', $route->id, 'Updated route: ' . $route->title);
        return redirect()->route('admin.routes')->with('success', 'Route updated.');
    }

    public function destroy(CuratedRoute $route)
    {
        $this->requireAdmin(request());
        $this->moderatorService->log(Auth::user(), 'route.deleted', 'curated_route', $route->id, 'Deleted route: ' . $route->title);
        $route->delete();
        return redirect()->route('admin.routes')->with('success', 'Route deleted.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:curated_routes,slug,' . $request->route('route')?->id,
            'route_type' => 'required|in:' . implode(',', CuratedRoute::TYPES),
            'difficulty' => 'nullable|in:' . implode(',', CuratedRoute::DIFFICULTIES),
            'description' => 'nullable|string|max:5000',
            'image' => 'nullable|url|max:500',
            'duration_days' => 'required|integer|min:1|max:365',
            'best_season' => 'nullable|string|max:100',
            'max_altitude_m' => 'nullable|integer|min:0|max:12000',
            'total_distance_km' => 'nullable|numeric|min:0|max:5000',
            'elevation_gain_m' => 'nullable|integer|min:0|max:30000',
            'starting_point' => 'nullable|string|max:255',
            'ending_point' => 'nullable|string|max:255',
            'waypoints' => 'nullable|string',
            'track' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']) . '-' . Str::lower(Str::random(4));
        $data['waypoints'] = array_filter(array_map('intval', explode(',', (string) ($data['waypoints'] ?? ''))));
        $data['track'] = $this->parseTrack($data['track'] ?? '');
        if (empty($data['track'])) {
            unset($data['track']);
        }
        return $data;
    }

    /**
     * Accepts one "lat,lng" or "lat,lng,Name" per line (also plain JSON).
     */
    private function parseTrack(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded, fn($p) => is_array($p) && isset($p['lat']) && isset($p['lng'])));
        }

        $points = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) >= 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $points[] = [
                    'lat' => (float) $parts[0],
                    'lng' => (float) $parts[1],
                    'name' => $parts[2] ?? null,
                ];
            }
        }
        return $points;
    }
}
