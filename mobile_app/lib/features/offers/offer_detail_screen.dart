import 'package:flutter/material.dart';
import 'dart:async';
import "../../core/services/localization_service.dart";
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:share_plus/share_plus.dart';
import '../../core/utils/share_helper.dart';
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
  DateTime? _claimCooldownUntil;

  @override
  void initState() {
    super.initState();
    // Fresh claim state on open — if the user already claimed this offer
    // (from another device/session) the button must not stay active.
    final provider = context.read<OfferProvider>();
    if (provider.myRedemptions.isEmpty) {
      Future.microtask(() => provider.fetchMyRedemptions());
    }
  }

  String _typeLabel(BuildContext context) {
    switch (widget.offer.offerType) {
      case 'percentage_off':
        return context.t('Percentage Off');
      case 'fixed_off':
        return context.t('Fixed Amount Off');
      case 'free_item':
        return context.t('Free Item');
      case 'buy_one_get_one':
        return context.t('Buy One Get One');
      default:
        return context.t('Special Offer');
    }
  }

  Future<void> _claim() async {
    final auth = context.read<AuthProvider>();
    if (!auth.isAuthenticated) {
      final goLogin = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(ctx.t('Login required')),
          content: Text(ctx.t('Log in to claim this reward and get your unique code.')),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(ctx.t('Cancel'))),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: Text(ctx.t('Log in')),
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
      setState(() => _claimCooldownUntil = DateTime.now().add(const Duration(seconds: 5)));
      await _showCodeSheet(redemption);
    } on DioException catch (e) {
      if (!mounted) return;
      final data = e.response?.data;
      if (e.response?.statusCode == 429) {
        // Server throttle active — back off longer.
        setState(() => _claimCooldownUntil = DateTime.now().add(const Duration(seconds: 30)));
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.t('Too many claims. Please try again later.'))),
        );
        return;
      }
      if (e.response?.statusCode == 409) {
        // Already claimed (maybe from another device) — the server returns the
        // existing redemption; consume it so the button flips to claimed and
        // the user gets their code instead of tapping forever.
        final redemption = data is Map && data['redemption'] is Map
            ? OfferRedemptionModel.fromJson(data['redemption'] as Map<String, dynamic>)
            : null;
        if (redemption != null) {
          context.read<OfferProvider>().addRedemption(redemption);
          if (!mounted) return;
          await _showCodeSheet(redemption);
          return;
        }
        setState(() => _claimCooldownUntil = DateTime.now().add(const Duration(seconds: 10)));
        final message = data is Map && data['message'] != null
            ? data['message'].toString()
            : context.t('You already claimed this offer.');
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
        return;
      }
      if (e.response?.statusCode == 422) {
        final message = data is Map && data['message'] != null
            ? data['message'].toString()
            : context.t('Not enough ORIPORI Coins.');
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Row(
              children: [
                const Icon(Icons.wallet_outlined, color: Colors.white, size: 18),
                const SizedBox(width: 8),
                Expanded(child: Text(message)),
              ],
            ),
            backgroundColor: Colors.orange.shade800,
            duration: const Duration(seconds: 4),
          ));
        }
        setState(() => _claimCooldownUntil = DateTime.now().add(const Duration(seconds: 5)));
        return;
      }
      setState(() => _claimCooldownUntil = DateTime.now().add(const Duration(seconds: 5)));
      final message = data is Map && data['message'] != null
          ? data['message'].toString()
          : context.t('Could not claim offer. Try again.');
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } catch (_) {
      if (!mounted) return;
      setState(() => _claimCooldownUntil = DateTime.now().add(const Duration(seconds: 5)));
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.t('Could not claim offer. Check your connection.'))),
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
            Text(
              context.t('Offer claimed!'),
              style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 4),
            Text(
              '${context.t('Show this code at')} ${widget.offer.business?.name ?? context.t('the business')}',
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
                  fontWeight: FontWeight.w700,
                  letterSpacing: 3,
                  color: AppTheme.primaryColor,
                  fontFamily: 'monospace',
                ),
              ),
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: Colors.grey.shade200),
              ),
              child: QrImageView(
                data: redemption.code,
                version: QrVersions.auto,
                size: 180,
                backgroundColor: Colors.white,
                foregroundColor: Colors.black87,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              context.t('Partner: Scan this QR to redeem'),
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey.shade500, fontSize: AppTheme.textXs),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () async {
                      final copiedMsg = context.tr('Code copied');
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
                    onPressed: () => ShareHelper.shareOfferCode(
                      code: redemption.code,
                      offerTitle: widget.offer.title,
                      businessName: widget.offer.business?.name,
                    ),
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
    final offer = widget.offer;
    final provider = context.watch<OfferProvider>();
    final alreadyClaimed = provider.myRedemptions
        .any((r) => r.offerId == offer.id && r.status != 'expired');
    final canClaim = !alreadyClaimed && !offer.isExpired;

    return Scaffold(
      appBar: AppBar(title: Text(context.t('Offer Details'))),
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
                    clipBehavior: Clip.antiAlias,
                    child: offer.image != null && offer.image!.isNotEmpty
                        ? CachedNetworkImage(
                            imageUrl: offer.image!,
                            fit: BoxFit.cover,
                            placeholder: (_, __) => Center(
                              child: Text(
                                offer.label,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppTheme.primaryColor,
                                  fontWeight: FontWeight.w600,
                                  fontSize: AppTheme.textLg,
                                ),
                              ),
                            ),
                            errorWidget: (_, __, ___) => Center(
                              child: Text(
                                offer.label,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppTheme.primaryColor,
                                  fontWeight: FontWeight.w600,
                                  fontSize: AppTheme.textLg,
                                ),
                              ),
                            ),
                          )
                        : Center(
                            child: Padding(
                              padding: const EdgeInsets.all(10),
                              child: Text(
                                offer.label,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  color: AppTheme.primaryColor,
                                  fontWeight: FontWeight.w600,
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
                    context.t(_typeLabel(context)),
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
              Text(context.t('About this offer'), style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: 8),
              Text(
                offer.description!,
                style: const TextStyle(fontSize: AppTheme.textBase, height: 1.6),
              ),
              const SizedBox(height: 20),
            ],

            if (offer.terms != null && offer.terms!.isNotEmpty) ...[
              Text(context.t('Terms & Conditions'), style: Theme.of(context).textTheme.titleMedium),
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
                    ? '${context.t('Valid till')} ${_fmtDate(offer.endsAt!)}'
                    : context.t('No expiry')),
                const SizedBox(width: 8),
                _InfoChip(
                  icon: Icons.people_outline,
                  label: offer.isUnlimited ? context.t('Unlimited') : '${offer.usageLimit - offer.usedCount} ${context.t('left')}',
                ),
              ],
            ),
            const SizedBox(height: 24),

            if (offer.isExpired)
              Center(
                child: Text(
                  context.t('This offer has expired'),
                  style: const TextStyle(color: Colors.red, fontWeight: FontWeight.w600),
                ),
              )
            else if (alreadyClaimed)
              Center(
                child: Text(
                  context.t('You already claimed this offer — check My Codes'),
                  style: const TextStyle(color: AppTheme.primaryColor, fontWeight: FontWeight.w600),
                ),
              )
            else
              _ClaimButton(
                offer: offer,
                canClaim: canClaim,
                isClaiming: _isClaiming,
                cooldownUntil: _claimCooldownUntil,
                onClaim: _claim,
              ),
          ],
        ),
      ),
    );
  }

  String _fmtDate(String iso) {
    final dt = DateTime.tryParse(iso);
    if (dt == null) return iso;
    final months = [
      context.t('Jan'), context.t('Feb'), context.t('Mar'), context.t('Apr'),
      context.t('May'), context.t('Jun'), context.t('Jul'), context.t('Aug'),
      context.t('Sep'), context.t('Oct'), context.t('Nov'), context.t('Dec'),
    ];
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

class _ClaimButton extends StatefulWidget {
  final OfferModel offer;
  final bool canClaim;
  final bool isClaiming;
  final DateTime? cooldownUntil;
  final VoidCallback onClaim;

  const _ClaimButton({
    required this.offer,
    required this.canClaim,
    required this.isClaiming,
    required this.cooldownUntil,
    required this.onClaim,
  });

  @override
  State<_ClaimButton> createState() => _ClaimButtonState();
}

class _ClaimButtonState extends State<_ClaimButton> {
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    if (widget.cooldownUntil != null) _startTicker();
  }

  @override
  void didUpdateWidget(covariant _ClaimButton oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.cooldownUntil != oldWidget.cooldownUntil) {
      _ticker?.cancel();
      if (widget.cooldownUntil != null) _startTicker();
    }
  }

  void _startTicker() {
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (widget.cooldownUntil != null && DateTime.now().isAfter(widget.cooldownUntil!)) {
        _ticker?.cancel();
        setState(() {});
      } else {
        setState(() {});
      }
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cooldownUntil = widget.cooldownUntil;
    final remaining = cooldownUntil == null
        ? Duration.zero
        : cooldownUntil.difference(DateTime.now());
    final cooling = remaining > Duration.zero;
    final enabled = widget.canClaim && !widget.isClaiming && !cooling;

    String label;
    if (widget.isClaiming) {
      label = context.t('Claiming...');
    } else if (cooling) {
      label = '${context.t('Please wait')} ${remaining.inSeconds + 1}s';
    } else {
      label = context.t('Get Offer Code');
    }

    return SizedBox(
      width: double.infinity,
      height: 52,
      child: FilledButton.icon(
        onPressed: enabled ? widget.onClaim : null,
        icon: widget.isClaiming
            ? const SizedBox(
                width: 20,
                height: 20,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              )
            : const Icon(Icons.local_offer),
        label: Text(label),
        style: FilledButton.styleFrom(
          backgroundColor: AppTheme.secondaryColor,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),
    );
  }
}
