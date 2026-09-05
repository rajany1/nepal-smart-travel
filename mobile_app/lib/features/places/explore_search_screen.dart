import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/localization_service.dart';
import '../../core/widgets/shimmer_loading.dart';
import '../../providers/place_provider.dart';
import '../../widgets/explore_place_card.dart';
import 'place_details_screen.dart';

/// Explore search & category browse. Searches the Nepal-wide dataset
/// (cached in Redis + SQLite) client-side — instant, works offline,
/// no live Overpass hammering.
class ExploreSearchScreen extends StatefulWidget {
  final String? initialQuery;
  final String? category;

  const ExploreSearchScreen({super.key, this.initialQuery, this.category});

  @override
  State<ExploreSearchScreen> createState() => _ExploreSearchScreenState();
}

class _ExploreSearchScreenState extends State<ExploreSearchScreen>
    with TickerProviderStateMixin {
  static const _categories = [
    _Cat('Restaurants', Icons.restaurant_rounded),
    _Cat('Hotels', Icons.hotel_rounded),
    _Cat('Cafe', Icons.local_cafe_rounded),
    _Cat('Attractions', Icons.photo_camera_rounded),
    _Cat('Activities', Icons.directions_run_rounded),
    _Cat('Nature', Icons.forest_rounded),
    _Cat('Shopping', Icons.shopping_bag_rounded),
    _Cat('Transport', Icons.directions_bus_rounded),
    _Cat('Emergency', Icons.local_hospital_rounded),
    _Cat('Blood Bank', Icons.bloodtype_rounded),
  ];

  final TextEditingController _controller = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  final FocusNode _searchFocus = FocusNode();
  Timer? _debounce;
  String _query = '';
  String? _category;
  late AnimationController _fadeController;

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery ?? '';
    _category = widget.category;
    _controller.text = _query;

    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 350),
    );

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final p = context.read<PlaceProvider>();
      if (p.nepalPlaces.isEmpty) p.fetchNepalPlaces();
      if (p.featuredPlaces.isEmpty) p.fetchFeaturedPlaces();
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    _searchFocus.dispose();
    _scrollController.dispose();
    _fadeController.dispose();
    super.dispose();
  }

  void _onQueryChanged(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 300), () {
      if (mounted) setState(() => _query = v.trim());
    });
  }

  List<PlaceModel> _filter(List<PlaceModel> all) {
    final q = _query.toLowerCase();
    final sel = _category;
    final out = all.where((p) {
      if (sel != null && (p.category ?? '').isNotEmpty) {
        if (!p.category!.toLowerCase().contains(sel.toLowerCase())) {
          return false;
        }
      }
      if (q.isNotEmpty) {
        final hay = '${p.name} ${p.description ?? ''} ${p.address ?? ''} '
                '${p.district ?? ''} ${p.category ?? ''}'
            .toLowerCase();
        if (!hay.contains(q)) return false;
      }
      return true;
    }).toList();
    out.sort((a, b) {
      if (a.isFeatured != b.isFeatured) return a.isFeatured ? -1 : 1;
      return a.name.toLowerCase().compareTo(b.name.toLowerCase());
    });
    return out;
  }

  Future<void> _onRefresh() async {
    final p = context.read<PlaceProvider>();
    await p.fetchNepalPlaces(force: true);
    await p.fetchFeaturedPlaces();
  }

  void _clearFilters() {
    _controller.clear();
    _searchFocus.unfocus();
    setState(() {
      _query = '';
      _category = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<PlaceProvider>();
    final loading = provider.isLoadingNepal && provider.nepalPlaces.isEmpty;
    final results = loading ? <PlaceModel>[] : _filter(provider.nepalPlaces);
    final hasQuery = _query.isNotEmpty || _category != null;
    final featured = provider.featuredPlaces;

    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ─── Header + Search Bar ───
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.t('Explore'),
                    style: const TextStyle(
                      fontSize: AppTheme.text3xl,
                      fontWeight: FontWeight.w800,
                      color: AppTheme.textPrimary,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    context.t('Discover the best of Nepal'),
                    style: const TextStyle(
                      fontSize: AppTheme.textSm,
                      color: AppTheme.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  // Search field
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                      boxShadow: [
                        BoxShadow(
                          color: AppTheme.primaryColor.withOpacity(0.08),
                          blurRadius: 12,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: TextField(
                      controller: _controller,
                      focusNode: _searchFocus,
                      autofocus: widget.initialQuery == null && widget.category == null,
                      textInputAction: TextInputAction.search,
                      onChanged: _onQueryChanged,
                      decoration: InputDecoration(
                        hintText: context.t('Search places, hotels, restaurants...'),
                        prefixIcon: const Icon(Icons.search,
                            color: AppTheme.textSecondary),
                        suffixIcon: _controller.text.isNotEmpty || _category != null
                            ? IconButton(
                                icon: const Icon(Icons.close,
                                    color: AppTheme.textSecondary, size: 20),
                                onPressed: _clearFilters,
                              )
                            : null,
                        border: InputBorder.none,
                        contentPadding:
                            const EdgeInsets.symmetric(vertical: 14),
                      ),
                      style: const TextStyle(
                          fontSize: AppTheme.textBase,
                          color: AppTheme.textPrimary),
                    ),
                  ),
                ],
              ),
            ),

            // ─── Category Chips ───
            SizedBox(
              height: 64,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                itemCount: _categories.length + 1,
                itemBuilder: (context, index) {
                  if (index == 0) {
                    return _chip(context, null, context.t('All'),
                        Icons.explore_rounded);
                  }
                  final c = _categories[index - 1];
                  return _chip(context, c.name, c.name, c.icon);
                },
              ),
            ),

            // ─── Result count ───
            if (hasQuery && !loading)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 4),
                child: Row(
                  children: [
                    Text(
                      '${results.length} ${context.t(results.length == 1 ? 'place' : 'places')}',
                      style: const TextStyle(
                        fontSize: AppTheme.textSm,
                        fontWeight: FontWeight.w600,
                        color: AppTheme.textSecondary,
                      ),
                    ),
                    const Spacer(),
                    if (_category != null)
                      GestureDetector(
                        onTap: () => setState(() => _category = null),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppTheme.primaryColor.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                _category!,
                                style: const TextStyle(
                                  fontSize: AppTheme.textXs,
                                  fontWeight: FontWeight.w600,
                                  color: AppTheme.primaryColor,
                                ),
                              ),
                              const SizedBox(width: 2),
                              const Icon(Icons.close,
                                  size: 12, color: AppTheme.primaryColor),
                            ],
                          ),
                        ),
                      ),
                  ],
                ),
              ),

            // ─── Body ───
            Expanded(
              child: loading
                  ? _buildShimmerList()
                  : RefreshIndicator(
                      onRefresh: _onRefresh,
                      color: AppTheme.primaryColor,
                      backgroundColor: Colors.white,
                      child: _buildBody(results, hasQuery, featured),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildBody(List<PlaceModel> results, bool hasQuery, List<PlaceModel> featured) {
    if (!hasQuery && results.isEmpty) {
      return _buildEmptyDiscover();
    }
    if (results.isEmpty) {
      return _buildEmptyResults();
    }

    // If no search/category active, show featured strip + full list
    final showFeatured = !hasQuery && featured.isNotEmpty;

    return CustomScrollView(
      controller: _scrollController,
      physics: const AlwaysScrollableScrollPhysics(
        parent: BouncingScrollPhysics(),
      ),
      slivers: [
        if (showFeatured) ...[
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 0, 4),
              child: Text(
                context.t('Featured Places'),
                style: const TextStyle(
                  fontSize: AppTheme.textLg,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.textPrimary,
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: SizedBox(
              height: 220,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 12),
                itemCount: min(featured.length, 8),
                separatorBuilder: (_, __) => const SizedBox(width: 12),
                itemBuilder: (context, i) {
                  final p = featured[i];
                  return ExplorePlaceCard(
                    place: p,
                    width: 170,
                    onTap: () => _openDetail(p),
                  );
                },
              ),
            ),
          ),
        ],
        SliverToBoxAdapter(
          child: Padding(
            padding: EdgeInsets.fromLTRB(16, showFeatured ? 4 : 8, 16, 4),
            child: Text(
              hasQuery ? context.t('Search Results') : context.t('All Places'),
              style: const TextStyle(
                fontSize: AppTheme.textLg,
                fontWeight: FontWeight.w700,
                color: AppTheme.textPrimary,
              ),
            ),
          ),
        ),
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
          sliver: SliverList.separated(
            itemCount: results.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (context, i) {
              final p = results[i];
              return AnimatedBuilder(
                animation: _fadeController..forward(),
                builder: (_, child) {
                  return FadeTransition(
                    opacity: Tween<double>(begin: 0, end: 1).animate(
                      CurvedAnimation(
                        parent: _fadeController,
                        curve: Interval(
                          (i / results.length).clamp(0.0, 1.0),
                          ((i + 3) / results.length).clamp(0.0, 1.0),
                          curve: Curves.easeOut,
                        ),
                      ),
                    ),
                    child: child,
                  );
                },
                child: ExplorePlaceCard(
                  place: p,
                  expanded: true,
                  onTap: () => _openDetail(p),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  void _openDetail(PlaceModel p) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => PlaceDetailsScreen(place: p.toPlace()),
      ),
    );
  }

  Widget _buildShimmerList() {
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
      itemCount: 8,
      itemBuilder: (_, __) => const Padding(
        padding: EdgeInsets.only(bottom: 12),
        child: PlaceCardShimmer(),
      ),
    );
  }

  Widget _buildEmptyDiscover() {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.55,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.08),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.travel_explore_rounded,
                  size: 48,
                  color: AppTheme.primaryColor.withOpacity(0.5),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                context.t('Discover places across Nepal'),
                style: const TextStyle(
                  fontSize: AppTheme.textLg,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 32),
                child: Text(
                  context.t('Type to search or pick a category to explore stunning destinations, hotels, and more.'),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: AppTheme.textSm,
                    color: AppTheme.textSecondary,
                    height: 1.4,
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                alignment: WrapAlignment.center,
                children: _categories.map((c) {
                  return ActionChip(
                    avatar: Icon(c.icon, size: 16, color: AppTheme.primaryColor),
                    label: Text(c.name),
                    labelStyle: const TextStyle(
                      fontSize: AppTheme.textSm,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.primaryColor,
                    ),
                    backgroundColor: AppTheme.primaryColor.withOpacity(0.08),
                    side: BorderSide.none,
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(24)),
                    onPressed: () => setState(() => _category = c.name),
                  );
                }).toList(),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyResults() {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.55,
        child: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 90,
                height: 90,
                decoration: BoxDecoration(
                  color: AppTheme.errorColor.withOpacity(0.08),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.location_off_rounded,
                  size: 40,
                  color: AppTheme.errorColor.withOpacity(0.5),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                context.t('No places found'),
                style: const TextStyle(
                  fontSize: AppTheme.textLg,
                  fontWeight: FontWeight.w700,
                  color: AppTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                context.t('Try a different search or category'),
                style: const TextStyle(
                  fontSize: AppTheme.textSm,
                  color: AppTheme.textSecondary,
                ),
              ),
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: _clearFilters,
                icon: const Icon(Icons.refresh, size: 18),
                label: Text(context.t('Clear filters')),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.primaryColor,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                  textStyle: const TextStyle(
                    fontSize: AppTheme.textSm,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _chip(BuildContext context, String? value, String label, IconData icon) {
    final selected = _category == value;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        child: ChoiceChip(
          selected: selected,
          avatar: Icon(
            icon,
            size: 16,
            color: selected ? Colors.white : AppTheme.textSecondary,
          ),
          label: Text(label),
          showCheckmark: false,
          selectedColor: AppTheme.primaryColor,
          backgroundColor: Colors.white,
          labelStyle: TextStyle(
            color: selected ? Colors.white : AppTheme.textPrimary,
            fontSize: AppTheme.textSm,
            fontWeight: FontWeight.w600,
          ),
          side: BorderSide(
            color: selected ? AppTheme.primaryColor : AppTheme.dividerColor,
            width: selected ? 1.5 : 1,
          ),
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
          onSelected: (_) => setState(() => _category = value),
        ),
      ),
    );
  }
}

class _Cat {
  final String name;
  final IconData icon;
  const _Cat(this.name, this.icon);
}
