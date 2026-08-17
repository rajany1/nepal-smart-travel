<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AchievementController;
use App\Http\Controllers\Admin\TravelPartnerController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\AiAgentController;
use App\Http\Controllers\Admin\AiAgentTaskController;
use App\Http\Controllers\Admin\TranslatorController;

// ============ PUBLIC TOURIST WEB ============
Route::prefix('/')->name('web.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Web\PublicController::class, 'home'])->name('home');
    Route::get('/places', [\App\Http\Controllers\Web\PublicController::class, 'places'])->name('places');
    Route::get('/places/{id}', [\App\Http\Controllers\Web\PublicController::class, 'placeShow'])->where('id', '[0-9]+|[0-9a-fA-F\-]{36}')->name('place');
    Route::get('/routes', [\App\Http\Controllers\Web\PublicController::class, 'routes'])->name('routes');
    Route::get('/routes/{route:slug}', [\App\Http\Controllers\Web\PublicController::class, 'routeShow'])->name('route');
    Route::get('/offers', [\App\Http\Controllers\Web\PublicController::class, 'offers'])->name('offers');
    Route::get('/{type}', [\App\Http\Controllers\Web\PublicController::class, 'categoryPage'])->whereIn('type', ['hotels', 'restaurants', 'attractions', 'cafes', 'activities'])->name('category');
});

// ============ ADMIN LOGIN (no auth) ============
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.post');
});

// ============ ADMIN LOGOUT ============
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// ============ PARTNER PORTAL (BUSINESS) ============
Route::prefix('partner')->name('partner.')->group(function () {
    Route::get('/register', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'register'])->name('register.post');
    Route::get('/login', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'logout'])->name('logout');
});

Route::prefix('partner')->name('partner.')->middleware(['auth', 'status'])->group(function () {
    Route::get('/pending', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'pending'])->name('pending');
    Route::get('/business-form', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'businessForm'])->name('business-form');
    Route::post('/business-form', [\App\Http\Controllers\Partner\PartnerAuthController::class, 'submitBusinessForm'])->name('business-form.post');
});

