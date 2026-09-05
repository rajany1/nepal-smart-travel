import 'package:flutter/foundation.dart';

class ReportModel {
  final String id;
  final String uuid;
  final String title;
  final String description;
  final int categoryId;
  final String categoryName;
  final String? categoryIcon;
  final String priority;
  final String status;
  final double? latitude;
  final double? longitude;
  final String? district;
  final String? locationName;
  final String? reportSubcategory;
  final bool isActive;
  final DateTime? expiresAt;
  final int confirmedByCount;
  final DateTime? lastConfirmedAt;
  final int helpfulCount;
  final int unhelpfulCount;
  final int commentsCount;
  final String reporterName;
  final String? reporterAvatar;
  final String reporterId;
  final String? userReaction;
  final List<String> imageUrls;
  final DateTime createdAt;
  final DateTime updatedAt;
  final String timeAgo;

  ReportModel({
    required this.id,
    required this.uuid,
    required this.title,
    required this.description,
    required this.categoryId,
    required this.categoryName,
    this.categoryIcon,
    this.priority = 'medium',
    this.status = 'pending',
    this.latitude,
    this.longitude,
    this.district,
    this.locationName,
    this.reportSubcategory,
    this.isActive = true,
    this.expiresAt,
    this.confirmedByCount = 0,
    this.lastConfirmedAt,
    this.helpfulCount = 0,
    this.unhelpfulCount = 0,
    this.commentsCount = 0,
    this.reporterName = 'Anonymous',
    this.reporterAvatar,
    this.reporterId = '',
    this.userReaction,
    this.imageUrls = const [],
    DateTime? createdAt,
    DateTime? updatedAt,
    this.timeAgo = '',
  })  : createdAt = createdAt ?? DateTime.now(),
        updatedAt = updatedAt ?? DateTime.now();

  factory ReportModel.fromJson(Map<String, dynamic> json) {
    return ReportModel(
      id: (json['id'] ?? '').toString(),
      uuid: json['uuid'] ?? '',
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      categoryId: json['category_id'] ?? 0,
      categoryName: json['category_name'] ?? 'General',
      categoryIcon: json['category_icon'],
      priority: json['priority'] ?? 'medium',
      status: json['status'] ?? 'pending',
      latitude: (() {
        final raw = json['latitude'];
        if (raw == null) return null;
        final parsed = double.tryParse(raw.toString());
        return (parsed != null && parsed != 0.0) ? parsed : null;
      })(),
      longitude: (() {
        final raw = json['longitude'];
        if (raw == null) return null;
        final parsed = double.tryParse(raw.toString());
        return (parsed != null && parsed != 0.0) ? parsed : null;
      })(),
      district: json['district'],
      locationName: json['location_name'],
      reportSubcategory: json['report_subcategory'],
      isActive: json['is_active'] ?? true,
      expiresAt: json['expires_at'] != null ? DateTime.tryParse(json['expires_at']) : null,
      confirmedByCount: json['confirmed_by_count'] ?? 0,
      lastConfirmedAt: json['last_confirmed_at'] != null ? DateTime.tryParse(json['last_confirmed_at']) : null,
      helpfulCount: json['helpful_count'] ?? 0,
      unhelpfulCount: json['unhelpful_count'] ?? 0,
      commentsCount: json['comments_count'] ?? 0,
      reporterName: json['reporter_name'] ?? 'Anonymous',
      reporterAvatar: json['reporter_avatar'],
      reporterId: (json['reporter_id'] ?? '').toString(),
      userReaction: json['user_reaction'],
      imageUrls: (() {
        final rawList = json['image_urls'];
        if (rawList is List && rawList.isNotEmpty) {
          final urls = <String>[];
          for (final item in rawList) {
            if (item is String && item.isNotEmpty) {
              urls.add(item);
            }
          }
          if (urls.isNotEmpty) return urls;
        }
        if (json['image_url'] != null && json['image_url'].toString().isNotEmpty) {
          return [json['image_url'].toString()];
        }
        return <String>[];
      })(),
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'])
          : DateTime.now(),
      timeAgo: json['time_ago'] ?? '',
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'uuid': uuid,
        'title': title,
        'description': description,
        'category_id': categoryId,
        'category_name': categoryName,
        'category_icon': categoryIcon,
        'priority': priority,
        'status': status,
        'latitude': latitude,
        'longitude': longitude,
        'district': district,
        'helpful_count': helpfulCount,
        'comments_count': commentsCount,
        'reporter_name': reporterName,
        'reporter_avatar': reporterAvatar,
        'image_urls': imageUrls,
      };

  bool get isApproved => status == 'approved';
  bool get isPending => status == 'pending';
  bool get isRejected => status == 'rejected';
  bool get isEmergency => priority == 'high' || priority == 'critical';

  String? get imageUrl => imageUrls.isNotEmpty ? imageUrls[0] : null;

  int get confidenceScore {
    int confirmBonus = (confirmedByCount * 15).clamp(0, 100);
    return (confirmBonus).clamp(0, 100);
  }

  String get timeState {
    final now = DateTime.now();
    final diff = now.difference(createdAt);
    if (diff.inMinutes < 60) return 'live';
    if (createdAt.year == now.year && createdAt.month == now.month && createdAt.day == now.day) return 'today';
    if (diff.inDays <= 7) return 'recent';
    return 'expired';
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

  /// Map priority to a numeric severity for sorting
  int get priorityLevel {
    switch (priority) {
      case 'critical':
        return 4;
      case 'high':
        return 3;
      case 'medium':
        return 2;
      case 'low':
        return 1;
      default:
        return 0;
    }
  }
}

class ReportCategory {
  final int id;
  final String name;
  final String? icon;

  ReportCategory({
    required this.id,
    required this.name,
    this.icon,
  });

  factory ReportCategory.fromJson(Map<String, dynamic> json) {
    return ReportCategory(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      icon: json['icon'],
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'icon': icon,
      };
}