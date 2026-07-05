import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../core/api/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../core/widgets/shimmer_loading.dart';

class UserPublicProfileScreen extends StatefulWidget {
  final String userId;

  const UserPublicProfileScreen({super.key, required this.userId});

  @override
  State<UserPublicProfileScreen> createState() => _UserPublicProfileScreenState();
}

class _UserPublicProfileScreenState extends State<UserPublicProfileScreen> {
  Map<String, dynamic>? _profile;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile() async {
    setState(() => _isLoading = true);
    try {
      final api = ApiClient.instance;
      final response = await api.getUserProfile(widget.userId);
      if (!mounted) return;
      setState(() {
        _profile = response.data['data'] as Map<String, dynamic>?;
        _isLoading = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _error = 'Failed to load profile';
      });
    }
  }

  Color _tickColor(String tick) {
    switch (tick) {
      case 'green': return AppTheme.greenTick;
      case 'blue': return AppTheme.blueTick;
      case 'gold': return AppTheme.goldTick;
      default: return AppTheme.grayTick;
    }
  }

  IconData _badgeIcon(String icon) {
    switch (icon) {
      case 'description': return Icons.description;
      case 'assignment': return Icons.assignment;
      case 'verified': return Icons.verified;
      case 'star': return Icons.star;
      case 'explore': return Icons.explore;
      case 'trending_up': return Icons.trending_up;
      case 'groups': return Icons.groups;
      case 'map': return Icons.map;
      case 'psychology': return Icons.psychology;
      default: return Icons.emoji_events;
    }
  }

