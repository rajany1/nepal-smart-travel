import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../config/themes/app_theme.dart';
import '../../../providers/store_provider.dart';

class StoreCategoryTabs extends StatelessWidget {
  final String selectedCategory;
  final ValueChanged<String> onCategoryChanged;

  const StoreCategoryTabs({
    super.key,
    required this.selectedCategory,
    required this.onCategoryChanged,
  });

  static const categories = [
    {'key': 'all', 'label': 'All', 'icon': Icons.explore},
    {'key': 'discount', 'label': 'Discounts', 'icon': Icons.percent},
    {'key': 'voucher', 'label': 'Vouchers', 'icon': Icons.confirmation_number},
    {'key': 'free_item', 'label': 'Free Items', 'icon': Icons.card_giftcard},
    {'key': 'special_offer', 'label': 'Offers', 'icon': Icons.local_offer},
  ];

  @override
  Widget build(BuildContext context) {
    return Consumer<StoreProvider>(
      builder: (context, store, _) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 38,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: categories.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  final cat = categories[index];
                  final key = cat['key'] as String;
                  final label = cat['label'] as String;
                  final icon = cat['icon'] as IconData;
                  final isSelected = selectedCategory == key;

                  final count = key == 'all'
                      ? store.items.length
                      : store.items.where((i) => i.rewardType == key).length;

                  return GestureDetector(
                    onTap: () => onCategoryChanged(key),
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 14),
                      decoration: BoxDecoration(
                        color: isSelected ? AppTheme.primaryColor : AppTheme.primaryColor.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: isSelected ? AppTheme.primaryColor : AppTheme.dividerColor,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(icon, size: 14, color: isSelected ? Colors.white : Colors.grey[600]),
                          const SizedBox(width: 6),
                          Text(
                            label,
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w500,
                              color: isSelected ? Colors.white : Colors.grey[700],
                            ),
                          ),
                          const SizedBox(width: 4),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                            decoration: BoxDecoration(
                              color: isSelected ? Colors.white.withValues(alpha: 0.2) : Colors.grey.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              '$count',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                                color: isSelected ? Colors.white : Colors.grey[600],
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        );
      },
    );
  }
}
