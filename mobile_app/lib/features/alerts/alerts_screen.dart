import "dart:async";

import "../../core/services/localization_service.dart";
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/alert_provider.dart';
import '../../providers/sos_provider.dart';
import '../../core/services/location_service.dart';
import '../emergency/emergency_screen.dart';
import '../../core/widgets/shimmer_loading.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  final LocationService _locationService = LocationService();
  // FL-32: keys seen before — only genuinely new items trigger a notification
  final Set<String> _seenItemKeys = {};
  // Pull-to-refresh cooldown
  DateTime? _lastRefreshTime;
  static const _refreshCooldown = Duration(seconds: 10);
  static const _snapshotMaxAge = Duration(seconds: 30);
  static const _maxLastKnownAge = Duration(minutes: 5);
  bool _initializing = true;
  bool _refreshing = false;

  @override
  void initState() {
    super.initState();
    // Home already resolves the location and loads nearby alerts. Reuse that
    // snapshot immediately instead of showing a skeleton while this screen
    // starts a second GPS fix.
    final provider = context.read<AlertProvider>();
    if (provider.hasLocation) {
      _initializing = false;
      _lastRefreshTime = DateTime.now();
      _seedSeenItems(provider);
    }
    WidgetsBinding.instance.addPostFrameCallback((_) => _initialize());
  }

  void _seedSeenItems(AlertProvider provider) {
    _seenItemKeys.addAll(provider.items.map((i) => '${i.source}:${i.id}'));
  }

  Future<void> _initialize() async {
    final provider = context.read<AlertProvider>();
    final sosProvider = context.read<SosProvider>();

    // Fast path: location is already available from Home. No GPS wait and no
    // redundant request when the snapshot was just fetched.
    if (provider.hasLocation) {
      if (!provider.isLoading &&
          (provider.errorMessage != null ||
              provider.dataIsOlderThan(_snapshotMaxAge))) {
        await provider.fetchNearby();
      }
      if (!sosProvider.isLoading && sosProvider.nearbySos.isEmpty) {
        unawaited(
          sosProvider.fetchNearbySos(
            provider.latitude!,
            provider.longitude!,
            radiusKm: 10,
          ),
        );
      }
      if (!mounted) return;
      _seedSeenItems(provider);
      if (_initializing) {
        setState(() {
          _initializing = false;
          _lastRefreshTime ??= DateTime.now();
        });
      }
      return;
    }

    // Cold entry (e.g. opened from a notification): use a recent cached fix
    // immediately when possible, otherwise resolve location once. Fetch once,
    // then clear the skeleton even if the request failed.
    try {
      final lastKnown = await _locationService.getLastKnownPosition();
      final lastKnownIsFresh =
          lastKnown != null &&
          DateTime.now().difference(lastKnown.timestamp) <= _maxLastKnownAge;
      if (lastKnownIsFresh) {
        provider.setLocation(lastKnown.latitude, lastKnown.longitude);
        unawaited(provider.fetchNearby());
        unawaited(
          sosProvider.fetchNearbySos(
            lastKnown.latitude,
            lastKnown.longitude,
            radiusKm: 10,
          ),
        );
        return;
      }

      final loc = await _locationService.getCurrentLocation();
      if (!mounted) return;
      if (loc != null) {
        provider.setLocation(loc.latitude, loc.longitude);
        unawaited(
          sosProvider.fetchNearbySos(loc.latitude, loc.longitude, radiusKm: 10),
        );
      }
      await provider.fetchNearby();
    } finally {
      if (mounted) {
        _seedSeenItems(provider);
        setState(() {
          _initializing = false;
          _lastRefreshTime = DateTime.now();
        });
      }
    }
  }

  Future<void> _onRefresh() async {
    final provider = context.read<AlertProvider>();
    final sosProvider = context.read<SosProvider>();
    if (provider.isLoading || _refreshing) return;
    if (_initializing && provider.items.isEmpty) return;
    final now = DateTime.now();
    final coolingDown =
        _lastRefreshTime != null &&
        now.difference(_lastRefreshTime!) < _refreshCooldown;
    // Allow an immediate retry after a failed load, otherwise respect cooldown.
    if (coolingDown && provider.errorMessage == null) return;
    _lastRefreshTime = now;
    if (mounted) setState(() => _refreshing = true);

    try {
      if (provider.hasLocation) {
        // Use the cached/OS last-known fix instead of forcing a fresh high
        // accuracy GPS lock during a refresh. This keeps the API request fast;
        // the next cold start or Home load can refresh the GPS position.
        try {
          final lastKnown = await _locationService
              .getLastKnownPosition()
              .timeout(const Duration(seconds: 2));
          if (lastKnown != null) {
            provider.setLocation(lastKnown.latitude, lastKnown.longitude);
          }
        } catch (_) {
          // Keep the last provider coordinates if no quick fix is available.
        }
      } else {
        final loc = await _locationService.getCurrentLocation();
        if (!mounted) return;
        if (loc != null) {
          provider.setLocation(loc.latitude, loc.longitude);
        }
      }

      await provider.fetchNearby();
      final lat = provider.latitude;
      final lng = provider.longitude;
      if (lat != null && lng != null && mounted) {
        unawaited(sosProvider.fetchNearbySos(lat, lng, radiusKm: 10));
      }
      if (!mounted) return;
      // Notify user of new items
      final newItems =
          provider.items
              .where((i) => _seenItemKeys.add('${i.source}:${i.id}'))
              .toList();
      if (newItems.isNotEmpty) {
        _showNewItemAlert(newItems.first);
      }
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  void _showNewItemAlert(NearbyItem item) {
    final t = context.tr;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          '${item.severityEmoji} ${t('New')} ${item.severity.toUpperCase()} '
          '${item.isReport ? t('report') : t('alert')}: ${item.title}',
        ),
        action: SnackBarAction(label: t('View'), onPressed: () {}),
        duration: const Duration(seconds: 6),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        backgroundColor: _severityColor(item.severity),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Consumer2<AlertProvider, SosProvider>(
      builder: (context, provider, sosProvider, _) {
        final sosList = sosProvider.nearbySos;
        final filtered = provider.filteredItems;
        final hasError = provider.errorMessage != null;
        final showErrorOnly =
            hasError && provider.items.isEmpty && sosList.isEmpty;
        final isBusy = provider.isLoading || _refreshing;
        // Keep a cached snapshot (including an all-clear one with zero items)
        // visible while a background refresh runs. Only show the skeleton
        // before the first successful snapshot arrives.
        final showSkeleton =
            provider.errorMessage == null &&
            !provider.hasSnapshot &&
            provider.items.isEmpty &&
            sosList.isEmpty &&
            (provider.isLoading || _initializing);
        final activeFilter = provider.activeFilter;

        String? district;
        for (final item in provider.items) {
          final value = item.affectedDistrict?.trim();
          if (value != null && value.isNotEmpty) {
            district = value;
            break;
          }
        }

        return Scaffold(
          backgroundColor: AppTheme.backgroundColor,
          body: SafeArea(
            bottom: false,
            child: RefreshIndicator(
              color: AppTheme.primaryColor,
              backgroundColor: Colors.white,
              onRefresh: _onRefresh,
              child: CustomScrollView(
                physics: const AlwaysScrollableScrollPhysics(
                  parent: BouncingScrollPhysics(),
                ),
                slivers: [
                  SliverToBoxAdapter(
                    child: _AlertsHeader(
                      isLoading: isBusy,
                      onBack: () => Navigator.pop(context),
                      onRefresh: _onRefresh,
                    ),
                  ),
                  if (isBusy && !showSkeleton)
                    SliverToBoxAdapter(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 6, 20, 0),
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
                    const SliverToBoxAdapter(child: _AlertsLoading())
                  else if (showErrorOnly)
                    SliverToBoxAdapter(
                      child: _AlertsStatusCard(
                        icon: Icons.cloud_off_rounded,
                        color: AppTheme.severityHigh,
                        title: context.t('Could not load alerts'),
                        message: context.t(
                          'Check your connection and try again. Pull down to refresh.',
                        ),
                        actionLabel: context.t('Try again'),
                        onAction: _onRefresh,
                      ),
                    )
                  else ...[
                    SliverToBoxAdapter(
                      child: _SafetyHero(
                        totalAlerts: provider.items.length,
                        criticalCount: provider.criticalCount,
                        highCount: provider.highCount,
                        sosCount: sosList.length,
                        district: district,
                      ),
                    ),
                    if (hasError)
                      SliverToBoxAdapter(
                        child: _AlertsErrorBanner(onRetry: _onRefresh),
                      ),
                    if (sosList.isNotEmpty)
                      SliverToBoxAdapter(
                        child: _NearbySosSection(sosList: sosList),
                      ),
                    if (provider.items.isNotEmpty)
                      SliverToBoxAdapter(
                        child: _AlertFilterRail(
                          activeFilter: activeFilter,
                          counts: {
                            'all': provider.items.length,
                            'critical': provider.criticalCount,
                            'high': provider.highCount,
                            'medium': provider.mediumCount,
                            'info': provider.infoCount,
                          },
                          onSelect: (value) {
                            provider.setFilter(value);
                          },
                        ),
                      ),
                    if (provider.items.isNotEmpty)
                      SliverToBoxAdapter(
                        child: _AlertsSectionHeader(
                          icon: Icons.notifications_active_rounded,
                          color: AppTheme.primaryColor,
                          title: context.t('Live updates'),
                          count: filtered.length,
                          subtitle: context.t('Newest updates near you'),
                        ),
                      ),
                    if (provider.items.isEmpty)
                      SliverToBoxAdapter(
                        child: _AlertsStatusCard(
                          icon: Icons.verified_user_rounded,
                          color: AppTheme.successColor,
                          title: context.t('You are all caught up'),
                          message: context.t(
                            'No active alerts nearby. Pull down anytime to check for updates.',
                          ),
                          actionLabel: context.t('Check again'),
                          onAction: _onRefresh,
                        ),
                      )
                    else if (filtered.isEmpty)
                      SliverToBoxAdapter(
                        child: _AlertsStatusCard(
                          icon: Icons.filter_alt_off_rounded,
                          color: AppTheme.severityMedium,
                          title: context.t('No alerts in this filter'),
                          message: context.t(
                            'Try another severity to see what is happening nearby.',
                          ),
                          actionLabel: context.t('Show all alerts'),
                          onAction: () => provider.setFilter(null),
                        ),
                      )
                    else
                      SliverList(
                        delegate: SliverChildBuilderDelegate((context, index) {
                          final item = filtered[index];
                          return _AlertCard(
                            item: item,
                            timeAgo: _timeAgo(context, item.createdAt),
                          );
                        }, childCount: filtered.length),
                      ),
                    const SliverToBoxAdapter(child: SizedBox(height: 28)),
                  ],
                ],
              ),
            ),
          ),
        );
      },
    );
  }
}

