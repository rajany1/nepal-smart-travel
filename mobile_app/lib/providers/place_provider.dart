import 'dart:math' as math;

import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import '../core/api/api_client.dart';
import '../core/models/place.dart';
import '../core/services/offline_db_service.dart';

class PlaceModel {
  final dynamic id;
  final String name;
  final String? description;
  final String? address;
  final String? district;
  final double latitude;
  final double longitude;
  final double? averageRating;
  final int totalReviews;
  final double? distanceKm;
  final String? category;
  final bool isVerified;
  final bool isFeatured;
  final String source;
  final List<String> images;
  final String? translatedName;

  PlaceModel({
    required this.id,
    // Can be int (admin) or String (OSM + combined)
    required this.name,
    this.description,
    this.address,
    this.district,
    required this.latitude,
    required this.longitude,
    this.averageRating,
    this.totalReviews = 0,
    this.distanceKm,
    this.category,
    this.isVerified = false,
    this.isFeatured = false,
    this.source = 'admin',
    this.images = const [],
    this.translatedName,
  });

  factory PlaceModel.fromJson(Map<String, dynamic> json) {
    final rawImages = List<String>.from(json['images'] ?? []);
    return PlaceModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      description: json['description'],
      address: json['address'],
      district: json['district'],
      latitude: double.tryParse((json['latitude'] ?? 0).toString()) ?? 0.0,
      longitude: double.tryParse((json['longitude'] ?? 0).toString()) ?? 0.0,
      averageRating: double.tryParse(json['average_rating']?.toString() ?? ''),
      totalReviews: int.tryParse(json['total_reviews']?.toString() ?? '') ?? 0,
      distanceKm: double.tryParse(json['distance_km']?.toString() ?? ''),
      category: json['category'],
      isVerified: json['is_verified'] ?? false,
      isFeatured: json['is_featured'] ?? false,
      source: json['source'] ?? 'admin',
      // /places/all payload uses a single `image` field instead of `images`
      images: rawImages.isNotEmpty || json['image'] == null
          ? rawImages
          : [json['image'].toString()],
      translatedName: json['translated_name'],
    );
  }

  Place toPlace() {
    return Place(
      id: id.toString(),
      name: name,
      description: description,
      category: category ?? 'General',
      latitude: latitude,
      longitude: longitude,
      address: address,
      district: district,
      averageRating: averageRating ?? 0,
      totalReviews: totalReviews,
      images: images,
      distanceKm: distanceKm ?? 0,
      isVerified: isVerified,
      isFeatured: isFeatured,
      source: source,
    );
  }

  PlaceModel copyWith({double? distanceKm}) {
    return PlaceModel(
      id: id,
      name: name,
      description: description,
      address: address,
      district: district,
      latitude: latitude,
      longitude: longitude,
      averageRating: averageRating,
      totalReviews: totalReviews,
      distanceKm: distanceKm ?? this.distanceKm,
      category: category,
      isVerified: isVerified,
      isFeatured: isFeatured,
      source: source,
      images: images,
      translatedName: translatedName,
    );
  }
}

// ── Displayable place pipeline ──────────────────────────────────────────────
//
// Three independent payloads describe "places":
//   _places         ← /places/nearby-combined (DB rows as `admin_<int id>` +
//                     live OSM nodes as `osm_<type>/<id>`)
//   _viewportPlaces ← /places/bbox            (DB rows as a plain int id)
//   _nepalPlaces    ← /places/all             (DB rows as a plain int id)
//
// buildDisplayablePlaces() folds them into the ONE collection the UI reads:
//
//   merge → dedupe by placeIdentityKey() → drop category == "All"
//                        ↓
//                 displayablePlaces
//                   ↙         ↘
//               Nearby        Map → viewport cull → markers
//
// Both consumers start from that collection, so a record can never be
// visible on one and missing from the other.

/// `place_categories` ships a placeholder row named "All" (id 1) and the
/// client prepends its own synthetic one (id 0); every gazetteer seed row is
/// attached to it. It means "no real category", so such records are not
/// displayable anywhere — Nearby list, map, or marker.
bool isDisplayablePlace(PlaceModel place) =>
    (place.category ?? '').trim().toLowerCase() != 'all';

