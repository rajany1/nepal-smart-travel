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
}
