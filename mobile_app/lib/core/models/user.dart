class UserModel {
  final String id;
  final String name;
  final String email;
  final String? phone;
  final String? avatarUrl;
  final String? bio;
  final int totalXp;
  final int currentLevel;
  final String verificationTick;
  final List<String> badges;
  final List<String> expertiseRegions;
  final int totalReports;
  final int approvedReports;
  final double approvalRate;
  final int rank;
  final DateTime? lastContributionAt;
  final String status;
  final String role;
  final String roleDisplay;
  final List<String> permissions;
  final bool profileCompleted;
  final DateTime createdAt;

  UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.avatarUrl,
    this.bio,
    this.totalXp = 0,
    this.currentLevel = 1,
    this.verificationTick = 'none',
    this.badges = const [],
    this.expertiseRegions = const [],
    this.totalReports = 0,
    this.approvedReports = 0,
    this.approvalRate = 0,
    this.rank = 0,
    this.lastContributionAt,
    this.status = 'active',
    this.role = 'user',
    this.roleDisplay = 'User',
    this.permissions = const [],
    this.profileCompleted = false,
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  factory UserModel.fromJson(Map<String, dynamic> json) {
    // Helper to safely convert approval_rate (can be string or number)
    double parseApprovalRate(dynamic value) {
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

    return UserModel(
      id: (json['user_id'] ?? json['id'] ?? '').toString(),
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone'],
      avatarUrl: json['avatar_url'],
      bio: json['bio'],
      totalXp: json['total_xp'] ?? 0,
      currentLevel: json['current_level'] ?? 1,
      verificationTick: json['verification_tick'] ?? 'gray',
      badges: List<String>.from(json['badges'] ?? []),
      expertiseRegions: List<String>.from(json['expertise_regions'] ?? []),
      totalReports: json['total_reports'] ?? 0,
      approvedReports: json['approved_reports'] ?? 0,
      approvalRate: parseApprovalRate(json['approval_rate']),
      rank: json['rank'] ?? 0,
      lastContributionAt: json['last_contribution_at'] != null
          ? DateTime.parse(json['last_contribution_at'])
          : null,
      status: json['status'] ?? 'active',
      role: json['role'] ?? 'user',
      roleDisplay: json['role_display'] as String? ?? 'User',
      permissions: json['permissions'] is List
          ? List<String>.from(json['permissions'])
          : [],
      profileCompleted: json['profile_completed'] ?? false,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'user_id': id,
      'name': name,
      'email': email,
      'phone': phone,
      'avatar_url': avatarUrl,
      'bio': bio,
      'total_xp': totalXp,
      'current_level': currentLevel,
      'verification_tick': verificationTick,
      'badges': badges,
      'expertise_regions': expertiseRegions,
      'total_reports': totalReports,
      'approved_reports': approvedReports,
      'approval_rate': approvalRate,
      'rank': rank,
      'last_contribution_at': lastContributionAt?.toIso8601String(),
      'role': role,
      'role_display': roleDisplay,
      'permissions': permissions,
      'status': status,
      'profile_completed': profileCompleted,
      'created_at': createdAt.toIso8601String(),
    };
  }

  String get levelName {
    if (currentLevel <= 5) return 'Explorer';
    if (currentLevel <= 15) return 'Contributor';
    if (currentLevel <= 30) return 'Trusted Local';
    if (currentLevel <= 50) return 'Regional Guide';
    if (currentLevel <= 100) return 'Community Expert';
    return 'Legendary Hero';
  }

  String get nextLevelName {
    if (currentLevel <= 5) return 'Contributor';
    if (currentLevel <= 15) return 'Trusted Local';
    if (currentLevel <= 30) return 'Regional Guide';
    if (currentLevel <= 50) return 'Community Expert';
    if (currentLevel <= 100) return 'Legendary Hero';
    return 'Max Level';
  }

  int get nextLevelXp {
    if (currentLevel <= 5) return 50;
    if (currentLevel <= 15) return 150;
    if (currentLevel <= 30) return 300;
    if (currentLevel <= 50) return 500;
    if (currentLevel <= 100) return 1000;
    return 0;
  }

  double get levelProgress {
    int xpForLevel(int level) {
      if (level <= 5) return 50;
      if (level <= 15) return 150;
      if (level <= 30) return 300;
      if (level <= 50) return 500;
      if (level <= 100) return 1000;
      return 0;
    }

    int cumulativeXpBeforeCurrentLevel() {
      int cumulative = 0;
      for (int i = 1; i < currentLevel; i++) {
        cumulative += xpForLevel(i);
      }
      return cumulative;
    }

    final nextXp = nextLevelXp;
    if (nextXp == 0) return 1.0;

    final cumulativeBefore = cumulativeXpBeforeCurrentLevel();
    final xpInCurrentLevel = (totalXp - cumulativeBefore).clamp(0, totalXp);
    return (xpInCurrentLevel / nextXp).clamp(0.0, 1.0);
  }
}

class AuthResponse {
  final bool success;
  final String? message;
  final String? userId;
  final String? accessToken;
  final String? refreshToken;
  final int? expiresIn;
  final Map<String, dynamic>? userData;
  final String? error;
  final Map<String, List<String>>? validationErrors;

  AuthResponse({
    required this.success,
    this.message,
    this.userId,
    this.accessToken,
    this.refreshToken,
    this.expiresIn,
    this.userData,
    this.error,
    this.validationErrors,
  });

  factory AuthResponse.fromJson(Map<String, dynamic> json) {
    // Extract user data - can be in 'data' or at root level
    final userData = json['data'] is Map ? json['data'] : null;

    return AuthResponse(
      success: json['success'] ?? false,
      message: json['message'],
      // ✅ access_token is at ROOT level
      accessToken: json['access_token'] ?? json['token'],
      refreshToken: json['refresh_token'],
      expiresIn: json['expires_in'],
      userId: userData?['id']?.toString() ?? userData?['user_id']?.toString(),
      userData: userData,
      error: json['error'],
      validationErrors: json['errors'] is Map
          ? Map<String, List<String>>.from(
              (json['errors'] as Map).map(
                (key, value) => MapEntry(
                  key.toString(),
                  value is List
                      ? List<String>.from(value.map((e) => e.toString()))
                      : [value.toString()],
                ),
              ),
            )
          : null,
    );
  }

  /// Get a user-friendly error message
  String getErrorMessage() {
    if (error != null) return error!;
    if (validationErrors != null && validationErrors!.isNotEmpty) {
      // Get first validation error
      final firstField = validationErrors!.entries.first;
      return '${firstField.key}: ${firstField.value.first}';
    }
    return message ?? 'Authentication failed';
  }
}