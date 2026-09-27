import 'dart:async';
import 'dart:convert';
import 'dart:isolate';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../api/api_client.dart';
import '../../config/constants/app_constants.dart';

/// Decode JSON in a background isolate so main thread is never blocked.
Future<Map<String, dynamic>> _decodeJson(String json) async {
  return Isolate.run(() => jsonDecode(json) as Map<String, dynamic>);
}

class NepalBoundaryService {
  static final NepalBoundaryService instance = NepalBoundaryService._();
  NepalBoundaryService._();

  /// Camera may pan this far (km) outside Nepal's actual border.
  static const double cameraBufferKm = 30.0;

  List<LatLng>? _nepalBoundary;
  List<List<LatLng>>? _provinces;
  List<List<LatLng>>? _districts;
  Map<String, String>? _provinceNames;
  Map<String, String>? _districtNames;

  // Cached polygon builders (avoid rebuild every frame)
  List<Polygon>? _cachedMask;
  List<Polygon>? _cachedProvincePolygons;
  List<Polygon>? _cachedDistrictPolygons;
  List<Marker>? _cachedDistrictLabels;

  bool _loading = false;
  bool get isLoaded => _nepalBoundary != null;
  bool get isLoading => _loading;

  // Callbacks for when data finishes loading
  final List<VoidCallback> _onLoadedCallbacks = [];

  void onLoaded(VoidCallback cb) => _onLoadedCallbacks.add(cb);
  void removeOnLoaded(VoidCallback cb) => _onLoadedCallbacks.remove(cb);

  void _notifyLoaded() {
    for (final cb in _onLoadedCallbacks) {
      cb();
    }
  }

  /// Load from Redis-cached API. Falls back to asset if API fails.
  Future<void> load() async {
    if (_nepalBoundary != null || _loading) return;
    _loading = true;

    try {
      final apiBase = _getApiBase();

      // Try API first (Redis cached on server)
      if (apiBase != null) {
        await _loadFromApi(apiBase);
      }
    } catch (e) {
      debugPrint('[NepalBoundary] API load failed, trying assets: $e');
    }

    // Fallback to bundled assets if API didn't work
    if (_nepalBoundary == null) {
      try {
        await _loadFromAssets();
      } catch (e) {
        debugPrint('[NepalBoundary] Asset load also failed: $e');
      }
    }

    _loading = false;

    if (isLoaded) {
      _notifyLoaded();
    }
  }

  Future<void> _loadFromApi(String apiBase) async {
    final client = ApiClient.instance.dio;

    // Single call instead of 3 parallel calls — faster, less network overhead
    final response = await client.get('/map/all');
    final data = response.data;

    if (data is! Map<String, dynamic>) {
      throw Exception('Unexpected /map/all response type');
    }

    final boundaryData = data['boundary'];
    final provincesData = data['provinces'];
    final districtsData = data['districts'];

    if (boundaryData == null && provincesData == null && districtsData == null) {
      throw Exception('No map data in /map/all response');
    }

    // Parse each in background isolate
    final results = await Future.wait([
      _decodeJson(boundaryData is String ? boundaryData : jsonEncode(boundaryData)),
      _decodeJson(provincesData is String ? provincesData : jsonEncode(provincesData)),
      _decodeJson(districtsData is String ? districtsData : jsonEncode(districtsData)),
    ]);

    _parseBoundary(results[0]);
    _parseProvinces(results[1]);
    _parseDistricts(results[2]);
  }

  Future<void> _loadFromAssets() async {
    // Assets removed — all boundary data served from Redis via API.
    // Device must have network connection at least once after install.
    debugPrint('[NepalBoundary] No boundary data — network required for first load');
  }

  void _parseBoundary(Map<String, dynamic> data) {
    final coords = data['features'][0]['geometry']['coordinates'][0];
    _nepalBoundary = coords.map<LatLng>((c) => LatLng(c[1], c[0])).toList();
    _invalidateCache();
  }

  void _parseProvinces(Map<String, dynamic> data) {
    _provinces = [];
    _provinceNames = {};
    int i = 0;
    for (final f in data['features']) {
      final geom = f['geometry'];
      final rings = _extractRings(geom);
      // New GeoJSON uses 'name', old uses 'shapeName'
      final name = f['properties']['name'] ??
          f['properties']['shapeName'] ??
          f['properties']['NAME'] ??
          '';
      for (final ring in rings) {
        _provinces!.add(ring);
        _provinceNames!['$i'] = name;
        i++;
      }
    }
    _invalidateCache();
  }

