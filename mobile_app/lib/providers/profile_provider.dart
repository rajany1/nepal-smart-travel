import 'dart:async';
import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/user.dart';
import '../core/models/profile_fields.dart';
import '../core/services/session_manager.dart';

/// Represents a single badge/achievement
class BadgeInfo {
  final String id;
  final String name;
  final String description;
  final String icon;
  final bool unlocked;

  BadgeInfo({
    required this.id,
    required this.name,
    required this.description,
    required this.icon,
    required this.unlocked,
  });

  factory BadgeInfo.fromJson(Map<String, dynamic> json) {
    return BadgeInfo(
      id: json['id'] ?? '',
      name: json['name'] ?? '',
      description: json['description'] ?? '',
      icon: json['icon'] ?? 'emoji_events',
      unlocked: json['unlocked'] ?? false,
    );
  }

  IconData get iconData {
    switch (icon) {
      case 'description': return Icons.description;
      case 'assignment': return Icons.assignment;
      case 'verified': return Icons.verified;
      case 'star': return Icons.star;
      case 'warning': return Icons.warning;
      case 'rate_review': return Icons.rate_review;
      case 'explore': return Icons.explore;
      case 'trending_up': return Icons.trending_up;
      case 'groups': return Icons.groups;
      case 'map': return Icons.map;
      case 'psychology': return Icons.psychology;
      case 'comment': return Icons.comment;
      case 'camera_alt': return Icons.camera_alt;
      case 'emergency': return Icons.emergency;
      default: return Icons.emoji_events;
    }
  }
}

/// Represents activity timeline item
class ActivityItem {
  final String id;
  final String type;
  final String action;
  final String title;
  final String description;
  final String? status;
  final String? severity;
  final int? rating;
  final DateTime createdAt;

  ActivityItem({
    required this.id,
    required this.type,
    required this.action,
    required this.title,
    this.description = '',
    this.status,
    this.severity,
    this.rating,
    required this.createdAt,
  });

