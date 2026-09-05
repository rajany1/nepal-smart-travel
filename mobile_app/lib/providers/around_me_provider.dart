import 'package:flutter/material.dart';
import '../core/api/api_client.dart';

class AroundMeItem {
  final int id;
  final String uuid;
  final String type; // 'report', 'alert', 'place'
  final String title;
  final String description;
  final String? category;
  final String? categoryIcon;
  final String? priority;
  final String? severity;
  final String? alertType;
  final double? latitude;
  final double? longitude;
  final String? district;
  final String? address;
  final double? distanceKm;
  final String? timeAgo;
  final String? timeState; // 'live', 'today', 'recent', 'expired'
  final int? helpfulCount;
  final int? unhelpfulCount;
  final int? commentsCount;
  final double? averageRating;
  final int? totalReviews;
  final bool? isVerified;
  final String? phone;
  final String? imageUrl;
  final String? reporterName;
  final String? reporterAvatar;
  final String? senderType;
  final DateTime? createdAt;

  AroundMeItem({
    required this.id,
    required this.uuid,
    required this.type,
    required this.title,
    required this.description,
    this.category,
    this.categoryIcon,
    this.priority,
    this.severity,
    this.alertType,
    this.latitude,
    this.longitude,
    this.district,
    this.address,
    this.distanceKm,
    this.timeAgo,
    this.timeState,
    this.helpfulCount,
    this.unhelpfulCount,
    this.commentsCount,
    this.averageRating,
    this.totalReviews,
    this.isVerified,
    this.phone,
    this.imageUrl,
    this.reporterName,
    this.reporterAvatar,
    this.senderType,
    this.createdAt,
  });

  factory AroundMeItem.fromJson(Map<String, dynamic> json) {
    return AroundMeItem(
      id: json['id'] ?? 0,
      uuid: json['uuid'] ?? '',
      type: json['type'] ?? 'report',
      title: json['title'] ?? json['name'] ?? '',
      description: json['description'] ?? '',
      category: json['category'],
      categoryIcon: json['category_icon'],
      priority: json['priority'],
      severity: json['severity'],
      alertType: json['alert_type'],
      latitude: double.tryParse(json['latitude']?.toString() ?? ''),
      longitude: double.tryParse(json['longitude']?.toString() ?? ''),
      district: json['district'],
      address: json['address'],
      distanceKm: json['distance_km'] != null
          ? (json['distance_km'] as num).toDouble()
          : null,
      timeAgo: json['time_ago'],
      timeState: json['time_state'],
      helpfulCount: json['helpful_count'] ?? 0,
      unhelpfulCount: json['unhelpful_count'] ?? 0,
      commentsCount: json['comments_count'] ?? 0,
      averageRating: json['average_rating'] != null
          ? (json['average_rating'] as num).toDouble()
          : null,
      totalReviews: json['total_reviews'],
      isVerified: json['is_verified'],
      phone: json['phone'],
      imageUrl: json['image_url'],
      reporterName: json['reporter_name'],
      reporterAvatar: json['reporter_avatar'],
      senderType: json['sender_type'],
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'])
          : null,
    );
  }

  Color get timeStateColor {
    switch (timeState) {
      case 'live':
        return const Color(0xFFE53935);
      case 'today':
        return const Color(0xFF43A047);
      case 'recent':
        return const Color(0xFF1E88E5);
      default:
        return Colors.grey;
    }
  }

  String get timeStateLabel {
    switch (timeState) {
      case 'live':
        return 'LIVE';
      case 'today':
        return 'TODAY';
      case 'recent':
        return 'RECENT';
      default:
        return 'OLD';
    }
  }

  IconData get typeIcon {
    switch (type) {
      case 'alert':
        return Icons.warning_amber_rounded;
      case 'place':
        return Icons.place;
      default:
        return Icons.article;
    }
  }
}

class AroundMeProvider extends ChangeNotifier {
  List<AroundMeItem> _emergency = [];
  List<AroundMeItem> _alerts = [];
  List<AroundMeItem> _reports = [];
  List<AroundMeItem> _places = [];
  bool _isLoading = false;
  String? _error;
  int _emergencyCount = 0;
  int _alertsCount = 0;
  int _reportsCount = 0;
  int _placesCount = 0;

  List<AroundMeItem> get emergency => _emergency;
  List<AroundMeItem> get alerts => _alerts;
  List<AroundMeItem> get reports => _reports;
  List<AroundMeItem> get places => _places;
  bool get isLoading => _isLoading;
  String? get error => _error;
  int get emergencyCount => _emergencyCount;
  int get alertsCount => _alertsCount;
  int get reportsCount => _reportsCount;
  int get placesCount => _placesCount;
  int get totalCount => _emergencyCount + _alertsCount + _reportsCount + _placesCount;

  List<AroundMeItem> get allItems {
    return [..._emergency, ..._alerts, ..._reports, ..._places];
  }

  Future<void> fetchAroundMe(double lat, double lng, {double radiusKm = 10}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await ApiClient.instance.dio.get(
        '/around-me',
        queryParameters: {
          'lat': lat,
          'lng': lng,
          'radius_km': radiusKm,
        },
      );

      if (response.data['success'] == true) {
        final data = response.data['data'];
        final summary = data['summary'] ?? {};

        _emergency = (data['emergency'] as List? ?? [])
            .map((e) => AroundMeItem.fromJson(e))
            .toList();
        _alerts = (data['alerts'] as List? ?? [])
            .map((e) => AroundMeItem.fromJson(e))
            .toList();
        _reports = (data['reports'] as List? ?? [])
            .map((e) => AroundMeItem.fromJson(e))
            .toList();
        _places = (data['places'] as List? ?? [])
            .map((e) => AroundMeItem.fromJson(e))
            .toList();

        _emergencyCount = summary['emergency_count'] ?? _emergency.length;
        _alertsCount = summary['alerts_count'] ?? _alerts.length;
        _reportsCount = summary['reports_count'] ?? _reports.length;
        _placesCount = summary['places_count'] ?? _places.length;
      } else {
        _error = 'Failed to load data';
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
