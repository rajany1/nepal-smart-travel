/// Curated routes (trekking + city itineraries) shown in the mobile app.
/// Mirrors the backend `curated_routes` table + the public API payload.
class RouteTrackPoint {
  final double lat;
  final double lng;
  final String? name;

  RouteTrackPoint({required this.lat, required this.lng, this.name});

  factory RouteTrackPoint.fromJson(Map<String, dynamic> json) {
    return RouteTrackPoint(
      lat: double.tryParse((json['lat'] ?? 0).toString()) ?? 0.0,
      lng: double.tryParse((json['lng'] ?? 0).toString()) ?? 0.0,
      name: json['name'],
    );
  }
}

/// A place stop on a route (from waypointPlaces on the backend).
class RoutePlaceModel {
  final int id;
  final String name;
  final String? district;
  final double latitude;
  final double longitude;
  final double averageRating;
  final String? category;
  final String? image;

  RoutePlaceModel({
    required this.id,
    required this.name,
    this.district,
    required this.latitude,
    required this.longitude,
    this.averageRating = 0,
    this.category,
    this.image,
  });

  factory RoutePlaceModel.fromJson(Map<String, dynamic> json) {
    return RoutePlaceModel(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      name: json['name'] ?? '',
      district: json['district'],
      latitude: double.tryParse((json['latitude'] ?? 0).toString()) ?? 0.0,
      longitude: double.tryParse((json['longitude'] ?? 0).toString()) ?? 0.0,
      averageRating:
          double.tryParse((json['average_rating'] ?? 0).toString()) ?? 0.0,
      category: json['category'],
      image: json['image'],
    );
  }
}

class CuratedRouteModel {
  final int id;
  final String title;
  final String slug;
  final String routeType; // itinerary | trekking
  final String? difficulty; // easy | moderate | challenging | hard
  final String difficultyLabel;
  final String? description;
  final String? image;
  final int durationDays;
  final String? bestSeason;
  final int? maxAltitudeM;
  final double? totalDistanceKm;
  final int? elevationGainM;
  final String? startingPoint;
  final String? endingPoint;
  final int waypointCount;
  final List<int> waypoints;
  final List<RouteTrackPoint> track;
  final List<RoutePlaceModel> places;

  CuratedRouteModel({
    required this.id,
    required this.title,
    required this.slug,
    required this.routeType,
    this.difficulty,
    this.difficultyLabel = '',
    this.description,
    this.image,
    required this.durationDays,
    this.bestSeason,
    this.maxAltitudeM,
    this.totalDistanceKm,
    this.elevationGainM,
    this.startingPoint,
    this.endingPoint,
    this.waypointCount = 0,
    this.waypoints = const [],
    this.track = const [],
    this.places = const [],
  });

  bool get isTrekking => routeType == 'trekking';

  factory CuratedRouteModel.fromJson(Map<String, dynamic> json) {
    return CuratedRouteModel(
      id: int.tryParse(json['id']?.toString() ?? '') ?? 0,
      title: json['title'] ?? '',
      slug: json['slug'] ?? '',
      routeType: json['route_type'] ?? 'itinerary',
      difficulty: json['difficulty'],
      difficultyLabel: json['difficulty_label'] ?? '',
      description: json['description'],
      image: json['image'],
      durationDays: int.tryParse(json['duration_days']?.toString() ?? '') ?? 1,
      bestSeason: json['best_season'],
      maxAltitudeM: json['max_altitude_m'] != null
          ? int.tryParse(json['max_altitude_m'].toString())
          : null,
      totalDistanceKm: json['total_distance_km'] != null
          ? double.tryParse(json['total_distance_km'].toString())
          : null,
      elevationGainM: json['elevation_gain_m'] != null
          ? int.tryParse(json['elevation_gain_m'].toString())
          : null,
      startingPoint: json['starting_point'],
      endingPoint: json['ending_point'],
      waypointCount: int.tryParse(json['waypoint_count']?.toString() ?? '') ?? 0,
      waypoints: (json['waypoints'] as List?)
              ?.map((e) => int.tryParse(e.toString()) ?? 0)
              .toList() ??
          const [],
      track: (json['track'] as List?)
              ?.map((e) => RouteTrackPoint.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
      places: (json['places'] as List?)
              ?.map((e) => RoutePlaceModel.fromJson(e as Map<String, dynamic>))
              .toList() ??
          const [],
    );
  }
}
