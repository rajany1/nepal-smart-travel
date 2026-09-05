import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/around_me_provider.dart';
import '../../providers/report_provider.dart';
import '../../core/services/location_service.dart';

class AroundMeScreen extends StatefulWidget {
  const AroundMeScreen({super.key});

  @override
  State<AroundMeScreen> createState() => _AroundMeScreenState();
}

class _AroundMeScreenState extends State<AroundMeScreen> {
  Timer? _pollTimer;
  final LocationService _locationService = LocationService();
  double? _lat;
  double? _lng;

  @override
  void initState() {
    super.initState();
    _loadData();
    _pollTimer = Timer.periodic(const Duration(seconds: 60), (_) => _loadData());
  }

  Future<void> _loadData() async {
    if (!mounted) return;
    final loc = await _locationService.getCurrentLocation();
    if (!mounted || loc == null) return;
    _lat = loc.latitude;
    _lng = loc.longitude;
    context.read<AroundMeProvider>().fetchAroundMe(_lat!, _lng!);
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6FA),
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFEC4899).withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.explore, color: Color(0xFFEC4899), size: 22),
            ),
            const SizedBox(width: 12),
            const Text('Around You', style: TextStyle(fontWeight: FontWeight.w600)),
          ],
        ),
        backgroundColor: Colors.white,
        foregroundColor: AppTheme.textPrimary,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: _loadData,
          ),
        ],
      ),
      body: Consumer<AroundMeProvider>(
        builder: (context, provider, _) {
          if (provider.isLoading && provider.allItems.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null && provider.allItems.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.error_outline, size: 48, color: Colors.grey),
                  const SizedBox(height: 16),
                  Text(provider.error!, style: const TextStyle(color: Colors.grey)),
                  const SizedBox(height: 16),
                  ElevatedButton(onPressed: _loadData, child: const Text('Retry')),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: _loadData,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _buildSummaryCard(provider),
                const SizedBox(height: 16),
                if (provider.emergency.isNotEmpty) ...[
                  _buildSectionHeader('Emergency', Icons.warning_amber_rounded, const Color(0xFFE53935), provider.emergencyCount),
                  ...provider.emergency.map((item) => _buildItemCard(item)),
                  const SizedBox(height: 16),
                ],
                if (provider.alerts.isNotEmpty) ...[
                  _buildSectionHeader('Alerts', Icons.notifications_active, const Color(0xFFFF9800), provider.alertsCount),
                  ...provider.alerts.map((item) => _buildItemCard(item)),
                  const SizedBox(height: 16),
                ],
                if (provider.reports.isNotEmpty) ...[
                  _buildSectionHeader('Local Updates', Icons.article, const Color(0xFF1E88E5), provider.reportsCount),
                  ...provider.reports.map((item) => _buildItemCard(item)),
                  const SizedBox(height: 16),
                ],
                if (provider.places.isNotEmpty) ...[
                  _buildSectionHeader('Useful Nearby', Icons.place, const Color(0xFF43A047), provider.placesCount),
                  ...provider.places.map((item) => _buildItemCard(item)),
                ],
                if (provider.allItems.isEmpty)
                  Center(
                    child: Padding(
                      padding: const EdgeInsets.only(top: 80),
                      child: Column(
                        children: [
                          Icon(Icons.explore, size: 64, color: Colors.grey[400]),
                          const SizedBox(height: 16),
                          Text(
                            'Nothing reported nearby yet',
                            style: TextStyle(fontSize: 16, color: Colors.grey[600]),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Be the first to report something!',
                            style: TextStyle(fontSize: 13, color: Colors.grey[500]),
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _buildSummaryCard(AroundMeProvider provider) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [AppTheme.primaryColor, AppTheme.primaryColor.withOpacity(0.8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: AppTheme.primaryColor.withOpacity(0.3),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.explore, color: Colors.white, size: 22),
              SizedBox(width: 8),
              Text(
                "What's happening around you?",
                style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildSummaryItem('🚨', '${provider.emergencyCount}', 'Emergency', Colors.red[100]!),
              _buildSummaryItem('⚠️', '${provider.alertsCount}', 'Alerts', Colors.orange[100]!),
              _buildSummaryItem('📋', '${provider.reportsCount}', 'Updates', Colors.blue[100]!),
              _buildSummaryItem('📍', '${provider.placesCount}', 'Places', Colors.green[100]!),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryItem(String emoji, String count, String label, Color bgColor) {
    return Column(
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.white.withOpacity(0.2),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Text(emoji, style: const TextStyle(fontSize: 20)),
        ),
        const SizedBox(height: 4),
        Text(count, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w600)),
        Text(label, style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 10)),
      ],
    );
  }

  Widget _buildSectionHeader(String title, IconData icon, Color color, int count) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(
              color: color.withOpacity(0.15),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(width: 10),
          Text(title, style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: color)),
          const Spacer(),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
              color: color.withOpacity(0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text('$count', style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
          ),
        ],
      ),
    );
  }

  Widget _buildItemCard(AroundMeItem item) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildItemIcon(item),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        item.title,
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    if (item.timeState != null) _buildTimeStateBadge(item),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  item.description,
                  style: TextStyle(color: Colors.grey[600], fontSize: 12),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    if (item.distanceKm != null) ...[
                      Icon(Icons.near_me, size: 12, color: Colors.grey[500]),
                      const SizedBox(width: 3),
                      Text(
                        item.distanceKm! < 1
                            ? '${(item.distanceKm! * 1000).round()}m'
                            : '${item.distanceKm!.toStringAsFixed(1)}km',
                        style: TextStyle(color: Colors.grey[500], fontSize: 11),
                      ),
                      const SizedBox(width: 10),
                    ],
                    if (item.timeAgo != null) ...[
                      Icon(Icons.access_time, size: 12, color: Colors.grey[500]),
                      const SizedBox(width: 3),
                      Text(item.timeAgo!, style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                    ],
                    const Spacer(),
                    if (item.type == 'report' && item.helpfulCount != null && item.helpfulCount! > 0)
                      _buildConfirmChip(item),
                    if (item.commentsCount != null && item.commentsCount! > 0) ...[
                      const SizedBox(width: 8),
                      Text('💬 ${item.commentsCount}', style: TextStyle(color: Colors.grey[500], fontSize: 11)),
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

  Widget _buildConfirmChip(AroundMeItem item) {
    return GestureDetector(
      onTap: () async {
        final provider = context.read<ReportProvider>();
        final loc = await LocationService().getCurrentLocation();
        await provider.confirmReport(
          item.id.toString(),
          lat: loc?.latitude,
          lng: loc?.longitude,
        );
      },
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
        decoration: BoxDecoration(
          color: const Color(0xFF43A047).withOpacity(0.12),
          borderRadius: BorderRadius.circular(6),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.check_circle_outline, size: 12, color: Color(0xFF43A047)),
            const SizedBox(width: 3),
            Text(
              '👍 ${item.helpfulCount}',
              style: const TextStyle(color: Color(0xFF43A047), fontSize: 11, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildItemIcon(AroundMeItem item) {
    Color bgColor;
    IconData icon;

    if (item.type == 'alert') {
      bgColor = const Color(0xFFFFF3E0);
      icon = Icons.warning_amber_rounded;
    } else if (item.type == 'place') {
      bgColor = const Color(0xFFE8F5E9);
      icon = Icons.place;
    } else {
      bgColor = const Color(0xFFE3F2FD);
      icon = Icons.article;
    }

    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Icon(icon, size: 20, color: bgColor.computeLuminance() > 0.5 ? Colors.grey[700] : Colors.white),
    );
  }

  Widget _buildTimeStateBadge(AroundMeItem item) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: item.timeStateColor.withOpacity(0.15),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(
        item.timeStateLabel,
        style: TextStyle(
          color: item.timeStateColor,
          fontSize: 9,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
