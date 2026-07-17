<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alert;
use App\Models\Report;
use App\Models\PushToken;
use App\Models\GameSetting;
use App\Services\AchievementService;
use App\Services\PushNotificationService;
use App\Services\TranslationService;

class AlertController extends Controller
{
    public function index(Request $request)
    {
        $query = Alert::query();

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('type')) {
            $query->where('alert_type', $request->type);
        }

        if ($request->filled('district')) {
            $query->where('affected_district', $request->district);
        }

        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radiusKm = (float) ($request->input('radius_km', 20));
            $latDelta = $radiusKm / 111.0;
            $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));
            $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                  ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        $alerts = $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->latest()
        ->limit(100)
        ->get();

        $data = $alerts->map(fn($alert) => [
            'id' => $alert->id,
            'uuid' => $alert->uuid,
            'title' => $alert->title,
            'description' => $alert->description,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'affected_district' => $alert->affected_district,
            'created_at' => $alert->created_at,
        ])->toArray();

        $data = TranslationService::attachToItems($data, 'alert');

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:500',
        ]);
        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = (float) ($validated['radius_km'] ?? 20);

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        $alerts = Alert::where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
        ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
        ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
        ->latest()
        ->limit(30)
        ->get()
        ->map(fn($a) => [
            'id' => $a->id,
            'uuid' => $a->uuid,
            'title' => $a->title,
            'description' => $a->description,
            'source' => 'alert',
            'alert_type' => $a->alert_type,
            'severity' => $a->severity,
            'latitude' => $a->latitude,
            'longitude' => $a->longitude,
            'affected_district' => $a->affected_district,
            'created_at' => $a->created_at,
        ]);

        $emergencyReports = Report::whereIn('status', ['approved', 'pending'])
            ->whereIn('priority', ['high', 'critical'])
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->with('user')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'title' => $r->title,
                'description' => $r->description,
                'source' => 'report',
                'alert_type' => 'emergency',
                'severity' => $r->priority === 'critical' ? 'critical' : 'high',
                'latitude' => $r->latitude,
                'longitude' => $r->longitude,
                'affected_district' => $r->district,
                'created_at' => $r->created_at,
            ]);

        $items = $alerts->concat($emergencyReports)->sortByDesc('created_at')->values()->toArray();

        $items = TranslationService::attachToItems($items, 'alert');
        $items = TranslationService::attachToItems($items, 'report');

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function roadConditions(Request $request)
    {
        $query = \App\Models\RoadCondition::query();

        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radiusKm = (float) ($request->input('radius_km', 20));
            $latDelta = $radiusKm / 111.0;
            $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));
            $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                  ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->limit(50)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'alert_type' => 'required|string|in:earthquake,flood,landslide,weather,strike,emergency',
            'severity' => 'required|string|in:info,medium,high,critical',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'affected_district' => 'nullable|string',
            'expires_at' => 'nullable|date',
        ]);

        $user = $request->user();
        $validated['created_by'] = $user->id;
        $alert = Alert::create($validated);

        $alertXp = GameSetting::getValue('alert_post_xp', 5);
        app(AchievementService::class)->awardXp(
            $user, $alertXp, 'alert_created',
            "Posted alert: {$alert->title}", $alert
        );

        if ($alert->latitude && $alert->longitude) {
            PushNotificationService::notifyNearbyUsers(
                title: $alert->title,
                message: $alert->description,
                latitude: (float) $alert->latitude,
                longitude: (float) $alert->longitude,
                radiusKm: 20,
                data: ['type' => 'alert', 'id' => $alert->id],
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Alert created successfully',
            'data' => $alert
        ], 201);
    }
}
