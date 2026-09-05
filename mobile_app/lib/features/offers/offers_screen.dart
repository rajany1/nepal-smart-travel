import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/offer_model.dart';
import '../../providers/offer_provider.dart';
import '../auth/login_screen.dart';
import '../../providers/auth_provider.dart';
import 'offer_detail_screen.dart';

class OffersScreen extends StatefulWidget {
  const OffersScreen({super.key});

  @override
  State<OffersScreen> createState() => _OffersScreenState();
}

class _OffersScreenState extends State<OffersScreen> {
  int _tab = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<OfferProvider>();
      provider.fetchOffers();
      if (context.read<AuthProvider>().isAuthenticated) {
        provider.fetchMyRedemptions();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: const Color(0xFFF97316).withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.local_offer, color: Color(0xFFF97316), size: 22),
            ),
            const SizedBox(width: 12),
            Text(context.t('Rewards')),
          ],
        ),
        bottom: TabBar(
          onTap: (i) => setState(() => _tab = i),
          labelColor: const Color(0xFFF97316),
          unselectedLabelColor: Colors.grey,
          indicatorColor: const Color(0xFFF97316),
          tabs: [
            Tab(icon: const Icon(Icons.explore_outlined), text: context.t('Explore')),
            Tab(icon: const Icon(Icons.confirmation_number_outlined), text: context.t('My Codes')),
          ],
        ),
      ),
        body: _tab == 0 ? const ExploreOffersView() : const MyCodesView(),
      ),
    );
  }
}

class ExploreOffersView extends StatelessWidget {
  const ExploreOffersView();

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<OfferProvider>();
    final offers = provider.offers;

    if (provider.isLoading && offers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (offers.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.card_giftcard, size: 64, color: AppTheme.primaryColor.withOpacity(0.4)),
              const SizedBox(height: 16),
              const Text('No offers available right now', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Text(
                context.t('Businesses post exclusive discounts here. Check back soon!'),
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.grey),
              ),
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: () => context.read<OfferProvider>().fetchOffers(),
                icon: const Icon(Icons.refresh),
                label: Text(context.t('Refresh')),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => context.read<OfferProvider>().fetchOffers(),
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: offers.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, i) => OfferCard(offer: offers[i]),
      ),
    );
  }
}

class OfferCard extends StatelessWidget {
  final OfferModel offer;

  const OfferCard({super.key, required this.offer});

  Color get _badgeColor {
    switch (offer.offerType) {
      case 'percentage_off':
        return AppTheme.secondaryColor;
      case 'fixed_off':
        return AppTheme.primaryColor;
      case 'free_item':
        return AppTheme.infoColor;
      default:
        return AppTheme.warningColor;
    }
  }

  String _typeLabel(BuildContext context) {
    switch (offer.offerType) {
      case 'percentage_off':
        return context.t('PERCENT OFF');
      case 'fixed_off':
        return context.t('AMOUNT OFF');
      case 'free_item':
        return context.t('FREE ITEM');
      case 'buy_one_get_one':
        return context.t('BUY 1 GET 1');
      default:
        return context.t('OFFER');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 1.5,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => OfferDetailScreen(offer: offer)),
        ),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [AppTheme.primaryColor, Color(0xFF0EA5A0)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 76,
                height: 76,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14),
                ),
                clipBehavior: Clip.antiAlias,
                child: offer.image != null && offer.image!.isNotEmpty
                    ? CachedNetworkImage(
                        imageUrl: offer.image!,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => Center(
                          child: Text(
                            offer.label,
                            textAlign: TextAlign.center,
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AppTheme.primaryColor,
                              fontWeight: FontWeight.w600,
                              fontSize: AppTheme.textSm,
                            ),
                          ),
                        ),
                        errorWidget: (_, __, ___) => Center(
                          child: Text(
                            offer.label,
                            textAlign: TextAlign.center,
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AppTheme.primaryColor,
                              fontWeight: FontWeight.w600,
                              fontSize: AppTheme.textSm,
                            ),
                          ),
                        ),
                      )
                    : Center(
                        child: Padding(
                          padding: const EdgeInsets.all(6),
                          child: Text(
                            offer.label,
                            textAlign: TextAlign.center,
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: AppTheme.primaryColor,
                              fontWeight: FontWeight.w600,
                              fontSize: AppTheme.textSm,
                            ),
                          ),
                        ),
                      ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.2),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        context.t(_typeLabel(context)),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          letterSpacing: 0.8,
                        ),
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      offer.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: AppTheme.textLg,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        const Icon(Icons.storefront, size: 13, color: Colors.white70),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            offer.business?.name ?? context.t('Local business'),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textXs),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              if (offer.priceXp > 0) ...[
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFD700),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.star, size: 12, color: Colors.black87),
                      const SizedBox(width: 3),
                      Text(
                        '${offer.priceXp} ${context.t('XP')}',
                        style: const TextStyle(
                          color: Colors.black87,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              const Icon(Icons.chevron_right, color: Colors.white70),
            ],
          ),
        ),
      ),
    );
  }
}