  void _parseDistricts(Map<String, dynamic> data) {
    _districts = [];
    _districtNames = {};
    int i = 0;
    for (final f in data['features']) {
      final geom = f['geometry'];
      final rings = _extractRings(geom);
      // New GeoJSON uses 'DISTRICT', old uses 'shapeName'
      final name = f['properties']['DISTRICT'] ??
          f['properties']['shapeName'] ??
          f['properties']['NAME'] ??
          '';
      for (final ring in rings) {
        _districts!.add(ring);
        _districtNames!['$i'] = name;
        i++;
      }
    }
    _invalidateCache();
  }

  List<List<LatLng>> _extractRings(Map geom) {
    if (geom['type'] == 'MultiPolygon') {
      return (geom['coordinates'] as List).map<List<LatLng>>((poly) {
        return (poly[0] as List).map<LatLng>((c) => LatLng(c[1], c[0])).toList();
      }).toList();
    } else {
      return [(geom['coordinates'][0] as List).map<LatLng>((c) => LatLng(c[1], c[0])).toList()];
    }
  }

  void _invalidateCache() {
    _cachedMask = null;
    _cachedProvincePolygons = null;
    _cachedDistrictPolygons = null;
    _cachedDistrictLabels = null;
    _cameraBounds = null;
    _tileIntersectPoly = null;
    _polyCentroid = null;
  }

  // ── Public getters ────────────────────────────────────────────────

  List<LatLng> get nepalBoundary => _nepalBoundary ?? [];
  List<List<LatLng>> get provinces => _provinces ?? [];
  List<List<LatLng>> get districts => _districts ?? [];
  Map<String, String> get provinceNames => _provinceNames ?? {};
  Map<String, String> get districtNames => _districtNames ?? {};

  LatLngBounds get nepalBounds {
    if (_nepalBoundary == null || _nepalBoundary!.isEmpty) {
      return LatLngBounds(LatLng(26.0, 79.5), LatLng(31.0, 89.0));
    }
    return LatLngBounds.fromPoints(_nepalBoundary!);
  }

  /// Nepal bounds expanded by [cameraBufferKm] for camera containment.
  /// Cached after first computation — the buffer constant never changes.
  LatLngBounds? _cameraBounds;
  LatLngBounds get cameraBounds {
    if (_cameraBounds != null) return _cameraBounds!;
    final b = nepalBounds;
    // Approx degrees per km at Nepal's mid-latitude (~28.4°N)
    const double kmPerDegLat = 1.0 / 111.32;
    const double kmPerDegLng = 1.0 / (111.32 * 0.8799);
    final dLat = cameraBufferKm * kmPerDegLat;
    final dLng = cameraBufferKm * kmPerDegLng;
    _cameraBounds = LatLngBounds(
      LatLng(b.southWest.latitude - dLat, b.southWest.longitude - dLng),
      LatLng(b.northEast.latitude + dLat, b.northEast.longitude + dLng),
    );
    return _cameraBounds!;
  }

  /// Whether tile at [z]/[x]/[y] intersects the Nepal polygon.
  /// Uses a simplified polygon (cached) for efficient intersection checks.
  /// Step 1: cheap bounding-box rejection.
  /// Step 2: edge-segment intersection or point-in-polygon test.
  bool isTileInAllowedRegion(int z, int x, int y) {
    if (_nepalBoundary == null || _nepalBoundary!.isEmpty) return true;

    // Tile bounding box in lat/lng
    final n = 1 << z;
    final tileWest = x / n * 360.0 - 180.0;
    final tileEast = (x + 1) / n * 360.0 - 180.0;
    final tileNorth = _tileYToLat(y, n);
    final tileSouth = _tileYToLat(y + 1, n);

    // Step 1: bounding-box pre-check (rejects clearly-outside tiles cheaply)
    final bounds = nepalBounds;
    if (tileEast < bounds.west ||
        tileWest > bounds.east ||
        tileNorth < bounds.south ||
        tileSouth > bounds.north) {
      return false;
    }

    // Step 2: polygon intersection using cached simplified ring
    final poly = _getTileIntersectPoly;
    if (poly.isEmpty) return true; // no boundary data → allow all
    final tileRect = _TileRect(tileWest, tileSouth, tileEast, tileNorth);

    // Check if any polygon edge crosses the tile rectangle
    for (int i = 0; i < poly.length - 1; i++) {
      if (_segmentsIntersectRect(poly[i], poly[i + 1], tileRect)) return true;
    }
    // Check if any tile corner is inside the polygon
    if (_pointInPolygon(tileNorth, tileWest, poly)) return true;
    if (_pointInPolygon(tileNorth, tileEast, poly)) return true;
    if (_pointInPolygon(tileSouth, tileWest, poly)) return true;
    if (_pointInPolygon(tileSouth, tileEast, poly)) return true;
    // Check if polygon centroid is inside the tile (covers tiny tiles inside Nepal)
    if (_polyCentroid != null &&
        tileRect.contains(_polyCentroid!.latitude, _polyCentroid!.longitude)) {
      return true;
    }
    return false;
  }