/// Stable identity of a place across the payloads that describe it.
///
/// Formats traced to their producers in PlaceController:
///   `35`          bboxQuery() / all() / nearby()  → int $place->id
///   `admin_35`    nearbyCombined() admin branch   → 'admin_' . int id
///   `osm_node/123` nearbyCombined() OSM branch    → 'osm_' . type/id
///
/// So `35` and `admin_35` are the same DB row and must collapse to one key,
/// while `osm_node/123` is a live OSM node that may or may not have a DB row.
String placeIdentityKey(PlaceModel place) {
  final raw = place.id?.toString() ?? '';
  if (raw.startsWith('admin_')) {
    final dbId = raw.substring('admin_'.length);
    if (int.tryParse(dbId) != null) return 'db:$dbId';
  } else if (raw.startsWith('osm_')) {
    return 'osm:${raw.substring('osm_'.length)}';
  }
  if (int.tryParse(raw) != null) return 'db:$raw';
  return 'raw:$raw';
}

/// Whether [place] sits inside the camera viewport (plus [margin] of
/// cushion so edge pins are already drawn before they scroll into view).
bool isWithinViewport(
  PlaceModel place, {
  required double minLat,
  required double maxLat,
  required double minLng,
  required double maxLng,
  double margin = 0.15,
}) =>
    place.latitude >= minLat - margin &&
    place.latitude <= maxLat + margin &&
    place.longitude >= minLng - margin &&
    place.longitude <= maxLng + margin;

/// Viewport cull shared by the marker pipeline.
List<PlaceModel> placesWithinViewport(
  List<PlaceModel> places, {
  required double minLat,
  required double maxLat,
  required double minLng,
  required double maxLng,
  double margin = 0.15,
}) =>
    [
      for (final place in places)
        if (isWithinViewport(place,
            minLat: minLat,
            maxLat: maxLat,
            minLng: minLng,
            maxLng: maxLng,
            margin: margin))
          place,
    ];

/// True when two records are within 100 m of each other — the distance the
/// server uses to decide a DB row and a live OSM node are the same place
/// (PlaceController::nearbyCombined cross-source dedupe).
bool _isSamePlaceWithin100m(PlaceModel a, PlaceModel b) {
  const earthRadiusKm = 6371.0;
  final dLat = (b.latitude - a.latitude) * math.pi / 180;
  final dLng = (b.longitude - a.longitude) * math.pi / 180;
  final h = math.sin(dLat / 2) * math.sin(dLat / 2) +
      math.cos(a.latitude * math.pi / 180) *
          math.cos(b.latitude * math.pi / 180) *
          math.sin(dLng / 2) * math.sin(dLng / 2);
  final distanceKm = 2 * earthRadiusKm * math.asin(math.min(1, math.sqrt(h)));
  return distanceKm <= 0.1;
}

/// Builds the canonical displayable collection (see pipeline note above).
///
/// Pure and only re-run when a raw source changes — the provider memoizes it,
/// so camera movement and widget rebuilds just read the result.
List<PlaceModel> buildDisplayablePlaces({
  required List<PlaceModel> nearbyPlaces,
  required List<PlaceModel> viewportPlaces,
  required List<PlaceModel> nepalPlaces,
}) {
  final byKey = <String, PlaceModel>{};

  // Trust order: the radius payload carries distance/images for the places it
  // actually selected, so it wins a tie; the bbox and Nepal-wide payloads
  // fill in everything it missed (server density caps, places outside the
  // radius).
  void addAll(List<PlaceModel> source) {
    for (final place in source) {
      if (!isDisplayablePlace(place)) continue;
      byKey.putIfAbsent(placeIdentityKey(place), () => place);
    }
  }

  addAll(nearbyPlaces);
  addAll(viewportPlaces);
  addAll(nepalPlaces);

  // Ids can never collide across the DB/OSM boundary (`db:35` vs
  // `osm:node/35`), so a live OSM node describing a place the DB already
  // stores would survive as a duplicate marker. Mirror the server rule: same
  // name within 100 m ⇒ one place, and the DB row wins (it has the images,
  // ratings and verification the live node lacks).
  final dbByName = <String, List<PlaceModel>>{};
  final osmKeys = <String>[];
  for (final entry in byKey.entries) {
    if (entry.key.startsWith('osm:')) {
      osmKeys.add(entry.key);
    } else if (entry.key.startsWith('db:')) {
      final name = entry.value.name.trim().toLowerCase();
      if (name.isNotEmpty) (dbByName[name] ??= []).add(entry.value);
    }
  }

  for (final key in osmKeys) {
    final osm = byKey[key];
    if (osm == null) continue;
    final candidates = dbByName[osm.name.trim().toLowerCase()];
    if (candidates == null) continue;
    if (candidates.any((db) => _isSamePlaceWithin100m(db, osm))) {
      byKey.remove(key);
    }
  }

  return byKey.values.toList(growable: false);
}

