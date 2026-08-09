import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/offer_model.dart';
import '../../providers/auth_provider.dart';
import '../../providers/offer_provider.dart';
import '../auth/login_screen.dart';

class OfferDetailScreen extends StatefulWidget {
  final OfferModel offer;

  const OfferDetailScreen({super.key, required this.offer});

  @override
  State<OfferDetailScreen> createState() => _OfferDetailScreenState();
}

class _OfferDetailScreenState extends State<OfferDetailScreen> {
  bool _isClaiming = false;

  String get _typeLabel {
    switch (widget.offer.offerType) {
      case 'percentage_off':
        return 'Percentage Off';
      case 'fixed_off':
        return 'Fixed Amount Off';
      case 'free_item':
        return 'Free Item';
      case 'buy_one_get_one':
        return 'Buy One Get One';
      default:
        return 'Special Offer';
    }
  }

  Future<void> _claim() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      final goLogin = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Login required'),
          content: const Text('Log in to claim this reward and get your unique code.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Log in'),
            ),
          ],
        ),
      );
      if (goLogin == true && mounted) {
        Navigator.push(context, MaterialPageRoute(builder: (_) => const LoginScreen()));
      }
      return;
    }

    if (_isClaiming) return;
    setState(() => _isClaiming = true);

    try {
      final redemption = await context.read<OfferProvider>().claimOffer(widget.offer.id);
      if (!mounted) return;
      await _showCodeSheet(redemption);
    } on DioException catch (e) {
      if (!mounted) return;
      final data = e.response?.data;
      final message = data is Map && data['message'] != null
          ? data['message'].toString()
          : 'Could not claim offer. Try again.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not claim offer. Check your connection.')),
      );
    } finally {
      if (mounted) setState(() => _isClaiming = false);
    }
  }

  Future<void> _showCodeSheet(OfferRedemptionModel redemption) async {
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
            const Icon(Icons.check_circle, color: Colors.green, size: 48),
            const SizedBox(height: 12),
            const Text('Offer claimed!', style: TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold)),
            const SizedBox(height: 4),
            Text(
              'Show this code at ${widget.offer.business?.name ?? 'the business'}',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textSm),
            ),
            const SizedBox(height: 16),
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
                  fontWeight: FontWeight.w900,
                  letterSpacing: 3,
                  color: AppTheme.primaryColor,
                  fontFamily: 'monospace',
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: redemption.code));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Code copied')),
                        );
                      }
                    },
                    icon: const Icon(Icons.copy, size: 18),
                    label: const Text('Copy'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () => Share.share(
                      'My Nepal Smart Travel reward code: ${redemption.code}\n'
                      'Offer: ${widget.offer.title}\n'
                      'Valid at: ${widget.offer.business?.name ?? ''}',
                    ),
                    icon: const Icon(Icons.share, size: 18),
                    label: const Text('Share'),
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
    final offer = widget.offer;
    final provider = context.watch<OfferProvider>();
    final alreadyClaimed = provider.myRedemptions
        .any((r) => r.offerId == offer.id && r.status != 'expired');
    final canClaim = !alreadyClaimed && !offer.isExpired;

    return Scaffold(
      appBar: AppBar(title: const Text('Offer Details')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [AppTheme.primaryColor, Color(0xFF0EA5A0)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(
                children: [
                  Container(
                    width: 96,
                    height: 96,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Center(
                      child: Padding(
                        padding: const EdgeInsets.all(10),
                        child: Text(
                          offer.label,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: AppTheme.primaryColor,
                            fontWeight: FontWeight.w900,
                            fontSize: AppTheme.textLg,
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    offer.title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: AppTheme.textXl,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _typeLabel,
                    style: const TextStyle(color: Colors.white70, fontSize: AppTheme.textSm),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            if (offer.business != null) ...[
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.06),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Icon(Icons.storefront, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            offer.business!.name,
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: AppTheme.textBase),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            [
                              offer.business!.address,
                              offer.business!.district,
                            ].where((s) => s != null && s.isNotEmpty).join(', '),
                            style: const TextStyle(color: Colors.grey, fontSize: AppTheme.textXs),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
            ],

            if (offer.description != null && offer.description!.isNotEmpty) ...[
              Text('About this offer', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(
                offer.description!,
                style: const TextStyle(fontSize: AppTheme.textBase, height: 1.6),
              ),
              const SizedBox(height: 20),
            ],

            if (offer.terms != null && offer.terms!.isNotEmpty) ...[
              Text('Terms & Conditions', style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.amber.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: Colors.amber.withOpacity(0.3)),
                ),
                child: Text(
                  offer.terms!,
                  style: const TextStyle(fontSize: AppTheme.textSm, height: 1.6),
                ),
              ),
              const SizedBox(height: 20),
            ],

            Row(
              children: [
                _InfoChip(icon: Icons.event, label: offer.endsAt != null
                    ? 'Valid till ${_fmtDate(offer.endsAt!)}'
                    : 'No expiry'),
                const SizedBox(width: 8),
                _InfoChip(
                  icon: Icons.people_outline,
                  label: offer.isUnlimited ? 'Unlimited' : '${offer.usageLimit - offer.usedCount} left',
                ),
              ],
            ),
            const SizedBox(height: 24),

            if (offer.isExpired)
              const Center(
                child: Text(
                  'This offer has expired',
                  style: TextStyle(color: Colors.red, fontWeight: FontWeight.w600),
                ),
              )
            else if (alreadyClaimed)
              const Center(
                child: Text(
                  'You already claimed this offer — check My Codes',
                  style: TextStyle(color: AppTheme.primaryColor, fontWeight: FontWeight.w600),
                ),
              )
            else
              SizedBox(
                width: double.infinity,
                height: 52,
                child: FilledButton.icon(
                  onPressed: canClaim ? _claim : null,
                  icon: _isClaiming
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.local_offer),
                  label: Text(_isClaiming ? 'Claiming...' : 'Get Offer Code'),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppTheme.secondaryColor,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  String _fmtDate(String iso) {
    final dt = DateTime.tryParse(iso);
    if (dt == null) return iso;
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return '${dt.day} ${months[dt.month - 1]} ${dt.year}';
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;

  const _InfoChip({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.grey.shade100,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: AppTheme.primaryColor),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(fontSize: AppTheme.textXs, fontWeight: FontWeight.w600)),
        ],
      ),
    );
  }
}
