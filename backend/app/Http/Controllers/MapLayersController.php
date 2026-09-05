<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Report;
use App\Models\Alert;
use App\Models\Place;

class MapLayersController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
            'layers' => 'nullable|string', // comma-separated: emergency,alerts,reports,places
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = (float) ($validated['radius_km'] ?? 10);
        $requestedLayers = $validated['layers']
            ? explode(',', $validated['layers'])
            : ['emergency', 'alerts', 'reports', 'places'];

        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));
        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        $data = [];
        $hasIsActive = Schema::hasColumn('reports', 'is_active');

        if (in_array('emergency', $requestedLayers)) {
            $q = Report::where('status', 'approved')
                ->whereIn('priority', ['high', 'critical'])
                ->whereNotNull('latitude')
                ->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng])
                ->with('category');
            if ($hasIsActive) $q->where('is_active', true);
            $data['emergency'] = $q->latest()->limit(50)->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'latitude' => $r->latitude,
                    'longitude' => $r->longitude,
                    'priority' => $r->priority,
                    'confirmed_by_count' => $hasIsActive ? ($r->confirmed_by_count ?? 0) : 0,
                ]);
        }

        if (in_array('alerts', $requestedLayers)) {
            $data['alerts'] = Alert::where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->where(function ($q) use ($minLat, $maxLat, $minLng, $maxLng) {
                    $q->where('is_broadcast', true)
                      ->orWhere(fn($geo) => $geo
                        ->whereBetween('latitude', [$minLat, $maxLat])
                        ->whereBetween('longitude', [$minLng, $maxLng]));
                })
                ->latest()->limit(50)->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'latitude' => $a->latitude,
                    'longitude' => $a->longitude,
                    'severity' => $a->severity,
                    'alert_type' => $a->alert_type,
                ]);
        }

        if (in_array('reports', $requestedLayers)) {
            $q = Report::where('status', 'approved')
                ->whereNotIn('priority', ['high', 'critical'])
                ->whereNotNull('latitude')
                ->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng])
                ->with('category');
            if ($hasIsActive) $q->where('is_active', true);
            $data['reports'] = $q->latest()->limit(50)->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'latitude' => $r->latitude,
                    'longitude' => $r->longitude,
                    'category' => $r->category->name ?? 'General',
                    'time_state' => $r->time_state,
                ]);
        }

        if (in_array('places', $requestedLayers)) {
            $data['places'] = Place::where('is_active', true)
                ->whereNotNull('latitude')
                ->whereBetween('latitude', [$minLat, $maxLat])
                ->whereBetween('longitude', [$minLng, $maxLng])
                ->with('category')
                ->latest()->limit(50)->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'latitude' => $p->latitude,
                    'longitude' => $p->longitude,
                    'category' => $p->category->name ?? 'Other',
                    'is_verified' => $p->is_verified,
                ]);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
