import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:cached_network_image/cached_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/themes/app_theme.dart';
import '../core/api/api_client.dart';
import '../core/models/ad_campaign.dart';

/// Ad banner specifically for report detail screens.
/// Uses getReportAd() endpoint which filters by report context/district
/// and returns coin earning info for the report owner.
class ReportAdBanner extends StatefulWidget {
  final dynamic reportId;
  final String? district;

  const ReportAdBanner({
    super.key,
    required this.reportId,
    this.district,
  });

  @override
  State<ReportAdBanner> createState() => _ReportAdBannerState();
}

class _ReportAdBannerState extends State<ReportAdBanner> {
  final _api = ApiClient.instance;
  AdCampaignModel? _ad;
  Map<String, dynamic>? _coinEarning;
  String? _reportOwnerName;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await _api.getReportAd(widget.reportId);
      final data = res.data['data'];
      if (data != null && data is Map<String, dynamic>) {
        final ad = AdCampaignModel.fromJson(data);
        _coinEarning = data['coin_earning'] as Map<String, dynamic>?;
        _reportOwnerName = data['report_owner_name'] as String?;
        _api.trackAdImpression(ad.id, reportId: widget.reportId, context: 'report').then((_) {}).catchError((_) {});
        if (mounted) setState(() => _ad = ad);
      }
    } catch (e) {
      debugPrint('ReportAdBanner load error: $e');
    }
    if (mounted) setState(() => _loaded = true);
  }

  Future<void> _onTap() async {
    final ad = _ad;
    if (ad == null) return;
    _api.trackAdClick(ad.id, reportId: widget.reportId, context: 'report').then((_) {}).catchError((_) {});
    final target = ad.targetUrl;
    if (target != null && target.isNotEmpty) {
      final uri = Uri.tryParse(target);
      if (uri != null) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || _ad == null) return const SizedBox.shrink();
    final ad = _ad!;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: Material(
        color: Colors.amber.withOpacity(0.03),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: _onTap,
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: Colors.amber.withOpacity(0.3), width: 1),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: SizedBox(
                        width: 44,
                        height: 44,
                        child: ad.image != null && ad.image!.isNotEmpty
                            ? CachedNetworkImage(
                                imageUrl: ad.image!,
                                fit: BoxFit.cover,
                                placeholder: (_, __) => _placeholder(),
                                errorWidget: (_, __, ___) => _placeholder(),
                              )
                            : _placeholder(),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                                decoration: BoxDecoration(
                                  color: Colors.amber.withOpacity(0.15),
                                  borderRadius: BorderRadius.circular(3),
                                ),
                                child: const Text('Sponsored', style: TextStyle(fontSize: 8, fontWeight: FontWeight.w700, color: Color(0xFFB8860B))),
                              ),
                              const SizedBox(width: 4),
                              Expanded(
                                child: Text(ad.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
                              ),
                            ],
                          ),
                          if (ad.content != null && ad.content!.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(ad.content!, style: TextStyle(color: AppTheme.textSecondary, fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
                          ],
                          if (ad.businessName != null && ad.businessName!.isNotEmpty)
                            Text(ad.businessName!, style: TextStyle(fontSize: 10, color: AppTheme.textSecondary, fontWeight: FontWeight.w500)),
                        ],
                      ),
                    ),
                    const Icon(Icons.open_in_new, size: 12, color: AppTheme.textSecondary),
                  ],
                ),
                if (_coinEarning != null) ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.green.withOpacity(0.06),
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: Colors.green.withOpacity(0.15)),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.monetization_on, size: 12, color: Colors.green),
                        const SizedBox(width: 4),
                        Text(
                          '${_coinEarning!['impression_value']} coins per view · ${_coinEarning!['click_value']} coins per click',
                          style: const TextStyle(fontSize: 10, color: Colors.green, fontWeight: FontWeight.w500),
                        ),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _placeholder() {
    return Container(
      color: Colors.amber.withOpacity(0.1),
      child: const Icon(Icons.campaign, size: 18, color: Colors.amber),
    );
  }
}
