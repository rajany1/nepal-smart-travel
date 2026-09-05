import 'dart:async';
import "../../core/services/localization_service.dart";
import 'dart:io';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:share_plus/share_plus.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/report_provider.dart';
import '../../core/models/report.dart';
import '../../core/models/report_comment.dart';
import '../../core/models/ad_campaign.dart';
import '../../core/services/location_service.dart';
import '../../core/services/offline_tile_provider.dart';
import '../../core/services/camera_service.dart';
import '../../core/services/auth_guard.dart';
import '../../core/services/exif_embedder_service.dart';
import '../../core/widgets/dynamic_form_field.dart';
import '../../widgets/image_carousel_widget.dart';
import '../../widgets/ad_inline_banner.dart';
import '../../widgets/report_ad_banner.dart';
import '../../core/widgets/shimmer_loading.dart';
import '../../config/constants/app_constants.dart';
import '../../core/api/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../providers/ad_provider.dart';
import '../places/utils/route_polyline_utils.dart';
import '../../widgets/ad_cards.dart';
import '../profile/user_public_profile_screen.dart';

class ReportsListScreen extends StatefulWidget {
  const ReportsListScreen({super.key});

  @override
  State<ReportsListScreen> createState() => _ReportsListScreenState();
}

class _ReportsListScreenState extends State<ReportsListScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final LocationService _locationService = LocationService();
  late final ReportProvider _reportProvider;
  double? _userLat;
  double? _userLng;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _tabController.addListener(_onTabChanged);
    _reportProvider = context.read<ReportProvider>();
    _loadInitialData();
  }

  void _onTabChanged() {
    if (mounted) setState(() {});
  }

  Future<void> _loadInitialData() async {
    final provider = context.read<ReportProvider>();
    // Load reports immediately — don't block on GPS
    unawaited(_locationService.getCurrentLocation().then((position) {
      if (position != null && mounted) {
        setState(() {
          _userLat = position.latitude;
          _userLng = position.longitude;
        });
      }
    }));
    await provider.refreshAll();
    await provider.fetchEmergencyReports(lat: _userLat, lng: _userLng, radiusKm: 20.0);
    if (mounted) provider.startAutoRefresh();
    // Preload ad campaigns for feed injection
    unawaited(context.read<AdProvider>().fetchActiveAds(feed: AdFeed.report, adContext: 'report', limit: 6));
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    // FL-28: provider captured in initState — no context.read inside dispose()
    _tabController.removeListener(_onTabChanged);
    _reportProvider.stopAutoRefresh();
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: AppTheme.backgroundColor,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      child: Scaffold(
        body: SafeArea(
        child: Column(
          children: [
            Container(
              color: AppTheme.backgroundColor,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: AppTheme.errorColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Icon(Icons.warning, color: AppTheme.errorColor, size: 22),
                      ),
                      const SizedBox(width: 12),
                      Text(context.t('Reports'),
                          style: const TextStyle(
                              fontSize: AppTheme.text2xl,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.textPrimary)),
                      const Spacer(),
                      _buildLiveBadge(),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Container(
                    height: 44,
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: AppTheme.dividerColor.withOpacity(0.35),
                      borderRadius: BorderRadius.circular(22),
                    ),
                    child: Row(
                      children: [
                        _buildTabSegment(0, Icons.history,
                            context.t('Recent'), AppTheme.primaryColor),
                        const SizedBox(width: 4),
                        _buildTabSegment(1, Icons.warning,
                            context.t('Emergency'), AppTheme.errorColor),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            _SearchFilterBar(onFilterChanged: _onFilterChanged),
            Expanded(
              child: TabBarView(
                controller: _tabController,
                children: [
                  _RecentReportsTab(
                    userLat: _userLat,
                    userLng: _userLng,
                    onStatusTap: () => _showSubmitReportSheet(context),
                  ),
                  _EmergencyReportsTab(
                    userLat: _userLat,
                    userLng: _userLng,
                    onStatusTap: () => _showSubmitReportSheet(context),
                  ),
],
              ),
            ),
          ],
        ),
      ),
      ),
    );
  }

  Timer? _searchDebounce;

  Widget _buildLiveBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: AppTheme.successColor.withOpacity(0.08),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(
              color: AppTheme.successColor,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: AppTheme.successColor.withOpacity(0.6),
                  blurRadius: 4,
                ),
              ],
            ),
          ),
          const SizedBox(width: 6),
          Text(
            context.t('Live'),
            style: const TextStyle(
              fontSize: AppTheme.textXs,
              fontWeight: FontWeight.w600,
              color: AppTheme.successColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTabSegment(
      int index, IconData icon, String label, Color activeColor) {
    final selected = _tabController.index == index;
    return Expanded(
      child: GestureDetector(
        onTap: () => _tabController.animateTo(index),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? activeColor : Colors.transparent,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon,
                  size: 16,
                  color: selected ? Colors.white : activeColor),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: AppTheme.textSm + 1,
                  fontWeight: FontWeight.w600,
                  color: selected
                      ? Colors.white
                      : AppTheme.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _onFilterChanged(String query, int? categoryId) {
    final provider = context.read<ReportProvider>();
    provider.setCategoryFilter(categoryId);
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () {
      if (!mounted) return;
      final searchTerm = query.trim().isEmpty ? null : query.trim();
      provider.fetchReports(
        search: searchTerm,
        lat: _userLat,
        lng: _userLng,
        radiusKm: 20.0,
      );
      provider.fetchEmergencyReports(
        search: searchTerm,
        lat: _userLat,
        lng: _userLng,
        radiusKm: 20.0,
        refresh: true,
      );
    });
  }

  Future<void> _showSubmitReportSheet(BuildContext context) async {
    // Guest mode: creating a report is an action → requires login.
    if (!await requireLogin(context)) return;
    if (!context.mounted) return;
    // After returning from a successful login the auth state is updated, so
    // re-check before opening the sheet.
    if (!context.read<AuthProvider>().isAuthenticated) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => const _SubmitReportSheet(),
    );
  }

}

// ============ STATUS CARD (Facebook-style) ============
class _StatusCard extends StatelessWidget {
  final VoidCallback onTap;
  const _StatusCard({required this.onTap});

  @override
  Widget build(BuildContext context) {
    final authUser = context.watch<AuthProvider>().user;
    final hasAvatar = authUser?.avatarUrl != null && authUser!.avatarUrl!.isNotEmpty;
    final userName = authUser?.name ?? '';
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
      child: Card(
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        elevation: 0,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
            ),
            child: Column(
              children: [
                Row(
                  children: [
                    CircleAvatar(
                      radius: 22,
                      backgroundColor: AppTheme.primaryLight.withOpacity(0.3),
                      backgroundImage: hasAvatar ? NetworkImage(authUser!.avatarUrl!) : null,
                      child: !hasAvatar
                          ? Text(userName.isNotEmpty ? userName[0].toUpperCase() : '?', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
                          : null,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                        decoration: BoxDecoration(
                          color: AppTheme.dividerColor.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(24),
                        ),
                        child: Text(context.t('What\'s on your mind?'), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textBase + 1)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                const Divider(height: 1),
                const SizedBox(height: 8),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _actionButton(Icons.photo_camera, context.t('Photo'), AppTheme.successColor, onTap),
                    _actionButton(Icons.location_on, context.t('Location'), AppTheme.errorColor, onTap),
                    _actionButton(Icons.priority_high, context.t('Emergency'), AppTheme.warningColor, onTap),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _actionButton(IconData icon, String label, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 18, color: color),
          const SizedBox(width: 4),
          Text(label, style: TextStyle(fontSize: AppTheme.textSm + 1, color: color.withOpacity(0.8), fontWeight: FontWeight.w500)),
        ],
      ),
    );
  }
}

// ============ SEARCH / FILTER BAR ============
class _SearchFilterBar extends StatefulWidget {
  final Function(String query, int? categoryId) onFilterChanged;
  const _SearchFilterBar({required this.onFilterChanged});

  @override
  State<_SearchFilterBar> createState() => _SearchFilterBarState();
}

class _SearchFilterBarState extends State<_SearchFilterBar> {
  final TextEditingController _searchController = TextEditingController();
  bool _showFilter = false;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      color: AppTheme.backgroundColor,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 4, 12, 0),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _searchController,
                    onChanged: (value) => widget.onFilterChanged(value, null),
                    decoration: InputDecoration(
                      hintText: context.t('Search reports...'),
                      hintStyle: const TextStyle(fontSize: AppTheme.textBase, color: AppTheme.textSecondary),
                      prefixIcon: const Icon(Icons.search, size: 20, color: AppTheme.textSecondary),
                      contentPadding: const EdgeInsets.symmetric(vertical: 8),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                      filled: true, fillColor: AppTheme.dividerColor.withOpacity(0.15),
                    ),
                    textInputAction: TextInputAction.search,
                  ),
                ),
                const SizedBox(width: 8),
                GestureDetector(
                  onTap: () => setState(() => _showFilter = !_showFilter),
                  child: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: _showFilter ? AppTheme.primaryColor.withOpacity(0.1) : AppTheme.dividerColor.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: Icon(
                      Icons.filter_list,
                      size: 20,
                      color: _showFilter ? AppTheme.primaryColor : AppTheme.textSecondary,
                    ),
                  ),
                ),
              ],
            ),
          ),
          AnimatedCrossFade(
            firstChild: const SizedBox.shrink(),
            secondChild: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
              child: Consumer<ReportProvider>(
                builder: (context, provider, child) {
                  if (provider.categories.isEmpty) return const SizedBox.shrink();
                  return SizedBox(
                    height: 40,
                    child: ListView.separated(
                      scrollDirection: Axis.horizontal,
                      itemCount: provider.categories.length + 1,
                      separatorBuilder: (_, __) => const SizedBox(width: 6),
                      itemBuilder: (context, index) {
                        if (index == 0) return _CategoryChip(label: context.t('All'), icon: Icons.all_inclusive, selected: provider.selectedCategoryId == null, onTap: () { provider.setCategoryFilter(null); widget.onFilterChanged(_searchController.text, null); });
                        final cat = provider.categories[index - 1];
                        return _CategoryChip(label: cat.name, icon: _getCategoryIcon(cat.icon), selected: provider.selectedCategoryId == cat.id, onTap: () { provider.setCategoryFilter(cat.id); widget.onFilterChanged(_searchController.text, cat.id); });
                      },
                    ),
                  );
                },
              ),
            ),
            crossFadeState: _showFilter ? CrossFadeState.showSecond : CrossFadeState.showFirst,
            duration: const Duration(milliseconds: 200),
          ),
        ],
      ),
    );
  }
}

