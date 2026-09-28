import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../core/api/api_client.dart';
import '../core/models/report.dart';
import '../core/models/form_field_config.dart';
import '../core/models/report_category.dart' as rc;
import '../config/constants/app_constants.dart';

class ReportProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  // State for reports list
  List<ReportModel> _reports = [];
  List<ReportModel> _myReports = [];
  List<ReportModel> _emergencyReports = [];
  List<rc.ReportCategory> _categories = [];
  List<rc.ReportCategoryGroup> _categoryGroups = [];
  List<rc.ReportCategory> _featuredCategories = [];
  List<rc.ReportCategoryOption> _featuredOptions = [];
  bool _isLoadingFeaturedOptions = false;
  ReportFormConfig? _formConfig;
  bool _isLoading = false;
  bool _isLoadingMore = false;
  bool _isLoadingMoreEmergency = false;
  bool _isLoadingCategories = false;
  bool _isLoadingFeatured = false;
  String? _errorMessage;
  String? _submissionErrorMessage;
  String _activeTab = 'recent';
  int? _selectedCategoryId;

  // Pagination
  int _currentOffset = 0;
  bool _hasMore = true;
  int _emergencyOffset = 0;
  bool _emergencyHasMore = true;
  static const int _pageSize = 10;

  // Auto-refresh
  Timer? _pollTimer;
  double? _lastLat;
  double? _lastLng;
  String? _searchQuery;
  bool _isFetching = false;

  // Getters
  List<ReportModel> get reports => _reports;
  List<ReportModel> get myReports => _myReports;
  List<ReportModel> get emergencyReports => _emergencyReports;
  List<rc.ReportCategory> get categories => _categories;
  List<rc.ReportCategoryGroup> get categoryGroups => _categoryGroups;
  List<rc.ReportCategory> get featuredCategories => _featuredCategories;
  List<rc.ReportCategoryOption> get featuredOptions => _featuredOptions;
  bool get isLoadingFeaturedOptions => _isLoadingFeaturedOptions;
  ReportFormConfig? get formConfig => _formConfig;
  bool get isLoading => _isLoading;
  bool get isLoadingMore => _isLoadingMore;
  bool get isLoadingMoreEmergency => _isLoadingMoreEmergency;
  bool get isLoadingCategories => _isLoadingCategories;
  bool get isLoadingFeatured => _isLoadingFeatured;
  String? get errorMessage => _errorMessage;
  String? get submissionErrorMessage => _submissionErrorMessage;
  String get activeTab => _activeTab;
  int? get selectedCategoryId => _selectedCategoryId;
  bool get hasMore => _hasMore;
  bool get emergencyHasMore => _emergencyHasMore;

  /// Location of the most recent feed fetch (used to scope refresh calls)
  double? get lastFetchLat => _lastLat;
  double? get lastFetchLng => _lastLng;

  /// Get filtered reports for the current tab
  List<ReportModel> get filteredReports {
    switch (_activeTab) {
      case 'emergency':
        return _reports.where((r) => r.isEmergency).toList();
      default:
        // Apply category filter if one is selected
        if (_selectedCategoryId != null) {
          return _reports.where((r) => r.categoryId == _selectedCategoryId).toList();
        }
        return _reports;
    }
  }

  int get totalCount => _reports.length;
  int get emergencyCount => _emergencyReports.length;
  int get myReportsCount => _myReports.length;

  // ============ Data Fetching ============

  /// Fetch dynamic form configuration from backend
  Future<void> fetchFormConfig() async {
    try {
      final response = await _api.dio.get('/reports/form-config');
      final data = response.data['data'] as Map<String, dynamic>? ?? {};
      _formConfig = ReportFormConfig.fromJson(data);
      notifyListeners();
    } catch (e) {
      print('⚠️ Failed to fetch form config: $e');
    }
  }

  /// Fetch all categories from the backend (dynamic)
  Future<void> fetchCategories() async {
    _isLoadingCategories = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.dio.get('/reports/categories/detailed');
      final data = response.data['data'] as List? ?? [];
      
      // Flatten categories from groups
      _categoryGroups = data.map((g) => rc.ReportCategoryGroup.fromJson(g)).toList();
      _categories = _categoryGroups.expand((g) => g.categories).toList();
      
      _isLoadingCategories = false;
      _errorMessage = null;
      notifyListeners();
    } catch (e) {
      print('⚠️ Failed to fetch report categories: $e');
      _isLoadingCategories = false;
      _errorMessage = 'Failed to load categories';
      // Fallback to hardcoded categories if API fails
      if (_categories.isEmpty) {
        _categories = [
          rc.ReportCategory(id: 1, name: 'General', icon: 'info', slug: 'general'),
          rc.ReportCategory(id: 2, name: 'Road & Traffic', icon: 'road', slug: 'road-traffic'),
          rc.ReportCategory(id: 3, name: 'Safety & Hazards', icon: 'warning', slug: 'safety-hazards'),
          rc.ReportCategory(id: 4, name: 'Weather & Conditions', icon: 'ac_unit', slug: 'weather-conditions'),
          rc.ReportCategory(id: 5, name: 'Transportation', icon: 'directions_bus', slug: 'transportation'),
          rc.ReportCategory(id: 6, name: 'Hidden Destinations', icon: 'explore', slug: 'hidden-destinations'),
          rc.ReportCategory(id: 7, name: 'Services & Utilities', icon: 'local_gas_station', slug: 'services-utilities'),
          rc.ReportCategory(id: 8, name: 'Events & Notices', icon: 'event', slug: 'events-notices'),
        ];
        notifyListeners();
      }
    }
  }

  /// Fetch featured/most-used categories for the initial report bottom sheet
  Future<void> fetchFeaturedCategories({int limit = 6}) async {
    _isLoadingFeatured = true;
    notifyListeners();

    try {
      final response = await _api.dio.get('/reports/categories/featured', queryParameters: {'limit': limit});
      final data = response.data['data'] as List? ?? [];
      _featuredCategories = data.map((j) => rc.ReportCategory.fromJson(j)).toList();
      _isLoadingFeatured = false;
      notifyListeners();
    } catch (e) {
      print('⚠️ Failed to fetch featured categories: $e');
      _isLoadingFeatured = false;
      // Fallback: use first few categories
      if (_featuredCategories.isEmpty && _categories.isNotEmpty) {
        _featuredCategories = _categories.take(limit).toList();
        notifyListeners();
      }
    }
  }

  /// Search categories
  Future<List<rc.ReportCategory>> searchCategories(String query, {int limit = 20}) async {
    try {
      final response = await _api.dio.get('/reports/categories/search', queryParameters: {
        'q': query,
        'limit': limit,
      });
      final data = response.data['data'] as List? ?? [];
      return data.map((j) => rc.ReportCategory.fromJson(j)).toList();
    } catch (e) {
      print('⚠️ Failed to search categories: $e');
      return [];
    }
  }

  /// Fetch featured/most-used report OPTIONS (flat, with severity) for the
  /// main "What do you want to report?" picker.
  Future<void> fetchFeaturedOptions({int limit = 9}) async {
    _isLoadingFeaturedOptions = true;
    notifyListeners();

    try {
      final response = await _api.dio.get('/reports/options/featured', queryParameters: {'limit': limit});
      final data = response.data['data'] as List? ?? [];
      _featuredOptions = data.map((j) => rc.ReportCategoryOption.fromJson(j)).toList();
      _isLoadingFeaturedOptions = false;
      notifyListeners();
    } catch (e) {
      print('⚠️ Failed to fetch featured options: $e');
      _isLoadingFeaturedOptions = false;
      notifyListeners();
    }
  }

  /// Search report OPTIONS by option/category/group name (flat results).
  Future<List<rc.ReportCategoryOption>> searchOptions(String query, {int limit = 20}) async {
    if (query.trim().isEmpty) return [];
    try {
      final response = await _api.dio.get('/reports/options/search', queryParameters: {
        'q': query.trim(),
        'limit': limit,
      });
      final data = response.data['data'] as List? ?? [];
      return data.map((j) => rc.ReportCategoryOption.fromJson(j)).toList();
    } catch (e) {
      print('⚠️ Failed to search options: $e');
      return [];
    }
  }

  /// Get category form configuration for dynamic form rendering
  Future<rc.ReportCategory?> getCategoryFormConfig(int categoryId) async {
    try {
      final response = await _api.dio.get('/reports/categories/$categoryId/form-config');
      final data = response.data['data'] as Map<String, dynamic>? ?? {};
      return rc.ReportCategory.fromJson(data);
    } catch (e) {
      print('⚠️ Failed to fetch category form config: $e');
      return null;
    }
  }

  /// Increment category usage count (call when user selects a category)
  Future<void> incrementCategoryUsage(int categoryId) async {
    try {
      // This could be a separate endpoint, for now we just track locally
      rc.ReportCategory? cat;
      try {
        cat = _categories.firstWhere((c) => c.id == categoryId);
      } catch (e) {
        cat = null;
      }
      if (cat != null) {
        // Note: usageCount is not mutable in the model, would need backend call
      }
    } catch (e) {
      print('⚠️ Failed to increment category usage: $e');
    }
  }

  /// Forget cached feed coordinates. Called when the device location becomes
  /// unavailable (permission denied / Location service off / no fresh fix) so
  /// the 60s auto-poll and the home-screen fallback stop sending stale lat/lng
  /// — the backend's existing no-location (latest nationwide) mode takes over.
  void clearLastFetchLocation() {
    _lastLat = null;
    _lastLng = null;
  }

  /// Fetch reports with optional filters
  Future<void> fetchReports({
    String? status,
    int? categoryId,
    String? district,
    String? search,
    double? lat,
    double? lng,
    double? radiusKm,
    String? sortBy,
    bool refresh = true,
  }) async {
    // Single-flight guard: WAIT for any in-flight fetch instead of silently
    // dropping this request. Dropping made pull-to-refresh a no-op whenever
    // a poll/pagination/initial fetch happened to be running — the exact
    // "refresh sometimes doesn't refresh at all" bug.
    while (_isFetching) {
      await Future<void>.delayed(const Duration(milliseconds: 40));
    }
    _isFetching = true;

    if (search != null) _searchQuery = search;

    if (refresh) {
      _isLoading = true;
      _currentOffset = 0;
      _hasMore = true;
    }
    _errorMessage = null;
    notifyListeners();

    // Track last used location for auto-refresh
    if (lat != null) _lastLat = lat;
    if (lng != null) _lastLng = lng;

    try {
      final response = await _api.dio.get('/reports', queryParameters: {
        'limit': _pageSize,
        'offset': _currentOffset,
        if (status != null) 'status': status,
        if (categoryId != null) 'category_id': categoryId,
        if (district != null) 'district': district,
        if (search != null && search.isNotEmpty) 'search': search,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (radiusKm != null) 'radius_km': radiusKm,
        if (sortBy != null) 'sort_by': sortBy,
      });

      final data = response.data['data'] as List? ?? [];
      final meta = response.data['meta'] as Map<String, dynamic>? ?? {};
      final hasMore = meta['has_more'] ?? false;

      final newReports = data.map((j) => ReportModel.fromJson(j)).toList();

      if (refresh) {
        // Deduplicate by ID to prevent any duplicates from race conditions
        final seenIds = <String>{};
        _reports = newReports.where((r) {
          if (seenIds.contains(r.id)) return false;
          seenIds.add(r.id);
          return true;
        }).toList();
      } else {
        final existingIds = _reports.map((r) => r.id).toSet();
        for (final r in newReports) {
          if (!existingIds.contains(r.id)) {
            _reports.add(r);
            existingIds.add(r.id);
          }
        }
      }
      _hasMore = hasMore;
      _currentOffset = _reports.length;
    } catch (e) {
      print('❌ Failed to fetch reports: $e');
      _errorMessage = 'Failed to load reports';
    }

    _isFetching = false;
    _isLoading = false;
    notifyListeners();
  }

  /// Fetch more reports (pagination)
  Future<void> fetchMoreReports() async {
    if (_isLoadingMore || !_hasMore) return;

    _isLoadingMore = true;
    notifyListeners();

    await fetchReports(refresh: false, search: _searchQuery);

    _isLoadingMore = false;
    notifyListeners();
  }

  /// Fetch emergency (high/critical) reports with their own pagination
  Future<void> fetchEmergencyReports({
    double? lat,
    double? lng,
    double? radiusKm,
    String? search,
    bool refresh = true,
  }) async {
    if (refresh) {
      _emergencyOffset = 0;
      _emergencyHasMore = true;
    }
    try {
      final response = await _api.dio.get('/reports', queryParameters: {
        'limit': _pageSize,
        'offset': _emergencyOffset,
        'is_emergency': true,
        if (search != null && search.isNotEmpty) 'search': search,
        if (lat != null) 'lat': lat,
        if (lng != null) 'lng': lng,
        if (radiusKm != null) 'radius_km': radiusKm,
      });

      final data = response.data['data'] as List? ?? [];
      final meta = response.data['meta'] as Map<String, dynamic>? ?? {};
      _emergencyHasMore = meta['has_more'] ?? false;

      final newReports = data.map((j) => ReportModel.fromJson(j)).toList();

      if (refresh) {
        final seenIds = <String>{};
        _emergencyReports = newReports.where((r) {
          if (seenIds.contains(r.id)) return false;
          seenIds.add(r.id);
          return true;
        }).toList();
      } else {
        final existingIds = _emergencyReports.map((r) => r.id).toSet();
        for (final r in newReports) {
          if (!existingIds.contains(r.id)) {
            _emergencyReports.add(r);
            existingIds.add(r.id);
          }
        }
      }
      _emergencyOffset = _emergencyReports.length;
    } catch (e) {
      print('❌ Failed to fetch emergency reports: $e');
    }
    notifyListeners();
  }

  /// Fetch the next page of emergency reports
  Future<void> fetchMoreEmergencyReports() async {
    if (_isLoadingMoreEmergency || !_emergencyHasMore) return;

    _isLoadingMoreEmergency = true;
    notifyListeners();

    await fetchEmergencyReports(refresh: false);

    _isLoadingMoreEmergency = false;
    notifyListeners();
  }

  /// Fetch my reports (requires auth - gracefully handles 401/403)
  Future<void> fetchMyReports() async {
    try {
      final response = await _api.dio.get('/reports/my', queryParameters: {
        'limit': 50,
        'offset': 0,
      });
      final data = response.data['data'] as List? ?? [];
      _myReports = data.map((j) => ReportModel.fromJson(j)).toList();
      notifyListeners();
    } catch (e) {
      // Gracefully handle auth errors - user may not be logged in
      print('ℹ️ Could not fetch my reports (may be unauthenticated): $e');
      // Don't set error state - just keep previous data or empty
      if (_myReports.isEmpty) {
        _myReports = [];
        notifyListeners();
      }
    }
  }

  /// Submit a new report with photo verification
  /// The photo is required (image is now required, not optional)
  ///
  /// [captureLatitude]/[captureLongitude] - GPS coordinates captured immediately
  ///   after the photo was taken. These are sent to the backend as additional
  ///   verification that the photo was actually taken at the user's location.
  ///   This is necessary because image_picker strips EXIF GPS data.
  /// Submit a report. Only `description` + live photo are strictly required;
  /// title/category/priority are optional — when omitted the backend
  /// auto-classifies them from the description.
  Future<bool> submitReport({
    String? title,
    required String description,
    int? categoryId,
    required double latitude,
    required double longitude,
    String? district,
    String? priority,
    String? photoPath, // Path to the in-app camera captured photo
    double? captureLatitude, // GPS at photo-capture time
    double? captureLongitude, // GPS at photo-capture time
    double? locationAccuracy, // Metres of the reported GPS fix
    DateTime? locationTimestamp, // Device time of the reported GPS fix
    Map<String, dynamic>? locationIntegrity, // Mock-location detection evidence
  }) async {
    if (photoPath == null) {
      _submissionErrorMessage = 'Live photo is required to submit a report.';
      notifyListeners();
      print('❌ Report submission failed: live photo is required but missing.');
      return false;
    }

    final photoFile = File(photoPath);
    if (!await photoFile.exists()) {
      _submissionErrorMessage = 'Captured photo file is missing. Please retake the photo.';
      notifyListeners();
      print('❌ Report submission failed: photo file does not exist at path $photoPath');
      return false;
    }

    try {
      _submissionErrorMessage = null;
      notifyListeners();
      final formData = <String, dynamic>{
        // title / category_id / priority are OPTIONAL: when omitted the
        // backend auto-classifies them from the description.
        if (title != null && title.isNotEmpty) 'title': title,
        'description': description,
        if (categoryId != null) 'category_id': categoryId,
        'latitude': latitude,
        'longitude': longitude,
        if (district != null) 'district': district,
        if (priority != null) 'priority': priority,
        'is_live_capture': true, // Layer 1: Only in-app camera accept
        'photo_captured_at': DateTime.now().toIso8601String(),
        if (captureLatitude != null && captureLongitude != null) ...{
          'capture_latitude': captureLatitude,
          'capture_longitude': captureLongitude,
        },
        // Location-integrity evidence (untrusted by design — the backend
        // re-evaluates these signals server-side before scoring them).
        if (locationAccuracy != null) 'location_accuracy': locationAccuracy,
        if (locationTimestamp != null)
          'location_timestamp': locationTimestamp.toIso8601String(),
        if (locationIntegrity != null)
          'location_integrity': locationIntegrity,
      };

      formData['image'] = await MultipartFile.fromFile(
        photoPath,
        filename: 'report_photo_${DateTime.now().millisecondsSinceEpoch}.jpg',
      );

      final response = await _api.dio.post(
        '/reports',
        data: FormData.fromMap(formData),
        options: Options(
          contentType: Headers.multipartFormDataContentType,
          receiveTimeout: const Duration(seconds: 30),
          sendTimeout: const Duration(seconds: 30),
        ),
      );

      // Check for GPS verification result in response
      final verificationData = response.data['data']?['gps_verification'];
      if (verificationData != null && !verificationData['verified']) {
        print('⚠️ GPS verification warning: ${verificationData['message']}');
      }

      _submissionErrorMessage = null;
      notifyListeners();
      return true;
    } on DioException catch (e) {
      if (e.response?.statusCode == 429) {
        _submissionErrorMessage = 'Too many reports. Please try again later.';
      } else {
        final responseData = e.response?.data;
        if (responseData is Map<String, dynamic>) {
          _submissionErrorMessage = responseData['message']?.toString() ?? e.message;
        } else {
          _submissionErrorMessage = e.message;
        }
      }
      notifyListeners();
      print('❌ Failed to submit report: $_submissionErrorMessage');
      return false;
    } catch (e) {
      _submissionErrorMessage = 'Failed to submit report. Please try again.';
      notifyListeners();
      print('❌ Failed to submit report: $e');
      return false;
    }
  }

  /// Refresh all data (form config + categories + reports + my reports)
  Future<void> refreshAll({double? lat, double? lng}) async {
    // Load form config, categories, and public reports first
    await Future.wait([
      fetchFormConfig(),
      fetchCategories(),
      fetchFeaturedCategories(),
      fetchFeaturedOptions(),
      fetchReports(lat: lat, lng: lng, radiusKm: 20.0),
    ]);
    // Try loading my reports separately (handles auth failure gracefully)
    await fetchMyReports();
  }

  // ============ Tab & Filter Management ============

  void setActiveTab(String tab) {
    if (_activeTab != tab) {
      _activeTab = tab;
      notifyListeners();
    }
  }

  void setCategoryFilter(int? categoryId) {
    _selectedCategoryId = categoryId;
    notifyListeners();
  }

  /// Start auto-refresh timer (polls every 60s)
  void startAutoRefresh() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 60), (_) async {
      if (_isFetching) return; // skip if a fetch is already in flight
      await _pollFetchReports();
      await fetchEmergencyReports(
          lat: _lastLat,
          lng: _lastLng,
          radiusKm: _lastLat != null ? 20.0 : null,
          refresh: true);
      await fetchMyReports();
    });
  }

  /// Poll: fetch page 1 and merge with loaded pages instead of replacing them,
  /// so items loaded via fetchMoreReports are not wiped on every poll.
  Future<void> _pollFetchReports() async {
    _isFetching = true;
    try {
      final response = await _api.dio.get('/reports', queryParameters: {
        'limit': _pageSize,
        'offset': 0,
        // Only send a nearby filter while trustworthy coordinates exist —
        // after clearLastFetchLocation() the poll degrades to the backend's
        // no-location (latest nationwide) mode instead of stale lat/lng.
        if (_lastLat != null) 'lat': _lastLat,
        if (_lastLng != null) 'lng': _lastLng,
        if (_lastLat != null) 'radius_km': 20.0,
        if (_searchQuery != null && _searchQuery!.isNotEmpty)
          'search': _searchQuery,
      });
      final data = response.data['data'] as List? ?? [];
      final fresh = data.map((j) => ReportModel.fromJson(j)).toList();
      final freshIds = fresh.map((r) => r.id).toSet();
      final older = _reports.where((r) => !freshIds.contains(r.id)).toList();
      _reports = [...fresh, ...older];
      _currentOffset = _reports.length;
      notifyListeners();
    } catch (e) {
      print('⚠️ Poll refresh failed: $e');
    }
    _isFetching = false;
  }

  /// Stop auto-refresh timer
  void stopAutoRefresh() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  /// Update a single report's reaction state locally (instant UI update)
  void updateReportReaction(String reportId, String? userReaction, int helpfulCount, int unhelpfulCount) {
    final index = _reports.indexWhere((r) => r.id == reportId);
    if (index == -1) return;

    final old = _reports[index];
    final updated = ReportModel(
      id: old.id,
      uuid: old.uuid,
      title: old.title,
      description: old.description,
      categoryId: old.categoryId,
      categoryName: old.categoryName,
      categoryIcon: old.categoryIcon,
      priority: old.priority,
      status: old.status,
      latitude: old.latitude,
      longitude: old.longitude,
      district: old.district,
      helpfulCount: helpfulCount,
      unhelpfulCount: unhelpfulCount,
      commentsCount: old.commentsCount,
      reporterName: old.reporterName,
      reporterAvatar: old.reporterAvatar,
      reporterId: old.reporterId,
      userReaction: userReaction,
      imageUrls: old.imageUrls,
      createdAt: old.createdAt,
      updatedAt: old.updatedAt,
      timeAgo: old.timeAgo,
    );
    
    _reports[index] = updated;
    notifyListeners();
  }

  Future<Map<String, dynamic>?> confirmReport(String reportId, {double? lat, double? lng, String? note}) async {
    try {
      final response = await ApiClient.instance.dio.post(
        '/reports/$reportId/confirm',
        data: {
          if (lat != null) 'latitude': lat,
          if (lng != null) 'longitude': lng,
          if (note != null) 'note': note,
        },
      );
      if (response.data['success'] == true) {
        final data = response.data['data'];
        // Update local report
        final index = _reports.indexWhere((r) => r.id == reportId);
        if (index != -1) {
          final old = _reports[index];
          _reports[index] = ReportModel(
            id: old.id,
            uuid: old.uuid,
            title: old.title,
            description: old.description,
            categoryId: old.categoryId,
            categoryName: old.categoryName,
            categoryIcon: old.categoryIcon,
            priority: old.priority,
            status: old.status,
            latitude: old.latitude,
            longitude: old.longitude,
            district: old.district,
            locationName: old.locationName,
            reportSubcategory: old.reportSubcategory,
            isActive: old.isActive,
            expiresAt: old.expiresAt,
            confirmedByCount: data['confirmed_by_count'] ?? old.confirmedByCount,
            lastConfirmedAt: DateTime.now(),
            helpfulCount: old.helpfulCount,
            unhelpfulCount: old.unhelpfulCount,
            commentsCount: old.commentsCount,
            reporterName: old.reporterName,
            reporterAvatar: old.reporterAvatar,
            reporterId: old.reporterId,
            userReaction: old.userReaction,
            imageUrls: old.imageUrls,
            createdAt: old.createdAt,
            updatedAt: old.updatedAt,
            timeAgo: old.timeAgo,
          );
          notifyListeners();
        }
        return data;
      }
    } catch (e) {
      // Silently fail
    }
    return null;
  }
}
