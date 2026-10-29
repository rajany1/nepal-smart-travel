import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/utils/share_helper.dart';
import 'package:intl/intl.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/place.dart';
import '../../providers/place_details_provider.dart';
import '../../widgets/image_carousel_widget.dart';
import '../../widgets/ad_inline_banner.dart';
import '../auth/login_screen.dart';
import 'place_correction_screen.dart';
import 'nearby_map_screen.dart';

class PlaceDetailsScreen extends StatefulWidget {
  final Place place;

  const PlaceDetailsScreen({super.key, required this.place});

  @override
  State<PlaceDetailsScreen> createState() => _PlaceDetailsScreenState();
}

class _PlaceDetailsScreenState extends State<PlaceDetailsScreen> {
  // Review form state
  int _reviewRating = 5;
  final _reviewTitleController = TextEditingController();
  final _reviewDescController = TextEditingController();
  bool _isSubmittingReview = false;
  DateTime? _reviewCooldownUntil;
  late final PlaceDetailsProvider _detailsProvider;

  bool get _isOsm => widget.place.id.startsWith('osm_');

  @override
  void initState() {
    super.initState();
    _detailsProvider = context.read<PlaceDetailsProvider>();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_isOsm) {
        _detailsProvider.fetchPlaceDetails(widget.place.id);
      }
      _detailsProvider.fetchPlaceReviews(widget.place.id);
    });
  }

  @override
  void dispose() {
    _reviewTitleController.dispose();
    _reviewDescController.dispose();
    // FL-28: provider captured in initState — no context.read inside dispose()
    _detailsProvider.clearDetails();
    super.dispose();
  }

  Future<void> _launchUrl(String url) async {
    final uri = Uri.parse(url.startsWith('http') ? url : 'https://$url');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  Future<void> _callPhone(String phone) async {
    final uri = Uri(scheme: 'tel', path: phone);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _sendEmail(String email) async {
    final uri = Uri(scheme: 'mailto', path: email);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  Future<void> _openDirections(double lat, double lng) async {
    if (!context.mounted) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => NearbyMapScreen(
          destinationLat: lat,
          destinationLng: lng,
          destinationName: widget.place.name,
        ),
      ),
    );
  }

  void _showReviewSheet(BuildContext context) {
    _reviewRating = 5;
    _reviewTitleController.clear();
    _reviewDescController.clear();
    _isSubmittingReview = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(builder: (ctx, setSheetState) {
          return Padding(
            padding: EdgeInsets.only(
              bottom: MediaQuery.of(ctx).viewInsets.bottom,
              left: 16, right: 16, top: 16,
            ),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text('Write a Review',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Center(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(5, (i) {
                        final starIdx = i + 1;
                        return GestureDetector(
                          onTap: () => setSheetState(() => _reviewRating = starIdx),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(horizontal: 4),
                            child: Icon(
                              starIdx <= _reviewRating ? Icons.star : Icons.star_border,
                              size: 40,
                              color: AppTheme.secondaryColor,
                            ),
                          ),
                        );
                      }),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Center(
                    child: Text(
                      ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'][_reviewRating],
                      style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _reviewTitleController,
                    decoration: InputDecoration(
                      hintText: 'Summary of your experience',
                      labelText: 'Title',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _reviewDescController,
                    decoration: InputDecoration(
                      hintText: 'Tell others about your experience...',
                      labelText: 'Review',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                    ),
                    maxLines: 4,
                    textInputAction: TextInputAction.newline,
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: (_isSubmittingReview ||
                              (_reviewCooldownUntil != null &&
                                  DateTime.now().isBefore(_reviewCooldownUntil!)))
                          ? null
                          : () async {
                              if (_reviewTitleController.text.trim().isEmpty ||
                                  _reviewDescController.text.trim().isEmpty) {
                                ScaffoldMessenger.of(ctx).showSnackBar(
                                  const SnackBar(content: Text('Please fill in title and review')),
                                );
                                return;
                              }
                              setSheetState(() => _isSubmittingReview = true);
                              try {
                                final p = widget.place;
                                await context.read<PlaceDetailsProvider>().ratePlace(
                                  p.id,
                                  rating: _reviewRating,
                                  title: _reviewTitleController.text.trim(),
                                  description: _reviewDescController.text.trim(),
                                  osmName: _isOsm ? p.name : null,
                                  osmLatitude: _isOsm ? p.latitude : null,
                                  osmLongitude: _isOsm ? p.longitude : null,
                                  osmCategory: _isOsm ? p.category : null,
                                  osmAddress: _isOsm ? p.address : null,
                                  osmDistrict: _isOsm ? p.district : null,
                                  osmPhone: _isOsm ? p.phone : null,
                                );
                                if (ctx.mounted) Navigator.pop(ctx);
                                if (context.mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(
                                      content: Text('Review submitted!'),
                                      backgroundColor: Colors.green,
                                    ),
                                  );
                                }
                              } catch (e) {
                                setSheetState(() => _isSubmittingReview = false);
                                final status = e is DioException ? e.response?.statusCode : null;
                                debugPrint(
                                  'REVIEW SUBMIT FAILED | id=${widget.place.id} | status=$status | error=$e'
                                  '${e is DioException && e.response != null ? ' | body=${e.response?.data}' : ''}',
                                );
                                ScaffoldMessenger.of(ctx).showSnackBar(
                                  SnackBar(
                                    content: Text(
                                      status == 429
                                          ? 'Too many reviews. Please try again later.'
                                          : status == 401
                                              ? 'Please log in to write a review'
                                              : status != null
                                                  ? 'Failed to submit review ($status)'
                                                  : 'Failed to submit review: ${e is DioException ? e.type.name : e}',
                                    ),
                                  ),
                                );
                                if (status == 429) {
                                  setSheetState(() =>
                                      _reviewCooldownUntil = DateTime.now().add(const Duration(seconds: 15)));
                                }
                                if (status == 401 && ctx.mounted) {
                                  Navigator.pop(ctx);
                                  Navigator.push(
                                    ctx,
                                    MaterialPageRoute(builder: (_) => const LoginScreen()),
                                  );
                                }
                              }
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primaryColor,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: _isSubmittingReview
                          ? const SizedBox(
                              height: 20, width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Text('Submit Review', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              ),
            ),
          );
        });
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<PlaceDetailsProvider>(
      builder: (context, provider, _) {
        // Only trust the provider payload when it belongs to this screen's
        // place. The provider is app-wide, so a previous place's details could
        // otherwise flash while the new request is in flight.
        final fetched = provider.currentPlace;
        final place =
            fetched != null && fetched.id == widget.place.id ? fetched : widget.place;
        final categoryColor = _categoryColor(place.category);
        final todayHours = _todayHours(place);
        final hours = _hoursEntries(place.operatingHours);

        return Scaffold(
          backgroundColor: AppTheme.backgroundColor,
          appBar: AppBar(
            elevation: 0,
            scrolledUnderElevation: 0,
            backgroundColor: AppTheme.backgroundColor,
            surfaceTintColor: Colors.transparent,
            foregroundColor: AppTheme.textPrimary,
            titleSpacing: 4,
            title: Text(
              place.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: AppTheme.textLg,
                fontWeight: FontWeight.w700,
                color: AppTheme.textPrimary,
              ),
            ),
            actions: [
              IconButton(
                tooltip: 'Share',
                icon: const Icon(Icons.share_outlined),
                onPressed: () {
                  ShareHelper.sharePlace(
                    placeId: place.id,
                    name: place.name,
                    category: place.category,
                    address: place.address,
                  );
                },
              ),
              const SizedBox(width: 4),
            ],
          ),
          body: Column(
            children: [
              if (provider.isLoading)
                const LinearProgressIndicator(
                  minHeight: 2,
                  backgroundColor: Colors.transparent,
                  color: AppTheme.primaryColor,
                ),
              Expanded(
                child: RefreshIndicator(
                  color: AppTheme.primaryColor,
                  onRefresh: () async {
                    if (!_isOsm) await provider.fetchPlaceDetails(place.id);
                    await provider.fetchPlaceReviews(place.id);
                  },
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: EdgeInsets.fromLTRB(
                      16,
                      4,
                      16,
                      28 + MediaQuery.of(context).padding.bottom,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _buildHero(place, categoryColor),
                        const SizedBox(height: 18),
                        _buildIdentity(place, categoryColor),
                        const SizedBox(height: 16),
                        _buildQuickActions(place),
                        if (provider.errorMessage != null) ...[
                          const SizedBox(height: 12),
                          _buildErrorBanner(provider, place),
                        ],
                        if (place.isOpen != null ||
                            todayHours != null ||
                            (place.todayOffer?.trim().isNotEmpty ?? false) ||
                            (place.liveEvent?.trim().isNotEmpty ?? false)) ...[
                          const SizedBox(height: 16),
                          _buildHighlights(place, todayHours),
                        ],
                        if (place.description?.trim().isNotEmpty ?? false) ...[
                          const SizedBox(height: 16),
                          _buildAbout(place),
                        ],
                        const SizedBox(height: 16),
                        _buildContact(place),
                        if (hours.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          _buildHours(place, hours),
                        ],
                        if (place.amenities.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          _buildAmenities(place),
                        ],
                        const SizedBox(height: 8),
                        AdInlineBanner(
                          adContext: 'place_detail',
                          category: place.category.toLowerCase(),
                          district: place.district,
                          persistent: true,
                        ),
                        const SizedBox(height: 8),
                        _buildReviews(provider, place),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  // ─────────────────────────────────────────────────────────────────────
  // Hero + identity
  // ─────────────────────────────────────────────────────────────────────

  Widget _buildHero(Place place, Color accent) {
    final width = MediaQuery.of(context).size.width - 32;
    final double height = (width * 0.62).clamp(190.0, 260.0).toDouble();
    final hasImages = place.images.isNotEmpty;

    return Container(
      height: height,
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: accent.withOpacity(0.16),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(22),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (hasImages)
              ImageCarouselWidget(
                images: place.images,
                height: height,
                showIndicators: true,
              )
            else
              _buildHeroPlaceholder(place, accent),
            // Bottom scrim keeps the overlays readable on bright photos.
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              height: height * 0.5,
              child: IgnorePointer(
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.bottomCenter,
                      end: Alignment.topCenter,
                      colors: [
                        Colors.black.withOpacity(0.55),
                        Colors.transparent,
                      ],
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              left: 14,
              bottom: 14,
              child: ConstrainedBox(
                constraints: BoxConstraints(maxWidth: width * 0.68),
                child: _buildHeroBadge(place, accent),
              ),
            ),
            if (place.images.length > 1)
              Positioned(
                right: 14,
                bottom: 14,
                child: Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.45),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.photo_library_outlined,
                          size: 12, color: Colors.white),
                      const SizedBox(width: 4),
                      Text(
                        '${place.images.length}',
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildHeroPlaceholder(Place place, Color accent) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [accent.withOpacity(0.9), AppTheme.primaryColor],
        ),
      ),
      child: Stack(
        children: [
          Positioned(
            right: -28,
            top: -34,
            child: Icon(
              _categoryIcon(place.category),
              size: 190,
              color: Colors.white.withOpacity(0.12),
            ),
          ),
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 76,
                  height: 76,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.16),
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Colors.white.withOpacity(0.22),
                    ),
                  ),
                  child: Icon(
                    _categoryIcon(place.category),
                    size: 38,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  place.category,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: Colors.white.withOpacity(0.9),
                    letterSpacing: 0.3,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeroBadge(Place place, Color accent) {
    final hasRating = place.averageRating > 0 || place.totalReviews > 0;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.12),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            hasRating ? Icons.star_rounded : _categoryIcon(place.category),
            size: 16,
            color: hasRating ? AppTheme.secondaryColor : accent,
          ),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              hasRating
                  ? '${place.averageRating.toStringAsFixed(1)}'
                      '${place.totalReviews > 0 ? ' · ${place.totalReviews} reviews' : ''}'
                  : place.category,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: AppTheme.textPrimary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildIdentity(Place place, Color accent) {
    final distance = _distanceLabel(place);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          place.name,
          style: const TextStyle(
            fontSize: 24,
            height: 1.15,
            fontWeight: FontWeight.w800,
            color: AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 9),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            _pill(place.category, accent),
            if (place.isVerified)
              _badge(Icons.verified_rounded, 'Verified', AppTheme.successColor),
            if (place.isFeatured)
              _badge(Icons.workspace_premium_rounded, 'Featured',
                  AppTheme.secondaryColor),
            if (distance != null)
              _badge(Icons.near_me_outlined, distance, AppTheme.textSecondary),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            ...List.generate(
              5,
              (index) => Icon(
                index < place.averageRating.round()
                    ? Icons.star_rounded
                    : Icons.star_border_rounded,
                size: 18,
                color: AppTheme.secondaryColor,
              ),
            ),
            const SizedBox(width: 8),
            Text(
              place.averageRating > 0
                  ? place.averageRating.toStringAsFixed(1)
                  : 'New',
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppTheme.textPrimary,
              ),
            ),
            const SizedBox(width: 5),
            Flexible(
              child: Text(
                place.totalReviews > 0
                    ? '(${place.totalReviews} reviews)'
                    : 'No reviews yet',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppTheme.textSecondary,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _pill(String text, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withOpacity(0.18)),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w600,
          color: color,
        ),
      ),
    );
  }

  Widget _badge(IconData icon, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
        ],
      ),
    );
  }

  // ─────────────────────────────────────────────────────────────────────
  // Actions + highlights
  // ─────────────────────────────────────────────────────────────────────

  Widget _buildQuickActions(Place place) {
    final actions = <Widget>[
      _quickAction(
        Icons.directions_outlined,
        'Directions',
        AppTheme.primaryColor,
        () => _openDirections(place.latitude, place.longitude),
      ),
      if (place.phone?.trim().isNotEmpty ?? false)
        _quickAction(
          Icons.call_outlined,
          'Call',
          AppTheme.successColor,
          () => _callPhone(place.phone!),
        ),
      _quickAction(
        Icons.share_outlined,
        'Share',
        const Color(0xFF3B82F6),
        () => ShareHelper.sharePlace(
          placeId: place.id,
          name: place.name,
          category: place.category,
          address: place.address,
        ),
      ),
      _quickAction(
        Icons.flag_outlined,
        'Report',
        AppTheme.textSecondary,
        () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (_) => PlaceCorrectionScreen(place: place),
            ),
          );
        },
      ),
    ];

    return _card(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 12),
      child: Row(
        children: [
          for (var i = 0; i < actions.length; i++) ...[
            if (i > 0)
              Container(
                width: 1,
                height: 38,
                color: AppTheme.dividerColor.withOpacity(0.8),
              ),
            Expanded(child: actions[i]),
          ],
        ],
      ),
    );
  }

  Widget _quickAction(
    IconData icon,
    String label,
    Color color,
    VoidCallback onTap,
  ) {
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 5, horizontal: 2),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(icon, size: 19, color: color),
            ),
            const SizedBox(height: 6),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildErrorBanner(PlaceDetailsProvider provider, Place place) {
    return _card(
      padding: const EdgeInsets.all(12),
      color: const Color(0xFFFFF8E8),
      borderColor: const Color(0xFFF5D48A),
      child: Row(
        children: [
          const Icon(Icons.cloud_off_outlined,
              size: 20, color: Color(0xFFB7791F)),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Some details could not be refreshed. Showing saved information.',
              style: TextStyle(
                fontSize: 12,
                color: Color(0xFF8A5A12),
                height: 1.35,
              ),
            ),
          ),
          TextButton(
            onPressed: () {
              if (!_isOsm) provider.fetchPlaceDetails(place.id);
              provider.fetchPlaceReviews(place.id);
            },
            style: TextButton.styleFrom(
              foregroundColor: const Color(0xFFB7791F),
              textStyle: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
            child: const Text('Retry'),
          ),
        ],
      ),
    );
  }

  Widget _buildHighlights(Place place, String? todayHours) {
    return Column(
      children: [
        if (place.isOpen != null || todayHours != null)
          _statusBanner(place.isOpen, todayHours),
        if (place.todayOffer?.trim().isNotEmpty ?? false) ...[
          const SizedBox(height: 10),
          _infoBanner(
            icon: Icons.local_offer_outlined,
            title: "Today's offer",
            message: place.todayOffer!.trim(),
            color: AppTheme.secondaryColor,
          ),
        ],
        if (place.liveEvent?.trim().isNotEmpty ?? false) ...[
          const SizedBox(height: 10),
          _infoBanner(
            icon: Icons.event_available_outlined,
            title: 'Live event',
            message: place.liveEvent!.trim(),
            color: const Color(0xFF8E44AD),
          ),
        ],
      ],
    );
  }

  Widget _statusBanner(bool? isOpen, String? todayHours) {
    final color = isOpen == true
        ? AppTheme.successColor
        : isOpen == false
            ? const Color(0xFFE74C3C)
            : AppTheme.primaryColor;
    final label =
        isOpen == true ? 'Open now' : (isOpen == false ? 'Closed now' : 'Hours');
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.2)),
      ),
      child: Row(
        children: [
          Container(
            width: 9,
            height: 9,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 10),
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
          if (todayHours != null) ...[
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                'Today · $todayHours',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 12,
                  color: AppTheme.textSecondary,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _infoBanner({
    required IconData icon,
    required String title,
    required String message,
    required Color color,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: color.withOpacity(0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withOpacity(0.18)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: color.withOpacity(0.14),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Icon(icon, size: 19, color: color),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: color,
                    letterSpacing: 0.2,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  message,
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.35,
                    fontWeight: FontWeight.w500,
                    color: AppTheme.textPrimary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ─────────────────────────────────────────────────────────────────────
  // About, contact, hours, amenities
  // ─────────────────────────────────────────────────────────────────────

  Widget _buildAbout(Place place) {
    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeader('About', icon: Icons.info_outline_rounded),
          const SizedBox(height: 10),
          Text(
            place.description!.trim(),
            style: const TextStyle(
              fontSize: 13.5,
              height: 1.55,
              color: AppTheme.textSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContact(Place place) {
    final rows = <Widget>[];
    final address = place.address?.trim() ?? '';
    final district = place.district?.trim() ?? '';

    rows.add(_contactRow(
      icon: Icons.location_on_outlined,
      label: 'Address',
      value: address.isNotEmpty
          ? address
          : (district.isNotEmpty ? district : 'View on map'),
      color: AppTheme.primaryColor,
      onTap: () => _openDirections(place.latitude, place.longitude),
      trailing: const Icon(
        Icons.directions_outlined,
        size: 18,
        color: AppTheme.primaryColor,
      ),
    ));

    if (place.phone?.trim().isNotEmpty ?? false) {
      rows.add(_contactRow(
        icon: Icons.call_outlined,
        label: 'Phone',
        value: place.phone!.trim(),
        color: AppTheme.successColor,
        onTap: () => _callPhone(place.phone!),
        trailing: const Icon(
          Icons.call,
          size: 17,
          color: AppTheme.successColor,
        ),
      ));
    }

    if (place.email?.trim().isNotEmpty ?? false) {
      rows.add(_contactRow(
        icon: Icons.mail_outline_rounded,
        label: 'Email',
        value: place.email!.trim(),
        color: const Color(0xFF3B82F6),
        onTap: () => _sendEmail(place.email!),
        trailing: const Icon(
          Icons.open_in_new,
          size: 17,
          color: Color(0xFF3B82F6),
        ),
      ));
    }

    if (place.website?.trim().isNotEmpty ?? false) {
      rows.add(_contactRow(
        icon: Icons.language_rounded,
        label: 'Website',
        value: place.website!.trim(),
        color: const Color(0xFF8E44AD),
        onTap: () => _launchUrl(place.website!),
        trailing: const Icon(
          Icons.open_in_new,
          size: 17,
          color: Color(0xFF8E44AD),
        ),
      ));
    }

    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeader('Contact & location',
              icon: Icons.contact_page_outlined),
          const SizedBox(height: 6),
          for (var i = 0; i < rows.length; i++) ...[
            if (i > 0)
              Divider(
                height: 1,
                color: AppTheme.dividerColor.withOpacity(0.7),
              ),
            rows[i],
          ],
        ],
      ),
    );
  }

  Widget _contactRow({
    required IconData icon,
    required String label,
    required String value,
    required Color color,
    VoidCallback? onTap,
    Widget? trailing,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 10),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: color.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, size: 19, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textSecondary,
                        letterSpacing: 0.2,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      value,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w500,
                        color: AppTheme.textPrimary,
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 8),
                trailing,
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildHours(Place place, List<MapEntry<String, String>> hours) {
    final today = _dayName(DateTime.now().weekday);
    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              _sectionHeader('Opening hours',
                  icon: Icons.schedule_rounded),
              const Spacer(),
              if (place.isOpen != null)
                Text(
                  place.isOpen! ? 'Open' : 'Closed',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: place.isOpen!
                        ? AppTheme.successColor
                        : const Color(0xFFE74C3C),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 8),
          for (final entry in hours)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(
                children: [
                  SizedBox(
                    width: 88,
                    child: Text(
                      entry.key,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: entry.key == today
                            ? FontWeight.w700
                            : FontWeight.w500,
                        color: entry.key == today
                            ? AppTheme.textPrimary
                            : AppTheme.textSecondary,
                      ),
                    ),
                  ),
                  Expanded(
                    child: Text(
                      entry.value,
                      textAlign: TextAlign.right,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: entry.key == today
                            ? FontWeight.w600
                            : FontWeight.w400,
                        color: entry.key == today
                            ? AppTheme.textPrimary
                            : AppTheme.textSecondary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildAmenities(Place place) {
    return _card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _sectionHeader('Amenities',
              icon: Icons.check_circle_outline_rounded),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: place.amenities.map((amenity) {
              return Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.07),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: AppTheme.primaryColor.withOpacity(0.14),
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.check_rounded,
                        size: 14, color: AppTheme.primaryColor),
                    const SizedBox(width: 4),
                    Text(
                      amenity,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                  ],
                ),
              );
            }).toList(),
          ),
        ],
      ),
    );
  }

  // ─────────────────────────────────────────────────────────────────────
  // Reviews
  // ─────────────────────────────────────────────────────────────────────

  Widget _buildReviews(PlaceDetailsProvider provider, Place place) {
    final loaded = provider.reviews;
    var rating = place.averageRating;
    if (rating <= 0 && loaded.isNotEmpty) {
      final sum = loaded.fold<int>(0, (total, review) => total + review.rating);
      rating = sum / loaded.length;
    }
    final count = place.totalReviews > loaded.length
        ? place.totalReviews
        : loaded.length;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            _sectionHeader('Reviews', icon: Icons.reviews_outlined),
            Flexible(
              child: TextButton.icon(
                onPressed: () => _showReviewSheet(context),
                icon: const Icon(Icons.edit_outlined, size: 16),
                label: const Text(
                  'Write a review',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                style: TextButton.styleFrom(
                  foregroundColor: AppTheme.primaryColor,
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  textStyle: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        _card(
          child: Row(
            children: [
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    rating > 0 ? rating.toStringAsFixed(1) : '—',
                    style: const TextStyle(
                      fontSize: 30,
                      height: 1,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: List.generate(
                      5,
                      (index) => Icon(
                        index < rating.round()
                            ? Icons.star_rounded
                            : Icons.star_border_rounded,
                        size: 13,
                        color: AppTheme.secondaryColor,
                      ),
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '$count reviews',
                    style: const TextStyle(
                      fontSize: 10.5,
                      color: AppTheme.textSecondary,
                    ),
                  ),
                ],
              ),
              Container(
                width: 1,
                height: 72,
                margin: const EdgeInsets.symmetric(horizontal: 16),
                color: AppTheme.dividerColor.withOpacity(0.8),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      rating > 0 ? _ratingWord(rating) : 'No reviews yet',
                      style: const TextStyle(
                        fontSize: 13.5,
                        fontWeight: FontWeight.w700,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      loaded.isNotEmpty
                          ? 'See what visitors are saying about this place.'
                          : 'Be the first to share your experience.',
                      style: const TextStyle(
                        fontSize: 12,
                        height: 1.4,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        if (provider.isLoadingReviews) ...[
          const SizedBox(height: 12),
          const LinearProgressIndicator(
            minHeight: 2,
            backgroundColor: Colors.transparent,
            color: AppTheme.primaryColor,
          ),
        ] else if (loaded.isNotEmpty) ...[
          const SizedBox(height: 12),
          for (final review in loaded) _buildReviewCard(review),
        ],
      ],
    );
  }

  Widget _buildReviewCard(Review review) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppTheme.dividerColor.withOpacity(0.7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 19,
                backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                backgroundImage: review.userAvatar != null
                    ? NetworkImage(review.userAvatar!)
                    : null,
                child: review.userAvatar == null
                    ? const Icon(
                        Icons.person_rounded,
                        size: 20,
                        color: AppTheme.primaryColor,
                      )
                    : null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      review.userName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13.5,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        ...List.generate(
                          5,
                          (index) => Icon(
                            index < review.rating
                                ? Icons.star_rounded
                                : Icons.star_border_rounded,
                            size: 13,
                            color: AppTheme.secondaryColor,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Flexible(
                          child: Text(
                            DateFormat('MMM dd, yyyy')
                                .format(review.createdAt),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 10.5,
                              color: AppTheme.textSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (review.title?.trim().isNotEmpty ?? false) ...[
            const SizedBox(height: 10),
            Text(
              review.title!.trim(),
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13.5,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
          if (review.description?.trim().isNotEmpty ?? false) ...[
            const SizedBox(height: 5),
            Text(
              review.description!.trim(),
              maxLines: 4,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 12.5,
                height: 1.45,
                color: AppTheme.textSecondary,
              ),
            ),
          ],
          if (review.images.isNotEmpty) ...[
            const SizedBox(height: 10),
            SizedBox(
              height: 64,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: review.images.length,
                separatorBuilder: (_, __) => const SizedBox(width: 8),
                itemBuilder: (context, index) {
                  return ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Image.network(
                      review.images[index],
                      width: 64,
                      height: 64,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return Container(
                          width: 64,
                          height: 64,
                          color: AppTheme.dividerColor,
                          child: const Icon(
                            Icons.image_not_supported_outlined,
                            size: 22,
                            color: AppTheme.textSecondary,
                          ),
                        );
                      },
                    ),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ─────────────────────────────────────────────────────────────────────
  // Shared pieces
  // ─────────────────────────────────────────────────────────────────────

  Widget _card({
    required Widget child,
    EdgeInsetsGeometry padding = const EdgeInsets.all(16),
    Color? color,
    Color? borderColor,
  }) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: color ?? Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: borderColor ?? AppTheme.dividerColor.withOpacity(0.7),
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: child,
    );
  }

  Widget _sectionHeader(String title, {IconData? icon}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[
          Icon(icon, size: 18, color: AppTheme.primaryColor),
          const SizedBox(width: 8),
        ],
        Text(
          title,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: AppTheme.textPrimary,
          ),
        ),
      ],
    );
  }

  String? _distanceLabel(Place place) {
    final distance = place.distanceKm;
    if (distance <= 0) return null;
    if (distance < 1) return '${(distance * 1000).round()} m away';
    return '${distance.toStringAsFixed(distance < 10 ? 1 : 0)} km away';
  }

  String _ratingWord(double rating) {
    if (rating >= 4.5) return 'Excellent';
    if (rating >= 4) return 'Very good';
    if (rating >= 3) return 'Good';
    if (rating >= 2) return 'Fair';
    return 'Poor';
  }

  static const List<String> _dayNames = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
  ];

  String _dayName(int weekday) => _dayNames[(weekday - 1).clamp(0, 6)];

  String? _todayHours(Place place) {
    final today = _dayName(DateTime.now().weekday);
    for (final entry in _hoursEntries(place.operatingHours)) {
      if (entry.key == today) return entry.value;
    }
    return null;
  }

  List<MapEntry<String, String>> _hoursEntries(Map<String, dynamic>? raw) {
    if (raw == null || raw.isEmpty) return const [];

    const aliases = <String, String>{
      'mon': 'Monday',
      'monday': 'Monday',
      'tue': 'Tuesday',
      'tues': 'Tuesday',
      'tuesday': 'Tuesday',
      'wed': 'Wednesday',
      'wednesday': 'Wednesday',
      'thu': 'Thursday',
      'thur': 'Thursday',
      'thurs': 'Thursday',
      'thursday': 'Thursday',
      'fri': 'Friday',
      'friday': 'Friday',
      'sat': 'Saturday',
      'saturday': 'Saturday',
      'sun': 'Sunday',
      'sunday': 'Sunday',
    };

    final byDay = <String, String>{};
    raw.forEach((key, value) {
      final day = aliases[key.trim().toLowerCase()];
      if (day == null) return;
      final text = _formatHoursValue(value);
      if (text != null && text.isNotEmpty) byDay[day] = text;
    });

    return [
      for (final day in _dayNames)
        if (byDay[day] != null) MapEntry(day, byDay[day]!),
    ];
  }

  String? _formatHoursValue(dynamic value) {
    if (value == null) return null;
    if (value is String) {
      final text = value.trim();
      if (text.isEmpty) return null;
      if (text.toLowerCase() == 'closed') return 'Closed';
      return _prettyHoursRange(text);
    }
    if (value is Map) {
      final open = value['open'] ?? value['from'] ?? value['start'];
      final close = value['close'] ?? value['to'] ?? value['end'];
      if (open != null && close != null) {
        return '${_prettyTime(open.toString())} – '
            '${_prettyTime(close.toString())}';
      }
      final parts = value.values
          .map((entry) => entry?.toString().trim() ?? '')
          .where((entry) => entry.isNotEmpty)
          .toList();
      return parts.isEmpty ? null : parts.join(', ');
    }
    if (value is List) {
      final parts = value
          .map(_formatHoursValue)
          .whereType<String>()
          .where((entry) => entry.isNotEmpty)
          .toList();
      return parts.isEmpty ? null : parts.join(', ');
    }
    return value.toString();
  }

  String _prettyHoursRange(String raw) {
    final parts = raw.split(RegExp(r'\s*[-–]\s*'));
    if (parts.length == 2) {
      return '${_prettyTime(parts[0])} – ${_prettyTime(parts[1])}';
    }
    return _prettyTime(raw);
  }

  String _prettyTime(String raw) {
    final match = RegExp(r'^(\d{1,2}):(\d{2})').firstMatch(raw.trim());
    if (match == null) return raw.trim();
    var hour = int.parse(match.group(1)!);
    final minute = match.group(2)!;
    final suffix = hour >= 12 ? 'PM' : 'AM';
    if (hour == 0) {
      hour = 12;
    } else if (hour > 12) {
      hour -= 12;
    }
    return '$hour:$minute $suffix';
  }

  Color _categoryColor(String category) {
    switch (category.toLowerCase()) {
      case 'restaurant':
      case 'restaurants':
      case 'food':
      case 'cafe':
        return const Color(0xFFE67E22);
      case 'hotel':
      case 'hotels':
      case 'accommodation':
        return const Color(0xFF2980B9);
      case 'attraction':
      case 'attractions':
      case 'place_of_worship':
      case 'museum':
        return const Color(0xFF8E44AD);
      case 'hospital':
      case 'clinic':
      case 'pharmacy':
      case 'blood bank':
      case 'blood_bank':
        return const Color(0xFF27AE60);
      case 'shopping':
      case 'clothes':
      case 'gift':
      case 'convenience':
        return const Color(0xFFD35400);
      case 'atm':
      case 'atms':
      case 'bank':
        return const Color(0xFF3498DB);
      default:
        return AppTheme.primaryColor;
    }
  }

  IconData _categoryIcon(String category) {
    switch (category.toLowerCase()) {
      case 'restaurant':
      case 'restaurants':
      case 'food':
        return Icons.restaurant_rounded;
      case 'cafe':
        return Icons.local_cafe_rounded;
      case 'hotel':
      case 'hotels':
      case 'accommodation':
        return Icons.hotel_rounded;
      case 'attraction':
      case 'attractions':
      case 'museum':
        return Icons.photo_camera_rounded;
      case 'place_of_worship':
        return Icons.temple_buddhist_rounded;
      case 'hospital':
      case 'clinic':
        return Icons.local_hospital_rounded;
      case 'pharmacy':
        return Icons.medication_rounded;
      case 'blood bank':
      case 'blood_bank':
        return Icons.bloodtype_rounded;
      case 'atm':
      case 'atms':
      case 'bank':
        return Icons.account_balance_rounded;
      case 'shopping':
      case 'clothes':
      case 'gift':
        return Icons.shopping_bag_rounded;
      case 'convenience':
      case 'supermarket':
        return Icons.storefront_rounded;
      case 'transport':
      case 'bus':
        return Icons.directions_bus_rounded;
      case 'nature':
      case 'park':
        return Icons.forest_rounded;
      default:
        return Icons.place_rounded;
    }
  }
}