// ============ HEADER ============

class _AlertsHeader extends StatelessWidget {
  final bool isLoading;
  final VoidCallback onBack;
  final VoidCallback onRefresh;

  const _AlertsHeader({
    required this.isLoading,
    required this.onBack,
    required this.onRefresh,
  });

  @override
  Widget build(BuildContext context) {
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
                  context.t('Live Alerts'),
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
                    const _LiveDot(),
                    const SizedBox(width: 6),
                    Flexible(
                      child: Text(
                        context.t('Real-time safety updates near you'),
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
            isLoading: isLoading,
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
  final bool isLoading;

  const _RoundIconButton({
    required this.icon,
    required this.label,
    required this.onTap,
    this.isLoading = false,
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
          onTap: isLoading ? null : onTap,
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
            child:
                isLoading
                    ? const Center(
                      child: SizedBox(
                        width: 17,
                        height: 17,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          color: AppTheme.primaryColor,
                        ),
                      ),
                    )
                    : Icon(icon, size: 19, color: AppTheme.textPrimary),
          ),
        ),
      ),
    );
  }
}

class _LiveDot extends StatelessWidget {
  const _LiveDot();

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 14,
      height: 14,
      decoration: BoxDecoration(
        color: AppTheme.successColor.withValues(alpha: 0.14),
        shape: BoxShape.circle,
      ),
      child: Center(
        child: Container(
          width: 7,
          height: 7,
          decoration: const BoxDecoration(
            color: AppTheme.successColor,
            shape: BoxShape.circle,
          ),
        ),
      ),
    );
  }
}

