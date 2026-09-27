import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../providers/report_provider.dart';
import '../../../../core/models/report_category.dart';
import '../../../../config/themes/app_theme.dart';
import 'more_categories_sheet.dart';
import 'category_form_sheet.dart';

/// Bottom sheet for selecting a report category
/// Shows featured categories + "More categories" button
class CategorySelectionSheet extends StatefulWidget {
  final Function(ReportCategory) onCategorySelected;

  const CategorySelectionSheet({
    super.key,
    required this.onCategorySelected,
  });

  @override
  State<CategorySelectionSheet> createState() => _CategorySelectionSheetState();
}

class _CategorySelectionSheetState extends State<CategorySelectionSheet> {
  late final ReportProvider _reportProvider;

  @override
  void initState() {
    super.initState();
    _reportProvider = context.read<ReportProvider>();
    // Load featured categories if not already loaded
    if (_reportProvider.featuredCategories.isEmpty && !_reportProvider.isLoadingFeatured) {
      _reportProvider.fetchFeaturedCategories();
    }
    // Load all categories for "More" sheet
    if (_reportProvider.categoryGroups.isEmpty && !_reportProvider.isLoadingCategories) {
      _reportProvider.fetchCategories();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<ReportProvider>(
      builder: (context, provider, child) {
        return Container(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
            left: 16,
            right: 16,
            top: 16,
          ),
          decoration: const BoxDecoration(
            color: AppTheme.backgroundColor,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Handle bar
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Header
              Text(
                context.t('What do you want to report?'),
                style: const TextStyle(
                  fontSize: AppTheme.text2xl,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.textPrimary,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                context.t('Share useful info around you quickly'),
                style: const TextStyle(
                  fontSize: AppTheme.textBase,
                  color: AppTheme.textSecondary,
                ),
              ),
              const SizedBox(height: 20),

              // Featured Categories
              if (provider.isLoadingFeatured)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                )
              else if (provider.featuredCategories.isEmpty)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No categories available'),
                  ),
                )
              else
                _buildFeaturedCategories(provider),

              const SizedBox(height: 16),

              // More Categories Button
              _buildMoreCategoriesButton(provider),

              const SizedBox(height: 16),
            ],
          ),
        );
      },
    );
  }

  Widget _buildFeaturedCategories(ReportProvider provider) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.t('Most Used'),
          style: const TextStyle(
            fontSize: AppTheme.textLg,
            fontWeight: FontWeight.w600,
            color: AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 0.85,
          ),
          itemCount: provider.featuredCategories.length,
          itemBuilder: (context, index) {
            final category = provider.featuredCategories[index];
            return _CategoryCard(
              category: category,
              isFeatured: true,
              onTap: () => _selectCategory(category),
            );
          },
        ),
      ],
    );
  }

  Widget _buildMoreCategoriesButton(ReportProvider provider) {
    return SizedBox(
      width: double.infinity,
      child: OutlinedButton.icon(
        onPressed: () => _showMoreCategoriesSheet(provider),
        icon: const Icon(Icons.expand_more, size: 20),
        label: Text(
          context.t('More categories'),
          style: const TextStyle(
            fontSize: AppTheme.textBase,
            fontWeight: FontWeight.w600,
          ),
        ),
        style: OutlinedButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 14),
          side: BorderSide(color: AppTheme.primaryColor.withOpacity(0.5)),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          foregroundColor: AppTheme.primaryColor,
        ),
      ),
    );
  }

  void _selectCategory(ReportCategory category) {
    Navigator.pop(context);
    widget.onCategorySelected(category);
  }

  void _showMoreCategoriesSheet(ReportProvider provider) {
    Navigator.pop(context);
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => MoreCategoriesSheet(
        categoryGroups: provider.categoryGroups,
        onCategorySelected: _selectCategory,
      ),
    );
  }
}

/// Individual category card in the grid
class _CategoryCard extends StatelessWidget {
  final ReportCategory category;
  final bool isFeatured;
  final VoidCallback onTap;

  const _CategoryCard({
    required this.category,
    required this.isFeatured,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final displayName = category.getLocalizedName(context);
    final iconData = IconMapper.getIconData(category.icon ?? 'assignment');
    final categoryColor = IconMapper.getCategoryColor(category.icon);

    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        curve: Curves.easeOut,
        decoration: BoxDecoration(
          color: AppTheme.surfaceColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isFeatured 
                ? categoryColor.withOpacity(0.3) 
                : AppTheme.dividerColor.withOpacity(0.5),
            width: isFeatured ? 2 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: categoryColor.withOpacity(isFeatured ? 0.15 : 0.08),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  // Icon
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: categoryColor.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      iconData,
                      size: 26,
                      color: categoryColor,
                    ),
                  ),
                  const SizedBox(height: 10),
                  // Name
                  Text(
                    displayName,
                    textAlign: TextAlign.center,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: AppTheme.textSm,
                      fontWeight: FontWeight.w600,
                      color: AppTheme.textPrimary,
                    ),
                  ),
                  if (isFeatured) ...[
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppTheme.primaryColor.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        context.t('Popular'),
                        style: const TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w600,
                          color: AppTheme.primaryColor,
                        ),
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
}