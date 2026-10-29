import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:provider/provider.dart';

import "../../core/services/localization_service.dart";
import '../../config/themes/app_theme.dart';
import '../../core/models/offer_model.dart';
import '../../core/models/route_model.dart';
import '../../core/models/user.dart';
import '../../core/services/location_service.dart';
import '../../core/widgets/shimmer_loading.dart';
import '../../providers/alert_provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/offer_provider.dart';
import '../../providers/place_provider.dart';
import '../../providers/report_provider.dart';
import '../../providers/route_provider.dart';
import '../../providers/sos_provider.dart';
import '../../widgets/ad_banner_carousel.dart';
import '../../widgets/ad_inline_banner.dart';
import '../../widgets/explore_place_card.dart';
import '../alerts/alerts_screen.dart';
import '../assistant/assistant_screen.dart';
import '../emergency/emergency_screen.dart';
import '../leaderboard/leaderboard_screen.dart';
import '../offers/offer_detail_screen.dart';
import '../offers/offers_screen.dart';
import '../places/explore_search_screen.dart';
import '../places/nearby_map_screen.dart';
import '../places/place_details_screen.dart';
import '../places/utils/category_utils.dart';
import '../profile/profile_screen.dart';
import '../reporting/reports_list_screen.dart';
import '../routes/route_detail_screen.dart';
import '../routes/routes_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  final LocationService _locationService = LocationService();

  @override
  void initState() {
    super.initState();
    _initLocation();
    _loadData();
  }

  Future<void> _initLocation() async {
    await _locationService.getCurrentLocation();
    if (mounted) setState(() {});
  }

  Future<void> _loadData() async {
    // Capture providers before awaiting location so a late callback can never
    // look up an ancestor on a deactivated element.
    final alerts = context.read<AlertProvider>();
    final places = context.read<PlaceProvider>();

    final loc = await _locationService.getCurrentLocation();
    if (!mounted) return;

    if (loc != null) {
      alerts.setLocation(loc.latitude, loc.longitude);
      alerts.fetchNearby();
      places.fetchNearbyPlaces(lat: loc.latitude, lng: loc.longitude);
      places.fetchFeaturedPlaces(lat: loc.latitude, lng: loc.longitude);
    } else {
      alerts.fetchNearby();
      // No GPS (web / permission denied): featured picks still give the home
      // screen something real to show, without pulling the 15k dataset.
      places.fetchFeaturedPlaces();
    }
  }

  void _onTabChanged(int index) {
    // FL-29: hidden IndexedStack tabs keep polling — pause the reports
    // auto-refresh while its tab is not visible
    final provider = context.read<ReportProvider>();
    if (_currentIndex == 2) provider.stopAutoRefresh();
    if (index == 2) {
      provider.startAutoRefresh();
      provider.fetchReports(
        lat: provider.lastFetchLat,
        lng: provider.lastFetchLng,
        radiusKm: 20.0,
      );
      provider.fetchEmergencyReports(
        lat: provider.lastFetchLat,
        lng: provider.lastFetchLng,
        radiusKm: 20.0,
        refresh: true,
      );
    }
    setState(() => _currentIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    final availableTabs = <Map<String, dynamic>>[
      {
        'icon': Icons.explore,
        'label': context.t('Explore'),
        'screen': const _ExploreTab(),
      },
      {
        'icon': Icons.place,
        'label': context.t('Nearby'),
        'screen': const NearbyMapScreen(),
      },
      {
        'icon': Icons.assignment,
        'label': context.t('Reports'),
        'screen': const ReportsListScreen(),
      },
      {
        'icon': Icons.emergency,
        'label': context.t('Emergency'),
        'screen': const EmergencyScreen(),
      },
      {
        'icon': Icons.person,
        'label': context.t('Profile'),
        'screen': const ProfileScreen(),
      },
    ];

    if (_currentIndex >= availableTabs.length) {
      _currentIndex = 0;
    }

    final compactNav = MediaQuery.sizeOf(context).width < 360;

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: [
          for (var i = 0; i < availableTabs.length; i++)
            TickerMode(
              // FL-30: hidden IndexedStack tabs stay mounted, so their
              // animation tickers (blue-dot pulse, SOS pulse, spinners) keep
              // scheduling frames for pixels nobody can see. Muting them
              // stops the build/layout/GPU work without unmounting anything.
              enabled: _currentIndex == i,
              child: availableTabs[i]['screen'] as Widget,
            ),
        ],
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: _onTabChanged,
        selectedFontSize: compactNav ? 10.5 : 14,
        unselectedFontSize: compactNav ? 10.5 : 12,
        items:
            availableTabs
                .map(
                  (tab) => BottomNavigationBarItem(
                    icon: Icon(tab['icon'] as IconData),
                    label: tab['label'] as String,
                  ),
                )
                .toList(),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Explore tab
// ─────────────────────────────────────────────────────────────────────────────

class _ExploreTab extends StatefulWidget {
  const _ExploreTab();

  @override
  State<_ExploreTab> createState() => _ExploreTabState();
}

class _ExploreTabState extends State<_ExploreTab> {
  final LocationService _locationService = LocationService();

  static const List<String> _categories = [
    'Restaurants',
    'Hotels',
    'Attractions',
    'Cafe',
    'Activities',
    'Nature',
    'Shopping',
    'Transport',
    'Emergency',
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadExploreData());
  }

  Future<void> _loadExploreData() async {
    // Resolve dependencies before the async gap; the widget can be disposed
    // while geolocation is still resolving.
    final places = context.read<PlaceProvider>();
    final offers = context.read<OfferProvider>();
    final routes = context.read<RouteProvider>();
    final sos = context.read<SosProvider>();

    final loc = await _locationService.getCurrentLocation();
    if (!mounted) return;

    places.fetchFeaturedPlaces(lat: loc?.latitude, lng: loc?.longitude);
    if (offers.offers.isEmpty && !offers.isLoading) offers.fetchOffers();
    if (routes.routes.isEmpty && !routes.isLoading) routes.fetchRoutes();
    if (loc != null) {
      sos.fetchNearbySos(loc.latitude, loc.longitude, radiusKm: 10);
    }
  }

  Future<void> _refresh() async {
    final places = context.read<PlaceProvider>();
    final alerts = context.read<AlertProvider>();
    final offers = context.read<OfferProvider>();
    final routes = context.read<RouteProvider>();
    final sos = context.read<SosProvider>();

    final loc = await _locationService.getCurrentLocation();
    final futures = <Future<void>>[offers.fetchOffers(), routes.fetchRoutes()];

    if (loc != null) {
      alerts.setLocation(loc.latitude, loc.longitude);
      futures.addAll([
        alerts.fetchNearby(),
        places.fetchNearbyPlaces(lat: loc.latitude, lng: loc.longitude),
        places.fetchFeaturedPlaces(lat: loc.latitude, lng: loc.longitude),
        sos.fetchNearbySos(loc.latitude, loc.longitude, radiusKm: 10),
      ]);
    } else {
      futures.add(alerts.fetchNearby());
    }

    await Future.wait(futures);
  }

  void _openSearch({String? category}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => ExploreSearchScreen(category: category),
      ),
    );
  }

  void _openPlace(PlaceModel place) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PlaceDetailsScreen(place: place.toPlace()),
      ),
    );
  }

  List<PlaceModel> _topPicks(
    List<PlaceModel> featured,
    List<PlaceModel> nearby,
  ) {
    final seen = <String>{};
    final picks = <PlaceModel>[];
    for (final place in [...featured, ...nearby]) {
      if (picks.length >= 10) break;
      if (seen.add(placeIdentityKey(place))) picks.add(place);
    }
    return picks;
  }

  String _greeting(BuildContext context) {
    final hour = DateTime.now().hour;
    if (hour < 12) return context.t('Good morning');
    if (hour < 17) return context.t('Good afternoon');
    return context.t('Good evening');
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final alertProvider = context.watch<AlertProvider>();
    final placeProvider = context.watch<PlaceProvider>();

    final user = auth.user;
    final alerts = alertProvider.items;
    final places = placeProvider.places;
    final picks = _topPicks(placeProvider.featuredPlaces, places);
    final nearbyDistrict = places.isNotEmpty ? places.first.district : null;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: AppTheme.backgroundColor,
        body: SafeArea(
          bottom: false,
          child: RefreshIndicator(
            color: AppTheme.primaryColor,
            backgroundColor: Colors.white,
            onRefresh: _refresh,
            child: ListView(
              padding: EdgeInsets.zero,
              physics: const AlwaysScrollableScrollPhysics(
                parent: BouncingScrollPhysics(),
              ),
              children: [
                _buildHeader(context, user, alerts.length, nearbyDistrict),
                const _SosSection(),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
                  child: _buildSearch(context),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
                  child: _HeroCard(
                    user: user,
                    nearbyCount: places.length,
                    alertCount: alerts.length,
                    onOpenAlerts:
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const AlertsScreen(),
                          ),
                        ),
                    onOpenMap:
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const NearbyMapScreen(),
                          ),
                        ),
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 16, 20, 0),
                  child: _QuickActions(
                    onNearby:
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const NearbyMapScreen(),
                          ),
                        ),
                    onReport:
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const ReportsListScreen(),
                          ),
                        ),
                    onAssistant:
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const AssistantScreen(),
                          ),
                        ),
                    onSos:
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const EmergencyScreen(),
                          ),
                        ),
                  ),
                ),
                _SectionHeader(
                  title: context.t('Discover'),
                  icon: Icons.grid_view_rounded,
                  onAction: () => _openSearch(),
                ),
                _CategoryRail(
                  categories: _categories,
                  onTap: (category) => _openSearch(category: category),
                ),
                _SectionHeader(
                  title: context.t('Top picks near you'),
                  icon: Icons.travel_explore_rounded,
                  onAction: () => _openSearch(),
                ),
                _PlacesRail(
                  places: picks,
                  isLoading: placeProvider.isLoading,
                  onTapPlace: _openPlace,
                  onExplore: () => _openSearch(),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 22, 16, 0),
                  child: const AdBannerCarousel(),
                ),
                _SectionHeader(
                  title: context.t('Live Alerts'),
                  icon: Icons.notifications_active_outlined,
                  color: AppTheme.severityHigh,
                  count: alerts.length,
                  onAction:
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const AlertsScreen()),
                      ),
                ),
                _AlertsSection(
                  alerts: alerts.take(3).toList(),
                  isLoading: alertProvider.isLoading,
                ),
                _SectionHeader(
                  title: context.t('Routes & Treks'),
                  icon: Icons.hiking_rounded,
                  color: const Color(0xFFB45309),
                  onAction:
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const RoutesScreen()),
                      ),
                ),
                const _RoutesSection(),
                _SectionHeader(
                  title: context.t('Rewards'),
                  icon: Icons.local_offer_outlined,
                  color: const Color(0xFFEA7A12),
                  onAction:
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const OffersScreen()),
                      ),
                ),
                const _OffersSection(),
                const _CommunityRow(),
                const Padding(
                  padding: EdgeInsets.fromLTRB(20, 24, 20, 0),
                  child: AdInlineBanner(adContext: 'home'),
                ),
                const SizedBox(height: 30),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildHeader(
    BuildContext context,
    UserModel? user,
    int alertCount,
    String? district,
  ) {
    final location =
        district != null && district.trim().isNotEmpty
            ? district.trim()
            : context.t('Nepal');

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 14, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              SvgPicture.asset('assets/images/oripori_logo.svg', height: 27),
              const Spacer(),
              _HeaderAction(
                icon: Icons.notifications_none_rounded,
                badgeCount: alertCount,
                onTap:
                    () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const AlertsScreen()),
                    ),
              ),
              const SizedBox(width: 8),
              _HeaderAction(
                icon: Icons.chat_bubble_outline_rounded,
                onTap:
                    () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const AssistantScreen(),
                      ),
                    ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Text(
            '${_greeting(context)} 👋',
            style: const TextStyle(
              fontSize: AppTheme.textSm,
              fontWeight: FontWeight.w600,
              color: AppTheme.textSecondary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            context.t('Explore Nepal'),
            style: const TextStyle(
              fontSize: 25,
              fontWeight: FontWeight.w800,
              color: AppTheme.textPrimary,
              letterSpacing: -0.6,
              height: 1.15,
            ),
          ),
          const SizedBox(height: 5),
          Row(
            children: [
              const Icon(
                Icons.location_on_rounded,
                size: 14,
                color: AppTheme.primaryColor,
              ),
              const SizedBox(width: 4),
              Flexible(
                child: Text(
                  location,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: AppTheme.textSm,
                    fontWeight: FontWeight.w600,
                    color: AppTheme.textPrimary,
                  ),
                ),
              ),
              const Padding(
                padding: EdgeInsets.symmetric(horizontal: 5),
                child: Text(
                  '·',
                  style: TextStyle(
                    fontSize: AppTheme.textSm,
                    color: AppTheme.textSecondary,
                  ),
                ),
              ),
              Text(
                context.t('Travel smart, explore more'),
                style: const TextStyle(
                  fontSize: AppTheme.textSm,
                  color: AppTheme.textSecondary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSearch(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _openSearch(),
        child: Container(
          height: 52,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppTheme.dividerColor),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.03),
                blurRadius: 10,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              const Icon(
                Icons.search_rounded,
                size: 22,
                color: AppTheme.textSecondary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  context.t('Search places, hotels, restaurants...'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: AppTheme.textBase,
                    color: AppTheme.textSecondary,
                  ),
                ),
              ),
              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.tune_rounded,
                  size: 18,
                  color: AppTheme.primaryColor,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Header
// ─────────────────────────────────────────────────────────────────────────────

class _HeaderAction extends StatelessWidget {
  final IconData icon;
  final int badgeCount;
  final VoidCallback onTap;

  const _HeaderAction({
    required this.icon,
    required this.onTap,
    this.badgeCount = 0,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(13),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(13),
        child: Container(
          width: 42,
          height: 42,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(13),
            border: Border.all(color: AppTheme.dividerColor),
          ),
          child: Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.center,
            children: [
              Icon(icon, size: 21, color: AppTheme.textPrimary),
              if (badgeCount > 0)
                Positioned(
                  right: -4,
                  top: -4,
                  child: Container(
                    constraints: const BoxConstraints(minWidth: 17),
                    height: 17,
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: AppTheme.errorColor,
                      borderRadius: BorderRadius.circular(9),
                      border: Border.all(color: Colors.white, width: 1.5),
                    ),
                    child: Text(
                      badgeCount > 9 ? '9+' : '$badgeCount',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 9,
                        fontWeight: FontWeight.w800,
                        height: 1,
                      ),
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

// ─────────────────────────────────────────────────────────────────────────────
// Emergency SOS
// ─────────────────────────────────────────────────────────────────────────────

class _SosSection extends StatelessWidget {
  const _SosSection();

  @override
  Widget build(BuildContext context) {
    return Consumer<SosProvider>(
      builder: (context, provider, _) {
        final items = provider.nearbySos.take(2).toList();
        if (items.isEmpty) return const SizedBox.shrink();
        return Padding(
          padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
          child: Column(
            children: [for (final sos in items) _SosCard(sos: sos)],
          ),
        );
      },
    );
  }
}

class _SosCard extends StatelessWidget {
  final SosAlert sos;

  const _SosCard({required this.sos});

  Future<void> _reportFalseAlarm(BuildContext context) async {
    final reason = await showDialog<String>(
      context: context,
      builder:
          (ctx) => AlertDialog(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
            title: Row(
              children: [
                const Icon(
                  Icons.flag_outlined,
                  color: Color(0xFFE67E22),
                  size: 24,
                ),
                const SizedBox(width: 8),
                Text(
                  context.t('Report False SOS?'),
                  style: const TextStyle(fontSize: 18),
                ),
              ],
            ),
            content: Text(
              context.t('Only report if you are sure this is a false alarm.'),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: Text(context.t('Cancel')),
              ),
              TextButton(
                onPressed: () => Navigator.pop(ctx, 'false_alarm'),
                child: Text(
                  context.t('False Alarm'),
                  style: const TextStyle(color: Color(0xFFE67E22)),
                ),
              ),
            ],
          ),
    );
    if (reason == null || !context.mounted) return;

    final message = await context.read<SosProvider>().reportFalseSos(
      sos.id,
      reason: reason,
    );
    if (message == null || !context.mounted) return;

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFDC2626), Color(0xFFB91C1C)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFFDC2626).withValues(alpha: 0.24),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap:
              () => Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const EmergencyScreen()),
              ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.warning_amber_rounded,
                    color: Colors.white,
                    size: 22,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Flexible(
                            child: Text(
                              context.t('Emergency SOS Nearby'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 13.5,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          if (sos.distanceKm != null) ...[
                            const SizedBox(width: 6),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 6,
                                vertical: 2,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.18),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                '${sos.distanceKm!.toStringAsFixed(1)} km',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 9.5,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${sos.emergencyType.toUpperCase()} · ${context.t('Someone nearby needs help')}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                IconButton(
                  onPressed: () => _reportFalseAlarm(context),
                  tooltip: context.t('Report false alarm'),
                  icon: const Icon(
                    Icons.flag_outlined,
                    size: 17,
                    color: Colors.white70,
                  ),
                  padding: EdgeInsets.zero,
                  constraints: const BoxConstraints(
                    minWidth: 32,
                    minHeight: 32,
                  ),
                ),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: Colors.white70,
                  size: 20,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Hero
// ─────────────────────────────────────────────────────────────────────────────

class _HeroCard extends StatelessWidget {
  final UserModel? user;
  final int nearbyCount;
  final int alertCount;
  final VoidCallback onOpenAlerts;
  final VoidCallback onOpenMap;

  const _HeroCard({
    required this.user,
    required this.nearbyCount,
    required this.alertCount,
    required this.onOpenAlerts,
    required this.onOpenMap,
  });

  @override
  Widget build(BuildContext context) {
    final isGuest = user == null;
    final firstName =
        (user?.name.trim().isNotEmpty ?? false)
            ? user!.name.trim().split(' ').first
            : context.t('Explorer');
    final alertValueColor =
        alertCount > 0 ? const Color(0xFFFFD166) : Colors.white;

    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF004D40), Color(0xFF00695C), Color(0xFF00897B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primaryColor.withValues(alpha: 0.24),
            blurRadius: 20,
            offset: const Offset(0, 9),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            right: 0,
            bottom: 0,
            height: 92,
            child: CustomPaint(
              painter: _MountainPainter(
                backColor: Colors.white.withValues(alpha: 0.06),
                frontColor: Colors.white.withValues(alpha: 0.09),
              ),
            ),
          ),
          Positioned(
            top: -36,
            right: -26,
            child: _decorCircle(118, Colors.white.withValues(alpha: 0.06)),
          ),
          Positioned(
            top: 48,
            right: 24,
            child: _decorCircle(
              9,
              AppTheme.secondaryColor.withValues(alpha: 0.85),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Container(
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
                              isGuest
                                  ? Icons.landscape_rounded
                                  : Icons.emoji_events_rounded,
                              size: 12,
                              color: AppTheme.secondaryColor,
                            ),
                            const SizedBox(width: 5),
                            Flexible(
                              child: Text(
                                isGuest
                                    ? context.t('EXPLORE NEPAL')
                                    : 'LVL ${user!.currentLevel} · ${user!.levelName.toUpperCase()}',
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.6,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    Text(
                      isGuest
                          ? context.t('Nepal')
                          : '${_formatCount(user!.totalXp)} XP',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.72),
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 13),
                Text(
                  isGuest
                      ? '${context.t('Welcome')}, ${context.t('Explorer')} 👋'
                      : '${context.t('Welcome')}, $firstName 👋',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 19,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  isGuest
                      ? context.t(
                        'Discover hidden gems, local favourites and live travel updates.',
                      )
                      : context.t('Ready to discover something new today?'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.72),
                    fontSize: 11.5,
                    height: 1.35,
                  ),
                ),
                if (!isGuest) ...[
                  const SizedBox(height: 12),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: user!.levelProgress,
                      minHeight: 6,
                      backgroundColor: Colors.white.withValues(alpha: 0.18),
                      valueColor: const AlwaysStoppedAnimation<Color>(
                        AppTheme.secondaryColor,
                      ),
                    ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    user!.nextLevelXp == 0
                        ? context.t('Max level reached')
                        : '${context.t('Next level')}: ${user!.nextLevelName}',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.62),
                      fontSize: 9.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                Container(
                  height: 1,
                  color: Colors.white.withValues(alpha: 0.14),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _HeroStat(
                        icon: Icons.place_outlined,
                        value: _formatCount(nearbyCount),
                        label: context.t('places nearby'),
                        onTap: onOpenMap,
                      ),
                    ),
                    _heroDivider(),
                    Expanded(
                      child: _HeroStat(
                        icon: Icons.notifications_none_rounded,
                        value: _formatCount(alertCount),
                        label: context.t('active alerts'),
                        valueColor: alertValueColor,
                        onTap: onOpenAlerts,
                      ),
                    ),
                    _heroDivider(),
                    Expanded(
                      child: _HeroStat(
                        icon:
                            isGuest
                                ? Icons.map_outlined
                                : Icons.verified_outlined,
                        value:
                            isGuest
                                ? '77'
                                : _formatCount(user!.approvedReports),
                        label:
                            isGuest
                                ? context.t('districts')
                                : context.t('approved reports'),
                      ),
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
  final IconData icon;
  final String value;
  final String label;
  final Color? valueColor;
  final VoidCallback? onTap;

  const _HeroStat({
    required this.icon,
    required this.value,
    required this.label,
    this.valueColor,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final content = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 13, color: Colors.white.withValues(alpha: 0.72)),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: valueColor ?? Colors.white,
                  fontSize: 15.5,
                  fontWeight: FontWeight.w800,
                  height: 1.1,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 3),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.66),
            fontSize: 9.5,
            fontWeight: FontWeight.w500,
          ),
        ),
      ],
    );

    if (onTap == null) return content;
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: content,
      ),
    );
  }
}

Widget _heroDivider() => Container(
  width: 1,
  height: 28,
  color: Colors.white.withValues(alpha: 0.14),
);

Widget _decorCircle(double size, Color color) => Container(
  width: size,
  height: size,
  decoration: BoxDecoration(shape: BoxShape.circle, color: color),
);

class _MountainPainter extends CustomPainter {
  final Color backColor;
  final Color frontColor;

  const _MountainPainter({required this.backColor, required this.frontColor});

  @override
  void paint(Canvas canvas, Size size) {
    final back =
        Path()
          ..moveTo(0, size.height)
          ..lineTo(0, size.height * 0.62)
          ..lineTo(size.width * 0.16, size.height * 0.42)
          ..lineTo(size.width * 0.30, size.height * 0.66)
          ..lineTo(size.width * 0.47, size.height * 0.28)
          ..lineTo(size.width * 0.66, size.height * 0.64)
          ..lineTo(size.width * 0.81, size.height * 0.46)
          ..lineTo(size.width, size.height * 0.70)
          ..lineTo(size.width, size.height)
          ..close();
    canvas.drawPath(back, Paint()..color = backColor);

    final front =
        Path()
          ..moveTo(0, size.height)
          ..lineTo(0, size.height * 0.82)
          ..lineTo(size.width * 0.22, size.height * 0.60)
          ..lineTo(size.width * 0.42, size.height * 0.86)
          ..lineTo(size.width * 0.60, size.height * 0.56)
          ..lineTo(size.width * 0.78, size.height * 0.84)
          ..lineTo(size.width, size.height * 0.64)
          ..lineTo(size.width, size.height)
          ..close();
    canvas.drawPath(front, Paint()..color = frontColor);
  }

  @override
  bool shouldRepaint(covariant _MountainPainter oldDelegate) =>
      oldDelegate.backColor != backColor ||
      oldDelegate.frontColor != frontColor;
}

// ─────────────────────────────────────────────────────────────────────────────
// Quick actions
// ─────────────────────────────────────────────────────────────────────────────

class _QuickActions extends StatelessWidget {
  final VoidCallback onNearby;
  final VoidCallback onReport;
  final VoidCallback onAssistant;
  final VoidCallback onSos;

  const _QuickActions({
    required this.onNearby,
    required this.onReport,
    required this.onAssistant,
    required this.onSos,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Row(
        children: [
          Expanded(
            child: _QuickActionTile(
              icon: Icons.map_outlined,
              label: context.t('Nearby'),
              color: AppTheme.primaryColor,
              onTap: onNearby,
            ),
          ),
          Expanded(
            child: _QuickActionTile(
              icon: Icons.add_alert_outlined,
              label: context.t('Report'),
              color: const Color(0xFFE67E22),
              onTap: onReport,
            ),
          ),
          Expanded(
            child: _QuickActionTile(
              icon: Icons.auto_awesome_rounded,
              label: context.t('AI Guide'),
              color: AppTheme.infoColor,
              onTap: onAssistant,
            ),
          ),
          Expanded(
            child: _QuickActionTile(
              icon: Icons.emergency_rounded,
              label: context.t('SOS'),
              color: AppTheme.errorColor,
              emphasized: true,
              onTap: onSos,
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickActionTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  final bool emphasized;

  const _QuickActionTile({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
    this.emphasized = false,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: emphasized ? color : color.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(
                icon,
                size: 22,
                color: emphasized ? Colors.white : color,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Sections
// ─────────────────────────────────────────────────────────────────────────────

class _SectionHeader extends StatelessWidget {
  final String title;
  final IconData? icon;
  final Color? color;
  final int? count;
  final VoidCallback onAction;

  const _SectionHeader({
    required this.title,
    required this.onAction,
    this.icon,
    this.color,
    this.count,
  });

  @override
  Widget build(BuildContext context) {
    final accent = color ?? AppTheme.primaryColor;
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 14, 12),
      child: Row(
        children: [
          if (icon != null) ...[
            Container(
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(9),
              ),
              child: Icon(icon, size: 16, color: accent),
            ),
            const SizedBox(width: 9),
          ],
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: AppTheme.textPrimary,
                letterSpacing: -0.2,
              ),
            ),
          ),
          if (count != null && count! > 0) ...[
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                '$count',
                style: TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  color: accent,
                ),
              ),
            ),
            const SizedBox(width: 4),
          ],
          InkWell(
            onTap: onAction,
            borderRadius: BorderRadius.circular(20),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    context.t('See All'),
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                      color: AppTheme.primaryColor,
                    ),
                  ),
                  const SizedBox(width: 2),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 16,
                    color: AppTheme.primaryColor,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CategoryRail extends StatelessWidget {
  final List<String> categories;
  final ValueChanged<String> onTap;

  const _CategoryRail({required this.categories, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 88,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: categories.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, index) {
          final category = categories[index];
          final color = getCategoryColor(category);
          return SizedBox(
            width: 64,
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => onTap(category),
              child: Column(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(17),
                      border: Border.all(color: color.withValues(alpha: 0.18)),
                    ),
                    child: Icon(
                      getCategoryIcon(category),
                      color: color,
                      size: 24,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    category,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}

class _PlacesRail extends StatelessWidget {
  final List<PlaceModel> places;
  final bool isLoading;
  final ValueChanged<PlaceModel> onTapPlace;
  final VoidCallback onExplore;

  const _PlacesRail({
    required this.places,
    required this.isLoading,
    required this.onTapPlace,
    required this.onExplore,
  });

  @override
  Widget build(BuildContext context) {
    if (places.isEmpty) {
      if (isLoading) {
        return const _StripSkeleton(height: 218, itemWidth: 192);
      }
      return _EmptyStripCard(
        icon: Icons.travel_explore_rounded,
        color: AppTheme.primaryColor,
        title: context.t('No nearby places yet'),
        subtitle: context.t(
          'Explore all places across Nepal and find your next stop.',
        ),
        actionLabel: context.t('Explore'),
        onAction: onExplore,
      );
    }

    return SizedBox(
      height: 218,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: places.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) {
          final place = places[index];
          return ExplorePlaceCard(
            place: place,
            width: 192,
            onTap: () => onTapPlace(place),
          );
        },
      ),
    );
  }
}

class _AlertsSection extends StatelessWidget {
  final List<NearbyItem> alerts;
  final bool isLoading;

  const _AlertsSection({required this.alerts, required this.isLoading});

  @override
  Widget build(BuildContext context) {
    if (isLoading && alerts.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: ShimmerSkeleton(
          child: Column(
            children: [
              for (var i = 0; i < 2; i++)
                Container(
                  height: 86,
                  margin: const EdgeInsets.only(bottom: 9),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
            ],
          ),
        ),
      );
    }

    if (alerts.isEmpty) return const _AllClearCard();

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Column(
        children: [
          for (final alert in alerts)
            Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: _AlertTile(alert: alert),
            ),
        ],
      ),
    );
  }
}

class _AllClearCard extends StatelessWidget {
  const _AllClearCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.successColor.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: AppTheme.successColor.withValues(alpha: 0.18),
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppTheme.successColor.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.check_circle_outline_rounded,
              color: AppTheme.successColor,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.t('All clear in your area'),
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  context.t('No active alerts right now. Travel safe!'),
                  style: const TextStyle(
                    fontSize: 11.5,
                    color: AppTheme.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AlertTile extends StatelessWidget {
  final NearbyItem alert;

  const _AlertTile({required this.alert});

  @override
  Widget build(BuildContext context) {
    final color = _severityColor(alert.severity);
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap:
            () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const AlertsScreen()),
            ),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppTheme.dividerColor),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.10),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  _severityIcon(alert.severity),
                  color: color,
                  size: 21,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            alert.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: color,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            alert.severity.toUpperCase(),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 8.5,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.3,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      alert.description,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11.5,
                        color: AppTheme.textSecondary,
                        height: 1.3,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        if (alert.affectedDistrict != null &&
                            alert.affectedDistrict!.isNotEmpty) ...[
                          Icon(
                            Icons.location_on_outlined,
                            size: 11,
                            color: AppTheme.textSecondary.withValues(
                              alpha: 0.8,
                            ),
                          ),
                          const SizedBox(width: 2),
                          Flexible(
                            child: Text(
                              alert.affectedDistrict!,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 10,
                                color: AppTheme.textSecondary,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                        ],
                        Icon(
                          Icons.schedule_rounded,
                          size: 11,
                          color: AppTheme.textSecondary.withValues(alpha: 0.8),
                        ),
                        const SizedBox(width: 2),
                        Text(
                          _relativeTime(context, alert.createdAt),
                          style: const TextStyle(
                            fontSize: 10,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                        const Spacer(),
                        Text(
                          alert.isReport
                              ? context.t('Community')
                              : context.t('Official'),
                          style: TextStyle(
                            fontSize: 9.5,
                            fontWeight: FontWeight.w700,
                            color:
                                alert.isReport
                                    ? AppTheme.infoColor
                                    : AppTheme.primaryColor,
                          ),
                        ),
                      ],
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
}

class _RoutesSection extends StatelessWidget {
  const _RoutesSection();

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RouteProvider>();
    final routes = provider.routes;

    if (provider.isLoading && routes.isEmpty) {
      return const _StripSkeleton(height: 164, itemWidth: 234);
    }

    if (routes.isEmpty) {
      return _EmptyStripCard(
        icon: Icons.hiking_rounded,
        color: const Color(0xFFB45309),
        title: context.t('Curated treks are on the way'),
        subtitle: context.t(
          'Hand-picked treks & itineraries to explore Nepal.',
        ),
        actionLabel: context.t('Explore'),
        onAction:
            () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const RoutesScreen()),
            ),
      );
    }

    return SizedBox(
      height: 164,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: routes.length > 6 ? 6 : routes.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) => _MiniRouteCard(route: routes[index]),
      ),
    );
  }
}

class _OffersSection extends StatelessWidget {
  const _OffersSection();

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OfferProvider>();
    final offers = provider.offers;

    if (provider.isLoading && offers.isEmpty) {
      return const _StripSkeleton(height: 158, itemWidth: 224);
    }

    if (offers.isEmpty) {
      return _EmptyStripCard(
        icon: Icons.card_giftcard_rounded,
        color: const Color(0xFFEA7A12),
        title: context.t('Exclusive business offers & discounts'),
        subtitle: context.t(
          'Local businesses post rewards here. Check back soon!',
        ),
        actionLabel: context.t('Explore'),
        onAction:
            () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const OffersScreen()),
            ),
      );
    }

    return SizedBox(
      height: 158,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: offers.length > 6 ? 6 : offers.length,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder: (context, index) => _MiniOfferCard(offer: offers[index]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Cards
// ─────────────────────────────────────────────────────────────────────────────

class _MiniRouteCard extends StatelessWidget {
  final CuratedRouteModel route;

  const _MiniRouteCard({required this.route});

  @override
  Widget build(BuildContext context) {
    final colors =
        route.isTrekking
            ? const [Color(0xFFB45309), Color(0xFF78350F)]
            : const [AppTheme.primaryColor, Color(0xFF0F766E)];

    final start = route.startingPoint?.trim();
    final end = route.endingPoint?.trim();
    final meta = [
      if (route.difficultyLabel.isNotEmpty) route.difficultyLabel,
      if (route.totalDistanceKm != null) '${route.totalDistanceKm!.round()} km',
      if (route.maxAltitudeM != null) '${_formatCount(route.maxAltitudeM!)} m',
    ].join('  ·  ');

    return GestureDetector(
      onTap:
          () => Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => RouteDetailScreen(routeId: route.id),
            ),
          ),
      child: Container(
        width: 234,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: colors,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: colors.first.withValues(alpha: 0.22),
              blurRadius: 12,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            Positioned(
              right: -22,
              top: -26,
              child: _decorCircle(96, Colors.white.withValues(alpha: 0.07)),
            ),
            Padding(
              padding: const EdgeInsets.all(15),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.16),
                          borderRadius: BorderRadius.circular(30),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              route.isTrekking
                                  ? Icons.hiking_rounded
                                  : Icons.map_outlined,
                              size: 11,
                              color: Colors.white,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              route.isTrekking
                                  ? context.t('Trekking')
                                  : context.t('Itinerary'),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const Spacer(),
                      Icon(
                        Icons.calendar_today_outlined,
                        size: 11,
                        color: Colors.white.withValues(alpha: 0.7),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '${route.durationDays} ${context.t('days')}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    route.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 15.5,
                      fontWeight: FontWeight.w800,
                      height: 1.2,
                    ),
                  ),
                  const Spacer(),
                  if (start != null &&
                      start.isNotEmpty &&
                      end != null &&
                      end.isNotEmpty) ...[
                    Row(
                      children: [
                        Icon(
                          Icons.route_outlined,
                          size: 12,
                          color: Colors.white.withValues(alpha: 0.7),
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            '$start → $end',
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.72),
                              fontSize: 10,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 5),
                  ],
                  Text(
                    meta,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.72),
                      fontSize: 10,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniOfferCard extends StatelessWidget {
  static const _gradients = [
    [Color(0xFF00695C), Color(0xFF26A69A)],
    [Color(0xFFEF6C00), Color(0xFFF9A825)],
    [Color(0xFF5C6BC0), Color(0xFF7E57C2)],
    [Color(0xFFC62828), Color(0xFFEF5350)],
    [Color(0xFF2E7D32), Color(0xFF66BB6A)],
  ];

  final OfferModel offer;

  const _MiniOfferCard({required this.offer});

  @override
  Widget build(BuildContext context) {
    final colors = _gradients[offer.id % _gradients.length];
    final business = offer.business?.name ?? context.t('Local business');

    return GestureDetector(
      onTap:
          () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => OfferDetailScreen(offer: offer)),
          ),
      child: Container(
        width: 224,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: colors,
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(
              color: colors.first.withValues(alpha: 0.22),
              blurRadius: 12,
              offset: const Offset(0, 5),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            Positioned(
              right: -20,
              top: -24,
              child: _decorCircle(92, Colors.white.withValues(alpha: 0.08)),
            ),
            Padding(
              padding: const EdgeInsets.all(15),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.18),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          context.t('REWARD'),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 8.5,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.6,
                          ),
                        ),
                      ),
                      const Spacer(),
                      if (!offer.isUnlimited)
                        Text(
                          offer.remainingLabel,
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.72),
                            fontSize: 9.5,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    offer.label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 19,
                      fontWeight: FontWeight.w900,
                      letterSpacing: -0.3,
                      height: 1.1,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    offer.title,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.82),
                      fontSize: 11,
                      height: 1.25,
                    ),
                  ),
                  const Spacer(),
                  Row(
                    children: [
                      Icon(
                        Icons.storefront_outlined,
                        size: 12,
                        color: Colors.white.withValues(alpha: 0.7),
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          business,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(width: 6),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 7,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.18),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.bolt_rounded,
                              size: 11,
                              color: Colors.white,
                            ),
                            const SizedBox(width: 2),
                            Text(
                              offer.priceXp > 0
                                  ? '${offer.priceXp} XP'
                                  : context.t('Free'),
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 9.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CommunityRow extends StatelessWidget {
  const _CommunityRow();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 0),
      child: Row(
        children: [
          Expanded(
            child: _CommunityCard(
              icon: Icons.emoji_events_rounded,
              color: AppTheme.secondaryColor,
              title: context.t('Leaderboard'),
              subtitle: context.t('See top contributors'),
              onTap:
                  () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const LeaderboardScreen(),
                    ),
                  ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: _CommunityCard(
              icon: Icons.edit_location_alt_rounded,
              color: AppTheme.primaryColor,
              title: context.t('New Report'),
              subtitle: context.t('Help travellers & earn XP'),
              onTap:
                  () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const ReportsListScreen(),
                    ),
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CommunityCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _CommunityCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 118,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppTheme.dividerColor),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child: Icon(icon, color: color, size: 19),
                ),
                const Spacer(),
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10.5,
                    color: AppTheme.textSecondary,
                    height: 1.25,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared small widgets
// ─────────────────────────────────────────────────────────────────────────────

class _EmptyStripCard extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final String actionLabel;
  final VoidCallback onAction;

  const _EmptyStripCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.actionLabel,
    required this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 21),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10.5,
                    color: AppTheme.textSecondary,
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          TextButton(
            onPressed: onAction,
            style: TextButton.styleFrom(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
            child: Text(
              actionLabel,
              style: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StripSkeleton extends StatelessWidget {
  final double height;
  final double itemWidth;

  const _StripSkeleton({required this.height, required this.itemWidth});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: height,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 20),
        itemCount: 3,
        separatorBuilder: (_, __) => const SizedBox(width: 12),
        itemBuilder:
            (_, __) => ShimmerSkeleton(
              child: Container(
                width: itemWidth,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                ),
              ),
            ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

String _formatCount(int value) {
  if (value >= 1000000) {
    return '${(value / 1000000).toStringAsFixed(1)}M';
  }
  if (value >= 1000) {
    final text = value.toString();
    return '${text.substring(0, text.length - 3)},${text.substring(text.length - 3)}';
  }
  return '$value';
}

String _relativeTime(BuildContext context, DateTime dateTime) {
  final diff = DateTime.now().difference(dateTime);
  if (diff.inMinutes < 1) return context.t('Just now');
  if (diff.inMinutes < 60) return '${diff.inMinutes} ${context.t('min ago')}';
  if (diff.inHours < 24) return '${diff.inHours} ${context.t('hr ago')}';
  return '${diff.inDays} ${context.t('d ago')}';
}

Color _severityColor(String severity) {
  switch (severity) {
    case 'critical':
      return AppTheme.severityCritical;
    case 'high':
      return AppTheme.severityHigh;
    case 'medium':
      return AppTheme.severityMedium;
    case 'low':
      return AppTheme.severityLow;
    default:
      return AppTheme.severityInfo;
  }
}

IconData _severityIcon(String severity) {
  switch (severity) {
    case 'critical':
      return Icons.warning_rounded;
    case 'high':
      return Icons.cloud_rounded;
    case 'medium':
      return Icons.info_rounded;
    case 'low':
      return Icons.check_circle_outline_rounded;
    default:
      return Icons.info_outline_rounded;
  }
}
