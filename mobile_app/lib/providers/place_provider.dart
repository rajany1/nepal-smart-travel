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
  bool _isLoading = false;
  bool _isLoadingNepal = false;
  String? _errorMessage;
  int _selectedCategoryId = 0;

  List<CategoryModel> get categories => _categories;
  List<PlaceModel> get places => _places;
  List<PlaceModel> get featuredPlaces => _featuredPlaces;

  /// Nepal-wide places (admin + OSM + user submitted) — the instant map
  /// dataset. Kept separate from [_places] (viewport nearby query).
  List<PlaceModel> get nepalPlaces => _nepalPlaces;
  bool get isLoading => _isLoading;
  bool get isLoadingNepal => _isLoadingNepal;
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
    try {
      final response = await _api.getFeaturedPlaces(lat: lat, lng: lng);
      final data = response.data['data'] as List? ?? [];
      _featuredPlaces = data.map((j) => PlaceModel.fromJson(j)).toList();
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
  Future<void> fetchNepalPlaces({bool force = false}) async {
    if (_isLoadingNepal) return;
    if (!force && _nepalPlaces.isNotEmpty) return;
    _isLoadingNepal = true;
    notifyListeners();

    try {
      final response = await _api.getNepalPlaces(limit: 1000);
      final data = (response.data['data'] as List?) ?? [];
      final places = data.map((j) => PlaceModel.fromJson(j)).toList();
      if (places.isNotEmpty) {
        _nepalPlaces = places;
        // Nepal-wide offline cache (single bulk insert)
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
        final cached = await _offlineDb.getAllCachedPlaces(limit: 1000);
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
      final cached = await _offlineDb.getAllCachedPlaces(limit: 1000);
      if (cached.isNotEmpty) {
        _nepalPlaces = cached.map((j) => PlaceModel.fromJson(j)).toList();
        notifyListeners();
      }
    } catch (e) {
      print('Failed to load cached Nepal places: $e');
    }
  }

  void setCategory(int categoryId) {
    _selectedCategoryId = categoryId;
    notifyListeners();
  }
}
