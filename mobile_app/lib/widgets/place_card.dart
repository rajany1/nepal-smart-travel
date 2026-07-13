import 'package:flutter/material.dart';
import '../config/themes/app_theme.dart';

class PlaceCard extends StatelessWidget {
  final String name;
  final String? category;
  final String? district;
  final double? rating;
  final int? reviewCount;
  final String? imageUrl;
  final String? distance;
  final bool isVerified;
  final VoidCallback? onTap;

  const PlaceCard({
    super.key,
    required this.name,
    this.category,
    this.district,
    this.rating,
    this.reviewCount,
    this.imageUrl,
    this.distance,
    this.isVerified = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      elevation: 1,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: SizedBox(
                  width: 64,
                  height: 64,
                  child: imageUrl != null && imageUrl!.isNotEmpty
                      ? Image.network(imageUrl!, fit: BoxFit.cover, errorBuilder: (_, __, ___) => _placeholderImage())
                      : _placeholderImage(),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textBase), maxLines: 1, overflow: TextOverflow.ellipsis),
                        ),
                        if (isVerified) const Icon(Icons.verified, size: 16, color: AppTheme.blueTick),
                      ],
                    ),
                    const SizedBox(height: 4),
                    if (category != null || district != null)
                      Text(
                        [category, district].where((e) => e != null).join(' • '),
                        style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textSm),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        if (rating != null) ...[
                          const Icon(Icons.star, size: 14, color: AppTheme.secondaryColor),
                          const SizedBox(width: 2),
                          Text(rating!.toStringAsFixed(1), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: AppTheme.textSm)),
                          if (reviewCount != null) Text(' ($reviewCount)', style: const TextStyle(color: AppTheme.textSecondary, fontSize: AppTheme.textXs)),
                          const SizedBox(width: 12),
                        ],
                        if (distance != null) ...[
                          const Icon(Icons.location_on, size: 12, color: AppTheme.textSecondary),
                          const SizedBox(width: 2),
                          Text(distance ?? '', style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary)),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, size: 18, color: AppTheme.textSecondary),
            ],
          ),
        ),
      ),
    );
  }

  Widget _placeholderImage() {
    return Container(
      color: AppTheme.dividerColor.withOpacity(0.3),
      child: const Icon(Icons.place, color: AppTheme.textSecondary, size: 28),
    );
  }
}
