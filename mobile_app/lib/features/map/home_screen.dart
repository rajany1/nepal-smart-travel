import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import "../../core/services/localization_service.dart";
import '../../config/themes/app_theme.dart';
import '../../core/services/location_service.dart';
import '../../providers/auth_provider.dart';
import '../../providers/alert_provider.dart';
import '../../providers/place_provider.dart';
import '../../providers/report_provider.dart';
import '../../providers/sos_provider.dart';

import '../places/nearby_map_screen.dart';
import '../reporting/reports_list_screen.dart';
import '../emergency/emergency_screen.dart';
import '../assistant/assistant_screen.dart';
import '../profile/profile_screen.dart';
import '../alerts/alerts_screen.dart';
import '../leaderboard/leaderboard_screen.dart';
import '../offers/offers_screen.dart';
import '../offers/offer_detail_screen.dart';
import '../../widgets/ad_banner_carousel.dart';
import '../../widgets/ad_inline_banner.dart';
import '../routes/routes_screen.dart';
import '../routes/route_detail_screen.dart';
import '../../core/models/offer_model.dart';
import '../../core/models/route_model.dart';
import '../../providers/offer_provider.dart';
import '../../providers/route_provider.dart';
import '../places/explore_search_screen.dart';
import '../places/place_details_screen.dart';
import '../places/utils/category_utils.dart';
import '../../widgets/explore_place_card.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 2;
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
    final loc = await _locationService.getCurrentLocation();
    if (mounted) {
      if (loc != null) {
        final provider = context.read<AlertProvider>();
        provider.setLocation(loc.latitude, loc.longitude);
        provider.fetchNearby();
        context.read<PlaceProvider>().fetchNearbyPlaces(
          lat: loc.latitude,
          lng: loc.longitude,
        );
      } else {
        context.read<AlertProvider>().fetchNearby();
      }
    }
  }

  void _onTabChanged(int index) {
    // FL-29: hidden IndexedStack tabs keep polling — pause the reports
    // auto-refresh while its tab is not visible
    final provider = context.read<ReportProvider>();
    if (_currentIndex == 2) provider.stopAutoRefresh();
    if (index == 2) {
      provider.startAutoRefresh();
      provider.fetchReports(lat: provider.lastFetchLat, lng: provider.lastFetchLng, radiusKm: 20.0);
      provider.fetchEmergencyReports(lat: provider.lastFetchLat, lng: provider.lastFetchLng, radiusKm: 20.0, refresh: true);
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

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: availableTabs.map<Widget>((tab) => tab['screen'] as Widget).toList(),
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: _onTabChanged,
        items: availableTabs
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

class _ExploreTab extends StatefulWidget {
  const _ExploreTab();

  @override
  State<_ExploreTab> createState() => _ExploreTabState();
}

class _ExploreTabState extends State<_ExploreTab> {
  final LocationService _locationService = LocationService();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final loc = await _locationService.getCurrentLocation();
      if (!mounted) return;
      context.read<PlaceProvider>().fetchFeaturedPlaces(
            lat: loc?.latitude,
            lng: loc?.longitude,
          );
      if (loc != null) {
        context.read<SosProvider>().fetchNearbySos(loc.latitude, loc.longitude, radiusKm: 10);
      }
    });
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return context.t('Just now');
    if (diff.inMinutes < 60) return '${diff.inMinutes} ${context.t('min ago')}';
    if (diff.inHours < 24) return '${diff.inHours} ${context.t('hr ago')}';
    return '${diff.inDays} ${context.t('d ago')}';
  }

  Widget _buildSearchHero(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const ExploreSearchScreen()),
        ),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppTheme.dividerColor),
          ),
          child: Row(
            children: [
              const Icon(Icons.search, color: AppTheme.textSecondary),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  context.t('Search places, hotels, restaurants...'),
                  style: const TextStyle(
                      color: AppTheme.textSecondary,
                      fontSize: AppTheme.textBase),
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text(
                  'NEPAL',
                  style: TextStyle(
                    color: AppTheme.primaryColor,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildWelcomeCard(BuildContext context, dynamic user) {
    final district = context.watch<PlaceProvider>().places.isNotEmpty
        ? context.watch<PlaceProvider>().places.first.district
        : null;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [AppTheme.primaryColor, AppTheme.primaryLight],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${context.t('Welcome')}${user != null ? ', ${user.name.split(' ').first}' : ''}!',
                  style: const TextStyle(
                    color: AppTheme.surfaceColor,
                    fontSize: AppTheme.text3xl,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
              if (district != null && district.isNotEmpty)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.location_on,
                          color: Colors.white, size: 14),
                      const SizedBox(width: 4),
                      Text(
                        district,
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: AppTheme.textXs,
                            fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            context.t('Discover Nepal\'s hidden gems, real-time travel conditions, and community insights'),
            style:
                const TextStyle(color: Colors.white70, fontSize: AppTheme.textBase),
          ),
        ],
      ),
    );
  }

  Widget _buildCategoryChips(BuildContext context) {
    const cats = [
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
    return SizedBox(
      height: 100,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: cats.length,
        separatorBuilder: (_, __) => const SizedBox(width: 10),
        itemBuilder: (context, i) {
          final c = cats[i];
          final color = getCategoryColor(c);
          return InkWell(
            borderRadius: BorderRadius.circular(14),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => ExploreSearchScreen(category: c)),
            ),
            child: Container(
              width: 98,
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppTheme.dividerColor),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: color.withOpacity(0.12),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(getCategoryIcon(c), color: color, size: 20),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    c,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                        fontSize: AppTheme.textXs,
                        fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final alerts = context.watch<AlertProvider>().items;
    final places = context.watch<PlaceProvider>().places;
    final featured = context.watch<PlaceProvider>().featuredPlaces;
    final recentAlerts = alerts.take(3).toList();
    final highlightPlaces = places.take(8).toList();
    final showFeatured = featured.isNotEmpty;

    return Scaffold(
      appBar: AppBar(
        title: Text(context.t('Nepal Smart Travel')),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AlertsScreen())),
          ),
          IconButton(
            icon: const Icon(Icons.chat_outlined),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AssistantScreen())),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Nearby SOS Alert
            Consumer<SosProvider>(
              builder: (context, sosProv, _) {
                if (sosProv.nearbySos.isEmpty) return const SizedBox.shrink();
                return Column(
                  children: [
                    for (final sos in sosProv.nearbySos.take(3))
                      Container(
                        margin: const EdgeInsets.only(bottom: 12),
                        decoration: BoxDecoration(
                          gradient: const LinearGradient(
                            colors: [Color(0xFFDC2626), Color(0xFFB91C1C)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(14),
                          boxShadow: [
                            BoxShadow(
                              color: const Color(0xFFDC2626).withOpacity(0.3),
                              blurRadius: 12,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Stack(
                          children: [
                            Material(
                              color: Colors.transparent,
                              child: InkWell(
                                borderRadius: BorderRadius.circular(14),
                                onTap: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => const EmergencyScreen()),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.all(14),
                                  child: Row(
                                    children: [
                                      Container(
                                        padding: const EdgeInsets.all(8),
                                        decoration: BoxDecoration(
                                          color: Colors.white.withOpacity(0.2),
                                          borderRadius: BorderRadius.circular(10),
                                        ),
                                        child: const Icon(Icons.warning_rounded, color: Colors.white, size: 22),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              context.t('Emergency SOS Nearby'),
                                              style: const TextStyle(
                                                color: Colors.white,
                                                fontSize: 15,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              sos.emergencyType.toUpperCase(),
                                              style: const TextStyle(
                                                color: Colors.white70,
                                                fontSize: 11,
                                                fontWeight: FontWeight.w600,
                                                letterSpacing: 0.5,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      if (sos.distanceKm != null)
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                          decoration: BoxDecoration(
                                            color: Colors.white.withOpacity(0.2),
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                          child: Text(
                                            '${sos.distanceKm!.toStringAsFixed(1)} km',
                                            style: const TextStyle(
                                              color: Colors.white,
                                              fontSize: 11,
                                              fontWeight: FontWeight.w700,
                                            ),
                                          ),
                                        ),
                                      const SizedBox(width: 8),
                                      const Icon(Icons.chevron_right, color: Colors.white70, size: 20),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                            Positioned(
                              top: 6,
                              right: 6,
                              child: GestureDetector(
                                onTap: () async {
                                  final reason = await showDialog<String>(
                                    context: context,
                                    builder: (ctx) => AlertDialog(
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                                      title: const Row(
                                        children: [
                                          Icon(Icons.flag_outlined, color: Color(0xFFE67E22), size: 24),
                                          SizedBox(width: 8),
                                          Text('Report False SOS?', style: TextStyle(fontSize: 18)),
                                        ],
                                      ),
                                      content: const Text('Only report if you are sure this is a false alarm.'),
                                      actions: [
                                        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
                                        TextButton(
                                          onPressed: () => Navigator.pop(ctx, 'false_alarm'),
                                          child: const Text('False Alarm', style: TextStyle(color: Color(0xFFE67E22))),
                                        ),
                                      ],
                                    ),
                                  );
                                  if (reason != null && mounted) {
                                    final msg = await context.read<SosProvider>().reportFalseSos(sos.id, reason: reason);
                                    if (mounted && msg != null) {
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          content: Text(msg),
                                          behavior: SnackBarBehavior.floating,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                        ),
                                      );
                                    }
                                  }
                                },
                                child: Container(
                                  padding: const EdgeInsets.all(4),
                                  decoration: BoxDecoration(
                                    color: Colors.white.withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: const Icon(Icons.flag_outlined, color: Colors.white70, size: 14),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                  ],
                );
              },
            ),
            _buildSearchHero(context),
            const SizedBox(height: 16),
            const AdBannerCarousel(),
            const SizedBox(height: 16),

            // Welcome Card
            _buildWelcomeCard(context, user),
            const SizedBox(height: 20),

            // Discover by category
            Text(context.t('Discover'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            _buildCategoryChips(context),
            const SizedBox(height: 20),

            // Quick Actions Grid
            Text(context.t('Quick Actions'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 3,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 14,
              crossAxisSpacing: 14,
              childAspectRatio: 1.1,
              children: [
                _QuickActionItem(icon: Icons.emoji_events, label: context.t('Leaderboard'), color: AppTheme.secondaryColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LeaderboardScreen()))),
                _QuickActionItem(icon: Icons.hiking, label: context.t('Routes'), color: const Color(0xFFB45309), onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RoutesScreen()))),
                _QuickActionItem(icon: Icons.add_circle, label: context.t('New Report'), color: AppTheme.warningColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportsListScreen()))),
                _QuickActionItem(icon: Icons.emergency, label: context.t('SOS'), color: AppTheme.errorColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EmergencyScreen()))),
                _QuickActionItem(icon: Icons.chat, label: context.t('AI Help'), color: AppTheme.infoColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AssistantScreen()))),
                _QuickActionItem(icon: Icons.local_offer, label: context.t('Offers'), color: AppTheme.primaryColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OffersScreen()))),
              ],
            ),
            const SizedBox(height: 24),

            // Rewards
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(context.t('Rewards'), style: Theme.of(context).textTheme.titleLarge),
                TextButton(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OffersScreen())),
                  child: Text(context.t('See All')),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const _RewardsStrip(),
            const SizedBox(height: 24),

            // Trekking & Curated Routes
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(context.t('Routes & Treks'), style: Theme.of(context).textTheme.titleLarge),
                TextButton(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RoutesScreen())),
                  child: Text(context.t('See All')),
                ),
              ],
            ),
            const SizedBox(height: 12),
            const _RoutesStrip(),
            const SizedBox(height: 24),

            // Live Alerts
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(context.t('Live Alerts'), style: Theme.of(context).textTheme.titleLarge),
                TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AlertsScreen())), child: Text(context.t('See All'))),
              ],
            ),
            const SizedBox(height: 8),
            if (recentAlerts.isEmpty)
              Card(
                margin: EdgeInsets.zero,
                child: ListTile(
                  leading: const Icon(Icons.check_circle, color: AppTheme.successColor),
                  title: Text(context.t('No active alerts')),
                  subtitle: Text(context.t('All clear in your area')),
                ),
              )
            else
              ...recentAlerts.map((alert) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _AlertCard(
                  icon: alert.severity == 'critical' ? Icons.warning_amber :
                       alert.severity == 'high' ? Icons.cloud :
                       alert.severity == 'medium' ? Icons.info :
                       Icons.info_outline,
                  title: alert.title,
                  description: alert.description,
                  severity: alert.severity.toUpperCase(),
                  color: alert.severity == 'critical' ? AppTheme.severityCritical :
                         alert.severity == 'high' ? AppTheme.severityHigh :
                         alert.severity == 'medium' ? AppTheme.severityMedium :
                         AppTheme.severityInfo,
                  time: _timeAgo(alert.createdAt),
                  district: alert.affectedDistrict,
                ),
              )),

            const SizedBox(height: 24),
            AdInlineBanner(adContext: 'home'),
            const SizedBox(height: 24),

            // Nearby Highlights
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(context.t('Nearby Highlights'), style: Theme.of(context).textTheme.titleLarge),
                TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NearbyMapScreen())), child: Text(context.t('View All'))),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 215,
              child: highlightPlaces.isEmpty
                  ? Center(child: Text(context.t('No nearby places found')))
                  : ListView.builder(
                      scrollDirection: Axis.horizontal,
                      itemCount: highlightPlaces.length,
                      itemBuilder: (context, index) {
                        final place = highlightPlaces[index];
                        return Padding(
                          padding: const EdgeInsets.only(right: 12),
                          child: ExplorePlaceCard(
                            place: place,
                            onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) =>
                                    PlaceDetailsScreen(place: place.toPlace()),
                              ),
                            ),
                          ),
                        );
                      },
                    ),
            ),

            if (showFeatured) ...[
              const SizedBox(height: 24),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(context.t('Featured Places'), style: Theme.of(context).textTheme.titleLarge),
                  TextButton(
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const ExploreSearchScreen()),
                    ),
                    child: Text(context.t('View All')),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              SizedBox(
                height: 215,
                child: ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: featured.length,
                  itemBuilder: (context, index) {
                    final place = featured[index];
                    return Padding(
                      padding: const EdgeInsets.only(right: 12),
                      child: ExplorePlaceCard(
                        place: place,
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) =>
                                PlaceDetailsScreen(place: place.toPlace()),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],

            if (user != null) ...[
              const SizedBox(height: 24),
              // User XP Card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppTheme.dividerColor),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryLight.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.emoji_events, color: AppTheme.secondaryColor, size: 32),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${context.t('Level')} ${user.currentLevel} - ${user.levelName}', style: const TextStyle(fontWeight: FontWeight.bold)),
                          const SizedBox(height: 4),
                          ClipRRect(
                            borderRadius: BorderRadius.circular(4),
                            child: LinearProgressIndicator(
                              value: user.levelProgress,
                              backgroundColor: AppTheme.dividerColor,
                              valueColor: const AlwaysStoppedAnimation(AppTheme.secondaryColor),
                              minHeight: 6,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text('${user.totalXp} XP \u2022 ${user.approvedReports} ${context.t('approved reports')}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RewardsStrip extends StatelessWidget {
  const _RewardsStrip();

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OfferProvider>();
    final offers = provider.offers;

    return SizedBox(
      height: 130,
      child: provider.isLoading && offers.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : offers.isEmpty
              ? Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppTheme.primaryColor.withOpacity(0.06),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.card_giftcard, color: AppTheme.primaryColor),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          context.t('Exclusive business offers & discounts'),
                          style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textSm),
                        ),
                      ),
                      TextButton(
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OffersScreen())),
                        child: Text(context.t('Explore')),
                      ),
                    ],
                  ),
                )
              : ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: offers.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                  itemBuilder: (context, i) => _MiniOfferCard(offer: offers[i]),
                ),
    );
  }
}

