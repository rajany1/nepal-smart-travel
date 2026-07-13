import 'package:flutter/material.dart';
import '../config/themes/app_theme.dart';

class InfoTile extends StatelessWidget {
  final Widget? leading;
  final IconData? icon;
  final Color? iconColor;
  final String? label;
  final String value;
  final Widget? trailing;
  final VoidCallback? onTap;
  final bool compact;

  const InfoTile({
    super.key,
    this.leading,
    this.icon,
    this.iconColor,
    this.label,
    required this.value,
    this.trailing,
    this.onTap,
    this.compact = false,
  });

  @override
  Widget build(BuildContext context) {
    final content = Row(
      children: [
        if (leading != null) leading!,
        if (icon != null) ...[
          Icon(icon, size: compact ? 14 : 18, color: iconColor ?? AppTheme.textSecondary),
          const SizedBox(width: 8),
        ],
        Expanded(
          child: label != null
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label!, style: const TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary)),
                    Text(value, style: TextStyle(fontSize: compact ? AppTheme.textSm : AppTheme.textBase, fontWeight: compact ? FontWeight.normal : FontWeight.w500, color: AppTheme.textPrimary)),
                  ],
                )
              : Text(value, style: TextStyle(fontSize: compact ? AppTheme.textSm : AppTheme.textBase, color: AppTheme.textPrimary)),
        ),
        if (trailing != null) trailing!,
        if (onTap != null) const Icon(Icons.chevron_right, size: 18, color: AppTheme.textSecondary),
      ],
    );

    if (onTap != null) {
      return InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: EdgeInsets.symmetric(vertical: compact ? 4 : 8),
          child: content,
        ),
      );
    }

    return Padding(
      padding: EdgeInsets.symmetric(vertical: compact ? 4 : 8),
      child: content,
    );
  }
}