// ============ SAFETY HERO ============

class _SafetyHero extends StatelessWidget {
  final int totalAlerts;
  final int criticalCount;
  final int highCount;
  final int sosCount;
  final String? district;

  const _SafetyHero({
    required this.totalAlerts,
    required this.criticalCount,
    required this.highCount,
    required this.sosCount,
    this.district,
  });

  @override
  Widget build(BuildContext context) {
    final hasCritical = criticalCount > 0;
    final hasHigh = highCount > 0;
    final hasAlerts = totalAlerts > 0;

    final List<Color> gradient;
    final IconData statusIcon;
    final String title;
    final String message;

    if (hasCritical) {
      gradient = const [Color(0xFF7F1D1D), Color(0xFFB91C1C)];
      statusIcon = Icons.warning_amber_rounded;
      title = context.t('Stay alert, stay safe');
      message =
          criticalCount == 1
              ? context.t('1 critical alert needs your attention nearby')
              : '$criticalCount ${context.t('critical alerts need your attention nearby')}';
    } else if (hasHigh) {
      gradient = const [Color(0xFF7C2D12), Color(0xFFC2410C)];
      statusIcon = Icons.shield_rounded;
      title = context.t('Travel with caution');
      message =
          highCount == 1
              ? context.t('1 high-priority alert reported around you')
              : '$highCount ${context.t('high-priority alerts reported around you')}';
    } else if (hasAlerts) {
      gradient = const [Color(0xFF134E4A), Color(0xFF0F766E)];
      statusIcon = Icons.info_outline_rounded;
      title = context.t('You are up to date');
      message =
          totalAlerts == 1
              ? context.t('1 active update nearby. Stay informed.')
              : '$totalAlerts ${context.t('active updates nearby. Stay informed.')}';
    } else {
      gradient = const [Color(0xFF064E3B), Color(0xFF059669)];
      statusIcon = Icons.verified_user_rounded;
      title = context.t('All clear around you');
      message = context.t(
        'No active alerts nearby. Travel safe and check back later.',
      );
    }

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 18, 20, 0),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: gradient,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: gradient.last.withValues(alpha: 0.24),
            blurRadius: 22,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            top: -52,
            right: -36,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.06),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Positioned(
            bottom: -64,
            left: -28,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.04),
                shape: BoxShape.circle,
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 30,
                      height: 30,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(statusIcon, size: 17, color: Colors.white),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        context.t('SAFETY OVERVIEW').toUpperCase(),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1.3,
                          color: Colors.white.withValues(alpha: 0.78),
                        ),
                      ),
                    ),
                    Container(
                      constraints: const BoxConstraints(maxWidth: 120),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            Icons.location_on_rounded,
                            size: 12,
                            color: Colors.white.withValues(alpha: 0.9),
                          ),
                          const SizedBox(width: 3),
                          Flexible(
                            child: Text(
                              district ?? context.t('Your area'),
                              style: const TextStyle(
                                fontSize: 10.5,
                                fontWeight: FontWeight.w600,
                                color: Colors.white,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w800,
                    color: Colors.white,
                    height: 1.15,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  message,
                  style: TextStyle(
                    fontSize: 12.5,
                    height: 1.4,
                    color: Colors.white.withValues(alpha: 0.82),
                  ),
                ),
                const SizedBox(height: 18),
                Container(
                  height: 1,
                  color: Colors.white.withValues(alpha: 0.14),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    _HeroStat(
                      value: '$totalAlerts',
                      label: context.t('Active alerts'),
                    ),
                    _HeroDivider(),
                    _HeroStat(
                      value: '$criticalCount',
                      label: context.t('Critical'),
                      accent: const Color(0xFFFFD166),
                    ),
                    _HeroDivider(),
                    _HeroStat(
                      value: '$sosCount',
                      label: context.t('SOS nearby'),
                      accent: const Color(0xFFFFB4A2),
                    ),
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

class _HeroStat extends StatelessWidget {
  final String value;
  final String label;
  final Color? accent;

  const _HeroStat({required this.value, required this.label, this.accent});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: accent ?? Colors.white,
              height: 1,
            ),
          ),
          const SizedBox(height: 5),
          Text(
            label,
            style: TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w500,
              color: Colors.white.withValues(alpha: 0.72),
            ),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

class _HeroDivider extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 1,
      height: 30,
      color: Colors.white.withValues(alpha: 0.14),
    );
  }
}