// ============ RECENT REPORTS TAB ============
class _RecentReportsTab extends StatefulWidget {
  final double? userLat;
  final double? userLng;
  final VoidCallback onStatusTap;
  const _RecentReportsTab({this.userLat, this.userLng, required this.onStatusTap});

  @override
  State<_RecentReportsTab> createState() => _RecentReportsTabState();
}

class _RecentReportsTabState extends State<_RecentReportsTab> {
  /// Facebook-style feed: ads randomly inserted after reports (~25% chance each)
  List<dynamic> _buildFeed(List<ReportModel> reports, List<AdCampaignModel> ads) {
    final feed = <dynamic>[];
    int adIndex = 0;
    final random = math.Random();

    for (int i = 0; i < reports.length; i++) {
      feed.add(reports[i]);
      // After each report (except last), randomly insert an ad
      if (i < reports.length - 1 && adIndex < ads.length && random.nextDouble() < 0.25) {
        feed.add(ads[adIndex++]);
      }
    }

    // Guarantee at least 1 ad if we have ads and 2+ reports
    if (adIndex == 0 && ads.isNotEmpty && reports.length >= 2) {
      final pos = 1 + random.nextInt(reports.length - 1);
      feed.insert(pos, ads[adIndex++]);
    }

    return feed;
  }

  @override
  Widget build(BuildContext context) {
    final ads = context.watch<AdProvider>().reportAds;
    return Consumer<ReportProvider>(
      builder: (context, provider, child) {
        if (provider.isLoading && provider.reports.isEmpty) return const _RecentReportsShimmer();
        if (provider.errorMessage != null && provider.reports.isEmpty) {
          return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off, size: 64, color: AppTheme.textSecondary),
            const SizedBox(height: 16),
            Text(provider.errorMessage!, style: const TextStyle(color: AppTheme.textSecondary)),
            const SizedBox(height: 8),
            ElevatedButton.icon(onPressed: () => provider.fetchReports(lat: widget.userLat, lng: widget.userLng, radiusKm: 20.0), icon: const Icon(Icons.refresh), label: Text(context.t('Retry'))),
          ]));
        }
        final filtered = provider.filteredReports;
        if (filtered.isEmpty) return _emptyState(context, icon: Icons.assignment, message: context.t('No reports yet'), subtitle: context.t('Be the first to submit a report'), onTap: widget.onStatusTap);
        final feed = _buildFeed(filtered, ads);
        return RefreshIndicator(
          onRefresh: () => provider.fetchReports(lat: widget.userLat, lng: widget.userLng, radiusKm: 20.0),
          child: NotificationListener<ScrollNotification>(
            onNotification: (notification) {
              if (notification is ScrollEndNotification && notification.metrics.pixels >= notification.metrics.maxScrollExtent - 100) provider.fetchMoreReports();
              return false;
            },
            child: ListView.builder(
              padding: const EdgeInsets.all(12),
              itemCount: feed.length + 2 + (provider.isLoadingMore ? 1 : 0),
              itemBuilder: (context, index) {
                if (index == 0) return _StatusCard(onTap: widget.onStatusTap);
                if (index == 1) return Padding(padding: const EdgeInsets.only(bottom: 8, left: 4, top: 8), child: Text('${filtered.length} ${filtered.length == 1 ? context.t('report') : context.t('reports')} ${context.t('near you')}', style: const TextStyle(color: AppTheme.textSecondary)));
                if (index > feed.length + 1) return const Padding(padding: EdgeInsets.all(16), child: Center(child: CircularProgressIndicator(strokeWidth: 2)));
                final item = feed[index - 2];
                if (item is AdCampaignModel) {
                  // Find nearest report above this ad for coin crediting
                  dynamic nearestReportId;
                  for (int j = index - 3; j >= 0; j--) {
                    if (feed[j] is ReportModel) { nearestReportId = feed[j].id; break; }
                  }
                  return AdReportCard(key: ValueKey('ad-report-${item.id}'), ad: item, reportId: nearestReportId, adContext: 'report');
                }
                return _ReportCard(report: item as ReportModel, showStatusBadge: true);
              },
            ),
          ),
        );
      },
    );
  }
}

// ============ RECENT REPORTS SHIMMER ============
class _RecentReportsShimmer extends StatelessWidget {
  const _RecentReportsShimmer();

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      padding: const EdgeInsets.all(12),
      itemCount: 4,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: 4),
        child: ReportCardShimmer(),
      ),
    );
  }
}

// ============ EMERGENCY REPORTS TAB ============
class _EmergencyReportsTab extends StatefulWidget {
  final double? userLat;
  final double? userLng;
  final VoidCallback onStatusTap;
  const _EmergencyReportsTab({this.userLat, this.userLng, required this.onStatusTap});

  @override
  State<_EmergencyReportsTab> createState() => _EmergencyReportsTabState();
}

