import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:intl/intl.dart';
import '../../config/themes/app_theme.dart';
import '../../core/models/place.dart';
import '../../providers/place_details_provider.dart';
import '../../widgets/image_carousel_widget.dart';
import '../../core/services/session_manager.dart';

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

  bool get _isOsm => widget.place.id.startsWith('osm_');

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final provider = context.read<PlaceDetailsProvider>();
      if (!_isOsm) {
        provider.fetchPlaceDetails(widget.place.id);
      }
      provider.fetchPlaceReviews(widget.place.id);
    });
  }

  @override
  void dispose() {
    _reviewTitleController.dispose();
    _reviewDescController.dispose();
    context.read<PlaceDetailsProvider>().clearDetails();
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
    final uri = Uri.parse('https://maps.google.com/maps?q=$lat,$lng');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
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
                      onPressed: _isSubmittingReview
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
                                ScaffoldMessenger.of(ctx).showSnackBar(
                                  const SnackBar(content: Text('Failed to submit review')),
                                );
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
        final place = provider.currentPlace ?? widget.place;

        return Scaffold(
          backgroundColor: Colors.grey[50],
          appBar: AppBar(
            elevation: 0,
            backgroundColor: Colors.white,
            foregroundColor: AppTheme.textPrimary,
            title: Text(
              place.name,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: AppTheme.textXl, fontWeight: FontWeight.bold),
            ),
            actions: [
              IconButton(
                icon: const Icon(Icons.share),
                onPressed: () {
                  // Share functionality can be added
                },
              ),
            ],
          ),
          body: provider.isLoading
              ? const Center(child: CircularProgressIndicator())
              : SingleChildScrollView(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Image Carousel
                      if (place.images.isNotEmpty)
                        Container(
                          margin: const EdgeInsets.all(12),
                          height: 280,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withOpacity(0.08),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(16),
                            child: ImageCarouselWidget(
                              images: place.images,
                              height: 280,
                              showIndicators: true,
                            ),
                          ),
                        ),
                      
                      // Place Info Card
                      Container(
                        margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppTheme.dividerColor, width: 0.5),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Name & Category
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        place.name,
                                        style: const TextStyle(
                                          fontSize: AppTheme.text2xl,
                                          fontWeight: FontWeight.bold,
                                          color: AppTheme.textPrimary,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Row(
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(
                                                horizontal: 8, vertical: 4),
                                            decoration: BoxDecoration(
                                              color: AppTheme.primaryColor
                                                  .withOpacity(0.1),
                                              borderRadius:
                                                  BorderRadius.circular(6),
                                            ),
                                            child: Text(
                                              place.category,
                                              style: const TextStyle(
                                                fontSize: AppTheme.textSm,
                                                fontWeight: FontWeight.w600,
                                                color: AppTheme.primaryColor,
                                              ),
                                            ),
                                          ),
                                          if (place.isVerified) ...[
                                            const SizedBox(width: 8),
                                            const Icon(Icons.verified,
                                                size: 16,
                                                color: AppTheme.successColor),
                                            const SizedBox(width: 4),
                                            const Text('Verified',
                                                style: TextStyle(
                                                  fontSize: 11,
                                                  color: AppTheme.successColor,
                                                  fontWeight: FontWeight.w600,
                                                )),
                                          ],
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            
                            // Rating & Reviews
                            Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 10),
                              decoration: BoxDecoration(
                                color: AppTheme.primaryColor.withOpacity(0.05),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.star,
                                      size: 20,
                                      color: AppTheme.secondaryColor),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          '${place.averageRating.toStringAsFixed(1)}/5.0',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.bold,
                                            fontSize: AppTheme.textLg,
                                            color: AppTheme.textPrimary,
                                          ),
                                        ),
                                        Text(
                                          '${place.totalReviews} reviews',
                                          style: const TextStyle(
                                            fontSize: AppTheme.textSm,
                                            color: AppTheme.textSecondary,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Description
                      if (place.description != null &&
                          place.description!.isNotEmpty) ...[
                        Padding(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'About',
                                style: TextStyle(
                                  fontSize: AppTheme.textLg,
                                  fontWeight: FontWeight.bold,
                                  color: AppTheme.textPrimary,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                place.description!,
                                style: const TextStyle(
                                  fontSize: AppTheme.textBase,
                                  color: AppTheme.textSecondary,
                                  height: 1.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],

                      // Contact & Location
                      Container(
                        margin: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 12),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                              color: AppTheme.dividerColor, width: 0.5),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Contact & Location',
                              style: TextStyle(
                                fontSize: AppTheme.textLg,
                                fontWeight: FontWeight.bold,
                                color: AppTheme.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 12),
                            
                            // Address
                            if (place.address != null &&
                                place.address!.isNotEmpty)
                              _buildContactItem(
                                icon: Icons.location_on,
                                label: 'Address',
                                value: place.address!,
                                onTap: () => _openDirections(
                                    place.latitude, place.longitude),
                                trailing: const Icon(Icons.directions,
                                    size: 18, color: AppTheme.primaryColor),
                              ),
                            
                            // Phone
                            if (place.phone != null &&
                                place.phone!.isNotEmpty) ...[
                              const SizedBox(height: 12),
                              _buildContactItem(
                                icon: Icons.phone,
                                label: 'Phone',
                                value: place.phone!,
                                onTap: () => _callPhone(place.phone!),
                                trailing: const Icon(Icons.call,
                                    size: 18, color: AppTheme.successColor),
                              ),
                            ],
                            
                            // Email
                            if (place.email != null &&
                                place.email!.isNotEmpty) ...[
                              const SizedBox(height: 12),
                              _buildContactItem(
                                icon: Icons.email,
                                label: 'Email',
                                value: place.email!,
                                onTap: () => _sendEmail(place.email!),
                                trailing: const Icon(Icons.mail,
                                    size: 18, color: AppTheme.primaryColor),
                              ),
                            ],
                            
                            // Website
                            if (place.website != null &&
                                place.website!.isNotEmpty) ...[
                              const SizedBox(height: 12),
                              _buildContactItem(
                                icon: Icons.language,
                                label: 'Website',
                                value: place.website!,
                                onTap: () => _launchUrl(place.website!),
                                trailing: const Icon(Icons.open_in_new,
                                    size: 18, color: AppTheme.primaryColor),
                              ),
                            ],
                          ],
                        ),
                      ),

                      // Amenities
                      if (place.amenities.isNotEmpty) ...[
                        Padding(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 12),
                          child: const Text(
                            'Amenities',
                            style: TextStyle(
                              fontSize: AppTheme.textLg,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                        ),
                        Container(
                          margin: const EdgeInsets.symmetric(horizontal: 12),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                                color: AppTheme.dividerColor, width: 0.5),
                          ),
                          child: Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: place.amenities
                                .map((amenity) => Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 12, vertical: 6),
                                      decoration: BoxDecoration(
                                        color: AppTheme.primaryColor
                                            .withOpacity(0.08),
                                        borderRadius:
                                            BorderRadius.circular(16),
                                        border: Border.all(
                                          color: AppTheme.primaryColor
                                              .withOpacity(0.2),
                                        ),
                                      ),
                                      child: Text(
                                        amenity,
                                        style: const TextStyle(
                                          fontSize: AppTheme.textSm,
                                          color: AppTheme.textPrimary,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                    ))
                                .toList(),
                          ),
                        ),
                      ],

                      // Write a Review
                      Padding(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 8),
                        child: SizedBox(
                          width: double.infinity,
                          child: OutlinedButton.icon(
                            onPressed: () => _showReviewSheet(context),
                            icon: const Icon(Icons.edit, size: 18),
                            label: const Text('Write a Review'),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppTheme.primaryColor,
                              side: BorderSide(color: AppTheme.primaryColor.withOpacity(0.3)),
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                          ),
                        ),
                      ),

                      // Reviews Section
                      if (provider.reviews.isNotEmpty) ...[
                        Padding(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 16, vertical: 8),
                          child: Text(
                            'Reviews (${provider.reviews.length})',
                            style: const TextStyle(
                              fontSize: AppTheme.textLg,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                        ),
                        ListView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          padding: const EdgeInsets.symmetric(horizontal: 12),
                          itemCount: provider.reviews.length,
                          itemBuilder: (context, index) {
                            final review = provider.reviews[index];
                            return _buildReviewCard(review);
                          },
                        ),
                      ],

                      const SizedBox(height: 24),
                    ],
                  ),
                ),
        );
      },
    );
  }

  Widget _buildContactItem({
    required IconData icon,
    required String label,
    required String value,
    VoidCallback? onTap,
    Widget? trailing,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Row(
            children: [
              Icon(icon, size: 20, color: AppTheme.primaryColor),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppTheme.textSecondary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      value,
                      style: const TextStyle(
                        fontSize: AppTheme.textBase,
                        color: AppTheme.textPrimary,
                        fontWeight: FontWeight.w500,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
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

  Widget _buildReviewCard(Review review) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.dividerColor, width: 0.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                backgroundImage: review.userAvatar != null
                    ? NetworkImage(review.userAvatar!)
                    : null,
                child: review.userAvatar == null
                    ? Icon(Icons.person,
                        size: 18, color: AppTheme.primaryColor)
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      review.userName,
                      style: const TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 13,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    Row(
                      children: [
                        ...List.generate(
                          5,
                          (index) => Icon(
                            index < review.rating ? Icons.star : Icons.star_border,
                            size: 14,
                            color: AppTheme.secondaryColor,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          DateFormat('MMM dd, yyyy')
                              .format(review.createdAt),
                          style: const TextStyle(
                            fontSize: 11,
                            color: AppTheme.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (review.title != null && review.title!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              review.title!,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 13,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
          if (review.description != null && review.description!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              review.description!,
              style: const TextStyle(
                fontSize: AppTheme.textSm,
                color: AppTheme.textSecondary,
                height: 1.4,
              ),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (review.images.isNotEmpty) ...[
            const SizedBox(height: 8),
            SizedBox(
              height: 60,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                itemCount: review.images.length,
                itemBuilder: (context, index) {
                  return Container(
                    width: 60,
                    margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(8),
                      color: Colors.grey[200],
                    ),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: Image.network(
                        review.images[index],
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            const Center(
                          child: Icon(Icons.image_not_supported, size: 24),
                        ),
                      ),
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
}
