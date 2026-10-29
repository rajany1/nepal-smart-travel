import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/auth_provider.dart';
import '../../providers/leaderboard_provider.dart';
import '../../core/widgets/shimmer_loading.dart';

class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({super.key});

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> {
  bool _initialLoad = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final provider = context.read<LeaderboardProvider>();
      await provider.refreshAll();
      if (mounted) setState(() => _initialLoad = false);
    });
  }

  @override
  Widget build(BuildContext context) {
    final myId = context.watch<AuthProvider>().user?.id;

    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      body: SafeArea(
        bottom: false,
        child: Consumer<LeaderboardProvider>(
          builder: (context, provider, child) {
            final inList =
                myId != null && provider.users.any((u) => u.userId == myId);
            final showRankCard =
                myId != null && !inList && provider.userRank != null;
            final showSkeleton =
                (_initialLoad || provider.isLoading) && provider.users.isEmpty;

            return RefreshIndicator(
              color: AppTheme.primaryColor,
              backgroundColor: Colors.white,
              onRefresh: () => provider.refreshAll(),
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                slivers: [
                  SliverToBoxAdapter(
                    child: _LeaderboardHeader(
                      totalUsers: provider.totalUsers,
                      onBack: () => Navigator.pop(context),
                      onRefresh: () => provider.refreshAll(),
                    ),
                  ),
                  SliverToBoxAdapter(child: _CategoryRail(provider: provider)),
                  if (provider.isLoading && provider.users.isNotEmpty)
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 0, 20, 0),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(2),
                          child: LinearProgressIndicator(
                            minHeight: 2.5,
                            backgroundColor: AppTheme.primaryColor.withValues(
                              alpha: 0.08,
                            ),
                            color: AppTheme.primaryColor,
                          ),
                        ),
                      ),
                    ),
                  if (showSkeleton)
                    const SliverToBoxAdapter(child: _LeaderboardLoading())
                  else if (provider.errorMessage != null &&
                      provider.users.isEmpty)
                    SliverToBoxAdapter(
                      child: _LeaderboardStatusCard(
                        icon: Icons.cloud_off_rounded,
                        color: AppTheme.severityHigh,
                        title: context.t('Could not load leaderboard'),
                        message: context.t(
                          'Check your connection and try again. Pull down to refresh.',
                        ),
                        actionLabel: context.t('Try again'),
                        onAction: () => provider.refreshAll(),
                      ),
                    )
                  else if (provider.users.isEmpty)
                    SliverToBoxAdapter(
                      child: _LeaderboardStatusCard(
                        icon: Icons.emoji_events_outlined,
                        color: AppTheme.secondaryColor,
                        title: context.t('No rankings available yet'),
                        message: context.t(
                          'Be the first to earn XP and claim the top spot.',
                        ),
                        actionLabel: context.t('Refresh'),
                        onAction: () => provider.refreshAll(),
                      ),
                    )
                  else ...[
                    SliverToBoxAdapter(
                      child: _PodiumCard(
                        topThree: provider.topThree,
                        category: provider.activeCategory,
                        totalUsers: provider.totalUsers,
                      ),
                    ),
                    if (showRankCard)
                      SliverToBoxAdapter(
                        child: _UserRankCard(
                          rank: provider.userRank!,
                          totalUsers: provider.totalUsers,
                        ),
                      ),
                    SliverToBoxAdapter(
                      child: _LeaderboardListHeader(
                        count: provider.users.length,
                        category: provider.activeCategory,
                      ),
                    ),
                    SliverList(
                      delegate: SliverChildBuilderDelegate((context, index) {
                        final user = provider.users[index];
                        return _LeaderboardRow(
                          user: user,
                          category: provider.activeCategory,
                          isCurrentUser: myId != null && user.userId == myId,
                        );
                      }, childCount: provider.users.length),
                    ),
                    const SliverToBoxAdapter(child: SizedBox(height: 28)),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

// ============ HEADER ============

class _LeaderboardHeader extends StatelessWidget {
  final int totalUsers;
  final VoidCallback onBack;
  final VoidCallback onRefresh;

  const _LeaderboardHeader({
    required this.totalUsers,
    required this.onBack,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
    final subtitle =
        totalUsers > 0
            ? '${context.t('Top 10 of')} ${_formatInt(totalUsers)} ${context.t('explorers')}'
            : context.t("Nepal's top explorers");

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
      child: Row(
        children: [
          _RoundIconButton(
            icon: Icons.arrow_back_ios_new_rounded,
            label: context.t('Back'),
            onTap: onBack,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.t('Leaderboard'),
                  style: const TextStyle(
                    fontSize: 23,
                    fontWeight: FontWeight.w800,
                    color: AppTheme.textPrimary,
                    height: 1.1,
                  ),
                ),
                const SizedBox(height: 5),
                Row(
                  children: [
                    const Icon(
                      Icons.emoji_events_rounded,
                      size: 13,
                      color: AppTheme.secondaryColor,
                    ),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                          color: AppTheme.textSecondary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          _RoundIconButton(
            icon: Icons.refresh_rounded,
            label: context.t('Refresh'),
            onTap: onRefresh,
          ),
        ],
      ),
    );
  }
}

class _RoundIconButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _RoundIconButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: label,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onTap,
          child: Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFEDE8DE)),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Icon(icon, size: 19, color: AppTheme.textPrimary),
          ),
        ),
      ),
    );
  }
}

