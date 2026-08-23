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
/// [tileType] keeps layer caches separate (e.g. 'default' vs 'satellite') so
/// satellite imagery never collides with the OSM layer's cache.
class OfflineTileProvider extends NetworkTileProvider {
  /// Cached map tiles older than this (90 days) are treated as stale and
  /// re-downloaded on the next visit. Guard against corrupt/blank responses
  /// that got persisted once and otherwise show as a gray tile forever.
  static const int tileCacheStaleAfterMs = 90 * 24 * 60 * 60 * 1000;

  /// Whether [bytes] is a plausible OSM/satellite image. OSM serves PNG
  /// (`89 50 4E 47 …`), the satellite/fallback layers serve JPEG (`FF D8 FF`).
  static bool _isPlausibleImage(Uint8List bytes) {
    if (bytes.isEmpty) return false;
    // PNG signature: 89 50 4E 47 0D 0A 1A 0A
    if (bytes.length >= 8 &&
        bytes[0] == 0x89 &&
        bytes[1] == 0x50 &&
        bytes[2] == 0x4E &&
        bytes[3] == 0x47) {
      return true;
    }
    // JPEG signature: FF D8 FF
    if (bytes.length >= 3 &&
        bytes[0] == 0xFF &&
        bytes[1] == 0xD8 &&
        bytes[2] == 0xFF) {
      return true;
    }
    return false;
  }

  OfflineTileProvider({
    super.headers,
    this.tileType = 'default',
    this.bypassNetworkCache = false,
  });

  final String tileType;

  /// When `true`, never read from or write to the on-device SQLite tile cache:
  /// every tile is fetched fresh from the network (OSM/Carto/satellite source).
  /// Use this for layers whose cached tiles are known to be blank/corrupt (e.g.
  /// OSM serving valid-but-empty PNGs) so the map always shows real tiles.
  final bool bypassNetworkCache;

  @override
  ImageProvider getImage(TileCoordinates coordinates, TileLayer options) {
    return _CachedTileImage(
      url: getTileUrl(coordinates, options),
      fallbackUrl: getTileFallbackUrl(coordinates, options),
      headers: headers,
      coordinates: coordinates,
      tileType: tileType,
      bypassNetworkCache: bypassNetworkCache,
    );
  }
}

class _CachedTileImage extends ImageProvider<_CachedTileImage> {
  final String url;
  final String? fallbackUrl;
  final Map<String, String> headers;
  final TileCoordinates coordinates;
  final String tileType;
  final bool bypassNetworkCache;

