import 'dart:async';
import 'package:dio/dio.dart';
import "../../core/services/localization_service.dart";
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../config/constants/app_constants.dart';
import '../services/session_manager.dart';

class ApiClient {
  static ApiClient? _instance;
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  final SessionManager _session = SessionManager.instance;

  /// Navigator key set from main.dart for interceptor navigation.
  static GlobalKey<NavigatorState>? navigatorKey;

  ApiClient._() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConstants.baseUrl,
      connectTimeout: AppConstants.apiTimeout,
      receiveTimeout: AppConstants.apiTimeout,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    _dio.interceptors.add(AuthInterceptor(_dio, _session));
    _dio.interceptors.add(LogInterceptor(
      requestBody: true,
      responseBody: true,
      error: true,
    ));
  }

  static ApiClient get instance {
    _instance ??= ApiClient._();
    return _instance!;
  }

  Dio get dio => _dio;

  /// ✅ Get access token from SessionManager
  Future<String?> getToken() async {
    return await _session.getAccessToken();
  }

  /// ✅ Set access token in SessionManager
  Future<void> setToken(String token) async {
    await _session.setAccessToken(token);
  }

  /// ✅ Get refresh token from SessionManager
  Future<String?> getRefreshToken() async {
    return await _session.getRefreshToken();
  }

  /// ✅ Set refresh token in SessionManager
  Future<void> setRefreshToken(String token) async {
    await _session.setRefreshToken(token);
  }

  /// ✅ Clear tokens from SessionManager
  Future<void> clearToken() async {
    await _session.clearSession();
  }

  // Auth endpoints
  Future<Response> socialLogin({
    required String idToken,
    bool? termsAccepted,
    bool? privacyAccepted,
    bool? ageConfirmed,
  }) async {
    return _dio.post('/auth/social-login', data: {
      'id_token': idToken,
      if (termsAccepted != null) 'terms_accepted': termsAccepted,
      if (privacyAccepted != null) 'privacy_accepted': privacyAccepted,
      if (ageConfirmed != null) 'age_confirmed': ageConfirmed,
    });
  }

  /// Pre-check: determine whether a Google token maps to a new or existing user
  /// without creating anything.  Returns `{ success, is_new_user }`.
  Future<Response> googlePreCheck({required String idToken}) async {
    return _dio.post('/auth/google-pre-check', data: {
      'id_token': idToken,
    });
  }

  /// Check which legal documents the authenticated user still needs to accept.
  Future<Response> getLegalAcceptanceStatus() async {
    return _dio.get('/auth/legal-acceptance-status');
  }

  /// Record the user's acceptance of the specified legal documents.
  Future<Response> acceptLegal({
    required List<String> documents,
    String? appVersion,
    String? platform,
  }) async {
    return _dio.post('/auth/legal-acceptance', data: {
      'documents': documents,
      if (appVersion != null) 'app_version': appVersion,
      if (platform != null) 'platform': platform,
    });
  }

  Future<Response> register({
    required String name,
    required String email,
    String? phone,
    required String password,
    required String passwordConfirmation,
    bool termsAccepted = false,
    bool privacyAccepted = false,
    bool ageConfirmed = false,
  }) async {
    return _dio.post('/auth/register', data: {
      'name': name,
      'email': email,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      'password': password,
      'password_confirmation': passwordConfirmation,
      'terms_accepted': termsAccepted,
      'privacy_accepted': privacyAccepted,
      'age_confirmed': ageConfirmed,
    });
  }

  Future<Response> login({
    required String email,
    required String password,
  }) async {
    return _dio.post('/auth/login', data: {
      'email': email,
      'password': password,
    });
  }

  Future<Response> refreshToken(String token) async {
    return _dio.post('/auth/refresh',
      options: Options(headers: {'Authorization': 'Bearer $token'}),
    );
  }

  Future<Response> logout() async {
    return _dio.post('/auth/logout');
  }

  Future<Response> deleteAccount({String? confirmation}) async {
    return _dio.delete('/users/me', data: {'confirmation': confirmation});
  }

  Future<Response> verifyEmail(String otp) async {
    return _dio.post('/auth/verify-email', data: {'otp': otp});
  }

  Future<Response> resendVerificationEmail(String email) async {
    return _dio.post('/auth/resend-verification', data: {'email': email});
  }

  Future<Response> sendPhoneOtp(String phone) async {
    return _dio.post('/auth/send-phone-otp', data: {'phone': phone});
  }

  Future<Response> verifyPhone(String otp) async {
    return _dio.post('/auth/verify-phone', data: {'otp': otp});
  }

  Future<Response> sendPasswordReset(String email) async {
    return _dio.post('/auth/forgot-password', data: {'email': email});
  }

  Future<Response> resetPassword(String email, String token, String newPassword) async {
    return _dio.post('/auth/reset-password', data: {
      'email': email,
      'token': token,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  // User endpoints
  Future<Response> getProfile() async {
    return _dio.get('/users/me');
  }

  Future<Response> updateProfile(Map<String, dynamic> data) async {
    return _dio.put('/users/me', data: data);
  }

  Future<Response> getUserProfile(String userId) async {
    return _dio.get('/users/$userId/profile');
  }

  // Alert endpoints
  Future<Response> getAlerts({String? severity, String? type, String? district, double? lat, double? lng, double? radiusKm}) async {
    final queryParams = <String, dynamic>{};
    if (severity != null) queryParams['severity'] = severity;
    if (type != null) queryParams['type'] = type;
    if (district != null) queryParams['district'] = district;
    if (lat != null) queryParams['lat'] = lat;
    if (lng != null) queryParams['lng'] = lng;
    if (radiusKm != null) queryParams['radius_km'] = radiusKm;
    return _dio.get('/alerts', queryParameters: queryParams);
  }

  /// Alerts + approved reports merged around a point (server-side bbox).
  Future<Response> getNearbyAlerts({required double lat, required double lng, double radiusKm = 5.0}) async {
    return _dio.get('/alerts/nearby', queryParameters: {
      'lat': lat,
      'lng': lng,
      'radius_km': radiusKm,
    });
  }

  // Push token endpoints
  Future<Response> registerPushToken(String fcmToken, {String? deviceType, double? latitude, double? longitude}) async {
    return _dio.post('/push-tokens', data: {
      'fcm_token': fcmToken,
      'device_type': deviceType ?? 'android',
      if (latitude != null) 'latitude': latitude,
      if (longitude != null) 'longitude': longitude,
    });
  }

  Future<Response> unsubscribePushToken(String fcmToken) async {
    return _dio.put('/push-tokens/unsubscribe', data: {'fcm_token': fcmToken});
  }

  /// Refreshes the device's stored location so nearby push targeting
  /// stays accurate while the user moves.
  Future<Response> updatePushTokenLocation({String? fcmToken, required double latitude, required double longitude}) async {
    return _dio.put('/push-tokens/location', data: {
      if (fcmToken != null) 'fcm_token': fcmToken,
      'latitude': latitude,
      'longitude': longitude,
    });
  }

  // Place endpoints
  Future<Response> getPlaceCategories() async {
    return _dio.get('/places/categories');
  }

  Future<Response> getNearbyPlaces({
    required double lat,
    required double lng,
    double radiusKm = 5.0,
    int? categoryId,
    String? search,
    int limit = 50,
  }) async {
    final queryParams = <String, dynamic>{
      'lat': lat,
      'lng': lng,
      'radius_km': radiusKm,
      'limit': limit,
    };
    if (categoryId != null) queryParams['category_id'] = categoryId;
    if (search != null && search.isNotEmpty) queryParams['search'] = search;
    return _dio.get('/places/nearby', queryParameters: queryParams);
  }

  /// Bounding box query - optimized for map viewport pan/zoom
  Future<Response> getPlacesInBBox({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    int? zoom,
    String? category,
    int limit = 500,
  }) async {
    return _dio.get('/places/bbox', queryParameters: {
      'min_lat': minLat,
      'max_lat': maxLat,
      'min_lng': minLng,
      'max_lng': maxLng,
      if (zoom != null) 'zoom': zoom,
      if (category != null) 'category': category,
      'limit': limit,
    });
  }

  Future<Response> getFeaturedPlaces({double? lat, double? lng}) async {
    final queryParams = <String, dynamic>{};
    if (lat != null) queryParams['lat'] = lat;
    if (lng != null) queryParams['lng'] = lng;
    return _dio.get('/places/featured', queryParameters: queryParams);
  }

  /// Nepal-wide places (admin + OSM + user submitted) in one lightweight
  /// payload. The backend caches this for 10 minutes (Redis), so the map
  /// opens instantly without waiting for GPS or a viewport query.
  Future<Response> getNepalPlaces({int limit = 15000}) async {
    return _dio.get('/places/all', queryParameters: {'limit': limit});
  }

  Future<Response> getCombinedNearbyPlaces({
    required double lat,
    required double lng,
    double radiusKm = 5.0,
    int? categoryId,
    String? search,
    int limit = 100,
  }) async {
    return _dio.get('/places/nearby-combined', queryParameters: {
      'lat': lat,
      'lng': lng,
      if (categoryId != null) 'category_id': categoryId,
      if (search != null && search.isNotEmpty) 'search': search,
      'radius_km': radiusKm,
      'limit': limit,
    });
  }

  /// Submit a place (used by offline sync queue).
  /// [mediaPaths] are local image files uploaded as multipart.
  Future<Response> createPlace(
    Map<String, dynamic> payload, {
    List<String>? mediaPaths,
  }) async {
    final formData = FormData.fromMap({
      ...payload,
      if (mediaPaths != null)
        for (final path in mediaPaths)
          'images[]': await MultipartFile.fromFile(path),
    });
    return _dio.post('/places', data: formData);
  }

  Future<Response> getPlaceDetails(String placeId) async {
    return _dio.get('/places/$placeId');
  }

  Future<Response> getPlaceReviews(String placeId) async {
    return _dio.get('/places/$placeId/reviews');
  }

  Future<Response> getDirections({
    required double fromLat,
    required double fromLng,
    required double toLat,
    required double toLng,
  }) {
    return _dio.get('/routing/directions', queryParameters: {
      'from_lat': fromLat,
      'from_lng': fromLng,
      'to_lat': toLat,
      'to_lng': toLng,
    });
  }

  Future<Response> addPlaceReview(
    String placeId, {
    required String title,
    required String description,
    required int rating,
    List<String>? images,
    String? osmName,
    double? osmLatitude,
    double? osmLongitude,
    String? osmCategory,
    String? osmAddress,
    String? osmDistrict,
    String? osmPhone,
  }) async {
    final data = <String, dynamic>{
      'title': title,
      'description': description,
      'rating': rating,
      'images': images ?? [],
    };
    if (osmName != null) data['name'] = osmName;
    if (osmLatitude != null) data['latitude'] = osmLatitude;
    if (osmLongitude != null) data['longitude'] = osmLongitude;
    if (osmCategory != null) data['category'] = osmCategory;
    if (osmAddress != null) data['address'] = osmAddress;
    if (osmDistrict != null) data['district'] = osmDistrict;
    if (osmPhone != null) data['phone'] = osmPhone;
    return _dio.post('/places/$placeId/reviews', data: data);
  }

  // Reports endpoints
  Future<Response> submitReport(FormData formData) async {
    return _dio.post('/reports', data: formData);
  }

  Future<Response> getReports({
    String? status,
    int? categoryId,
    String? district,
    String? search,
    double? lat,
    double? lng,
    double? radiusKm,
    String? sortBy,
    int limit = 20,
    int offset = 0,
  }) async {
    final queryParams = {
      'limit': limit,
      'offset': offset,
    } as Map<String, dynamic>;
    if (status != null) queryParams['status'] = status;
    if (categoryId != null) queryParams['category_id'] = categoryId;
    if (district != null) queryParams['district'] = district;
    if (search != null) queryParams['search'] = search;
    if (lat != null) queryParams['lat'] = lat;
    if (lng != null) queryParams['lng'] = lng;
    if (radiusKm != null) queryParams['radius_km'] = radiusKm;
    if (sortBy != null) queryParams['sort_by'] = sortBy;
    return _dio.get('/reports', queryParameters: queryParams);
  }

  Future<Response> getReportDetails(String reportId) async {
    return _dio.get('/reports/$reportId');
  }

  Future<Response> updateReport(String reportId, Map<String, dynamic> data) async {
    return _dio.put('/reports/$reportId', data: data);
  }

  Future<Response> addReportComment(String reportId, String content, {String? parentCommentId}) async {
    return _dio.post('/reports/$reportId/comments', data: {
      'content': content,
      if (parentCommentId != null) 'parent_comment_id': parentCommentId,
    });
  }

  Future<Response> reactToReport(String reportId, String reactionType) async {
    return _dio.post('/reports/$reportId/reactions', data: {
      'reaction_type': reactionType,
    });
  }

  // Road conditions
  Future<Response> getRoadConditions({
    String? district,
    String? severity,
    double? lat,
    double? lng,
    double? radiusKm,
  }) async {
    final queryParams = <String, dynamic>{};
    if (district != null) queryParams['district'] = district;
    if (severity != null) queryParams['severity'] = severity;
    if (lat != null) queryParams['lat'] = lat;
    if (lng != null) queryParams['lng'] = lng;
    if (radiusKm != null) queryParams['radius_km'] = radiusKm;
    return _dio.get('/road-conditions', queryParameters: queryParams);
  }

  // AI Assistant
  Future<Response> chatWithAssistant({
    required String message,
    double? lat,
    double? lng,
  }) async {
    return _dio.post('/assistant/chat',
        data: {
          'message': message,
          'context': {
            if (lat != null) 'lat': lat,
            if (lng != null) 'lng': lng,
          },
        },
        options: Options(receiveTimeout: const Duration(seconds: 90)));
  }

  /// Daily AI chat quota for the logged-in user (limit/used/remaining/reset_at).
  Future<Response> getAssistantQuota() async {
    return _dio.get('/assistant/quota');
  }

  // ✅ Support Conversations
  Future<Response> getSupportConversations({int page = 1}) =>
      _dio.get('/support', queryParameters: {'page': page});

  Future<Response> getSupportConversation(int id) =>
      _dio.get('/support/$id');

  Future<Response> createSupportConversation({
    required String subject,
    required String message,
    String category = 'general',
    String priority = 'normal',
  }) =>
      _dio.post('/support', data: {
        'subject': subject,
        'message': message,
        'category': category,
        'priority': priority,
      });

  Future<Response> replyToSupport(int conversationId, String message) =>
      _dio.post('/support/$conversationId/reply', data: {'message': message});

  Future<Response> getSupportSatisfaction(int conversationId) =>
      _dio.get('/support/$conversationId/satisfaction');

  Future<Response> submitSupportSatisfaction(int conversationId, {required int rating, String? comment}) =>
      _dio.post('/support/$conversationId/satisfaction', data: {
        'rating': rating,
        if (comment != null && comment.isNotEmpty) 'comment': comment,
      });

  // ✅ Profile Completion endpoints

  // Sponsors removed — replaced by ad campaigns (2026-08)

  // Reward Offer codes available for booking auto-apply
  Future<Response> getAvailableOfferCodes() => _dio.get('/offers/available');
  /// Complete the user profile with required information
  Future<Response> completeProfile({
    String? bio,
    String? avatar,
    String? phone,
  }) async {
    return _dio.post('/auth/complete-profile', data: {
      if (bio != null && bio.isNotEmpty) 'bio': bio,
      if (avatar != null) 'avatar': avatar,
      if (phone != null) 'phone': phone,
    });
  }

  /// Check the current profile completion status
  Future<Response> checkProfileStatus() async {
    return _dio.get('/auth/check-profile-status');
  }

  // ============ Profile Management ============
  
  /// Get full profile data with stats, badges, achievements
  Future<Response> getFullProfile() async {
    return _dio.get('/profile');
  }

  /// Get detailed profile stats breakdown
  Future<Response> getProfileStats() async {
    return _dio.get('/profile/stats');
  }

  /// Get all badges with unlock conditions
  Future<Response> getProfileBadges() async {
    return _dio.get('/profile/badges');
  }

  /// Get recent activity timeline
  Future<Response> getProfileActivity({int limit = 20}) async {
    return _dio.get('/profile/activity', queryParameters: {'limit': limit});
  }

  /// Update profile with specific fields
  Future<Response> updateProfileData(Map<String, dynamic> data) async {
    return _dio.put('/profile', data: data);
  }

  /// Update avatar
  Future<Response> updateProfileAvatar(String avatarUrl) async {
    return _dio.post('/profile/avatar', data: {'avatar': avatarUrl});
  }

  /// Get user settings
  Future<Response> getUserSettings() async {
    return _dio.get('/profile/settings');
  }

  /// Update user settings
  Future<Response> updateUserSettings(Map<String, dynamic> settings) async {
    return _dio.put('/profile/settings', data: settings);
  }

  /// UI translation dictionary (English -> Nepali), public
  Future<Response> getTranslations() async {
    return _dio.get('/translations');
  }

  // ============ Dynamic Profile Fields ============

  /// Get available profile field options (for dropdowns and multi-selects)
  Future<Response> getProfileFieldOptions() async {
    return _dio.get('/profile/field-options');
  }

  /// Get profile field definitions (schema for form building)
  Future<Response> getProfileFieldDefinitions() async {
    return _dio.get('/profile/field-definitions');
  }

  // ============ Ad Campaigns ============

  Future<Response> getActiveAds({
    String? adContext,
    String? district,
    String? category,
    int? limit,
    bool persistent = false,
  }) async {
    final params = <String, dynamic>{};
    if (adContext != null && adContext.isNotEmpty) params['context'] = adContext;
    if (district != null && district.isNotEmpty) params['district'] = district;
    if (category != null && category.isNotEmpty) params['category'] = category;
    if (limit != null) params['limit'] = limit;
    if (persistent) params['persistent'] = '1';
    return _dio.get('/ads/active', queryParameters: params);
  }

  Future<Response> trackAdImpression(int adCampaignId, {dynamic reportId, String? context}) {
    return _dio.post('/ads/track-impression', data: {
      'ad_campaign_id': adCampaignId,
      if (reportId != null) 'report_id': int.tryParse(reportId.toString()) ?? reportId,
      if (context != null) 'context': context,
    });
  }

  Future<Response> trackAdClick(int adCampaignId, {dynamic reportId, String? context}) {
    return _dio.post('/ads/track-click', data: {
      'ad_campaign_id': adCampaignId,
      if (reportId != null) 'report_id': int.tryParse(reportId.toString()) ?? reportId,
      if (context != null) 'context': context,
    });
  }

  Future<Response> getReportAd(dynamic reportId) {
    return _dio.get('/ads/report/${reportId}');
  }

  // ============ Subscription Plans ============

  Future<Response> getSubscriptionPlans() async {
    return _dio.get('/subscription/plans');
  }

  Future<Response> getMySubscription() async {
    return _dio.get('/subscription/my');
  }

  Future<Response> purchaseSubscription(int planId, String gateway) async {
    return _dio.post('/subscriptions/$planId/purchase', data: {'gateway': gateway});
  }

  Future<Response> verifySubscription(int planId, {String? reference}) async {
    return _dio.post('/subscriptions/$planId/verify', data: {
      if (reference != null) 'reference': reference,
    });
  }

  // ============ Reward Offers ============

  Future<Response> getOffers({String? district, String? type}) async {
    return _dio.get('/offers', queryParameters: {
      if (district != null && district.isNotEmpty) 'district': district,
      if (type != null && type.isNotEmpty) 'type': type,
    });
  }

  Future<Response> getOfferById(int id) async {
    return _dio.get('/offers/$id');
  }

  Future<Response> claimOffer(int id) async {
    return _dio.post('/offers/$id/claim');
  }

  Future<Response> getMyOffers() async {
    return _dio.get('/offers/my');
  }

  // ============ Curated Routes (trekking + itineraries) ============

  Future<Response> getRoutes({
    String? type,
    String? difficulty,
    String? search,
    bool withTrack = false,
    int limit = 50,
  }) async {
    final params = <String, dynamic>{'limit': limit};
    if (type != null && type.isNotEmpty) params['type'] = type;
    if (difficulty != null && difficulty.isNotEmpty) params['difficulty'] = difficulty;
    if (search != null && search.isNotEmpty) params['q'] = search;
    if (withTrack) params['with_track'] = 1;
    return _dio.get('/routes', queryParameters: params);
  }

  Future<Response> getRouteById(int id) async {
    return _dio.get('/routes/$id');
  }

  Future<Response> getRouteGeometry(int id) async {
    return _dio.get('/routes/$id/geometry');
  }

  // ===== Oripori Coins / Wallet =====

  Future<Response> getWallet() async {
    return _dio.get('/wallet');
  }

  Future<Response> getWalletTransactions({int limit = 20, int offset = 0}) async {
    return _dio.get('/wallet/transactions', queryParameters: {
      'limit': limit,
      'offset': offset,
    });
  }

  Future<Response> requestWithdrawal({
    required double amount,
    required String method,
    required Map<String, dynamic> accountDetails,
  }) async {
    return _dio.post('/wallet/withdraw', data: {
      'amount': amount,
      'method': method,
      'account_details': accountDetails,
    });
  }

  Future<Response> cancelWithdrawal(int id) async {
    return _dio.post('/wallet/withdraw/$id/cancel');
  }

  // ===== Partner Payments =====

  Future<Response> getPartnerList() async {
    return _dio.get('/partner-payments/partners');
  }

  Future<Response> initiatePartnerPayment({
    required int partnerId,
    required double amount,
    required String paymentMethod,
    String? description,
  }) async {
    return _dio.post('/partner-payments/initiate', data: {
      'partner_id': partnerId,
      'amount': amount,
      'payment_method': paymentMethod,
      'description': description,
    });
  }

  Future<Response> getMyPayments({int page = 1}) async {
    return _dio.get('/partner-payments/my', queryParameters: {'page': page});
  }

  Future<Response> getPartnerPaymentDetail(int paymentId) async {
    return _dio.get('/partner-payments/$paymentId');
  }
}

class AuthInterceptor extends Interceptor {
  final Dio dio;
  final SessionManager session;

  /// Mutex: only one refresh at a time. Concurrent 401s wait for the first refresh.
  Completer<String?>? _refreshCompleter;

  /// Prevents multiple concurrent redirects to the legal re-acceptance screen.
  bool _legalRedirectInProgress = false;

  AuthInterceptor(this.dio, this.session);

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    try {
      final token = await session.getAccessToken();
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
      }
    } catch (e) {
      // Token fetch failed silently
    }
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      // If the refresh endpoint itself returns 401 → refresh token is invalid
      if (err.requestOptions.path.contains('/auth/refresh')) {
        _refreshCompleter = null;
        await session.clearSession();
        handler.next(err);
        return;
      }

      // If a refresh is already in progress, wait for it instead of starting another
      if (_refreshCompleter != null) {
        final newToken = await _refreshCompleter!.future;
        if (newToken != null) {
          final retryOptions = err.requestOptions;
          retryOptions.headers['Authorization'] = 'Bearer $newToken';
          try {
            final retryResponse = await dio.fetch(retryOptions);
            handler.resolve(retryResponse);
            return;
          } catch (_) {
            // Retry failed — fall through to original error
          }
        }
        handler.next(err);
        return;
      }

      // Start a single refresh attempt
      _refreshCompleter = Completer<String?>();
      String? refreshedToken;

      try {
        final storedRefreshToken = await session.getRefreshToken();
        if (storedRefreshToken == null) {
          _refreshCompleter?.complete(null);
          _refreshCompleter = null;
          await session.clearSession();
          handler.next(err);
          return;
        }

        final response = await dio.post('/auth/refresh',
          data: {'refresh_token': storedRefreshToken},
        );
        final newToken = response.data['access_token'];
        final newRefreshToken = response.data['refresh_token'];

        if (newToken != null) {
          await session.setAccessToken(newToken);
          if (newRefreshToken != null) {
            await session.setRefreshToken(newRefreshToken);
          }
          refreshedToken = newToken;
          _refreshCompleter?.complete(newToken);

          // Retry the original request
          final retryOptions = err.requestOptions;
          retryOptions.headers['Authorization'] = 'Bearer $newToken';
          final retryResponse = await dio.fetch(retryOptions);
          handler.resolve(retryResponse);
          return;
        }

        _refreshCompleter?.complete(null);
      } catch (e) {
        // Refresh failed — only clear session if it's a 401 from refresh (token invalid)
        // Don't clear on network errors or 429 rate limits
        _refreshCompleter?.complete(null);
      } finally {
        _refreshCompleter = null;
      }

      // Only clear session if we didn't get a new token (confirming refresh is broken)
      if (refreshedToken == null) {
        // Double-check: is the refresh token actually invalid?
        // Don't clear on transient errors (network, timeout, 429)
        final isRefreshInvalid = err.response?.statusCode == 401 ||
            (err.error?.toString().contains('SocketException') != true &&
             err.error?.toString().contains('TimeoutException') != true &&
             err.response?.statusCode != 429);
        if (isRefreshInvalid) {
          await session.clearSession();
        }
      }
    }

    if (err.response?.statusCode == 403) {
      final data = err.response?.data;
      final code = data is Map ? data['code'] : null;
      final requiresLogout = data is Map && data['requires_logout'] == true;
      final isBanned = code == 'ACCOUNT_BANNED' || code == 'ACCOUNT_DELETED';

      // Handle legal re-acceptance requirement
      if (code == 'LEGAL_RE_ACCEPTANCE_REQUIRED') {
        if (!_legalRedirectInProgress && ApiClient.navigatorKey?.currentContext != null) {
          _legalRedirectInProgress = true;
          Navigator.of(ApiClient.navigatorKey!.currentContext!)
              .pushNamed('/legal-re-acceptance')
              .then((_) => _legalRedirectInProgress = false);
        }
        handler.next(err);
        return;
      }

      if (isBanned || requiresLogout) {
        await session.clearSession();
      }
      handler.next(err);
      return;
    }

    handler.next(err);
  }
}