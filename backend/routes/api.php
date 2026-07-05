<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;

use App\Http\Controllers\AlertController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PushTokenController;
use App\Http\Controllers\AchievementController as ApiAchievementController;
use App\Http\Controllers\WeatherController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SubscriptionController as ApiSubscriptionController;
use App\Http\Controllers\AdController;
use App\Http\Controllers\Api\ConsumerController;

Route::prefix('v1')->group(function () {

    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:3,60');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,60');
    Route::post('/auth/social-login', [AuthController::class, 'socialLogin']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refreshToken']);
    });

    // Public routes
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/nearby', [AlertController::class, 'nearby']);
    Route::get('/places/categories', [PlaceController::class, 'categories']);
    Route::get('/places/nearby', [PlaceController::class, 'nearby']);
    Route::get('/places/bbox', [PlaceController::class, 'bboxQuery']);
    Route::get('/places/nearby-combined', [PlaceController::class, 'nearbyCombined']);
    Route::get('/places/featured', [PlaceController::class, 'featured']);
    Route::get('/places/{id}', [PlaceController::class, 'show']);
    Route::get('/places/{id}/reviews', [PlaceController::class, 'reviews']);
    
    Route::get('/profile/field-options', [ProfileController::class, 'fieldOptions']);
    Route::get('/profile/field-definitions', [ProfileController::class, 'fieldDefinitions']);

    // ✅ Reports - public read
    Route::get('/reports/categories', [ReportController::class, 'categories']);
    Route::get('/reports/form-config', [ReportController::class, 'formConfig']);
    Route::get('/reports', [ReportController::class, 'index']);
    // /reports/my MUST be BEFORE /reports/{id} to avoid "my" being matched as {id}
    Route::get('/reports/my', [ReportController::class, 'myReports']);
    Route::get('/reports/{id}', [ReportController::class, 'show']);

    // ✅ Public user profile
    Route::get('/users/{id}/profile', [UserProfileController::class, 'show']);

    // ✅ Leaderboard - public read
    Route::get('/leaderboard/top', [LeaderboardController::class, 'topThree']);
    Route::get('/leaderboard', [LeaderboardController::class, 'index']);

    // ✅ Weather grid - public read
    Route::get('/weather/grid', [WeatherController::class, 'grid']);

    // ✅ Subscription plans - public read
    Route::get('/subscription/plans', [ApiSubscriptionController::class, 'plans']);

    // ✅ Active ads - public read
    Route::get('/ads/active', [AdController::class, 'active']);

    // ✅ Partners - public read
    Route::get('/partners', [ConsumerController::class, 'partners']);
    Route::get('/partners/{id}', [ConsumerController::class, 'partnerDetail']);

    // ✅ Sponsors - public read
    Route::get('/sponsors', [ConsumerController::class, 'sponsors']);
    Route::get('/road-conditions', [AlertController::class, 'roadConditions']);
    Route::post('/assistant/chat', [ReportController::class, 'assistantChat']);

    Route::middleware(['auth:sanctum', 'status'])->group(function () {
        Route::get('/users/me', [AuthController::class, 'me']);
        Route::put('/users/me', [AuthController::class, 'update']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
        Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
        Route::post('/auth/complete-profile', [AuthController::class, 'completeProfile']);
        Route::get('/auth/check-profile-status', [AuthController::class, 'checkProfileStatus']);

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

        // ✅ Places - auth required for write operations
        Route::post('/places/{id}/reviews', [PlaceController::class, 'addReview']);

        // ✅ Reports - auth required for write operations
        Route::post('/reports', [ReportController::class, 'store']);
        Route::put('/reports/{id}', [ReportController::class, 'update']);
        Route::delete('/reports/{id}', [ReportController::class, 'destroy']);

        // ✅ Report Reactions (max 10/min to prevent spam)
        Route::post('/reports/{id}/reactions', [ReportController::class, 'toggleReaction'])->middleware('throttle:10,1');
        Route::delete('/reports/{id}/reactions', [ReportController::class, 'removeReaction'])->middleware('throttle:10,1');

        // ✅ Report Comments
        Route::post('/reports/{id}/comments', [ReportController::class, 'addComment']);
        Route::delete('/reports/{id}/comments/{commentId}', [ReportController::class, 'deleteComment']);

        Route::middleware('profile.completed')->group(function () {
            Route::post('/alerts', [AlertController::class, 'store']);
        });

        // Push notification tokens
        Route::post('/push-tokens', [PushTokenController::class, 'store']);
        Route::put('/push-tokens/unsubscribe', [PushTokenController::class, 'unsubscribe']);

        // Store
        Route::get('/store/items', [StoreController::class, 'items']);
        Route::post('/store/items/{shopItem}/purchase', [StoreController::class, 'purchase']);
        Route::get('/store/my-purchases', [StoreController::class, 'myPurchases']);

        // Subscription
        Route::get('/subscription/my', [ApiSubscriptionController::class, 'my']);
        Route::get('/subscription/features', [ApiSubscriptionController::class, 'features']);

        // Ad tracking
        Route::post('/ads/track-impression', [AdController::class, 'trackImpression']);

        // User bookings
        Route::post('/bookings', [ConsumerController::class, 'createBooking']);
        Route::get('/bookings/my', [ConsumerController::class, 'myBookings']);

    });

});
