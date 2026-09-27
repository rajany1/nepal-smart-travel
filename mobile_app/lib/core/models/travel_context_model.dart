import 'package:flutter/foundation.dart';

/// One resolved endpoint of a journey (origin, checkpoint, or destination).
class TravelPoint {
  final String name;
  final double lat;
  final double lng;
  final String kind; // origin | checkpoint | destination
  final String? resolvedVia;

  const TravelPoint({
    required this.name,
    required this.lat,
    required this.lng,
    this.kind = 'checkpoint',
    this.resolvedVia,
  });

  factory TravelPoint.fromJson(Map<String, dynamic> json) {
    return TravelPoint(
      name: (json['name'] ?? '').toString(),
      lat: (json['lat'] as num?)?.toDouble() ?? 0,
      lng: (json['lng'] as num?)?.toDouble() ?? 0,
      kind: (json['kind'] ?? 'checkpoint').toString(),
      resolvedVia: json['resolved_via']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'name': name,
        'lat': lat,
        'lng': lng,
        'kind': kind,
        if (resolvedVia != null) 'resolved_via': resolvedVia,
      };
}

/// A raw geometry point on the corridor.
class TravelLatLng {
  final double lat;
  final double lng;
  const TravelLatLng(this.lat, this.lng);

  factory TravelLatLng.fromJson(Map<String, dynamic> json) =>
      TravelLatLng((json['lat'] as num).toDouble(), (json['lng'] as num).toDouble());

  Map<String, dynamic> toJson() => {'lat': lat, 'lng': lng};
}

/// One resolved leg of the corridor (origin→cp1, cp1→cp2, …).
class TravelSegment {
  final String fromName;
  final String toName;
  final String status; // routed | approximate | unrouted
  final String source;
  final double distanceM;
  final double durationS;
  final List<TravelLatLng> points;

  const TravelSegment({
    required this.fromName,
    required this.toName,
    required this.status,
    required this.source,
    required this.distanceM,
    required this.durationS,
    required this.points,
  });

  bool get isRouted => status == 'routed';
  bool get isApproximate => status == 'approximate';

  factory TravelSegment.fromJson(Map<String, dynamic> json) {
    return TravelSegment(
      fromName: (json['from_name'] ?? '').toString(),
      toName: (json['to_name'] ?? '').toString(),
      status: (json['status'] ?? 'approximate').toString(),
      source: (json['source'] ?? '').toString(),
      distanceM: (json['distance_m'] as num?)?.toDouble() ?? 0,
      durationS: (json['duration_s'] as num?)?.toDouble() ?? 0,
      points: [
        for (final p in (json['points'] as List? ?? []))
          if (p is Map<String, dynamic>) TravelLatLng.fromJson(p),
      ],
    );
  }

  Map<String, dynamic> toJson() => {
        'from_name': fromName,
        'to_name': toName,
        'status': status,
        'source': source,
        'distance_m': distanceM,
        'duration_s': durationS,
        'points': points.map((p) => p.toJson()).toList(),
      };
}

/// A suggested alternative when the route is ambiguous.
class RouteOption {
  final String label;
  final double distanceM;
  final double durationS;
  final List<TravelLatLng> points;
  final List<TravelPoint> via;

  const RouteOption({
    required this.label,
    required this.distanceM,
    required this.durationS,
    required this.points,
    required this.via,
  });

  factory RouteOption.fromJson(Map<String, dynamic> json) {
    return RouteOption(
      label: (json['label'] ?? 'Route').toString(),
      distanceM: (json['distance_m'] as num?)?.toDouble() ?? 0,
      durationS: (json['duration_s'] as num?)?.toDouble() ?? 0,
      points: [
        for (final p in (json['points'] as List? ?? []))
          if (p is Map<String, dynamic>) TravelLatLng.fromJson(p),
      ],
      via: [
        for (final v in (json['via'] as List? ?? []))
          if (v is Map<String, dynamic>) TravelPoint.fromJson(v),
      ],
    );
  }
}

/// Journey progress when the user's GPS projects onto the corridor.
class JourneyProgress {
  final double progress;
  final double distanceToRouteM;
  final double distanceAlongM;

  const JourneyProgress({
    required this.progress,
    required this.distanceToRouteM,
    required this.distanceAlongM,
  });

