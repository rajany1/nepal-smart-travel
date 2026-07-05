import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:url_launcher/url_launcher.dart';
import '../config/themes/app_theme.dart';
import '../core/models/ad_campaign.dart';

class AdPlaceCard extends StatelessWidget {
  final AdCampaignModel ad;

  const AdPlaceCard({super.key, required this.ad});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Material(
        color: Colors.amber.withOpacity(0.03),
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => _onTap(context),
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
                    width: 60,
                    height: 60,
                    child: ad.image != null && ad.image!.isNotEmpty
                        ? CachedNetworkImage(
                            imageUrl: ad.image!,
                            width: 60,
                            height: 60,
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
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(ad.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase), maxLines: 1, overflow: TextOverflow.ellipsis),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      if (ad.content != null && ad.content!.isNotEmpty)
                        Text(ad.content!, style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), maxLines: 2, overflow: TextOverflow.ellipsis),
                      if (ad.businessName != null && ad.businessName!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Row(
                          children: [
                            const Icon(Icons.store, size: 12, color: AppTheme.textSecondary),
                            const SizedBox(width: 3),
                            Text(ad.businessName!, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
                          ],
                        ),
                      ],
                      if (ad.targetDistrict != null && ad.targetDistrict!.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Row(
                          children: [
                            const Icon(Icons.location_on, size: 12, color: AppTheme.textSecondary),
                            const SizedBox(width: 3),
                            Text(ad.targetDistrict!, style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
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
      child: const Icon(Icons.campaign, size: 24, color: Colors.amber),
    );
  }

  void _onTap(BuildContext context) {
    if (ad.targetUrl != null && ad.targetUrl!.isNotEmpty) {
      launchUrl(Uri.parse(ad.targetUrl!), mode: LaunchMode.externalApplication);
    }
  }
}

class AdReportCard extends StatelessWidget {
  final AdCampaignModel ad;

  const AdReportCard({super.key, required this.ad});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      elevation: 0,
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () => _onTap(context),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.amber.withOpacity(0.25), width: 1.2),
          ),
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: Colors.amber.withOpacity(0.15),
                child: const Icon(Icons.campaign, color: Color(0xFFB8860B), size: 20),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(ad.businessName ?? ad.name, style: TextStyle(fontSize: AppTheme.textBase + 1, fontWeight: FontWeight.w600)),
                const SizedBox(height: 2),
                Row(children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                    decoration: BoxDecoration(color: Colors.amber.withOpacity(0.12), borderRadius: BorderRadius.circular(4)),
                    child: Text('Sponsored', style: TextStyle(fontSize: AppTheme.textXs, fontWeight: FontWeight.w700, color: Color(0xFFB8860B))),
                  ),
                  if (ad.targetCategory != null) Text(ad.targetCategory!, style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm)),
                ]),
              ])),
            ]),
            const SizedBox(height: 12),
            Text(ad.name, style: TextStyle(fontSize: AppTheme.textLg, fontWeight: FontWeight.w700)),
            if (ad.content != null && ad.content!.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(ad.content!, style: TextStyle(fontSize: AppTheme.textBase, height: 1.6)),
            ],
            if (ad.image != null && ad.image!.isNotEmpty) ...[
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: CachedNetworkImage(
                  imageUrl: ad.image!,
                  height: 200,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  placeholder: (_, __) => Container(height: 200, color: Colors.amber.withOpacity(0.05), child: const Center(child: CircularProgressIndicator(strokeWidth: 2))),
                  errorWidget: (_, __, ___) => Container(height: 200, color: Colors.amber.withOpacity(0.05), child: const Icon(Icons.broken_image, color: AppTheme.textSecondary)),
                ),
              ),
            ],
            const SizedBox(height: 14),
            Row(children: [
              Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), decoration: BoxDecoration(color: Colors.amber.withOpacity(0.12), borderRadius: BorderRadius.circular(20)), child: Row(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.touch_app, size: 16, color: Color(0xFFB8860B)), const SizedBox(width: 4), const Text('Learn More', style: TextStyle(color: Color(0xFFB8860B), fontWeight: FontWeight.w600))])),
              if (ad.targetDistrict != null) ...[const SizedBox(width: 12), Expanded(child: Text(ad.targetDistrict!, style: TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm), overflow: TextOverflow.ellipsis))],
            ]),
          ]),
        ),
      ),
    );
  }

  void _onTap(BuildContext context) {
    if (ad.targetUrl != null && ad.targetUrl!.isNotEmpty) {
      launchUrl(Uri.parse(ad.targetUrl!), mode: LaunchMode.externalApplication);
    }
  }
}