  const _CachedTileImage({
    required this.url,
    required this.fallbackUrl,
    required this.headers,
    required this.coordinates,
    required this.tileType,
    required this.bypassNetworkCache,
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

    // 1) Cache hit — works offline, no network involved. Skipped entirely when
    //    [bypassNetworkCache] is set (used for a layer whose cached tiles are
    //    blank/corrupt) so a fresh tile is always pulled from the network.
    if (!bypassNetworkCache) {
      final cached = await db.getCachedTile(
        z: coordinates.z,
        x: coordinates.x,
        y: coordinates.y,
        tileType: tileType,
        maxAgeMs: OfflineTileProvider.tileCacheStaleAfterMs,
      );
      if (cached != null && OfflineTileProvider._isPlausibleImage(cached)) {
        try {
          return decode(await ui.ImmutableBuffer.fromUint8List(cached));
        } catch (_) {
          // Corrupt/cannot-decode cached tile — fall through and re-fetch below.
          debugPrint('OSM cached tile decode failed: $coordinates');
        }
        // A bad cached tile must not block a fresh download: purge it.
        await db.clearTile(
          z: coordinates.z,
          x: coordinates.x,
          y: coordinates.y,
          tileType: tileType,
        );
      }
    }

    // 2) Cache miss — fetch from the network and persist for next time.
    //    Any failure (non-200 OR timeout/exception) falls back to
    //    [fallbackUrl] before giving up with a transparent tile.
    final client = http.Client();
    try {
      Uint8List? bytes;
      try {
        final response = await client
            .get(Uri.parse(url), headers: headers)
            .timeout(const Duration(seconds: 20));
        if (response.statusCode == 200 && response.bodyBytes.isNotEmpty) {
          bytes = response.bodyBytes;
        }
      } catch (_) {
        // Primary source unreachable — try the fallback below.
      }
      if (bytes == null && fallbackUrl != null) {
        try {
          final response = await client
              .get(Uri.parse(fallbackUrl!), headers: headers)
              .timeout(const Duration(seconds: 20));
          if (response.statusCode == 200 && response.bodyBytes.isNotEmpty) {
            bytes = response.bodyBytes;
          }
        } catch (_) {
          // Both sources unreachable — transparent tile below.
        }
      }
      if (bytes != null) {
        if (!bypassNetworkCache && !await AppSettingsService.dataSaverMode) {
          await db.saveTile(
            z: coordinates.z,
            x: coordinates.x,
            y: coordinates.y,
            tileType: tileType,
            bytes: bytes,
          );
        }
        return decode(await ui.ImmutableBuffer.fromUint8List(bytes));
      }
    } finally {
      client.close();
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

  /// Full-Nepal bounding box (same constants as the map screen).
  static const double nepalMinLat = 26.347;
  static const double nepalMaxLat = 30.447;
  static const double nepalMinLng = 80.058;
  static const double nepalMaxLng = 88.201;

  /// Download tiles covering [minLat/maxLat/minLng/maxLng] for zooms
  /// [minZoom..maxZoom] (defaults 8..16). Skips when Data Saver is on.
  /// [onProgress] reports (done, total) every few tiles; [isCancelled] is
  /// polled per tile so the user can abort a full-country download.
  static Future<void> downloadRegion({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    int minZoom = 8,
    int maxZoom = 16,
    Map<String, String>? headers,
    void Function(int done, int total)? onProgress,
    bool Function()? isCancelled,
    bool ignoreDataSaver = false,
  }) async {
    if (_running) return;
    if (!ignoreDataSaver && await AppSettingsService.dataSaverMode) return;

    _running = true;
    final db = OfflineDbService.instance;
    final client = http.Client();
    final total = regionTileCount(
      minLat: minLat,
      maxLat: maxLat,
      minLng: minLng,
      maxLng: maxLng,
      minZoom: minZoom,
      maxZoom: maxZoom,
    );
    var done = 0;

    try {
      for (var z = minZoom; z <= maxZoom; z++) {
        final n = 1 << z;
        final xMin = _lonToTileX(minLng, n);
        final xMax = _lonToTileX(maxLng, n);
        final yMin = _latToTileY(maxLat, n);
        final yMax = _latToTileY(minLat, n);

        for (var x = xMin; x <= xMax; x++) {
          for (var y = yMin; y <= yMax; y++) {
            if (isCancelled != null && isCancelled()) return;
            if (await db.hasTile(z: z, x: x, y: y)) {
              done++;
              if (done % 5 == 0) onProgress?.call(done, total);
              continue;
            }
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
            done++;
            if (done % 5 == 0) onProgress?.call(done, total);
            await Future.delayed(const Duration(milliseconds: 40));
          }
        }
      }
    } finally {
      client.close();
      _running = false;
    }
  }

  /// Downloads the full-Nepal offline pack (zooms 8..12, ~6500 tiles) and
  /// records its validity window. Cities visited later get extra detail via
  /// the auto-downloader. Explicit user action — ignores Data Saver.
  static Future<void> downloadNepalMap({
    required int validDays,
    Map<String, String>? headers,
    void Function(int done, int total)? onProgress,
    bool Function()? isCancelled,
  }) async {
    await downloadRegion(
      minLat: nepalMinLat,
      maxLat: nepalMaxLat,
      minLng: nepalMinLng,
      maxLng: nepalMaxLng,
      minZoom: 8,
      maxZoom: 12,
      headers: headers,
      onProgress: onProgress,
      isCancelled: isCancelled,
      ignoreDataSaver: true,
    );
    if (isCancelled != null && isCancelled()) return;
    await OfflineDbService.instance.saveOfflineRegion(
      name: 'nepal',
      minLat: nepalMinLat,
      maxLat: nepalMaxLat,
      minLng: nepalMinLng,
      maxLng: nepalMaxLng,
      minZoom: 8,
      maxZoom: 12,
      validDays: validDays,
    );
  }

  /// Number of tiles a region download would fetch (for progress + size UI).
  static int regionTileCount({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    required int minZoom,
    required int maxZoom,
  }) {
    var total = 0;
    for (var z = minZoom; z <= maxZoom; z++) {
      final n = 1 << z;
      total += (_lonToTileX(maxLng, n) - _lonToTileX(minLng, n) + 1) *
          (_latToTileY(minLat, n) - _latToTileY(maxLat, n) + 1);
    }
    return total;
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
