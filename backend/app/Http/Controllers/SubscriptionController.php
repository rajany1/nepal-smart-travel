<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->orderBy('price')->get();
        return response()->json(['data' => $plans]);
    }

    public function my(): JsonResponse
    {
        $user = Auth::user();
        $sub = UserSubscription::where('user_id', $user->id)
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->where('ends_at', '>', now())
            ->first();

        return response()->json(['data' => $sub]);
    }

    public function features(): JsonResponse
    {
        $user = Auth::user();
        $sub = UserSubscription::where('user_id', $user->id)
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->where('ends_at', '>', now())
            ->first();

        $features = $sub?->plan?->features;
        if (!is_array($features)) $features = is_string($features) ? (json_decode($features, true) ?? []) : [];
        return response()->json([
            'is_premium' => $sub !== null,
            'features' => $features,
        ]);
    }
}
