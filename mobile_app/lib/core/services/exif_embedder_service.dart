import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

/// Service that captures the device GPS location immediately after a photo is taken.
/// This is needed because image_picker strips EXIF GPS data from camera captures.
///
/// Strategy:
///   When the user captures a photo → immediately grab device GPS coordinates
///   → send those coords as form fields along with the photo in the report submission.
///
/// The backend then compares capture-time-GPS vs report-location to verify authenticity.
/// This is far more reliable than EXIF manipulation across different devices/OS versions.
class CaptureLocationService {
  double? _captureLatitude;
  double? _captureLongitude;
  DateTime? _capturedAt;

  bool get hasCaptureLocation =>
      _captureLatitude != null && _captureLongitude != null;

  double? get captureLatitude => _captureLatitude;
  double? get captureLongitude => _captureLongitude;
  DateTime? get capturedAt => _capturedAt;

  /// Immediately after a photo is taken, grab the current GPS position.
  /// This captures the user's actual location at the moment of photo capture.
  ///
  /// Returns true if location was successfully captured, false otherwise.
  Future<bool> captureLocationAfterPhoto() async {
    try {
      // Get precise GPS right after photo capture
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.best,
          timeLimit: Duration(seconds: 5),
        ),
      );

      _captureLatitude = position.latitude;
      _captureLongitude = position.longitude;
      _capturedAt = DateTime.now();

      debugPrint('📍 [CaptureLocationService] GPS captured at photo time: '
          '$_captureLatitude, $_captureLongitude');
      return true;
    } catch (e) {
      debugPrint('⚠️ [CaptureLocationService] Failed to get GPS after photo: $e');
      return false;
    }
  }

  /// Clear the stored capture location (e.g., after successful submission or cancellation).
  void clear() {
    _captureLatitude = null;
    _captureLongitude = null;
    _capturedAt = null;
  }
}
