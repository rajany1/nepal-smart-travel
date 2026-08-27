import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart' as ll;

import '../api/api_client.dart';
import 'local_notification_service.dart';
import 'location_service.dart';

/// One nearby alert/report worth warning the user about.
class ProximityAlertItem {
  final String key;
  final String title;
  final String body;
  final double latitude;
  final double longitude;
  final String severity;

  ProximityAlertItem({
    required this.key,
    required this.title,
    required this.body,
    required this.latitude,
    required this.longitude,
    required this.severity,
  });

  factory ProximityAlertItem.fromJson(Map<String, dynamic> j) {
    return ProximityAlertItem(
      key: '${j['source'] ?? 'alert'}-${j['id']}',
      title: (j['title'] ?? '').toString(),
      body: (j['description'] ?? '').toString(),
      latitude: double.tryParse(j['latitude']?.toString() ?? '') ?? 0,
      longitude: double.tryParse(j['longitude']?.toString() ?? '') ?? 0,
      severity: (j['severity'] ?? 'info').toString(),
    );
  }

  String get severityEmoji => switch (severity) {
        'critical' => '🚨',
        'high' => '⚠️',
        'medium' => '⏳',
        _ => 'ℹ️',
      };
}

/// Client-side proximity engine.
///
/// While the user navigates a chosen route, this service keeps a fresh
/// list of alerts + approved reports around them (5 km) and fires a local
/// notification (+ [onProximityAlert] callback for an in-app banner)
/// whenever they come within [notifyThresholdMeters] of one. Per-item
/// cooldown prevents notification spam on repeated passes.
class ProximityAlertService {
  static final ProximityAlertService instance = ProximityAlertService._();

  ProximityAlertService._();

  /// Radius used when fetching alert/report candidates from the backend.
  static const double fetchRadiusKm = 5.0;

  /// Distance from an alert at which the user gets warned.
  static const double notifyThresholdMeters = 500.0;

  /// How often the candidate list is refreshed while monitoring.
  static const Duration refreshInterval = Duration(seconds: 60);

  /// Minimum time between two notifications for the same item.
  static const Duration perItemCooldown = Duration(minutes: 15);

  /// Called on the UI side to show an in-app banner/haptic.
  void Function(ProximityAlertItem item)? onProximityAlert;

  final ApiClient _api = ApiClient.instance;
  final LocationService _locationService = LocationService();
  final ll.Distance _distance = const ll.Distance();

  StreamSubscription<Position>? _positionSub;
  Timer? _refreshTimer;
  final Map<String, ProximityAlertItem> _items = {};
  final Map<String, DateTime> _lastNotifiedAt = {};
  bool _monitoring = false;

  bool get isMonitoring => _monitoring;
  List<ProximityAlertItem> get items => _items.values.toList(growable: false);

  /// Begin monitoring. Safe to call repeatedly; only the first call
  /// subscribes. Intended to be started when the user begins following a
  /// route on the map.
  void startNavigationMonitoring() {
    if (_monitoring) return;
    _monitoring = true;
    unawaited(_refresh());
    _refreshTimer = Timer.periodic(refreshInterval, (_) => unawaited(_refresh()));
    _positionSub = _locationService
        .getPositionStream(intervalMs: 3000, distanceFilterM: 15)
        .listen(
      (position) {
        _checkProximity(position);
        // Opportunistically refresh sooner than the timer when the user has
        // clearly moved away from the last fetch origin.
        unawaited(_maybeRefresh(position));
      },
      onError: (e) => debugPrint('Proximity position stream error: $e'),
    );
    debugPrint('ProximityAlertService: monitoring started');
  }

  void stopNavigationMonitoring() {
    if (!_monitoring) return;
    _monitoring = false;
    _positionSub?.cancel();
    _positionSub = null;
    _refreshTimer?.cancel();
    _refreshTimer = null;
    _items.clear();
    debugPrint('ProximityAlertService: monitoring stopped');
  }

  DateTime? _lastFetchAt;
  double? _lastFetchLat;
  double? _lastFetchLng;

  /// Re-fetch early if we moved >2 km from the last fetch origin,
  /// otherwise wait for the periodic timer.
  Future<void> _maybeRefresh(Position pos) async {
    final movedFar = _lastFetchLat != null &&
        _lastFetchLng != null &&
        Geolocator.distanceBetween(pos.latitude, pos.longitude,
                _lastFetchLat!, _lastFetchLng!) >
        2000;
    final cooledDown = _lastFetchAt == null ||
        DateTime.now().difference(_lastFetchAt!) >
            const Duration(seconds: 20);
    if (movedFar && cooledDown) {
      await _refresh();
    }
  }

  Future<void> _refresh() async {
    try {
      final pos = await _locationService.getLastKnownPosition();
      if (pos == null) return;
      _lastFetchLat = pos.latitude;
      _lastFetchLng = pos.longitude;
      _lastFetchAt = DateTime.now();

      final response = await _api.getNearbyAlerts(
        lat: pos.latitude,
        lng: pos.longitude,
        radiusKm: fetchRadiusKm,
      );
      final data = response.data['data'] as List? ?? [];
      final next = <String, ProximityAlertItem>{};
      for (final j in data) {
        final item = ProximityAlertItem.fromJson(j as Map<String, dynamic>);
        if (item.latitude == 0 && item.longitude == 0) continue;
        next[item.key] = item;
      }
      _items
        ..clear()
        ..addAll(next);
    } catch (e) {
      debugPrint('Proximity alert refresh failed: $e');
    }
  }

  void _checkProximity(Position pos) {
    if (_items.isEmpty) return;
    for (final item in _items.values) {
      final dist = _distance.as(
        ll.LengthUnit.Meter,
        ll.LatLng(pos.latitude, pos.longitude),
        ll.LatLng(item.latitude, item.longitude),
      );
      if (dist > notifyThresholdMeters) continue;

      final last = _lastNotifiedAt[item.key];
      if (last != null &&
          DateTime.now().difference(last) < perItemCooldown) {
        continue;
      }
      _lastNotifiedAt[item.key] = DateTime.now();

      LocalNotificationService.instance.showProximityAlert(
        id: item.key.hashCode & 0x7fffffff,
        title: '${item.severityEmoji} ${item.title}',
        body: '${item.body.isEmpty ? 'Alert area ahead' : item.body}\n'
            '~${dist.round()} m ahead',
        payload: item.key,
      );
      onProximityAlert?.call(item);
    }
  }
}
