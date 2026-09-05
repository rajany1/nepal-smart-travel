import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/around_me_provider.dart';
import '../../providers/map_view_provider.dart';

class NearbySummarySheet extends StatelessWidget {
  const NearbySummarySheet({super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AroundMeProvider>(
      builder: (context, provider, _) {
        if (provider.allItems.isEmpty) return const SizedBox.shrink();

        return Container(
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.1),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                children: [
                  const Icon(Icons.explore, size: 18, color: Color(0xFF1565C0)),
                  const SizedBox(width: 8),
                  const Expanded(
                    child: Text(
                      "What's happening around you?",
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                    ),
                  ),
                  Text(
                    '${provider.allItems.length} items',
                    style: TextStyle(fontSize: 11, color: Colors.grey[600]),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  _buildLayerChip(
                    context,
                    '🚨 Emergency',
                    provider.emergencyCount,
                    const Color(0xFFE53935),
                    () => context.read<MapViewProvider>().toggleEmergency(),
                  ),
                  _buildLayerChip(
                    context,
                    '⚠️ Alerts',
                    provider.alertsCount,
                    const Color(0xFFFF9800),
                    () => context.read<MapViewProvider>().toggleAlerts(),
                  ),
                  _buildLayerChip(
                    context,
                    '📋 Updates',
                    provider.reportsCount,
                    const Color(0xFF1E88E5),
                    () => context.read<MapViewProvider>().toggleReports(),
                  ),
                  _buildLayerChip(
                    context,
                    '📍 Places',
                    provider.placesCount,
                    const Color(0xFF43A047),
                    () => context.read<MapViewProvider>().toggleServices(),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildLayerChip(BuildContext context, String label, int count, Color color, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(
          color: color.withOpacity(0.1),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: color.withOpacity(0.3)),
        ),
        child: Column(
          children: [
            Text(
              '$count',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: color),
            ),
            Text(
              label,
              style: TextStyle(fontSize: 9, color: color, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}
