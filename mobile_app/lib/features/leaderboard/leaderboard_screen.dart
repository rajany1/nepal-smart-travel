import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/leaderboard_provider.dart';

class LeaderboardScreen extends StatefulWidget {
  const LeaderboardScreen({super.key});

  @override
  State<LeaderboardScreen> createState() => _LeaderboardScreenState();
}

class _LeaderboardScreenState extends State<LeaderboardScreen> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<LeaderboardProvider>().refreshAll();
    });
    _scrollController.addListener(_onScroll);
  }

  void _onScroll() {
    if (_scrollController.position.pixels >=
        _scrollController.position.maxScrollExtent - 200) {
      context.read<LeaderboardProvider>().loadMore();
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.emoji_events, color: AppTheme.secondaryColor),
            SizedBox(width: 8),
            Text('Leaderboard'),
          ],
        ),
      ),
      body: Consumer<LeaderboardProvider>(
        builder: (context, provider, child) {
          return RefreshIndicator(
            onRefresh: () => provider.refreshAll(),
            child: CustomScrollView(
              controller: _scrollController,
              slivers: [
                // Category filter chips
                SliverToBoxAdapter(child: _CategoryFilter(provider: provider)),
                // Top 3 podium
                SliverToBoxAdapter(child: _PodiumSection(topThree: provider.topThree)),
                // Your rank card
                if (provider.userRank != null)
                  SliverToBoxAdapter(child: _UserRankCard(rank: provider.userRank!)),
                // Ranked list header
                const SliverToBoxAdapter(
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                    child: Text('All Rankings',
                        style: TextStyle(
                            fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
                  ),
                ),
                // Loading
                if (provider.isLoading)
                  const SliverFillRemaining(
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (provider.errorMessage != null)
                  SliverFillRemaining(
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.cloud_off, size: 48,
                              color: AppTheme.textSecondary),
                          const SizedBox(height: 8),
                          Text(provider.errorMessage!,
                              style: const TextStyle(
                                  color: AppTheme.textSecondary)),
                          const SizedBox(height: 8),
                          ElevatedButton(
                              onPressed: () => provider.refreshAll(),
                              child: const Text('Retry')),
                        ],
                      ),
                    ),
                  )
                else if (provider.users.isEmpty)
                  const SliverFillRemaining(
                    child: Center(child: Text('No rankings available yet')),
                  )
                else ...[
                  SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final user = provider.users[index];
                        final isMe = false; // Could compare with auth provider
                        return _LeaderboardRow(
                          user: user,
                          isCurrentUser: isMe,
                        );
                      },
                      childCount: provider.users.length,
                    ),
                  ),
                  if (provider.hasMore)
                    const SliverToBoxAdapter(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                      ),
                    ),
                  // Bottom padding
                  const SliverToBoxAdapter(
                    child: SizedBox(height: 80),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

// ============ CATEGORY FILTER ============

class _CategoryFilter extends StatelessWidget {
  final LeaderboardProvider provider;

  const _CategoryFilter({required this.provider});

  @override
  Widget build(BuildContext context) {
    final categories = [
      {'key': 'xp', 'label': 'XP', 'icon': Icons.emoji_events},
      {'key': 'reports', 'label': 'Reports', 'icon': Icons.assignment},
      {'key': 'alerts', 'label': 'Alerts', 'icon': Icons.warning_amber},
      {'key': 'reviews', 'label': 'Reviews', 'icon': Icons.rate_review},
    ];

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Row(
        children: categories.map((cat) {
          final isActive = provider.activeCategory == cat['key'];
          return Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 3),
              child: GestureDetector(
                onTap: () => provider.switchCategory(cat['key'] as String),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  decoration: BoxDecoration(
                    color: isActive ? AppTheme.primaryColor : AppTheme.primaryLight.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: isActive ? AppTheme.primaryColor : AppTheme.dividerColor,
                    ),
                  ),
                  child: Column(
                    children: [
                      Icon(
                        cat['icon'] as IconData,
                        color: isActive ? Colors.white : AppTheme.textSecondary,
                        size: 20,
                      ),
                      const SizedBox(height: 2),
                      Text(
                        cat['label'] as String,
                        style: TextStyle(
                          fontSize: AppTheme.textXs,
                          fontWeight: isActive ? FontWeight.bold : FontWeight.normal,
                          color: isActive ? Colors.white : AppTheme.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

// ============ PODIUM SECTION ============

class _PodiumSection extends StatelessWidget {
  final List<LeaderboardUser> topThree;

  const _PodiumSection({required this.topThree});

  @override
  Widget build(BuildContext context) {
    if (topThree.isEmpty) return const SizedBox.shrink();

    // Reorder for podium display: 2nd, 1st, 3rd
    var display = <int?>[0];
    if (topThree.length >= 3) {
      display = [1, 0, 2]; // silver, gold, bronze
    } else if (topThree.length >= 2) {
      display = [1, 0];
    }

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 8),
      child: Column(
        children: [
          const Text('🏆 Top Contributors',
              style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold)),
          const SizedBox(height: 20),
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              mainAxisAlignment: MainAxisAlignment.center,
              children: display.map((index) {
                if (index == null || index >= topThree.length) {
                  return const SizedBox.shrink();
                }
                final user = topThree[index];
                final isFirst = user.rank == 1;
                final isSecond = user.rank == 2;

                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        // Rank badge
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: isFirst
                                ? const Color(0xFFFFD700).withOpacity(0.2)
                                : isSecond
                                    ? const Color(0xFFC0C0C0).withOpacity(0.2)
                                    : const Color(0xFFCD7F32).withOpacity(0.2),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '${user.rank}${_getOrdinal(user.rank)}',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              color: isFirst
                                  ? const Color(0xFFFFD700)
                                  : isSecond
                                      ? const Color(0xFFC0C0C0)
                                      : const Color(0xFFCD7F32),
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        // Avatar
                        CircleAvatar(
                          radius: isFirst ? 28 : 24,
                          backgroundColor: AppTheme.primaryLight.withOpacity(0.15),
                          child: Text(
                            user.name.isNotEmpty ? user.name[0].toUpperCase() : '?',
                            style: TextStyle(
                              fontSize: isFirst ? 24 : 20,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.primaryColor,
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        // Name
                        Text(
                          user.name.split(' ').first,
                          style: TextStyle(
                            fontWeight: isFirst ? FontWeight.bold : FontWeight.w600,
                            fontSize: isFirst ? 14 : 13,
                          ),
                          textAlign: TextAlign.center,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        // XP
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(
                            color: AppTheme.secondaryColor.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            '${_formatNumber(user.totalXp)} XP',
                            style: const TextStyle(
                              fontSize: 11,
                              color: AppTheme.secondaryColor,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                        // Level
                        Text(
                          user.levelName,
                          style: const TextStyle(
                            fontSize: AppTheme.textXs,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
        ],
      ),
    );
  }

  String _getOrdinal(int n) {
    if (n == 1) return 'st';
    if (n == 2) return 'nd';
    if (n == 3) return 'rd';
    return 'th';
  }

  String _formatNumber(int n) {
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}k';
    return n.toString();
  }
}

// ============ YOUR RANK CARD ============

class _UserRankCard extends StatelessWidget {
  final int rank;

  const _UserRankCard({required this.rank});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppTheme.primaryColor.withOpacity(0.1),
            AppTheme.primaryLight.withOpacity(0.05),
          ],
        ),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.primaryColor.withOpacity(0.2)),
      ),
      child: Row(
        children: [
          const Icon(Icons.person_pin, color: AppTheme.primaryColor),
          const SizedBox(width: 8),
          const Text('Your Rank', style: TextStyle(fontWeight: FontWeight.w600)),
          const Spacer(),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            decoration: BoxDecoration(
              color: AppTheme.primaryColor,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '#$rank',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.bold,
                fontSize: AppTheme.textLg,
              ),
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
  final bool isCurrentUser;

  const _LeaderboardRow({
    required this.user,
    this.isCurrentUser = false,
  });

  @override
  Widget build(BuildContext context) {
    final rankColor = user.rank == 1
        ? const Color(0xFFFFD700)
        : user.rank == 2
            ? const Color(0xFFC0C0C0)
            : user.rank == 3
                ? const Color(0xFFCD7F32)
                : AppTheme.textSecondary;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 3),
      decoration: BoxDecoration(
        color: isCurrentUser ? AppTheme.primaryColor.withOpacity(0.05) : null,
        borderRadius: BorderRadius.circular(10),
        border: isCurrentUser
            ? Border.all(color: AppTheme.primaryColor.withOpacity(0.2))
            : null,
      ),
      child: ListTile(
        leading: user.rank <= 3
            ? Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: rankColor.withOpacity(0.15),
                ),
                child: Center(
                  child: Text(
                    '${user.rank}',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: rankColor,
                      fontSize: AppTheme.textBase,
                    ),
                  ),
                ),
              )
            : SizedBox(
                width: 36,
                child: Center(
                  child: Text(
                    '${user.rank}',
                    style: TextStyle(
                      color: AppTheme.textSecondary.withOpacity(0.6),
                      fontSize: 13,
                    ),
                  ),
                ),
              ),
        title: Row(
          children: [
            Flexible(
              child: Text(
                user.name,
                style: TextStyle(
                  fontWeight:
                      user.rank <= 3 ? FontWeight.bold : FontWeight.w500,
                ),
                overflow: TextOverflow.ellipsis,
              ),
            ),
            if (user.verificationTick == 'verified')
              const Padding(
                padding: EdgeInsets.only(left: 4),
                child: Icon(Icons.verified, size: 16, color: AppTheme.primaryColor),
              ),
            if (user.badgeCount > 0) ...[
              const SizedBox(width: 4),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                decoration: BoxDecoration(
                  color: AppTheme.secondaryColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(
                  '${user.badgeCount}',
                  style: const TextStyle(
                    fontSize: 9,
                    color: AppTheme.secondaryColor,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ],
        ),
        subtitle: Row(
          children: [
            Icon(Icons.emoji_events, size: 12, color: AppTheme.secondaryColor),
            const SizedBox(width: 2),
            Text('${_formatNumber(user.totalXp)} XP',
                style: const TextStyle(fontSize: 11)),
            if (user.approvedReports > 0) ...[
              const SizedBox(width: 8),
              const Icon(Icons.check_circle, size: 12, color: AppTheme.successColor),
              const SizedBox(width: 2),
              Text('${user.approvedReports}',
                  style: const TextStyle(fontSize: 11)),
            ],
            const SizedBox(width: 8),
            Text(user.levelName,
                style: const TextStyle(fontSize: 11)),
          ],
        ),
        trailing: user.rank <= 3
            ? Icon(
                user.rank == 1
                    ? Icons.emoji_events
                    : user.rank == 2
                        ? Icons.emoji_events
                        : Icons.emoji_events,
                color: rankColor,
                size: 20,
              )
            : null,
      ),
    );
  }

  String _formatNumber(int n) {
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}k';
    return n.toString();
  }
}