  factory JourneyProgress.fromJson(Map<String, dynamic> json) {
    return JourneyProgress(
      progress: (json['progress'] as num?)?.toDouble() ?? 0,
      distanceToRouteM: (json['distance_to_route_m'] as num?)?.toDouble() ?? 0,
      distanceAlongM: (json['distance_along_m'] as num?)?.toDouble() ?? 0,
    );
  }
}

/// Resolved travel context: origin → checkpoints → destination + corridor.
class TravelContextModel {
  final TravelPoint origin;
  final TravelPoint destination;
  final List<TravelPoint> checkpoints;
  final List<TravelPoint> waypoints;
  final List<TravelSegment> segments;
  final List<TravelLatLng> geometry;
  final String status; // routed | approximate | mixed | unrouted
  final bool needsClarification;
  final List<RouteOption> routeOptions;
  final double? totalDistanceKm;
  final int? totalDurationMin;
  final JourneyProgress? journey;
  final String? unresolved;

  const TravelContextModel({
    required this.origin,
    required this.destination,
    required this.checkpoints,
    required this.waypoints,
    required this.segments,
    required this.geometry,
    required this.status,
    required this.needsClarification,
    required this.routeOptions,
    this.totalDistanceKm,
    this.totalDurationMin,
    this.journey,
    this.unresolved,
  });

  bool get isApproximate => status != 'routed';
  bool get hasCorridor => geometry.length >= 2;

  String get summaryLabel {
    final parts = <String>[
      origin.name,
      ...checkpoints.map((c) => c.name),
      destination.name,
    ];
    return parts.join(' → ');
  }

  factory TravelContextModel.fromJson(Map<String, dynamic> json) {
    final originJson =
        ((json['origin'] as Map?) ?? const {}).cast<String, dynamic>();
    final destJson =
        ((json['destination'] as Map?) ?? const {}).cast<String, dynamic>();
    originJson.putIfAbsent('kind', () => 'origin');
    destJson.putIfAbsent('kind', () => 'destination');

    return TravelContextModel(
      origin: TravelPoint.fromJson(originJson),
      destination: TravelPoint.fromJson(destJson),
      checkpoints: [
        for (final c in (json['checkpoints'] as List? ?? []))
          if (c is Map<String, dynamic>) TravelPoint.fromJson(c),
      ],
      waypoints: [
        for (final w in (json['waypoints'] as List? ?? []))
          if (w is Map<String, dynamic>) TravelPoint.fromJson(w),
      ],
      segments: [
        for (final s in (json['segments'] as List? ?? []))
          if (s is Map<String, dynamic>) TravelSegment.fromJson(s),
      ],
      geometry: [
        for (final p in (json['geometry'] as List? ?? []))
          if (p is Map<String, dynamic>) TravelLatLng.fromJson(p),
      ],
      status: (json['status'] ?? 'approximate').toString(),
      needsClarification: json['needs_clarification'] == true,
      routeOptions: [
        for (final o in (json['route_options'] as List? ?? []))
          if (o is Map<String, dynamic>) RouteOption.fromJson(o),
      ],
      totalDistanceKm: (json['total_distance_km'] as num?)?.toDouble(),
      totalDurationMin: (json['total_duration_min'] as num?)?.toInt(),
      journey: json['journey'] is Map<String, dynamic>
          ? JourneyProgress.fromJson(json['journey'] as Map<String, dynamic>)
          : null,
      unresolved: json['unresolved']?.toString(),
    );
  }

  /// Payload shape accepted by POST /travel-context/intelligence.
  Map<String, dynamic> toPayload({bool includeGeometry = true}) {
    return {
      'origin': origin.toJson(),
      'destination': destination.toJson(),
      'checkpoints': checkpoints.map((c) => c.toJson()).toList(),
      'waypoints': waypoints.map((w) => w.toJson()).toList(),
      'status': status,
      'needs_clarification': needsClarification,
      'total_distance_km': totalDistanceKm,
      'total_duration_min': totalDurationMin,
      if (includeGeometry) ...{
        'segments': segments.map((s) => s.toJson()).toList(),
        'geometry': geometry.map((g) => g.toJson()).toList(),
      },
      'journey': journey == null
          ? null
          : {
              'progress': journey!.progress,
              'distance_to_route_m': journey!.distanceToRouteM,
              'distance_along_m': journey!.distanceAlongM,
            },
    };
  }
}

/// Ranked intelligence item along the corridor (report | alert | place).
class RouteIntelItem {
  final String type;
  final double relevanceScore;
  final double? corridorDistanceKm;
  final double? routeProgress;
  final String direction; // ahead | passed | behind_severe | broadcast
  final int id;
  final String title;
  final String? description;
  final String? priority;
  final String? severity;
  final String? categoryName;
  final String? categoryIcon;
  final double? latitude;
  final double? longitude;
  final String? district;
  final String? timeAgo;
  final String? timeState;
  final String? imageUrl;
  final String? reporterName;
  final double? averageRating;
  final int? helpfulCount;
  final int? commentsCount;
  final DateTime? createdAt;
  final Map<String, dynamic> raw;

