import 'package:flutter/material.dart';
import '../core/api/api_client.dart';

class NearbyItem {
  final int id;
  final String uuid;
  final String title;
  final String description;
  final String source; // 'alert' or 'report'
  final String alertType;
  final String severity;
  final double? latitude;
  final double? longitude;
  final String? affectedDistrict;
  final DateTime createdAt;

  NearbyItem({
    required this.id,
    required this.uuid,
    required this.title,
    required this.description,
    required this.source,
    required this.alertType,
    required this.severity,
    this.latitude,
    this.longitude,
    this.affectedDistrict,
    required this.createdAt,
  });

  factory NearbyItem.fromJson(Map<String, dynamic> json) {
    return NearbyItem(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      source: json['source'] ?? 'alert',
      alertType: json['alert_type'] ?? '',
      severity: json['severity'] ?? 'info',
      latitude: json['latitude']?.toDouble(),
      longitude: json['longitude']?.toDouble(),
      affectedDistrict: json['affected_district'],
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }

  String get severityEmoji {
    switch (severity) {
      case 'critical': return '🚧';
      case 'high': return '⚠️';
      case 'medium': return '⏳';
      case 'info': return 'ℹ️';
      default: return '📢';
    }
  }

  bool get isReport => source == 'report';
}

class AlertProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<NearbyItem> _items = [];
  bool _isLoading = false;
  String? _errorMessage;
  String? _activeFilter;
  double? _userLat;
  double? _userLng;
  int _lastSeenId = 0;

  List<NearbyItem> get items => _items;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String? get activeFilter => _activeFilter;

  int get criticalCount => _items.where((a) => a.severity == 'critical').length;
  int get highCount => _items.where((a) => a.severity == 'high').length;
  int get mediumCount => _items.where((a) => a.severity == 'medium').length;
  int get infoCount => _items.where((a) => a.severity == 'info').length;

  List<NearbyItem> get filteredItems {
    if (_activeFilter == null || _activeFilter == 'all') return _items;
    return _items.where((a) => a.severity == _activeFilter).toList();
  }

  void setLocation(double lat, double lng) {
    _userLat = lat;
    _userLng = lng;
  }

  Future<void> fetchNearby({String? severity, String? type}) async {
    if (_userLat == null || _userLng == null) return;
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.getNearbyAlerts(lat: _userLat!, lng: _userLng!);
      final data = response.data['data'] as List? ?? [];
      _items = data.map((j) => NearbyItem.fromJson(j)).toList();
      if (_items.isNotEmpty) {
        _lastSeenId = _items.first.id;
      }
    } catch (e) {
      print('❌ Failed to fetch nearby alerts: $e');
      _errorMessage = 'Failed to load alerts';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Returns newly arrived items (for SnackBar alerts)
  List<NearbyItem> checkNewItems() {
    if (_items.isEmpty) return [];
    return _items.where((i) => i.id > _lastSeenId).toList();
  }

  void updateLastSeen() {
    if (_items.isNotEmpty) {
      _lastSeenId = _items.first.id;
    }
  }

  void setFilter(String? filter) {
    _activeFilter = filter;
    notifyListeners();
  }
}
