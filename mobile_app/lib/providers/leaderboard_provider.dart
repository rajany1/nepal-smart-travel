import 'package:flutter/material.dart';
import '../core/api/api_client.dart';

class LeaderboardUser {
  final int rank;
  final String userId;
  final String name;
  final String? avatarUrl;
  final int totalXp;
  final int currentLevel;
  final String levelName;
  final int approvedReports;
  final int totalReports;
  final int totalAlerts;
  final int totalReviews;
  final int placesContributed;
  final int correctionsApplied;
  final int contributionScore;
  final double totalEarned;
  final int badgeCount;
  final String verificationTick;

  LeaderboardUser({
    required this.rank,
    required this.userId,
    required this.name,
    this.avatarUrl,
    this.totalXp = 0,
    this.currentLevel = 1,
    this.levelName = 'Explorer',
    this.approvedReports = 0,
    this.totalReports = 0,
    this.totalAlerts = 0,
    this.totalReviews = 0,
    this.placesContributed = 0,
    this.correctionsApplied = 0,
    this.contributionScore = 0,
    this.totalEarned = 0,
    this.badgeCount = 0,
    this.verificationTick = 'gray',
  });

  factory LeaderboardUser.fromJson(Map<String, dynamic> json) {
    return LeaderboardUser(
      rank: json['rank'] ?? 0,
      userId: json['user_id']?.toString() ?? '',
      name: json['name'] ?? 'Anonymous',
      avatarUrl: json['avatar_url'],
      totalXp: json['total_xp'] ?? 0,
      currentLevel: json['current_level'] ?? 1,
      levelName: json['level_name'] ?? 'Explorer',
      approvedReports: json['approved_reports'] ?? 0,
      totalReports: json['total_reports'] ?? 0,
      totalAlerts: json['total_alerts'] ?? 0,
      totalReviews: json['total_reviews'] ?? 0,
      placesContributed: json['places_contributed'] ?? 0,
      correctionsApplied: json['corrections_applied'] ?? 0,
      contributionScore: json['contribution_score'] ?? 0,
      totalEarned: (json['total_earned'] ?? 0).toDouble(),
      badgeCount: json['badge_count'] ?? 0,
      verificationTick: json['verification_tick'] ?? 'gray',
    );
  }
}

class LeaderboardProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  /// Only the top 10 contributors are listed; everyone else just sees their
  /// own position (rank card).
  static const int topCount = 10;

  List<LeaderboardUser> _users = [];
  bool _isLoading = false;
  String? _errorMessage;
  String _activeCategory = 'xp';
  int? _userRank;
  int _totalUsers = 0;

  List<LeaderboardUser> get users => _users;
  List<LeaderboardUser> get topThree => _users.take(3).toList();
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String get activeCategory => _activeCategory;
  int? get userRank => _userRank;
  int get totalUsers => _totalUsers;

  Future<void> fetchLeaderboard({
    String category = 'xp',
    bool refresh = true,
  }) async {
    if (refresh) {
      _isLoading = true;
      _activeCategory = category;
    }
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.dio.get(
        '/leaderboard',
        queryParameters: {'category': category, 'limit': topCount, 'offset': 0},
      );

      final data = response.data['data'] as List? ?? [];
      final meta = response.data['meta'] as Map<String, dynamic>? ?? {};

      _users = data.map((j) => LeaderboardUser.fromJson(j)).toList();
      _userRank = meta['user_rank'];
      _totalUsers =
          (meta['system_total_users'] as num?)?.toInt() ?? _users.length;
    } catch (e) {
      debugPrint('❌ Failed to fetch leaderboard: $e');
      _errorMessage = 'Failed to load leaderboard';
    }

    _isLoading = false;
    notifyListeners();
  }

  void switchCategory(String category) {
    if (_activeCategory != category) {
      fetchLeaderboard(category: category, refresh: true);
    }
  }

  Future<void> refreshAll() =>
      fetchLeaderboard(category: _activeCategory, refresh: true);
}
