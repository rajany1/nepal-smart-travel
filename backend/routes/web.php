<?php

use App\Http\Controllers\Admin\AchievementController;
use App\Http\Controllers\Admin\AdCampaignController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\AiAgentController;
use App\Http\Controllers\Admin\AiAgentTaskController;
use App\Http\Controllers\Admin\CuratedRouteController;
use App\Http\Controllers\Admin\LiveFeedController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PlatformExpenseController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SafetyController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TranslatorController;
use App\Http\Controllers\Admin\TravelPartnerController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\LegalDocumentTypeController;
use App\Http\Controllers\Partner\AdController;
use App\Http\Controllers\Partner\DashboardController;
use App\Http\Controllers\Partner\OfferController;
use App\Http\Controllers\Partner\PartnerAuthController;
use App\Http\Controllers\Partner\PartnerPaymentController;
use App\Http\Controllers\Partner\PayoutController;
use App\Http\Controllers\Web\LegalController;
use App\Http\Controllers\Web\PublicController;
use App\Http\Controllers\WebAuthController;
use App\Models\GameSetting;
use App\Services\MapCacheService;
use Illuminate\Support\Facades\Route;

$adminPrefix = GameSetting::getValue('admin_route_prefix', 'admin') ?? 'admin';

// ============ PUBLIC TOURIST WEB ============
Route::prefix('/')->name('web.')->group(function () {
    Route::get('/', [PublicController::class, 'home'])->name('home');
    Route::get('/places', [PublicController::class, 'places'])->name('places');
    Route::get('/places/{id}', [PublicController::class, 'placeShow'])->where('id', '[0-9]+|[0-9a-fA-F\-]{36}')->name('place');
    Route::get('/routes', [PublicController::class, 'routes'])->name('routes');
    Route::get('/routes/{route:slug}', [PublicController::class, 'routeShow'])->name('route');
    Route::get('/offers', [PublicController::class, 'offers'])->name('offers');
    Route::get('/{type}', [PublicController::class, 'categoryPage'])->whereIn('type', ['hotels', 'restaurants', 'attractions', 'cafes', 'activities'])->name('category');
});

// ============ PUBLIC LEGAL CENTER / ACCOUNT DELETION ============
Route::get('/delete-account', function () {
    return view('web.delete-account');
})->name('web.delete-account');

// Legacy aliases — kept working, render the canonical document pages.
Route::get('/terms', [LegalController::class, 'legacyTerms'])->name('web.terms');
Route::get('/privacy-policy', [LegalController::class, 'legacyPrivacy'])->name('web.privacy-policy');

// Legal Center index + document pages (slug-based; legacy type slugs resolve too).
Route::get('/legal', [LegalController::class, 'index'])->name('web.legal.index');
Route::get('/legal/{slug}', [LegalController::class, 'show'])->where('slug', '[a-z0-9_-]+')->name('web.legal.show');

// ============ ADMIN LOGIN (no auth) ============
Route::get('/login', function () {
    return redirect()->route('admin.login');
})->name('login');
Route::prefix($adminPrefix)->name('admin.')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])->name('login.post')->middleware('throttle:admin-login');
});