  String _levelName(String name) {
    return name;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_profile != null ? _profile!['name'] as String : 'Profile'),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const SingleChildScrollView(
        physics: AlwaysScrollableScrollPhysics(),
        padding: EdgeInsets.all(16),
        child: ProfileShimmer(),
      );
    }
    if (_error != null || _profile == null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.person_off, size: 64, color: AppTheme.textSecondary),
            const SizedBox(height: 16),
            Text(_error ?? 'Profile not found', style: const TextStyle(color: AppTheme.textSecondary)),
            const SizedBox(height: 8),
            ElevatedButton.icon(
              onPressed: _loadProfile,
              icon: const Icon(Icons.refresh),
              label: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    final p = _profile!;
    final name = p['name'] as String? ?? '';
    final avatarUrl = p['avatar_url'] as String?;
    final bio = p['bio'] as String?;
    final verificationTick = p['verification_tick'] as String? ?? 'none';
    final totalXp = p['total_xp'] as int? ?? 0;
    final currentLevel = p['current_level'] as int? ?? 1;
    final levelName = p['level_name'] as String? ?? 'Explorer';
    final nextLevelName = p['next_level_name'] as String? ?? '';
    final nextLevelXp = p['next_level_xp'] as int? ?? 50;
    final levelProgress = (p['level_progress'] is num ? (p['level_progress'] as num).toDouble() : double.tryParse(p['level_progress']?.toString() ?? '')) ?? 0.0;
    final rank = p['rank'] is int ? p['rank'] as int : int.tryParse(p['rank']?.toString() ?? '') ?? 0;
    final totalReports = p['total_reports'] is int ? p['total_reports'] as int : int.tryParse(p['total_reports']?.toString() ?? '') ?? 0;
    final approvedReports = p['approved_reports'] is int ? p['approved_reports'] as int : int.tryParse(p['approved_reports']?.toString() ?? '') ?? 0;
    final approvalRate = p['approval_rate'] is num ? p['approval_rate'] as num : num.tryParse(p['approval_rate']?.toString() ?? '') ?? 0;
    final badges = p['badges'] as List? ?? [];
    final recentReports = p['recent_reports'] as List? ?? [];
    final isOwnProfile = context.read<AuthProvider>().user?.id == widget.userId;

    return RefreshIndicator(
      onRefresh: _loadProfile,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeader(name, avatarUrl, bio, verificationTick),
            const SizedBox(height: 16),
            _buildStatsCard(totalXp, currentLevel, levelName, rank, totalReports, approvedReports, approvalRate),
            const SizedBox(height: 16),
            _buildXpProgress(currentLevel, levelName, nextLevelName, nextLevelXp, levelProgress),
            if (verificationTick != 'none') ...[
              const SizedBox(height: 16),
              _buildVerificationTick(verificationTick),
            ],
            const SizedBox(height: 16),
            _buildBadgesSection(badges),
            if (recentReports.isNotEmpty) ...[
              const SizedBox(height: 16),
              _buildRecentReports(recentReports, name),
            ],
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(String name, String? avatarUrl, String? bio, String tick) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppTheme.primaryColor, AppTheme.primaryLight],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        children: [
          Stack(
            children: [
              CircleAvatar(
                radius: 44,
                backgroundColor: Colors.white.withValues(alpha: 0.2),
                backgroundImage: avatarUrl != null && avatarUrl.isNotEmpty
                    ? NetworkImage(avatarUrl)
                    : null,
                child: avatarUrl == null || avatarUrl.isEmpty
                    ? Text(
                        name.isNotEmpty ? name[0].toUpperCase() : '?',
                        style: const TextStyle(fontSize: 36, color: Colors.white, fontWeight: FontWeight.bold),
                      )
                    : null,
              ),
              if (tick != 'none')
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(Icons.verified, color: _tickColor(tick), size: 20),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(name, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, color: Colors.white)),
          if (bio != null && bio.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(bio, style: const TextStyle(fontSize: AppTheme.textBase, color: Colors.white70), textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis),
          ],
        ],
      ),
    );
  }

  Widget _buildStatsCard(int totalXp, int currentLevel, String levelName, int rank, int totalReports, int approvedReports, num approvalRate) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Statistics', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          Row(
            children: [
              _statItem(Icons.emoji_events, '$totalXp', 'XP'),
              _statItem(Icons.stars, '$currentLevel', 'Level'),
              _statItem(Icons.trending_up, '#$rank', 'Rank'),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              _statItem(Icons.assignment, '$totalReports', 'Reports'),
              _statItem(Icons.check_circle, '$approvedReports', 'Approved'),
              _statItem(Icons.percent, '${approvalRate}%', 'Rate'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _statItem(IconData icon, String value, String label) {
    return Expanded(
      child: Column(
        children: [
          Icon(icon, size: 22, color: AppTheme.primaryColor),
          const SizedBox(height: 4),
          Text(value, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg)),
          Text(label, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
        ],
      ),
    );
  }

  Widget _buildXpProgress(int level, String levelName, String nextLevelName, int nextLevelXp, double progress) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Level Progress', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
              Text('Level $level', style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.primaryColor)),
            ],
          ),
          const SizedBox(height: 8),
          Text(levelName, style: const TextStyle(color: AppTheme.textSecondary)),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(
              value: progress,
              minHeight: 10,
              backgroundColor: AppTheme.dividerColor,
              valueColor: const AlwaysStoppedAnimation<Color>(AppTheme.primaryLight),
            ),
          ),
          const SizedBox(height: 6),
          Text('${(progress * 100).toInt()}% to $nextLevelName', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
        ],
      ),
    );
  }

  Widget _buildVerificationTick(String tick) {
    final color = _tickColor(tick);
    String label;
    switch (tick) {
      case 'green': label = 'Verified Contributor'; break;
      case 'blue': label = 'Trusted Local Expert'; break;
      case 'gold': label = 'Community Expert'; break;
      default: label = 'Verified'; break;
    }
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          Icon(Icons.verified, color: color, size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: TextStyle(fontWeight: FontWeight.bold, color: color)),
                const Text('This user has been verified by the community', style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBadgesSection(List badges) {
    final earned = badges.where((b) => b['unlocked'] == true).toList();
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text('Badges', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
              Text('${earned.length}/${badges.length}', style: const TextStyle(color: AppTheme.textSecondary)),
            ],
          ),
          const SizedBox(height: 12),
          if (earned.isEmpty)
            const Text('No badges earned yet', style: TextStyle(color: AppTheme.textSecondary))
          else
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: earned.map((b) => Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: AppTheme.primaryLight.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(_badgeIcon(b['icon'] as String? ?? ''), size: 16, color: AppTheme.primaryColor),
                    const SizedBox(width: 4),
                    Text(b['name'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w600)),
                  ],
                ),
              )).toList(),
            ),
        ],
      ),
    );
  }

  Widget _buildRecentReports(List reports, String userName) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Reports by $userName', style: const TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          ...reports.map((r) {
            final reportData = r as Map<String, dynamic>;
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () => _showReportDetails(context, reportData),
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppTheme.dividerColor.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(reportData['title'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                            const SizedBox(height: 4),
                            Row(
                              children: [
                                Text(reportData['time_ago'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                                const SizedBox(width: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: AppTheme.infoColor.withOpacity(0.1),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(reportData['category_name'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.infoColor)),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                      if (reportData['image_url'] != null)
                        ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.network(reportData['image_url'] as String, width: 48, height: 48, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox.shrink()),
                        ),
                    ],
                  ),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  void _showReportDetails(BuildContext context, Map<String, dynamic> reportData) {
    final title = reportData['title'] as String? ?? '';
    final description = reportData['description'] as String? ?? '';
    final category = reportData['category_name'] as String? ?? '';
    final priority = reportData['priority'] as String? ?? 'medium';
    final timeAgo = reportData['time_ago'] as String? ?? '';
    final imageUrl = reportData['image_url'] as String?;
    final helpful = reportData['helpful_count'] as int? ?? 0;
    final comments = reportData['comments_count'] as int? ?? 0;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.5,
        minChildSize: 0.3,
        maxChildSize: 0.8,
        expand: false,
        builder: (context, scrollController) => Padding(
          padding: const EdgeInsets.all(20),
          child: ListView(
            controller: scrollController,
            children: [
              Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppTheme.textSecondary.withOpacity(0.3), borderRadius: BorderRadius.circular(2)))),
              const SizedBox(height: 16),
              Row(children: [
                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), decoration: BoxDecoration(color: AppTheme.infoColor.withOpacity(0.1), borderRadius: BorderRadius.circular(8)), child: Text(category, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.infoColor, fontWeight: FontWeight.w600))),
                const SizedBox(width: 8),
                Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), decoration: BoxDecoration(color: AppTheme.warningColor.withOpacity(0.1), borderRadius: BorderRadius.circular(8)), child: Text(priority.toUpperCase(), style: TextStyle(fontSize: AppTheme.textSm, color: priority == 'critical' || priority == 'high' ? AppTheme.errorColor : AppTheme.warningColor, fontWeight: FontWeight.w600))),
                const Spacer(),
                Text(timeAgo, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
              ]),
              const SizedBox(height: 16),
              Text(title, style: const TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),
              Text(description, style: const TextStyle(fontSize: AppTheme.textBase, height: 1.6)),
              if (imageUrl != null) ...[
                const SizedBox(height: 16),
                ClipRRect(borderRadius: BorderRadius.circular(12), child: Image.network(imageUrl, width: double.infinity, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox.shrink())),
              ],
              const SizedBox(height: 16),
              Row(children: [
                Icon(Icons.thumb_up, size: 16, color: AppTheme.textSecondary),
                const SizedBox(width: 4),
                Text('$helpful', style: const TextStyle(color: AppTheme.textSecondary)),
                const SizedBox(width: 16),
                Icon(Icons.comment, size: 16, color: AppTheme.textSecondary),
                const SizedBox(width: 4),
                Text('$comments', style: const TextStyle(color: AppTheme.textSecondary)),
              ]),
            ],
          ),
        ),
      ),
    );
  }
}
