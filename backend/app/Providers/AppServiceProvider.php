<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Services\Ai\AiFallbackRouter;
use App\Services\Ai\AiProviderInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiProviderInterface::class, function () {
            return AiFallbackRouter::textChain();
        });
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasPermission') && $user->hasPermission($ability)) {
                return true;
            }
            return null;
        });

        Blade::if('admin', function () {
            return auth()->check() && auth()->user()->isAdmin();
        });

        Blade::if('moderator', function () {
            return auth()->check() && auth()->user()->isModerator();
        });

        Blade::if('adminOrModerator', function () {
            return auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isModerator());
        });

        $this->registerRateLimiters();
    }

    /**
     * Redis-backed per-user/per-IP rate limits for write endpoints.
     * (CACHE_STORE=redis, so the throttle middleware counters live in Memurai.)
     */
    private function registerRateLimiters(): void
    {
        // Reviews: burst guard only (daily cap is enforced in PlaceController
        // with a 24h Redis counter because updateOrCreate allows one review
        // per place — the real spam vector is many places in a row).
        RateLimiter::for('reviews', function (Request $request) {
            return Limit::perMinute(5)->by('reviews:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Place submission (user-added places)
        RateLimiter::for('places-store', function (Request $request) {
            return Limit::perHour(5)->by('places-store:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Place corrections
        RateLimiter::for('corrections', function (Request $request) {
            return Limit::perHour(10)->by('corrections:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Report submission
        RateLimiter::for('reports-store', function (Request $request) {
            return Limit::perHour(10)->by('reports-store:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Report comments
        RateLimiter::for('comments', function (Request $request) {
            return Limit::perMinute(10)->by('comments:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Report update/delete
        RateLimiter::for('reports-mutate', function (Request $request) {
            return Limit::perMinute(30)->by('reports-mutate:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Alerts (broadcast to everyone — spam = everyone disturbed)
        RateLimiter::for('alerts', function (Request $request) {
            return Limit::perHour(5)->by('alerts:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // AI assistant chat (public + costs AI credits per message)
        RateLimiter::for('assistant-chat', function (Request $request) {
            $userKey = $request->user()?->id ? 'user:' . $request->user()->id : 'guest:' . $request->ip();
            return Limit::perMinute(10)->by('assistant-chat:' . $userKey);
        });

        // Social login brute force
        RateLimiter::for('social-login', function (Request $request) {
            return Limit::perMinute(10)->by('social-login:' . $request->ip());
        });

        // Verification email resend (email bombing guard)
        RateLimiter::for('resend-verification', function (Request $request) {
            return Limit::perHour(3)->by('resend-verification:' . ($request->user()?->id ?: $request->ip()));
        });

        // Offer claiming
        RateLimiter::for('offer-claim', function (Request $request) {
            return Limit::perMinute(10)->by('offer-claim:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // Bookings + payment calls
        RateLimiter::for('bookings', function (Request $request) {
            return Limit::perMinute(10)->by('bookings:' . ($request->user()?->id ?: 'guest:' . $request->ip()));
        });

        // OSM status check (public write — same shape as ad tracking)
        RateLimiter::for('osm-status', function (Request $request) {
            return Limit::perMinute(60)->by('osm-status:' . $request->ip());
        });

        // Directions proxy -> OSRM upstream. Public GET with a 15-min Redis
        // cache per from/to pair, but rapid distinct calls still hit OSRM.
        RateLimiter::for('directions', function (Request $request) {
            return Limit::perMinute(30)->by('directions:' . $request->ip());
        });

        // Email OTP verification — brute-force guard (5 attempts/min; the
        // controller also locks the OTP after 5 wrong tries).
        RateLimiter::for('verify-email', function (Request $request) {
            return Limit::perMinute(5)->by('verify-email:' . ($request->user()?->id ?: $request->ip()));
        });

        // Admin panel login (web) — brute force guard
        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by('admin-login:' . $request->ip());
        });

        // Partner portal login (web) — brute force guard
        RateLimiter::for('partner-login', function (Request $request) {
            return Limit::perMinute(5)->by('partner-login:' . $request->ip());
        });

        // Partner registration (web) — caps business submission spam
        RateLimiter::for('partner-register', function (Request $request) {
            return Limit::perHour(5)->by('partner-register:' . $request->ip());
        });

        // Phone/Email change OTP — 3 requests per hour per user
        RateLimiter::for('phone-change', function (Request $request) {
            return Limit::perHour(3)->by('phone-change:' . $request->user()?->id);
        });

        RateLimiter::for('email-change', function (Request $request) {
            return Limit::perHour(3)->by('email-change:' . $request->user()?->id);
        });

        // SOS activation — max 5 per hour per user
        RateLimiter::for('sos', function (Request $request) {
            return Limit::perHour(5)->by('sos:' . $request->user()?->id);
        });
    }
}
