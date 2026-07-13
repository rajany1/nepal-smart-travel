import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/location_service.dart';
import '../../core/api/api_client.dart';
import '../../providers/auth_provider.dart';
import '../../providers/alert_provider.dart';
import '../../providers/place_provider.dart';

import '../places/nearby_places_screen.dart';
import '../reporting/reports_list_screen.dart';
import '../emergency/emergency_screen.dart';
import '../assistant/assistant_screen.dart';
import '../profile/profile_screen.dart';
import '../alerts/alerts_screen.dart';
import '../leaderboard/leaderboard_screen.dart';
import '../sponsors/sponsors_screen.dart';
import '../store/store_screen.dart';
import '../bookings/my_bookings_screen.dart';
import '../subscriptions/subscription_plans_screen.dart';
import '../../widgets/ad_banner_carousel.dart';

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
    setState(() => _currentIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    final availableTabs = <Map<String, dynamic>>[
      {
        'icon': Icons.explore,
        'label': 'Explore',
        'screen': const _ExploreTab(),
      },
      {
        'icon': Icons.place,
        'label': 'Nearby',
        'screen': const NearbyPlacesScreen(),
      },
      {
        'icon': Icons.assignment,
        'label': 'Reports',
        'screen': const ReportsListScreen(),
      },
      {
        'icon': Icons.emergency,
        'label': 'Emergency',
        'screen': const EmergencyScreen(),
      },
      {
        'icon': Icons.person,
        'label': 'Profile',
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

class _ExploreTab extends StatelessWidget {
  const _ExploreTab();

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final alerts = context.watch<AlertProvider>().items;
    final places = context.watch<PlaceProvider>().places;
    final recentAlerts = alerts.take(3).toList();
    final highlightPlaces = places.take(5).toList();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Nepal Smart Travel'),
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
            const AdBannerCarousel(),
            const SizedBox(height: 16),

            // Welcome Card
            Container(
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
                  Text(
                    'Welcome${user != null ? ', ${user.name.split(' ').first}' : ''}!',
                    style: const TextStyle(
color: AppTheme.surfaceColor,
                      fontSize: AppTheme.text3xl,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Discover Nepal\'s hidden gems, real-time travel conditions, and community insights',
                    style: TextStyle(color: Colors.white70, fontSize: AppTheme.textBase),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Quick Actions Grid
            Text('Quick Actions', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            GridView.count(
              crossAxisCount: 4,
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 0.85,
              children: [
                _QuickActionItem(icon: Icons.emoji_events, label: 'Leaderboard', color: AppTheme.secondaryColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LeaderboardScreen()))),
                _QuickActionItem(icon: Icons.add_circle, label: 'New Report', color: AppTheme.warningColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ReportsListScreen()))),
                _QuickActionItem(icon: Icons.emergency, label: 'SOS', color: AppTheme.errorColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const EmergencyScreen()))),
                _QuickActionItem(icon: Icons.chat, label: 'AI Help', color: AppTheme.infoColor, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AssistantScreen()))),
              ],
            ),
            const SizedBox(height: 4),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MyBookingsScreen())),
                icon: const Icon(Icons.book_online, size: 18),
                label: const Text('My Bookings'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppTheme.primaryColor,
                  side: const BorderSide(color: AppTheme.primaryColor),
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
            ),
            const SizedBox(height: 24),

            // Live Alerts
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Live Alerts', style: Theme.of(context).textTheme.titleLarge),
                TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AlertsScreen())), child: const Text('See All')),
              ],
            ),
            const SizedBox(height: 8),
            if (recentAlerts.isEmpty)
              const Card(
                margin: EdgeInsets.zero,
                child: ListTile(
                  leading: Icon(Icons.check_circle, color: AppTheme.successColor),
                  title: Text('No active alerts'),
                  subtitle: Text('All clear in your area'),
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
                ),
              )),

            const SizedBox(height: 24),

            // Sponsors
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Our Sponsors', style: Theme.of(context).textTheme.titleLarge),
                TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SponsorsScreen())), child: const Text('See All')),
              ],
            ),
            const SizedBox(height: 8),
            const _SponsorsLogoStrip(),
            const SizedBox(height: 24),

            // Nearby Highlights
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text('Nearby Highlights', style: Theme.of(context).textTheme.titleLarge),
                TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NearbyPlacesScreen())), child: const Text('View All')),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 160,
              child: highlightPlaces.isEmpty
                  ? const Center(child: Text('No nearby places found'))
                  : ListView.builder(
                      scrollDirection: Axis.horizontal,
                      itemCount: highlightPlaces.length,
                      itemBuilder: (context, index) {
                        final place = highlightPlaces[index];
                        return _PlaceCard(
                          name: place.name,
                          category: place.category ?? 'Place',
                          rating: place.averageRating ?? 0,
                          image: Icons.place,
                        );
                      },
                    ),
            ),

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
                          Text('Level ${user.currentLevel} - ${user.levelName}', style: const TextStyle(fontWeight: FontWeight.bold)),
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
                          Text('${user.totalXp} XP \u2022 ${user.approvedReports} approved reports', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
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

  const _AlertCard({required this.icon, required this.title, required this.description, required this.severity, required this.color});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: EdgeInsets.zero,
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
          child: Icon(icon, color: color),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
        subtitle: Text(description, maxLines: 1, overflow: TextOverflow.ellipsis),
        trailing: Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
          child: Text(severity, style: const TextStyle(color: Colors.white, fontSize: AppTheme.textSm)),
        ),
      ),
    );
  }
}

class _PlaceCard extends StatelessWidget {
  final String name, category;
  final double rating;
  final IconData image;

  const _PlaceCard({required this.name, required this.category, required this.rating, required this.image});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 140,
      margin: const EdgeInsets.only(right: 12),
      child: Card(
        margin: EdgeInsets.zero,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: AppTheme.primaryLight.withOpacity(0.1),
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
                ),
                child: Icon(image, size: 48, color: AppTheme.primaryColor),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase), maxLines: 1, overflow: TextOverflow.ellipsis),
                  Text(category, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                  Row(
                    children: [
                      const Icon(Icons.star, size: 14, color: AppTheme.secondaryColor),
                      const SizedBox(width: 2),
                      Text(rating.toStringAsFixed(1), style: const TextStyle(fontSize: AppTheme.textSm)),
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

class _SponsorsLogoStrip extends StatefulWidget {
  const _SponsorsLogoStrip();
  @override
  State<_SponsorsLogoStrip> createState() => _SponsorsLogoStripState();
}

class _SponsorsLogoStripState extends State<_SponsorsLogoStrip> {
  final _api = ApiClient.instance;
  List<dynamic> _sponsors = [];
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await _api.getSponsors();
      _sponsors = res.data['data'] as List<dynamic>;
    } catch (_) {}
    if (mounted) setState(() { _loaded = true; });
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _sponsors.isEmpty) return const SizedBox.shrink();
    return SizedBox(
      height: 90,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        itemCount: _sponsors.length,
        itemBuilder: (_, i) {
          final s = _sponsors[i] as Map<String, dynamic>;
          return GestureDetector(
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SponsorsScreen())),
            child: Container(
              width: 100,
              margin: const EdgeInsets.only(right: 10),
              child: Card(
                margin: EdgeInsets.zero,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 18,
                      backgroundColor: Colors.purple.shade50,
                      child: Text(s['name']?[0]?.toUpperCase() ?? '?', style: TextStyle(fontSize: 16, color: Colors.purple.shade700, fontWeight: FontWeight.bold)),
                    ),
                    const SizedBox(height: 4),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      child: Text(s['name'] ?? '', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w500), maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
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
