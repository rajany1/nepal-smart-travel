import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/offer_provider.dart';
import '../../providers/auth_provider.dart';
import '../bookings/my_bookings_screen.dart';
import '../offers/offers_screen.dart';
import 'widgets/store_xp_header.dart';

class StoreScreen extends StatefulWidget {
  const StoreScreen({super.key});

  @override
  State<StoreScreen> createState() => _StoreScreenState();
}

class _StoreScreenState extends State<StoreScreen> {
  int _tabIndex = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final offers = context.read<OfferProvider>();
      offers.fetchOffers();
      if (context.read<AuthProvider>().isAuthenticated) {
        offers.fetchMyRedemptions();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('XP Rewards')),
      body: IndexedStack(
        index: _tabIndex,
        children: const [
          _BuildRewardsTab(),
          MyCodesView(),
          MyBookingsScreen(inTab: true),
        ],
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _tabIndex,
        onTap: (i) => setState(() => _tabIndex = i),
        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.local_offer_outlined),
            label: 'Rewards',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.confirmation_number_outlined),
            label: 'My Codes',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.book_online_outlined),
            label: 'Bookings',
          ),
        ],
      ),
    );
  }
}

class _BuildRewardsTab extends StatelessWidget {
  const _BuildRewardsTab();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: StoreXpHeader(),
        ),
        const Expanded(child: ExploreOffersView()),
      ],
    );
  }
}