// ============ ADMIN LOGOUT ============
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// ============ PARTNER PORTAL (BUSINESS) ============
Route::prefix('partner')->name('partner.')->group(function () {
    Route::get('/register', [PartnerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [PartnerAuthController::class, 'register'])->name('register.post')->middleware('throttle:partner-register');
    Route::get('/login', [PartnerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [PartnerAuthController::class, 'login'])->name('login.post')->middleware('throttle:partner-login');
    Route::post('/logout', [PartnerAuthController::class, 'logout'])->name('logout');
});

Route::prefix('partner')->name('partner.')->middleware(['auth', 'status'])->group(function () {
    Route::get('/pending', [PartnerAuthController::class, 'pending'])->name('pending');
    Route::get('/business-form', [PartnerAuthController::class, 'businessForm'])->name('business-form');
    Route::post('/business-form', [PartnerAuthController::class, 'submitBusinessForm'])->name('business-form.post');

    // Registration wizard
    Route::get('/wizard', [PartnerAuthController::class, 'wizard'])->name('wizard');
    Route::post('/send-email-otp', [PartnerAuthController::class, 'sendEmailOtp'])->name('send-email-otp');
    Route::post('/verify-email-otp', [PartnerAuthController::class, 'verifyEmailOtp'])->name('verify-email-otp');
    Route::post('/send-phone-otp', [PartnerAuthController::class, 'sendPhoneOtp'])->name('send-phone-otp');
    Route::post('/verify-phone', [PartnerAuthController::class, 'verifyPhone'])->name('verify-phone');
});

Route::prefix('partner')->name('partner.')->middleware(['auth', 'status', 'business'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/offers', [OfferController::class, 'index'])->name('offers');
    Route::get('/offers/create', [OfferController::class, 'create'])->name('offers.create');
    Route::post('/offers', [OfferController::class, 'store'])->name('offers.store');
    Route::get('/offers/{offer}/edit', [OfferController::class, 'edit'])->name('offers.edit');
    Route::put('/offers/{offer}', [OfferController::class, 'update'])->name('offers.update');
    Route::post('/offers/{offer}/pause', [OfferController::class, 'pause'])->name('offers.pause');
    Route::post('/offers/{offer}/resume', [OfferController::class, 'resume'])->name('offers.resume');
    Route::delete('/offers/{offer}', [OfferController::class, 'destroy'])->name('offers.destroy');
    Route::get('/offers/{offer}/redemptions', [OfferController::class, 'redemptions'])->name('offers.redemptions');
    Route::post('/offers/{offer}/redemptions/{redemption}/used', [OfferController::class, 'markUsed'])->name('offers.redemptions.used');

    Route::get('/ads', [AdController::class, 'index'])->name('ads');
    Route::get('/ads/create', [AdController::class, 'create'])->name('ads.create');
    Route::post('/ads', [AdController::class, 'store'])->name('ads.store');
    Route::get('/ads/{adCampaign}/edit', [AdController::class, 'edit'])->name('ads.edit');
    Route::put('/ads/{adCampaign}', [AdController::class, 'update'])->name('ads.update');
    Route::post('/ads/{adCampaign}/pause', [AdController::class, 'pause'])->name('ads.pause');
    Route::post('/ads/{adCampaign}/resume', [AdController::class, 'resume'])->name('ads.resume');
    Route::get('/ads/{adCampaign}/pay', [AdController::class, 'pay'])->name('ads.pay');
    Route::post('/ads/{adCampaign}/pay', [AdController::class, 'initiatePayment'])->name('ads.pay.initiate');
    Route::get('/payments/esewa/callback', [AdController::class, 'esewaCallback'])->name('payments.esewa.callback');
    Route::get('/payments/khalti/callback', [AdController::class, 'khaltiCallback'])->name('payments.khalti.callback');

    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts', [PayoutController::class, 'store'])->name('payouts.store');
    Route::delete('/payouts/{payout}', [PayoutController::class, 'cancel'])->name('payouts.cancel');
    Route::delete('/ads/{adCampaign}', [AdController::class, 'destroy'])->name('ads.destroy');

    // Partner Wallet & Payments
    Route::get('/wallet', [PartnerPaymentController::class, 'wallet'])->name('wallet');
    Route::get('/wallet/topup', [PartnerPaymentController::class, 'topUpPage'])->name('wallet.topup');
    Route::post('/wallet/topup', [PartnerPaymentController::class, 'initiateTopUp'])->name('wallet.topup.initiate');
    Route::get('/wallet/topup/callback/{gateway}', [PartnerPaymentController::class, 'topUpCallback'])->name('topup.callback');
    Route::get('/payments/scan', [PartnerPaymentController::class, 'scanPage'])->name('payments.scan');
    Route::post('/payments/verify', [PartnerPaymentController::class, 'verifyCode'])->name('payments.verify');
    Route::get('/payments/history', [PartnerPaymentController::class, 'paymentHistory'])->name('payments.history');
    Route::post('/withdraw', [PartnerPaymentController::class, 'requestWithdrawal'])->name('withdraw');
    Route::post('/withdraw/{withdrawal}/cancel', [PartnerPaymentController::class, 'cancelWithdrawal'])->name('withdraw.cancel');
});

// ============ ADMIN PROTECTED ROUTES ============
Route::prefix($adminPrefix)->name('admin.')->middleware(['auth', 'status', 'role:admin,super_admin,moderator'])->group(function () {
    // Dashboard
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Reports
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::get('/reports/{id}', [AdminController::class, 'reportDetails'])->name('reports.view');
    Route::post('/reports/{id}/approve', [AdminController::class, 'approveReport'])->name('reports.approve');
    Route::post('/reports/{id}/reject', [AdminController::class, 'rejectReport'])->name('reports.reject');
    Route::post('/reports/{id}/delete', [AdminController::class, 'deleteReport'])->name('reports.delete');
    Route::post('/reports/bulk-delete', [AdminController::class, 'bulkDeleteReports'])->name('reports.bulk-delete');

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
    Route::post('/alerts/translate', [AdminController::class, 'translateAlert'])->name('alerts.translate');
    Route::post('/alerts/preview', [AdminController::class, 'previewAlert'])->name('alerts.preview');

    // Report Categories
    Route::get('/report-categories', [AdminController::class, 'reportCategories'])->name('report-categories');
    Route::get('/report-categories/groups', [AdminController::class, 'reportCategoryGroups'])->name('report-category-groups');
    Route::post('/report-categories/groups', [AdminController::class, 'createReportCategoryGroup'])->name('report-category-groups.create');
    Route::post('/report-categories/groups/{id}/update', [AdminController::class, 'updateReportCategoryGroup'])->name('report-category-groups.update');
    Route::post('/report-categories/groups/{id}/delete', [AdminController::class, 'deleteReportCategoryGroup'])->name('report-category-groups.delete');
    Route::post('/report-categories', [AdminController::class, 'createReportCategory'])->name('report-categories.create');
    Route::post('/report-categories/{id}/update', [AdminController::class, 'updateReportCategory'])->name('report-categories.update');
    Route::post('/report-categories/{id}/delete', [AdminController::class, 'deleteReportCategory'])->name('report-categories.delete');
    Route::post('/report-categories/{id}/options', [AdminController::class, 'createReportCategoryOption'])->name('report-categories.options.create');
    Route::post('/report-categories/options/{id}/update', [AdminController::class, 'updateReportCategoryOption'])->name('report-categories.options.update');
    Route::post('/report-categories/options/{id}/delete', [AdminController::class, 'deleteReportCategoryOption'])->name('report-categories.options.delete');
    Route::post('/report-categories/{id}/fields', [AdminController::class, 'createReportCategoryField'])->name('report-categories.fields.create');
    Route::post('/report-categories/fields/{id}/update', [AdminController::class, 'updateReportCategoryField'])->name('report-categories.fields.update');
    Route::post('/report-categories/fields/{id}/delete', [AdminController::class, 'deleteReportCategoryField'])->name('report-categories.fields.delete');

    // SOS Emergency
    Route::get('/sos', [AdminController::class, 'sosAlerts'])->name('sos');
    Route::post('/sos/{id}/restrict', [AdminController::class, 'restrictSosUser'])->name('sos.restrict');
    Route::post('/sos/{id}/unrestrict', [AdminController::class, 'unrestrictSosUser'])->name('sos.unrestrict');

    // Places
    Route::get('/places', [AdminController::class, 'places'])->name('places');
    Route::get('/places/osm', [AdminController::class, 'placesOsm'])->name('places.osm');
    Route::get('/places/corrections', [AdminController::class, 'corrections'])->name('places.corrections');
    Route::get('/places/{id}', [AdminController::class, 'showPlace'])->name('places.view');
    Route::get('/places/{id}/reviews', [AdminController::class, 'placeReviews'])->name('places.reviews');
    Route::post('/places/reviews/{id}/update', [AdminController::class, 'updatePlaceReview'])->name('places.reviews.update');
    Route::post('/places/reviews/{id}/delete', [AdminController::class, 'deletePlaceReview'])->name('places.reviews.delete');
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
    Route::get('/live-map/places', [AdminController::class, 'liveMapPlaces'])->name('live-map.places');
    Route::get('/live-map/landmarks', [AdminController::class, 'liveMapLandmarks'])->name('live-map.landmarks');

    // Realtime feed
    Route::get('/live-feed/changes', [LiveFeedController::class, 'changes'])->name('live-feed.changes');
    Route::get('/live-feed/map-layers', [LiveFeedController::class, 'mapLayers'])->name('live-feed.map-layers');
    Route::get('/live-feed/stats', [AdminController::class, 'liveFeedStats'])->name('live-feed.stats');

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

    // Legal Documents (Legal & Policies)
    Route::get('/legal-documents', [LegalDocumentController::class, 'index'])->name('legal-documents.index');
    Route::get('/legal-documents/create', [LegalDocumentController::class, 'create'])->name('legal-documents.create');
    Route::post('/legal-documents', [LegalDocumentController::class, 'store'])->name('legal-documents.store');
    Route::get('/legal-documents/{id}/edit', [LegalDocumentController::class, 'edit'])->name('legal-documents.edit');
    Route::put('/legal-documents/{id}', [LegalDocumentController::class, 'update'])->name('legal-documents.update');
    Route::get('/legal-documents/{id}/preview', [LegalDocumentController::class, 'preview'])->name('legal-documents.preview');
    Route::get('/legal-documents/{id}/versions', [LegalDocumentController::class, 'versions'])->name('legal-documents.versions');
    Route::post('/legal-documents/{id}/publish', [LegalDocumentController::class, 'publish'])->name('legal-documents.publish');
    Route::post('/legal-documents/{id}/unpublish', [LegalDocumentController::class, 'unpublish'])->name('legal-documents.unpublish');
    Route::post('/legal-documents/{id}/archive', [LegalDocumentController::class, 'archive'])->name('legal-documents.archive');
    Route::post('/legal-documents/{id}/delete', [LegalDocumentController::class, 'destroy'])->name('legal-documents.delete');

    // Legal Document Types
    Route::get('/legal-document-types', [LegalDocumentTypeController::class, 'index'])->name('legal-document-types.index');
    Route::get('/legal-document-types/create', [LegalDocumentTypeController::class, 'create'])->name('legal-document-types.create');
    Route::post('/legal-document-types', [LegalDocumentTypeController::class, 'store'])->name('legal-document-types.store');
    Route::get('/legal-document-types/{id}/edit', [LegalDocumentTypeController::class, 'edit'])->name('legal-document-types.edit');
    Route::put('/legal-document-types/{id}', [LegalDocumentTypeController::class, 'update'])->name('legal-document-types.update');
    Route::post('/legal-document-types/{id}/delete', [LegalDocumentTypeController::class, 'destroy'])->name('legal-document-types.delete');

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
    Route::post('/ad-campaigns/{adCampaign}/pause', [AdCampaignController::class, 'pause'])->name('ad-campaigns.pause');
    Route::post('/ad-campaigns/{adCampaign}/resume', [AdCampaignController::class, 'resume'])->name('ad-campaigns.resume');
    Route::post('/ad-campaigns/{adCampaign}/refund', [AdCampaignController::class, 'refund'])->name('ad-campaigns.refund');

    // Reward Offers
    Route::get('/offers', [App\Http\Controllers\Admin\OfferController::class, 'index'])->name('offers');
    Route::post('/offers/{offer}/pause', [App\Http\Controllers\Admin\OfferController::class, 'pause'])->name('offers.pause');
    Route::post('/offers/{offer}/resume', [App\Http\Controllers\Admin\OfferController::class, 'resume'])->name('offers.resume');
    Route::post('/offers/{offer}/value', [App\Http\Controllers\Admin\OfferController::class, 'updateValue'])->name('offers.value');
    Route::post('/offers/{offer}/delete', [App\Http\Controllers\Admin\OfferController::class, 'destroy'])->name('offers.delete');
    Route::post('/offers/{offer}/restore', [App\Http\Controllers\Admin\OfferController::class, 'restore'])->name('offers.restore')->withTrashed();

    // Content Safety (Review AI agent reports)
    Route::get('/moderation', [SafetyController::class, 'index'])->name('moderation');
    Route::get('/moderation/users/{user}', [SafetyController::class, 'showUser'])->name('moderation.users');
    Route::post('/moderation/strike/{user}', [SafetyController::class, 'strike'])->name('moderation.strike');
    Route::post('/moderation/activate/{user}', [SafetyController::class, 'activate'])->name('moderation.activate');

    // Payouts
    Route::get('/payouts', [App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts');
    Route::get('/payouts/{payout}', [App\Http\Controllers\Admin\PayoutController::class, 'show'])->name('payouts.show');
    Route::post('/payouts/{payout}/approve', [App\Http\Controllers\Admin\PayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout}/process', [App\Http\Controllers\Admin\PayoutController::class, 'processPayment'])->name('payouts.process');
    Route::post('/payouts/{payout}/retry', [App\Http\Controllers\Admin\PayoutController::class, 'retryPayment'])->name('payouts.retry');
    Route::post('/payouts/{payout}/paid', [App\Http\Controllers\Admin\PayoutController::class, 'markPaid'])->name('payouts.paid');
    Route::post('/payouts/{payout}/reject', [App\Http\Controllers\Admin\PayoutController::class, 'reject'])->name('payouts.reject');

    // Curated Routes
    Route::get('/routes', [CuratedRouteController::class, 'index'])->name('routes');
    Route::post('/routes', [CuratedRouteController::class, 'store'])->name('routes.store');
    Route::put('/routes/{route}', [CuratedRouteController::class, 'update'])->name('routes.update');
    Route::delete('/routes/{route}', [CuratedRouteController::class, 'destroy'])->name('routes.destroy');

    // Business verification
    Route::post('/travel-partners/{travelPartner}/verify', [TravelPartnerController::class, 'verifyPartner'])->name('travel-partners.verify');
    Route::post('/travel-partners/{travelPartner}/reject', [TravelPartnerController::class, 'rejectPartner'])->name('travel-partners.reject');
    Route::post('/travel-partners/{travelPartner}/suspend', [TravelPartnerController::class, 'suspendPartner'])->name('travel-partners.suspend');
    Route::post('/travel-partners/{travelPartner}/reinstate', [TravelPartnerController::class, 'reinstatePartner'])->name('travel-partners.reinstate');

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

    // Withdrawals & Coin System
    Route::get('/withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals');
    Route::get('/withdrawals/{id}', [WithdrawalController::class, 'show'])->name('withdrawals.show');
    Route::post('/withdrawals/{id}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
    Route::post('/withdrawals/{id}/process', [WithdrawalController::class, 'processPayment'])->name('withdrawals.process');
    Route::post('/withdrawals/{id}/retry', [WithdrawalController::class, 'retryPayment'])->name('withdrawals.retry');
    Route::post('/withdrawals/{id}/complete', [WithdrawalController::class, 'complete'])->name('withdrawals.complete');
    Route::post('/withdrawals/{id}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
    Route::get('/coin-settings', [WithdrawalController::class, 'coinSettings'])->name('coin-settings');
    Route::post('/coin-settings', [WithdrawalController::class, 'updateCoinSettings'])->name('coin-settings.update');
    Route::get('/earnings-report', [WithdrawalController::class, 'earningsReport'])->name('earnings-report');
    Route::get('/financial-audit', [WithdrawalController::class, 'financialAuditStatement'])->name('financial-audit');
    Route::get('/money-flow', [WithdrawalController::class, 'moneyFlowAudit'])->name('money-flow');

    // Platform Expenses
    Route::get('/expenses', [PlatformExpenseController::class, 'index'])->name('expenses');
    Route::post('/expenses', [PlatformExpenseController::class, 'store'])->name('expenses.store');
    Route::put('/expenses/{expense}', [PlatformExpenseController::class, 'update'])->name('expenses.update');
    Route::delete('/expenses/{expense}', [PlatformExpenseController::class, 'destroy'])->name('expenses.destroy');
    Route::post('/expenses/{expense}/mark-paid', [PlatformExpenseController::class, 'markPaid'])->name('expenses.mark-paid');
    Route::get('/expenses/renewal-alerts', [PlatformExpenseController::class, 'renewalAlerts'])->name('expenses.renewal-alerts');

    // Employee Salaries
    Route::get('/salaries', [PlatformExpenseController::class, 'employees'])->name('salaries');
    Route::post('/salaries', [PlatformExpenseController::class, 'storeSalary'])->name('salaries.store');
    Route::post('/salaries/{salary}/mark-paid', [PlatformExpenseController::class, 'markSalaryPaid'])->name('salaries.mark-paid');
    Route::put('/salaries/{salary}', [PlatformExpenseController::class, 'updateSalary'])->name('salaries.update');
    Route::delete('/salaries/{salary}', [PlatformExpenseController::class, 'deleteSalary'])->name('salaries.delete');

    // Financial Overview
    Route::get('/financial-overview', [PlatformExpenseController::class, 'financialOverview'])->name('financial-overview');

    // Support Inbox
    Route::get('/support', [AdminSupportController::class, 'index'])->name('support');
    Route::get('/support/{id}', [AdminSupportController::class, 'show'])->name('support.show')->whereNumber('id');
    Route::post('/support/{id}/reply', [AdminSupportController::class, 'reply'])->name('support.reply')->whereNumber('id');
    Route::post('/support/{id}/assign', [AdminSupportController::class, 'assign'])->name('support.assign')->whereNumber('id');
    Route::post('/support/{id}/takeover', [AdminSupportController::class, 'takeOver'])->name('support.takeover')->whereNumber('id');
    Route::post('/support/{id}/category', [AdminSupportController::class, 'changeCategory'])->name('support.category')->whereNumber('id');
    Route::post('/support/{id}/priority', [AdminSupportController::class, 'changePriority'])->name('support.priority')->whereNumber('id');
    Route::post('/support/{id}/resolve', [AdminSupportController::class, 'resolve'])->name('support.resolve')->whereNumber('id');
    Route::post('/support/{id}/close', [AdminSupportController::class, 'close'])->name('support.close')->whereNumber('id');
    Route::post('/support/{id}/reopen', [AdminSupportController::class, 'reopen'])->name('support.reopen')->whereNumber('id');
    Route::post('/support/{id}/escalate', [AdminSupportController::class, 'escalate'])->name('support.escalate')->whereNumber('id');
    Route::post('/support/{id}/stop-ai', [AdminSupportController::class, 'stopAi'])->name('support.stop-ai')->whereNumber('id');
    Route::get('/support/poll/inbox', [AdminSupportController::class, 'pollInbox'])->name('support.poll-inbox');
    Route::get('/support/{id}/poll', [AdminSupportController::class, 'pollMessages'])->name('support.poll-messages')->whereNumber('id');
});

// ============ ADMIN GEOJSON (auth only, Redis cached) ============
Route::prefix($adminPrefix)->name('admin.')->middleware(['auth'])->group(function () {
    Route::get('/nepal-boundary.geojson', function () {
        $content = MapCacheService::getBoundary();
        if ($content === null) {
            abort(404);
        }

        return response($content, 200)->header('Content-Type', 'application/json');
    })->name('nepal.boundary');
    Route::get('/nepal-adm1.geojson', function () {
        $content = MapCacheService::getProvinces();
        if ($content === null) {
            abort(404);
        }

        return response($content, 200)->header('Content-Type', 'application/json');
    })->name('nepal.adm1');
    Route::get('/nepal-adm2.geojson', function () {
        $content = MapCacheService::getDistricts();
        if ($content === null) {
            abort(404);
        }

        return response($content, 200)->header('Content-Type', 'application/json');
    })->name('nepal.adm2');
});

// ============ REDIRECT /admin to custom prefix ============
if ($adminPrefix !== 'admin') {
    Route::match(['get', 'post'], '/admin/{any?}', function ($any = null) use ($adminPrefix) {
        $path = '/'.$adminPrefix;
        if ($any) {
            $path .= '/'.$any;
        }

        return redirect($path, 301);
    })->where('any', '.*');
}
