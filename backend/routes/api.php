<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\AlertController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Api\BookingPaymentController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\AchievementController as ApiAchievementController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\SubscriptionController as ApiSubscriptionController;
use App\Http\Controllers\AdController;
use App\Http\Controllers\Api\ConsumerController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\AroundMeController;
use App\Http\Controllers\MapLayersController;
use App\Http\Controllers\SosController;
use App\Http\Controllers\EmergencyContactController;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:3,60');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,60');
    Route::post('/auth/social-login', [AuthController::class, 'socialLogin'])->middleware('throttle:social-login');
    // Token rotation - public: validates the refresh token itself (not the access token)
    Route::post('/auth/refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:10,1');

    // Public routes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/nearby', [AlertController::class, 'nearby']);
    Route::get('/places/categories', [PlaceController::class, 'categories']);
    Route::get('/places/nearby', [PlaceController::class, 'nearby']);
    Route::get('/places/bbox', [PlaceController::class, 'bboxQuery']);
    Route::get('/places/all', [PlaceController::class, 'all']);
    Route::get('/places/nearby-combined', [PlaceController::class, 'nearbyCombined']);
    Route::get('/places/featured', [PlaceController::class, 'featured']);
    // Directions proxy (in-app route drawing — no Google Maps)
    Route::get('/routing/directions', [PlaceController::class, 'directions'])->middleware('throttle:directions');
    // Specific sub-paths MUST be registered before /places/{id} (id regex is '.*' and would swallow them)
    Route::get('/places/{id}/reviews', [PlaceController::class, 'reviews'])->where('id', '.*');
    Route::get('/places/{id}/translations', [PlaceController::class, 'translations']);
    Route::get('/places/{id}', [PlaceController::class, 'show'])->where('id', '.*');
    Route::post('/places/osm-status', [PlaceController::class, 'osmStatus'])->middleware('throttle:osm-status');
    
    Route::get('/profile/field-options', [ProfileController::class, 'fieldOptions']);
    Route::get('/profile/field-definitions', [ProfileController::class, 'fieldDefinitions']);

    // âœ… Reports - public read
    Route::get('/reports/categories', [ReportController::class, 'categories']);
    Route::get('/reports/form-config', [ReportController::class, 'formConfig']);
    Route::get('/reports', [ReportController::class, 'index']);
    // /reports/my MUST be BEFORE /reports/{id} to avoid "my" being matched as {id}
    Route::get('/reports/my', [ReportController::class, 'myReports']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);

    // âœ… Public user profile
    Route::get('/users/{id}/profile', [UserProfileController::class, 'show']);

    // âœ… Leaderboard - public read
    Route::get('/leaderboard/top', [LeaderboardController::class, 'topThree']);
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);

    // âœ… Weather grid - public read
    Route::get('/weather/grid', [WeatherController::class, 'grid']);

    // âœ… Subscription plans - public read
    Route::get('/subscription/plans', [ApiSubscriptionController::class, 'plans']);

    // âœ… Active ads - public read
    Route::get('/ads/active', [AdController::class, 'active']);
    Route::get('/ads/report/{reportId}', [AdController::class, 'forReport'])->whereNumber('reportId');

    // Ad tracking - public (guest impressions/clicks counted, deduped by IP)
    Route::post('/ads/track-impression', [AdController::class, 'trackImpression'])->middleware('throttle:60,1');
    Route::post('/ads/track-click', [AdController::class, 'trackClick'])->middleware('throttle:60,1');

    // âœ… Partners - public read
    Route::get('/partners', [ConsumerController::class, 'partners']);
    Route::get('/partners/{id}', [ConsumerController::class, 'partnerDetail']);

    Route::get('/road-conditions', [AlertController::class, 'roadConditions']);

    // Reward offers - public read
    Route::get('/offers', [\App\Http\Controllers\Api\OfferController::class, 'index']);
    Route::get('/offers/{id}', [\App\Http\Controllers\Api\OfferController::class, 'show'])->whereNumber('id');

    // Curated routes (trekking + itineraries) - public read
    Route::get('/routes', [\App\Http\Controllers\Api\RouteController::class, 'index']);
    Route::get('/routes/{id}', [\App\Http\Controllers\Api\RouteController::class, 'show'])->whereNumber('id');

    // Legal documents - public read
    Route::get('/legal', [\App\Http\Controllers\Api\LegalDocumentController::class, 'index']);
    Route::get('/legal/{type}', [\App\Http\Controllers\Api\LegalDocumentController::class, 'show']);

    // UI translation dictionary (English -> Nepali) - public read
    Route::get('/translations', [\App\Http\Controllers\Api\TranslationController::class, 'dictionary']);

    // Around Me - location intelligence feed (public)
    Route::get('/around-me', [AroundMeController::class, 'index']);

    // Map layers - category-filtered map data (public)
    Route::get('/map/layers', [MapLayersController::class, 'index']);

    // SOS Emergency - nearby is public (no login required)
    Route::get('/sos/nearby', [SosController::class, 'nearby']);

    Route::middleware(['auth:sanctum', 'status'])->group(function () {
        // AI assistant - login required, 5 chats/day per user (Redis)
        Route::get('/assistant/quota', [ReportController::class, 'assistantQuota']);
        Route::post('/assistant/chat', [ReportController::class, 'assistantChat'])->middleware('throttle:assistant-chat');

        Route::get('/offers/my', [\App\Http\Controllers\Api\OfferController::class, 'my']);
        Route::get('/offers/available', [\App\Http\Controllers\Api\OfferController::class, 'available']);
        Route::post('/offers/{id}/claim', [\App\Http\Controllers\Api\OfferController::class, 'claim'])->middleware('throttle:offer-claim');

        Route::get('/users/me', [AuthController::class, 'me']);
        Route::put('/users/me', [AuthController::class, 'update']);
        Route::delete('/users/me', [AuthController::class, 'destroy']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Phone/Email change with OTP
        Route::post('/auth/request-phone-change', [AuthController::class, 'requestPhoneChange'])->middleware('throttle:phone-change');
        Route::post('/auth/verify-phone-change', [AuthController::class, 'verifyPhoneChange']);
        Route::post('/auth/request-email-change', [AuthController::class, 'requestEmailChange'])->middleware('throttle:email-change');
        Route::post('/auth/verify-email-change', [AuthController::class, 'verifyEmailChange']);
        Route::get('/auth/change-limits', [AuthController::class, 'checkChangeLimits']);

        Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail'])->middleware('throttle:verify-email');
        Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification'])->middleware('throttle:resend-verification');
        Route::post('/auth/complete-profile', [AuthController::class, 'completeProfile']);
        Route::get('/auth/check-profile-status', [AuthController::class, 'checkProfileStatus']);

        // Phone verification
        Route::post('/auth/send-phone-otp', [AuthController::class, 'sendPhoneOtp'])->middleware('throttle:phone-otp');
        Route::post('/auth/verify-phone', [AuthController::class, 'verifyPhone'])->middleware('throttle:phone-otp');

        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'index']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::post('/avatar', [ProfileController::class, 'updateAvatar']);
            Route::get('/stats', [ProfileController::class, 'stats']);
            Route::get('/badges', [ProfileController::class, 'badges']);
            Route::get('/activity', [ProfileController::class, 'activity']);
            Route::get('/settings', [ProfileController::class, 'getSettings']);
            Route::put('/settings', [ProfileController::class, 'updateSettings']);
            Route::get('/sections', [ProfileController::class, 'profileSections']);
        });

        Route::get('/achievements', [ApiAchievementController::class, 'index']);
        Route::get('/xp-history', [ApiAchievementController::class, 'xpHistory']);

        // âœ… Places - auth required for write operations
        Route::post('/places', [PlaceController::class, 'store'])->middleware('throttle:places-store');
        Route::post('/places/{id}/reviews', [PlaceController::class, 'addReview'])->where('id', '.*')->middleware('throttle:reviews');
        Route::post('/places/corrections', [PlaceController::class, 'storeCorrection'])->middleware('throttle:corrections');
        Route::get('/places/corrections/mine', [PlaceController::class, 'myCorrections']);
        Route::put('/places/{id}/status', [PlaceController::class, 'updateStatus'])->where('id', '.*');

        // âœ… Reports - auth required for write operations
        Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:reports-store');
        Route::put('/reports/{id}', [ReportController::class, 'update'])->middleware('throttle:reports-mutate');
        Route::delete('/reports/{id}', [ReportController::class, 'destroy'])->middleware('throttle:reports-mutate');

        // âœ… Report Reactions (max 10/min to prevent spam)
        Route::post('/reports/{id}/reactions', [ReportController::class, 'toggleReaction'])->middleware('throttle:10,1');
        Route::delete('/reports/{id}/reactions', [ReportController::class, 'removeReaction'])->middleware('throttle:10,1');

        // âœ… Report Comments
        Route::post('/reports/{id}/comments', [ReportController::class, 'addComment'])->middleware('throttle:comments');
        Route::delete('/reports/{id}/comments/{commentId}', [ReportController::class, 'deleteComment'])->middleware('throttle:comments');

        // Community confirmation ("I can confirm")
        Route::post('/reports/{id}/confirm', [ReportController::class, 'confirm'])->middleware('throttle:10,1');
        Route::get('/reports/{id}/confirmers', [ReportController::class, 'confirmers']);

        Route::middleware('profile.completed')->group(function () {
            Route::post('/alerts', [AlertController::class, 'store'])->middleware('throttle:alerts');
        });

        // Push notification tokens
        Route::post('/push-tokens', [PushTokenController::class, 'store']);
        Route::put('/push-tokens/location', [PushTokenController::class, 'updateLocation'])->middleware('throttle:30,1');
        Route::put('/push-tokens/unsubscribe', [PushTokenController::class, 'unsubscribe']);

        // Subscription
        Route::get('/subscription/my', [ApiSubscriptionController::class, 'my']);
        Route::get('/subscription/features', [ApiSubscriptionController::class, 'features']);

        // User bookings
        Route::post('/bookings', [ConsumerController::class, 'createBooking'])->middleware('throttle:bookings');
        Route::get('/bookings/my', [ConsumerController::class, 'myBookings']);
        Route::post('/bookings/{booking}/cancel', [ConsumerController::class, 'cancelBooking'])->middleware('throttle:bookings');
        Route::delete('/bookings/{booking}/coupon', [ConsumerController::class, 'removeCoupon'])->middleware('throttle:bookings');

        // Booking payments
        Route::post('/bookings/{booking}/payment/initiate', [BookingPaymentController::class, 'initiate'])->middleware('throttle:bookings');
        Route::post('/bookings/{booking}/payment/verify', [BookingPaymentController::class, 'verify'])->middleware('throttle:bookings');

        // Oripori Coins - User wallet & withdrawal
        Route::get('/wallet', [WithdrawalController::class, 'wallet']);
        Route::get('/wallet/transactions', [WithdrawalController::class, 'transactions']);
        Route::post('/wallet/withdraw', [WithdrawalController::class, 'requestWithdrawal'])->middleware('throttle:withdrawal');
        Route::post('/wallet/withdraw/{id}/cancel', [WithdrawalController::class, 'cancel']);

        // Partner Payments - User pays partner via QR
        Route::get('/partner-payments/partners', [\App\Http\Controllers\Api\PartnerPaymentApiController::class, 'partners']);
        Route::post('/partner-payments/initiate', [\App\Http\Controllers\Api\PartnerPaymentApiController::class, 'initiate']);
        Route::get('/partner-payments/my', [\App\Http\Controllers\Api\PartnerPaymentApiController::class, 'myPayments']);
        Route::get('/partner-payments/{payment}', [\App\Http\Controllers\Api\PartnerPaymentApiController::class, 'show']);

        // SOS Emergency
        Route::post('/sos', [SosController::class, 'activate'])->middleware('throttle:sos');
        Route::get('/sos/active', [SosController::class, 'myActive']);
        Route::patch('/sos/{id}/location', [SosController::class, 'updateLocation'])->whereNumber('id');
        Route::get('/sos/for-me', [SosController::class, 'forMe']);
        Route::post('/sos/{id}/resolve', [SosController::class, 'resolve'])->whereNumber('id');
        Route::post('/sos/{id}/cancel', [SosController::class, 'cancel'])->whereNumber('id');
        Route::get('/sos/{id}', [SosController::class, 'show'])->whereNumber('id');
        Route::post('/sos/{id}/report-false', [SosController::class, 'reportFalse'])->whereNumber('id');

        // Emergency Contacts
        Route::get('/emergency-contacts', [EmergencyContactController::class, 'index']);
        Route::post('/emergency-contacts', [EmergencyContactController::class, 'store']);
        Route::get('/emergency-contacts/check-phone/{phone}', [EmergencyContactController::class, 'checkPhone']);
        Route::patch('/emergency-contacts/{id}', [EmergencyContactController::class, 'update'])->whereNumber('id');
        Route::delete('/emergency-contacts/{id}', [EmergencyContactController::class, 'destroy'])->whereNumber('id');

    });

    // Gateway callbacks (hit by eSewa / Khalti redirects — no auth)
    Route::get('/payments/esewa/callback', [BookingPaymentController::class, 'esewaCallback'])->name('api.payments.esewa.callback');
    Route::get('/payments/khalti/callback', [BookingPaymentController::class, 'khaltiCallback'])->name('api.payments.khalti.callback');

});
