import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../config/constants/app_constants.dart';
import '../services/session_manager.dart';

class ApiClient {
  static ApiClient? _instance;
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  final SessionManager _session = SessionManager.instance;

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
  Future<Response> socialLogin({required String idToken}) async {
    return _dio.post('/auth/social-login', data: {'id_token': idToken});
  }

  Future<Response> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    return _dio.post('/auth/register', data: {
      'name': name,
      'email': email,
      'phone': phone,
      'password': password,
      'password_confirmation': passwordConfirmation,
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

  Future<Response> verifyEmail(String otp) async {
    return _dio.post('/auth/verify-email', data: {'otp': otp});
  }

  Future<Response> resendVerificationEmail(String email) async {
    return _dio.post('/auth/resend-verification', data: {'email': email});
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

  Future<Response> getUserReputation(String userId) async {
    return _dio.get('/users/$userId/reputation');
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

  Future<Response> getNearbyAlerts({required double lat, required double lng, double radiusKm = 20}) async {
    return _dio.get('/alerts/nearby', queryParameters: {'lat': lat, 'lng': lng, 'radius_km': radiusKm});
  }

  Future<Response> createAlert(Map<String, dynamic> data) async {
    return _dio.post('/alerts', data: data);
  }

  // Push token endpoints
  Future<Response> registerPushToken(String fcmToken, {String? deviceType}) async {
    return _dio.post('/push-tokens', data: {'fcm_token': fcmToken, 'device_type': deviceType ?? 'android'});
  }

  Future<Response> unsubscribePushToken(String fcmToken) async {
    return _dio.put('/push-tokens/unsubscribe', data: {'fcm_token': fcmToken});
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
    String? category,
    int limit = 200,
  }) async {
    return _dio.get('/places/bbox', queryParameters: {
      'min_lat': minLat,
      'max_lat': maxLat,
      'min_lng': minLng,
      'max_lng': maxLng,
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

  Future<Response> getPlaceDetails(String placeId) async {
    return _dio.get('/places/$placeId');
  }

  Future<Response> getPlaceReviews(String placeId) async {
    return _dio.get('/places/$placeId/reviews');
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

  // Weather grid
  Future<Response> getWeatherGrid() async {
    return _dio.get('/weather/grid');
  }

  // Store
  Future<Response> getStoreItems() async {
    return _dio.get('/store/items');
  }

  Future<Response> purchaseItem(int itemId) async {
    return _dio.post('/store/items/$itemId/purchase');
  }

  Future<Response> getMyPurchases() async {
    return _dio.get('/store/my-purchases');
  }

  // AI Assistant
  Future<Response> chatWithAssistant({
    required String message,
    double? lat,
    double? lng,
  }) async {
    return _dio.post('/assistant/chat', data: {
      'message': message,
      'context': {
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
      },
    });
  }
  // ✅ Profile Completion endpoints

  // Partners
  Future<Response> getPartners({String? district, String? type}) async {
    final params = <String, dynamic>{};
    if (district != null) params['district'] = district;
    if (type != null) params['type'] = type;
    return _dio.get('/partners', queryParameters: params);
  }

  Future<Response> getPartnerDetail(int id) => _dio.get('/partners/$id');

  // Sponsors
  Future<Response> getSponsors() => _dio.get('/sponsors');

  // User Bookings
  Future<Response> createBooking(Map<String, dynamic> data) => _dio.post('/bookings', data: data);
  Future<Response> getMyBookings() => _dio.get('/bookings/my');
  Future<Response> removeBookingCoupon(int bookingId) => _dio.delete('/bookings/$bookingId/coupon');
  Future<Response> cancelBooking(int bookingId) => _dio.post('/bookings/$bookingId/cancel');

  // Store - Available Codes for booking auto-apply
  Future<Response> getAvailableCodes() => _dio.get('/store/my-available-codes');
  /// Complete the user profile with required information
  Future<Response> completeProfile({
    required String bio,
    String? avatar,
    String? phone,
  }) async {
    return _dio.post('/auth/complete-profile', data: {
      'bio': bio,
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

  Future<Response> getActiveAds() async {
    return _dio.get('/ads/active');
  }

  // ============ Subscription Plans ============

  Future<Response> getSubscriptionPlans() async {
    return _dio.get('/subscription/plans');
  }

  Future<Response> getMySubscription() async {
    return _dio.get('/subscription/my');
  }
}

class AuthInterceptor extends Interceptor {
  final Dio dio;
  final SessionManager session;

  AuthInterceptor(this.dio, this.session);

  @override
  Future<void> onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    try {
      final token = await session.getAccessToken();
      if (token != null) {
        options.headers['Authorization'] = 'Bearer $token';
        print('✅ Auth token attached to request: ${options.path}');
      } else {
        print('⚠️ No auth token available for request: ${options.path}');
      }
    } catch (e) {
      print('❌ Error fetching auth token: $e');
    }
    handler.next(options);
  }

  @override
  Future<void> onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      // Prevent retry loops on the refresh endpoint itself
      if (err.requestOptions.path.contains('/auth/refresh')) {
        await session.clearSession();
        handler.next(err);
        return;
      }

      final storedRefreshToken = await session.getRefreshToken();
      if (storedRefreshToken != null) {
        try {
          print('🔄 Attempting to refresh token...');
          final response = await dio.post('/auth/refresh',
            options: Options(headers: {'Authorization': 'Bearer $storedRefreshToken'}),
          );
          final newToken = response.data['access_token'];
          if (newToken != null) {
            await session.setAccessToken(newToken);
            print('✅ Token refreshed successfully');

            final retryOptions = err.requestOptions;
            retryOptions.headers['Authorization'] = 'Bearer $newToken';
            final retryResponse = await dio.fetch(retryOptions);
            handler.resolve(retryResponse);
            return;
          }
        } catch (e) {
          print('❌ Token refresh failed: $e');
        }
      }
      await session.clearSession();
    }

    if (err.response?.statusCode == 403) {
      print('🚫 Account banned or suspended — clearing session');
      await session.clearSession();
      handler.next(err);
      return;
    }

    handler.next(err);
  }
}