  factory ActivityItem.fromJson(Map<String, dynamic> json) {
    return ActivityItem(
      id: json['id']?.toString() ?? '',
      type: json['type'] ?? '',
      action: json['action'] ?? '',
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      status: json['status'],
      severity: json['severity'],
      rating: json['rating'],
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }

  IconData get icon {
    switch (type) {
      case 'report': return Icons.assignment;
      case 'alert': return Icons.warning_amber;
      case 'review': return Icons.rate_review;
      default: return Icons.circle;
    }
  }

  Color get iconColor {
    switch (type) {
      case 'report':
        return status == 'approved' ? Colors.green :
               status == 'rejected' ? Colors.red : Colors.orange;
      case 'alert':
        return severity == 'critical' ? Colors.red :
               severity == 'high' ? Colors.orange :
               severity == 'medium' ? Colors.yellow.shade700 : Colors.blue;
      case 'review':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  String get formattedDate {
    final now = DateTime.now();
    final diff = now.difference(createdAt);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inHours < 1) return '${diff.inMinutes}m ago';
    if (diff.inDays < 1) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${createdAt.day}/${createdAt.month}/${createdAt.year}';
  }
}

/// Profile stats breakdown
class ProfileStats {
  final Map<String, int> reportsByStatus;
  final Map<String, int> alertsBySeverity;
  final Map<String, int> xpBreakdown;
  final List<Map<String, dynamic>> monthlyActivity;

  ProfileStats({
    this.reportsByStatus = const {},
    this.alertsBySeverity = const {},
    this.xpBreakdown = const {},
    this.monthlyActivity = const [],
  });

  factory ProfileStats.fromJson(Map<String, dynamic> json) {
    return ProfileStats(
      reportsByStatus: Map<String, int>.from(
        (json['reports_by_status'] as Map?)?.map(
          (k, v) => MapEntry(k.toString(), (v as num).toInt())
        ) ?? {},
      ),
      alertsBySeverity: Map<String, int>.from(
        (json['alerts_by_severity'] as Map?)?.map(
          (k, v) => MapEntry(k.toString(), (v as num).toInt())
        ) ?? {},
      ),
      xpBreakdown: Map<String, int>.from(
        (json['xp_breakdown'] as Map?)?.map(
          (k, v) => MapEntry(k.toString(), (v as num).toInt())
        ) ?? {},
      ),
      monthlyActivity: List<Map<String, dynamic>>.from(
        (json['monthly_activity'] as List?) ?? []
      ),
    );
  }
}

/// Full profile data model from /profile endpoint
class FullProfileData {
  final String userId;
  final String name;
  final String email;
  final String? phone;
  final String? avatarUrl;
  final String? bio;
  final String role;
  final String status;
  final String? gender;
  final String? interest;
  final bool profileCompleted;
  final DateTime? createdAt;
  final int memberSinceDays;
  
  // XP & Level
  final int totalXp;
  final int currentLevel;
  final String levelName;
  final String nextLevelName;
  final int nextLevelXp;
  final double levelProgress;
  final int rank;
  
  // Stats
  final int totalReports;
  final int approvedReports;
  final int rejectedReports;
  final int pendingReports;
  final double approvalRate;
  final int totalAlerts;
  final int totalComments;
  final int totalReviews;
  
  // Verification
  final String verificationTick;
  
  // Badges
  final List<BadgeInfo> badges;
  final List<String> expertiseRegions;
  
  // Activity
  final List<ActivityItem> recentActivity;
  
  final DateTime? lastContributionAt;

  FullProfileData({
    required this.userId,
    required this.name,
    required this.email,
    this.phone,
    this.avatarUrl,
    this.bio,
    this.role = 'user',
    this.status = 'active',
    this.gender,
    this.interest,
    this.profileCompleted = false,
    this.createdAt,
    this.memberSinceDays = 0,
    this.totalXp = 0,
    this.currentLevel = 1,
    this.levelName = 'Explorer',
    this.nextLevelName = 'Contributor',
    this.nextLevelXp = 50,
    this.levelProgress = 0,
    this.rank = 0,
    this.totalReports = 0,
    this.approvedReports = 0,
    this.rejectedReports = 0,
    this.pendingReports = 0,
    this.approvalRate = 0,
    this.totalAlerts = 0,
    this.totalComments = 0,
    this.totalReviews = 0,
    this.verificationTick = 'none',
    this.badges = const [],
    this.expertiseRegions = const [],
    this.recentActivity = const [],
    this.lastContributionAt,
  });

  factory FullProfileData.fromJson(Map<String, dynamic> json) {
    final data = json['data'] is Map ? json['data'] : json;
    
    // Helper to safely parse numeric values
    int parseIntValue(dynamic value) {
      if (value == null) return 0;
      if (value is int) return value;
      if (value is double) return value.toInt();
      if (value is String) {
        try {
          return int.parse(value);
        } catch (e) {
          return 0;
        }
      }
      return 0;
    }
    
    double parseDoubleValue(dynamic value) {
      if (value == null) return 0.0;
      if (value is double) return value;
      if (value is int) return value.toDouble();
      if (value is String) {
        try {
          return double.parse(value);
        } catch (e) {
          return 0.0;
        }
      }
      return 0.0;
    }
    
    return FullProfileData(
      userId: data['user_id']?.toString() ?? '',
      name: data['name'] ?? '',
      email: data['email'] ?? '',
      phone: data['phone'],
      avatarUrl: data['avatar_url'],
      bio: data['bio'],
      role: data['role'] ?? 'user',
      status: data['status'] ?? 'active',
      gender: data['gender'],
      interest: data['interest'],
      profileCompleted: data['profile_completed'] ?? false,
      createdAt: data['created_at'] != null
          ? DateTime.parse(data['created_at'])
          : null,
      memberSinceDays: parseIntValue(data['member_since_days']),
      totalXp: parseIntValue(data['total_xp']),
      currentLevel: parseIntValue(data['current_level']),
      levelName: data['level_name'] ?? 'Explorer',
      nextLevelName: data['next_level_name'] ?? 'Contributor',
      nextLevelXp: parseIntValue(data['next_level_xp']),
      levelProgress: parseDoubleValue(data['level_progress']),
      rank: parseIntValue(data['rank']),
      totalReports: parseIntValue(data['total_reports']),
      approvedReports: parseIntValue(data['approved_reports']),
      rejectedReports: parseIntValue(data['rejected_reports']),
      pendingReports: parseIntValue(data['pending_reports']),
      approvalRate: parseDoubleValue(data['approval_rate']),
      totalAlerts: parseIntValue(data['total_alerts']),
      totalComments: parseIntValue(data['total_comments']),
      totalReviews: parseIntValue(data['total_reviews']),
      verificationTick: data['verification_tick'] ?? 'none',
      badges: (data['badges'] as List?)
          ?.map((b) => BadgeInfo.fromJson(b is Map ? Map<String, dynamic>.from(b) : <String, dynamic>{'id': b.toString(), 'name': b.toString(), 'description': '', 'icon': 'emoji_events', 'unlocked': true}))
          .toList() ?? [],
      expertiseRegions: List<String>.from(data['expertise_regions'] ?? []),
      recentActivity: (data['recent_activity'] as List?)
          ?.map((a) => ActivityItem.fromJson(a is Map ? Map<String, dynamic>.from(a) : <String, dynamic>{}))
          .toList() ?? [],
      lastContributionAt: data['last_contribution_at'] != null
          ? DateTime.parse(data['last_contribution_at'])
          : null,
    );
  }
}

class ProfileProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;
  final SessionManager _session = SessionManager.instance;

  FullProfileData? _profileData;
  ProfileStats? _profileStats;
  List<BadgeInfo> _allBadges = [];
  List<ActivityItem> _activity = [];
  Map<String, dynamic> _settings = {};
  
  // Dynamic field definitions and options
  List<ProfileFieldDefinition> _fieldDefinitions = [];
  ProfileFieldOptions? _fieldOptions;

  bool _isLoading = false;
  bool _isStatsLoading = false;
  bool _isBadgesLoading = false;
  bool _isActivityLoading = false;
  bool _isFieldsLoading = false;
  String? _errorMessage;
  bool _isRefreshing = false;
  Timer? _pollTimer;

  // Getters
  FullProfileData? get profile => _profileData;
  ProfileStats? get stats => _profileStats;
  List<BadgeInfo> get allBadges => _allBadges;
  List<ActivityItem> get activity => _activity;
  Map<String, dynamic> get settings => _settings;
  List<ProfileFieldDefinition> get fieldDefinitions => _fieldDefinitions;
  ProfileFieldOptions? get fieldOptions => _fieldOptions;
  bool get isLoading => _isLoading;
  bool get isStatsLoading => _isStatsLoading;
  bool get isBadgesLoading => _isBadgesLoading;
  bool get isActivityLoading => _isActivityLoading;
  bool get isFieldsLoading => _isFieldsLoading;
  String? get errorMessage => _errorMessage;
  bool get isRefreshing => _isRefreshing;

  /// Get unlocked badges count
  int get unlockedBadgesCount => _profileData?.badges.where((b) => b.unlocked).length ?? 0;
  
  /// Get total badges count
  int get totalBadgesCount => _profileData?.badges.length ?? _allBadges.length;

  /// Load full profile data
  Future<void> loadProfile({bool forceRefresh = false}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.getFullProfile();
      _profileData = FullProfileData.fromJson(response.data);
      
      // Update persisted user data with latest profile info
      if (_profileData != null) {
        final userModel = UserModel(
          id: _profileData!.userId,
          name: _profileData!.name,
          email: _profileData!.email,
          phone: _profileData!.phone,
          avatarUrl: _profileData!.avatarUrl,
          bio: _profileData!.bio,
          totalXp: _profileData!.totalXp,
          currentLevel: _profileData!.currentLevel,
          verificationTick: _profileData!.verificationTick,
          badges: _profileData!.badges.where((b) => b.unlocked).map((b) => b.name).toList(),
          expertiseRegions: _profileData!.expertiseRegions,
          totalReports: _profileData!.totalReports,
          approvedReports: _profileData!.approvedReports,
          approvalRate: _profileData!.approvalRate,
          rank: _profileData!.rank,
          lastContributionAt: _profileData!.lastContributionAt,
          status: _profileData!.status,
          role: _profileData!.role,
          profileCompleted: _profileData!.profileCompleted,
          createdAt: _profileData!.createdAt ?? DateTime.now(),
        );
        await _session.setUser(userModel);
      }
      
      _errorMessage = null;
    } catch (e) {
      print('❌ Error loading profile: $e');
      // Try to fallback to existing user data
      if (_profileData == null) {
        _errorMessage = 'Failed to load profile';
      }
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Load profile stats
  Future<void> loadStats() async {
    _isStatsLoading = true;
    notifyListeners();

    try {
      final response = await _api.getProfileStats();
      final dataMap = response.data['data'];
      _profileStats = ProfileStats.fromJson(
        dataMap is Map ? Map<String, dynamic>.from(dataMap) : {}
      );
    } catch (e) {
      print('❌ Error loading stats: $e');
    }

    _isStatsLoading = false;
    notifyListeners();
  }

  /// Load all badges
  Future<void> loadBadges() async {
    _isBadgesLoading = true;
    notifyListeners();

    try {
      final response = await _api.getProfileBadges();
      final List<dynamic> badgeList = response.data['data'] ?? [];
      _allBadges = badgeList.map((b) => BadgeInfo.fromJson(b is Map ? Map<String, dynamic>.from(b) : <String, dynamic>{})).toList();
    } catch (e) {
      print('❌ Error loading badges: $e');
    }

    _isBadgesLoading = false;
    notifyListeners();
  }

  /// Load activity timeline
  Future<void> loadActivity({int limit = 20}) async {
    _isActivityLoading = true;
    notifyListeners();

    try {
      final response = await _api.getProfileActivity(limit: limit);
      final List<dynamic> activityList = response.data['data'] ?? [];
      _activity = activityList.map((a) => ActivityItem.fromJson(a is Map ? Map<String, dynamic>.from(a) : <String, dynamic>{})).toList();
    } catch (e) {
      print('❌ Error loading activity: $e');
    }

    _isActivityLoading = false;
    notifyListeners();
  }

  /// Refresh all profile data
  Future<void> refreshAll() async {
    _isRefreshing = true;
    notifyListeners();

    try {
      await loadProfile(forceRefresh: true);
    } catch (e) {
      debugPrint("refreshAll error: $e");
    }

    _isRefreshing = false;
    notifyListeners();
  }

  /// Update profile fields
  Future<bool> updateProfile({
    String? name,
    String? phone,
    String? bio,
    String? gender,
    String? interest,
    List<String>? expertiseRegions,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = <String, dynamic>{};
      if (name != null) data['name'] = name;
      if (phone != null) data['phone'] = phone;
      if (bio != null) data['bio'] = bio;
      if (gender != null) data['gender'] = gender;
      if (interest != null) data['interest'] = interest;
      if (expertiseRegions != null) data['expertise_regions'] = expertiseRegions;

      final response = await _api.updateProfileData(data);
      
      if (response.data['success'] == true) {
        // Reload profile after update
        await loadProfile(forceRefresh: true);
        return true;
      }
      
      _errorMessage = 'Failed to update profile';
      return false;
    } catch (e) {
      print('❌ Error updating profile: $e');
      _errorMessage = 'Failed to update profile';
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Update avatar
  Future<bool> updateAvatar(String avatarUrl) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.updateProfileAvatar(avatarUrl);
      if (response.data['success'] == true) {
        await loadProfile(forceRefresh: true);
        return true;
      }
      _errorMessage = 'Failed to update avatar';
      return false;
    } catch (e) {
      print('❌ Error updating avatar: $e');
      _errorMessage = 'Failed to update avatar';
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Load user settings
  Future<void> loadSettings() async {
    try {
      final response = await _api.getUserSettings();
      _settings = response.data['data'] ?? {};
      notifyListeners();
    } catch (e) {
      print('❌ Error loading settings: $e');
    }
  }

  /// Update user settings
  Future<bool> updateSettings(Map<String, dynamic> newSettings) async {
    try {
      final response = await _api.updateUserSettings(newSettings);
      if (response.data['success'] == true) {
        _settings = newSettings;
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      print('❌ Error updating settings: $e');
      return false;
    }
  }

  /// Load profile field definitions (schema for form building)
  Future<void> loadFieldDefinitions() async {
    _isFieldsLoading = true;
    notifyListeners();

    try {
      final response = await _api.getProfileFieldDefinitions();
      final fieldDefResponse = ProfileFieldDefinitionsResponse.fromJson(response.data);
      _fieldDefinitions = fieldDefResponse.fields;
      print('✅ Loaded ${_fieldDefinitions.length} profile field definitions');
    } catch (e) {
      print('❌ Error loading field definitions: $e');
    }

    _isFieldsLoading = false;
    notifyListeners();
  }

  /// Load profile field options (for dropdowns and multi-selects)
  Future<void> loadFieldOptions() async {
    _isFieldsLoading = true;
    notifyListeners();

    try {
      final response = await _api.getProfileFieldOptions();
      _fieldOptions = ProfileFieldOptionsResponse.fromJson(response.data).options;
      print('✅ Loaded profile field options');
    } catch (e) {
      print('❌ Error loading field options: $e');
    }

    _isFieldsLoading = false;
    notifyListeners();
  }

  /// Load both field definitions and options
  Future<void> loadFieldSchemas() async {
    _isFieldsLoading = true;
    notifyListeners();

    try {
      await Future.wait([
        loadFieldDefinitions(),
        loadFieldOptions(),
      ]);
    } catch (e) {
      print('❌ Error loading field schemas: $e');
    }

    _isFieldsLoading = false;
    notifyListeners();
  }

  /// Start auto-refresh timer (polls every 15s)
  void startAutoRefresh() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 15), (_) {
      refreshAll();
    });
  }

  /// Stop auto-refresh timer
  void stopAutoRefresh() {
    _pollTimer?.cancel();
    _pollTimer = null;
  }

  /// Clear error
  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  // ============ Public Profile ============
  Map<String, dynamic>? _publicProfile;
  Map<String, dynamic>? get publicProfile => _publicProfile;
  bool _isLoadingPublic = false;
  bool get isLoadingPublic => _isLoadingPublic;

  Future<void> loadPublicProfile(String userId) async {
    _isLoadingPublic = true;
    notifyListeners();
    try {
      final response = await ApiClient.instance.getUserProfile(userId);
      _publicProfile = response.data['data'] as Map<String, dynamic>?;
    } catch (e) {
      _publicProfile = null;
    }
    _isLoadingPublic = false;
    notifyListeners();
  }

  /// Reset entire provider
  void reset() {
    stopAutoRefresh();
    _profileData = null;
    _profileStats = null;
    _allBadges = [];
    _activity = [];
    _settings = {};
    _isLoading = false;
    _isStatsLoading = false;
    _isBadgesLoading = false;
    _isActivityLoading = false;
    _errorMessage = null;
    _isRefreshing = false;
    notifyListeners();
  }
}