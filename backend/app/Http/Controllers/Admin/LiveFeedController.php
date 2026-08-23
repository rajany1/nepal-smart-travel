<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Report;
use App\Support\LiveFeed;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LiveFeedController extends Controller
{
    /**
     * Diff the client's fingerprint against the server. Called by the global
     * admin poller every ~10s. Returns only changed ids per table.
     */
    public function changes(Request $request)
    {
        $since = $request->input('since')
            ? (array) json_decode((string) $request->input('since'), true)
            : [];
        $delsSeen = $request->input('dels_seen')
            ? (array) json_decode((string) $request->input('dels_seen'), true)
            : [];

        return response()->json([
            'success' => true,
            'fp' => LiveFeed::fingerprint(),
            'changes' => LiveFeed::changes($since, $delsSeen),
        ]);
    }

    /**
     * Reports + alerts for the live map — small payload, refetched by the map
     * whenever either table changes.
     */
    public function mapLayers()
    {
        $reports = Report::whereIn('status', ['approved', 'pending'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'report',
                'name' => $r->title,
                'description' => Str::limit($r->description, 100),
                'latitude' => (float) $r->latitude,
                'longitude' => (float) $r->longitude,
                'category' => $r->category?->name ?? 'Report',
                'color' => $r->status === 'approved' ? '#f97316' : '#ef4444',
                'status' => $r->status,
                'url' => route('admin.reports.view', $r->id),
            ]);

        $alerts = Alert::where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'type' => 'alert',
                'name' => $a->title,
                'description' => Str::limit($a->description, 100),
                'latitude' => (float) $a->latitude,
                'longitude' => (float) $a->longitude,
                'category' => $a->severity ?? 'info',
                'color' => match ($a->severity) {
                    'critical' => '#dc2626',
                    'warning' => '#f59e0b',
                    'info' => '#3b82f6',
                    default => '#6b7280',
                },
                'status' => $a->severity ?? 'info',
                'url' => '#',
            ]);

        return response()->json([
            'success' => true,
            'reports' => $reports->values(),
            'alerts' => $alerts->values(),
        ]);
    }
}