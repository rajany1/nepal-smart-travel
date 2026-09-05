import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/services/location_service.dart';

class SosAlert {
  final int id;
  final String status;
  final String emergencyType;
  final String? message;
  final double latitude;
  final double longitude;
  final double? locationAccuracy;
  final DateTime? startedAt;
  final DateTime? lastLocationUpdateAt;
  final DateTime? resolvedAt;
  final int? durationSeconds;
  final int? contactsNotified;
  final String? userName;
  final String? userAvatar;
  final String? userPhone;
  final double? distanceKm;

  SosAlert({
    required this.id,
    required this.status,
    required this.emergencyType,
    this.message,
    required this.latitude,
    required this.longitude,
    this.locationAccuracy,
    this.startedAt,
    this.lastLocationUpdateAt,
    this.resolvedAt,
    this.durationSeconds,
    this.contactsNotified,
    this.userName,
    this.userAvatar,
    this.userPhone,
    this.distanceKm,
  });

  factory SosAlert.fromJson(Map<String, dynamic> json) {
    double parseDouble(dynamic v) {
      if (v == null) return 0.0;
      if (v is num) return v.toDouble();
      if (v is String) return double.tryParse(v) ?? 0.0;
      return 0.0;
    }

    int? parseInt(dynamic v) {
      if (v == null) return null;
      if (v is int) return v;
      if (v is num) return v.round();
      if (v is String) return int.tryParse(v);
      return null;
    }

    return SosAlert(
      id: json['id'] ?? 0,
      status: json['status'] ?? 'active',
      emergencyType: json['emergency_type'] ?? 'other',
      message: json['message'],
      latitude: parseDouble(json['latitude']),
      longitude: parseDouble(json['longitude']),
      locationAccuracy: parseDouble(json['location_accuracy']),
      startedAt: json['started_at'] != null ? DateTime.tryParse(json['started_at'].toString()) : null,
      lastLocationUpdateAt: json['last_location_update_at'] != null
          ? DateTime.tryParse(json['last_location_update_at'].toString())
          : null,
      resolvedAt: json['resolved_at'] != null ? DateTime.tryParse(json['resolved_at'].toString()) : null,
      durationSeconds: parseInt(json['duration_seconds']),
      contactsNotified: parseInt(json['contacts_notified']),
      userName: json['user_name'],
      userAvatar: json['user_avatar'],
      userPhone: json['user_phone'],
      distanceKm: parseDouble(json['distance_km']),
    );
  }

  bool get isActive => status == 'active';
}

class EmergencyContact {
  final int id;
  final String name;
  final String? phoneNumber;
  final int? contactUserId;
  final String? relationship;
  final bool isVerified;
  final bool isActive;
  final String? contactUserName;
  final String? contactUserAvatar;
  final String? contactUserPhone;

  EmergencyContact({
    required this.id,
    required this.name,
    this.phoneNumber,
    this.contactUserId,
    this.relationship,
    required this.isVerified,
    required this.isActive,
    this.contactUserName,
    this.contactUserAvatar,
    this.contactUserPhone,
  });

  factory EmergencyContact.fromJson(Map<String, dynamic> json) {
    return EmergencyContact(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      phoneNumber: json['phone_number'],
      contactUserId: json['contact_user_id'],
      relationship: json['relationship'],
      isVerified: json['is_verified'] ?? false,
      isActive: json['is_active'] ?? true,
      contactUserName: json['contact_user']?['name'],
      contactUserAvatar: json['contact_user']?['avatar'],
      contactUserPhone: json['contact_user']?['phone'],
    );
  }
}

class SosProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;
  final LocationService _locationService = LocationService();

  SosAlert? _activeSos;
  List<SosAlert> _nearbySos = [];
  List<SosAlert> _inboxSos = [];
  List<EmergencyContact> _contacts = [];
  bool _isLoading = false;
  bool _isActivating = false;
  String? _error;
  StreamSubscription? _gpsSubscription;
  Timer? _locationUpdateTimer;

  SosAlert? get activeSos => _activeSos;
  List<SosAlert> get nearbySos => _nearbySos;
  List<SosAlert> get inboxSos => _inboxSos;
  List<EmergencyContact> get contacts => _contacts;
  bool get isLoading => _isLoading;
  bool get isActivating => _isActivating;
  String? get error => _error;
  bool get hasActiveSos => _activeSos?.isActive ?? false;

  Future<void> checkActiveSos() async {
    try {
      final response = await _api.dio.get('/sos/active');
      if (response.data['success'] == true && response.data['data'] != null) {
        _activeSos = SosAlert.fromJson(response.data['data']);
        if (_activeSos!.isActive) _startLocationTracking();
        notifyListeners();
      }
    } catch (_) {}
  }

  /// SOS alerts sent by people who listed the current user as an emergency
  /// contact — lets contacts learn about alerts they missed while offline.
  Future<void> fetchSosForMe() async {
    try {
      final response = await _api.dio.get('/sos/for-me');
      if (response.data['success'] == true && response.data['data'] != null) {
        _inboxSos = (response.data['data'] as List)
            .map((j) => SosAlert.fromJson(j))
            .toList();
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<bool> activateSos({
    String emergencyType = 'other',
    String? message,
  }) async {
    _isActivating = true;
    _error = null;
    notifyListeners();

    try {
      final pos = await _locationService.getCurrentLocation();
      if (pos == null) {
        _error = 'Could not get your location. Please enable GPS.';
        _isActivating = false;
        notifyListeners();
        return false;
      }

      final response = await _api.dio.post('/sos', data: {
        'latitude': pos.latitude,
        'longitude': pos.longitude,
        'location_accuracy': pos.accuracy,
        'emergency_type': emergencyType,
        'message': message,
      });

      if (response.data['success'] == true) {
        _activeSos = SosAlert.fromJson(response.data['data']);
        _startLocationTracking();
        _isActivating = false;
        notifyListeners();
        return true;
      }

      _error = response.data['message'] ?? 'Failed to activate SOS.';
      _isActivating = false;
      notifyListeners();
      return false;
    } catch (e) {
      _error = _friendlyError(e, 'Network error. Please try again.');
      _isActivating = false;
      notifyListeners();
      return false;
    }
  }

  String? resolveMessage;

  /// Maps a Dio/network exception to a clear, user-friendly message so the
  /// real cause (timeout, connectivity, auth, rate-limit, server error)
  /// surfaces instead of a generic "Network error."
  String _friendlyError(Object e, String fallback) {
    if (e is DioException) {
      final data = e.response?.data;
      final msg = data is Map ? data['message'] : null;
      final serverMsg = (msg is String && msg.isNotEmpty) ? msg : null;

      switch (e.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return 'Connection timed out. Make sure your phone and the server are on the same network, and that the server is running.';
        case DioExceptionType.connectionError:
          return 'Cannot reach server. Check your internet connection.';
        case DioExceptionType.badCertificate:
          return 'Security certificate error.';
        case DioExceptionType.cancel:
          return 'Request cancelled.';
        case DioExceptionType.badResponse:
          final code = e.response?.statusCode ?? 0;
          if (code == 429) {
            return serverMsg ?? 'Too many SOS requests. Please wait and try again.';
          }
          if (code == 401 || code == 403) {
            return serverMsg ?? 'Session expired or not allowed. Please log in again.';
          }
          if (code >= 500) {
            return serverMsg ?? 'Server error. Please try again later.';
          }
          return serverMsg ?? 'Request failed (HTTP $code). Please try again.';
        default:
          break;
      }
    }

    final eStr = e.toString();
    if (eStr.contains('SocketException') ||
        eStr.contains('Failed host lookup') ||
        eStr.contains('Connection refused')) {
      return 'Cannot reach server. Check your internet connection.';
    }
    return fallback;
  }

  Future<bool> resolveSos() async {
    if (_activeSos == null) return false;

    try {
      final response = await _api.dio.post('/sos/${_activeSos!.id}/resolve');
      if (response.data['success'] == true) {
        _activeSos = SosAlert.fromJson(response.data['data']);
        resolveMessage = response.data['message'];
        _stopLocationTracking();
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      _error = _friendlyError(e, 'Failed to resolve SOS. Please try again.');
      notifyListeners();
      return false;
    }
  }

  /// Cancel an active SOS without applying a false-alarm strike.
  Future<bool> cancelSos() async {
    if (_activeSos == null) return false;

    try {
      final response = await _api.dio.post('/sos/${_activeSos!.id}/cancel');
      if (response.data['success'] == true) {
        _activeSos = SosAlert.fromJson(response.data['data']);
        resolveMessage = response.data['message'];
        _stopLocationTracking();
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      _error = _friendlyError(e, 'Failed to cancel SOS. Please try again.');
      notifyListeners();
      return false;
    }
  }

  void _startLocationTracking() {
    _stopLocationTracking();
    _gpsSubscription = _locationService.getPositionStream(
      distanceFilterM: 25,
    ).listen((pos) {
      _updateSosLocation(pos.latitude, pos.longitude, pos.accuracy);
    });
  }

  void _stopLocationTracking() {
    _gpsSubscription?.cancel();
    _gpsSubscription = null;
    _locationUpdateTimer?.cancel();
    _locationUpdateTimer = null;
  }

  Future<void> _updateSosLocation(double lat, double lng, double accuracy) async {
    if (_activeSos == null || !_activeSos!.isActive) return;
    try {
      await _api.dio.patch('/sos/${_activeSos!.id}/location', data: {
        'latitude': lat,
        'longitude': lng,
        'location_accuracy': accuracy,
      });
      _activeSos = SosAlert(
        id: _activeSos!.id,
        status: _activeSos!.status,
        emergencyType: _activeSos!.emergencyType,
        message: _activeSos!.message,
        latitude: lat,
        longitude: lng,
        locationAccuracy: accuracy,
        startedAt: _activeSos!.startedAt,
        lastLocationUpdateAt: DateTime.now(),
        resolvedAt: _activeSos!.resolvedAt,
        durationSeconds: _activeSos!.durationSeconds,
        contactsNotified: _activeSos!.contactsNotified,
        userName: _activeSos!.userName,
        userAvatar: _activeSos!.userAvatar,
        userPhone: _activeSos!.userPhone,
      );
      notifyListeners();
    } catch (_) {}
  }

  Future<void> fetchNearbySos(double lat, double lng, {double radiusKm = 5}) async {
    try {
      final response = await _api.dio.get('/sos/nearby', queryParameters: {
        'lat': lat,
        'lng': lng,
        'radius_km': radiusKm,
      });
      if (response.data['success'] == true) {
        _nearbySos = (response.data['data'] as List)
            .map((j) => SosAlert.fromJson(j))
            .toList();
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<void> fetchContacts() async {
    try {
      final response = await _api.dio.get('/emergency-contacts');
      if (response.data['success'] == true) {
        _contacts = (response.data['data'] as List)
            .map((j) => EmergencyContact.fromJson(j))
            .toList();
        notifyListeners();
      }
    } catch (_) {}
  }

  Future<bool> addContact({
    required String name,
    String? phoneNumber,
    int? contactUserId,
    String? relationship,
  }) async {
    try {
      final response = await _api.dio.post('/emergency-contacts', data: {
        'name': name,
        'phone_number': phoneNumber,
        'contact_user_id': contactUserId,
        'relationship': relationship,
      });
      if (response.data['success'] == true) {
        await fetchContacts();
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> updateContact(int id, Map<String, dynamic> data) async {
    try {
      final response = await _api.dio.patch('/emergency-contacts/$id', data: data);
      if (response.data['success'] == true) {
        await fetchContacts();
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  Future<bool> deleteContact(int id) async {
    try {
      final response = await _api.dio.delete('/emergency-contacts/$id');
      if (response.data['success'] == true) {
        _contacts.removeWhere((c) => c.id == id);
        notifyListeners();
        return true;
      }
      return false;
    } catch (_) {
      return false;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  Future<Map<String, dynamic>?> checkPhone(String phone) async {
    try {
      final response = await _api.dio.get('/emergency-contacts/check-phone/$phone');
      if (response.data['success'] == true) {
        return response.data;
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  Future<String?> reportFalseSos(int sosId, {String reason = 'false_alarm', String? description}) async {
    try {
      final response = await _api.dio.post('/sos/$sosId/report-false', data: {
        'reason': reason,
        'description': description,
      });
      if (response.data['success'] == true) {
        return response.data['message'];
      }
      return null;
    } catch (_) {
      return null;
    }
  }

  @override
  void dispose() {
    _stopLocationTracking();
    super.dispose();
  }
}
