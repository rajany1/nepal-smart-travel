import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../providers/place_provider.dart';

class PlaceFilter {
  final int? categoryId;
  final double? radiusKm;
  final bool onlyVerified;
  final bool onlyFeatured;
  final String? search;

  PlaceFilter({
    this.categoryId,
    this.radiusKm,
    this.onlyVerified = false,
    this.onlyFeatured = false,
    this.search,
  });
}

class FilterPlacesSheet extends StatefulWidget {
  final void Function(PlaceFilter filters)? onApply;

  const FilterPlacesSheet({super.key, this.onApply});

  @override
  State<FilterPlacesSheet> createState() => _FilterPlacesSheetState();
}

class _FilterPlacesSheetState extends State<FilterPlacesSheet> {
  int? _selectedCategoryId;
  double _radiusKm = 5.0;
  bool _onlyVerified = false;
  bool _onlyFeatured = false;

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<PlaceProvider>();
    final categories = provider.categories;

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Handle
          Center(
            child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 20),

          // Title
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Filter Places',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w600),
              ),
              TextButton(
                onPressed: () {
                  setState(() {
                    _selectedCategoryId = null;
                    _radiusKm = 5.0;
                    _onlyVerified = false;
                    _onlyFeatured = false;
                  });
                },
                child: const Text('Reset', style: TextStyle(color: AppTheme.primaryColor)),
              ),
            ],
          ),
          const SizedBox(height: 16),

          // Category filter
          const Text('Category', style: TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              FilterChip(
                label: const Text('All'),
                selected: _selectedCategoryId == null,
                onSelected: (_) => setState(() => _selectedCategoryId = null),
                selectedColor: AppTheme.primaryColor.withOpacity(0.2),
                checkmarkColor: AppTheme.primaryColor,
              ),
              ...categories.where((c) => c.id != 0).map((cat) => FilterChip(
                label: Text(cat.name),
                selected: _selectedCategoryId == cat.id,
                onSelected: (selected) => setState(() => _selectedCategoryId = selected ? cat.id : null),
                selectedColor: AppTheme.primaryColor.withOpacity(0.2),
                checkmarkColor: AppTheme.primaryColor,
              )),
            ],
          ),
          const SizedBox(height: 16),

          // Radius
          const Text('Search Radius', style: TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
          const SizedBox(height: 4),
          Row(
            children: [
              Expanded(
                child: Slider.adaptive(
                  min: 1.0,
                  max: 50.0,
                  divisions: 49,
                  value: _radiusKm,
                  activeColor: AppTheme.primaryColor,
                  label: '${_radiusKm.toStringAsFixed(0)} km',
                  onChanged: (v) => setState(() => _radiusKm = v),
                ),
              ),
              SizedBox(
                width: 50,
                child: Text(
                  '${_radiusKm.toStringAsFixed(0)} km',
                  style: const TextStyle(fontWeight: FontWeight.w600, color: AppTheme.primaryColor),
                ),
              ),
            ],
          ),

          // Toggle options
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Verified places only', style: TextStyle(fontSize: 14)),
            value: _onlyVerified,
            onChanged: (v) => setState(() => _onlyVerified = v),
            activeColor: AppTheme.primaryColor,
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Featured places only', style: TextStyle(fontSize: 14)),
            value: _onlyFeatured,
            onChanged: (v) => setState(() => _onlyFeatured = v),
            activeColor: AppTheme.primaryColor,
          ),
          const SizedBox(height: 16),

          // Apply button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                final filters = PlaceFilter(
                  categoryId: _selectedCategoryId,
                  radiusKm: _radiusKm,
                  onlyVerified: _onlyVerified,
                  onlyFeatured: _onlyFeatured,
                );
                widget.onApply?.call(filters);
                Navigator.pop(context);
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primaryColor,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: const Text('Apply Filters', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
            ),
          ),
        ],
      ),
    );
  }
}