  /// Simplified polygon for tile intersection (cached, ~200 points).
  List<LatLng>? _tileIntersectPoly;
  LatLng? _polyCentroid;

  List<LatLng> get _getTileIntersectPoly {
    if (_tileIntersectPoly != null) return _tileIntersectPoly!;
    if (_nepalBoundary == null) return [];
    _tileIntersectPoly = _simplifyRingDP(_nepalBoundary!, maxPoints: 200);
    // Precompute centroid for the centroid-inside-tile check
    double lat = 0, lng = 0;
    for (final p in _tileIntersectPoly!) {
      lat += p.latitude;
      lng += p.longitude;
    }
    _polyCentroid = LatLng(
        lat / _tileIntersectPoly!.length, lng / _tileIntersectPoly!.length);
    return _tileIntersectPoly!;
  }

  static double _tileYToLat(int y, int n) {
    final t = math.pi * (1 - 2 * y / n);
    final sinh = (math.exp(t) - math.exp(-t)) / 2;
    return math.atan(sinh) * 180.0 / math.pi;
  }

  // ── Polygon / rectangle intersection helpers ──────────────────────

  static bool _segmentsIntersectRect(
      LatLng a, LatLng b, _TileRect r) {
    // Quick reject if both endpoints are on the same side outside the rect
    if ((a.longitude < r.minLng && b.longitude < r.minLng) ||
        (a.longitude > r.maxLng && b.longitude > r.maxLng) ||
        (a.latitude < r.minLat && b.latitude < r.minLat) ||
        (a.latitude > r.maxLat && b.latitude > r.maxLat)) {
      return false;
    }
    // Check if segment crosses any of the 4 rect edges
    if (_segCrossesSeg(a, b, LatLng(r.minLat, r.minLng), LatLng(r.minLat, r.maxLng))) return true;
    if (_segCrossesSeg(a, b, LatLng(r.maxLat, r.minLng), LatLng(r.maxLat, r.maxLng))) return true;
    if (_segCrossesSeg(a, b, LatLng(r.minLat, r.minLng), LatLng(r.maxLat, r.minLng))) return true;
    if (_segCrossesSeg(a, b, LatLng(r.minLat, r.maxLng), LatLng(r.maxLat, r.maxLng))) return true;
    return false;
  }

  static bool _segCrossesSeg(LatLng a1, LatLng a2, LatLng b1, LatLng b2) {
    final d1 = _cross(a2, a1, b1);
    final d2 = _cross(a2, a1, b2);
    final d3 = _cross(b2, b1, a1);
    final d4 = _cross(b2, b1, a2);
    if (((d1 > 0 && d2 < 0) || (d1 < 0 && d2 > 0)) &&
        ((d3 > 0 && d4 < 0) || (d3 < 0 && d4 > 0))) {
      return true;
    }
    return false;
  }

  static double _cross(LatLng a, LatLng b, LatLng c) {
    return (b.longitude - a.longitude) * (c.latitude - a.latitude) -
        (b.latitude - a.latitude) * (c.longitude - a.longitude);
  }

  /// Ray-casting point-in-polygon.
  static bool _pointInPolygon(double lat, double lng, List<LatLng> poly) {
    bool inside = false;
    for (int i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      final yi = poly[i].latitude, xi = poly[i].longitude;
      final yj = poly[j].latitude, xj = poly[j].longitude;
      if (((yi > lat) != (yj > lat)) &&
          (lng < (xj - xi) * (lat - yi) / (yj - yi) + xi)) {
        inside = !inside;
      }
    }
    return inside;
  }