class _RoutesStrip extends StatefulWidget {
  const _RoutesStrip();

  @override
  State<_RoutesStrip> createState() => _RoutesStripState();
}

class _RoutesStripState extends State<_RoutesStrip> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<RouteProvider>();
      if (provider.routes.isEmpty && !provider.isLoading) {
        provider.fetchRoutes();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<RouteProvider>();
    final routes = provider.routes;

    return SizedBox(
      height: 140,
      child: provider.isLoading && routes.isEmpty
          ? const Center(child: CircularProgressIndicator())
          : routes.isEmpty
              ? Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppTheme.primaryColor.withOpacity(0.06),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.hiking, color: AppTheme.primaryColor),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          context.t('Hand-picked treks & itineraries to explore Nepal'),
                          style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textSm),
                        ),
                      ),
                      TextButton(
                        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RoutesScreen())),
                        child: Text(context.t('Explore')),
                      ),
                    ],
                  ),
                )
              : ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: routes.length,
                  separatorBuilder: (_, __) => const SizedBox(width: 12),
                  itemBuilder: (context, i) => _MiniRouteCard(route: routes[i]),
                ),
    );
  }
}

class _MiniRouteCard extends StatelessWidget {
  final CuratedRouteModel route;

  const _MiniRouteCard({required this.route});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => RouteDetailScreen(routeId: route.id)),
      ),
      child: Container(
        width: 210,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: route.isTrekking
              ? const LinearGradient(
                  colors: [Color(0xFFB45309), Color(0xFF78350F)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                )
              : const LinearGradient(
                  colors: [AppTheme.primaryColor, AppTheme.primaryLight],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Row(
              children: [
                Icon(
                  route.isTrekking ? Icons.hiking : Icons.route,
                  color: Colors.white,
                  size: 18,
                ),
                const SizedBox(width: 6),
                Text(
                  route.isTrekking ? context.t('Trekking') : context.t('Itinerary'),
                  style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textXs, fontWeight: FontWeight.w600),
                ),
                const Spacer(),
                Text(
                  '${route.durationDays} ${context.t('days')}',
                  style: const TextStyle(color: Colors.white, fontSize: AppTheme.textXs, fontWeight: FontWeight.bold),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              route.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: AppTheme.textLg,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              [
                if (route.difficultyLabel.isNotEmpty) route.difficultyLabel,
                if (route.totalDistanceKm != null) '${route.totalDistanceKm!.round()} km',
                if (route.maxAltitudeM != null) '${route.maxAltitudeM} m',
              ].join(' · '),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textXs),
            ),
          ],
        ),
      ),
    );
  }
}

