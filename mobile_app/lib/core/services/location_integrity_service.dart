import 'dart:async';

import 'package:flutter/services.dart';

/// Server-facing status of the device location integrity check.
///
/// Mirrors the backend vocabulary sent in `location_integrity[status]`:
///   genuine           — OS said the fix is not mock, nothing contradicted it
///   mock_detected     — the OS flagged the fix as coming from a mock provider
///   cannot_determine  — detector unavailable / no fix / inconclusive
///                       (NEVER presented as "genuine")
///   unknown           — unexpected platform response
enum LocationIntegrityStatus {
  genuine('genuine'),
  mockDetected('mock_detected'),
  cannotDetermine('cannot_determine'),
  unknown('unknown');

  const LocationIntegrityStatus(this.apiValue);

  /// Value used in the report payload.
  final String apiValue;

  static LocationIntegrityStatus fromApiValue(String? value) {
    switch (value) {
      case 'genuine':
        return LocationIntegrityStatus.genuine;
      case 'mock_detected':
        return LocationIntegrityStatus.mockDetected;
      case 'cannot_determine':
        return LocationIntegrityStatus.cannotDetermine;
      default:
        return LocationIntegrityStatus.unknown;
    }
  }
}

/// Result of a single location-integrity check.
///
/// This is client-side EVIDENCE only — the Laravel backend treats every
/// field as untrusted and re-evaluates it against EXIF/capture GPS,
/// timestamps, accuracy and movement history.
class LocationIntegrityResult {
  const LocationIntegrityResult({
    required this.isMockLocation,
    required this.status,
    required this.reason,
    required this.detectionSource,
  });

  /// OS mock flag: true / false / null when the detector could not tell.
  final bool? isMockLocation;

  final LocationIntegrityStatus status;

  /// Machine-readable reason (e.g. `location_flagged_mock`, `no_location_fix`).
  final String reason;

  /// Where the verdict came from: `android`, `unavailable`, ...
  final String detectionSource;

  bool get isMock => status == LocationIntegrityStatus.mockDetected;

  /// Inconclusive detector output must never be reported as genuine.
  bool get isGenuine => status == LocationIntegrityStatus.genuine;

  /// Fallback used whenever detection fails, times out or is unsupported
  /// (e.g. iOS). Deliberately `cannot_determine`, never `genuine`.
  factory LocationIntegrityResult.inconclusive(String reason) =>
      LocationIntegrityResult(
        isMockLocation: null,
        status: LocationIntegrityStatus.cannotDetermine,
        reason: reason,
        detectionSource: 'unavailable',
      );

  /// Shape sent to the backend as `location_integrity` form fields.
  ///
  /// `mock_location_detected` is omitted when unknown — dio would serialize
  /// a null as `''`, which is easy to misread server-side. Unknown is
  /// already expressed by `status: cannot_determine`.
  Map<String, dynamic> toRequestPayload() => {
        'status': status.apiValue,
        if (isMockLocation != null) 'mock_location_detected': isMockLocation,
        'detection_source': detectionSource,
      };

  @override
  String toString() =>
      'LocationIntegrityResult(status: ${status.apiValue}, isMock: $isMockLocation, reason: $reason, source: $detectionSource)';
}

/// Thin wrapper around the native Android mock-location detector
/// (`MainActivity.kt` → MethodChannel `oripori/location_integrity`).
///
/// Contract:
///  - NEVER throws — every failure path resolves to `cannot_determine`.
///  - NEVER claims `genuine` when detection did not actually run.
///  - Bounded by a short timeout so report submission is never delayed
///    by a slow/stuck native call.
class LocationIntegrityService {
  LocationIntegrityService._();

  static final LocationIntegrityService instance = LocationIntegrityService._();

  static const MethodChannel _channel =
      MethodChannel('oripori/location_integrity');
  static const Duration _defaultTimeout = Duration(seconds: 3);

  LocationIntegrityResult? _lastResult;

  /// Most recent completed check (may still be null before the first call).
  LocationIntegrityResult? get lastResult => _lastResult;

  /// Runs (or reuses) the native detection.
  ///
  /// [force] re-runs the check even if a cached result exists — used right
  /// before submission when no check has completed yet.
  Future<LocationIntegrityResult> check({
    Duration timeout = _defaultTimeout,
    bool force = false,
  }) async {
    if (!force && _lastResult != null) return _lastResult!;

    LocationIntegrityResult result;
    try {
      final raw = await _channel
          .invokeMethod<Map<dynamic, dynamic>>('getMockLocationStatus')
          .timeout(timeout);
      result = _parse(raw);
    } on MissingPluginException {
      // iOS / desktop / non-Android: no detector available.
      result = LocationIntegrityResult.inconclusive('platform_channel_unavailable');
    } on TimeoutException {
      result = LocationIntegrityResult.inconclusive('detection_timeout');
    } on PlatformException catch (e) {
      result = LocationIntegrityResult.inconclusive('platform_error_${e.code}');
    } catch (e) {
      result = LocationIntegrityResult.inconclusive('unexpected_error');
    }

    _lastResult = result;
    return result;
  }

  LocationIntegrityResult _parse(Map<dynamic, dynamic>? raw) {
    if (raw == null || raw.isEmpty) {
      return LocationIntegrityResult.inconclusive('empty_platform_response');
    }

    final status =
        LocationIntegrityStatus.fromApiValue(raw['status'] as String?);
    final mockFlag = raw['isMockLocation'];
    final source = (raw['detection_source'] as String?) ?? _sourceFor(status);
    final reason = (raw['reason'] as String?) ?? 'unknown';

    // Cross-check: a mock_detected status must carry a true mock flag and
    // vice versa — inconsistent native output degrades to `unknown` instead
    // of trusting either field.
    final bool? isMock;
    if (mockFlag is bool) {
      isMock = mockFlag;
    } else {
      isMock = null;
    }

    if (status == LocationIntegrityStatus.mockDetected && isMock != true) {
      return LocationIntegrityResult(
        isMockLocation: isMock,
        status: LocationIntegrityStatus.unknown,
        reason: 'inconsistent_platform_response',
        detectionSource: source,
      );
    }

    return LocationIntegrityResult(
      isMockLocation: isMock,
      status: status,
      reason: reason,
      detectionSource: source,
    );
  }

  String _sourceFor(LocationIntegrityStatus status) =>
      status == LocationIntegrityStatus.genuine ||
              status == LocationIntegrityStatus.mockDetected
          ? 'android'
          : 'unavailable';

  /// Clears the cached verdict (e.g. between submissions).
  void reset() {
    _lastResult = null;
  }
}
