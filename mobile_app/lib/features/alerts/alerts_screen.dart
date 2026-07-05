import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/alert_provider.dart';
import '../../core/services/location_service.dart';
import '../../core/widgets/shimmer_loading.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  Timer? _pollTimer;
  final LocationService _locationService = LocationService();

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
    });
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _pollNearby();
    });
  }

  Future<void> _pollNearby() async {
    if (!mounted) return;
    final provider = context.read<AlertProvider>();
    final oldItems = List<NearbyItem>.from(provider.items);
    await provider.fetchNearby();
    if (!mounted) return;
    final newItems = provider.items
        .where((i) => !oldItems.any((o) => o.id == i.id && o.source == i.source))
        .toList();
    if (newItems.isNotEmpty) {
      _showNewItemAlert(newItems.first);
    }
  }

  void _showNewItemAlert(NearbyItem item) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text('${item.severityEmoji} New ${item.severity.toUpperCase()} ${item.isReport ? "report" : "alert"}: ${item.title}'),
      action: SnackBarAction(label: 'View', onPressed: () {}),
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
            title: const Text('Live Alerts'),
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
                          _AlertStat(count: '${provider.criticalCount}', label: 'Critical', color: AppTheme.severityCritical),
                          _AlertStat(count: '${provider.highCount}', label: 'High', color: AppTheme.severityHigh),
                          _AlertStat(count: '${provider.mediumCount}', label: 'Medium', color: AppTheme.severityMedium),
                          _AlertStat(count: '${provider.infoCount}', label: 'Info', color: AppTheme.severityInfo),
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
                          _FilterChip(label: 'All', selected: provider.activeFilter == null || provider.activeFilter == 'all', onSelected: () => provider.setFilter(null)),
                          _FilterChip(label: 'Critical', selected: provider.activeFilter == 'critical', onSelected: () => provider.setFilter('critical')),
                          _FilterChip(label: 'High', selected: provider.activeFilter == 'high', onSelected: () => provider.setFilter('high')),
                          _FilterChip(label: 'Medium', selected: provider.activeFilter == 'medium', onSelected: () => provider.setFilter('medium')),
                          _FilterChip(label: 'Info', selected: provider.activeFilter == 'info', onSelected: () => provider.setFilter('info')),
                        ],
                      ),
                    ),
                    // Alert List
                    Expanded(
                      child: provider.filteredItems.isEmpty
                          ? const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(Icons.notifications_off, size: 48, color: AppTheme.textSecondary), SizedBox(height: 12), Text('No alerts found', style: TextStyle(color: AppTheme.textSecondary)), SizedBox(height: 4), Text('Everything looks clear in your area', style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm))]))
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
                                                        child: Text('REPORT', style: TextStyle(fontSize: AppTheme.textXs, fontWeight: FontWeight.w600, color: severityColor)),
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
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
    if (diff.inHours < 24) return '${diff.inHours} hour${diff.inHours > 1 ? 's' : ''} ago';
    return '${diff.inDays} day${diff.inDays > 1 ? 's' : ''} ago';
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
