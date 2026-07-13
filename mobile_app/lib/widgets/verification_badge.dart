import 'package:flutter/material.dart';
import '../config/themes/app_theme.dart';

class VerificationBadge extends StatelessWidget {
  final String tick;
  final bool compact;

  const VerificationBadge({
    super.key,
    required this.tick,
    this.compact = false,
  });

  static Color tickColorFromString(String tick) {
    switch (tick) {
      case 'gray': return AppTheme.grayTick;
      case 'green': return AppTheme.greenTick;
      case 'blue': return AppTheme.blueTick;
      case 'gold': return AppTheme.goldTick;
      case 'diamond': return AppTheme.diamondTick;
      default: return AppTheme.grayTick;
    }
  }

  Color get tickColor => tickColorFromString(tick);

  String get tickLabel => tick.toUpperCase();

  @override
  Widget build(BuildContext context) {
    if (compact) {
      return Container(
        padding: const EdgeInsets.all(2),
        decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
        child: Icon(Icons.verified, size: 18, color: tickColor),
      );
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(color: tickColor.withOpacity(0.1), shape: BoxShape.circle),
            child: Icon(Icons.verified, color: tickColor, size: 28),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('$tickLabel TICK', style: TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase, color: tickColor)),
                Text('${tickLabel.toLowerCase()} level verification', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  static Color colorFor(String tick) {
    switch (tick) {
      case 'gray': return AppTheme.grayTick;
      case 'green': return AppTheme.greenTick;
      case 'blue': return AppTheme.blueTick;
      case 'gold': return AppTheme.goldTick;
      case 'diamond': return AppTheme.diamondTick;
      default: return AppTheme.grayTick;
    }
  }
}

class LevelBadge extends StatelessWidget {
  final int level;

  const LevelBadge({super.key, required this.level});

  Color get levelColor {
    if (level <= 5) return AppTheme.explorerColor;
    if (level <= 15) return AppTheme.contributorColor;
    if (level <= 30) return AppTheme.trustedLocalColor;
    if (level <= 50) return AppTheme.regionalGuideColor;
    if (level <= 100) return AppTheme.communityExpertColor;
    return AppTheme.communityExpertColor;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: levelColor.withOpacity(0.2), borderRadius: BorderRadius.circular(16)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.stars, size: 14, color: levelColor),
          const SizedBox(width: 4),
          Text('Level $level', style: TextStyle(color: levelColor, fontSize: AppTheme.textSm, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