  // ── Cached polygon builders ───────────────────────────────────────

  List<Polygon> buildNepalMask() {
    if (_cachedMask != null) return _cachedMask!;
    if (_nepalBoundary == null) return [];

    // Use a huge outer ring so the mask edges are never visible on screen,
    // even when the user pans to the very edge of Nepal. The Nepal-shaped
    // hole is the exact boundary (simplified to 800 points via DP).
    final bounds = nepalBounds;
    final sw = bounds.southWest;
    final ne = bounds.northEast;
    final padDeg = 15.0; // degrees — far beyond any screen viewport

    final outerRing = [
      LatLng(sw.latitude - padDeg, sw.longitude - padDeg),
      LatLng(sw.latitude - padDeg, ne.longitude + padDeg),
      LatLng(ne.latitude + padDeg, ne.longitude + padDeg),
      LatLng(ne.latitude + padDeg, sw.longitude - padDeg),
      LatLng(sw.latitude - padDeg, sw.longitude - padDeg),
    ];

    final holeRing = _simplifyRingDP(_nepalBoundary!, maxPoints: 800);

    _cachedMask = [
      Polygon(
        points: outerRing,
        holePointsList: [holeRing],
        color: const Color(0xF0F1F1F1), // covers foreign tiles
        borderColor: const Color(0xFF7C3AED),
        borderStrokeWidth: 2.5,
      ),
    ];
    return _cachedMask!;
  }

  List<Polygon> buildProvincePolygons() {
    if (_cachedProvincePolygons != null) return _cachedProvincePolygons!;
    if (_provinces == null) return [];

    _cachedProvincePolygons = _provinces!.map((ring) => Polygon(
      points: _simplifyRing(ring, maxPoints: 300),
      color: const Color(0x00000000),
      borderColor: const Color(0x997C3AED),
      borderStrokeWidth: 1.2,
      isFilled: false,
    )).toList();
    return _cachedProvincePolygons!;
  }

  List<Polygon> buildDistrictPolygons() {
    if (_cachedDistrictPolygons != null) return _cachedDistrictPolygons!;
    if (_districts == null) return [];

    _cachedDistrictPolygons = _districts!.map((ring) => Polygon(
      points: _simplifyRing(ring, maxPoints: 150),
      color: const Color(0x00000000),
      borderColor: const Color(0x602563EB),
      borderStrokeWidth: 0.8,
      isFilled: false,
    )).toList();
    return _cachedDistrictPolygons!;
  }

