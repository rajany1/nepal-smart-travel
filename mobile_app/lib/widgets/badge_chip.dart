import 'package:flutter/material.dart';
import '../config/themes/app_theme.dart';

class BadgeChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool unlocked;
  final Color? unlockedColor;
  final VoidCallback? onTap;
  final String? tooltip;

  const BadgeChip({
    super.key,
    required this.icon,
    required this.label,
    this.unlocked = true,
    this.unlockedColor,
    this.onTap,
    this.tooltip,
  });

  @override
  Widget build(BuildContext context) {
    final chipColor = unlockedColor ?? AppTheme.secondaryColor;

    Widget chip = Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: unlocked ? chipColor.withOpacity(0.1) : AppTheme.dividerColor.withOpacity(0.5),
        borderRadius: BorderRadius.circular(20),
        border: unlocked ? Border.all(color: chipColor.withOpacity(0.3)) : null,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: unlocked ? chipColor : AppTheme.textSecondary),
          const SizedBox(width: 4),
          Text(
            label.toUpperCase(),
            style: TextStyle(
              fontSize: AppTheme.textXs,
              fontWeight: FontWeight.w600,
              color: unlocked ? chipColor : AppTheme.textSecondary,
            ),
          ),
        ],
      ),
    );

    if (tooltip != null) {
      chip = Tooltip(message: tooltip!, child: chip);
    }

    if (onTap != null) {
      chip = InkWell(onTap: onTap, borderRadius: BorderRadius.circular(20), child: chip);
    }

    return chip;
  }
}