Route::prefix('partner')->name('partner.')->middleware(['auth', 'status', 'business'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Partner\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/offers', [\App\Http\Controllers\Partner\OfferController::class, 'index'])->name('offers');
    Route::get('/offers/create', [\App\Http\Controllers\Partner\OfferController::class, 'create'])->name('offers.create');
    Route::post('/offers', [\App\Http\Controllers\Partner\OfferController::class, 'store'])->name('offers.store');
    Route::get('/offers/{offer}/edit', [\App\Http\Controllers\Partner\OfferController::class, 'edit'])->name('offers.edit');
    Route::put('/offers/{offer}', [\App\Http\Controllers\Partner\OfferController::class, 'update'])->name('offers.update');
    Route::post('/offers/{offer}/pause', [\App\Http\Controllers\Partner\OfferController::class, 'pause'])->name('offers.pause');
    Route::post('/offers/{offer}/resume', [\App\Http\Controllers\Partner\OfferController::class, 'resume'])->name('offers.resume');
    Route::delete('/offers/{offer}', [\App\Http\Controllers\Partner\OfferController::class, 'destroy'])->name('offers.destroy');
    Route::get('/offers/{offer}/redemptions', [\App\Http\Controllers\Partner\OfferController::class, 'redemptions'])->name('offers.redemptions');
    Route::post('/offers/{offer}/redemptions/{redemption}/used', [\App\Http\Controllers\Partner\OfferController::class, 'markUsed'])->name('offers.redemptions.used');

    Route::get('/ads', [\App\Http\Controllers\Partner\AdController::class, 'index'])->name('ads');
    Route::get('/ads/create', [\App\Http\Controllers\Partner\AdController::class, 'create'])->name('ads.create');
    Route::post('/ads', [\App\Http\Controllers\Partner\AdController::class, 'store'])->name('ads.store');
    Route::get('/ads/{adCampaign}/edit', [\App\Http\Controllers\Partner\AdController::class, 'edit'])->name('ads.edit');
    Route::put('/ads/{adCampaign}', [\App\Http\Controllers\Partner\AdController::class, 'update'])->name('ads.update');
    Route::post('/ads/{adCampaign}/pause', [\App\Http\Controllers\Partner\AdController::class, 'pause'])->name('ads.pause');
    Route::post('/ads/{adCampaign}/resume', [\App\Http\Controllers\Partner\AdController::class, 'resume'])->name('ads.resume');
    Route::get('/ads/{adCampaign}/pay', [\App\Http\Controllers\Partner\AdController::class, 'pay'])->name('ads.pay');
    Route::post('/ads/{adCampaign}/pay', [\App\Http\Controllers\Partner\AdController::class, 'initiatePayment'])->name('ads.pay.initiate');
    Route::get('/payments/esewa/callback', [\App\Http\Controllers\Partner\AdController::class, 'esewaCallback'])->name('payments.esewa.callback');
    Route::get('/payments/khalti/callback', [\App\Http\Controllers\Partner\AdController::class, 'khaltiCallback'])->name('payments.khalti.callback');

    Route::get('/payouts', [\App\Http\Controllers\Partner\PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts', [\App\Http\Controllers\Partner\PayoutController::class, 'store'])->name('payouts.store');
    Route::delete('/payouts/{payout}', [\App\Http\Controllers\Partner\PayoutController::class, 'cancel'])->name('payouts.cancel');
    Route::delete('/ads/{adCampaign}', [\App\Http\Controllers\Partner\AdController::class, 'destroy'])->name('ads.destroy');
});

// ============ ADMIN PROTECTED ROUTES ============
Route::prefix('admin')->name('admin.')->middleware(['auth', 'status'])->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Reports
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/reports/{id}', [AdminController::class, 'reportDetails'])->name('reports.view');
    Route::post('/reports/{id}/approve', [AdminController::class, 'approveReport'])->name('reports.approve');
    Route::post('/reports/{id}/reject', [AdminController::class, 'rejectReport'])->name('reports.reject');
    Route::post('/reports/{id}/delete', [AdminController::class, 'deleteReport'])->name('reports.delete');

    // Users
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users/{id}/toggle-status', [AdminController::class, 'toggleUserStatus'])->name('users.toggle-status');
    Route::post('/users/{id}/make-admin', [AdminController::class, 'makeAdmin'])->name('users.make-admin');
    Route::post('/users/{id}/remove-admin', [AdminController::class, 'removeAdmin'])->name('users.remove-admin');
    Route::post('/users/{id}/make-moderator', [AdminController::class, 'makeModerator'])->name('users.make-moderator');
    Route::post('/users/{id}/remove-moderator', [AdminController::class, 'removeModerator'])->name('users.remove-moderator');
    Route::post('/users/{id}/assign-role', [AdminController::class, 'assignUserRole'])->name('users.assign-role');

    // Alerts
    Route::get('/alerts', [AdminController::class, 'alerts'])->name('alerts');
    Route::post('/alerts', [AdminController::class, 'createAlert'])->name('alerts.create');
    Route::post('/alerts/{id}/delete', [AdminController::class, 'deleteAlert'])->name('alerts.delete');

    // Places
    Route::get('/places', [AdminController::class, 'places'])->name('places');
    Route::get('/places/osm', [AdminController::class, 'placesOsm'])->name('places.osm');
    Route::get('/places/corrections', [AdminController::class, 'corrections'])->name('places.corrections');
    Route::get('/places/{id}', [AdminController::class, 'showPlace'])->name('places.view');
    Route::post('/places', [AdminController::class, 'createPlace'])->name('places.create');
    Route::post('/places/{id}/update', [AdminController::class, 'updatePlace'])->name('places.update');
    Route::post('/places/{id}/delete', [AdminController::class, 'deletePlace'])->name('places.delete');
    Route::post('/places/{id}/feature', [AdminController::class, 'featurePlace'])->name('places.feature');
    Route::post('/places/{id}/approve', [AdminController::class, 'approvePlace'])->name('places.approve');
    Route::post('/places/{id}/reject', [AdminController::class, 'rejectPlace'])->name('places.reject');
    Route::post('/places/corrections/{id}/apply', [AdminController::class, 'applyCorrection'])->name('places.corrections.apply');
    Route::post('/places/corrections/{id}/reject', [AdminController::class, 'rejectCorrection'])->name('places.corrections.reject');
    Route::post('/places/{id}/images/delete', [AdminController::class, 'deletePlaceImage'])->name('places.images.delete');
    Route::post('/places/import-osm', [AdminController::class, 'importOsmPlaces'])->name('places.import-osm');
    Route::match(['post', 'put', 'delete'], '/places/categories', [AdminController::class, 'manageCategories'])->name('places.categories');
    Route::post('/places/bulk-delete', [AdminController::class, 'bulkDeletePlaces'])->name('places.bulk-delete');
    Route::post('/places/bulk-update', [AdminController::class, 'bulkUpdatePlaces'])->name('places.bulk-update');

    // Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');

    // Live Map
    Route::get('/live-map', [AdminController::class, 'liveMap'])->name('live-map');

    // Audit Logs
    Route::get('/audit-logs', [AdminController::class, 'auditLogs'])->name('audit-logs');

    // Moderator Permissions

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])->name('roles');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions');
    Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
    Route::get('/permissions/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

    // Achievements
    Route::get('/achievements', [AchievementController::class, 'index'])->name('achievements');
    Route::post('/achievements', [AchievementController::class, 'store'])->name('achievements.store');
    Route::get('/achievements/{achievement}/edit', [AchievementController::class, 'edit'])->name('achievements.edit');
    Route::put('/achievements/{achievement}', [AchievementController::class, 'update'])->name('achievements.update');
    Route::delete('/achievements/{achievement}', [AchievementController::class, 'destroy'])->name('achievements.destroy');

    // User Progress (admin view)
    Route::get('/users/{user}/progress', [AchievementController::class, 'userProgress'])->name('users.progress');
    Route::post('/users/{user}/adjust-xp', [AchievementController::class, 'adjustXp'])->name('users.adjust-xp');
    Route::post('/users/{user}/recalculate-level', [AchievementController::class, 'recalculateLevel'])->name('users.recalculate-level');
    Route::post('/user-achievements/{userAchievement}/flag', [AchievementController::class, 'flagAchievement'])->name('user-achievements.flag');
    Route::post('/user-achievements/{userAchievement}/clear', [AchievementController::class, 'clearSuspicious'])->name('user-achievements.clear');

    // Travel Partners & Bookings
    Route::get('/travel-partners', [TravelPartnerController::class, 'partners'])->name('travel-partners');
    Route::post('/travel-partners', [TravelPartnerController::class, 'partnerStore'])->name('travel-partners.store');
    Route::put('/travel-partners/{travelPartner}', [TravelPartnerController::class, 'partnerUpdate'])->name('travel-partners.update');
    Route::get('/bookings', [TravelPartnerController::class, 'bookings'])->name('bookings');
    Route::post('/bookings', [TravelPartnerController::class, 'bookingStore'])->name('bookings.store');
    Route::post('/bookings/{booking}/confirm', [TravelPartnerController::class, 'bookingConfirm'])->name('bookings.confirm');
    Route::post('/bookings/{booking}/complete', [TravelPartnerController::class, 'bookingComplete'])->name('bookings.complete');
    Route::post('/bookings/{booking}/cancel', [TravelPartnerController::class, 'bookingCancel'])->name('bookings.cancel');

    // Subscriptions
    Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
    Route::post('/subscription/plans', [SubscriptionController::class, 'planStore'])->name('subscription.plans.store');
    Route::put('/subscription/plans/{subscriptionPlan}', [SubscriptionController::class, 'planUpdate'])->name('subscription.plans.update');
    Route::delete('/subscription/plans/{subscriptionPlan}', [SubscriptionController::class, 'planDestroy'])->name('subscription.plans.destroy');
    Route::post('/subscription/plans/{subscriptionPlan}/toggle-active', [SubscriptionController::class, 'planToggleActive'])->name('subscription.plans.toggle-active');
    Route::get('/subscription/users', [SubscriptionController::class, 'users'])->name('subscription.users');
    Route::post('/subscription/users/assign', [SubscriptionController::class, 'assignSubscription'])->name('subscription.users.assign');
    Route::post('/subscription/users/{userSubscription}/cancel', [SubscriptionController::class, 'cancelSubscription'])->name('subscription.users.cancel');

    // Ad Campaigns
    Route::get('/ad-campaigns', [AdCampaignController::class, 'index'])->name('ad-campaigns');
    Route::post('/ad-campaigns', [AdCampaignController::class, 'store'])->name('ad-campaigns.store');
    Route::put('/ad-campaigns/{adCampaign}', [AdCampaignController::class, 'update'])->name('ad-campaigns.update');
    Route::delete('/ad-campaigns/{adCampaign}', [AdCampaignController::class, 'destroy'])->name('ad-campaigns.destroy');
    Route::post('/ad-campaigns/{adCampaign}/approve', [AdCampaignController::class, 'approve'])->name('ad-campaigns.approve');
    Route::post('/ad-campaigns/{adCampaign}/reject', [AdCampaignController::class, 'reject'])->name('ad-campaigns.reject');
    Route::post('/ad-campaigns/{adCampaign}/pause', [AdCampaignController::class, 'pause'])->name('ad-campaigns.pause');
    Route::post('/ad-campaigns/{adCampaign}/resume', [AdCampaignController::class, 'resume'])->name('ad-campaigns.resume');
    Route::post('/ad-campaigns/{adCampaign}/refund', [AdCampaignController::class, 'refund'])->name('ad-campaigns.refund');

    // Reward Offers
    Route::get('/offers', [\App\Http\Controllers\Admin\OfferController::class, 'index'])->name('offers');
    Route::post('/offers/{offer}/approve', [\App\Http\Controllers\Admin\OfferController::class, 'approve'])->name('offers.approve');
    Route::post('/offers/{offer}/reject', [\App\Http\Controllers\Admin\OfferController::class, 'reject'])->name('offers.reject');
    Route::post('/offers/{offer}/pause', [\App\Http\Controllers\Admin\OfferController::class, 'pause'])->name('offers.pause');
    Route::post('/offers/{offer}/resume', [\App\Http\Controllers\Admin\OfferController::class, 'resume'])->name('offers.resume');
    Route::post('/offers/{offer}/value', [\App\Http\Controllers\Admin\OfferController::class, 'updateValue'])->name('offers.value');
    Route::post('/offers/{offer}/delete', [\App\Http\Controllers\Admin\OfferController::class, 'destroy'])->name('offers.delete');
    Route::post('/offers/{offer}/restore', [\App\Http\Controllers\Admin\OfferController::class, 'restore'])->name('offers.restore')->withTrashed();

    // Content Safety (Review AI agent reports)
    Route::get('/moderation', [\App\Http\Controllers\Admin\SafetyController::class, 'index'])->name('moderation');
    Route::get('/moderation/users/{user}', [\App\Http\Controllers\Admin\SafetyController::class, 'showUser'])->name('moderation.users');
    Route::post('/moderation/strike/{user}', [\App\Http\Controllers\Admin\SafetyController::class, 'strike'])->name('moderation.strike');
    Route::post('/moderation/activate/{user}', [\App\Http\Controllers\Admin\SafetyController::class, 'activate'])->name('moderation.activate');

    // Payouts
    Route::get('/payouts', [\App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts/{payout}/paid', [\App\Http\Controllers\Admin\PayoutController::class, 'markPaid'])->name('payouts.paid');
    Route::post('/payouts/{payout}/reject', [\App\Http\Controllers\Admin\PayoutController::class, 'reject'])->name('payouts.reject');

    // Curated Routes
    Route::get('/routes', [\App\Http\Controllers\Admin\CuratedRouteController::class, 'index'])->name('routes');
    Route::post('/routes', [\App\Http\Controllers\Admin\CuratedRouteController::class, 'store'])->name('routes.store');
    Route::put('/routes/{route}', [\App\Http\Controllers\Admin\CuratedRouteController::class, 'update'])->name('routes.update');
    Route::delete('/routes/{route}', [\App\Http\Controllers\Admin\CuratedRouteController::class, 'destroy'])->name('routes.destroy');

    // Business verification
    Route::post('/travel-partners/{travelPartner}/verify', [TravelPartnerController::class, 'verifyPartner'])->name('travel-partners.verify');
    Route::post('/travel-partners/{travelPartner}/reject', [TravelPartnerController::class, 'rejectPartner'])->name('travel-partners.reject');

    // AI Agents
    Route::get('/ai/agents', [AiAgentController::class, 'index'])->name('ai.agents');
    Route::post('/ai/agents', [AiAgentController::class, 'store'])->name('ai.agents.store');
    Route::post('/ai/agents/{agent}/update', [AiAgentController::class, 'update'])->name('ai.agents.update');
    Route::get('/ai/agents/{agent}/run', [AiAgentController::class, 'run'])->name('ai.agents.run');
    Route::get('/ai/tasks', [AiAgentTaskController::class, 'index'])->name('ai.tasks');
    Route::post('/ai/tasks', [AiAgentTaskController::class, 'store'])->name('ai.tasks.store');
    Route::get('/ai/tasks/{task}/retry', [AiAgentTaskController::class, 'retry'])->name('ai.tasks.retry');

    // Translator (word dictionary for the mobile app UI)
    Route::get('/translator', [TranslatorController::class, 'index'])->name('translator');
    Route::post('/translator', [TranslatorController::class, 'store'])->name('translator.store');
    Route::post('/translator/import', [TranslatorController::class, 'bulkImport'])->name('translator.import');
    Route::post('/translator/{translation}/update', [TranslatorController::class, 'update'])->name('translator.update');
    Route::post('/translator/{translation}/toggle', [TranslatorController::class, 'toggle'])->name('translator.toggle');
    Route::post('/translator/{translation}/delete', [TranslatorController::class, 'destroy'])->name('translator.delete');
});
