<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileCompleted
{
    /**
     * Handle an incoming request.
     * 
     * This middleware checks if the authenticated user has completed their profile.
     * If profile is not completed, it returns an error response.
     * This protects certain endpoints from being accessed until profile setup is done.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // If no user is authenticated, let auth middleware handle it
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // ✅ Check if user has completed their profile
        if (!$user->profile_completed) {
            return response()->json([
                'success' => false,
                'message' => 'Profile completion required',
                'code' => 'PROFILE_INCOMPLETE',
                'profile_completed' => false,
                'missing_fields' => $this->getMissingFields($user),
            ], 403);  // 403 Forbidden - user is authenticated but action not allowed
        }

        return $next($request);
    }

    /**
     * Get missing profile fields
     */
    private function getMissingFields($user)
    {
        $missing = [];

        if (empty($user->bio)) {
            $missing[] = 'bio';
        }

        if (empty($user->phone)) {
            $missing[] = 'phone';
        }

        return $missing;
    }
}
