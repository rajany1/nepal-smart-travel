import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/themes/app_theme.dart';
import '../../../providers/store_provider.dart';
import '../../../providers/auth_provider.dart';
import '../../places/nearby_places_screen.dart';

class StoreItemCard extends StatelessWidget {
  final ShopItem item;

  const StoreItemCard({super.key, required this.item});

  Color get _rewardColor {
    switch (item.rewardType) {
      case 'discount':
        return Colors.blue;
      case 'free_item':
        return Colors.green;
      case 'voucher':
        return Colors.purple;
      case 'special_offer':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  IconData get _rewardIcon {
    switch (item.rewardType) {
      case 'discount':
        return Icons.percent;
      case 'free_item':
        return Icons.card_giftcard;
      case 'voucher':
        return Icons.confirmation_number;
      case 'special_offer':
        return Icons.local_offer;
      default:
        return Icons.card_giftcard;
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final user = auth.user;
    final userLevel = user?.currentLevel ?? 1;
    final userXp = user?.totalXp ?? 0;
    final meetsLevel = userLevel >= item.minLevel;
    final meetsXp = userXp >= item.priceXp;
    final canAfford = meetsLevel && meetsXp;
    final store = context.watch<StoreProvider>();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.dividerColor),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              width: 5,
              decoration: BoxDecoration(
                color: _rewardColor,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(14),
                  bottomLeft: Radius.circular(14),
                ),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Container(
                          width: 44,
                          height: 44,
                          decoration: BoxDecoration(
                            color: _rewardColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Icon(item.iconData, color: _rewardColor, size: 22),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Expanded(
                                    child: Text(item.name,
                                        style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: _rewardColor.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: Text(item.rewardLabel,
                                        style: TextStyle(color: _rewardColor, fontSize: 10, fontWeight: FontWeight.w600)),
                                  ),
                                ],
                              ),
                              if (item.description != null && item.description!.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(item.description!,
                                    style: TextStyle(color: Colors.grey[500], fontSize: 12),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis),
                              ],
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        if (item.sponsor != null) ...[
                          if (item.sponsor!.logo != null)
                            ClipRRect(
                              borderRadius: BorderRadius.circular(4),
                              child: Image.network(item.sponsor!.logo!, width: 16, height: 16, fit: BoxFit.cover),
                            )
                          else
                            Icon(Icons.business, size: 14, color: Colors.grey[400]),
                          const SizedBox(width: 4),
                          Flexible(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(item.sponsor!.name,
                                    style: TextStyle(color: Colors.grey[600], fontSize: 11), overflow: TextOverflow.ellipsis),
                                if (item.sponsor!.address != null)
                                  Text(item.sponsor!.address!,
                                      style: TextStyle(color: Colors.grey[400], fontSize: 9), overflow: TextOverflow.ellipsis),
                              ],
                            ),
                          ),
                          const SizedBox(width: 8),
                          if (item.sponsor!.hasLocation)
                            GestureDetector(
                              onTap: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => NearbyPlacesScreen.withDestination(
                                    destinationLat: item.sponsor!.latitude!,
                                    destinationLng: item.sponsor!.longitude!,
                                    destinationName: item.sponsor!.name,
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
                        const Spacer(),
                        if (item.usageLimitPerUser != null)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.grey.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text('${item.usageLimitPerUser}x',
                                style: TextStyle(color: Colors.grey[500], fontSize: 10)),
                          ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: AppTheme.secondaryColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.star, size: 14, color: AppTheme.secondaryColor),
                              const SizedBox(width: 4),
                              Text('${item.priceXp} XP',
                                  style: const TextStyle(fontWeight: FontWeight.bold, color: AppTheme.secondaryColor, fontSize: 13)),
                            ],
                          ),
                        ),
                        const SizedBox(width: 8),
                        if (item.minLevel > 1)
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: meetsLevel ? Colors.green.withValues(alpha: 0.1) : Colors.red.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(12),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  meetsLevel ? Icons.lock_open : Icons.lock,
                                  size: 12,
                                  color: meetsLevel ? Colors.green : Colors.red,
                                ),
                                const SizedBox(width: 3),
                                Text('Lv.${item.minLevel}',
                                    style: TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w500,
                                        color: meetsLevel ? Colors.green : Colors.red)),
                              ],
                            ),
                          ),
                        const Spacer(),
                        SizedBox(
                          height: 38,
                          child: ElevatedButton(
                            onPressed: (!canAfford || store.isPurchasing)
                                ? null
                                : () => _showTermsThenPurchase(context, store, item),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: canAfford ? AppTheme.primaryColor : Colors.grey[300],
                              foregroundColor: canAfford ? Colors.white : Colors.grey[500],
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                              padding: const EdgeInsets.symmetric(horizontal: 20),
                              elevation: 0,
                            ),
                            child: store.isPurchasing
                                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                : Text(canAfford ? 'Redeem' : (meetsLevel ? 'Need XP' : 'Locked'),
                                    style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                          ),
                        ),
                      ],
                    ),
                    if (!meetsLevel && item.minLevel > 1)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text('Requires level ${item.minLevel} (you are level $userLevel)',
                            style: const TextStyle(color: Colors.red, fontSize: 11)),
                      )
                    else if (!meetsXp)
                      Padding(
                        padding: const EdgeInsets.only(top: 6),
                        child: Text('Need ${item.priceXp - userXp} more XP',
                            style: const TextStyle(color: Colors.red, fontSize: 11)),
                      ),
                    if (item.terms != null && item.terms!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      GestureDetector(
                        onTap: () => _showTerms(context, item),
                        child: Row(
                          children: [
                            Icon(Icons.info_outline, size: 12, color: Colors.grey[400]),
                            const SizedBox(width: 4),
                            Text('View terms & conditions',
                                style: TextStyle(color: Colors.grey[400], fontSize: 10)),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showTerms(BuildContext context, ShopItem item) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            Text(item.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16)),
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.amber[50],
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.amber[200]!),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline, size: 16, color: Colors.amber[800]),
                  const SizedBox(width: 8),
                  Expanded(child: Text(item.terms!, style: TextStyle(color: Colors.amber[900], fontSize: 13))),
                ],
              ),
            ),
            const SizedBox(height: 16),
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
          ],
        ),
      ),
    );
  }

  void _showTermsThenPurchase(BuildContext context, StoreProvider store, ShopItem item) {
    final user = context.read<AuthProvider>().user;
    final xp = user?.totalXp ?? 0;
    final remaining = xp - item.priceXp;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            Container(
              width: 64,
              height: 64,
              decoration: BoxDecoration(color: _rewardColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(16)),
              child: Icon(_rewardIcon, color: _rewardColor, size: 32),
            ),
            const SizedBox(height: 12),
            Text(item.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 18)),
            if (item.sponsor != null) ...[
              const SizedBox(height: 4),
              Text('by ${item.sponsor!.name}', style: TextStyle(color: Colors.grey[500], fontSize: 13)),
            ],
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Current Balance', style: TextStyle(color: Colors.grey[500], fontSize: 12)),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            const Icon(Icons.star, size: 16, color: AppTheme.secondaryColor),
                            const SizedBox(width: 4),
                            Text('$xp XP', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                          ],
                        ),
                      ],
                    ),
                  ),
                  Column(
                    children: [
                      const Icon(Icons.remove, color: Colors.red, size: 20),
                      Text('-${item.priceXp} XP', style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w600, fontSize: 13)),
                    ],
                  ),
                  const SizedBox(width: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('Remaining', style: TextStyle(color: Colors.grey[500], fontSize: 12)),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          const Icon(Icons.star, size: 16, color: AppTheme.secondaryColor),
                          const SizedBox(width: 4),
                          Text('$remaining XP', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
                        ],
                      ),
                    ],
                  ),
                ],
              ),
            ),
            if (item.terms != null && item.terms!.isNotEmpty) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: Colors.amber[50],
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.info_outline, size: 14, color: Colors.amber[800]),
                    const SizedBox(width: 6),
                    Expanded(child: Text(item.terms!, style: TextStyle(color: Colors.amber[900], fontSize: 11))),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(ctx),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Cancel'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.pop(ctx);
                      _doPurchase(context, store, item);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppTheme.primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: Text('Redeem ${item.priceXp} XP', style: const TextStyle(fontWeight: FontWeight.w600)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _doPurchase(BuildContext context, StoreProvider store, ShopItem item) async {
    final result = await store.purchaseItem(item.id);
    if (!context.mounted) return;

    if (result?['success'] == true) {
      await context.read<AuthProvider>().refreshProfile();
      _showPurchaseResult(context, result!);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result?['message'] ?? 'Redemption failed'), backgroundColor: Colors.red),
      );
    }
  }

  void _showPurchaseResult(BuildContext context, Map<String, dynamic> result) {
    final purchase = result['purchase'] as Purchase;
    final isCompleted = purchase.status == 'completed';

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: isCompleted ? Colors.green.withValues(alpha: 0.1) : Colors.orange.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  isCompleted ? Icons.check_circle : Icons.hourglass_empty,
                  color: isCompleted ? Colors.green : Colors.orange,
                  size: 40,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                isCompleted ? 'Redemption Successful!' : 'Redemption Submitted',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Text(
                result['message'] ?? '',
                style: TextStyle(color: Colors.grey[500], fontSize: 13),
                textAlign: TextAlign.center,
              ),
              if (purchase.code != null) ...[
                const SizedBox(height: 16),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.grey[100],
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey[200]!),
                  ),
                  child: Column(
                    children: [
                      Text('Your Redeem Code', style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                      const SizedBox(height: 8),
                      SelectableText(
                        purchase.code!,
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 20, letterSpacing: 2),
                      ),
                      const SizedBox(height: 8),
                      GestureDetector(
                        onTap: () {
                          // Copy to clipboard would use Clipboard.setData
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Code copied!'), duration: Duration(seconds: 1)),
                          );
                        },
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.copy, size: 14, color: AppTheme.primaryColor),
                              const SizedBox(width: 4),
                              Text('Copy', style: TextStyle(color: AppTheme.primaryColor, fontSize: 12, fontWeight: FontWeight.w500)),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 4),
                Text('Show this code at the partner location',
                    style: TextStyle(color: Colors.grey[400], fontSize: 11)),
              ],
              if (purchase.shopItem?.redemptionInstructions != null) ...[
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.blue[50],
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(purchase.shopItem!.redemptionInstructions!,
                      style: TextStyle(color: Colors.blue[800], fontSize: 12)),
                ),
              ],
              if (purchase.shopItem?.sponsor?.hasLocation == true) ...[
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: () {
                    Navigator.pop(ctx);
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => NearbyPlacesScreen.withDestination(
                          destinationLat: purchase.shopItem!.sponsor!.latitude!,
                          destinationLng: purchase.shopItem!.sponsor!.longitude!,
                          destinationName: purchase.shopItem!.sponsor!.name,
                        ),
                      ),
                    );
                  },
                  icon: const Icon(Icons.directions, size: 16),
                  label: const Text('Get Directions'),
                  style: OutlinedButton.styleFrom(
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () => Navigator.pop(ctx),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text('Done'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
