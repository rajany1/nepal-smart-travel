import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../../config/themes/app_theme.dart';
import '../../../providers/store_provider.dart';
import '../../places/nearby_places_screen.dart';

class StoreOrderCard extends StatelessWidget {
  final Purchase purchase;

  const StoreOrderCard({super.key, required this.purchase});

  Color get _statusColor {
    switch (purchase.status) {
      case 'completed':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'cancelled':
        return Colors.red;
      case 'refunded':
        return Colors.purple;
      default:
        return Colors.grey;
    }
  }

  IconData get _statusIcon {
    switch (purchase.status) {
      case 'completed':
        return Icons.check_circle;
      case 'pending':
        return Icons.hourglass_empty;
      case 'cancelled':
        return Icons.cancel;
      case 'refunded':
        return Icons.undo;
      default:
        return Icons.help;
    }
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor;
    final statusIcon = _statusIcon;
    final date = purchase.createdAt.length >= 10 ? purchase.createdAt.substring(0, 10) : purchase.createdAt;
    final sponsor = purchase.shopItem?.sponsor;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.dividerColor),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(statusIcon, color: statusColor, size: 22),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        purchase.shopItem?.name ?? 'Item #${purchase.shopItemId}',
                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                      ),
                      const SizedBox(height: 2),
                      Row(
                        children: [
                          Icon(Icons.star, size: 12, color: AppTheme.secondaryColor),
                          const SizedBox(width: 3),
                          Text('${purchase.xpSpent} XP',
                              style: TextStyle(color: AppTheme.secondaryColor, fontSize: 12, fontWeight: FontWeight.w500)),
                          const SizedBox(width: 8),
                          Text(date,
                              style: TextStyle(color: Colors.grey[400], fontSize: 11)),
                        ],
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    purchase.status[0].toUpperCase() + purchase.status.substring(1),
                    style: TextStyle(color: statusColor, fontSize: 11, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ),
            if (purchase.code != null && purchase.status == 'completed') ...[
              const SizedBox(height: 10),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: purchase.codeStatus == 'consumed' ? Colors.grey[100] : Colors.grey[50],
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.grey[200]!),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text('Redeem Code', style: TextStyle(color: Colors.grey[500], fontSize: 10)),
                              if (purchase.codeStatus == 'applied')
                                Container(
                                  margin: const EdgeInsets.only(left: 6),
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                  decoration: BoxDecoration(
                                    color: Colors.blue.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text('Applied to Booking',
                                      style: TextStyle(color: Colors.blue[700], fontSize: 8, fontWeight: FontWeight.w600)),
                                ),
                              if (purchase.codeStatus == 'consumed')
                                Container(
                                  margin: const EdgeInsets.only(left: 6),
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                                  decoration: BoxDecoration(
                                    color: Colors.green.withValues(alpha: 0.1),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text('Consumed',
                                      style: TextStyle(color: Colors.green[700], fontSize: 8, fontWeight: FontWeight.w600)),
                                ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(purchase.code!,
                              style: TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 16,
                                  letterSpacing: 1.5,
                                  color: purchase.codeStatus == 'consumed' ? Colors.grey[400] : Colors.black)),
                        ],
                      ),
                    ),
                    if (purchase.codeStatus == 'assigned')
                      GestureDetector(
                        onTap: () {
                          Clipboard.setData(ClipboardData(text: purchase.code!));
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Code copied!'), duration: Duration(seconds: 1)),
                          );
                        },
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Icon(Icons.copy, size: 18, color: AppTheme.primaryColor),
                        ),
                      ),
                  ],
                ),
              ),
            ],
            Row(
              children: [
                if (sponsor != null) ...[
                  const SizedBox(height: 8),
                  if (sponsor.logo != null)
                    ClipRRect(
                      borderRadius: BorderRadius.circular(4),
                      child: Image.network(sponsor.logo!, width: 14, height: 14, fit: BoxFit.cover),
                    )
                  else
                    Icon(Icons.business, size: 14, color: Colors.grey[400]),
                  const SizedBox(width: 4),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(sponsor.name, style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                      if (sponsor.address != null)
                        Text(sponsor.address!, style: TextStyle(color: Colors.grey[400], fontSize: 9)),
                    ],
                  ),
                  if (sponsor.hasLocation) ...[
                    const SizedBox(width: 8),
                    GestureDetector(
                      onTap: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => NearbyPlacesScreen.withDestination(
                            destinationLat: sponsor.latitude!,
                            destinationLng: sponsor.longitude!,
                            destinationName: sponsor.name,
                          ),
                        ),
                      ),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.blue.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.directions, size: 12, color: Colors.blue[700]),
                            const SizedBox(width: 3),
                            Text('Directions',
                                style: TextStyle(color: Colors.blue[700], fontSize: 10, fontWeight: FontWeight.w500)),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}