// ============ FILTERS ============

class _AlertFilterRail extends StatelessWidget {
  final String? activeFilter;
  final Map<String, int> counts;
  final ValueChanged<String?> onSelect;

  const _AlertFilterRail({
    required this.activeFilter,
    required this.counts,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    final entries = <_AlertFilterEntry>[
      _AlertFilterEntry(
        key: null,
        label: context.t('All'),
        color: AppTheme.primaryColor,
      ),
      _AlertFilterEntry(
        key: 'critical',
        label: context.t('Critical'),
        color: AppTheme.severityCritical,
      ),
      _AlertFilterEntry(
        key: 'high',
        label: context.t('High'),
        color: AppTheme.severityHigh,
      ),
      _AlertFilterEntry(
        key: 'medium',
        label: context.t('Medium'),
        color: AppTheme.severityMedium,
      ),
      _AlertFilterEntry(
        key: 'info',
        label: context.t('Info'),
        color: AppTheme.severityInfo,
      ),
    ];

    final selected = activeFilter == 'all' ? null : activeFilter;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children:
            entries.map((entry) {
              return _AlertFilterChip(
                label: entry.label,
                count: counts[entry.key ?? 'all'] ?? 0,
                color: entry.color,
                selected: selected == entry.key,
                onTap: () => onSelect(entry.key),
              );
            }).toList(),
      ),
    );
  }
}

