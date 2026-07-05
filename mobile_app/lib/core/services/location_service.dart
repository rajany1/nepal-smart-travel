import 'dart:async';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';

class LocationService {
  static final LocationService _instance = LocationService._();
  LocationService._();
  factory LocationService() => _instance;

  Position? _currentPosition;
  String? _currentAddress;

  Position? get currentPosition => _currentPosition;
  String? get currentAddress => _currentAddress;

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
    return Geolocator.getPositionStream(
      locationSettings: LocationSettings(
        accuracy: accuracy,
        timeLimit: Duration(milliseconds: intervalMs),
        distanceFilter: distanceFilterM.toInt(),
      ),
    );
  }

  Future<bool> isLocationServiceEnabled() async {
    return await Geolocator.isLocationServiceEnabled();
  }
}
