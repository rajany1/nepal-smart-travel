import 'package:flutter/material.dart';
import '../config/themes/app_theme.dart';
import '../core/models/report.dart';

Color _statusColor(String status) {
  switch (status) {
    case 'approved': return AppTheme.successColor;
    case 'rejected': return AppTheme.errorColor;
    default: return AppTheme.warningColor;
  }
}

IconData _statusIcon(String status) {
  switch (status) {
    case 'approved': return Icons.check_circle;
    case 'rejected': return Icons.cancel;
    default: return Icons.access_time;
  }
}

String _formatTimeAgo(DateTime dateTime) {
  final now = DateTime.now();
  final diff = now.difference(dateTime);
  if (diff.inMinutes < 1) return 'Just now';
  if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
  if (diff.inHours < 24) return '${diff.inHours}h ago';
  if (diff.inDays < 7) return '${diff.inDays}d ago';
  return '${dateTime.day}/${dateTime.month}/${dateTime.year}';
}

class ReportCard extends StatelessWidget {
  final ReportModel report;
  final VoidCallback? onTap;
  final bool dense;

  const ReportCard({
    super.key,
    required this.report,
    this.onTap,
    this.dense = false,
  });

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(report.status);
    final statusIcon = _statusIcon(report.status);
    final hasImage = report.imageUrls.isNotEmpty;
    final imageUrl = hasImage ? report.imageUrls.first : null;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: BoxDecoration(
            color: AppTheme.dividerColor.withOpacity(0.15),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              if (hasImage)
                ClipRRect(
                  borderRadius: BorderRadius.circular(6),
                  child: SizedBox(
                    width: dense ? 36 : 44,
                    height: dense ? 36 : 44,
                    child: Image.network(
                      imageUrl!,
                      fit: BoxFit.cover,
                      errorBuilder: (_, __, ___) => Container(
                        color: statusColor.withOpacity(0.1),
                        child: Icon(statusIcon, size: dense ? 16 : 20, color: statusColor),
                      ),
                      loadingBuilder: (_, child, progress) {
                        if (progress == null) return child;
                        return Container(
                          color: AppTheme.dividerColor.withOpacity(0.3),
                          child: const Center(child: SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))),
                        );
                      },
                    ),
                  ),
                )
              else
                Container(
                  width: dense ? 36 : 44,
                  height: dense ? 36 : 44,
                  decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(6)),
                  child: Icon(statusIcon, size: dense ? 16 : 20, color: statusColor),
                ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(report.title, style: TextStyle(fontSize: dense ? AppTheme.textSm : AppTheme.textBase, fontWeight: FontWeight.w500), maxLines: 1, overflow: TextOverflow.ellipsis),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        Text(report.categoryName, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
                        const SizedBox(width: 8),
                        Text('·', style: TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary.withOpacity(0.5))),
                        const SizedBox(width: 8),
                        Text(report.timeAgo.isNotEmpty ? report.timeAgo : _formatTimeAgo(report.createdAt), style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
                      ],
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(color: statusColor.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
                child: Text(report.status.toUpperCase(), style: TextStyle(fontSize: AppTheme.textXs, color: statusColor, fontWeight: FontWeight.w600)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