class _AlertFilterEntry {
  final String? key;
  final String label;
  final Color color;

  const _AlertFilterEntry({
    required this.key,
    required this.label,
    required this.color,
  });
}

class _AlertFilterChip extends StatelessWidget {
  final String label;
  final int count;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _AlertFilterChip({
    required this.label,
    required this.count,
    required this.color,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(13),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: selected ? color : Colors.white,
            borderRadius: BorderRadius.circular(13),
            border: Border.all(
              color: selected ? color : const Color(0xFFE9E3D8),
            ),
            boxShadow:
                selected
                    ? [
                      BoxShadow(
                        color: color.withValues(alpha: 0.2),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ]
                    : null,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                label,
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: selected ? Colors.white : AppTheme.textPrimary,
                ),
              ),
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color:
                      selected
                          ? Colors.white.withValues(alpha: 0.22)
                          : color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(7),
                ),
                child: Text(
                  '$count',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.w700,
                    color: selected ? Colors.white : color,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============ SOS SECTION ============

class _NearbySosSection extends StatelessWidget {
  final List<SosAlert> sosList;

  const _NearbySosSection({required this.sosList});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _AlertsSectionHeader(
          icon: Icons.emergency_share_rounded,
          color: const Color(0xFFDC2626),
          title: context.t('Nearby SOS'),
          count: sosList.length,
          subtitle: context.t('People nearby who need help'),
        ),
        Container(
          margin: const EdgeInsets.symmetric(horizontal: 20),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF5F5),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFFECACA)),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            children: [
              for (var i = 0; i < sosList.length; i++) ...[
                if (i > 0)
                  const Divider(
                    height: 1,
                    indent: 16,
                    endIndent: 16,
                    color: Color(0xFFFDE2E2),
                  ),
                _SosRow(sos: sosList[i]),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _SosRow extends StatelessWidget {
  final SosAlert sos;

  const _SosRow({required this.sos});

  @override
  Widget build(BuildContext context) {
    final type = _titleCase(sos.emergencyType.replaceAll('_', ' '));
    final name = sos.userName?.trim();
    final distance = sos.distanceKm;
    final location =
        distance != null && distance > 0
            ? '${distance.toStringAsFixed(1)} km ${context.t('away')}'
            : context.t('Nearby');

    return InkWell(
      onTap:
          () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const EmergencyScreen()),
          ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFEF4444), Color(0xFFB91C1C)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(13),
                boxShadow: [
                  BoxShadow(
                    color: const Color(0xFFDC2626).withValues(alpha: 0.24),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Icon(
                _sosIcon(sos.emergencyType),
                size: 21,
                color: Colors.white,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'SOS • ${context.t(type)}',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF991B1B),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${name == null || name.isEmpty ? context.t('Someone nearby') : name} • $location',
                    style: const TextStyle(
                      fontSize: 11,
                      color: Color(0xFFB45353),
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            const Icon(
              Icons.arrow_forward_ios_rounded,
              size: 14,
              color: Color(0xFFDC2626),
            ),
          ],
        ),
      ),
    );
  }
}

// ============ SECTION HEADER ============

class _AlertsSectionHeader extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final int count;
  final String? subtitle;