class _EmergencyReportsTabState extends State<_EmergencyReportsTab> {
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scrollController.removeListener(_onScroll);
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.extentAfter < 300) {
      context.read<ReportProvider>().fetchMoreEmergencyReports();
    }
  }

  /// Facebook-style feed: ads randomly inserted after reports (~25% chance each)
  List<dynamic> _buildFeed(List<ReportModel> reports, List<AdCampaignModel> ads) {
    final feed = <dynamic>[];
    int adIndex = 0;
    final random = math.Random();

    for (int i = 0; i < reports.length; i++) {
      feed.add(reports[i]);
      if (i < reports.length - 1 && adIndex < ads.length && random.nextDouble() < 0.25) {
        feed.add(ads[adIndex++]);
      }
    }

    if (adIndex == 0 && ads.isNotEmpty && reports.length >= 2) {
      final pos = 1 + random.nextInt(reports.length - 1);
      feed.insert(pos, ads[adIndex++]);
    }

    return feed;
  }

  @override
  Widget build(BuildContext context) {
    final ads = context.watch<AdProvider>().reportAds;
    return Consumer<ReportProvider>(
      builder: (context, provider, child) {
        if (provider.isLoading && provider.emergencyReports.isEmpty) return const _RecentReportsShimmer();
        final emergencyReports = provider.emergencyReports;
        if (emergencyReports.isEmpty && !provider.isLoading) return _emptyState(context, icon: Icons.check_circle, message: context.t('No emergencies reported'), subtitle: context.t('Everything looks safe in your area'), iconColor: AppTheme.successColor.withOpacity(0.5), iconSize: 80, messageStyle: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.w600, color: AppTheme.textPrimary), onTap: widget.onStatusTap);
        final feed = _buildFeed(emergencyReports, ads);
        return RefreshIndicator(
          onRefresh: () => provider.fetchEmergencyReports(lat: widget.userLat, lng: widget.userLng, radiusKm: 20.0, refresh: true),
          child: ListView.builder(
            controller: _scrollController,
            padding: const EdgeInsets.all(12),
            itemCount: feed.length + 2,
            itemBuilder: (context, index) {
              if (index == 0) return _StatusCard(onTap: widget.onStatusTap);
              if (index == 1) return Padding(padding: const EdgeInsets.only(bottom: 12, left: 4, top: 8), child: Row(children: [const Icon(Icons.warning_amber, color: AppTheme.errorColor, size: 20), const SizedBox(width: 8), Text('${emergencyReports.length} ${emergencyReports.length == 1 ? context.t('emergency report') : context.t('emergency reports')}', style: const TextStyle(color: AppTheme.errorColor, fontWeight: FontWeight.w600))]));
              final item = feed[index - 2];
                if (item is AdCampaignModel) {
                  dynamic nearestReportId;
                  for (int j = index - 3; j >= 0; j--) {
                    if (feed[j] is ReportModel) { nearestReportId = feed[j].id; break; }
                  }
                  return AdReportCard(key: ValueKey('ad-emergency-${item.id}'), ad: item, reportId: nearestReportId, adContext: 'report');
                }
                return _ReportCard(report: item as ReportModel, highlightEmergency: true);
            },
          ),
        );
      },
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final String label; final IconData icon; final bool selected; final VoidCallback onTap;
  const _CategoryChip({required this.label, required this.icon, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(color: selected ? AppTheme.primaryColor : AppTheme.primaryLight.withOpacity(0.1), borderRadius: BorderRadius.circular(20), border: Border.all(color: selected ? AppTheme.primaryColor : AppTheme.dividerColor)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 16, color: selected ? Colors.white : AppTheme.textSecondary),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(fontSize: AppTheme.textSm, fontWeight: selected ? FontWeight.w600 : FontWeight.normal, color: selected ? Colors.white : AppTheme.textSecondary)),
        ]),
      ),
    );
  }
}

// ============ HELPERS ============
String _formatTimeAgo(BuildContext context, DateTime dateTime) {
  final now = DateTime.now(); final diff = now.difference(dateTime);
  if (diff.inMinutes < 1) return context.t('Just now');
  if (diff.inMinutes < 60) return '${diff.inMinutes}${context.t('m ago')}';
  if (diff.inHours < 24) return '${diff.inHours}${context.t('h ago')}';
  if (diff.inDays < 7) return '${diff.inDays}${context.t('d ago')}';
  return '${dateTime.day}/${dateTime.month}/${dateTime.year}';
}

IconData _getCategoryIcon(String? icon) {
  switch (icon) { case 'road': return Icons.traffic; case 'warning': return Icons.warning_amber; case 'ac_unit': return Icons.ac_unit; case 'directions_bus': return Icons.directions_bus; case 'explore': return Icons.explore; case 'local_gas_station': return Icons.local_gas_station; case 'event': return Icons.event; case 'info': return Icons.info_outline; default: return Icons.assignment; }
}

Color _getReportCategoryColor(String? icon) {
  switch (icon) {
    case 'road': return const Color(0xFFF39C12);
    case 'warning': return AppTheme.errorColor;
    case 'ac_unit': return const Color(0xFF5C6BC0);
    case 'directions_bus': return const Color(0xFF8E44AD);
    case 'explore': return AppTheme.primaryColor;
    case 'local_gas_station': return const Color(0xFF16A085);
    case 'event': return const Color(0xFFE91E63);
    case 'info': return AppTheme.infoColor;
    default: return AppTheme.primaryColor;
  }
}

// ============ REPORT CARD ============
class _ReportCard extends StatelessWidget {
  final ReportModel report; final bool highlightEmergency; final bool showStatusBadge;
  const _ReportCard({required this.report, this.highlightEmergency = false, this.showStatusBadge = false});

