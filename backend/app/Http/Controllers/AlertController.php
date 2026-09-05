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
use Illuminate\Support\Facades\Log;

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
            // Broadcast alerts are location-independent and always visible.
            $query->where(function ($q) use ($lat, $lng, $latDelta, $lngDelta) {
                $q->where('is_broadcast', true)
                  ->orWhere(function ($geo) use ($lat, $lng, $latDelta, $lngDelta) {
                      $geo->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                          ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
                  });
            });
        }

        $alerts = $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->latest()
        ->limit(100)
        ->get();

        // Merge the authenticated user's personal (targeted) system alerts into
        // the feed — they are relevant to them regardless of location.
        $user = $request->user() ?? \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
        if ($user) {
            $targeted = Alert::where('target_user_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->get();
            $byId = $alerts->keyBy('id');
            foreach ($targeted as $t) {
                if (!$byId->has($t->id)) {
                    $alerts->push($t);
                }
            }
            $alerts = $alerts->sortByDesc('created_at')->take(100)->values();
        }

        $data = $alerts->map(fn($alert) => [
            'id' => $alert->id,
            'uuid' => $alert->uuid,
            'title' => $alert->title,
            'description' => $alert->description,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'sender_type' => $alert->sender_type ?? 'user',
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'affected_district' => $alert->affected_district,
            'link_type' => $alert->link_type,
            'link_value' => $alert->link_value,
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
        ->where(function ($geo) use ($lat, $lng, $latDelta, $lngDelta) {
            $geo->where('is_broadcast', true)
                ->orWhere(function ($near) use ($lat, $lng, $latDelta, $lngDelta) {
                    $near->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                         ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
                });
        })
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
            'sender_type' => $a->sender_type ?? 'user',
            'latitude' => $a->latitude,
            'longitude' => $a->longitude,
            'affected_district' => $a->affected_district,
            'link_type' => $a->link_type,
            'link_value' => $a->link_value,
            'created_at' => $a->created_at,
        ]);

        // BE-23: only approved reports may surface as emergency alerts
        $emergencyReports = Report::where('status', 'approved')
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

        // Review AI agent - real-time safety guard
        $safety = app(\App\Services\ContentSafetyService::class);
        $guardTitle = $safety->guard($user, (string) $validated['title'], 'alert', $alert->id, 'title', 'realtime');
        $guardDesc = $safety->guard($user, (string) $validated['description'], 'alert', $alert->id, 'description', 'realtime');
        if ($guardTitle['action'] === 'censored' || $guardDesc['action'] === 'censored') {
            $alert->update(['title' => $guardTitle['censored'] ?? $guardTitle['text'], 'description' => $guardDesc['censored'] ?? $guardDesc['text']]);
        }
        $safetyPayload = $safety->payload([$guardTitle, $guardDesc]);

        $alertXp = GameSetting::getValue('alert_post_xp', 5);
        app(AchievementService::class)->awardXp(
            $user, $alertXp, 'alert_created',
            "Posted alert: {$alert->title}", $alert
        );

        app(\App\Services\AlertPublisherService::class)->dispatchForAlert($alert);

        return response()->json([
            'success' => true,
            'message' => 'Alert created successfully',
            'data' => $alert,
            'safety' => $safetyPayload,
        ], 201);
    }
}
