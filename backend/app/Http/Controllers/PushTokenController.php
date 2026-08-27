<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushToken;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:255',
            'device_type' => 'nullable|string|max:50',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $existing = PushToken::where('fcm_token', $validated['fcm_token'])->first();
        if ($existing && $existing->user_id !== $request->user()->id) {
            $existing->update(['user_id' => $request->user()->id]);
        }

        $token = PushToken::updateOrCreate(
            ['fcm_token' => $validated['fcm_token']],
            [
                'user_id' => $request->user()->id,
                'device_type' => $validated['device_type'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'subscribed' => true,
            ]
        );

        return response()->json(['success' => true, 'data' => $token]);
    }

    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'required|string|max:255',
        ]);

        PushToken::where('fcm_token', $validated['fcm_token'])
            ->where('user_id', $request->user()->id)
            ->update(['subscribed' => false]);

        return response()->json(['success' => true]);
    }

    /**
     * Keep the device's last known location fresh so "nearby" push
     * targeting stays accurate while the user moves. The app calls this
     * periodically (every few minutes) while it is in the foreground.
     */
    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'fcm_token' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $query = PushToken::where('user_id', $request->user()->id)->where('subscribed', true);
        if (!empty($validated['fcm_token'])) {
            $query->where('fcm_token', $validated['fcm_token']);
        }

        $updated = $query->update([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
        ]);

        return response()->json(['success' => true, 'updated' => $updated]);
    }
}
