import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:latlong2/latlong.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Last camera + location snapshot for the Nearby map, persisted locally.
///
/// This is the *Flutter-side* half of the cache stack:
///
/// ```text
/// Flutter local cache (this file + SQLite places)  -> instant map restore
///          ↓
/// Laravel API
///          ↓
/// Redis cache (PlacesCache)                        -> cheap repeat queries
///          ↓ cache miss
/// Database
/// ```
///
/// Redis only makes repeated *network* requests cheap — it can never make the
/// map paint before the first GPS fix / API response. That is what this
/// snapshot is for: reopening Nearby restores the previous camera and the last
/// known position immediately, then fresh GPS/places arrive in the background.
class NearbyMapCache {
  NearbyMapCache._();

  static final NearbyMapCache instance = NearbyMapCache._();

  static const String _stateKey = 'nearby_map:state:v1';

  /// Snapshots older than this are still used for the camera (the user's
  /// last viewed area is useful no matter how old), but a location older
  /// than this is no longer offered as the blue-dot position.
  static const Duration _maxCameraAge = Duration(days: 7);

  SharedPreferences? _prefs;

  Future<SharedPreferences> _p() async =>
      _prefs ??= await SharedPreferences.getInstance();

  Future<NearbyMapSnapshot?> read() async {
    try {
      final prefs = await _p();
      final raw = prefs.getString(_stateKey);
      if (raw == null || raw.isEmpty) return null;
      final map = jsonDecode(raw);
      if (map is! Map<String, dynamic>) return null;
      final snapshot = NearbyMapSnapshot.fromJson(map);
      // Guard against a corrupt/partial payload: a zeroed camera would open
      // the map on an empty world, which is worse than opening fresh.
      if (snapshot.zoom <= 0 ||
          (snapshot.centerLat == 0 && snapshot.centerLng == 0)) {
        return null;
      }
      final savedAt = snapshot.savedAt;
      if (savedAt != null &&
          DateTime.now().difference(savedAt) > _maxCameraAge) {
        return null;
      }
      return snapshot;
    } catch (e) {
      debugPrint('NearbyMapCache read failed: $e');
      return null;
    }
  }

  /// Persist the camera (viewport center + zoom) and the follow flag.
  ///
  /// [location] is the device's last known position — kept separately from the
  /// camera because the camera is where the user *looked*, while the location
  /// is where the user *was*.
  Future<void> save({
    required double centerLat,
    required double centerLng,
    required double zoom,
    required bool isTracking,
    LatLng? location,
    double? accuracy,
    DateTime? fixedAt,
  }) async {
    try {
      final prefs = await _p();
      final previous = await read();
      final snapshot = NearbyMapSnapshot(
        centerLat: centerLat,
        centerLng: centerLng,
        zoom: zoom,
        isTracking: isTracking,
        locationLat: location?.latitude ?? previous?.locationLat,
        locationLng: location?.longitude ?? previous?.locationLng,
        accuracy: accuracy ?? previous?.accuracy,
        fixedAt: fixedAt ?? previous?.fixedAt,
        savedAt: DateTime.now(),
      );
      await prefs.setString(_stateKey, jsonEncode(snapshot.toJson()));
    } catch (e) {
      debugPrint('NearbyMapCache save failed: $e');
    }
  }

  Future<void> clear() async {
    try {
      final prefs = await _p();
      await prefs.remove(_stateKey);
    } catch (e) {
      debugPrint('NearbyMapCache clear failed: $e');
    }
  }
}

@immutable
class NearbyMapSnapshot {
  const NearbyMapSnapshot({
    required this.centerLat,
    required this.centerLng,
    required this.zoom,
    required this.isTracking,
    this.locationLat,
    this.locationLng,
    this.accuracy,
    this.fixedAt,
    this.savedAt,
  });

  final double centerLat;
  final double centerLng;
  final double zoom;
  final bool isTracking;
  final double? locationLat;
  final double? locationLng;
  final double? accuracy;
  final DateTime? fixedAt;
  final DateTime? savedAt;

  bool get hasLocation => locationLat != null && locationLng != null;

  /// The stored position, but only when it is recent enough to be worth
  /// showing as the blue dot (stale fixes are still fine as a camera target,
  /// they are just not presented as "you are here").
  LatLng? get freshLocation {
    if (!hasLocation) return null;
    final at = fixedAt;
    if (at != null && DateTime.now().difference(at) > const Duration(days: 1)) {
      return null;
    }
    return LatLng(locationLat!, locationLng!);
  }

  factory NearbyMapSnapshot.fromJson(Map<String, dynamic> json) {
    double? asDouble(dynamic v) =>
        v == null ? null : double.tryParse(v.toString());
    return NearbyMapSnapshot(
      centerLat: asDouble(json['cLat']) ?? 0,
      centerLng: asDouble(json['cLng']) ?? 0,
      zoom: asDouble(json['z']) ?? 0,
      isTracking: json['t'] == true,
      locationLat: asDouble(json['lLat']),
      locationLng: asDouble(json['lLng']),
      accuracy: asDouble(json['acc']),
      fixedAt: json['fixAt'] == null
          ? null
          : DateTime.tryParse(json['fixAt'].toString()),
      savedAt: json['at'] == null
          ? null
          : DateTime.tryParse(json['at'].toString()),
    );
  }

  Map<String, dynamic> toJson() => {
        'cLat': centerLat,
        'cLng': centerLng,
        'z': zoom,
        't': isTracking,
        if (locationLat != null) 'lLat': locationLat,
        if (locationLng != null) 'lLng': locationLng,
        if (accuracy != null) 'acc': accuracy,
        if (fixedAt != null) 'fixAt': fixedAt!.toIso8601String(),
        if (savedAt != null) 'at': savedAt!.toIso8601String(),
      };
}
