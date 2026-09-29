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
      notifyListeners();
    }
  }

  /// Viewport places computed client-side from the Nepal-wide dataset —
  /// instant, no network round-trip, no spinner.
  void setViewportPlaces(List<PlaceModel> places) {
    _places = places;
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
  Future<void> fetchViewportPlaces({
    required double minLat,
    required double maxLat,
    required double minLng,
    required double maxLng,
    int? zoom,
    String? category,
  }) async {
    if (_isLoadingViewport) return;

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
      return;
    }

    _isLoadingViewport = true;
    notifyListeners();

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
      final places = data.map((j) => PlaceModel.fromJson(j)).toList();
      if (places.isNotEmpty) {
        _viewportPlaces = places;
        _viewportMinLat = minLat;
        _viewportMaxLat = maxLat;
        _viewportMinLng = minLng;
        _viewportMaxLng = maxLng;
        _viewportSignature = signature;
        _viewportFetchedAt = DateTime.now();
        notifyListeners();
      }
    } catch (e) {
      // Viewport fetch failed — nepalPlaces remains as fallback
      debugPrint('fetchViewportPlaces failed: $e');
    }

    _isLoadingViewport = false;
    notifyListeners();
  }

  /// Set viewport places directly (used for client-side fallback from nepalPlaces).
  void setViewportPlacesDirect(List<PlaceModel> places) {
    _viewportPlaces = places;
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
