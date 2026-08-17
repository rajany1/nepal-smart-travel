import 'dart:async';
import "../../core/services/localization_service.dart";
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/foundation.dart';
import 'package:flutter/painting.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:http/http.dart' as http;

import 'app_settings_service.dart';
import 'offline_db_service.dart';

/// flutter_map [TileProvider] that serves map tiles from the on-device SQLite
/// cache first and falls back to the network. Every successfully fetched tile
/// is persisted so previously visited areas keep working without internet.
///
/// Offline-first order: cache -> network (save unless Data Saver is on).
class OfflineTileProvider extends NetworkTileProvider {
  OfflineTileProvider({super.headers});

  @override
  ImageProvider getImage(TileCoordinates coordinates, TileLayer options) {
    return _CachedTileImage(
      url: getTileUrl(coordinates, options),
      fallbackUrl: getTileFallbackUrl(coordinates, options),
      headers: headers,
      coordinates: coordinates,
    );
  }
}

class _CachedTileImage extends ImageProvider<_CachedTileImage> {
  final String url;
  final String? fallbackUrl;
  final Map<String, String> headers;
  final TileCoordinates coordinates;

  const _CachedTileImage({
    required this.url,
    required this.fallbackUrl,
    required this.headers,
    required this.coordinates,
  });

  @override
  Future<_CachedTileImage> obtainKey(ImageConfiguration configuration) =>
      SynchronousFuture(this);

  @override
  ImageStreamCompleter loadImage(
    _CachedTileImage key,
    ImageDecoderCallback decode,
  ) {
    return MultiFrameImageStreamCompleter(
      codec: _load(decode),
      scale: 1,
      debugLabel: url,
    );
  }

  Future<ui.Codec> _load(ImageDecoderCallback decode) async {
    final db = OfflineDbService.instance;

    // 1) Cache hit — works offline, no network involved.
    final cached = await db.getCachedTile(
      z: coordinates.z,
      x: coordinates.x,
      y: coordinates.y,
    );
    if (cached != null) {
      return decode(await ui.ImmutableBuffer.fromUint8List(cached));
    }

    // 2) Cache miss — fetch from the network and persist for next time.
    try {
      final client = http.Client();
      try {
        var response = await client
            .get(Uri.parse(url), headers: headers)
            .timeout(const Duration(seconds: 20));
        if (response.statusCode != 200 && fallbackUrl != null) {
          response = await client
              .get(Uri.parse(fallbackUrl!), headers: headers)
              .timeout(const Duration(seconds: 20));
        }
        if (response.statusCode == 200 && response.bodyBytes.isNotEmpty) {
          final bytes = response.bodyBytes;
          if (!await AppSettingsService.dataSaverMode) {
            await db.saveTile(
              z: coordinates.z,
              x: coordinates.x,
              y: coordinates.y,
              bytes: bytes,
            );
          }
          return decode(await ui.ImmutableBuffer.fromUint8List(bytes));
        }
      } finally {
        client.close();
      }
    } catch (_) {
      // Offline / unreachable — return a transparent tile.
    }

    return decode(
      await ui.ImmutableBuffer.fromUint8List(TileProvider.transparentImage),
    );
  }

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is _CachedTileImage && other.url == url);

  @override
  int get hashCode => url.hashCode;
}

/// Background "Auto-download Maps" helper: prefetches tiles for a region so the
/// area works offline. Runs on a bounded queue and skips tiles already cached.
class OfflineTileDownloader {
  static bool _running = false;

  /// Download tiles covering [minLat/maxLat/minLng/maxLng] for zooms
  /// [minZoom..maxZoom] (defaults 8..16). Skips when Data Saver is on.
  static Future<void> downloadRegion({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    int minZoom = 8,
    int maxZoom = 16,
    Map<String, String>? headers,
  }) async {
    if (_running) return;
    if (await AppSettingsService.dataSaverMode) return;

    _running = true;
    final db = OfflineDbService.instance;
    final client = http.Client();

    try {
      for (var z = minZoom; z <= maxZoom; z++) {
        final n = 1 << z;
        final xMin = _lonToTileX(minLng, n);
        final xMax = _lonToTileX(maxLng, n);
        final yMin = _latToTileY(maxLat, n);
        final yMax = _latToTileY(minLat, n);

        for (var x = xMin; x <= xMax; x++) {
          for (var y = yMin; y <= yMax; y++) {
            if (await db.hasTile(z: z, x: x, y: y)) continue;
            final url = 'https://tile.openstreetmap.org/$z/$x/$y.png';
            try {
              final response = await client
                  .get(Uri.parse(url), headers: headers)
                  .timeout(const Duration(seconds: 15));
              if (response.statusCode == 200 && response.bodyBytes.isNotEmpty) {
                await db.saveTile(
                  z: z,
                  x: x,
                  y: y,
                  bytes: response.bodyBytes,
                );
              }
            } catch (_) {
              // Skip failed tiles; continue with the rest of the region.
            }
            await Future.delayed(const Duration(milliseconds: 60));
          }
        }
      }
    } finally {
      client.close();
      _running = false;
    }
  }

  static int _lonToTileX(double lng, int n) {
    return (((lng + 180.0) / 360.0) * n).floor().clamp(0, n - 1).toInt();
  }

  static int _latToTileY(double lat, int n) {
    final rad = lat * 3.141592653589793 / 180.0;
    final tanLat = math.tan(rad);
    final asinh = math.log(tanLat + math.sqrt(tanLat * tanLat + 1));
    final t = (1.0 - (asinh / 3.141592653589793)) / 2.0;
    return (t * n).floor().clamp(0, n - 1).toInt();
  }
}
