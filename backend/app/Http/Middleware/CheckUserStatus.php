<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if (in_array($user->status, ['banned', 'suspended'])) {
            $user->tokens()->delete();

            // Web response: logout session and redirect
            if (!$request->expectsJson()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect('/admin/login')
                    ->with('error', $user->status === 'banned'
                        ? 'Your account has been permanently banned.'
                        : 'Your account has been temporarily suspended.');
            }

            // API response: return 403 JSON
            $reason = $user->status;
            return response()->json([
                'success' => false,
                'message' => $reason === 'banned'
                    ? 'Your account has been permanently banned due to violation of our community guidelines.'
                    : 'Your account has been temporarily suspended. Please contact support to regain access.',
                'reason' => $reason,
                'code' => $reason === 'banned' ? 'ACCOUNT_BANNED' : 'ACCOUNT_SUSPENDED',
                'requires_logout' => true,
            ], 403);
        }

        return $next($request);
    }
}