  @override
  Widget build(BuildContext context) {
    final isHighPriority = report.isEmergency;
    final catColor = _getReportCategoryColor(report.categoryIcon);
    Color statusColor; IconData statusIcon;
    switch (report.status) {
      case 'approved': statusColor = AppTheme.successColor; statusIcon = Icons.check_circle; break;
      case 'rejected': statusColor = AppTheme.errorColor; statusIcon = Icons.cancel; break;
      default: statusColor = AppTheme.warningColor; statusIcon = Icons.access_time;
    }
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _showReportDetails(context, report),
        child: Container(
          decoration: highlightEmergency
              ? BoxDecoration(
                  borderRadius: BorderRadius.circular(16),
                  border: Border(
                    left: const BorderSide(color: AppTheme.errorColor, width: 4),
                    top: BorderSide(color: AppTheme.errorColor.withOpacity(0.12), width: 1),
                    right: BorderSide(color: AppTheme.errorColor.withOpacity(0.12), width: 1),
                    bottom: BorderSide(color: AppTheme.errorColor.withOpacity(0.12), width: 1),
                  ),
                  color: AppTheme.errorColor.withOpacity(0.03),
                )
              : BoxDecoration(borderRadius: BorderRadius.circular(16), border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5), width: 1)),
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            GestureDetector(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserPublicProfileScreen(userId: report.reporterId))),
              child: Row(children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: AppTheme.primaryLight.withOpacity(0.3),
                  backgroundImage: (report.reporterAvatar != null && report.reporterAvatar!.isNotEmpty) ? NetworkImage(report.reporterAvatar!) : null,
                  child: (report.reporterAvatar == null || report.reporterAvatar!.isEmpty)
                      ? Text(report.reporterName.isNotEmpty ? report.reporterName[0].toUpperCase() : '?', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(report.reporterName, style: const TextStyle(fontSize: AppTheme.textBase + 1, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  Row(children: [
                    Text(report.timeAgo.isNotEmpty ? report.timeAgo : _formatTimeAgo(context, report.createdAt), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                    const SizedBox(width: 6), Text('·', style: TextStyle(color: AppTheme.textSecondary.withOpacity(0.7))), const SizedBox(width: 6),
                    Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: catColor.withOpacity(0.12), borderRadius: BorderRadius.circular(5)), child: Text(report.categoryName, style: TextStyle(fontSize: AppTheme.textXs, color: catColor, fontWeight: FontWeight.w600))),
                  ]),
                ])),
                if (showStatusBadge) Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4), decoration: BoxDecoration(color: statusColor.withOpacity(0.12), borderRadius: BorderRadius.circular(12)), child: Text(report.status.toUpperCase(), style: TextStyle(fontSize: AppTheme.textXs, color: statusColor, fontWeight: FontWeight.w600))),
              ]),
            ),
            const SizedBox(height: 12),
            if (report.isEmergency)
              Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: AppTheme.errorColor, borderRadius: BorderRadius.circular(8)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.bolt, size: 12, color: Colors.white),
                  SizedBox(width: 3),
                  Text('EMERGENCY', style: TextStyle(fontSize: 9, color: Colors.white, fontWeight: FontWeight.w700)),
                ]),
              ),
            Text(report.description, style: const TextStyle(fontSize: AppTheme.textLg, height: 1.6, fontWeight: FontWeight.normal)),
            if (report.imageUrls.isNotEmpty) ...[
              const SizedBox(height: 12),
              ClipRRect(borderRadius: BorderRadius.circular(16), child: ImageCarouselWidget(images: report.imageUrls, height: 230)),
            ],
            const SizedBox(height: 14),
            Row(children: [
              if (report.priority.isNotEmpty) Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6), decoration: BoxDecoration(color: AppTheme.primaryLight.withOpacity(0.12), borderRadius: BorderRadius.circular(20)), child: Text(report.priority.toUpperCase(), style: TextStyle(color: report.isEmergency ? AppTheme.errorColor : AppTheme.primaryColor, fontSize: AppTheme.textXs + 1, fontWeight: FontWeight.w600))),
              if (report.district != null) ...[const SizedBox(width: 8), Expanded(child: Text(report.district!, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), overflow: TextOverflow.ellipsis))],
            ]),
            const SizedBox(height: 12), const Divider(), const SizedBox(height: 8),
            Row(children: [
              _ReactionButton(helpfulCount: report.helpfulCount, unhelpfulCount: report.unhelpfulCount, userReaction: report.userReaction, onTapHelpful: () => _toggleReaction(context, report, 'helpful'), onTapUnhelpful: () => _toggleReaction(context, report, 'unhelpful')),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: () => _showCommentsSheet(context, report),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(color: AppTheme.textSecondary.withOpacity(0.06), borderRadius: BorderRadius.circular(20)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.chat_bubble_outline, size: 18, color: AppTheme.textSecondary),
                    const SizedBox(width: 6),
                    Text('${report.commentsCount}', style: TextStyle(fontSize: AppTheme.textSm + 1, color: AppTheme.textSecondary)),
                  ]),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: () => _shareReport(context, report),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(color: AppTheme.textSecondary.withOpacity(0.06), borderRadius: BorderRadius.circular(20)),
                  child: const Icon(Icons.share, size: 18, color: AppTheme.textSecondary),
                ),
              ),
              const Spacer(),
              GestureDetector(
                onTap: () => _showOnMap(context, report),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(color: AppTheme.infoColor.withOpacity(0.08), borderRadius: BorderRadius.circular(20)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.map_outlined, size: 18, color: AppTheme.infoColor),
                    const SizedBox(width: 6),
                    Text(context.t('Map'), style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.infoColor, fontWeight: FontWeight.w600)),
                  ]),
                ),
              ),
            ]),
          ]),
        ),
      ),
    );
  }

  void _toggleReaction(BuildContext context, ReportModel report, String type) {
    final api = ApiClient.instance; final provider = context.read<ReportProvider>();
    final newUserReaction = report.userReaction == type ? null : type;
    int deltaHelpful = report.helpfulCount; int deltaUnhelpful = report.unhelpfulCount;
    if (newUserReaction == 'helpful') { deltaHelpful += 1; if (report.userReaction == 'unhelpful') deltaUnhelpful -= 1; }
    else if (newUserReaction == 'unhelpful') { deltaUnhelpful += 1; if (report.userReaction == 'helpful') deltaHelpful -= 1; }
    else { if (report.userReaction == 'helpful') deltaHelpful -= 1; if (report.userReaction == 'unhelpful') deltaUnhelpful -= 1; }
    provider.updateReportReaction(report.id, newUserReaction, deltaHelpful < 0 ? 0 : deltaHelpful, deltaUnhelpful < 0 ? 0 : deltaUnhelpful);
    api.dio.post('/reports/${report.id}/reactions', data: {'reaction_type': type}).then((response) {
      if (!context.mounted) return; final data = response.data;
      provider.updateReportReaction(report.id, data['user_reaction'] as String?, (data['helpful_count'] as int?) ?? deltaHelpful, (data['unhelpful_count'] as int?) ?? deltaUnhelpful);
    }).catchError((error) { if (context.mounted) provider.updateReportReaction(report.id, report.userReaction, report.helpfulCount, report.unhelpfulCount); });
  }

  void _showCommentsSheet(BuildContext context, ReportModel report) {
    showModalBottomSheet(context: context, isScrollControlled: true, shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))), builder: (ctx) => _CommentsSheet(report: report));
  }

  Future<void> _shareReport(BuildContext context, ReportModel report) async {
    final text = '📍 ${report.title}\n\n${report.description}\n\n🔗 View in ORIPORI App: https://nepalsmarttravel.com/reports/${report.id}\n\n📱 Download ORIPORI: https://play.google.com/store/apps/details?id=com.example.nepal_smart_travel';
    Share.share(text);
  }

  void _showOnMap(BuildContext context, ReportModel report) {
    Navigator.push(context, MaterialPageRoute(builder: (context) => _ReportMapScreen(report: report)));
  }

  void _showReportDetails(BuildContext context, ReportModel report) {
    showModalBottomSheet(context: context, isScrollControlled: true, shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))), builder: (ctx) => _ReportDetailsSheet(report: report));
  }
}

// ============ REACTION BUTTONS (Agree / Disagree) ============
class _ReactionButton extends StatelessWidget {
  final int helpfulCount; final int unhelpfulCount; final String? userReaction;
  final VoidCallback onTapHelpful; final VoidCallback onTapUnhelpful;
  const _ReactionButton({required this.helpfulCount, required this.unhelpfulCount, this.userReaction, required this.onTapHelpful, required this.onTapUnhelpful});

  @override
  Widget build(BuildContext context) {
    final isAgreed = userReaction == 'helpful';
    final isDisagreed = userReaction == 'unhelpful';
    return Row(mainAxisSize: MainAxisSize.min, children: [
      GestureDetector(
        onTap: onTapHelpful,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: isAgreed ? AppTheme.infoColor.withOpacity(0.12) : AppTheme.textSecondary.withOpacity(0.06),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: isAgreed ? AppTheme.infoColor.withOpacity(0.3) : Colors.transparent),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(isAgreed ? Icons.thumb_up : Icons.thumb_up_outlined, size: 20, color: isAgreed ? AppTheme.infoColor : AppTheme.textSecondary),
            const SizedBox(width: 6),
            Text('${helpfulCount}', style: TextStyle(fontSize: AppTheme.textSm + 1, fontWeight: isAgreed ? FontWeight.w700 : FontWeight.w500, color: isAgreed ? AppTheme.infoColor : AppTheme.textSecondary)),
          ]),
        ),
      ),
      const SizedBox(width: 10),
      GestureDetector(
        onTap: onTapUnhelpful,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: isDisagreed ? AppTheme.errorColor.withOpacity(0.12) : AppTheme.textSecondary.withOpacity(0.06),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: isDisagreed ? AppTheme.errorColor.withOpacity(0.3) : Colors.transparent),
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(isDisagreed ? Icons.thumb_down : Icons.thumb_down_outlined, size: 20, color: isDisagreed ? AppTheme.errorColor : AppTheme.textSecondary),
            const SizedBox(width: 6),
            Text('${unhelpfulCount}', style: TextStyle(fontSize: AppTheme.textSm + 1, fontWeight: isDisagreed ? FontWeight.w700 : FontWeight.w500, color: isDisagreed ? AppTheme.errorColor : AppTheme.textSecondary)),
          ]),
        ),
      ),
    ]);
  }
}

// ============ REPORT MAP SCREEN ============
class _ReportMapScreen extends StatefulWidget {
  final ReportModel report;
  const _ReportMapScreen({required this.report});
  @override State<_ReportMapScreen> createState() => _ReportMapScreenState();
}

