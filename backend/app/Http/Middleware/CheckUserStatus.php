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

        // Auto-reactivate expired suspensions so users are never locked out longer than intended
        if ($user->status === 'suspended' && $user->suspended_until !== null && $user->suspended_until->lte(now())) {
            $user->update(['status' => 'active', 'suspended_until' => null]);
            return $next($request);
        }

        if (in_array($user->status, ['banned', 'suspended', 'deleted'])) {
            // Banned/deleted = permanent, tokens are revoked. Suspended users keep
            // their session so the app can still show the reason + expiry to them.
            if (in_array($user->status, ['banned', 'deleted'])) {
                $user->tokens()->delete();
            }

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

            // The latest system-generated account notice for this user, so the app
            // can show them their ban/suspension notice (the ONLY thing they may see).
            $accountAlert = \App\Models\Alert::where('target_user_id', $user->id)
                ->where('sender_type', 'system')
                ->orderByDesc('created_at')
                ->first();

            return response()->json([
                'success' => false,
                'message' => match ($reason) {
                    'banned' => 'Your account has been permanently banned due to violation of our community guidelines.',
                    'suspended' => 'Your account has been temporarily suspended until ' . optional($user->suspended_until)->format('M j, Y g:i A') . ' due to repeated violations of our community guidelines.',
                    default => 'This account has been deleted.',
                },
                'reason' => $reason,
                'suspended_until' => optional($user->suspended_until)->toDateTimeString(),
                'code' => match ($reason) {
                    'banned' => 'ACCOUNT_BANNED',
                    'suspended' => 'ACCOUNT_SUSPENDED',
                    default => 'ACCOUNT_DELETED',
                },
                'requires_logout' => $reason !== 'suspended',
                'account_alert' => $accountAlert ? [
                    'id' => $accountAlert->id,
                    'title' => $accountAlert->title,
                    'description' => $accountAlert->description,
                    'severity' => $accountAlert->severity,
                    'sender_type' => $accountAlert->sender_type,
                    'link_type' => $accountAlert->link_type,
                    'link_value' => $accountAlert->link_value,
                    'created_at' => $accountAlert->created_at,
                ] : null,
            ], 403);
        }

        return $next($request);
    }
}
