import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/themes/app_theme.dart';
import '../core/api/api_client.dart';
import '../core/models/ad_campaign.dart';

/// Maps a place category name (as shown in the app) to the backend ad
/// context slug used by the "Show on screens" targeting.
String adContextForCategory(String name) {
  final n = name.toLowerCase();
  if (n.contains('hotel') || n.contains('accommodation') || n.contains('stay')) {
    return 'hotels';
  }
  if (n.contains('restaurant') || n.contains('food') || n.contains('dining')) {
    return 'restaurants';
  }
  if (n.contains('cafe') || n.contains('coffee')) {
    return 'cafes';
  }
  if (n.contains('attraction') || n.contains('landmark') || n.contains('sightseeing')) {
    return 'attractions';
  }
  if (n.contains('activit') || n.contains('adventure') || n.contains('trek')) {
    return 'activities';
  }
  return 'nearby';
}

/// Maps a place category name to the backend target_category slug used for
/// district/category targeting scoring (null when no match).
String? adCategorySlugForCategory(String name) {
  final n = name.toLowerCase();
  if (n.contains('hotel') || n.contains('accommodation') || n.contains('stay')) {
    return 'hotel';
  }
  if (n.contains('restaurant') || n.contains('food') || n.contains('dining')) {
    return 'restaurant';
  }
  if (n.contains('cafe') || n.contains('coffee')) {
    return 'cafe';
  }
  if (n.contains('attraction') || n.contains('landmark') || n.contains('sightseeing')) {
    return 'attraction';
  }
  if (n.contains('activit') || n.contains('adventure') || n.contains('trek')) {
    return 'activity';
  }
  return null;
}

/// Self-contained compact ad banner: fetches its own ads for the given
/// context, shows the first one and records impression/click.
class AdInlineBanner extends StatefulWidget {
  final String adContext;
  final String? category;
  final String? district;

  const AdInlineBanner({
    super.key,
    this.adContext = 'explore',
    this.category,
    this.district,
  });

  @override
  State<AdInlineBanner> createState() => _AdInlineBannerState();
}

class _AdInlineBannerState extends State<AdInlineBanner> {
  final _api = ApiClient.instance;
  AdCampaignModel? _ad;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final res = await _api.getActiveAds(
        adContext: widget.adContext,
        category: widget.category,
        district: widget.district,
        limit: 1,
      );
      final data = (res.data['data'] as List<dynamic>?) ?? [];
      if (data.isNotEmpty && data.first is Map<String, dynamic>) {
        final ad = AdCampaignModel.fromJson(data.first as Map<String, dynamic>);
        _api.trackAdImpression(ad.id).then((_) {}).catchError((_) {});
        if (mounted) setState(() => _ad = ad);
      }
    } catch (_) {}
    if (mounted) setState(() => _loaded = true);
  }

  Future<void> _onTap() async {
    final ad = _ad;
    if (ad == null) return;
    _api.trackAdClick(ad.id).then((_) {}).catchError((_) {});
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
            child: Row(
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: SizedBox(
                    width: 52,
                    height: 52,
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
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.amber.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: const Text('Sponsored', style: TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: Color(0xFFB8860B))),
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(ad.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase), maxLines: 1, overflow: TextOverflow.ellipsis),
                          ),
                        ],
                      ),
                      if (ad.content != null && ad.content!.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(ad.content!, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), maxLines: 2, overflow: TextOverflow.ellipsis),
                      ],
                      if (ad.businessName != null && ad.businessName!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Row(
                          children: [
                            const Icon(Icons.store, size: 12, color: AppTheme.textSecondary),
                            const SizedBox(width: 3),
                            Flexible(
                              child: Text(ad.businessName!, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary), overflow: TextOverflow.ellipsis),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                const Icon(Icons.open_in_new, size: 14, color: AppTheme.textSecondary),
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
      child: const Icon(Icons.campaign, size: 22, color: Colors.amber),
    );
  }
}