class _ReportMapScreenState extends State<_ReportMapScreen> {
  final MapController _mapController = MapController();
  final OfflineTileProvider _offlineTiles = OfflineTileProvider();
  final LocationService _locationService = LocationService();
  double? _myLat; double? _myLng;
  List<LatLng> _routePoints = [];
  List<bool> _routeOffRoad = [];
  bool _isLoadingRoute = false;

  @override void initState() { super.initState(); _getMyLocation(); }

  Future<void> _getMyLocation() async {
    final pos = await _locationService.getCurrentLocation();
    if (pos != null && mounted) setState(() { _myLat = pos.latitude; _myLng = pos.longitude; });
  }

  Future<void> _openDirections() async {
    final report = widget.report;
    if (report.latitude == null || report.longitude == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.tr('This report has no location data'))),
        );
      }
      return;
    }
    if (_myLat == null || _myLng == null) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.tr('Could not determine your location'))),
        );
      }
      return;
    }
    setState(() => _isLoadingRoute = true);
    try {
      final response = await ApiClient.instance.getDirections(
        fromLat: _myLat!,
        fromLng: _myLng!,
        toLat: report.latitude!,
        toLng: report.longitude!,
      );
      final data = response.data['routes'] as List? ?? [];
      if (data.isNotEmpty) {
        final pts = <LatLng>[];
        final offRoad = <bool>[];
        for (final p in (data.first['points'] as List)) {
          final m = p as Map;
          pts.add(LatLng(
            ((m['lat'] as num)).toDouble(),
            ((m['lng'] as num)).toDouble(),
          ));
          offRoad.add(m['offRoad'] == true);
        }
        if (pts.length > 1) {
          setState(() {
            _routePoints = pts;
            _routeOffRoad = offRoad;
          });
          final bounds = LatLngBounds.fromPoints(pts);
          _mapController.fitCamera(
            CameraFit.bounds(bounds: bounds, padding: const EdgeInsets.all(60)),
          );
        } else if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(context.tr('No valid routes found'))),
          );
        }
      } else if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.tr('No valid routes found'))),
        );
      }
    } catch (e) {
      debugPrint('Report route error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.tr('Could not fetch route. Please try again.'))),
        );
      }
    }
    if (mounted) setState(() => _isLoadingRoute = false);
  }

  @override
  Widget build(BuildContext context) {
    final report = widget.report;
    final hasCoords = report.latitude != null && report.longitude != null;
    return Scaffold(
      appBar: AppBar(title: Text(context.t('Report Location')), actions: [IconButton(icon: _isLoadingRoute ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.directions), tooltip: context.t('Get Directions'), onPressed: _isLoadingRoute ? null : _openDirections)]),
      body: Column(children: [
        Container(width: double.infinity, padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: Colors.white, border: Border(bottom: BorderSide(color: AppTheme.dividerColor))),
          child: Row(children: [
            Container(padding: const EdgeInsets.all(8), decoration: BoxDecoration(color: report.isEmergency ? AppTheme.errorColor.withOpacity(0.1) : AppTheme.primaryLight.withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
              child: Icon(report.isEmergency ? Icons.warning : Icons.assignment, color: report.isEmergency ? AppTheme.errorColor : AppTheme.primaryColor)),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(report.title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textLg)), const SizedBox(height: 4), Text(report.description, style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary), maxLines: 2, overflow: TextOverflow.ellipsis)])),
          ]),
        ),
        if (hasCoords)
          Expanded(child: FlutterMap(mapController: _mapController, options: MapOptions(initialCenter: LatLng(report.latitude!, report.longitude!), initialZoom: 15.0, interactionOptions: const InteractionOptions(flags: InteractiveFlag.all)), children: [
            TileLayer(
              urlTemplate:
                  'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'np.com.nepalsmarttravel',
              minZoom: 6.0,
              maxZoom: 19,
              // tileProvider: _offlineTiles,
            ),
            if (_routePoints.length > 1)
              PolylineLayer(
                polylines: buildRoutePolylines(
                  _routePoints,
                  _routeOffRoad.isEmpty
                      ? List<bool>.filled(_routePoints.length, false)
                      : _routeOffRoad,
                  color: const Color(0xFF4285F4).withOpacity(0.85),
                  strokeWidth: 5,
                ),
              ),
            MarkerLayer(markers: [
              Marker(point: LatLng(report.latitude!, report.longitude!), child: Column(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.location_on, size: 40, color: AppTheme.errorColor), Text(context.t('Report'), style: const TextStyle(fontSize: AppTheme.textXs, fontWeight: FontWeight.bold))])),
              if (_myLat != null && _myLng != null) Marker(point: LatLng(_myLat!, _myLng!), child: Column(mainAxisSize: MainAxisSize.min, children: [Container(padding: const EdgeInsets.all(6), decoration: const BoxDecoration(color: AppTheme.infoColor, shape: BoxShape.circle), child: const Icon(Icons.person, size: 16, color: Colors.white))])),
            ]),
          ]))
        else
          Expanded(child: Center(child: Text(context.t('No location data for this report'), style: const TextStyle(color: AppTheme.textSecondary)))),
        Container(padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: AppTheme.dividerColor))),
          child: SafeArea(top: false, child: Row(children: [
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(context.t('Location'), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase)), const SizedBox(height: 4), Text(hasCoords ? '${report.latitude!.toStringAsFixed(6)}, ${report.longitude!.toStringAsFixed(6)}' : context.t('Unknown'), style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)), if (report.district != null) Text(report.district!, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary))])),
            ElevatedButton.icon(onPressed: _isLoadingRoute ? null : _openDirections, icon: _isLoadingRoute ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.directions, size: 18), label: Text(context.t('Directions')), style: ElevatedButton.styleFrom(backgroundColor: AppTheme.primaryColor, foregroundColor: Colors.white)),
          ])),
        ),
      ]),
    );
  }
}

