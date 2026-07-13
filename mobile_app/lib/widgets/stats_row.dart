import 'package:flutter/material.dart';
import '../config/themes/app_theme.dart';

class StatItem {
  final IconData icon;
  final String value;
  final String label;
  final Color color;

  const StatItem({
    required this.icon,
    required this.value,
    required this.label,
    required this.color,
  });
}

class StatsRow extends StatelessWidget {
  final List<StatItem> stats;
  final int crossAxisCount;

  const StatsRow({
    super.key,
    required this.stats,
    this.crossAxisCount = 4,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: stats.take(crossAxisCount).map((stat) => _buildStatItem(stat)).toList(),
        ),
        if (stats.length > crossAxisCount) ...[
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: stats.skip(crossAxisCount).map((stat) => _buildStatItem(stat)).toList(),
          ),
        ],
      ],
    );
  }

  Widget _buildStatItem(StatItem stat) {
    return Column(
      children: [
        Icon(stat.icon, color: stat.color, size: 22),
        const SizedBox(height: 2),
        Text(stat.value, style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase, color: stat.color)),
        Text(stat.label, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
      ],
    );
  }
}