  const RouteIntelItem({
    required this.type,
    required this.relevanceScore,
    required this.corridorDistanceKm,
    required this.routeProgress,
    required this.direction,
    required this.id,
    required this.title,
    this.description,
    this.priority,
    this.severity,
    this.categoryName,
    this.categoryIcon,
    this.latitude,
    this.longitude,
    this.district,
    this.timeAgo,
    this.timeState,
    this.imageUrl,
    this.reporterName,
    this.averageRating,
    this.helpfulCount,
    this.commentsCount,
    this.createdAt,
    required this.raw,
  });

  bool get isSevere =>
      priority == 'critical' ||
      priority == 'high' ||
      severity == 'critical' ||
      severity == 'high';

  bool get isAhead => direction == 'ahead' || direction == 'broadcast';

  factory RouteIntelItem.fromJson(Map<String, dynamic> json) {
    return RouteIntelItem(
      type: (json['type'] ?? 'report').toString(),
      relevanceScore: (json['relevance_score'] as num?)?.toDouble() ?? 0,
      corridorDistanceKm: (json['corridor_distance_km'] as num?)?.toDouble(),
      routeProgress: (json['route_progress'] as num?)?.toDouble(),
      direction: (json['direction'] ?? 'ahead').toString(),
      id: (json['id'] as num?)?.toInt() ?? 0,
      title: (json['title'] ?? json['name'] ?? '').toString(),
      description: json['description']?.toString(),
      priority: json['priority']?.toString(),
      severity: json['severity']?.toString(),
      categoryName: (json['category_name'] ?? json['category'])?.toString(),
      categoryIcon: json['category_icon']?.toString(),
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      district: json['district']?.toString(),
      timeAgo: json['time_ago']?.toString(),
      timeState: json['time_state']?.toString(),
      imageUrl: json['image_url']?.toString(),
      reporterName: json['reporter_name']?.toString(),
      averageRating: (json['average_rating'] as num?)?.toDouble(),
      helpfulCount: (json['helpful_count'] as num?)?.toInt(),
      commentsCount: (json['comments_count'] as num?)?.toInt(),
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      raw: json,
    );
  }
}

/// Grouped intelligence returned by the intelligence endpoint.
class RouteIntelligence {
  final List<RouteIntelItem> reports;
  final List<RouteIntelItem> alerts;
  final List<RouteIntelItem> places;
  final Map<String, dynamic> summary;

  const RouteIntelligence({
    required this.reports,
    required this.alerts,
    required this.places,
    required this.summary,
  });

  int get total =>
      (summary['total'] as num?)?.toInt() ??
      (reports.length + alerts.length + places.length);

  bool get isEmpty => total == 0;

  factory RouteIntelligence.fromJson(Map<String, dynamic> json) {
    return RouteIntelligence(
      reports: [
        for (final r in (json['reports'] as List? ?? []))
          if (r is Map<String, dynamic>) RouteIntelItem.fromJson(r),
      ],
      alerts: [
        for (final a in (json['alerts'] as List? ?? []))
          if (a is Map<String, dynamic>) RouteIntelItem.fromJson(a),
      ],
      places: [
        for (final p in (json['places'] as List? ?? []))
          if (p is Map<String, dynamic>) RouteIntelItem.fromJson(p),
      ],
      summary: (json['summary'] as Map?)?.cast<String, dynamic>() ?? const {},
    );
  }

  List<RouteIntelItem> get all => [...reports, ...alerts, ...places];
}

/// Debug helper (keeps foundation import used when assertions are off).
void debugLogTravel(String message) {
  assert(() {
    debugPrint('[TravelContext] $message');
    return true;
  }());
}
