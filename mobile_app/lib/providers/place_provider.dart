import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/place.dart';

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
  });

  factory PlaceModel.fromJson(Map<String, dynamic> json) {
    return PlaceModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      description: json['description'],
      address: json['address'],
      district: json['district'],
      latitude: (json['latitude'] ?? 0).toDouble(),
      longitude: (json['longitude'] ?? 0).toDouble(),
      averageRating: json['average_rating']?.toDouble(),
      totalReviews: json['total_reviews'] ?? 0,
      distanceKm: json['distance_km']?.toDouble(),
      category: json['category'],
      isVerified: json['is_verified'] ?? false,
      isFeatured: json['is_featured'] ?? false,
      source: json['source'] ?? 'admin',
      images: List<String>.from(json['images'] ?? []),
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

  List<CategoryModel> _categories = [];
  List<PlaceModel> _places = [];
  List<PlaceModel> _featuredPlaces = [];
  bool _isLoading = false;
  String? _errorMessage;
  int _selectedCategoryId = 0;

  List<CategoryModel> get categories => _categories;
  List<PlaceModel> get places => _places;
  List<PlaceModel> get featuredPlaces => _featuredPlaces;
  bool get isLoading => _isLoading;
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

  void setCategory(int categoryId) {
    _selectedCategoryId = categoryId;
    notifyListeners();
  }
}
