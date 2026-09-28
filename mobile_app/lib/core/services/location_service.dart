import 'dart:async';

import "../../core/services/localization_service.dart";
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';

import '../../config/constants/app_constants.dart';

/// Explicit device-location lifecycle — the resolver NEVER invents a
/// position and NEVER silently reuses an untrustworthy one.
///
/// Key distinctions:
///   grantedFresh      — permission granted + fresh GPS fix
///   grantedLastKnown  — permission granted, only a RECENT last-known fix
///                       (age within AppConstants.locationCacheDuration)
///   denied            — location permission denied (or denied forever)
///   serviceDisabled   — permission granted but device Location toggle is off
///   unavailable       — granted + service on, but no fix within the
///                       freshness window
///
/// Manually selected coordinates are deliberately NOT part of this state:
/// the app has no manual "pick my location" for nearby queries, and manual
/// coordinates (report/place forms) must never be presented as the device's
/// current location.
enum DeviceLocationStatus {
  grantedFresh,
  grantedLastKnown,
  denied,
  serviceDisabled,
  unavailable,
}

/// Result of [LocationService.resolveDeviceLocation] with provenance.
class DeviceLocationResult {
  const DeviceLocationResult({
    required this.status,
    this.latitude,
    this.longitude,
    this.accuracy,
    this.fixedAt,
    this.permanentlyDenied = false,
  });

  final DeviceLocationStatus status;
  final double? latitude;
  final double? longitude;
  final double? accuracy;
  final DateTime? fixedAt;

  /// True when the OS will no longer show a permission prompt
  /// (deniedForever) — the UI should deep-link to app settings instead.
  final bool permanentlyDenied;

  bool get hasCoordinates => latitude != null && longitude != null;

  bool get isUsable =>
      status == DeviceLocationStatus.grantedFresh ||
      status == DeviceLocationStatus.grantedLastKnown;
}

class LocationService {
  /// Fresh-fix age window — same 30s convention the existing
  /// fresh-fix gate in getCurrentLocation() has always used.
  static const Duration _freshAgeWindow = Duration(seconds: 30);
  static final LocationService _instance = LocationService._();
  LocationService._();
  factory LocationService() => _instance;

  Position? _currentPosition;
  String? _currentAddress;

  Position? get currentPosition => _currentPosition;
  String? get currentAddress => _currentAddress;

  /// Instant last-known fix (cached in memory or from the OS) — used so the
  /// map opens on a real spot without waiting for a fresh GPS fix.
  Future<Position?> getLastKnownPosition() async {
    if (_currentPosition != null) return _currentPosition;
    try {
      _currentPosition = await Geolocator.getLastKnownPosition();
    } catch (_) {
      _currentPosition = null;
    }
    return _currentPosition;
  }

