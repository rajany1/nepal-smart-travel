import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/store_provider.dart';
import '../../providers/auth_provider.dart';
import 'widgets/store_xp_header.dart';
import 'widgets/store_item_card.dart';
import 'widgets/store_order_card.dart';
import 'widgets/store_category_tabs.dart';

class StoreScreen extends StatefulWidget {
  const StoreScreen({super.key});

  @override
  State<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends State<StoreScreen> {
  int _tabIndex = 0;
  String _selectedCategory = 'all';
  String _sortBy = 'default';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AuthProvider>().refreshProfile();
      context.read<StoreProvider>().loadItems();
      context.read<StoreProvider>().loadPurchases();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('XP Rewards'),
        actions: [
          if (_tabIndex == 0)
            IconButton(
              icon: const Icon(Icons.sort),
              onPressed: _showSortOptions,
            ),
        ],
      ),
      body: _tabIndex == 0 ? _buildShopTab() : _buildOrdersTab(),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _tabIndex,
        onTap: (i) => setState(() => _tabIndex = i),
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.store), label: 'Rewards'),
          BottomNavigationBarItem(icon: Icon(Icons.receipt_long), label: 'My Orders'),
        ],
      ),
    );
  }

  Widget _buildShopTab() {
    return Consumer<StoreProvider>(
      builder: (context, store, _) {
        if (store.isLoading) {
          return const Center(child: CircularProgressIndicator());
        }

        var items = store.items.where((item) {
          if (_selectedCategory == 'all') return true;
          return item.rewardType == _selectedCategory;
        }).toList();

        switch (_sortBy) {
          case 'price_low':
            items.sort((a, b) => a.priceXp.compareTo(b.priceXp));
            break;
          case 'price_high':
            items.sort((a, b) => b.priceXp.compareTo(a.priceXp));
            break;
          case 'level':
            items.sort((a, b) => a.minLevel.compareTo(b.minLevel));
            break;
          case 'name':
            items.sort((a, b) => a.name.compareTo(b.name));
            break;
        }

        if (store.items.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.store_mall_directory_outlined, size: 64, color: Colors.grey[300]),
                const SizedBox(height: 16),
                Text('No rewards yet', style: TextStyle(color: Colors.grey[500], fontSize: 16)),
                const SizedBox(height: 8),
                Text('Check back later for new rewards', style: TextStyle(color: Colors.grey[400], fontSize: 13)),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () async {
            await context.read<AuthProvider>().refreshProfile();
            await store.loadItems();
          },
          child: CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                  child: Column(
                    children: [
                      const StoreXpHeader(),
                      _buildEarnXpBanner(),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: StoreCategoryTabs(
                    selectedCategory: _selectedCategory,
                    onCategoryChanged: (cat) => setState(() => _selectedCategory = cat),
                  ),
                ),
              ),
              if (items.isEmpty)
                SliverFillRemaining(
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.search_off, size: 48, color: Colors.grey[300]),
                        const SizedBox(height: 12),
                        Text('No rewards in this category',
                            style: TextStyle(color: Colors.grey[500], fontSize: 15)),
                      ],
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) => StoreItemCard(item: items[index]),
                      childCount: items.length,
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildEarnXpBanner() {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.amber[50],
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: Colors.amber[200]!),
      ),
      child: Row(
        children: [
          Icon(Icons.local_fire_department, size: 18, color: Colors.amber[700]),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Submit reports, post alerts & write reviews to earn more XP!',
              style: TextStyle(color: Colors.amber[900], fontSize: 12, fontWeight: FontWeight.w500),
            ),
          ),
          GestureDetector(
            onTap: () => _showEarnTips(),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.amber[200],
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text('Earn XP', style: TextStyle(color: Colors.amber[900], fontSize: 11, fontWeight: FontWeight.w600)),
            ),
          ),
        ],
      ),
    );
  }

  void _showEarnTips() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('How to Earn XP', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            const SizedBox(height: 16),
            _tipRow(Icons.assignment, 'Submit Reports', '+10 XP per approved report'),
            _tipRow(Icons.warning_amber, 'Post Alerts', '+5 XP per alert'),
            _tipRow(Icons.rate_review, 'Write Reviews', '+3 XP per review'),
            _tipRow(Icons.emoji_events, 'Unlock Achievements', 'Bonus XP rewards'),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () => Navigator.pop(ctx),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Got it!'),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _tipRow(IconData icon, String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: AppTheme.primaryColor.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, color: AppTheme.primaryColor, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
                Text(subtitle, style: TextStyle(color: Colors.grey[500], fontSize: 12)),
              ],
            ),
          ),
          Icon(Icons.arrow_forward_ios, size: 12, color: Colors.grey[400]),
        ],
      ),
    );
  }

  void _showSortOptions() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('Sort Rewards', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)),
            const SizedBox(height: 12),
            _sortOption(ctx, 'default', 'Default'),
            _sortOption(ctx, 'price_low', 'Price: Low to High'),
            _sortOption(ctx, 'price_high', 'Price: High to Low'),
            _sortOption(ctx, 'level', 'Level Required'),
            _sortOption(ctx, 'name', 'Name (A-Z)'),
          ],
        ),
      ),
    );
  }

  Widget _sortOption(BuildContext ctx, String value, String label) {
    final isSelected = _sortBy == value;
    return ListTile(
      leading: Icon(
        isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
        color: isSelected ? AppTheme.primaryColor : Colors.grey,
        size: 20,
      ),
      title: Text(label, style: TextStyle(fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal)),
      onTap: () {
        setState(() => _sortBy = value);
        Navigator.pop(ctx);
      },
    );
  }

  Widget _buildOrdersTab() {
    return Consumer<StoreProvider>(
      builder: (context, store, _) {
        if (store.purchases.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(Icons.receipt_long_outlined, size: 64, color: Colors.grey[300]),
                const SizedBox(height: 16),
                Text('No redemptions yet', style: TextStyle(color: Colors.grey[500], fontSize: 16)),
                const SizedBox(height: 8),
                Text('Browse rewards and start redeeming', style: TextStyle(color: Colors.grey[400], fontSize: 13)),
                const SizedBox(height: 20),
                ElevatedButton.icon(
                  onPressed: () => setState(() => _tabIndex = 0),
                  icon: const Icon(Icons.store, size: 18),
                  label: const Text('Browse Rewards'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppTheme.primaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () => store.loadPurchases(),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: store.purchases.length,
            itemBuilder: (context, index) => StoreOrderCard(purchase: store.purchases[index]),
          ),
        );
      },
    );
  }
}