// ============ REPORT DETAILS SHEET ============
class _ReportDetailsSheet extends StatelessWidget {
  final ReportModel report;
  const _ReportDetailsSheet({required this.report});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.6, minChildSize: 0.4, maxChildSize: 0.85, expand: false,
      builder: (context, scrollController) {
        return Padding(
          padding: const EdgeInsets.all(20),
          child: ListView(controller: scrollController, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppTheme.textSecondary.withOpacity(0.3), borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              CircleAvatar(backgroundColor: report.isEmergency ? AppTheme.errorColor.withOpacity(0.1) : AppTheme.primaryLight.withOpacity(0.1), child: Icon(report.isEmergency ? Icons.warning : Icons.assignment, color: report.isEmergency ? AppTheme.errorColor : AppTheme.primaryColor)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(report.title, style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Row(children: [Icon(Icons.person, size: 14, color: AppTheme.textSecondary), const SizedBox(width: 4), Text(report.reporterName, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)), const SizedBox(width: 12), Icon(Icons.access_time, size: 14, color: AppTheme.textSecondary), const SizedBox(width: 4),                 Text(report.timeAgo.isNotEmpty ? report.timeAgo : _formatTimeAgo(context, report.createdAt), style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary))]),
              ])),
            ]),
            const SizedBox(height: 16), const Divider(), const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 4, children: [
              _Badge(label: report.status.toUpperCase(), color: report.isApproved ? AppTheme.successColor : report.isPending ? AppTheme.warningColor : AppTheme.errorColor, icon: report.isApproved ? Icons.check_circle : report.isPending ? Icons.access_time : Icons.cancel),
              _Badge(label: report.categoryName, color: AppTheme.primaryColor, icon: Icons.category),
              _Badge(label: report.priority.toUpperCase(), color: report.isEmergency ? AppTheme.errorColor : AppTheme.warningColor, icon: report.isEmergency ? Icons.warning : Icons.flag),
            ]),
            const SizedBox(height: 16),
            if (report.imageUrls.isNotEmpty) ...[ClipRRect(borderRadius: BorderRadius.circular(16), child: ImageCarouselWidget(images: report.imageUrls, height: 240)), const SizedBox(height: 16)],
            GestureDetector(onTap: () { Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (context) => _ReportMapScreen(report: report))); }, child: Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AppTheme.infoColor.withOpacity(0.06), borderRadius: BorderRadius.circular(12), border: Border.all(color: AppTheme.infoColor.withOpacity(0.2))), child: Row(children: [const Icon(Icons.location_on, size: 18, color: AppTheme.infoColor), const SizedBox(width: 8), Expanded(child: Text(report.district ?? context.t('Unknown location'), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase), maxLines: 1, overflow: TextOverflow.ellipsis)), const SizedBox(width: 8), Text(context.t('View on map'), style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.infoColor, fontWeight: FontWeight.w600)), const Icon(Icons.chevron_right, size: 18, color: AppTheme.infoColor)]))),
            const SizedBox(height: 12),
            Text(report.description, style: const TextStyle(fontSize: AppTheme.textBase + 1, height: 1.5, fontWeight: FontWeight.normal)),
            const SizedBox(height: 16),
            ReportAdBanner(reportId: report.id, district: report.district),
            const SizedBox(height: 20),
            Row(children: [
              Icon(Icons.thumb_up, size: 16, color: AppTheme.textSecondary),
              const SizedBox(width: 4),
              Text('${report.helpfulCount}', style: const TextStyle(fontSize: AppTheme.textSm + 1, color: AppTheme.textSecondary)),
              const SizedBox(width: 16),
              Icon(Icons.thumb_down, size: 16, color: AppTheme.textSecondary),
              const SizedBox(width: 4),
              Text('${report.unhelpfulCount}', style: const TextStyle(fontSize: AppTheme.textSm + 1, color: AppTheme.textSecondary)),
              const SizedBox(width: 24),
              GestureDetector(onTap: () { Navigator.pop(context); _showCommentsSheet(context, report); }, child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.chat_bubble_outline, size: 16, color: AppTheme.textSecondary), const SizedBox(width: 4), Text('${report.commentsCount}', style: const TextStyle(fontSize: AppTheme.textSm + 1, color: AppTheme.textSecondary))])),
              const Spacer(),
              GestureDetector(onTap: () => _shareReport(context, report), child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: AppTheme.primaryLight.withOpacity(0.1), borderRadius: BorderRadius.circular(8)), child: const Icon(Icons.share, size: 16, color: AppTheme.primaryColor))),
            ]),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(
                child: GestureDetector(
                  onTap: () => _toggleHelpful(context, report),
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppTheme.infoColor.withOpacity(0.3)),
                      color: AppTheme.infoColor.withOpacity(0.06),
                    ),
                    child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.thumb_up, size: 22, color: AppTheme.infoColor),
                    ]),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: GestureDetector(
                  onTap: () => _toggleUnhelpful(context, report),
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppTheme.errorColor.withOpacity(0.3)),
                      color: AppTheme.errorColor.withOpacity(0.06),
                    ),
                    child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.thumb_down, size: 22, color: AppTheme.errorColor),
                    ]),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: GestureDetector(
                  onTap: () => _shareReport(context, report),
                  child: Container(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: AppTheme.primaryColor.withOpacity(0.3)),
                      color: AppTheme.primaryColor.withOpacity(0.06),
                    ),
                    child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.share, size: 22, color: AppTheme.primaryColor),
                    ]),
                  ),
                ),
              ),
            ]),
          ]),
        );
      },
    );
  }

  void _toggleHelpful(BuildContext context, ReportModel report) {
    final helpfulMsg = context.tr('Marked as agree');
    ApiClient.instance.dio.post('/reports/${report.id}/reactions', data: {'reaction_type': 'helpful'}).then((_) { if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(helpfulMsg), duration: const Duration(seconds: 1))); }).catchError((_) {});
  }

  void _toggleUnhelpful(BuildContext context, ReportModel report) {
    final msg = context.tr('Marked as disagree');
    ApiClient.instance.dio.post('/reports/${report.id}/reactions', data: {'reaction_type': 'unhelpful'}).then((_) { if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), duration: const Duration(seconds: 1))); }).catchError((_) {});
  }

  void _showCommentsSheet(BuildContext context, ReportModel report) {
    showModalBottomSheet(context: context, isScrollControlled: true, shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))), builder: (ctx) => _CommentsSheet(report: report));
  }

  Future<void> _shareReport(BuildContext context, ReportModel report) async {
    final text = '📍 ${report.title}\n\n${report.description}\n\n🔗 View in ORIPORI App: https://nepalsmarttravel.com/reports/${report.id}\n\n📱 Download ORIPORI: https://play.google.com/store/apps/details?id=com.example.nepal_smart_travel';
    Share.share(text);
  }
}

// ============ COMMENTS SHEET (with Reply feature) ============
class _CommentsSheet extends StatefulWidget {
  final ReportModel report;
  const _CommentsSheet({required this.report});
  @override State<_CommentsSheet> createState() => _CommentsSheetState();
}

class _CommentsSheetState extends State<_CommentsSheet> {
  final TextEditingController _commentController = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final FocusNode _commentFocusNode = FocusNode();
  List<ReportComment> _comments = [];
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _replyingToCommentId;
  String? _replyingToUserName;

  @override
  void initState() { super.initState(); _loadComments(); }

  @override
  void dispose() { _commentController.dispose(); _scrollController.dispose(); _commentFocusNode.dispose(); super.dispose(); }

  Future<void> _loadComments() async {
    try {
      final api = ApiClient.instance;
      final response = await api.dio.get('/reports/${widget.report.id}');
      final data = response.data['data'] as Map<String, dynamic>? ?? {};
      final commentsData = data['comments'] as List? ?? [];
      setState(() {
        _comments = commentsData.map((c) => ReportComment.fromJson(c is Map ? Map<String, dynamic>.from(c) : {})).toList();
        _isLoading = false;
      });
    } catch (e) { setState(() => _isLoading = false); }
  }

  void _startReply(String commentId, String userName) {
    setState(() {
      _replyingToCommentId = commentId;
      _replyingToUserName = userName;
    });
    _commentController.text = '@$userName ';
    _commentController.selection = TextSelection.fromPosition(TextPosition(offset: _commentController.text.length));
    _commentFocusNode.requestFocus();
  }

  void _cancelReply() {
    setState(() {
      _replyingToCommentId = null;
      _replyingToUserName = null;
    });
    _commentController.clear();
  }

  String _stripReplyPrefix(String content) {
    final name = _replyingToUserName;
    if (name == null || name.isEmpty) return content;
    return content
        .replaceFirst(RegExp('^@${RegExp.escape(name)}\\s*'), '')
        .trim();
  }