  Future<bool> requestPermission() async {
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.deniedForever) {
      return false;
    }
    return permission == LocationPermission.always ||
        permission == LocationPermission.whileInUse;
  }

  /// Resolve the DEVICE's current location with explicit provenance.
  ///
  /// Guarantees (the "never invent a location" rule):
  ///  - permission denied           -> status `denied`, NO coordinates, and
  ///    any in-memory fix cached before a revocation is discarded;
  ///  - device Location toggle off  -> status `serviceDisabled`, NO coordinates
  ///    (an old fix is NOT silently served);
  ///  - fresh GPS                   -> status `grantedFresh`;
  ///  - only a last-known fix       -> status `grantedLastKnown` ONLY when its
  ///    age is within [AppConstants.locationCacheDuration] (the project's own
  ///    location-cache convention), otherwise status `unavailable`.
  ///
  /// [requestPermission] — set false for background callers that must never
  /// pop the system prompt (e.g. periodic intel refresh).
  /// [freshTimeout] — how long to wait for a fresh GPS fix.
  Future<DeviceLocationResult> resolveDeviceLocation({
    bool requestPermission = true,
    Duration freshTimeout = const Duration(seconds: 15),
  }) async {
    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied && requestPermission) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      // Permission missing/revoked: any previously cached fix is no longer
      // trustworthy as "current" — discard it so no caller can reuse it.
      _currentPosition = null;
      _currentAddress = null;
      return DeviceLocationResult(
        status: DeviceLocationStatus.denied,
        permanentlyDenied: permission == LocationPermission.deniedForever,
      );
    }

    if (!await Geolocator.isLocationServiceEnabled()) {
      // Permission exists but the Location toggle is off: an old fix must
      // not be served as current — there is no way to get a live fix.
      return const DeviceLocationResult(
          status: DeviceLocationStatus.serviceDisabled);
    }

    // Fresh GPS attempt (bounded — same platform limit as getCurrentLocation).
    Position? fresh;
    try {
      fresh = await Geolocator.getCurrentPosition(
        locationSettings: LocationSettings(
          accuracy: LocationAccuracy.best,
          timeLimit: freshTimeout,
        ),
      );
    } catch (_) {
      fresh = null;
    }

    if (fresh != null &&
        DateTime.now().difference(fresh.timestamp) <= _freshAgeWindow) {
      _currentPosition = fresh;
      return DeviceLocationResult(
        status: DeviceLocationStatus.grantedFresh,
        latitude: fresh.latitude,
        longitude: fresh.longitude,
        accuracy: fresh.accuracy,
        fixedAt: fresh.timestamp,
      );
    }

    // No fresh fix — consider last-known candidates (an old-timestamped fix
    // from the attempt above, the in-memory cache, and the OS cache).
    // ONLY fixes inside the freshness window qualify; expired fixes yield
    // `unavailable`, never coordinates.
    final candidates = <Position>[
      if (fresh != null) fresh,
      if (_currentPosition != null) _currentPosition!,
    ];
    final osLast = await _safeOsLastKnown();
    if (osLast != null) candidates.add(osLast);

    Position? recent;
    for (final candidate in candidates) {
      if (DateTime.now().difference(candidate.timestamp) >
          AppConstants.locationCacheDuration) {
        continue;
      }
      if (recent == null || candidate.timestamp.isAfter(recent.timestamp)) {
        recent = candidate;
      }
    }

    if (recent != null) {
      _currentPosition = recent;
      return DeviceLocationResult(
        status: DeviceLocationStatus.grantedLastKnown,
        latitude: recent.latitude,
        longitude: recent.longitude,
        accuracy: recent.accuracy,
        fixedAt: recent.timestamp,
      );
    }

    return const DeviceLocationResult(status: DeviceLocationStatus.unavailable);
  }

  Future<Position?> _safeOsLastKnown() async {
    try {
      return await Geolocator.getLastKnownPosition();
    } catch (_) {
      return null;
    }
  }

  /// Deep-link to system Location settings (for "service disabled").
  Future<void> openLocationSettings() async {
    try {
      await Geolocator.openLocationSettings();
    } catch (_) {
      // Unsupported platform — the caller can still ask the user manually.
    }
  }

  /// Deep-link to this app's system settings (for deniedForever).
  Future<void> openAppSettings() async {
    try {
      await Geolocator.openAppSettings();
    } catch (_) {
      // Unsupported platform — ignore.
    }
  }

  Future<Position?> _getLastKnownLocation() async {
    try {
      _currentPosition = await Geolocator.getLastKnownPosition();
      return _currentPosition;
    } catch (_) {
      return null;
    }
  }

  /// Get the most accurate GPS location possible.
  /// Uses the fused location provider and falls back to last known location if needed.
  Future<Position?> getCurrentLocation() async {
    try {
      final hasPermission = await requestPermission();
      if (!hasPermission) {
        return await _getLastKnownLocation();
      }

      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        return await _getLastKnownLocation();
      }

      _currentPosition ??= await _getLastKnownLocation();

      Position? current;
      try {
        current = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.best,
            timeLimit: Duration(seconds: 15),
          ),
        );
      } catch (_) {
        current = null;
      }

      if (current != null) {
        _currentPosition = current;
      }

      final now = DateTime.now();
      if (current != null) {
        final isRecent = current.timestamp != null &&
            now.difference(current.timestamp!).inSeconds <= 30;
        if (current.accuracy <= 20.0 && isRecent) {
          return current;
        }
      }

      final Position? bestPosition = current ?? _currentPosition;
      if (bestPosition == null) {
        return null;
      }

      Position best = bestPosition;
      final completer = Completer<Position?>();
      final stopwatch = Stopwatch()..start();
      const timeout = Duration(seconds: 30);

      StreamSubscription<Position>? sub;
      sub = Geolocator.getPositionStream(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.best,
          distanceFilter: 0,
          timeLimit: Duration(seconds: 10),
        ),
      ).listen((pos) {
        if (pos.timestamp == null) return;
        final isPosRecent = now.difference(pos.timestamp!).inSeconds <= 30;
        if (!isPosRecent) return;

        if (pos.accuracy < best.accuracy) {
          best = pos;
        }

        if (best.accuracy <= 20.0) {
          sub?.cancel();
          if (!completer.isCompleted) completer.complete(best);
          return;
        }

        if (stopwatch.elapsed >= timeout) {
          sub?.cancel();
          if (!completer.isCompleted) completer.complete(best);
        }
      }, onError: (err) {
        sub?.cancel();
        if (!completer.isCompleted) completer.complete(best);
      }, onDone: () {
        sub?.cancel();
        if (!completer.isCompleted) completer.complete(best);
      });

      final result = await completer.future;
      _currentPosition = result ?? _currentPosition;
      return _currentPosition;
    } catch (e) {
      return await _getLastKnownLocation();
    }
  }

  /// Get a high-accuracy position specifically for report verification.
  /// Called right before submitting a report to ensure the GPS pin is accurate.
  Future<Position?> getAccurateLocationForReport() async {
    final hasPermission = await requestPermission();
    if (!hasPermission) {
      return await _getLastKnownLocation();
    }

    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      return await _getLastKnownLocation();
    }

    Position? bestPosition;
    final completer = Completer<Position?>();
    final stopwatch = Stopwatch()..start();
    const timeout = Duration(seconds: 25);

    StreamSubscription<Position>? sub;
    sub = Geolocator.getPositionStream(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.best,
        distanceFilter: 0,
        timeLimit: Duration(seconds: 10),
      ),
    ).listen((pos) {
      bestPosition = pos;

      if (pos.accuracy <= 10.0) {
        sub?.cancel();
        if (!completer.isCompleted) completer.complete(pos);
        return;
      }

      if (stopwatch.elapsed >= timeout) {
        sub?.cancel();
        if (!completer.isCompleted) completer.complete(pos);
      }
    }, onError: (err) {
      sub?.cancel();
      if (!completer.isCompleted) completer.complete(bestPosition);
    }, onDone: () {
      sub?.cancel();
      if (!completer.isCompleted) completer.complete(bestPosition);
    });

    final result = await completer.future;
    return result ?? await _getLastKnownLocation();
  }

  Future<String?> getAddressFromCoordinates(double lat, double lng) async {
    try {
      List<Placemark> placemarks = await placemarkFromCoordinates(lat, lng);
      if (placemarks.isNotEmpty) {
        final place = placemarks.first;
        _currentAddress =
            '${place.street ?? ''}, ${place.locality ?? ''}, ${place.administrativeArea ?? ''}';
        return _currentAddress;
      }
    } catch (e) {
      print('⚠️ Geocoding failed: $e');
    }
    return null;
  }

  Future<double> calculateDistance(double startLat, double startLng, double endLat, double endLng) async {
    return Geolocator.distanceBetween(startLat, startLng, endLat, endLng);
  }

  Stream<Position> getPositionStream({
    LocationAccuracy accuracy = LocationAccuracy.bestForNavigation,
    int intervalMs = 5000,
    double distanceFilterM = 0,
  }) {
    // NOTE: no timeLimit here — geolocator applies it as a Stream.timeout()
    // which KILLS the stream when updates are slower than the limit (e.g.
    // walking with a 5m distance filter), freezing the blue dot. A live
    // tracking stream must never time out.
    return Geolocator.getPositionStream(
      locationSettings: LocationSettings(
        accuracy: accuracy,
        distanceFilter: distanceFilterM.toInt(),
      ),
    );
  }

  Future<bool> isLocationServiceEnabled() async {
    return await Geolocator.isLocationServiceEnabled();
  }
}
