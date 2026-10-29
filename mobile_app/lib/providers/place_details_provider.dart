import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import '../core/api/api_client.dart';
import '../core/models/place.dart';

class PlaceDetailsProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  Place? _currentPlace;
  String? _currentPlaceId;
  List<Review> _reviews = [];
  String? _reviewsPlaceId;
  bool _isLoading = false;
  bool _isLoadingReviews = false;
  String? _errorMessage;
  
  Place? get currentPlace =>
      _currentPlace != null && _currentPlace!.id == _currentPlaceId
          ? _currentPlace
          : null;
  List<Review> get reviews => _reviews;
  bool get isLoading => _isLoading;
  bool get isLoadingReviews => _isLoadingReviews;
  String? get errorMessage => _errorMessage;

  Future<void> fetchPlaceDetails(String placeId) async {
    _currentPlaceId = placeId;
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.getPlaceDetails(placeId);
      final data = response.data['data'];
      
      if (data != null) {
        _currentPlace = Place.fromJson(data);
      } else {
        _currentPlace = null;
      }
    } catch (e) {
      print('❌ Failed to fetch place details: $e');
      _currentPlace = null;
      _errorMessage = 'Failed to load place details';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchPlaceReviews(String placeId) async {
    // Drop the previous place's reviews immediately; otherwise opening a new
    // place shows the old reviews until the request lands.
    if (_reviewsPlaceId != placeId) {
      _reviews = [];
      _reviewsPlaceId = placeId;
    }
    _isLoadingReviews = true;
    notifyListeners();

    try {
      final response = await _api.getPlaceReviews(placeId);
      final data = response.data['data'] as List? ?? [];
      _reviews = data.map((json) => Review.fromJson(json)).toList();
    } catch (e) {
      print('❌ Failed to fetch reviews: $e');
    }

    _isLoadingReviews = false;
    notifyListeners();
  }

  Future<void> ratePlace(String placeId, {
    required int rating,
    required String title,
    required String description,
    String? osmName,
    double? osmLatitude,
    double? osmLongitude,
    String? osmCategory,
    String? osmAddress,
    String? osmDistrict,
    String? osmPhone,
  }) async {
    try {
      await _api.addPlaceReview(
        placeId,
        title: title,
        description: description,
        rating: rating,
        osmName: osmName,
        osmLatitude: osmLatitude,
        osmLongitude: osmLongitude,
        osmCategory: osmCategory,
        osmAddress: osmAddress,
        osmDistrict: osmDistrict,
        osmPhone: osmPhone,
      );
      await fetchPlaceDetails(placeId);
      await fetchPlaceReviews(placeId);
    } catch (e) {
      print('❌ Failed to submit review: $e');
      rethrow;
    }
  }

  void clearDetails() {
    _currentPlace = null;
    _currentPlaceId = null;
    _reviews = [];
    _reviewsPlaceId = null;
    _errorMessage = null;
    notifyListeners();
  }

  // Helper to check if place has images
  bool get hasImages => (_currentPlace?.images.isNotEmpty) ?? false;

  // Helper to check if place has amenities
  bool get hasAmenities => (_currentPlace?.amenities.isNotEmpty) ?? false;

  // Helper to format rating
  String get ratingDisplay {
    if (_currentPlace == null) return 'N/A';
    return '${_currentPlace!.averageRating.toStringAsFixed(1)} (${_currentPlace!.totalReviews} reviews)';
  }

  // Helper to get opening status
  String getOpeningStatus() {
    if (_currentPlace?.operatingHours == null) return 'Hours not available';
    return 'Open'; // Can be enhanced with actual time comparison
  }
}