class CategoryModel {
  final int id;
  final String name;
  final String? icon;

  CategoryModel({required this.id, required this.name, this.icon});

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      icon: json['icon'],
    );
  }
}

class PlaceProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;
  final OfflineDbService _offlineDb = OfflineDbService.instance;

  List<CategoryModel> _categories = [];
  List<PlaceModel> _places = [];
  List<PlaceModel> _featuredPlaces = [];
  List<PlaceModel> _nepalPlaces = [];
  List<PlaceModel> _viewportPlaces = [];
  bool _isLoading = false;
  bool _isLoadingNepal = false;
  bool _isLoadingViewport = false;
  String? _errorMessage;
  int _selectedCategoryId = 0;

  // ── Refresh gating ──────────────────────────────────────────────────
  // Reopening the Nearby screen must not re-issue the same requests just
  // because the screen was recreated. Each source remembers what it last
  // fetched and only refetches once the data could plausibly have changed.
  DateTime? _nepalFetchedAt;
  String? _viewportSignature;
  DateTime? _viewportFetchedAt;
  String? _featuredSignature;
  DateTime? _featuredFetchedAt;

  /// Bounding box the last viewport (bbox API) response covers, used to
  /// decide whether the in-memory viewport places still describe the
  /// camera the user is actually looking at.
  double? _viewportMinLat, _viewportMaxLat, _viewportMinLng, _viewportMaxLng;

  /// Nepal-wide payload is valid for as long as the server caches it.
  static const Duration nepalStaleness = Duration(minutes: 10);
  static const Duration viewportStaleness = Duration(minutes: 2);
  static const Duration featuredStaleness = Duration(minutes: 5);

  static String _round(double v, [int digits = 3]) =>
      v.toStringAsFixed(digits);

  List<CategoryModel> get categories => _categories;
  List<PlaceModel> get places => _places;
  List<PlaceModel> get featuredPlaces => _featuredPlaces;

  /// Nepal-wide places (admin + OSM + user submitted) — the instant map
  /// dataset. Kept separate from [_places] (viewport nearby query).
  List<PlaceModel> get nepalPlaces => _nepalPlaces;

  /// Viewport-specific places from the bbox API. Primary source for markers
  /// when available. Falls back to nepalPlaces for offline/initial load.
  List<PlaceModel> get viewportPlaces => _viewportPlaces;

  /// True when the last bbox response is known to cover [minLat..maxLat] /
  /// [minLng..maxLng]. False when the places came from another source or the
  /// user has since moved the camera somewhere the payload does not describe.
  bool viewportCovers({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
  }) {
    if (_viewportPlaces.isEmpty) return false;
    final n = _viewportMaxLat;
    final s = _viewportMinLat;
    final e = _viewportMaxLng;
    final w = _viewportMinLng;
    if (n == null || s == null || e == null || w == null) return false;
    const slack = 0.01; // ~1 km — absorbs rounding of the viewport estimate
    return minLat >= s - slack && maxLat <= n + slack &&
        minLng >= w - slack && maxLng <= e + slack;
  }

  // ── Canonical displayable collection (memoized) ────────────────────────
  // Rebuilt only by _invalidateDisplayable(), which every mutator of
  // _places / _viewportPlaces / _nepalPlaces calls before notifyListeners().
  // Camera movement and rebuilds read the memo — they never re-merge.
  List<PlaceModel>? _displayablePlaces;
  Set<String>? _viewportPlaceKeys;

  /// The one collection the UI reads: every raw source merged, deduped and
  /// stripped of `category == "All"`. The Nearby sheet and the map's marker
  /// builder both start here, so they cannot disagree about what is
  /// displayable.
  List<PlaceModel> get displayablePlaces =>
      _displayablePlaces ??= buildDisplayablePlaces(
        nearbyPlaces: _places,
        viewportPlaces: _viewportPlaces,
        nepalPlaces: _nepalPlaces,
      );

  /// Normalized identities of the current bbox payload. The marker builder
  /// uses them to keep the rows the server already scoped to the viewport
  /// (viewport + prefetch buffer) without re-testing each coordinate.
  Set<String> get viewportPlaceKeys =>
      _viewportPlaceKeys ??= _viewportPlaces.map(placeIdentityKey).toSet();

  void _invalidateDisplayable() {
    _displayablePlaces = null;
    _viewportPlaceKeys = null;
  }

  bool get isLoading => _isLoading;
  bool get isLoadingNepal => _isLoadingNepal;
  bool get isLoadingViewport => _isLoadingViewport;
  String? get errorMessage => _errorMessage;
  int get selectedCategoryId => _selectedCategoryId;

  Future<void> fetchCategories() async {
    if (_categories.length > 1) return;
    try {
      final response = await _api.getPlaceCategories();
      final data = response.data['data'] as List? ?? [];
      _categories = [
        CategoryModel(id: 0, name: 'All'),
        ...data.map((j) => CategoryModel.fromJson(j)).toList(),
      ];
      notifyListeners();
    } catch (e) {
      print('❌ Failed to fetch categories: $e');
    }
  }

  Future<void> fetchNearbyPlaces({
    required double lat,
    required double lng,
    double radiusKm = 5.0,
    int? categoryId,
    String? search,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // Use combined endpoint for OSM + admin places
      final response = await _api.getCombinedNearbyPlaces(
        lat: lat,
        lng: lng,
        radiusKm: radiusKm,
        categoryId: categoryId,
        search: search,
        limit: 100,
      );
      final data = response.data['data'] as List? ?? [];
      _places = data.map((j) => PlaceModel.fromJson(j)).toList();
      _invalidateDisplayable();
    } catch (e) {
      print('❌ Failed to fetch nearby places: $e');
      _errorMessage = 'Failed to load nearby places';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchFeaturedPlaces({double? lat, double? lng}) async {
    final signature = lat == null || lng == null
        ? 'global'
        : '${_round(lat)},${_round(lng)}';
    final fetchedAt = _featuredFetchedAt;
    if (fetchedAt != null &&
        _featuredSignature == signature &&
        DateTime.now().difference(fetchedAt) < featuredStaleness) {
      return; // Same spot, still fresh — reopening the screen must not refetch.
    }
    try {
      final response = await _api.getFeaturedPlaces(lat: lat, lng: lng);
      final data = response.data['data'] as List? ?? [];
      _featuredPlaces = data.map((j) => PlaceModel.fromJson(j)).toList();
      _featuredSignature = signature;
      _featuredFetchedAt = DateTime.now();
      notifyListeners();
    } catch (e) {
      print('❌ Failed to fetch featured places: $e');
    }
  }

  /// Set places from local cache (offline mode)
  void setCachedPlaces(List<PlaceModel> places) {
    if (places.isNotEmpty) {
      _places = places;
      _invalidateDisplayable();
      notifyListeners();
    }
  }

  /// Viewport places computed client-side from the Nepal-wide dataset —
  /// instant, no network round-trip, no spinner.
  void setViewportPlaces(List<PlaceModel> places) {
    _places = places;
    _invalidateDisplayable();
    notifyListeners();
  }

  /// Nepal-wide places (max 1000) — fired in parallel with GPS lookup so the
  /// map paints instantly. Falls back to the SQLite cache when offline.
  ///
  /// [force] always hits the network. Without it the payload is only
  /// refetched once it is older than [nepalStaleness] (the same window the
  /// server caches /places/all for), so reopening the Nearby screen does not
  /// re-issue a request for data that cannot have changed yet. The SQLite
  /// cache written below is what restores markers instantly on cold start.
  Future<void> fetchNepalPlaces({bool force = false}) async {
    if (_isLoadingNepal) return;
    if (!force &&
        _nepalPlaces.isNotEmpty &&
        _nepalFetchedAt != null &&
        DateTime.now().difference(_nepalFetchedAt!) < nepalStaleness) {
      return;
    }
    _isLoadingNepal = true;
    notifyListeners();

    try {
      final response = await _api.getNepalPlaces(limit: 15000);
      final data = (response.data['data'] as List?) ?? [];
      final places = data.map((j) => PlaceModel.fromJson(j)).toList();
      if (places.isNotEmpty) {
        _nepalPlaces = places;
        _nepalFetchedAt = DateTime.now();
        _invalidateDisplayable();
        try {
          await _offlineDb.cachePlacesBulk(
            data.map((j) => Map<String, dynamic>.from(j)).toList(),
          );
        } catch (e) {
          print('Offline cache write failed: $e');
        }
      }
    } catch (e) {
      print('Failed to fetch Nepal places: $e');
      try {
        final cached = await _offlineDb.getAllCachedPlaces(limit: 15000);
        if (cached.isNotEmpty) {
          _nepalPlaces = cached.map((j) => PlaceModel.fromJson(j)).toList();
          _invalidateDisplayable();
        }
      } catch (e2) {
        print('Failed to load cached Nepal places: $e2');
      }
    }

    _isLoadingNepal = false;
    notifyListeners();
  }

  /// Restore Nepal-wide places from the local cache immediately (offline /
  /// cold-start path, no network).
  Future<void> setNepalCachedPlaces() async {
    if (_nepalPlaces.isNotEmpty) return;
    try {
      final cached = await _offlineDb.getAllCachedPlaces(limit: 15000);
      if (cached.isNotEmpty) {
        _nepalPlaces = cached.map((j) => PlaceModel.fromJson(j)).toList();
        _invalidateDisplayable();
        notifyListeners();
      }
    } catch (e) {
      print('Failed to load cached Nepal places: $e');
    }
  }

  /// Fetch places for a specific viewport bounding box from the server.
  /// This is the primary data source for the map — returns only places
  /// inside the requested bbox, with zoom-aware density limiting.
  ///
  /// Repeated requests for the same (grid-normalised) area — e.g. reopening
  /// the Nearby screen without moving the map — are served from memory for
  /// [viewportStaleness] instead of hitting the API again.
  ///
  /// Returns the raw payload (so the caller can persist it for the next open)
  /// or an empty list when nothing new was fetched.
  Future<List<Map<String, dynamic>>> fetchViewportPlaces({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    int? zoom,
    String? category,
  }) async {
    if (_isLoadingViewport) return const [];

    // Same 0.05° grid the server rounds its cache key to, so a request that
    // the backend would answer from Redis is also skipped on the client.
    String signatureOf(double lat) => (lat / 0.05).round().toString();
    final signature = [
      signatureOf(minLat),
      signatureOf(maxLat),
      signatureOf(minLng),
      signatureOf(maxLng),
      zoom ?? '',
      category ?? '',
    ].join(':');
    final fetchedAt = _viewportFetchedAt;
    if (fetchedAt != null &&
        _viewportSignature == signature &&
        DateTime.now().difference(fetchedAt) < viewportStaleness) {
      return const [];
    }

    _isLoadingViewport = true;
    notifyListeners();

    var raw = const <Map<String, dynamic>>[];
    try {
      final response = await _api.getPlacesInBBox(
        minLat: minLat,
        maxLat: maxLat,
        minLng: minLng,
        maxLng: maxLng,
        zoom: zoom,
        category: category,
        limit: 500,
      );
      final data = (response.data['data'] as List?) ?? [];
      raw = data.map((j) => Map<String, dynamic>.from(j)).toList();
      final places = raw.map((j) => PlaceModel.fromJson(j)).toList();
      if (places.isNotEmpty) {
        _viewportPlaces = places;
        _viewportMinLat = minLat;
        _viewportMaxLat = maxLat;
        _viewportMinLng = minLng;
        _viewportMaxLng = maxLng;
        _viewportSignature = signature;
        _viewportFetchedAt = DateTime.now();
        _invalidateDisplayable();
        notifyListeners();
      }
    } catch (e) {
      // Viewport fetch failed — nepalPlaces remains as fallback
      debugPrint('fetchViewportPlaces failed: $e');
      raw = const [];
    }

    _isLoadingViewport = false;
    notifyListeners();
    return raw;
  }

  /// Rehydrate the last successful bbox payload from the local snapshot so
  /// reopening Nearby paints markers before any request goes out.
  ///
  /// The coverage bounds are recorded (otherwise [_markerPlacesForViewport]
  /// would ignore the payload), but the staleness stamps are deliberately
  /// left untouched — this data came from disk, not from the API, so the
  /// background refresh must still run.
  void restoreViewportPlaces(
    List<PlaceModel> places, {
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
  }) {
    if (places.isEmpty) return;
    _viewportPlaces = places;
    _viewportMinLat = minLat;
    _viewportMaxLat = maxLat;
    _viewportMinLng = minLng;
    _viewportMaxLng = maxLng;
    _invalidateDisplayable();
    notifyListeners();
  }

  /// Set viewport places directly (used for client-side fallback from nepalPlaces).
  void setViewportPlacesDirect(List<PlaceModel> places) {
    _viewportPlaces = places;
    _invalidateDisplayable();
    // Deliberately do NOT record coverage bounds: these came from the
    // Nepal-wide dataset, not from a bbox response, so the caller should
    // keep treating them as an unbounded fallback.
    notifyListeners();
  }

  void setCategory(int categoryId) {
    _selectedCategoryId = categoryId;
    notifyListeners();
  }
}
