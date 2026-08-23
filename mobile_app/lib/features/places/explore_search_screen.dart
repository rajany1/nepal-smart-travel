import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../core/services/localization_service.dart';
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

class _ExploreSearchScreenState extends State<ExploreSearchScreen> {
  static const _categories = [
    'Restaurants',
    'Hotels',
    'Cafe',
    'Attractions',
    'Activities',
    'Nature',
    'Shopping',
    'Transport',
    'Emergency',
  ];

  final TextEditingController _controller = TextEditingController();
  Timer? _debounce;
  String _query = '';
  String? _category;

  @override
  void initState() {
    super.initState();
    _query = widget.initialQuery ?? '';
    _category = widget.category;
    _controller.text = _query;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final p = context.read<PlaceProvider>();
      if (p.nepalPlaces.isEmpty) p.fetchNepalPlaces();
    });
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
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

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<PlaceProvider>();
    final loading = provider.isLoadingNepal && provider.nepalPlaces.isEmpty;
    final results = loading ? <PlaceModel>[] : _filter(provider.nepalPlaces);
    final hasQuery = _query.isNotEmpty || _category != null;

    return Scaffold(
      backgroundColor: AppTheme.backgroundColor,
      appBar: AppBar(
        titleSpacing: 0,
        title: TextField(
          controller: _controller,
          autofocus: widget.initialQuery == null && widget.category == null,
          textInputAction: TextInputAction.search,
          onChanged: _onQueryChanged,
          decoration: InputDecoration(
            hintText: context.t('Search places, hotels, restaurants...'),
            border: InputBorder.none,
            isCollapsed: true,
          ),
          style: const TextStyle(fontSize: AppTheme.textBase),
        ),
        actions: [
          if (_controller.text.isNotEmpty || _category != null)
            IconButton(
              icon: const Icon(Icons.close),
              onPressed: () {
                _controller.clear();
                setState(() {
                  _query = '';
                  _category = null;
                });
              },
            ),
        ],
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 52,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              children: [
                _chip(context, null, context.t('All')),
                for (final c in _categories) _chip(context, c, c),
              ],
            ),
          ),
          if (loading)
            const Expanded(
              child: Center(child: CircularProgressIndicator()),
            )
          else if (!hasQuery && results.isEmpty)
            Expanded(
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.search,
                        size: 56, color: AppTheme.textSecondary.withOpacity(0.4)),
                    const SizedBox(height: 12),
                    Text(
                      context.t('Discover places across Nepal'),
                      style: const TextStyle(
                          fontSize: AppTheme.textBase,
                          color: AppTheme.textSecondary),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      context.t('Type to search or pick a category'),
                      style: const TextStyle(
                          fontSize: AppTheme.textSm,
                          color: AppTheme.textSecondary),
                    ),
                  ],
                ),
              ),
            )
          else if (results.isEmpty)
            Expanded(
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.location_off,
                        size: 56, color: AppTheme.textSecondary),
                    const SizedBox(height: 12),
                    Text(
                      context.t('No places found'),
                      style: const TextStyle(
                          fontSize: AppTheme.textBase,
                          fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      context.t('Try a different search or category'),
                      style: const TextStyle(
                          fontSize: AppTheme.textSm,
                          color: AppTheme.textSecondary),
                    ),
                    const SizedBox(height: 16),
                    OutlinedButton(
                      onPressed: () {
                        _controller.clear();
                        setState(() {
                          _query = '';
                          _category = null;
                        });
                      },
                      child: Text(context.t('Clear filters')),
                    ),
                  ],
                ),
              ),
            )
          else
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                itemCount: results.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, i) {
                  final p = results[i];
                  return ExplorePlaceCard(
                    place: p,
                    expanded: true,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => PlaceDetailsScreen(place: p.toPlace()),
                      ),
                    ),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  Widget _chip(BuildContext context, String? value, String label) {
    final selected = _category == value;
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: ChoiceChip(
        selected: selected,
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
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        onSelected: (_) => setState(() => _category = value),
      ),
    );
  }
}