  Future<void> _submitComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty) return;
    // Guest mode: posting a comment is an action → requires login.
    if (!await requireLogin(context)) return;
    if (!mounted) return;
    final failMsg = context.tr('Failed to post comment. Please try again.');
    setState(() => _isSubmitting = true);
    try {
      final api = ApiClient.instance;
      await api.addReportComment(
        widget.report.id,
        _replyingToCommentId != null ? _stripReplyPrefix(content) : content,
        parentCommentId: _replyingToCommentId,
      );
      _commentController.clear();
      _cancelReply();
      await _loadComments();
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollController.hasClients) _scrollController.animateTo(_scrollController.position.maxScrollExtent, duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
      });
      if (context.mounted) context.read<ReportProvider>().fetchReports(refresh: false, lat: context.read<ReportProvider>().lastFetchLat, lng: context.read<ReportProvider>().lastFetchLng, radiusKm: 20.0);
    } catch (e) {
      final is429 = e is DioException && e.response?.statusCode == 429;
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              is429 ? context.tr('Too many comments. Please try again later.') : failMsg,
            ),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
    }
    if (mounted) setState(() => _isSubmitting = false);
  }

  // Build a single comment with its replies
  Widget _buildCommentItem(ReportComment comment, {int depth = 0}) {
    return Padding(
      padding: EdgeInsets.only(left: depth * 16.0),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            CircleAvatar(radius: 14, backgroundColor: AppTheme.primaryLight.withOpacity(0.3), child: Text(comment.userName.isNotEmpty ? comment.userName[0].toUpperCase() : '?', style: const TextStyle(fontSize: AppTheme.textXs + 1, fontWeight: FontWeight.bold))),
            const SizedBox(width: 8),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                if (comment.isAuthor) Container(margin: const EdgeInsets.only(right: 4), padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1), decoration: BoxDecoration(color: AppTheme.primaryColor.withOpacity(0.1), borderRadius: BorderRadius.circular(4)), child: Text(context.t('Author'), style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.primaryColor, fontWeight: FontWeight.w700))),
                Text(comment.userName, style: const TextStyle(fontSize: AppTheme.textSm + 1, fontWeight: FontWeight.w600)),
                const SizedBox(width: 6),
                Text(comment.timeAgo, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
              ]),
              const SizedBox(height: 2),
              if (comment.replyToName != null)
                Text('@${comment.replyToName}', style: TextStyle(fontSize: AppTheme.textXs + 1, color: AppTheme.primaryColor.withOpacity(0.7), fontWeight: FontWeight.w500)),
              Text(comment.content, style: const TextStyle(fontSize: AppTheme.textSm + 1, fontWeight: FontWeight.normal)),
              const SizedBox(height: 2),
              GestureDetector(
                onTap: () => _startReply(comment.id, comment.userName),
                child: Text(context.t('Reply'), style: TextStyle(fontSize: AppTheme.textXs + 1, color: AppTheme.primaryColor.withOpacity(0.7), fontWeight: FontWeight.w600)),
              ),
            ])),
          ]),
        ),
        // Nested replies
        if (comment.hasReplies)
          ...comment.replies.map((reply) => _buildCommentItem(reply, depth: depth + 1)),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.only(bottom: bottomInset),
      child: DraggableScrollableSheet(
        initialChildSize: 0.75, minChildSize: 0.5, maxChildSize: 0.9, expand: false,
        builder: (context, scrollController) {
          return Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
            child: Column(children: [
              Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: AppTheme.textSecondary.withOpacity(0.3), borderRadius: BorderRadius.circular(2)))),
              const SizedBox(height: 12),
              Row(children: [
                Text('${context.t('Comments')} (${_comments.length})', style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w600, color: AppTheme.textSecondary)),
                const Spacer(),
                GestureDetector(onTap: () => Navigator.pop(context), child: const Icon(Icons.close, size: 20, color: AppTheme.textSecondary)),
              ]),
              const SizedBox(height: 10),
              // Reply indicator
              if (_replyingToCommentId != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(color: AppTheme.primaryColor.withOpacity(0.05), borderRadius: BorderRadius.circular(8)),
                  child: Row(children: [
                    Icon(Icons.reply, size: 14, color: AppTheme.primaryColor),
                    const SizedBox(width: 4),
                    Text('${context.t('Replying to')} $_replyingToUserName', style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.primaryColor)),
                    const Spacer(),
                    GestureDetector(onTap: _cancelReply, child: const Icon(Icons.close, size: 16, color: AppTheme.textSecondary)),
                  ]),
                ),
              // Input field at top
              Row(children: [
                Expanded(
                  child: TextField(
                    controller: _commentController,
                    focusNode: _commentFocusNode,
                    decoration: InputDecoration(
                      hintText: _replyingToCommentId != null ? context.t('Write a reply...') : context.t('Write a comment...'),
                      hintStyle: const TextStyle(fontSize: AppTheme.textBase),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide(color: AppTheme.dividerColor)),
                      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide(color: AppTheme.dividerColor)),
                      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: const BorderSide(color: AppTheme.primaryColor)),
                      filled: true, fillColor: AppTheme.dividerColor.withOpacity(0.1),
                    ),
                    maxLines: 2, minLines: 1,
                    textInputAction: TextInputAction.send,
                    onSubmitted: (_) => _submitComment(),
                  ),
                ),
                const SizedBox(width: 8),
                Container(decoration: BoxDecoration(color: AppTheme.primaryColor, shape: BoxShape.circle),
                  child: IconButton(
                    onPressed: _isSubmitting ? null : _submitComment,
                    icon: _isSubmitting
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Icon(Icons.send, color: Colors.white, size: 20),
                  ),
                ),
              ]),
              const SizedBox(height: 8),
              const Divider(height: 1),
              const SizedBox(height: 4),
              // Comments list
              Expanded(
                child: _isLoading
                    ? const Center(child: CircularProgressIndicator())
                    : _comments.isEmpty
                        ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.chat_bubble_outline, size: 48, color: AppTheme.textSecondary.withOpacity(0.3)), const SizedBox(height: 8), Text(context.t('No comments yet'), style: const TextStyle(color: AppTheme.textSecondary)), const SizedBox(height: 4), Text(context.t('Be the first to comment!'), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm))]))
                        : ListView.builder(
                            controller: _scrollController,
                            itemCount: _comments.length,
                            itemBuilder: (ctx, i) => _buildCommentItem(_comments[i]),
                          ),
              ),
            ]),
          );
        },
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  final String label; final Color color; final IconData? icon;
  const _Badge({required this.label, required this.color, this.icon});

  @override
  Widget build(BuildContext context) {
    return Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(8)), child: Row(mainAxisSize: MainAxisSize.min, children: [
      if (icon != null) ...[Icon(icon, size: 12, color: color), const SizedBox(width: 4)],
      Text(label, style: TextStyle(fontSize: AppTheme.textXs + 1, color: color, fontWeight: FontWeight.w600)),
    ]));
  }
}

// ============ SUBMIT REPORT SHEET ============
class _SubmitReportSheet extends StatefulWidget {
  const _SubmitReportSheet();
  @override State<_SubmitReportSheet> createState() => _SubmitReportSheetState();
}

