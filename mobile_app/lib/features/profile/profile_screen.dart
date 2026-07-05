import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../providers/report_provider.dart';
import '../../providers/store_provider.dart';
import '../../core/models/user.dart';
import '../../core/models/report.dart';
import '../../core/widgets/shimmer_loading.dart';
import '../store/store_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _initialLoadDone = false;
  bool _reportsLoaded = false;

  @override
  void initState() {
    super.initState();
    _initialLoadDone = false;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadProfileData();
      context.read<ProfileProvider>().startAutoRefresh();
    });
  }

  @override
  void dispose() {
    context.read<ProfileProvider>().stopAutoRefresh();
    super.dispose();
  }

  void _loadProfileData() async {
    if (_initialLoadDone) return;
    
    final authProv = context.read<AuthProvider>();
    final profileProv = context.read<ProfileProvider>();

    // Wait until auth initialization completes
    int attempts = 0;
    while (!authProv.isInitialized && attempts < 50) {
      await Future.delayed(const Duration(milliseconds: 100));
      attempts++;
    }

    if (authProv.isAuthenticated && authProv.user != null) {
      _initialLoadDone = true;
      // Always fetch fresh data from server
      await profileProv.loadProfile(forceRefresh: true);
    }
  }

  void _loadMyReports() {
    if (!_reportsLoaded) {
      _reportsLoaded = true;
      context.read<ReportProvider>().fetchMyReports();
    }
  }

  void _showMoreMenu() {
    final profileProv = context.read<ProfileProvider>();
    final profile = profileProv.profile;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Drag handle
              Container(
                margin: const EdgeInsets.only(top: 8),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppTheme.textSecondary.withOpacity(0.3),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 8),
              
              // Recent Activity
              if (profile != null && profile.recentActivity.isNotEmpty)
                ListTile(
                  leading: const Icon(Icons.history, color: AppTheme.infoColor),
                  title: const Text('Recent Activity'),
                  subtitle: Text('${profile.recentActivity.length} items',
                      style: const TextStyle(fontSize: AppTheme.textSm)),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () {
                    Navigator.pop(ctx);
                    _showFullActivity(context, profileProv);
                  },
                ),

              // My Bookings
              ListTile(
                leading: const Icon(Icons.book_online, color: Colors.green),
                title: const Text('My Bookings'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).pushNamed('/bookings');
                },
              ),

              // Reward Store
              ListTile(
                leading: const Icon(Icons.store, color: AppTheme.secondaryColor),
                title: const Text('Reward Store'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).pushNamed('/store');
                },
              ),

              // Sponsors
              ListTile(
                leading: const Icon(Icons.star, color: Colors.purple),
                title: const Text('Sponsors'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).pushNamed('/sponsors');
                },
              ),

              // Subscriptions
              ListTile(
                leading: const Icon(Icons.subscriptions, color: Colors.red),
                title: const Text('Subscriptions'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).pushNamed('/subscriptions');
                },
              ),

              // Settings
              ListTile(
                leading: const Icon(Icons.settings, color: AppTheme.textSecondary),
                title: const Text('Settings'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).pushNamed('/settings');
                },
              ),

              // Policies
              ListTile(
                leading: const Icon(Icons.description, color: AppTheme.textSecondary),
                title: const Text('Policies & Info'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  Navigator.of(context).pushNamed('/policies');
                },
              ),

              // Offline Maps
              ListTile(
                leading: const Icon(Icons.map, color: AppTheme.textSecondary),
                title: const Text('Offline Maps'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                },
              ),

              // About
              ListTile(
                leading: const Icon(Icons.info, color: AppTheme.textSecondary),
                title: const Text('About'),
                trailing: const Icon(Icons.chevron_right),
                onTap: () {
                  Navigator.pop(ctx);
                  showAboutDialog(
                    context: context,
                    applicationName: 'Nepal Smart Travel',
                    applicationVersion: '1.0.0',
                    applicationLegalese: '(c) 2026 Nepal Smart Travel',
                  );
                },
              ),

              const SizedBox(height: 8),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          Consumer<AuthProvider>(
            builder: (context, auth, _) {
              if (!auth.isAuthenticated) return const SizedBox.shrink();
              
              return Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Consumer<ProfileProvider>(
                    builder: (context, profileProv, _) {
                      if (profileProv.isRefreshing) {
                        return const Padding(
                          padding: EdgeInsets.all(16),
                          child: SizedBox(
                            width: 20, height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          ),
                        );
                      }
                      return IconButton(
                        icon: const Icon(Icons.refresh),
                        onPressed: () async {
                          await profileProv.refreshAll();
                        },
                      );
                    },
                  ),
                  IconButton(
                    icon: const Icon(Icons.more_vert),
                    onPressed: _showMoreMenu,
                  ),
                  IconButton(
                    icon: const Icon(Icons.logout),
                    onPressed: () async {
                      await auth.logout();
                      if (context.mounted) {
                        Navigator.of(context).pushReplacementNamed('/login');
                      }
                    },
                  ),
                ],
              );
            },
          ),
        ],
      ),
      body: Consumer2<ProfileProvider, AuthProvider>(
        builder: (context, profileProv, authProv, _) {
          final profile = profileProv.profile;
          final user = authProv.user;
          
          // ✅ Case 1: User is not authenticated at all - show login prompt
          if (!authProv.isInitialized) {
            return const Center(
              child: CircularProgressIndicator(),
            );
          }

          if (!authProv.isAuthenticated || user == null) {
            return _buildNotLoggedIn(context);
          }
          
          // ✅ Case 2: Loading profile data for first time
          if (profileProv.isLoading && profile == null) {
            return const _ProfileLoadingShimmer();
          }

          // ✅ Case 3: Profile data failed to load - show fallback with user data from AuthProvider
          if (profile == null) {
            return const Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  CircularProgressIndicator(),
                  SizedBox(height: 12),
                  Text("Loading profile..."),
                ],
              ),
            );
          }

          // Load my reports for the reports section
          _loadMyReports();

          return RefreshIndicator(
            onRefresh: () => profileProv.refreshAll(),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Profile Header
                  _buildProfileHeader(context, profile),
                  const SizedBox(height: 16),

                  // Stats Card
                  _buildStatsCard(context, profile),
                  const SizedBox(height: 16),

                  // XP Progress
                  _buildXpProgress(context, profile),
                  const SizedBox(height: 12),
                  _buildStoreButton(context),
                  const SizedBox(height: 16),

                  // Verification Tick
                  _buildVerificationTick(context, profile),
                  const SizedBox(height: 16),

                  // Badges Section
                  _buildBadgesSection(context, profile, profileProv),
                  const SizedBox(height: 16),

                  // My Reports Section (like Facebook/Instagram)
                  _buildMyReportsSection(context, profile),
                  const SizedBox(height: 32),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  // ============ Profile Header (with Edit Profile on avatar bottom-right) ============
  Widget _buildProfileHeader(BuildContext context, FullProfileData profile) {
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
          // Avatar with Edit button at bottom-right
          Stack(
            children: [
              CircleAvatar(
                radius: 44,
                backgroundColor: Colors.white.withValues(alpha: 0.2),
                backgroundImage: profile.avatarUrl != null && profile.avatarUrl!.isNotEmpty
                    ? NetworkImage(profile.avatarUrl!)
                    : null,
                child: profile.avatarUrl == null || profile.avatarUrl!.isEmpty
                    ? Text(
                        profile.name.isNotEmpty ? profile.name[0].toUpperCase() : '?',
                        style: const TextStyle(fontSize: 36, color: Colors.white, fontWeight: FontWeight.bold),
                      )
                    : null,
              ),
              if (profile.verificationTick != 'none')
                Positioned(
                  bottom: 0,
                  right: 0,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.verified,
                      size: 18,
                      color: _getVerificationColor(profile.verificationTick),
                    ),
                  ),
                ),
              // Edit Profile button at bottom-right of avatar
              Positioned(
                bottom: -4,
                right: -4,
                child: GestureDetector(
                  onTap: () {
                    Navigator.of(context).pushNamed('/profile-setup');
                  },
                  child: Container(
                    padding: const EdgeInsets.all(6),
                    decoration: const BoxDecoration(
                      color: AppTheme.primaryColor,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.edit,
                      size: 16,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Name
          Text(
            profile.name,
            style: const TextStyle(fontSize: AppTheme.text2xl, color: Colors.white, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          // Email
          Text(profile.email, style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textBase)),
          const SizedBox(height: 8),
          // Level & Rank
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildHeaderChip(Icons.stars, 'Level ${profile.currentLevel} - ${profile.levelName}'),
              const SizedBox(width: 8),
              _buildHeaderChip(Icons.emoji_events, 'Rank #${profile.rank}'),
            ],
          ),
          if (profile.memberSinceDays > 0) ...[
            const SizedBox(height: 6),
            Text(
              'Member for ${profile.memberSinceDays} days',
              style: const TextStyle(color: Colors.white60, fontSize: AppTheme.textSm),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildHeaderChip(IconData icon, String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.2),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: Colors.white),
          const SizedBox(width: 4),
          Text(text, style: const TextStyle(color: Colors.white, fontSize: AppTheme.textSm)),
        ],
      ),
    );
  }

  // ============ Stats Card ============
  Widget _buildStatsCard(BuildContext context, FullProfileData profile) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _StatItem(icon: Icons.emoji_events, value: '${profile.totalXp}', label: 'Total XP', color: AppTheme.secondaryColor),
              _StatItem(icon: Icons.assignment, value: '${profile.totalReports}', label: 'Reports', color: AppTheme.infoColor),
              _StatItem(icon: Icons.check_circle, value: '${profile.approvedReports}', label: 'Approved', color: AppTheme.successColor),
              _StatItem(icon: Icons.trending_up, value: '${profile.approvalRate}%', label: 'Rate', color: AppTheme.primaryColor),
            ],
          ),
          const SizedBox(height: 12),
          // Additional stats row
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _StatItem(icon: Icons.warning_amber, value: '${profile.totalAlerts}', label: 'Alerts', color: AppTheme.warningColor),
              _StatItem(icon: Icons.rate_review, value: '${profile.totalReviews}', label: 'Reviews', color: AppTheme.infoColor),
              _StatItem(icon: Icons.comment, value: '${profile.totalComments}', label: 'Comments', color: AppTheme.accentColor),
              _StatItem(icon: Icons.cancel, value: '${profile.rejectedReports}', label: 'Rejected', color: AppTheme.errorColor),
            ],
          ),
        ],
      ),
    );
  }

  // ============ XP Progress ============
  Widget _buildXpProgress(BuildContext context, FullProfileData profile) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.trending_up, size: 18, color: AppTheme.secondaryColor),
              const SizedBox(width: 8),
              Text('Level Progress', style: TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase, color: _getLevelColor(profile.currentLevel))),
              const Spacer(),
              Text('${profile.totalXp}/${profile.nextLevelXp} XP', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: profile.levelProgress.clamp(0.0, 1.0),
              backgroundColor: AppTheme.dividerColor,
              valueColor: AlwaysStoppedAnimation(_getLevelColor(profile.currentLevel)),
              minHeight: 8,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('${profile.levelName} (Lv.${profile.currentLevel})', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
              Text('Next: ${profile.nextLevelName}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
            ],
          ),
        ],
      ),
    );
  }

  // ============ Store Button ============
  Widget _buildStoreButton(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: () {
          Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const _StoreScreenWrapper()),
          );
        },
        icon: const Icon(Icons.store, size: 18),
        label: const Text('XP Reward Store'),
        style: OutlinedButton.styleFrom(
          foregroundColor: AppTheme.secondaryColor,
          side: const BorderSide(color: AppTheme.secondaryColor),
          padding: const EdgeInsets.symmetric(vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ),
      ),
    );
  }

  // ============ Verification Tick ============
  Widget _buildVerificationTick(BuildContext context, FullProfileData profile) {
    final tickColor = _getVerificationColor(profile.verificationTick);
    final tickLabel = profile.verificationTick.toUpperCase();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: tickColor.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.verified, color: tickColor, size: 28),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$tickLabel TICK', style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase, color: tickColor)),
                Text('${profile.approvedReports} approved reports • ${profile.totalReports} total contributions',
                    style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============ Badges Section ============
  Widget _buildBadgesSection(BuildContext context, FullProfileData profile, ProfileProvider profileProv) {
    final unlockedBadges = profile.badges.where((b) => b.unlocked).toList();
    final lockedBadges = profile.badges.where((b) => !b.unlocked).toList();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.emoji_events, size: 18, color: AppTheme.secondaryColor),
              const SizedBox(width: 8),
              Text(
                'Badges & Achievements',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg, color: AppTheme.textPrimary),
              ),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(
                  color: AppTheme.secondaryColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  '${unlockedBadges.length}/${profile.badges.length}',
                  style: TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w600, color: AppTheme.secondaryColor),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          // Unlocked badges
          if (unlockedBadges.isNotEmpty) ...[
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: unlockedBadges.map((badge) => _buildBadgeChip(badge, true)).toList(),
            ),
            if (lockedBadges.isNotEmpty) const SizedBox(height: 12),
          ],
          // Locked badges
          if (lockedBadges.isNotEmpty) ...[
            Text('Locked Badges', style: TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w500, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: lockedBadges.take(6).map((badge) => _buildBadgeChip(badge, false)).toList(),
            ),
            if (lockedBadges.length > 6)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(
                  '+${lockedBadges.length - 6} more locked badges',
                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textXs, fontStyle: FontStyle.italic),
                ),
              ),
          ],
          if (unlockedBadges.isEmpty && lockedBadges.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('No badges available yet. Start contributing to earn badges!',
                  style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
            ),
        ],
      ),
    );
  }

  Widget _buildBadgeChip(BadgeInfo badge, bool unlocked) {
    return Tooltip(
      message: '${badge.name}\n${badge.description}',
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: unlocked
              ? AppTheme.secondaryColor.withOpacity(0.1)
              : AppTheme.dividerColor.withOpacity(0.5),
          borderRadius: BorderRadius.circular(20),
          border: unlocked
              ? Border.all(color: AppTheme.secondaryColor.withOpacity(0.3))
              : null,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              badge.iconData,
              size: 14,
              color: unlocked ? AppTheme.secondaryColor : AppTheme.textSecondary,
            ),
            const SizedBox(width: 4),
            Text(
              badge.name.toUpperCase(),
              style: TextStyle(
                fontSize: AppTheme.textXs,
                fontWeight: FontWeight.w600,
                color: unlocked ? AppTheme.secondaryColor : AppTheme.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============ My Reports Section (Facebook style - flat list with status badges) ============
  Widget _buildMyReportsSection(BuildContext context, FullProfileData profile) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
            child: Row(
              children: [
                const Icon(Icons.my_library_books, size: 18, color: AppTheme.infoColor),
                const SizedBox(width: 8),
                Text(
                  'My Reports',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg, color: AppTheme.textPrimary),
                ),
                const Spacer(),
                Text(
                  '${profile.totalReports} total',
                  style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
                ),
              ],
            ),
          ),
          const Divider(height: 1),

          // Flat list of all reports sorted by date with status badges (like Facebook)
          Consumer<ReportProvider>(
            builder: (context, reportProv, _) {
              if (reportProv.isLoading && reportProv.myReports.isEmpty) {
                return const SizedBox(
                  height: 120,
                  child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                );
              }

              // Sort reports by newest first
              final myReports = List<ReportModel>.from(reportProv.myReports)
                ..sort((a, b) => b.createdAt.compareTo(a.createdAt));

              if (myReports.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          children: [
                            Icon(Icons.inbox, size: 36, color: AppTheme.textSecondary.withOpacity(0.3)),
                            const SizedBox(height: 6),
                            const Text('No reports yet', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                            Text(
                              'Tap + in Reports to submit',
                              style: TextStyle(color: AppTheme.textSecondary.withOpacity(0.6), fontSize: AppTheme.textXs),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              }

              return ListView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                itemCount: myReports.length > 5 ? 5 : myReports.length,
                itemBuilder: (context, index) {
                  final report = myReports[index];
                  return _buildMyReportItem(report);
                },
              );
            },
          ),
        ],
      ),
    );
  }

  Widget _buildMyReportItem(ReportModel report) {
    Color statusColor;
    IconData statusIcon;
    switch (report.status) {
      case 'approved':
        statusColor = AppTheme.successColor;
        statusIcon = Icons.check_circle;
        break;
      case 'rejected':
        statusColor = AppTheme.errorColor;
        statusIcon = Icons.cancel;
        break;
      default:
        statusColor = AppTheme.warningColor;
        statusIcon = Icons.access_time;
    }

    // Get the first image URL if available
    final hasImage = report.imageUrls.isNotEmpty;
    final imageUrl = hasImage ? report.imageUrls.first : null;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: () => _showReportDetails(context, report),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            color: AppTheme.dividerColor.withOpacity(0.15),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              // Thumbnail image if available
              if (hasImage)
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: SizedBox(
                    width: 44,
                    height: 44,
                    child: Image.network(
                      imageUrl!,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) => Container(
                        color: statusColor.withOpacity(0.1),
                        child: Icon(statusIcon, size: 20, color: statusColor),
                      ),
                      loadingBuilder: (context, child, loadingProgress) {
                        if (loadingProgress == null) return child;
                        return Container(
                          color: AppTheme.dividerColor.withOpacity(0.3),
                          child: const Center(
                            child: SizedBox(
                              width: 16, height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                )
              else
                Container(
                  width: 44,
                  height: 44,
                  padding: const EdgeInsets.all(4),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Icon(statusIcon, size: 20, color: statusColor),
                ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
Text(
                          report.title,
                          style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w500),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        Text(
                          report.categoryName,
                          style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '·',
                          style: TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary.withOpacity(0.5)),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          report.timeAgo.isNotEmpty
                              ? report.timeAgo
                              : _formatTimeAgo(report.createdAt),
                          style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: statusColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  report.status.toUpperCase(),
                  style: TextStyle(fontSize: AppTheme.textXs, color: statusColor, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ============ Recent Activity (in bottom sheet) ============
  void _showFullActivity(BuildContext context, ProfileProvider profileProv) {
    // Load activity if not loaded
    profileProv.loadActivity();
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return DraggableScrollableSheet(
          initialChildSize: 0.7,
          minChildSize: 0.4,
          maxChildSize: 0.9,
          expand: false,
          builder: (ctx, scrollController) {
            return Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Center(
                    child: Container(
                      width: 40, height: 4,
                      decoration: BoxDecoration(
                        color: AppTheme.textSecondary.withOpacity(0.3),
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text('All Activity', style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold, color: AppTheme.textPrimary)),
                  const SizedBox(height: 16),
                  Expanded(
                    child: Consumer<ProfileProvider>(
                      builder: (ctx, prov, _) {
                        if (prov.isActivityLoading) {
                          return const Center(child: CircularProgressIndicator());
                        }
                        if (prov.activity.isEmpty) {
                          return const Center(child: Text('No activity yet', style: TextStyle(color: AppTheme.textSecondary)));
                        }
                        return ListView.separated(
                          controller: scrollController,
                          itemCount: prov.activity.length,
                          separatorBuilder: (_, __) => const Divider(height: 1),
                          itemBuilder: (ctx, i) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            child: _buildActivityItem(prov.activity[i]),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Widget _buildActivityItem(ActivityItem item) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: item.iconColor.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(item.icon, size: 16, color: item.iconColor),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title.length > 40 ? '${item.title.substring(0, 40)}...' : item.title,
                  style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w500),
                ),
                const SizedBox(height: 2),
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                      decoration: BoxDecoration(
                        color: item.iconColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        item.type.toUpperCase(),
                        style: TextStyle(fontSize: AppTheme.textXs, fontWeight: FontWeight.w600, color: item.iconColor),
                      ),
                    ),
                    const SizedBox(width: 6),
                    if (item.status != null)
                      Text(
                        item.status!.toUpperCase(),
                        style: TextStyle(fontSize: AppTheme.textXs, color: item.status == 'approved' ? AppTheme.successColor : AppTheme.warningColor),
                      ),
                    const Spacer(),
                    Text(item.formattedDate, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showReportDetails(BuildContext context, ReportModel report) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _ProfileReportDetailsSheet(report: report),
    );
  }

  // ============ Not Logged In State ============
  Widget _buildNotLoggedIn(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.person_off, size: 64, color: AppTheme.textSecondary),
            const SizedBox(height: 24),
            const Text(
              'You are not logged in',
              style: TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold, color: AppTheme.textPrimary),
            ),
            const SizedBox(height: 8),
            const Text(
              'Please log in to view your profile',
              style: TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 32),
            ElevatedButton.icon(
              onPressed: () => Navigator.of(context).pushReplacementNamed('/login'),
              icon: const Icon(Icons.login),
              label: const Text('Go to Login'),
              style: ElevatedButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ============ Color Helpers ============
  Color _getVerificationColor(String tick) {
    switch (tick) {
      case 'gray': return AppTheme.grayTick;
      case 'green': return AppTheme.greenTick;
      case 'blue': return AppTheme.blueTick;
      case 'gold': return AppTheme.goldTick;
      case 'diamond': return AppTheme.diamondTick;
      default: return AppTheme.grayTick;
    }
  }

  Color _getLevelColor(int level) {
    if (level <= 5) return AppTheme.explorerColor;
    if (level <= 15) return AppTheme.contributorColor;
    if (level <= 30) return AppTheme.trustedLocalColor;
    if (level <= 50) return AppTheme.regionalGuideColor;
    if (level <= 100) return AppTheme.communityExpertColor;
    return AppTheme.communityExpertColor;
  }

  String _formatTimeAgo(DateTime dateTime) {
    final now = DateTime.now();
    final diff = now.difference(dateTime);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${dateTime.day}/${dateTime.month}/${dateTime.year}';
  }
}

// ============ PROFILE LOADING SHIMMER ============
class _ProfileLoadingShimmer extends StatelessWidget {
  const _ProfileLoadingShimmer();

  @override
  Widget build(BuildContext context) {
    return const SingleChildScrollView(
      physics: AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.all(16),
      child: ProfileShimmer(),
    );
  }
}

// ============ Reusable Sub-widgets ============

class _StatItem extends StatelessWidget {
  final IconData icon;
  final String value, label;
  final Color color;

  const _StatItem({required this.icon, required this.value, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Icon(icon, color: color, size: 22),
        const SizedBox(height: 2),
        Text(value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase, color: color)),
        Text(label, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
      ],
    );
  }
}

// ============ Report Details Sheet (for profile screen) ============

class _ProfileReportDetailsSheet extends StatelessWidget {
  final ReportModel report;

  const _ProfileReportDetailsSheet({required this.report});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.5,
      minChildSize: 0.3,
      maxChildSize: 0.8,
      expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: ListView(
            controller: scrollController,
            children: [
              // Drag handle
              Center(
                child: Container(
                  width: 40, height: 4,
                  decoration: BoxDecoration(
                    color: AppTheme.textSecondary.withOpacity(0.3),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Status
              Row(
                children: [
                  Icon(
                    report.isApproved
                        ? Icons.check_circle
                        : report.isPending
                            ? Icons.access_time
                            : Icons.cancel,
                    size: 20,
                    color: report.isApproved
                        ? AppTheme.successColor
                        : report.isPending
                            ? AppTheme.warningColor
                            : AppTheme.errorColor,
                  ),
                  const SizedBox(width: 8),
                  Text(
                    report.status.toUpperCase(),
                    style: TextStyle(
                      fontSize: AppTheme.textLg,
                      fontWeight: FontWeight.bold,
                      color: report.isApproved
                          ? AppTheme.successColor
                          : report.isPending
                              ? AppTheme.warningColor
                              : AppTheme.errorColor,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Title
              Text(report.title,
                  style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),

              // Category & time
              Row(
                children: [
                  Icon(Icons.category, size: 14, color: AppTheme.textSecondary),
                  const SizedBox(width: 4),
                  Text(report.categoryName,
                      style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                  const SizedBox(width: 12),
                  Icon(Icons.access_time, size: 14, color: AppTheme.textSecondary),
                  const SizedBox(width: 4),
                  Text(
                    report.timeAgo.isNotEmpty
                        ? report.timeAgo
                        : _formatTimeAgoCustom(report.createdAt),
                    style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Description
              Text(report.description,
                  style: const TextStyle(fontSize: AppTheme.textBase, height: 1.5)),
              const SizedBox(height: 16),

              // Location
              Row(
                children: [
                  const Icon(Icons.location_on, size: 14, color: AppTheme.textSecondary),
                  const SizedBox(width: 4),
                  Text(
                    '${report.latitude.toStringAsFixed(4)}, ${report.longitude.toStringAsFixed(4)}',
                    style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  String _formatTimeAgoCustom(DateTime dateTime) {
    final now = DateTime.now();
    final diff = now.difference(dateTime);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${dateTime.day}/${dateTime.month}/${dateTime.year}';
  }
}

class _StoreScreenWrapper extends StatelessWidget {
  const _StoreScreenWrapper();

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => StoreProvider(),
      child: const StoreScreen(),
    );
  }
}
