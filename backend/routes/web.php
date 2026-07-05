<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\AchievementController;
use App\Http\Controllers\Admin\StoreController as AdminStoreController;
use App\Http\Controllers\Admin\SponsorController;
use App\Http\Controllers\Admin\TravelPartnerController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\AdCampaignController;

// Welcome/landing page
Route::get('/', function () {
    return view('welcome');
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
    Route::get('/places/{id}', [AdminController::class, 'showPlace'])->name('places.view');
    Route::post('/places', [AdminController::class, 'createPlace'])->name('places.create');
    Route::post('/places/{id}/update', [AdminController::class, 'updatePlace'])->name('places.update');
    Route::post('/places/{id}/delete', [AdminController::class, 'deletePlace'])->name('places.delete');
    Route::post('/places/{id}/feature', [AdminController::class, 'featurePlace'])->name('places.feature');
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

    // Sponsors
    Route::get('/sponsors', [SponsorController::class, 'index'])->name('sponsors');
    Route::post('/sponsors', [SponsorController::class, 'store'])->name('sponsors.store');
    Route::put('/sponsors/{sponsor}', [SponsorController::class, 'update'])->name('sponsors.update');
    Route::delete('/sponsors/{sponsor}', [SponsorController::class, 'destroy'])->name('sponsors.destroy');

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

    // Store
    Route::get('/store/items', [AdminStoreController::class, 'items'])->name('store.items');
    Route::post('/store/items', [AdminStoreController::class, 'store'])->name('store.items.store');
    Route::put('/store/items/{shopItem}', [AdminStoreController::class, 'update'])->name('store.items.update');
    Route::post('/store/items/{shopItem}/codes', [AdminStoreController::class, 'uploadCodes'])->name('store.items.codes');
    Route::get('/store/orders', [AdminStoreController::class, 'orders'])->name('store.orders');
    Route::post('/store/orders/{userPurchase}/fulfill', [AdminStoreController::class, 'fulfill'])->name('store.orders.fulfill');
    Route::post('/store/orders/{userPurchase}/cancel', [AdminStoreController::class, 'cancel'])->name('store.orders.cancel');
    Route::post('/store/orders/{userPurchase}/refund', [AdminStoreController::class, 'refund'])->name('store.orders.refund');
});
