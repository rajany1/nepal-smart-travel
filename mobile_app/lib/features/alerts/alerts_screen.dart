import 'dart:async';
import "../../core/services/localization_service.dart";
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/alert_provider.dart';
import '../../core/services/location_service.dart';
import '../../core/services/localization_service.dart';
import '../../core/widgets/shimmer_loading.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  Timer? _pollTimer;
  final LocationService _locationService = LocationService();
  // FL-32: keys seen before — only genuinely new items trigger a notification
  final Set<String> _seenItemKeys = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final loc = await _locationService.getCurrentLocation();
      if (!mounted) return;
      final provider = context.read<AlertProvider>();
      if (loc != null) {
        provider.setLocation(loc.latitude, loc.longitude);
      }
      provider.fetchNearby();
      // Seed seen-keys so the first poll doesn't re-notify existing items
      _seenItemKeys.addAll(provider.items.map((i) => '${i.source}:${i.id}'));
    });
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _pollNearby();
    });
  }

  Future<void> _pollNearby() async {
    if (!mounted) return;
    final provider = context.read<AlertProvider>();
    await provider.fetchNearby();
    if (!mounted) return;
    final newItems = provider.items
        .where((i) => _seenItemKeys.add('${i.source}:${i.id}'))
        .toList();
    if (newItems.isNotEmpty) {
      _showNewItemAlert(newItems.first);
    }
  }

  void _showNewItemAlert(NearbyItem item) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text('${item.severityEmoji} ${context.t('New')} ${item.severity.toUpperCase()} ${item.isReport ? context.t('report') : context.t('alert')}: ${item.title}'),
      action: SnackBarAction(label: context.t('View'), onPressed: () {}),
      duration: const Duration(seconds: 6),
      backgroundColor: item.severity == 'critical'
          ? AppTheme.severityCritical
          : item.severity == 'high'
              ? AppTheme.severityHigh
              : AppTheme.severityMedium,
    ));
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AlertProvider>(
      builder: (context, provider, _) {
        return Scaffold(
          appBar: AppBar(
            title: Text(context.t('Live Alerts')),
            actions: [
              IconButton(
                icon: const Icon(Icons.refresh),
                onPressed: () => provider.fetchNearby(),
              ),
              IconButton(
                icon: const Icon(Icons.filter_list),
                onPressed: () {},
              ),
            ],
          ),
          body: provider.isLoading
              ? const _AlertsScreenShimmer()
              : Column(
                  children: [
                    // Alert Stats
                    Container(
                      padding: const EdgeInsets.all(16),
                      color: AppTheme.surfaceColor,
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          _AlertStat(count: '${provider.criticalCount}', label: context.t('Critical'), color: AppTheme.severityCritical),
                          _AlertStat(count: '${provider.highCount}', label: context.t('High'), color: AppTheme.severityHigh),
                          _AlertStat(count: '${provider.mediumCount}', label: context.t('Medium'), color: AppTheme.severityMedium),
                          _AlertStat(count: '${provider.infoCount}', label: context.t('Info'), color: AppTheme.severityInfo),
                        ],
                      ),
                    ),
                    const Divider(height: 1),
                    // Filter Chips
                    Container(
                      height: 48,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      child: ListView(
                        scrollDirection: Axis.horizontal,
                        children: [
                          _FilterChip(label: context.t('All'), selected: provider.activeFilter == null || provider.activeFilter == 'all', onSelected: () => provider.setFilter(null)),
                          _FilterChip(label: context.t('Critical'), selected: provider.activeFilter == 'critical', onSelected: () => provider.setFilter('critical')),
                          _FilterChip(label: context.t('High'), selected: provider.activeFilter == 'high', onSelected: () => provider.setFilter('high')),
                          _FilterChip(label: context.t('Medium'), selected: provider.activeFilter == 'medium', onSelected: () => provider.setFilter('medium')),
                          _FilterChip(label: context.t('Info'), selected: provider.activeFilter == 'info', onSelected: () => provider.setFilter('info')),
                        ],
                      ),
                    ),
                    // Alert List
                    Expanded(
                      child: provider.filteredItems.isEmpty
                          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.notifications_off, size: 48, color: AppTheme.textSecondary), const SizedBox(height: 12), Text(context.t('No alerts found'), style: const TextStyle(color: AppTheme.textSecondary)), const SizedBox(height: 4), Text(context.t('Everything looks clear in your area'), style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm))]))
                          : ListView.builder(
                              padding: const EdgeInsets.all(12),
                              itemCount: provider.filteredItems.length,
                              itemBuilder: (context, index) {
                                final item = provider.filteredItems[index];
                                final severityColor = item.severity == 'critical' ? AppTheme.severityCritical :
                                                       item.severity == 'high' ? AppTheme.severityHigh :
                                                       item.severity == 'medium' ? AppTheme.severityMedium :
                                                       AppTheme.severityInfo;
                                return Card(
                                  margin: const EdgeInsets.only(bottom: 8),
                                  child: InkWell(
                                    borderRadius: BorderRadius.circular(12),
                                    onTap: () {},
                                    child: Padding(
                                      padding: const EdgeInsets.all(12),
                                      child: Row(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.all(8),
                                            decoration: BoxDecoration(
                                              color: severityColor.withOpacity(0.1),
                                              borderRadius: BorderRadius.circular(8),
                                            ),
                                            child: Text(item.severityEmoji, style: const TextStyle(fontSize: AppTheme.text2xl)),
                                          ),
                                          const SizedBox(width: 12),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              children: [
                                                Row(
                                                  children: [
                                                    if (item.isReport)
                                                      Container(
                                                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                                        margin: const EdgeInsets.only(right: 4),
                                                        decoration: BoxDecoration(
                                                          color: severityColor.withOpacity(0.2),
                                                          borderRadius: BorderRadius.circular(4),
                                                        ),
                                                        child: Text(context.t('REPORT'), style: TextStyle(fontSize: AppTheme.textXs, fontWeight: FontWeight.w600, color: severityColor)),
                                                      ),
                                                    Expanded(
                                                      child: Text(item.title, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase)),
                                                    ),
                                                    Container(
                                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                                      decoration: BoxDecoration(
                                                        color: severityColor,
                                                        borderRadius: BorderRadius.circular(8),
                                                      ),
                                                      child: Text(item.severity.toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: AppTheme.textXs, fontWeight: FontWeight.w600)),
                                                    ),
                                                  ],
                                                ),
                                                const SizedBox(height: 4),
                                                Text(item.description, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), maxLines: 2, overflow: TextOverflow.ellipsis),
                                                const SizedBox(height: 6),
                                                Row(
                                                  children: [
                                                    if (item.affectedDistrict != null) ...[
                                                      Icon(Icons.location_on, size: 12, color: AppTheme.textSecondary),
                                                      const SizedBox(width: 4),
                                                      Text(item.affectedDistrict!, style: const TextStyle(color: AppTheme.textSecondary, fontSize: 11)),
                                                      const Spacer(),
                                                    ],
                                                    Icon(Icons.access_time, size: 12, color: AppTheme.textSecondary),
                                                    const SizedBox(width: 4),
                                                    Text(_timeAgo(item.createdAt), style: const TextStyle(color: AppTheme.textSecondary, fontSize: 11)),
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
                              },
                            ),
                    ),
                  ],
                ),
        );
      },
    );
  }

  String _timeAgo(DateTime date) {
    final diff = DateTime.now().difference(date);
    if (diff.inMinutes < 1) return context.t('Just now');
    if (diff.inMinutes < 60) return '${diff.inMinutes} ${context.t('min ago')}';
    if (diff.inHours < 24) return '${diff.inHours} ${diff.inHours > 1 ? context.t('hours ago') : context.t('hour ago')}';
    return '${diff.inDays} ${diff.inDays > 1 ? context.t('days ago') : context.t('day ago')}';
  }
}

class _AlertStat extends StatelessWidget {
  final String count, label;
  final Color color;

  const _AlertStat({required this.count, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(count, style: TextStyle(fontSize: AppTheme.text3xl, fontWeight: FontWeight.bold, color: color)),
        Text(label, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
      ],
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onSelected;

  const _FilterChip({required this.label, required this.selected, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
      child: ChoiceChip(
        label: Text(label, style: TextStyle(fontSize: AppTheme.textSm, color: selected ? Colors.white : AppTheme.textPrimary)),
        selected: selected,
        selectedColor: AppTheme.primaryColor,
        backgroundColor: AppTheme.surfaceColor,
        side: BorderSide(color: selected ? AppTheme.primaryColor : AppTheme.dividerColor),
        onSelected: (_) => onSelected(),
      ),
    );
  }
}

// ============ ALERTS SHIMMER ============
class _AlertsScreenShimmer extends StatelessWidget {
  const _AlertsScreenShimmer();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const AlertStatsShimmer(),
        const Divider(height: 1),
        const SizedBox(height: 8),
        const Expanded(
          child: AlertCardShimmer(count: 6),
        ),
      ],
    );
  }
}