class _MiniOfferCard extends StatelessWidget {
  final OfferModel offer;

  const _MiniOfferCard({required this.offer});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => OfferDetailScreen(offer: offer)),
      ),
      child: Container(
        width: 200,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [AppTheme.primaryColor, Color(0xFF0EA5A0)],
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              offer.label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: AppTheme.textLg,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              offer.title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textXs),
            ),
            const SizedBox(height: 6),
            Text(
              offer.business?.name ?? context.t('Local business'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.white, fontSize: AppTheme.textXs, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickActionItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _QuickActionItem({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: color, size: 28),
          ),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(fontSize: AppTheme.textSm, fontWeight: FontWeight.w500), textAlign: TextAlign.center),
        ],
      ),
    );
  }
}

class _AlertCard extends StatelessWidget {
  final IconData icon;
  final String title, description, severity;
  final Color color;
  final String? time;
  final String? district;

  const _AlertCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.severity,
    required this.color,
    this.time,
    this.district,
  });

  @override
  Widget build(BuildContext context) {
    final meta = [
      if (district != null && district!.isNotEmpty) district!,
      if (time != null) time!,
    ].join(' · ');
    return Card(
      margin: EdgeInsets.zero,
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
          child: Icon(icon, color: color),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(description, maxLines: 1, overflow: TextOverflow.ellipsis),
            if (meta.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                meta,
                style: const TextStyle(
                    color: AppTheme.textSecondary, fontSize: AppTheme.textXs),
              ),
            ],
          ],
        ),
        isThreeLine: meta.isNotEmpty,
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
          child: Text(severity, style: const TextStyle(color: Colors.white, fontSize: AppTheme.textSm)),
        ),
      ),
    );
  }
}

