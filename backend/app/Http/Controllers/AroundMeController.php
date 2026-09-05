<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Report;
use App\Models\Alert;
use App\Models\Place;

class AroundMeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = (float) ($validated['radius_km'] ?? 10);

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        $hasIsActive = Schema::hasColumn('reports', 'is_active');

        // ── Emergency: high/critical approved reports ──
        $emergencyQuery = Report::where('status', 'approved')
            ->whereIn('priority', ['high', 'critical'])
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->with(['category', 'media', 'user']);
        if ($hasIsActive) {
            $emergencyQuery->where('is_active', true);
        }
        $emergency = $emergencyQuery->latest()->limit(20)->get()
            ->map(fn($r) => $this->formatReport($r, $lat, $lng));

        // ── Alerts: active alerts in area ──
        $alerts = Alert::where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
                $q->where('is_broadcast', true)
                  ->orWhere(function ($geo) use ($minLat, $maxLat, $minLng, $maxLng) {
                      $geo->whereBetween('latitude', [$minLat, $maxLat])
                          ->whereBetween('longitude', [$minLng, $maxLng]);
                  });
            })
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn($a) => $this->formatAlert($a, $lat, $lng));

        // ── Reports: all approved reports in area (non-emergency) ──
        $reportsQuery = Report::where('status', 'approved')
            ->whereNotIn('priority', ['high', 'critical'])
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->with(['category', 'media', 'user']);
        if ($hasIsActive) {
            $reportsQuery->where('is_active', true);
        }
        $reports = $reportsQuery->latest()->limit(30)->get()
            ->map(fn($r) => $this->formatReport($r, $lat, $lng));

        // ── Places: nearby useful places ──
        $places = Place::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereBetween('latitude', [$minLat, $maxLat])
            ->whereBetween('longitude', [$minLng, $maxLng])
            ->with('category')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn($p) => $this->formatPlace($p, $lat, $lng));

        // ── Compute time states ──
        $now = now();
        $classifyTime = fn($createdAt) => match(true) {
            $createdAt->diffInMinutes($now) < 60  => 'live',
            $createdAt->isToday()                  => 'today',
            $createdAt->diffInDays($now) <= 7      => 'recent',
            default                                 => 'expired',
        };

        // ── Build grouped response ──
        $allItems = $emergency->concat($alerts)->concat($reports)->concat($places);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'emergency_count' => $emergency->count(),
                    'alerts_count' => $alerts->count(),
                    'reports_count' => $reports->count(),
                    'places_count' => $places->count(),
                    'total' => $allItems->count(),
                    'radius_km' => $radiusKm,
                ],
                'emergency' => $emergency->values(),
                'alerts' => $alerts->values(),
                'reports' => $reports->values(),
                'places' => $places->values(),
            ],
        ]);
    }

    private function formatReport($report, $userLat, $userLng): array
    {
        $distance = $this->haversine($userLat, $userLng, $report->latitude, $report->longitude);
        $timeAgo = $this->timeAgo($report->created_at);

        return [
            'id' => $report->id,
            'uuid' => $report->uuid,
            'type' => 'report',
            'title' => $report->title,
            'description' => $report->description,
            'category' => $report->category->name ?? 'General',
            'category_icon' => $report->category->icon ?? 'info',
            'priority' => $report->priority,
            'latitude' => $report->latitude,
            'longitude' => $report->longitude,
            'district' => $report->district,
            'distance_km' => round($distance, 2),
            'time_ago' => $timeAgo,
            'time_state' => $this->timeState($report->created_at),
            'helpful_count' => $report->helpful_count,
            'unhelpful_count' => $report->unhelpful_count,
            'comments_count' => $report->comments_count,
            'image_url' => $report->media->first()->media_url ?? null,
            'reporter_name' => $report->user->name ?? 'Anonymous',
            'reporter_avatar' => $report->user->avatar ?? null,
            'created_at' => $report->created_at,
        ];
    }

    private function formatAlert($alert, $userLat, $userLng): array
    {
        $distance = ($alert->latitude && $alert->longitude)
            ? $this->haversine($userLat, $userLng, $alert->latitude, $alert->longitude)
            : null;

        return [
            'id' => $alert->id,
            'uuid' => $alert->uuid,
            'type' => 'alert',
            'title' => $alert->title,
            'description' => $alert->description,
            'alert_type' => $alert->alert_type,
            'severity' => $alert->severity,
            'latitude' => $alert->latitude,
            'longitude' => $alert->longitude,
            'affected_district' => $alert->affected_district,
            'distance_km' => $distance ? round($distance, 2) : null,
            'time_ago' => $this->timeAgo($alert->created_at),
            'time_state' => $this->timeState($alert->created_at),
            'sender_type' => $alert->sender_type ?? 'system',
            'created_at' => $alert->created_at,
        ];
    }

    private function formatPlace($place, $userLat, $userLng): array
    {
        $distance = $this->haversine($userLat, $userLng, $place->latitude, $place->longitude);

        return [
            'id' => $place->id,
            'uuid' => $place->uuid,
            'type' => 'place',
            'name' => $place->name,
            'description' => $place->description,
            'category' => $place->category->name ?? 'Other',
            'category_icon' => $place->category->icon ?? 'place',
            'address' => $place->address,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'district' => $place->district,
            'distance_km' => round($distance, 2),
            'average_rating' => (float) $place->average_rating,
            'total_reviews' => $place->total_reviews,
            'is_verified' => $place->is_verified,
            'phone' => $place->phone,
            'image_url' => $place->images->first()->image_url ?? null,
        ];
    }

    private function haversine($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function timeAgo($datetime): string
    {
        $diff = now()->diff($datetime);
        if ($diff->i < 1) return 'just now';
        if ($diff->i < 60) return $diff->i . 'm ago';
        if ($diff->h < 24) return $diff->h . 'h ago';
        if ($diff->d < 7) return $diff->d . 'd ago';
        return $datetime->diffForHumans();
    }

    private function timeState($createdAt): string
    {
        $minutesAgo = now()->diffInMinutes($createdAt);
        if ($minutesAgo < 60) return 'live';
        if ($createdAt->isToday()) return 'today';
        if ($createdAt->diffInDays(now()) <= 7) return 'recent';
        return 'expired';
    }
}