  const _AlertsSectionHeader({
    required this.icon,
    required this.color,
    required this.title,
    required this.count,
    this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 12),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, size: 20, color: color),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Flexible(
                      child: Text(
                        title,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: AppTheme.textPrimary,
                          height: 1.15,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    const SizedBox(width: 7),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 7,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        '$count',
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: color,
                        ),
                      ),
                    ),
                  ],
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 3),
                  Text(
                    subtitle!,
                    style: const TextStyle(
                      fontSize: 11,
                      color: AppTheme.textSecondary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ============ ALERT CARD ============

class _AlertCard extends StatelessWidget {
  final NearbyItem item;
  final String timeAgo;

  const _AlertCard({required this.item, required this.timeAgo});

  @override
  Widget build(BuildContext context) {
    final color = _severityColor(item.severity);

    return Container(
      margin: const EdgeInsets.fromLTRB(20, 0, 20, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.16)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.035),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: () {},
          child: Padding(
            padding: const EdgeInsets.all(15),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Center(
                        child: Text(
                          item.severityEmoji,
                          style: const TextStyle(fontSize: 22),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Text(
                                  item.title,
                                  style: const TextStyle(
                                    fontSize: 14.5,
                                    fontWeight: FontWeight.w700,
                                    color: AppTheme.textPrimary,
                                    height: 1.3,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              const SizedBox(width: 7),
                              _SeverityPill(
                                label: context.t(_titleCase(item.severity)),
                                color: color,
                              ),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text(
                            item.description,
                            style: const TextStyle(
                              fontSize: 12,
                              height: 1.4,
                              color: AppTheme.textSecondary,
                            ),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Container(
                  height: 1,
                  color: AppTheme.dividerColor.withValues(alpha: 0.65),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    if (item.affectedDistrict != null &&
                        item.affectedDistrict!.trim().isNotEmpty) ...[
                      const Icon(
                        Icons.location_on_rounded,
                        size: 13,
                        color: AppTheme.textSecondary,
                      ),
                      const SizedBox(width: 3),
                      Flexible(
                        child: Text(
                          item.affectedDistrict!,
                          style: const TextStyle(
                            fontSize: 10.5,
                            color: AppTheme.textSecondary,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 10),
                    ],
                    const Icon(
                      Icons.access_time_rounded,
                      size: 12,
                      color: AppTheme.textSecondary,
                    ),
                    const SizedBox(width: 3),
                    Text(
                      timeAgo,
                      style: const TextStyle(
                        fontSize: 10.5,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                    const Spacer(),
                    Icon(
                      item.isReport
                          ? Icons.people_alt_rounded
                          : Icons.verified_rounded,
                      size: 12,
                      color:
                          item.isReport
                              ? AppTheme.secondaryColor
                              : AppTheme.primaryColor,
                    ),
                    const SizedBox(width: 3),
                    Text(
                      item.isReport
                          ? context.t('Community report')
                          : context.t('Official alert'),
                      style: TextStyle(
                        fontSize: 9.5,
                        fontWeight: FontWeight.w600,
                        color:
                            item.isReport
                                ? AppTheme.secondaryDark
                                : AppTheme.primaryColor,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SeverityPill extends StatelessWidget {
  final String label;
  final Color color;

  const _SeverityPill({required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          fontSize: 8.5,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.5,
          color: color,
        ),
      ),
    );
  }
}

// ============ STATUS CARDS ============

class _AlertsStatusCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String message;
  final String actionLabel;
  final VoidCallback onAction;

  const _AlertsStatusCard({
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

class _AlertsErrorBanner extends StatelessWidget {
  final VoidCallback onRetry;

  const _AlertsErrorBanner({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 12, 20, 0),
      padding: const EdgeInsets.fromLTRB(12, 10, 8, 10),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFFDE68A)),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 18,
            color: Color(0xFFB45309),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              context.t(
                'Could not refresh alerts. Showing the latest saved results.',
              ),
              style: const TextStyle(
                fontSize: 11,
                height: 1.35,
                color: Color(0xFF92400E),
              ),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            style: TextButton.styleFrom(
              foregroundColor: const Color(0xFFB45309),
              padding: const EdgeInsets.symmetric(horizontal: 10),
              minimumSize: const Size(0, 32),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: Text(
              context.t('Retry'),
              style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}

// ============ LOADING ============

class _AlertsLoading extends StatelessWidget {
  const _AlertsLoading();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
      child: ShimmerSkeleton(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              height: 178,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
              ),
            ),
            const SizedBox(height: 18),
            Row(
              children: List.generate(4, (index) {
                return Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(right: index == 3 ? 0 : 8),
                    child: Container(
                      height: 34,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                  ),
                );
              }),
            ),
            const SizedBox(height: 20),
            for (var i = 0; i < 3; i++) ...[
              Container(
                height: 106,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                ),
              ),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

// ============ HELPERS ============

Color _severityColor(String severity) {
  switch (severity) {
    case 'critical':
      return AppTheme.severityCritical;
    case 'high':
      return AppTheme.severityHigh;
    case 'medium':
      return AppTheme.severityMedium;
    case 'info':
      return AppTheme.severityInfo;
    default:
      return AppTheme.textSecondary;
  }
}

IconData _sosIcon(String type) {
  switch (type.toLowerCase()) {
    case 'medical':
    case 'health':
    case 'hospital':
      return Icons.medical_services_rounded;
    case 'fire':
      return Icons.local_fire_department_rounded;
    case 'accident':
    case 'road':
      return Icons.car_crash_rounded;
    case 'crime':
    case 'police':
      return Icons.local_police_rounded;
    case 'flood':
    case 'landslide':
    case 'earthquake':
    case 'natural_disaster':
      return Icons.flood_rounded;
    default:
      return Icons.sos_rounded;
  }
}

String _titleCase(String value) {
  if (value.isEmpty) return value;
  return value
      .split(' ')
      .where((part) => part.isNotEmpty)
      .map((part) => '${part[0].toUpperCase()}${part.substring(1)}')
      .join(' ');
}

String _timeAgo(BuildContext context, DateTime date) {
  final diff = DateTime.now().difference(date);
  if (diff.inMinutes < 1) return context.t('Just now');
  if (diff.inMinutes < 60) return '${diff.inMinutes} ${context.t('min ago')}';
  if (diff.inHours < 24) {
    return '${diff.inHours} ${diff.inHours > 1 ? context.t('hours ago') : context.t('hour ago')}';
  }
  return '${diff.inDays} ${diff.inDays > 1 ? context.t('days ago') : context.t('day ago')}';
}
