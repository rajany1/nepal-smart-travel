<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PushToken;

class PushTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_id' => 'required|string|max:255',
            'device_type' => 'nullable|string|max:50',
        ]);

        // Check if this token belongs to another user
        $existing = PushToken::where('player_id', $validated['player_id'])->first();
        if ($existing && $existing->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'This push token is already registered to another user.',
            ], 409);
        }

        $token = PushToken::updateOrCreate(
            ['player_id' => $validated['player_id']],
            [
                'user_id' => $request->user()->id,
                'device_type' => $validated['device_type'] ?? null,
                'subscribed' => true,
            ]
        );

        return response()->json(['success' => true, 'data' => $token]);
    }

    public function unsubscribe(Request $request)
    {
        $validated = $request->validate([
            'player_id' => 'required|string|max:255',
        ]);

        PushToken::where('player_id', $validated['player_id'])
            ->where('user_id', $request->user()->id)
            ->update(['subscribed' => false]);

        return response()->json(['success' => true]);
    }
}
