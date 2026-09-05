import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../providers/report_provider.dart';
import '../../core/models/report.dart';
import '../../core/widgets/shimmer_loading.dart';
import '../../widgets/section_card.dart';
import '../../widgets/stats_row.dart';
import '../../widgets/badge_chip.dart';
import '../../widgets/verification_badge.dart';
import '../../widgets/report_card.dart';
import '../store/store_screen.dart';
import '../wallet/wallet_screen.dart';
import '../reporting/reports_list_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  bool _initialLoadDone = false;
  bool _reportsLoaded = false;
  late final ProfileProvider _profileProvider;

  @override
  void initState() {
    super.initState();
    _initialLoadDone = false;
    _profileProvider = context.read<ProfileProvider>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadProfileData();
      _profileProvider.startAutoRefresh();
    });
  }

  @override
  void dispose() {
    // FL-28: provider captured in initState — no context.read inside dispose()
    _profileProvider.stopAutoRefresh();
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
    final auth = context.read<AuthProvider>();
    final profile = profileProv.profile;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (ctx) {
        return Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: SafeArea(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Drag handle
                Container(
                  margin: const EdgeInsets.only(top: 12),
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.withOpacity(0.3),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                
                // Header
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 12),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [AppTheme.primaryColor, Color(0xFF667EEA)],
                          ),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Icon(Icons.menu_rounded, color: Colors.white, size: 22),
                      ),
                      const SizedBox(width: 14),
                      Text(
                        context.t('Menu'),
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.textPrimary,
                        ),
                      ),
                    ],
                  ),
                ),

                // Menu Items
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Column(
                    children: [
                      // Recent Activity
                      if (profile != null && profile.recentActivity.isNotEmpty)
                        _buildMenuTile(
                          icon: Icons.history_rounded,
                          iconColor: const Color(0xFF667EEA),
                          title: context.t('Recent Activity'),
                          subtitle: '${profile.recentActivity.length} ${context.t('items')}',
                          onTap: () {
                            Navigator.pop(ctx);
                            _showFullActivity(context, profileProv);
                          },
                        ),

                      // Reward Store
                      _buildMenuTile(
                        icon: Icons.store_rounded,
                        iconColor: const Color(0xFFF5576C),
                        title: context.t('Reward Store'),
                        subtitle: 'Earn XP and rewards',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.of(context).pushNamed('/store');
                        },
                      ),

                      // Oripori Coins Wallet
                      _buildMenuTile(
                        icon: Icons.account_balance_wallet_rounded,
                        iconColor: const Color(0xFF43E97B),
                        title: 'Oripori Coins',
                        subtitle: 'Earn from ad views',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => const WalletScreen()),
                          );
                        },
                      ),

                      // Subscriptions
                      _buildMenuTile(
                        icon: Icons.diamond_rounded,
                        iconColor: const Color(0xFFFF6B6B),
                        title: context.t('Subscriptions'),
                        subtitle: 'Premium features',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.of(context).pushNamed('/subscriptions');
                        },
                      ),

                      // Settings
                      _buildMenuTile(
                        icon: Icons.settings_rounded,
                        iconColor: const Color(0xFF78909C),
                        title: context.t('Settings'),
                        subtitle: 'App preferences',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.of(context).pushNamed('/settings');
                        },
                      ),

                      // Divider
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Row(
                          children: [
                            Expanded(child: Divider(color: Colors.grey.withOpacity(0.2))),
                            Padding(
                              padding: const EdgeInsets.symmetric(horizontal: 12),
                              child: Text(
                                context.t('Legal'),
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w500,
                                  color: Colors.grey.withOpacity(0.6),
                                ),
                              ),
                            ),
                            Expanded(child: Divider(color: Colors.grey.withOpacity(0.2))),
                          ],
                        ),
                      ),

                      // Privacy Policy
                      _buildMenuTile(
                        icon: Icons.privacy_tip_rounded,
                        iconColor: const Color(0xFF4FACFE),
                        title: context.t('Privacy Policy'),
                        subtitle: 'How we protect your data',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.of(context).pushNamed('/legal', arguments: 'privacy_policy');
                        },
                      ),

                      // Terms & Conditions
                      _buildMenuTile(
                        icon: Icons.gavel_rounded,
                        iconColor: const Color(0xFFFFB74D),
                        title: context.t('Terms & Conditions'),
                        subtitle: 'Service agreement',
                        onTap: () {
                          Navigator.pop(ctx);
                          Navigator.of(context).pushNamed('/legal', arguments: 'terms_conditions');
                        },
                      ),

                      // About
                      _buildMenuTile(
                        icon: Icons.info_outline_rounded,
                        iconColor: const Color(0xFF90A4AE),
                        title: context.t('About'),
                        subtitle: 'Nepal Smart Travel v1.0.0',
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

                      // Logout
                      Container(
                        margin: const EdgeInsets.only(top: 8, bottom: 16),
                        decoration: BoxDecoration(
                          color: AppTheme.errorColor.withOpacity(0.08),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: ListTile(
                          leading: Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: AppTheme.errorColor.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: const Icon(Icons.logout_rounded, color: AppTheme.errorColor, size: 22),
                          ),
                          title: Text(
                            context.t('Logout'),
                            style: const TextStyle(
                              color: AppTheme.errorColor,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          subtitle: Text(
                            'Sign out of your account',
                            style: TextStyle(
                              color: AppTheme.errorColor.withOpacity(0.7),
                              fontSize: 12,
                            ),
                          ),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          onTap: () async {
                            Navigator.pop(ctx);
                            await auth.logout();
                            if (context.mounted) {
                              Navigator.of(context).pushReplacementNamed('/login');
                            }
                          },
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildMenuTile({
    required IconData icon,
    required Color iconColor,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 4),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: iconColor.withOpacity(0.1),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, size: 22, color: iconColor),
        ),
        title: Text(
          title,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w500,
            color: AppTheme.textPrimary,
          ),
        ),
        subtitle: Text(
          subtitle,
          style: TextStyle(
            fontSize: 12,
            color: AppTheme.textSecondary.withOpacity(0.7),
          ),
        ),
        trailing: Container(
          padding: const EdgeInsets.all(4),
          decoration: BoxDecoration(
            color: Colors.grey.withOpacity(0.08),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Icon(Icons.chevron_right_rounded, color: Colors.grey.withOpacity(0.5), size: 18),
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        onTap: onTap,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FA),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: const Color(0xFF00695C).withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.person, color: Color(0xFF00695C), size: 20),
            ),
            const SizedBox(width: 10),
            const Text(
              'Profile',
              style: TextStyle(
                color: Color(0xFF2D3436),
                fontSize: 20,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        actions: [
          Consumer<AuthProvider>(
            builder: (context, auth, _) {
              if (!auth.isAuthenticated) return const SizedBox.shrink();
              return GestureDetector(
                onTap: _showMoreMenu,
                child: Container(
                  margin: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade50,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(Icons.more_vert, color: Colors.grey.shade600, size: 22),
                ),
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
          colors: [Color(0xFF004D40), Color(0xFF00695C), Color(0xFF00897B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF00695C).withOpacity(0.3),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        children: [
          // Avatar with Edit button
          Stack(
            children: [
              Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white.withOpacity(0.3), width: 2),
                ),
                child: CircleAvatar(
                  radius: 44,
                  backgroundColor: Colors.white.withOpacity(0.2),
                  backgroundImage: profile.avatarUrl != null && profile.avatarUrl!.isNotEmpty
                      ? NetworkImage(profile.avatarUrl!)
                      : null,
                  child: profile.avatarUrl == null || profile.avatarUrl!.isEmpty
                      ? Container(
                          width: 88,
                          height: 88,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            gradient: LinearGradient(
                              colors: [Color(0xFF00695C), Color(0xFF00897B), Color(0xFF26A69A)],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                          ),
                          child: Center(
                            child: Text(
                              profile.name.isNotEmpty ? profile.name[0].toUpperCase() : '?',
                              style: const TextStyle(fontSize: 36, color: Colors.white, fontWeight: FontWeight.w800),
                            ),
                          ),
                        )
                      : null,
                ),
              ),
              // Edit overlay
              Positioned.fill(
                child: GestureDetector(
                  onTap: () => Navigator.of(context).pushNamed('/profile-edit'),
                  child: Container(
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.25),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.edit,
                      size: 24,
                      color: Colors.white.withOpacity(0.85),
                    ),
                  ),
                ),
              ),
              if (profile.verificationTick != 'none')
                Positioned(
                  bottom: 4,
                  right: 4,
                  child: Container(
                    padding: const EdgeInsets.all(2),
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      Icons.verified,
                      size: 18,
                      color: VerificationBadge.tickColorFromString(profile.verificationTick),
                    ),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 16),
          // Name
          Text(
            profile.name,
            style: const TextStyle(
              fontSize: 22,
              color: Colors.white,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 4),
          // Email
          Text(
            profile.email,
            style: TextStyle(
              color: Colors.white.withOpacity(0.75),
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 12),
          // Level & Rank chips
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              _buildHeaderChip(Icons.stars, '${context.t('Level')} ${profile.currentLevel} - ${profile.levelName}'),
              const SizedBox(width: 8),
              _buildHeaderChip(Icons.emoji_events, '${context.t('Rank')} #${profile.rank}'),
            ],
          ),
          if (profile.memberSinceDays > 0) ...[
            const SizedBox(height: 8),
            Text(
              '${context.t('Member for')} ${profile.memberSinceDays} ${context.t('days')}',
              style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 12),
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
          Text(text, style: const TextStyle(color: Colors.white, fontSize: AppTheme.textSm, fontWeight: FontWeight.normal)),
        ],
      ),
    );
  }

  // ============ Stats Card ============
  Widget _buildStatsCard(BuildContext context, FullProfileData profile) {
    return SectionCard(
      child: StatsRow(stats: [
        StatItem(icon: Icons.emoji_events, value: '${profile.totalXp}', label: context.t('Total XP'), color: AppTheme.secondaryColor),
        StatItem(icon: Icons.assignment, value: '${profile.totalReports}', label: context.t('Reports'), color: AppTheme.infoColor),
        StatItem(icon: Icons.check_circle, value: '${profile.approvedReports}', label: context.t('Approved'), color: AppTheme.successColor),
        StatItem(icon: Icons.trending_up, value: '${profile.approvalRate}%', label: context.t('Rate'), color: AppTheme.primaryColor),
        StatItem(icon: Icons.warning_amber, value: '${profile.totalAlerts}', label: context.t('Alerts'), color: AppTheme.warningColor),
        StatItem(icon: Icons.rate_review, value: '${profile.totalReviews}', label: context.t('Reviews'), color: AppTheme.infoColor),
        StatItem(icon: Icons.comment, value: '${profile.totalComments}', label: context.t('Comments'), color: AppTheme.accentColor),
        StatItem(icon: Icons.cancel, value: '${profile.rejectedReports}', label: context.t('Rejected'), color: AppTheme.errorColor),
      ]),
    );
  }

  // ============ XP Progress ============
  Widget _buildXpProgress(BuildContext context, FullProfileData profile) {
    final levelColor = LevelBadge(level: profile.currentLevel).levelColor;
    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.trending_up, size: 18, color: AppTheme.secondaryColor),
              const SizedBox(width: 8),
              Text(context.t('Level Progress'), style: TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase, color: levelColor)),
              const Spacer(),
              Text('${profile.totalXp}/${profile.nextLevelXp} XP', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm, fontWeight: FontWeight.normal)),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: profile.levelProgress.clamp(0.0, 1.0),
              backgroundColor: AppTheme.dividerColor,
              valueColor: AlwaysStoppedAnimation(levelColor),
              minHeight: 8,
            ),
          ),
          const SizedBox(height: 4),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text('${profile.levelName} (Lv.${profile.currentLevel})', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
              Text('${context.t('Next')}: ${profile.nextLevelName}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
            ],
          ),
        ],
      ),
    );
  }

  // ============ Store Button ============
  Widget _buildStoreButton(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFF39C12), Color(0xFFE67E22)],
        ),
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFF39C12).withOpacity(0.3),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () {
            Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const StoreScreen()),
            );
          },
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.store, size: 18, color: Colors.white),
                const SizedBox(width: 8),
                Text(
                  context.t('XP Reward Store'),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  // ============ Verification Tick ============
  Widget _buildVerificationTick(BuildContext context, FullProfileData profile) {
    if (profile.verificationTick == 'none') {
      return SectionCard(
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppTheme.dividerColor.withOpacity(0.5),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.workspace_premium_outlined, color: AppTheme.textSecondary, size: 28),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(context.t('Get Verified'), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase, color: AppTheme.textPrimary)),
                  Text(context.t('Earn a verification tick by getting reports approved'), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                ],
              ),
            ),
          ],
        ),
      );
    }
    return SectionCard(
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: VerificationBadge.tickColorFromString(profile.verificationTick).withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(Icons.verified, color: VerificationBadge.tickColorFromString(profile.verificationTick), size: 28),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${profile.verificationTick.toUpperCase()} TICK', style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase, color: VerificationBadge.tickColorFromString(profile.verificationTick))),
                Text('${profile.approvedReports} approved reports • ${profile.totalReports} total contributions', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm, fontWeight: FontWeight.normal)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ============ Badges Section ============
  Widget _buildBadgesSection(BuildContext context, FullProfileData profile, ProfileProvider profileProv) {
    final unlocked = profile.badges.where((b) => b.unlocked).toList();
    final locked = profile.badges.where((b) => !b.unlocked).toList();

    return SectionCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.emoji_events, size: 18, color: AppTheme.secondaryColor),
              const SizedBox(width: 8),
              Text(context.t('Badges & Achievements'), style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg, color: AppTheme.textPrimary)),
              const Spacer(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                decoration: BoxDecoration(color: AppTheme.secondaryColor.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
                child: Text('${unlocked.length}/${profile.badges.length}', style: TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w600, color: AppTheme.secondaryColor)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (unlocked.isNotEmpty)
            Wrap(spacing: 8, runSpacing: 8, children: unlocked.map((b) => BadgeChip(icon: b.iconData, label: b.name, unlocked: true, unlockedColor: AppTheme.secondaryColor, tooltip: '${b.name}\n${b.description}')).toList()),
          if (unlocked.isNotEmpty && locked.isNotEmpty) const SizedBox(height: 12),
          if (locked.isNotEmpty) ...[
            Text(context.t('Locked Badges'), style: TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w500, color: AppTheme.textSecondary)),
            const SizedBox(height: 6),
            Wrap(spacing: 8, runSpacing: 8, children: locked.take(6).map((b) => BadgeChip(icon: b.iconData, label: b.name, unlocked: false, tooltip: '${b.name}\n${b.description}')).toList()),
            if (locked.length > 6)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text('+${locked.length - 6} ${context.t('more locked badges')}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textXs, fontStyle: FontStyle.italic)),
              ),
          ],
          if (unlocked.isEmpty && locked.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 8),
              child: Text('No badges available yet.', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
            ),
        ],
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
                  context.t('My Reports'),
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg, color: AppTheme.textPrimary),
                ),
                const Spacer(),
                Text(
                  '${profile.totalReports} ${context.t('total')}',
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
                            Text(context.t('No reports yet'), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                            Text(
                              context.t('Tap + in Reports to submit'),
                              style: TextStyle(color: AppTheme.textSecondary.withOpacity(0.6), fontSize: AppTheme.textXs),
                            ),
                            const SizedBox(height: 12),
                            OutlinedButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(builder: (_) => const ReportsListScreen()),
                              ),
                              icon: const Icon(Icons.add, size: 16),
                              label: Text(context.t('Go to Reports')),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppTheme.primaryColor,
                                side: BorderSide(color: AppTheme.primaryColor.withOpacity(0.4)),
                                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                              ),
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
    return ReportCard(report: report, onTap: () => _showReportDetails(context, report));
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
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                shape: BoxShape.circle,
              ),
              child: Icon(Icons.person_outline, size: 56, color: Colors.grey.shade400),
            ),
            const SizedBox(height: 24),
            const Text(
              'Welcome!',
              style: TextStyle(fontSize: 24, fontWeight: FontWeight.w800, color: Color(0xFF2D3436)),
            ),
            const SizedBox(height: 8),
            Text(
              'Sign in to view your profile, earn XP, and unlock rewards',
              style: TextStyle(fontSize: 14, color: Colors.grey.shade500),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 32),
            Container(
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF00695C), Color(0xFF00897B)],
                ),
                borderRadius: BorderRadius.circular(14),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFF00695C).withOpacity(0.3),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () => Navigator.of(context).pushReplacementNamed('/login'),
                  borderRadius: BorderRadius.circular(14),
                  child: const Padding(
                    padding: EdgeInsets.symmetric(horizontal: 32, vertical: 14),
                    child: Text(
                      'Go to Login',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
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
                  Flexible(
                    child: Text(
                      report.district ?? context.t('No location data'),
                      style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                      overflow: TextOverflow.ellipsis,
                    ),
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
