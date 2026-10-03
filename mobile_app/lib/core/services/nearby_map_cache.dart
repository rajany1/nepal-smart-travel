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
  static const String _placesKey = 'nearby_map:places:v1';

  /// Snapshots older than this are still used for the camera (the user's
  /// last viewed area is useful no matter how old), but a location older
  /// than this is no longer offered as the blue-dot position.
  static const Duration _maxCameraAge = Duration(days: 7);

  /// The places payload is a *rendering* shortcut, not an archive: past this
  /// age a fresh request is more useful than a stale map.
  static const Duration _maxPlacesAge = Duration(days: 3);

  /// Hard cap on how many places are kept. The bbox endpoint returns at most
  /// 500; anything larger would bloat SharedPreferences for no benefit.
  static const int _maxCachedPlaces = 500;

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

  /// Persists the last successful nearby payload together with the bbox it
  /// was fetched for, so the next open can render the same pins instantly and
  /// the map knows they describe that area.
  ///
  /// Written only after a successful response — a failed refresh never
  /// overwrites a good snapshot with nothing.
  Future<void> savePlaces(
    List<Map<String, dynamic>> places, {
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
  }) async {
    if (places.isEmpty) return;
    try {
      final prefs = await _p();
      final trimmed = places.length > _maxCachedPlaces
          ? places.sublist(0, _maxCachedPlaces)
          : places;
      await prefs.setString(
        _placesKey,
        jsonEncode({
          'at': DateTime.now().toIso8601String(),
          'nLat': minLat,
          'sLat': maxLat,
          'wLng': minLng,
          'eLng': maxLng,
          'p': trimmed,
        }),
      );
    } catch (e) {
      debugPrint('NearbyMapCache savePlaces failed: $e');
    }
  }

  /// Reads back the payload written by [savePlaces]. Returns null when there
  /// is nothing usable (never written, corrupt, or older than
  /// [_maxPlacesAge]) so the caller falls through to the normal fetch path.
  Future<NearbyPlacesSnapshot?> readPlaces() async {
    try {
      final prefs = await _p();
      final raw = prefs.getString(_placesKey);
      if (raw == null || raw.isEmpty) return null;
      final map = jsonDecode(raw);
      if (map is! Map<String, dynamic>) return null;
      final snapshot = NearbyPlacesSnapshot.fromJson(map);
      if (snapshot.places.isEmpty) return null;
      final savedAt = snapshot.savedAt;
      if (savedAt != null &&
          DateTime.now().difference(savedAt) > _maxPlacesAge) {
        return null;
      }
      return snapshot;
    } catch (e) {
      debugPrint('NearbyMapCache readPlaces failed: $e');
      return null;
    }
  }

  Future<void> clear() async {
    try {
      final prefs = await _p();
      await prefs.remove(_stateKey);
      await prefs.remove(_placesKey);
    } catch (e) {
      debugPrint('NearbyMapCache clear failed: $e');
    }
  }
}

@immutable
class NearbyPlacesSnapshot {
  const NearbyPlacesSnapshot({
    required this.places,
    required this.minLat,
    required this.maxLat,
    required this.minLng,
    required this.maxLng,
    this.savedAt,
  });

  final List<Map<String, dynamic>> places;
  final double minLat;
  final double maxLat;
  final double minLng;
  final double maxLng;
  final DateTime? savedAt;

  factory NearbyPlacesSnapshot.fromJson(Map<String, dynamic> json) {
    double asDouble(dynamic v) => double.tryParse(v.toString()) ?? 0;
    final rawPlaces = json['p'] is List ? (json['p'] as List) : const [];
    return NearbyPlacesSnapshot(
      places: rawPlaces
          .whereType<Map>()
          .map((e) => Map<String, dynamic>.from(e))
          .toList(),
      minLat: asDouble(json['nLat']),
      maxLat: asDouble(json['sLat']),
      minLng: asDouble(json['wLng']),
      maxLng: asDouble(json['eLng']),
      savedAt: json['at'] == null
          ? null
          : DateTime.tryParse(json['at'].toString()),
    );
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