class MyCodesView extends StatelessWidget {
  const MyCodesView();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final provider = context.watch<OfferProvider>();

    if (!auth.isAuthenticated) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.lock_outline, size: 64, color: Colors.grey),
              const SizedBox(height: 16),
              const Text('Login to see your claimed codes', style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w600)),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen())),
                icon: const Icon(Icons.login),
                label: Text(context.t('Log in')),
              ),
            ],
          ),
        ),
      );
    }

    final redemptions = provider.myRedemptions;

    if (provider.isLoadingMine && redemptions.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }

    if (redemptions.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.confirmation_number_outlined, size: 64, color: AppTheme.primaryColor.withOpacity(0.4)),
              const SizedBox(height: 16),
              Text(
                context.t('No codes yet'),
                style: const TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              Text(
                context.t('Claim an offer from the Explore tab to get a unique code.'),
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.grey),
              ),
            ],
          ),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: () => context.read<OfferProvider>().fetchMyRedemptions(),
      child: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: redemptions.length,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (context, i) => CodeCard(redemption: redemptions[i]),
      ),
    );
  }
}

class CodeCard extends StatelessWidget {
  final OfferRedemptionModel redemption;

  const CodeCard({required this.redemption});

  Future<void> _showCodeSheet(BuildContext context) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => Container(
        margin: const EdgeInsets.all(12),
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        decoration: BoxDecoration(
          color: Theme.of(context).scaffoldBackgroundColor,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
            ),
            Text(
              context.t('Your Reward Code'),
              style: const TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 6),
            Text(
              redemption.offer?.title ?? context.t('Offer'),
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textSm),
            ),
            const SizedBox(height: 20),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 16),
              decoration: BoxDecoration(
                color: AppTheme.primaryColor.withOpacity(0.1),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppTheme.primaryColor.withOpacity(0.3)),
              ),
              child: Text(
                redemption.code,
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 3,
                  color: AppTheme.primaryColor,
                  fontFamily: 'monospace',
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${context.t('Show this code at')} ${redemption.offer?.business?.name ?? context.t('the business')} ${context.t('to redeem.')}',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textXs),
            ),
            const SizedBox(height: 20),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      final copiedMsg = context.tr('Code copied to clipboard');
                      await Clipboard.setData(ClipboardData(text: redemption.code));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(copiedMsg)),
                        );
                      }
                    },
                    icon: const Icon(Icons.copy, size: 18),
                    label: Text(context.t('Copy')),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () async {
                      await Share.share(
                        '${context.t('My Nepal Smart Travel reward code:')} ${redemption.code}\n'
                        '${context.t('Offer:')} ${redemption.offer?.title ?? ''}\n'
                        '${context.t('Valid at:')} ${redemption.offer?.business?.name ?? ''}',
                      );
                    },
                    icon: const Icon(Icons.share, size: 18),
                    label: Text(context.t('Share')),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final used = redemption.status == 'used';
    final expired = redemption.status == 'expired';

    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      color: used || expired ? Colors.grey.shade100 : Colors.white,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _showCodeSheet(context),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: used || expired ? Colors.grey.shade300 : AppTheme.secondaryColor.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  Icons.confirmation_number,
                  color: used || expired ? Colors.grey : AppTheme.secondaryColor,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      redemption.offer?.title ?? context.t('Offer'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      redemption.offer?.business?.name ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textXs),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      redemption.code,
                      style: const TextStyle(
                        fontFamily: 'monospace',
                        fontWeight: FontWeight.w700,
                        letterSpacing: 1.5,
                        color: AppTheme.primaryColor,
                      ),
                    ),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: used
                          ? Colors.green.withOpacity(0.15)
                          : expired
                              ? Colors.grey.shade300
                              : AppTheme.warningColor.withOpacity(0.15),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      used ? context.t('USED') : expired ? context.t('EXPIRED') : context.t('ACTIVE'),
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: used ? Colors.green : expired ? Colors.grey : AppTheme.warningColor,
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    context.t('Tap to view'),
                    style: TextStyle(fontSize: 10, color: Colors.grey.shade500),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
