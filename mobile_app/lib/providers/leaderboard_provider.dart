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
    this.badgeCount = 0,
    this.verificationTick = 'none',
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
      badgeCount: json['badge_count'] ?? 0,
      verificationTick: json['verification_tick'] ?? 'none',
    );
  }
}

class LeaderboardProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<LeaderboardUser> _users = [];
  List<LeaderboardUser> _topThree = [];
  bool _isLoading = false;
  String? _errorMessage;
  bool _hasMore = true;
  int _currentOffset = 0;
  String _activeCategory = 'xp';
  int? _userRank;

  static const int _pageSize = 50;

  List<LeaderboardUser> get users => _users;
  List<LeaderboardUser> get topThree => _topThree;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get hasMore => _hasMore;
  String get activeCategory => _activeCategory;
  int? get userRank => _userRank;

  Future<void> fetchTopThree() async {
    try {
      final response = await _api.dio.get('/leaderboard/top');
      final data = response.data['data'] as List? ?? [];
      _topThree = data.map((j) => LeaderboardUser.fromJson(j)).toList();
      notifyListeners();
    } catch (e) {
      print('⚠️ Failed to fetch top three: $e');
    }
  }

  Future<void> fetchLeaderboard({
    String category = 'xp',
    bool refresh = true,
  }) async {
    if (refresh) {
      _isLoading = true;
      _currentOffset = 0;
      _hasMore = true;
      _activeCategory = category;
    }
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.dio.get('/leaderboard', queryParameters: {
        'category': category,
        'limit': _pageSize,
        'offset': _currentOffset,
      });

      final data = response.data['data'] as List? ?? [];
      final meta = response.data['meta'] as Map<String, dynamic>? ?? {};

      final newUsers = data.map((j) => LeaderboardUser.fromJson(j)).toList();

      if (refresh) {
        _users = newUsers;
      } else {
        _users.addAll(newUsers);
      }

      _userRank = meta['user_rank'];
      _hasMore = meta['has_more'] ?? false;
      _currentOffset = _users.length;
    } catch (e) {
      print('❌ Failed to fetch leaderboard: $e');
      _errorMessage = 'Failed to load leaderboard';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadMore() async {
    if (_isLoading || !_hasMore) return;
    await fetchLeaderboard(category: _activeCategory, refresh: false);
  }

  void switchCategory(String category) {
    if (_activeCategory != category) {
      fetchLeaderboard(category: category, refresh: true);
      fetchTopThree();
    }
  }

  Future<void> refreshAll() async {
    await Future.wait([
      fetchTopThree(),
      fetchLeaderboard(category: _activeCategory, refresh: true),
    ]);
  }
}