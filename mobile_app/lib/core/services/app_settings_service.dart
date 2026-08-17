import 'package:flutter/painting.dart';
import "../../core/services/localization_service.dart";
import 'package:shared_preferences/shared_preferences.dart';

/// Local (device-only) app preferences that are not synced to the backend.
class AppSettingsService {
  static const _dataSaverKey = 'data_saver_mode';
  static const _autoDownloadKey = 'auto_download_maps';

  static bool? _dataSaver;
  static bool? _autoDownload;

  static Future<bool> get dataSaverMode async {
    _dataSaver ??= await _readBool(_dataSaverKey, false);
    return _dataSaver!;
  }

  static Future<bool> get autoDownloadMaps async {
    _autoDownload ??= await _readBool(_autoDownloadKey, true);
    return _autoDownload!;
  }

  static Future<void> setDataSaverMode(bool value) async {
    _dataSaver = value;
    await _writeBool(_dataSaverKey, value);
    applyDataSaverLimits();
  }

  static Future<void> setAutoDownloadMaps(bool value) async {
    _autoDownload = value;
    await _writeBool(_autoDownloadKey, value);
  }

  static Future<bool> _readBool(String key, bool fallback) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getBool(key) ?? fallback;
    } catch (_) {
      return fallback;
    }
  }

  static Future<void> _writeBool(String key, bool value) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool(key, value);
    } catch (_) {}
  }

  /// Data Saver: shrink the in-memory image cache so images are evicted sooner
  /// and memory stays low. Safe to call from anywhere (idempotent).
  static void applyDataSaverLimits() async {
    final enabled = await dataSaverMode;
    final cache = PaintingBinding.instance.imageCache;
    if (enabled) {
      cache.maximumSize = 100;
      cache.maximumSizeBytes = 24 * 1024 * 1024;
    } else {
      cache.maximumSize = 1000;
      cache.maximumSizeBytes = 100 * 1024 * 1024;
    }
  }
}
