<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SosAlert;
use App\Models\EmergencyContact;
use App\Models\PushToken;
use App\Models\User;
use App\Jobs\NotifySosEmergencyContacts;
use Illuminate\Support\Facades\DB;

class SosController extends Controller
{
    public function activate(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_accuracy' => 'nullable|numeric|min:0',
            'emergency_type' => 'nullable|in:medical,accident,flood,other',
            'message' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        // Check if user is restricted due to false SOS
        if ($user->sos_restricted_until && $user->sos_restricted_until->isFuture()) {
            $minutesLeft = now()->diffInMinutes($user->sos_restricted_until);
            return response()->json([
                'success' => false,
                'message' => "SOS temporarily restricted. Try again in {$minutesLeft} minutes.",
            ], 429);
        }

        $existing = SosAlert::where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => $this->formatSos($existing, $user),
                'message' => 'Existing active SOS returned.',
            ]);
        }

        $sos = SosAlert::create([
            'user_id' => $user->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'location_accuracy' => $validated['location_accuracy'] ?? null,
            'status' => 'active',
            'emergency_type' => $validated['emergency_type'] ?? 'other',
            'message' => $validated['message'] ?? null,
            'started_at' => now(),
            'last_location_update_at' => now(),
        ]);

        NotifySosEmergencyContacts::dispatch($sos);

        return response()->json([
            'success' => true,
            'data' => $this->formatSos($sos, $user),
            'message' => 'SOS activated. Emergency contacts notified.',
        ], 201);
    }

    public function myActive(Request $request)
    {
        $sos = SosAlert::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (!$sos) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSos($sos, $request->user()),
        ]);
    }

    public function forMe(Request $request)
    {
        $user = $request->user();

        // IDs of users who listed the current user as an active emergency contact
        $ownerIds = EmergencyContact::where('contact_user_id', $user->id)
            ->where('is_active', true)
            ->pluck('user_id');

        $sosAlerts = SosAlert::with('user:id,name,avatar,phone')
            ->whereIn('user_id', $ownerIds)
            ->whereIn('status', ['active', 'resolved'])
            ->where('started_at', '>=', now()->subHours(24))
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        $data = $sosAlerts->map(function (SosAlert $sos) {
            return [
                'id' => $sos->id,
                'status' => $sos->status,
                'emergency_type' => $sos->emergency_type,
                'message' => $sos->message,
                'latitude' => $sos->latitude,
                'longitude' => $sos->longitude,
                'location_accuracy' => $sos->location_accuracy,
                'started_at' => $sos->started_at,
                'last_location_update_at' => $sos->last_location_update_at,
                'resolved_at' => $sos->resolved_at,
                'duration_seconds' => $sos->started_at ? (int) $sos->started_at->diffInSeconds($sos->resolved_at ?? now()) : 0,
                'user_name' => $sos->user?->name,
                'user_avatar' => $sos->user?->avatar,
                'user_phone' => $sos->user?->phone,
            ];
        })->values();

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
            'radius_km' => 'nullable|numeric|min:0.1|max:50',
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = (float) ($validated['radius_km'] ?? 5);

        $sosAlerts = SosAlert::nearby($lat, $lng, $radiusKm)
            ->get()
            ->map(fn($sos) => $this->formatNearbySos($sos, $lat, $lng));

        return response()->json([
            'success' => true,
            'data' => $sosAlerts,
        ]);
    }

    public function updateLocation(Request $request, string $id)
    {
        $sos = SosAlert::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_accuracy' => 'nullable|numeric|min:0',
        ]);

        $sos->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'location_accuracy' => $validated['location_accuracy'] ?? $sos->location_accuracy,
            'last_location_update_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatSos($sos->fresh(), $request->user()),
        ]);
    }

    public function resolve(Request $request, string $id)
    {
        $sos = SosAlert::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $user = $request->user();
        $duration = $sos->started_at->diffInSeconds(now());

        $sos->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        // False SOS detection: resolved within 30 seconds = likely false
        $isFalseSos = $duration <= 30;
        $strikeMessage = null;

        if ($isFalseSos) {
            $user->increment('sos_false_count');

            // 3 false SOS → restrict for 24 hours
            if ($user->sos_false_count >= 3) {
                $user->update([
                    'sos_restricted_until' => now()->addHours(24),
                    'sos_false_count' => 0,
                ]);
                $strikeMessage = 'SOS restricted for 24 hours due to repeated false alarms.';
            } else {
                $remaining = 3 - $user->sos_false_count;
                $strikeMessage = "False SOS detected. {$remaining} more false SOS will restrict your SOS for 24 hours.";
            }

            \Illuminate\Support\Facades\Log::warning("False SOS by user {$user->id}: resolved in {$duration}s (strike #{$user->sos_false_count})");
        }

        // Notify contacts
        $contacts = EmergencyContact::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        foreach ($contacts as $contact) {
            if ($contact->contact_user_id) {
                \App\Services\PushNotificationService::sendToUser(
                    $contact->contact_user_id,
                    'SOS Resolved',
                    $user->name . ' has marked themselves as safe.',
                    [
                        'type' => 'sos_resolved',
                        'sos_id' => (string) $sos->id,
                        'user_name' => $user->name,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSos($sos->fresh(), $user),
            'message' => $strikeMessage ?? 'SOS resolved.',
        ]);
    }

    public function cancel(Request $request, string $id)
    {
        $sos = SosAlert::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->firstOrFail();

        $sos->update([
            'status' => 'cancelled',
            'resolved_at' => now(),
        ]);

        // Notify contacts that the SOS was cancelled
        $contacts = EmergencyContact::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->get();

        foreach ($contacts as $contact) {
            if ($contact->contact_user_id) {
                \App\Services\PushNotificationService::sendToUser(
                    $contact->contact_user_id,
                    'SOS Cancelled',
                    $request->user()->name . ' cancelled their SOS. No further action needed.',
                    ['type' => 'sos_cancelled', 'sos_id' => (string) $sos->id]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSos($sos->fresh(), $request->user()),
            'message' => 'SOS cancelled.',
        ]);
    }

    public function show(Request $request, string $id)
    {
        $sos = SosAlert::with('user:id,name,avatar,phone')->findOrFail($id);

        $isOwner = $sos->user_id === $request->user()->id;
        $isEmergencyContact = EmergencyContact::where('user_id', $sos->user_id)
            ->where('contact_user_id', $request->user()->id)
            ->where('is_active', true)
            ->exists();

        // Only owner and verified emergency contacts can view SOS details
        if (!$isOwner && !$isEmergencyContact) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $data = $this->formatSos($sos, $request->user());

        if ($isOwner || $isEmergencyContact) {
            $data['user_name'] = $sos->user->name;
            $data['user_avatar'] = $sos->user->avatar;
            $data['user_phone'] = $sos->user->phone;
        }

        if ($isEmergencyContact && !$isOwner) {
            $myLat = $request->user()->pushTokens()->latest()->value('latitude');
            $myLng = $request->user()->pushTokens()->latest()->value('longitude');
            if ($myLat && $myLng) {
                $data['distance_km'] = round($this->haversine(
                    $myLat, $myLng,
                    $sos->latitude, $sos->longitude
                ), 2);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    private function formatSos(SosAlert $sos, User $viewer): array
    {
        $isOwner = $sos->user_id === $viewer->id;
        $duration = $sos->started_at ? (int) $sos->started_at->diffInSeconds($sos->resolved_at ?? now()) : 0;

        $result = [
            'id' => $sos->id,
            'status' => $sos->status,
            'emergency_type' => $sos->emergency_type,
            'message' => $sos->message,
            'latitude' => $sos->latitude,
            'longitude' => $sos->longitude,
            'location_accuracy' => $sos->location_accuracy,
            'started_at' => $sos->started_at,
            'last_location_update_at' => $sos->last_location_update_at,
            'resolved_at' => $sos->resolved_at,
            'duration_seconds' => $duration,
            'created_at' => $sos->created_at,
        ];

        if ($isOwner) {
            $result['contacts_notified'] = EmergencyContact::where('user_id', $viewer->id)
                ->where('is_active', true)
                ->count();
        }

        return $result;
    }

    public function reportFalse(Request $request, string $id)
    {
        $sos = SosAlert::where('id', $id)
            ->where('status', 'active')
            ->firstOrFail();

        if ($sos->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot report your own SOS as false.',
            ], 403);
        }

        $validated = $request->validate([
            'reason' => 'nullable|in:false_alarm,prank,other',
            'description' => 'nullable|string|max:500',
        ]);

        $report = \App\Models\SosReport::updateOrCreate(
            [
                'sos_alert_id' => $sos->id,
                'reporter_id' => $request->user()->id,
            ],
            [
                'reason' => $validated['reason'] ?? 'false_alarm',
                'description' => $validated['description'] ?? null,
            ]
        );

        // If 3+ reports, auto-resolve and penalize
        $reportCount = \App\Models\SosReport::where('sos_alert_id', $sos->id)->count();
        $strikeMessage = null;

        if ($reportCount >= 3 && $sos->status === 'active') {
            $sos->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            $user = $sos->user;
            $user->increment('sos_false_count');

            if ($user->sos_false_count >= 3) {
                $user->update([
                    'sos_restricted_until' => now()->addHours(24),
                    'sos_false_count' => 0,
                ]);
                $strikeMessage = 'This SOS was auto-resolved. User restricted for 24 hours.';
            } else {
                $remaining = 3 - $user->sos_false_count;
                $strikeMessage = "This SOS was auto-resolved. User has {$remaining} false SOS strikes remaining.";
            }

            // Notify owner
            \App\Services\PushNotificationService::sendToUser(
                $sos->user_id,
                'SOS Auto-Resolved',
                'Your SOS was reported as false by multiple users and has been resolved.',
                ['type' => 'sos_auto_resolved', 'sos_id' => (string) $sos->id]
            );

            \Illuminate\Support\Facades\Log::warning("SOS {$sos->id} auto-resolved after {$reportCount} false reports. User {$user->id} strikes: {$user->sos_false_count}");
        }

        return response()->json([
            'success' => true,
            'message' => $strikeMessage ?? 'Report submitted. Thank you for helping keep the community safe.',
            'report_count' => $reportCount,
        ]);
    }

    private function formatNearbySos(SosAlert $sos, float $userLat, float $userLng): array
    {
        $distance = $this->haversine($userLat, $userLng, $sos->latitude, $sos->longitude);
        $duration = (int) $sos->started_at->diffInSeconds(now());

        return [
            'id' => $sos->id,
            'latitude' => $sos->latitude,
            'longitude' => $sos->longitude,
            'emergency_type' => $sos->emergency_type,
            'distance_km' => round($distance, 2),
            'duration_seconds' => $duration,
            'last_location_update_at' => $sos->last_location_update_at,
        ];
    }

    private function haversine($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