  List<Marker> buildDistrictLabels() {
    if (_cachedDistrictLabels != null) return _cachedDistrictLabels!;
    if (_districts == null || _districtNames == null) return [];

    final markers = <Marker>[];
    final centroids = districtCentroids;
    centroids.forEach((i, center) {
      final name = _districtNames!['$i'] ?? '';
      if (name.isEmpty) return;
      markers.add(Marker(
        point: center,
        width: 80,
        height: 20,
        child: Text(
          name,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Color(0xFF2563EB),
            fontSize: 7,
            fontWeight: FontWeight.w500,
            shadows: [
              Shadow(blurRadius: 2, color: Colors.white, offset: Offset(1, 1)),
              Shadow(blurRadius: 2, color: Colors.white, offset: Offset(-1, -1)),
            ],
          ),
        ),
      ));
    });
    _cachedDistrictLabels = markers;
    return _cachedDistrictLabels!;
  }

  // ── Helpers ───────────────────────────────────────────────────────

  LatLng _polygonCenter(List<LatLng> ring) {
    double lat = 0, lng = 0;
    for (final p in ring) {
      lat += p.latitude;
      lng += p.longitude;
    }
    return LatLng(lat / ring.length, lng / ring.length);
  }

  Map<int, LatLng> get provinceCentroids {
    final map = <int, LatLng>{};
    _provinces?.asMap().forEach((i, ring) {
      map[i] = _polygonCenter(ring);
    });
    return map;
  }

  Map<int, LatLng> get districtCentroids {
    final map = <int, LatLng>{};
    _districts?.asMap().forEach((i, ring) {
      map[i] = _polygonCenter(ring);
    });
    return map;
  }

  /// Douglas-Peucker-ish simplification: keep every Nth point + endpoints.
  List<LatLng> _simplifyRing(List<LatLng> ring, {required int maxPoints}) {
    if (ring.length <= maxPoints) return ring;
    final step = ring.length / maxPoints;
    final result = <LatLng>[ring.first];
    for (int i = 1; i < maxPoints - 1; i++) {
      result.add(ring[(i * step).toInt().clamp(0, ring.length - 1)]);
    }
    result.add(ring.last);
    return result;
  }

  /// Douglas-Peucker line simplification — preserves geographic shape far
  /// better than uniform downsampling at low vertex counts.
  List<LatLng> _simplifyRingDP(List<LatLng> ring, {required int maxPoints}) {
    if (ring.length <= maxPoints) return ring;
    // Build tolerance from average segment length so the algorithm adapts
    // to the actual boundary density.
    double totalDist = 0;
    for (int i = 0; i < ring.length - 1; i++) {
      totalDist += _pointToSegmentDist(ring[i], ring[i + 1]);
    }
    final avgSeg = totalDist / (ring.length - 1);
    // Start with a small tolerance and increase until we're under the limit.
    double tolerance = avgSeg * 0.5;
    List<LatLng> simplified;
    for (int attempt = 0; attempt < 20; attempt++) {
      simplified = _douglasPeucker(ring, tolerance);
      if (simplified.length <= maxPoints) break;
      tolerance *= 1.5;
    }
    simplified = _douglasPeucker(ring, tolerance);
    // Ensure first/last points are preserved (closed ring).
    if (simplified.first != ring.first || simplified.last != ring.last) {
      final mid = simplified.length > 2 ? simplified.sublist(1, simplified.length - 1) : <LatLng>[];
      simplified = [ring.first, ...mid, ring.last];
    }
    return simplified.length <= maxPoints ? simplified : simplified.sublist(0, maxPoints);
  }

  static List<LatLng> _douglasPeucker(List<LatLng> points, double tolerance) {
    if (points.length <= 2) return points;
    double maxDist = 0;
    int maxIdx = 0;
    final first = points.first;
    final last = points.last;
    for (int i = 1; i < points.length - 1; i++) {
      final d = _pointToSegmentDist(points[i], first, last);
      if (d > maxDist) {
        maxDist = d;
        maxIdx = i;
      }
    }
    if (maxDist > tolerance) {
      final left = _douglasPeucker(points.sublist(0, maxIdx + 1), tolerance);
      final right = _douglasPeucker(points.sublist(maxIdx), tolerance);
      return [...left.take(left.length - 1), ...right];
    }
    return [first, last];
  }

  /// Perpendicular distance from [p] to the line segment [a]–[b].
  static double _pointToSegmentDist(LatLng p, LatLng a, [LatLng? b]) {
    if (b == null) return _haversine(p, a);
    final dx = b.longitude - a.longitude;
    final dy = b.latitude - a.latitude;
    final lenSq = dx * dx + dy * dy;
    if (lenSq == 0) return _haversine(p, a);
    var t = ((p.longitude - a.longitude) * dx + (p.latitude - a.latitude) * dy) / lenSq;
    t = t.clamp(0.0, 1.0);
    final proj = LatLng(a.latitude + t * dy, a.longitude + t * dx);
    return _haversine(p, proj);
  }

  static double _haversine(LatLng a, LatLng b) {
    const R = 6371000.0;
    final dLat = (b.latitude - a.latitude) * math.pi / 180;
    final dLon = (b.longitude - a.longitude) * math.pi / 180;
    final la1 = a.latitude * math.pi / 180;
    final la2 = b.latitude * math.pi / 180;
    final h = (dLat / 2) * (dLat / 2) + math.cos(la1) * math.cos(la2) * (dLon / 2) * (dLon / 2);
    return 2 * R * math.sin(h).abs().clamp(0.0, 1.0);
  }

  String? _getApiBase() {
    return AppConstants.baseUrl;
  }
}

/// Simple axis-aligned rectangle for tile bounds (avoids LatLngBounds overhead).
class _TileRect {
  final double minLng, minLat, maxLng, maxLat;
  const _TileRect(this.minLng, this.minLat, this.maxLng, this.maxLat);

  bool contains(double lat, double lng) =>
      lng >= minLng && lng <= maxLng && lat >= minLat && lat <= maxLat;
}