// ============ CATEGORY RAIL ============

class _LeaderboardCategory {
  final String key;
  final String label;
  final IconData icon;

  const _LeaderboardCategory({
    required this.key,
    required this.label,
    required this.icon,
  });
}

class _CategoryRail extends StatelessWidget {
  final LeaderboardProvider provider;

  const _CategoryRail({required this.provider});

  static const List<_LeaderboardCategory> categories = [
    _LeaderboardCategory(
      key: 'xp',
      label: 'XP',
      icon: Icons.auto_awesome_rounded,
    ),
    _LeaderboardCategory(
      key: 'reports',
      label: 'Reports',
      icon: Icons.assignment_turned_in_rounded,
    ),
    _LeaderboardCategory(
      key: 'alerts',
      label: 'Alerts',
      icon: Icons.warning_amber_rounded,
    ),
    _LeaderboardCategory(
      key: 'earnings',
      label: 'Earnings',
      icon: Icons.toll_rounded,
    ),
    _LeaderboardCategory(
      key: 'contributions',
      label: 'Map Contribution',
      icon: Icons.add_location_alt_rounded,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 88,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(20, 14, 20, 6),
        itemCount: categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 6),
        itemBuilder: (context, index) {
          final cat = categories[index];
          final isActive = provider.activeCategory == cat.key;
          final color = _categoryColor(cat.key);

          return Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => provider.switchCategory(cat.key),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 9,
                ),
                decoration: BoxDecoration(
                  gradient:
                      isActive
                          ? const LinearGradient(
                            colors: [
                              AppTheme.primaryColor,
                              AppTheme.primaryDark,
                            ],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          )
                          : null,
                  color: isActive ? null : Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color:
                        isActive
                            ? AppTheme.primaryColor
                            : const Color(0xFFEDE8DE),
                  ),
                  boxShadow:
                      isActive
                          ? [
                            BoxShadow(
                              color: AppTheme.primaryColor.withValues(
                                alpha: 0.2,
                              ),
                              blurRadius: 14,
                              offset: const Offset(0, 5),
                            ),
                          ]
                          : null,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 31,
                      height: 31,
                      decoration: BoxDecoration(
                        color:
                            isActive
                                ? Colors.white.withValues(alpha: 0.16)
                                : color.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(
                        cat.icon,
                        size: 16,
                        color: isActive ? Colors.white : color,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      context.t(cat.label),
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight:
                            isActive ? FontWeight.w700 : FontWeight.w600,
                        color: isActive ? Colors.white : AppTheme.textPrimary,
                      ),
                      maxLines: 1,
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

// ============ PODIUM ============

class _PodiumCard extends StatelessWidget {
  final List<LeaderboardUser> topThree;
  final String category;
  final int totalUsers;

  const _PodiumCard({
    required this.topThree,
    required this.category,
    required this.totalUsers,
  });

  @override
  Widget build(BuildContext context) {
    if (topThree.isEmpty) return const SizedBox.shrink();

    // Podium order: 2nd, 1st, 3rd.
    var display = <int>[0];
    if (topThree.length >= 3) {
      display = [1, 0, 2];
    } else if (topThree.length >= 2) {
      display = [1, 0];
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 10, 20, 0),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0B3D3A), Color(0xFF00695C)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(26),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primaryColor.withValues(alpha: 0.24),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            top: -58,
            right: -30,
            child: Container(
              width: 160,
              height: 160,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.05),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -70,
            left: -40,
            child: Container(
              width: 180,
              height: 180,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.035),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 17, 16, 15),
            child: Column(
              children: [
                Row(
                  children: [
                    Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.13),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(
                        Icons.emoji_events_rounded,
                        size: 17,
                        color: Color(0xFFFFD166),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        context.t('TOP CONTRIBUTORS').toUpperCase(),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1.2,
                          color: Colors.white.withValues(alpha: 0.78),
                        ),
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        context.t(_categoryShortLabel(category)),
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                        maxLines: 1,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children:
                      display.map((index) {
                        if (index >= topThree.length) {
                          return const Expanded(child: SizedBox.shrink());
                        }
                        return Expanded(
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 3),
                            child: _PodiumEntry(
                              user: topThree[index],
                              category: category,
                            ),
                          ),
                        );
                      }).toList(),
                ),
                const SizedBox(height: 14),
                Container(
                  height: 1,
                  color: Colors.white.withValues(alpha: 0.12),
                ),
                const SizedBox(height: 11),
                Row(
                  children: [
                    Icon(
                      Icons.trending_up_rounded,
                      size: 14,
                      color: Colors.white.withValues(alpha: 0.65),
                    ),
                    const SizedBox(width: 5),
                    Expanded(
                      child: Text(
                        '${context.t('Ranked by')} ${context.t(_categoryLabel(category))}',
                        style: TextStyle(
                          fontSize: 10.5,
                          color: Colors.white.withValues(alpha: 0.68),
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (totalUsers > 0) ...[
                      const SizedBox(width: 8),
                      Text(
                        '${_formatInt(totalUsers)} ${context.t('explorers')}',
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w600,
                          color: Colors.white.withValues(alpha: 0.78),
                        ),
                        maxLines: 1,
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PodiumEntry extends StatelessWidget {
  final LeaderboardUser user;
  final String category;

  const _PodiumEntry({required this.user, required this.category});

  @override
  Widget build(BuildContext context) {
    final isFirst = user.rank == 1;
    final medal = _rankColor(user.rank);
    final avatarSize = isFirst ? 56.0 : 46.0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (isFirst)
          const Padding(
            padding: EdgeInsets.only(bottom: 4),
            child: Icon(
              Icons.workspace_premium_rounded,
              size: 20,
              color: Color(0xFFFFD166),
            ),
          ),
        Center(
          child: _LeaderboardAvatar(
            user: user,
            size: avatarSize,
            ringColor: medal,
            ringWidth: isFirst ? 2.5 : 2,
          ),
        ),
        const SizedBox(height: 7),
        Tooltip(
          message: user.name,
          child: Text(
            user.name.split(' ').first,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: isFirst ? 13 : 12,
              fontWeight: isFirst ? FontWeight.w800 : FontWeight.w700,
              color: Colors.white,
              height: 1.15,
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        const SizedBox(height: 4),
        Center(
          child: Container(
            constraints: const BoxConstraints(maxWidth: 96),
            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.13),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              _metricLabel(context, user, category),
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 9.5,
                fontWeight: FontWeight.w700,
                color: Colors.white,
              ),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
        const SizedBox(height: 8),
        Container(
          height: isFirst ? 40 : (user.rank == 2 ? 31 : 25),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.07),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
            border: Border(
              top: BorderSide(color: medal.withValues(alpha: 0.9), width: 2),
            ),
          ),
          child: Center(
            child: Text(
              '${user.rank}',
              style: TextStyle(
                fontSize: isFirst ? 18 : 14,
                fontWeight: FontWeight.w800,
                color: medal,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

// ============ YOUR RANK ============

class _UserRankCard extends StatelessWidget {
  final int rank;
  final int totalUsers;

  const _UserRankCard({required this.rank, required this.totalUsers});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 14, 20, 0),
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.primaryColor.withValues(alpha: 0.1),
            AppTheme.accentColor.withValues(alpha: 0.05),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: AppTheme.primaryColor.withValues(alpha: 0.18),
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppTheme.primaryColor, AppTheme.primaryLight],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: AppTheme.primaryColor.withValues(alpha: 0.22),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: const Icon(
              Icons.person_pin_circle_rounded,
              color: Colors.white,
              size: 26,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.t('YOUR RANK').toUpperCase(),
                  style: const TextStyle(
                    fontSize: 9.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.8,
                    color: AppTheme.textSecondary,
                  ),
                ),
                const SizedBox(height: 2),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      '#$rank',
                      style: const TextStyle(
                        fontSize: 25,
                        fontWeight: FontWeight.w800,
                        color: AppTheme.primaryColor,
                        height: 1,
                      ),
                    ),
                    if (totalUsers > 0) ...[
                      const SizedBox(width: 4),
                      Padding(
                        padding: const EdgeInsets.only(bottom: 2),
                        child: Text(
                          '/ ${_formatInt(totalUsers)}',
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  context.t('Keep contributing to climb higher'),
                  style: const TextStyle(
                    fontSize: 10.5,
                    color: AppTheme.textSecondary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.75),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.trending_up_rounded,
              size: 18,
              color: AppTheme.primaryColor,
            ),
          ),
        ],
      ),
    );
  }
}

// ============ LIST HEADER ============

class _LeaderboardListHeader extends StatelessWidget {
  final int count;
  final String category;

  const _LeaderboardListHeader({required this.count, required this.category});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 22, 20, 12),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppTheme.primaryColor.withValues(alpha: 0.09),
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.format_list_numbered_rounded,
              size: 20,
              color: AppTheme.primaryColor,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      context.t('Top 10'),
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: AppTheme.textPrimary,
                        height: 1.15,
                      ),
                    ),
                    const SizedBox(width: 7),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor.withValues(alpha: 0.09),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '$count',
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: AppTheme.primaryColor,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(
                  context.t('Only the top 10 contributors are listed'),
                  style: const TextStyle(
                    fontSize: 11,
                    color: AppTheme.textSecondary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ============ LEADERBOARD ROW ============

class _LeaderboardRow extends StatelessWidget {
  final LeaderboardUser user;
  final String category;
  final bool isCurrentUser;

  const _LeaderboardRow({
    required this.user,
    required this.category,
    this.isCurrentUser = false,
  });

  @override
  Widget build(BuildContext context) {
    final rankColor = _rankColor(user.rank);
    final categoryColor = _categoryColor(category);
    final verification = _verificationColor(user.verificationTick);
    final metric = _metricNumber(user, category);

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 10),
      decoration: BoxDecoration(
        color:
            isCurrentUser
                ? AppTheme.primaryColor.withValues(alpha: 0.055)
                : Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color:
              isCurrentUser
                  ? AppTheme.primaryColor.withValues(alpha: 0.28)
                  : user.rank <= 3
                  ? rankColor.withValues(alpha: 0.2)
                  : const Color(0xFFEDE8DE),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(11),
        child: Row(
          children: [
            _RankBadge(rank: user.rank),
            const SizedBox(width: 11),
            _LeaderboardAvatar(user: user, size: 40),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Flexible(
                        child: Text(
                          user.name,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: AppTheme.textPrimary,
                            height: 1.2,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (isCurrentUser) ...[
                        const SizedBox(width: 4),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 5,
                            vertical: 1,
                          ),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryColor,
                            borderRadius: BorderRadius.circular(5),
                          ),
                          child: Text(
                            context.t('You'),
                            style: const TextStyle(
                              fontSize: 8,
                              fontWeight: FontWeight.w700,
                              color: Colors.white,
                            ),
                          ),
                        ),
                      ],
                      if (verification != null) ...[
                        const SizedBox(width: 3),
                        Icon(
                          Icons.verified_rounded,
                          size: 14,
                          color: verification,
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(
                        Icons.military_tech_rounded,
                        size: 12,
                        color: categoryColor,
                      ),
                      const SizedBox(width: 2),
                      Flexible(
                        child: Text(
                          context.t(user.levelName),
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w500,
                            color: AppTheme.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      if (user.badgeCount > 0) ...[
                        const SizedBox(width: 8),
                        const Icon(
                          Icons.workspace_premium_rounded,
                          size: 12,
                          color: AppTheme.secondaryColor,
                        ),
                        const SizedBox(width: 2),
                        Text(
                          '${user.badgeCount}',
                          style: const TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                            color: AppTheme.secondaryDark,
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  _formatNumber(metric),
                  style: TextStyle(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w800,
                    color: categoryColor,
                    height: 1,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  context.t(_metricUnit(category, value: metric)),
                  style: const TextStyle(
                    fontSize: 9,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.3,
                    color: AppTheme.textSecondary,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _RankBadge extends StatelessWidget {
  final int rank;

  const _RankBadge({required this.rank});

  @override
  Widget build(BuildContext context) {
    final color = _rankColor(rank);
    final isTopThree = rank <= 3;

    return Container(
      width: 34,
      height: 34,
      decoration: BoxDecoration(
        gradient:
            isTopThree
                ? LinearGradient(
                  colors: [
                    color.withValues(alpha: 0.22),
                    color.withValues(alpha: 0.36),
                  ],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
                : null,
        color: isTopThree ? null : const Color(0xFFF7F4EC),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(
          color:
              isTopThree
                  ? color.withValues(alpha: 0.35)
                  : const Color(0xFFEDE8DE),
        ),
      ),
      child: Center(
        child: Text(
          '$rank',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: isTopThree ? color : AppTheme.textSecondary,
          ),
        ),
      ),
    );
  }
}

class _LeaderboardAvatar extends StatelessWidget {
  final LeaderboardUser user;
  final double size;
  final Color? ringColor;
  final double ringWidth;

  const _LeaderboardAvatar({
    required this.user,
    required this.size,
    this.ringColor,
    this.ringWidth = 2,
  });

  @override
  Widget build(BuildContext context) {
    final fallback = _avatarColor(user.name);
    final initial =
        user.name.trim().isNotEmpty ? user.name.trim()[0].toUpperCase() : '?';
    final avatarUrl = user.avatarUrl;

    return Container(
      padding: EdgeInsets.all(ringColor != null ? 2.5 : 0),
      decoration:
          ringColor != null
              ? BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: ringColor!, width: ringWidth),
              )
              : null,
      child: SizedBox(
        width: size,
        height: size,
        child: ClipOval(
          child: Stack(
            fit: StackFit.expand,
            children: [
              Container(
                color: fallback,
                child: Center(
                  child: Text(
                    initial,
                    style: TextStyle(
                      fontSize: size * 0.4,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.primaryDark.withValues(alpha: 0.7),
                    ),
                  ),
                ),
              ),
              if (avatarUrl != null && avatarUrl.trim().isNotEmpty)
                Positioned.fill(
                  child: Image.network(
                    avatarUrl,
                    fit: BoxFit.cover,
                    gaplessPlayback: true,
                    loadingBuilder: (context, child, progress) {
                      return progress == null ? child : const SizedBox.shrink();
                    },
                    errorBuilder: (context, error, stackTrace) {
                      return const SizedBox.shrink();
                    },
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============ STATUS / LOADING ============

class _LeaderboardStatusCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String message;
  final String actionLabel;
  final VoidCallback onAction;

  const _LeaderboardStatusCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.message,
    required this.actionLabel,
    required this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 18, 20, 0),
      padding: const EdgeInsets.fromLTRB(22, 26, 22, 22),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFEDE8DE)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 32, color: color),
          ),
          const SizedBox(height: 14),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 17,
              fontWeight: FontWeight.w700,
              color: AppTheme.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              height: 1.45,
              color: AppTheme.textSecondary,
            ),
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onAction,
            icon: const Icon(Icons.refresh_rounded, size: 16),
            label: Text(actionLabel),
            style: OutlinedButton.styleFrom(
              foregroundColor: color,
              side: BorderSide(color: color.withValues(alpha: 0.35)),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              textStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _LeaderboardLoading extends StatelessWidget {
  const _LeaderboardLoading();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
      child: ShimmerSkeleton(
        child: Column(
          children: [
            Container(
              height: 242,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(26),
              ),
            ),
            const SizedBox(height: 18),
            Container(
              height: 62,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
              ),
            ),
            const SizedBox(height: 22),
            for (var i = 0; i < 5; i++) ...[
              Container(
                height: 64,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                ),
              ),
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

// ============ HELPERS ============

Color _categoryColor(String category) {
  switch (category) {
    case 'reports':
      return const Color(0xFF2563EB);
    case 'alerts':
      return AppTheme.severityHigh;
    case 'earnings':
      return AppTheme.successColor;
    case 'contributions':
      return const Color(0xFF7C3AED);
    case 'xp':
    default:
      return AppTheme.secondaryDark;
  }
}

String _categoryLabel(String category) {
  switch (category) {
    case 'reports':
      return 'Reports';
    case 'alerts':
      return 'Alerts';
    case 'earnings':
      return 'Earnings';
    case 'contributions':
      return 'Contribution';
    case 'xp':
    default:
      return 'XP';
  }
}

String _categoryShortLabel(String category) {
  switch (category) {
    case 'contributions':
      return 'Map';
    case 'earnings':
      return 'Coins';
    case 'reports':
      return 'Reports';
    case 'alerts':
      return 'Alerts';
    case 'xp':
    default:
      return 'XP';
  }
}

Color _rankColor(int rank) {
  switch (rank) {
    case 1:
      return const Color(0xFFC9911A);
    case 2:
      return const Color(0xFF7B8794);
    case 3:
      return const Color(0xFFB4682E);
    default:
      return AppTheme.textSecondary;
  }
}

Color? _verificationColor(String tick) {
  switch (tick) {
    case 'verified':
      return AppTheme.blueTick;
    case 'green':
      return AppTheme.greenTick;
    case 'gold':
      return AppTheme.goldTick;
    case 'diamond':
      return AppTheme.diamondTick;
    default:
      return null;
  }
}

Color _avatarColor(String name) {
  const palette = [
    Color(0xFFB2DFDB),
    Color(0xFFFFE0B2),
    Color(0xFFC5CAE9),
    Color(0xFFF8BBD0),
    Color(0xFFC8E6C9),
    Color(0xFFB3E5FC),
  ];
  if (name.isEmpty) return palette.first;
  final sum = name.codeUnits.fold<int>(0, (total, unit) => total + unit);
  return palette[sum % palette.length];
}

String _metricLabel(
  BuildContext context,
  LeaderboardUser user,
  String category,
) {
  final value = _metricNumber(user, category);
  return '${_formatNumber(value)} ${context.t(_metricUnit(category, value: value))}';
}

num _metricNumber(LeaderboardUser user, String category) {
  switch (category) {
    case 'reports':
      return user.totalReports;
    case 'alerts':
      return user.totalAlerts;
    case 'earnings':
      return user.totalEarned;
    case 'contributions':
      return user.contributionScore;
    case 'xp':
    default:
      return user.totalXp;
  }
}

String _metricUnit(String category, {num? value}) {
  final singular = value == 1;
  switch (category) {
    case 'reports':
      return singular ? 'Report' : 'Reports';
    case 'alerts':
      return singular ? 'Alert' : 'Alerts';
    case 'earnings':
      return singular ? 'Coin' : 'Coins';
    case 'contributions':
      return singular ? 'Point' : 'Points';
    case 'xp':
    default:
      return 'XP';
  }
}

String _formatNumber(num n) {
  if (n >= 1000000) return '${(n / 1000000).toStringAsFixed(1)}M';
  if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}k';
  if (n == n.roundToDouble()) return n.round().toString();
  return n.toStringAsFixed(1);
}

String _formatInt(int value) {
  final digits = value.abs().toString();
  final buffer = StringBuffer();
  for (var i = 0; i < digits.length; i++) {
    if (i > 0 && (digits.length - i) % 3 == 0) buffer.write(',');
    buffer.write(digits[i]);
  }
  return value < 0 ? '-$buffer' : buffer.toString();
}
