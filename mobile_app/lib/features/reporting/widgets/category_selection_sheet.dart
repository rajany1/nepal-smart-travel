import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../providers/report_provider.dart';
import '../../../../core/models/report_category.dart' as rc;
import '../../../../config/themes/app_theme.dart';
import 'more_categories_sheet.dart';

/// Bottom sheet for selecting what to report.
/// Shows featured report OPTIONS (with severity-colored outlines) plus a
/// "More categories" button that opens the full group/category browser.
class CategorySelectionSheet extends StatefulWidget {
  final void Function(rc.ReportCategory category, rc.ReportCategoryOption? option)
      onCategorySelected;

  const CategorySelectionSheet({
    super.key,
    required this.onCategorySelected,
  });

  @override
  State<CategorySelectionSheet> createState() => _CategorySelectionSheetState();
}

class _CategorySelectionSheetState extends State<CategorySelectionSheet> {
  late final ReportProvider _reportProvider;
  int? _loadingOptionId;
  bool _isOpening = false;

  @override
  void initState() {
    super.initState();
    _reportProvider = context.read<ReportProvider>();
    if (_reportProvider.featuredOptions.isEmpty &&
        !_reportProvider.isLoadingFeaturedOptions) {
      _reportProvider.fetchFeaturedOptions(limit: 9);
    }
    // Fallback + "More" sheet data
    if (_reportProvider.featuredCategories.isEmpty &&
        !_reportProvider.isLoadingFeatured) {
      _reportProvider.fetchFeaturedCategories();
    }
    if (_reportProvider.categoryGroups.isEmpty &&
        !_reportProvider.isLoadingCategories) {
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

              // Featured report options
              if (provider.isLoadingFeaturedOptions && provider.featuredOptions.isEmpty)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                )
              else if (provider.featuredOptions.isNotEmpty)
                _buildFeaturedOptions(provider)
              else if (provider.isLoadingFeatured)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(strokeWidth: 2),
                  ),
                )
              else if (provider.featuredCategories.isNotEmpty)
                _buildFeaturedCategories(provider)
              else
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No categories available'),
                  ),
                ),

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

  Widget _buildFeaturedOptions(ReportProvider provider) {
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
          itemCount: provider.featuredOptions.length,
          itemBuilder: (context, index) {
            final option = provider.featuredOptions[index];
            return _OptionCard(
              option: option,
              isLoading: _loadingOptionId == option.id,
              onTap: () => _selectOption(option),
            );
          },
        ),
      ],
    );
  }

  /// Fallback when the options endpoint is unavailable: featured categories.
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
              onTap: () {
                if (_isOpening) return;
                _isOpening = true;
                widget.onCategorySelected(category, null);
              },
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

  /// Loads the full category form config for an option, closes this sheet and
  /// hands (category, option) to the caller so it can open the report form.
  Future<void> _selectOption(rc.ReportCategoryOption option) async {
    if (_loadingOptionId != null || _isOpening) return;
    setState(() => _loadingOptionId = option.id);

    final config =
        await context.read<ReportProvider>().getCategoryFormConfig(option.categoryId);
    if (!mounted) return;

    final category = config ?? option.parentCategory;
    if (category == null) {
      setState(() => _loadingOptionId = null);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(context.t('Failed to load category')),
            backgroundColor: AppTheme.errorColor,
          ),
        );
      }
      return;
    }

    _isOpening = true;
    Navigator.pop(context);
    widget.onCategorySelected(category, option);
  }

  void _showMoreCategoriesSheet(ReportProvider provider) {
    Navigator.pop(context);
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => MoreCategoriesSheet(
        categoryGroups: provider.categoryGroups,
        onCategorySelected: widget.onCategorySelected,
      ),
    );
  }
}

/// Option card in the grid, outlined by severity: low -> green,
/// medium -> blue, high/critical -> red.
class _OptionCard extends StatelessWidget {
  final rc.ReportCategoryOption option;
  final bool isLoading;
  final VoidCallback onTap;

  const _OptionCard({
    required this.option,
    required this.isLoading,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final displayName = option.getLocalizedNameFromContext(context);
    final iconData = IconMapper.getIconData(option.icon ?? 'assignment');
    final severityColor = option.severityColor;

    return GestureDetector(
      onTap: isLoading ? null : onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        curve: Curves.easeOut,
        decoration: BoxDecoration(
          color: AppTheme.surfaceColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: severityColor.withOpacity(0.75),
            width: 1.5,
          ),
          boxShadow: [
            BoxShadow(
              color: severityColor.withOpacity(0.12),
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
            onTap: isLoading ? null : onTap,
            child: Stack(
              children: [
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: option.severityFillColor,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(
                          iconData,
                          size: 26,
                          color: severityColor,
                        ),
                      ),
                      const SizedBox(height: 10),
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
                    ],
                  ),
                ),
                if (isLoading)
                  Positioned.fill(
                    child: Container(
                      decoration: BoxDecoration(
                        color: AppTheme.surfaceColor.withOpacity(0.75),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: const Center(
                        child: SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Fallback category card (options endpoint unavailable)
class _CategoryCard extends StatelessWidget {
  final rc.ReportCategory category;
  final VoidCallback onTap;

  const _CategoryCard({
    required this.category,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final displayName = category.getLocalizedNameFromContext(context);
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
            color: categoryColor.withOpacity(0.3),
            width: 2,
          ),
          boxShadow: [
            BoxShadow(
              color: categoryColor.withOpacity(0.15),
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
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
