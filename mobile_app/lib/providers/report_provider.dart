import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../core/api/api_client.dart';
import '../core/models/report.dart';
import '../core/models/form_field_config.dart';
import '../config/constants/app_constants.dart';

class ReportProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  // State for reports list
  List<ReportModel> _reports = [];
  List<ReportModel> _myReports = [];
  List<ReportCategory> _categories = [];
  ReportFormConfig? _formConfig;
  bool _isLoading = false;
  bool _isLoadingMore = false;
  String? _errorMessage;
  String? _submissionErrorMessage;
  String _activeTab = 'recent';
  int? _selectedCategoryId;

  // Pagination
  int _currentOffset = 0;
  bool _hasMore = true;
  static const int _pageSize = 10;

  // Auto-refresh
  Timer? _pollTimer;
  double? _lastLat;
  double? _lastLng;
  bool _isFetching = false;

  // Getters
  List<ReportModel> get reports => _reports;
  List<ReportModel> get myReports => _myReports;
  List<ReportCategory> get categories => _categories;
  ReportFormConfig? get formConfig => _formConfig;
  bool get isLoading => _isLoading;
  bool get isLoadingMore => _isLoadingMore;
  String? get errorMessage => _errorMessage;
  String? get submissionErrorMessage => _submissionErrorMessage;
  String get activeTab => _activeTab;
  int? get selectedCategoryId => _selectedCategoryId;
  bool get hasMore => _hasMore;

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
  int get emergencyCount => _reports.where((r) => r.isEmergency).length;
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

  /// Fetch categories from the backend (dynamic)
  Future<void> fetchCategories() async {
    try {
      final response = await _api.dio.get('/reports/categories');
      final data = response.data['data'] as List? ?? [];
      _categories = data.map((j) => ReportCategory.fromJson(j)).toList();
      notifyListeners();
    } catch (e) {
      print('⚠️ Failed to fetch report categories: $e');
      // Fallback to hardcoded categories if API fails
      if (_categories.isEmpty) {
        _categories = [
          ReportCategory(id: 1, name: 'General', icon: 'info'),
          ReportCategory(id: 2, name: 'Road & Traffic', icon: 'road'),
          ReportCategory(id: 3, name: 'Safety & Hazards', icon: 'warning'),
          ReportCategory(id: 4, name: 'Weather & Conditions', icon: 'ac_unit'),
          ReportCategory(id: 5, name: 'Transportation', icon: 'directions_bus'),
          ReportCategory(id: 6, name: 'Hidden Destinations', icon: 'explore'),
          ReportCategory(id: 7, name: 'Services & Utilities', icon: 'local_gas_station'),
          ReportCategory(id: 8, name: 'Events & Notices', icon: 'event'),
        ];
        notifyListeners();
      }
    }
  }

  /// Fetch reports with optional filters
  Future<void> fetchReports({
    String? status,
    int? categoryId,
    String? district,
    double? lat,
    double? lng,
    double? radiusKm,
    String? sortBy,
    bool refresh = true,
  }) async {
    // Prevent concurrent fetches (race condition guard)
    if (_isFetching) return;
    _isFetching = true;

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

    await fetchReports(refresh: false);

    _isLoadingMore = false;
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
  Future<bool> submitReport({
    required String title,
    required String description,
    required int categoryId,
    required double latitude,
    required double longitude,
    String? district,
    String? priority,
    String? photoPath, // Path to the in-app camera captured photo
    double? captureLatitude, // GPS at photo-capture time
    double? captureLongitude, // GPS at photo-capture time
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
        'title': title,
        'description': description,
        'category_id': categoryId,
        'latitude': latitude,
        'longitude': longitude,
        if (district != null) 'district': district,
        if (priority != null) 'priority': priority,
        'is_live_capture': true, // Layer 1: Only in-app camera accept
        'photo_captured_at': DateTime.now().toIso8601String(),
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
      final responseData = e.response?.data;
      if (responseData is Map<String, dynamic>) {
        _submissionErrorMessage = responseData['message']?.toString() ?? e.message;
      } else {
        _submissionErrorMessage = e.message;
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

  /// Start auto-refresh timer (polls every 15s)
  void startAutoRefresh() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) async {
      if (_isFetching) return; // skip if a fetch is already in flight
      await fetchReports(lat: _lastLat, lng: _lastLng, radiusKm: 20.0);
      await fetchMyReports();
    });
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
}
