import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import '../config/themes/app_theme.dart';
import '../features/places/utils/category_utils.dart';
import '../providers/place_provider.dart';
import 'package:cached_network_image/cached_network_image.dart';

/// Polished place card used across Explore: horizontal strip + full-width list.
class ExplorePlaceCard extends StatelessWidget {
  final PlaceModel place;
  final double? width;
  final bool expanded;
  final VoidCallback? onTap;

  const ExplorePlaceCard({
    super.key,
    required this.place,
    this.width,
    this.expanded = false,
    this.onTap,
  });

  String? get _image => place.images.isNotEmpty ? place.images.first : null;

  String get _metaLine {
    final parts = <String>[
      if (place.district != null && place.district!.isNotEmpty) place.district!,
      if (place.distanceKm != null && place.distanceKm! > 0)
        '${place.distanceKm!.toStringAsFixed(1)} km',
    ];
    return parts.join(' · ');
  }

  String get _ratingLabel {
    final r = place.averageRating;
    if (r == null || r <= 0) return 'New';
    return r.toStringAsFixed(1);
  }

  @override
  Widget build(BuildContext context) {
    return expanded ? _buildListCard(context) : _buildStripCard(context);
  }

  // ---------- Strip card (horizontal carousels) ----------
  Widget _buildStripCard(BuildContext context) {
    final categoryColor = getCategoryColor(place.category);
    return GestureDetector(
      onTap: onTap,
      child: SizedBox(
        width: width ?? 190,
        child: Card(
          margin: EdgeInsets.zero,
          clipBehavior: Clip.antiAlias,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          elevation: 0,
          color: AppTheme.surfaceColor,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(
                height: 110,
                width: double.infinity,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    _image != null
                        ? CachedNetworkImage(
                            imageUrl: _image!,
                            fit: BoxFit.cover,
                            errorWidget: (_, __, ___) =>
                                _iconFallback(categoryColor),
                          )
                        : _iconFallback(categoryColor),
                    if (place.isFeatured)
                      Positioned(
                        top: 8,
                        left: 8,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppTheme.secondaryColor,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.star,
                                  size: 11, color: Colors.white),
                              SizedBox(width: 3),
                              Text(
                                'FEATURED',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 0.6,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              place.name,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: AppTheme.textBase,
                                fontWeight: FontWeight.w700,
                                height: 1.15,
                              ),
                            ),
                          ),
                          if (place.isVerified) ...[
                            const SizedBox(width: 4),
                            const Icon(Icons.verified,
                                size: 16, color: AppTheme.blueTick),
                          ],
                        ],
                      ),
                      if (_metaLine.isNotEmpty) ...[
                        const SizedBox(height: 3),
                        Text(
                          _metaLine,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                              color: AppTheme.textSecondary,
                              fontSize: AppTheme.textXs),
                        ),
                      ],
                      const Spacer(),
                      Row(
                        children: [
                          const Icon(Icons.star,
                              size: 14, color: AppTheme.secondaryColor),
                          const SizedBox(width: 3),
                          Text(
                            _ratingLabel,
                            style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: AppTheme.textSm),
                          ),
                          if (place.totalReviews > 0) ...[
                            const SizedBox(width: 3),
                            Text(
                              '(${place.totalReviews})',
                              style: const TextStyle(
                                  color: AppTheme.textSecondary,
                                  fontSize: AppTheme.textXs),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ---------- Full-width list card (search results) ----------
  Widget _buildListCard(BuildContext context) {
    final categoryColor = getCategoryColor(place.category);
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      elevation: 0,
      color: AppTheme.surfaceColor,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: SizedBox(
                  width: 88,
                  height: 88,
                  child: _image != null
                      ? CachedNetworkImage(
                          imageUrl: _image!,
                          fit: BoxFit.cover,
                          errorWidget: (_, __, ___) =>
                              _iconFallback(categoryColor),
                        )
                      : _iconFallback(categoryColor),
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
                          child: Text(
                            place.name,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: AppTheme.textBase,
                              fontWeight: FontWeight.w700,
                              height: 1.15,
                            ),
                          ),
                        ),
                        if (place.isVerified)
                          const Icon(Icons.verified,
                              size: 17, color: AppTheme.blueTick),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      place.category ?? context.t('Place'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                          color: categoryColor,
                          fontSize: AppTheme.textSm,
                          fontWeight: FontWeight.w600),
                    ),
                    if (_metaLine.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        _metaLine,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            color: AppTheme.textSecondary,
                            fontSize: AppTheme.textXs),
                      ),
                    ],
                    const SizedBox(height: 5),
                    Row(
                      children: [
                        const Icon(Icons.star,
                            size: 14, color: AppTheme.secondaryColor),
                        const SizedBox(width: 3),
                        Text(
                          _ratingLabel,
                          style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: AppTheme.textSm),
                        ),
                        if (place.totalReviews > 0) ...[
                          const SizedBox(width: 3),
                          Text(
                            '(${place.totalReviews} ${context.t('reviews')})',
                            style: const TextStyle(
                                color: AppTheme.textSecondary,
                                fontSize: AppTheme.textXs),
                          ),
                        ],
                        const Spacer(),
                        if (place.isFeatured)
                          const Icon(Icons.star,
                              size: 15, color: AppTheme.secondaryColor),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _iconFallback(Color color) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color.withOpacity(0.25), color.withOpacity(0.08)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Icon(
        getCategoryIcon(place.category),
        size: expanded ? 34 : 40,
        color: color,
      ),
    );
  }
}