class _SubmitReportSheetState extends State<_SubmitReportSheet> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, dynamic> _formValues = {};
  bool _isSubmitting = false;
  bool _isLoadingLocation = true;
  double? _lat; double? _lng; String? _district;
  bool _configReady = false;
  final CameraService _cameraService = CameraService();
  final CaptureLocationService _captureLocationService = CaptureLocationService();
  XFile? _capturedPhoto;
  bool _isCapturingPhoto = false;

  @override void initState() { super.initState(); _initialize(); }

  Future<void> _initialize() async {
    final provider = context.read<ReportProvider>();
    if (provider.formConfig == null) await provider.fetchFormConfig();
    if (provider.categories.isEmpty) await provider.fetchCategories();
    final loc = LocationService(); final pos = await loc.getCurrentLocation();
    if (pos != null && mounted) {
      setState(() { _lat = pos.latitude; _lng = pos.longitude; _isLoadingLocation = false; });
      final address = await loc.getAddressFromCoordinates(pos.latitude, pos.longitude);
      if (address != null && mounted) { final parts = address.split(','); if (parts.length >= 2) setState(() => _district = parts[1].trim()); }
    } else if (mounted) setState(() { _lat = null; _lng = null; _isLoadingLocation = false; });
    if (mounted) setState(() => _configReady = true);
  }

  @override void dispose() { super.dispose(); }

  Future<void> _capturePhoto() async {
    final tooLargeMsg = context.tr('Photo is too large. Max 5MB.');
    final captureFailMsg = context.tr('Failed to capture photo.');
    setState(() => _isCapturingPhoto = true);
    try {
      // Open the camera immediately — never block on a slow high-accuracy GPS
      // fix. Pre-capture GPS was blocking the button up to ~25s.
      final photo = await _cameraService.capturePhoto(
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 88,
      );
      if (photo != null && mounted) {
        if (await CameraService.isWithinSizeLimit(photo)) {
          setState(() => _capturedPhoto = photo);
          // Capture capture-time GPS in the background (no UI blocking).
          unawaited(_refreshCaptureLocation());
        } else {
          await CameraService.cleanUp(photo);
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(tooLargeMsg), backgroundColor: AppTheme.errorColor));
        }
      }
    } catch (e) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(captureFailMsg), backgroundColor: AppTheme.errorColor)); }
    if (mounted) setState(() => _isCapturingPhoto = false);
  }

  /// Grab capture-time GPS coords off the UI thread, and upgrade the report
  /// pin with them when they arrive. Never blocks the photo/submit flow.
  Future<void> _refreshCaptureLocation() async {
    await _captureLocationService.captureLocationAfterPhoto();
    if (!mounted || !_captureLocationService.hasCaptureLocation) return;
    setState(() {
      _lat = _captureLocationService.captureLatitude;
      _lng = _captureLocationService.captureLongitude;
    });
  }

  Future<void> _retakePhoto() async { if (_capturedPhoto != null) await CameraService.cleanUp(_capturedPhoto!); setState(() => _capturedPhoto = null); await _capturePhoto(); }

  void _showBioPrompt(BuildContext context, AuthProvider auth) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) {
        final bioController = TextEditingController();
        return Padding(
          padding: EdgeInsets.fromLTRB(24, 24, 24, MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            Text(context.t('Add a Bio'), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
            const SizedBox(height: 4),
            Text(context.t('Tell others about yourself. This shows on your profile.'), style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
            const SizedBox(height: 16),
            TextField(
              controller: bioController,
              maxLines: 3,
              maxLength: 200,
              decoration: InputDecoration(hintText: context.t('e.g. Traveler, foodie, Kathmandu local...'), border: OutlineInputBorder(borderRadius: BorderRadius.circular(12))),
            ),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(child: OutlinedButton(onPressed: () => Navigator.pop(ctx), child: Text(context.t('Later')))),
              const SizedBox(width: 12),
              Expanded(child: ElevatedButton(
                onPressed: () async {
                  final bio = bioController.text.trim();
                  if (bio.isEmpty) return;
                  await auth.updateProfile({'bio': bio});
                  if (ctx.mounted) Navigator.pop(ctx);
                },
                child: Text(context.t('Save')),
              )),
            ]),
          ]),
        );
      },
    );
  }

  Future<void> _submitReport() async {
    if (!_formKey.currentState!.validate()) return;
    final provider = context.read<ReportProvider>();
    final captureMsg = context.tr('Please capture a live photo.');
    final fileMissingMsg = context.tr('Photo file missing.');
    final locUnavailableMsg = context.tr('Location unavailable. Please enable GPS and try again.');
    final successMsg = context.tr('Report submitted!');
    if (_capturedPhoto == null) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(captureMsg), backgroundColor: AppTheme.errorColor)); return; }
    if (!await File(_capturedPhoto!.path).exists()) { ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(fileMissingMsg), backgroundColor: AppTheme.errorColor)); return; }
    setState(() => _isSubmitting = true);
    // Reuse the pin already captured during photo/screen init — do NOT block
    // submit on another high-accuracy GPS fix (was a 10-25s spinner).
    if (_lat == null || _lng == null) {
      if (mounted) {
        setState(() => _isSubmitting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(locUnavailableMsg),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
      return;
    }
    final success = await provider.submitReport(
      description: _formValues['description']?.toString() ?? '',
      latitude: _lat!,
      longitude: _lng!,
      district: _district,
      photoPath: _capturedPhoto?.path,
      captureLatitude: _captureLocationService.captureLatitude,
      captureLongitude: _captureLocationService.captureLongitude,
    );
    _captureLocationService.clear();
    if (mounted) {
      setState(() => _isSubmitting = false);
      if (success) {
        if (_capturedPhoto != null) CameraService.cleanUp(_capturedPhoto!);
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(successMsg), backgroundColor: AppTheme.successColor));
        provider.fetchReports(lat: _lat, lng: _lng, radiusKm: 20.0);
        provider.fetchMyReports();

        final auth = context.read<AuthProvider>();
        if (auth.user?.bio == null || auth.user!.bio!.trim().isEmpty) {
          _showBioPrompt(context, auth);
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<ReportProvider>(); final formConfig = provider.formConfig;
    return Padding(padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom, left: 20, right: 20, top: 20),
      child: Form(key: _formKey, child: SingleChildScrollView(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, mainAxisSize: MainAxisSize.min, children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(formConfig != null ? '${formConfig.submitButtonText}' : context.t('Submit Report'), style: const TextStyle(fontSize: AppTheme.text2xl, fontWeight: FontWeight.bold)), IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close))]),
        const SizedBox(height: 16),
        if (formConfig == null || !_configReady) Padding(padding: const EdgeInsets.symmetric(vertical: 24), child: Center(child: Column(children: [const CircularProgressIndicator(strokeWidth: 2), const SizedBox(height: 12), Text(context.t('Loading...'), style: const TextStyle(color: AppTheme.textSecondary))])))
        else ...[
          ...formConfig.fields.map((field) => Padding(padding: const EdgeInsets.only(bottom: 12), child: DynamicFormField(config: field, currentValue: _formValues[field.name], categories: provider.categories, onChanged: (v) => setState(() => _formValues[field.name] = v)))),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: (_lat != null ? AppTheme.successColor : AppTheme.errorColor).withOpacity(0.08),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(children: [
              if (_isLoadingLocation)
                const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
              else
                Icon(_lat != null ? Icons.gps_fixed : Icons.gps_off, size: 18, color: _lat != null ? AppTheme.successColor : AppTheme.errorColor),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  _isLoadingLocation
                      ? context.t('Getting your location...')
                      : _district != null
                          ? _district!
                          : _lat != null
                              ? context.t('Location detected')
                              : context.t('GPS needed for photo & report'),
                  style: TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w600, color: _lat != null ? AppTheme.successColor : AppTheme.errorColor),
                ),
              ),
            ]),
          ),
          if (_capturedPhoto != null) ...[ClipRRect(borderRadius: BorderRadius.circular(8), child: Stack(children: [Image.file(File(_capturedPhoto!.path), height: 160, width: double.infinity, fit: BoxFit.cover), Positioned(top: 8, right: 8, child: GestureDetector(onTap: _retakePhoto, child: Container(padding: const EdgeInsets.all(6), decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(20)), child: const Icon(Icons.refresh, color: Colors.white, size: 18))))])), const SizedBox(height: 8)]
          else
            GestureDetector(
              onTap: _isCapturingPhoto ? null : _capturePhoto,
              child: Container(
                height: 110,
                decoration: BoxDecoration(
                  color: AppTheme.primaryLight.withOpacity(0.06),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppTheme.primaryColor.withOpacity(0.4), width: 1.5),
                ),
                child: _isCapturingPhoto
                    ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
                    : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                        const Icon(Icons.photo_camera_outlined, size: 30, color: AppTheme.primaryColor),
                        const SizedBox(height: 6),
                        Text(context.t('Tap to capture live photo'), style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                      ]),
              ),
            ),
          const SizedBox(height: 8),
          SizedBox(
            height: 50,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submitReport,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primaryColor,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: _isSubmitting
                  ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Text(formConfig?.submitButtonText ?? context.t('Submit'), style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            ),
          ),
        ],
      ]))),
    );
  }
}

// ============ EMPTY STATE ============
Widget _emptyState(BuildContext context, {required IconData icon, required String message, String? subtitle, Color? iconColor, double iconSize = 64, TextStyle? messageStyle, VoidCallback? onTap}) {
  return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: iconSize, color: iconColor ?? AppTheme.textSecondary.withOpacity(0.3)),
    const SizedBox(height: 16),
    Text(message, style: messageStyle ?? const TextStyle(color: AppTheme.textSecondary)),
    if (subtitle != null) ...[const SizedBox(height: 8), Text(subtitle, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm + 1))],
    if (onTap != null) ...[
      const SizedBox(height: 24),
      ElevatedButton.icon(
        onPressed: onTap,
        icon: const Icon(Icons.add),
        label: Text(context.t('Create Report')),
        style: ElevatedButton.styleFrom(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        ),
      ),
    ],
  ]));
}
