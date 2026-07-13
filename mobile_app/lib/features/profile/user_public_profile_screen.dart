import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../core/widgets/shimmer_loading.dart';
import '../../widgets/section_card.dart';
import '../../widgets/stats_row.dart';
import '../../widgets/badge_chip.dart';
import '../../widgets/verification_badge.dart';

class UserPublicProfileScreen extends StatefulWidget {
  final String userId;

  const UserPublicProfileScreen({super.key, required this.userId});

  @override
  State<UserPublicProfileScreen> createState() => _UserPublicProfileScreenState();
}

class _UserPublicProfileScreenState extends State<UserPublicProfileScreen> {
  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ProfileProvider>().loadPublicProfile(widget.userId).then((_) {
        if (mounted) setState(() => _initialized = true);
      });
    });
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: Consumer<ProfileProvider>(
        builder: (context, prov, _) {
          if (!_initialized || prov.isLoadingPublic) {
            return const SingleChildScrollView(
              physics: AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.all(16),
              child: ProfileShimmer(),
            );
          }

          final p = prov.publicProfile;
          if (p == null) {
            return Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(Icons.person_off, size: 64, color: AppTheme.textSecondary),
                  const SizedBox(height: 16),
                  const Text('Profile not found', style: TextStyle(color: AppTheme.textSecondary)),
                  const SizedBox(height: 8),
                  ElevatedButton.icon(
                    onPressed: () {
                      prov.loadPublicProfile(widget.userId);
                    },
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retry'),
                  ),
                ],
              ),
            );
          }

          final name = p['name'] as String? ?? '';
          final avatarUrl = p['avatar_url'] as String?;
          final bio = p['bio'] as String?;
          final tick = p['verification_tick'] as String? ?? 'none';
          final totalXp = p['total_xp'] as int? ?? 0;
          final level = p['current_level'] as int? ?? 1;
          final levelName = p['level_name'] as String? ?? 'Explorer';
          final nextLevelName = p['next_level_name'] as String? ?? '';
          final nextLevelXp = p['next_level_xp'] as int? ?? 50;
          final progress = (p['level_progress'] is num ? (p['level_progress'] as num).toDouble() : 0.0);
          final rank = p['rank'] is int ? p['rank'] as int : 0;
          final totalReports = p['total_reports'] is int ? p['total_reports'] as int : 0;
          final approvedReports = p['approved_reports'] is int ? p['approved_reports'] as int : 0;
          final approvalRate = p['approval_rate'] is num ? p['approval_rate'] as num : 0;
          final badges = p['badges'] as List? ?? [];
          final recentReports = p['recent_reports'] as List? ?? [];

          return RefreshIndicator(
            onRefresh: () => prov.loadPublicProfile(widget.userId),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildHeader(name, avatarUrl, bio, tick),
                  const SizedBox(height: 16),
                  SectionCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Statistics', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
                        const SizedBox(height: 12),
                        StatsRow(stats: [
                          StatItem(icon: Icons.emoji_events, value: '$totalXp', label: 'XP', color: AppTheme.secondaryColor),
                          StatItem(icon: Icons.stars, value: '$level', label: 'Level', color: AppTheme.infoColor),
                          StatItem(icon: Icons.trending_up, value: '#$rank', label: 'Rank', color: AppTheme.primaryColor),
                          StatItem(icon: Icons.assignment, value: '$totalReports', label: 'Reports', color: AppTheme.successColor),
                          StatItem(icon: Icons.check_circle, value: '$approvedReports', label: 'Approved', color: AppTheme.greenTick),
                          StatItem(icon: Icons.percent, value: '${approvalRate}%', label: 'Rate', color: AppTheme.accentColor),
                        ], crossAxisCount: 3),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  SectionCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Level Progress', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
                            LevelBadge(level: level),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(levelName, style: const TextStyle(color: AppTheme.textSecondary)),
                        const SizedBox(height: 8),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(4),
                          child: LinearProgressIndicator(
                            value: progress.clamp(0.0, 1.0),
                            minHeight: 8,
                            backgroundColor: AppTheme.dividerColor,
                            valueColor: const AlwaysStoppedAnimation<Color>(AppTheme.primaryLight),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text('${(progress * 100).toInt()}% to $nextLevelName', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                      ],
                    ),
                  ),
                  if (tick != 'none') ...[
                    const SizedBox(height: 16),
                    VerificationBadge(tick: tick),
                  ],
                  if (badges.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    SectionCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text('Badges', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 12),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: badges.where((b) => b['unlocked'] == true).map((b) => BadgeChip(
                              icon: _badgeIcon(b['icon'] as String? ?? ''),
                              label: b['name'] as String? ?? '',
                              unlocked: true,
                              unlockedColor: AppTheme.primaryColor,
                            )).toList(),
                          ),
                        ],
                      ),
                    ),
                  ],
                  if (recentReports.isNotEmpty) ...[
                    const SizedBox(height: 16),
                    SectionCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Reports by $name', style: const TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
                          const SizedBox(height: 12),
                          ...recentReports.map((r) {
                            final rd = r as Map<String, dynamic>;
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: InkWell(
                                borderRadius: BorderRadius.circular(8),
                                onTap: () => _showReportDetails(context, rd),
                                child: Container(
                                  padding: const EdgeInsets.all(12),
                                  decoration: BoxDecoration(
                                    color: AppTheme.dividerColor.withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(rd['title'] as String? ?? '', style: const TextStyle(fontWeight: FontWeight.w600)),
                                            const SizedBox(height: 4),
                                            Row(
                                              children: [
                                                Text(rd['time_ago'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                                                const SizedBox(width: 6),
                                                Container(
                                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                  decoration: BoxDecoration(color: AppTheme.infoColor.withOpacity(0.1), borderRadius: BorderRadius.circular(4)),
                                                  child: Text(rd['category_name'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.infoColor)),
                                                ),
                                              ],
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (rd['image_url'] != null)
                                        ClipRRect(
                                          borderRadius: BorderRadius.circular(6),
                                          child: Image.network(rd['image_url'] as String, width: 44, height: 44, fit: BoxFit.cover, errorBuilder: (_, __, ___) => const SizedBox.shrink()),
                                        ),
                                    ],
                                  ),
                                ),
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 32),
                ],
              ),
            ),
          );
        },
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
                backgroundImage: avatarUrl != null && avatarUrl.isNotEmpty ? NetworkImage(avatarUrl) : null,
                child: avatarUrl == null || avatarUrl.isEmpty
                    ? Text(name.isNotEmpty ? name[0].toUpperCase() : '?', style: const TextStyle(fontSize: 36, color: Colors.white, fontWeight: FontWeight.bold))
                    : null,
              ),
              if (tick != 'none')
                Positioned(
                  bottom: 0, right: 0,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                    child: Icon(Icons.verified, color: VerificationBadge.tickColorFromString(tick), size: 20),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          Text(name, style: const TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold, color: Colors.white)),
          if (bio != null && bio.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(bio, style: const TextStyle(fontSize: AppTheme.textBase, color: Colors.white70), textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis),
          ],
        ],
      ),
    );
  }

  void _showReportDetails(BuildContext context, Map<String, dynamic> reportData) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
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
              Text(reportData['title'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              Text(reportData['description'] as String? ?? '', style: const TextStyle(fontSize: AppTheme.textBase, height: 1.6)),
            ],
          ),
        ),
      ),
    );
  }
}
