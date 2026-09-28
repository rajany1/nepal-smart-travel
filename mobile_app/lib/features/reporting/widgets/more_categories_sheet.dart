import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../providers/report_provider.dart';
import '../../../../core/models/report_category.dart' as rc;
import '../../../../config/themes/app_theme.dart';

/// Full-screen bottom sheet for browsing all report options grouped by
/// category group (Travel, Community, Safety, Services) with search.
class MoreCategoriesSheet extends StatefulWidget {
  final List<rc.ReportCategoryGroup> categoryGroups;
  final void Function(rc.ReportCategory category, rc.ReportCategoryOption? option)
      onCategorySelected;

  const MoreCategoriesSheet({
    super.key,
    required this.categoryGroups,
    required this.onCategorySelected,
  });

  @override
  State<MoreCategoriesSheet> createState() => _MoreCategoriesSheetState();
}

class _MoreCategoriesSheetState extends State<MoreCategoriesSheet> {
  final TextEditingController _searchController = TextEditingController();
  final FocusNode _searchFocus = FocusNode();
  List<rc.ReportCategoryOption> _searchResults = [];
  bool _isSearching = false;
  String _lastQuery = '';
  int? _loadingOptionId;

  @override
  void initState() {
    super.initState();
    _searchFocus.requestFocus();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.removeListener(_onSearchChanged);
    _searchController.dispose();
    _searchFocus.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    final query = _searchController.text.trim();
    if (query != _lastQuery) {
      _lastQuery = query;
      if (query.isEmpty) {
        setState(() {
          _searchResults = [];
          _isSearching = false;
        });
      } else {
        _performSearch(query);
      }
    }
  }

  Future<void> _performSearch(String query) async {
    setState(() => _isSearching = true);
    final provider = context.read<ReportProvider>();
    final results = await provider.searchOptions(query);
    if (mounted) {
      setState(() {
        _searchResults = results;
        _isSearching = false;
      });
    }
  }

  /// Loads the full category form config for an option, closes this sheet and
  /// hands (category, option) to the caller so it can open the report form.
  Future<void> _selectOption(
      rc.ReportCategory? category, rc.ReportCategoryOption option) async {
    if (_loadingOptionId != null) return;
    setState(() => _loadingOptionId = option.id);

    final config =
        await context.read<ReportProvider>().getCategoryFormConfig(option.categoryId);
    if (!mounted) return;

    final fullCategory = config ?? category ?? option.parentCategory;
    if (fullCategory == null) {
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

    Navigator.pop(context);
    widget.onCategorySelected(fullCategory, option);
  }

  void _selectCategory(rc.ReportCategory category) {
    if (_loadingOptionId != null) return;
    Navigator.pop(context);
    widget.onCategorySelected(category, null);
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.9,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        return Container(
          decoration: const BoxDecoration(
            color: AppTheme.backgroundColor,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            children: [
              // Handle bar
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  margin: const EdgeInsets.only(top: 12),
                  decoration: BoxDecoration(
                    color: Colors.grey.shade300,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),

              // Header
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Row(
                  children: [
                    Text(
                      context.t('Choose a category'),
                      style: const TextStyle(
                        fontSize: AppTheme.text2xl,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close, size: 24),
                      color: AppTheme.textSecondary,
                    ),
                  ],
                ),
              ),

              // Search Bar
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                child: TextField(
                  controller: _searchController,
                  focusNode: _searchFocus,
                  decoration: InputDecoration(
                    hintText: context.t('Search categories...'),
                    hintStyle: const TextStyle(
                      fontSize: AppTheme.textBase,
                      color: AppTheme.textSecondary,
                    ),
                    prefixIcon: _isSearching
                        ? const Padding(
                            padding: EdgeInsets.all(12),
                            child: SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            ),
                          )
                        : const Icon(Icons.search, size: 20, color: AppTheme.textSecondary),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, color: AppTheme.textSecondary),
                            onPressed: () {
                              _searchController.clear();
                              _searchFocus.requestFocus();
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: AppTheme.surfaceColor,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide.none,
                    ),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  ),
                  textInputAction: TextInputAction.search,
                ),
              ),

              // Content
              Expanded(
                child: _searchController.text.isNotEmpty
                    ? _buildSearchResults()
                    : _buildCategoryGroups(scrollController),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSearchResults() {
    if (_isSearching) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      );
    }

    if (_searchResults.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.search_off,
              size: 64,
              color: AppTheme.textSecondary.withOpacity(0.3),
            ),
            const SizedBox(height: 16),
            Text(
              context.t('No category found'),
              style: const TextStyle(
                fontSize: AppTheme.textLg,
                color: AppTheme.textSecondary,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              context.t('Try a different search term'),
              style: const TextStyle(
                fontSize: AppTheme.textBase,
                color: AppTheme.textSecondary,
              ),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: _searchResults.length,
      itemBuilder: (context, index) {
        final option = _searchResults[index];
        return Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: _OptionResultCard(
            option: option,
            isLoading: _loadingOptionId == option.id,
            onTap: () => _selectOption(option.parentCategory, option),
          ),
        );
      },
    );
  }

  Widget _buildCategoryGroups(ScrollController scrollController) {
    if (widget.categoryGroups.isEmpty) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      );
    }

    return ListView.builder(
      controller: scrollController,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: widget.categoryGroups.length,
      itemBuilder: (context, index) {
        final group = widget.categoryGroups[index];
        if (group.categories.isEmpty) return const SizedBox.shrink();

        final groupName = group.getLocalizedNameFromContext(context);

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 8),
            // Group Header
            Row(
              children: [
                if (group.icon != null) ...[
                  Icon(
                    IconMapper.getIconData(group.icon!),
                    size: 18,
                    color: group.iconColor != null
                        ? Color(int.parse(group.iconColor!.replaceFirst('#', '0xFF')))
                        : AppTheme.textSecondary,
                  ),
                  const SizedBox(width: 8),
                ],
                Text(
                  groupName,
                  style: const TextStyle(
                    fontSize: AppTheme.textXs + 1,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.textSecondary,
                    letterSpacing: 0.5,
                  ),
                ),
                if (group.isEmergencyGroup) ...[
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppTheme.errorColor.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      context.t('Emergency'),
                      style: const TextStyle(
                        fontSize: 9,
                        fontWeight: FontWeight.w700,
                        color: AppTheme.errorColor,
                      ),
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 10),
            // Categories (with their options)
            ...group.categories.map((category) => _buildCategorySection(category)),
            const SizedBox(height: 8),
          ],
        );
      },
    );
  }

  /// A category header plus its option chips (severity-outlined). Categories
  /// without options stay tappable cards that open the form directly.
  Widget _buildCategorySection(rc.ReportCategory category) {
    if (category.options.isEmpty) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: _GroupCategoryCard(
          category: category,
          isFeatured: category.isFeatured,
          onTap: () => _selectCategory(category),
        ),
      );
    }

    final iconData = IconMapper.getIconData(category.icon ?? 'assignment');
    final categoryColor = IconMapper.getCategoryColor(category.icon);

    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Category subheader
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: categoryColor.withOpacity(0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(iconData, size: 16, color: categoryColor),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  category.getLocalizedNameFromContext(context),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: AppTheme.textBase,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.textPrimary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          // Option chips with severity outlines
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: category.options
                .map(
                  (option) => _OptionChip(
                    option: option,
                    isLoading: _loadingOptionId == option.id,
                    onTap: () => _selectOption(category, option),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

/// Option chip outlined by severity: low -> green, medium -> blue,
/// high/critical -> red.
class _OptionChip extends StatelessWidget {
  final rc.ReportCategoryOption option;
  final bool isLoading;
  final VoidCallback onTap;

  const _OptionChip({
    required this.option,
    required this.isLoading,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final severityColor = option.severityColor;
    final iconData = IconMapper.getIconData(option.icon ?? 'assignment');

    return GestureDetector(
      onTap: isLoading ? null : onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
        decoration: BoxDecoration(
          color: option.severityFillColor,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: severityColor.withOpacity(0.75),
            width: 1.5,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (isLoading)
              SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: severityColor,
                ),
              )
            else
              Icon(iconData, size: 16, color: severityColor),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                option.getLocalizedNameFromContext(context),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: AppTheme.textSm,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.textPrimary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Search result row for a flat report option (shows its category as context)
class _OptionResultCard extends StatelessWidget {
  final rc.ReportCategoryOption option;
  final bool isLoading;
  final VoidCallback onTap;

  const _OptionResultCard({
    required this.option,
    required this.isLoading,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final severityColor = option.severityColor;
    final iconData = IconMapper.getIconData(option.icon ?? 'assignment');
    final categoryName = option.parentCategory?.getLocalizedNameFromContext(context);

    return GestureDetector(
      onTap: isLoading ? null : onTap,
      child: Container(
        decoration: BoxDecoration(
          color: AppTheme.surfaceColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: severityColor.withOpacity(0.75),
            width: 1.5,
          ),
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: isLoading ? null : onTap,
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  // Icon
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: option.severityFillColor,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(iconData, size: 22, color: severityColor),
                  ),
                  const SizedBox(width: 12),
                  // Info
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          option.getLocalizedNameFromContext(context),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: AppTheme.textBase,
                            fontWeight: FontWeight.w600,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                        if (categoryName != null && categoryName.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            categoryName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: AppTheme.textXs,
                              color: AppTheme.textSecondary,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (isLoading)
                    const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  else
                    Icon(Icons.chevron_right,
                        color: AppTheme.textSecondary.withOpacity(0.5)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Category card for groups whose categories have no options
class _GroupCategoryCard extends StatelessWidget {
  final rc.ReportCategory category;
  final bool isFeatured;
  final VoidCallback onTap;

  const _GroupCategoryCard({
    required this.category,
    required this.isFeatured,
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
      ),
    );
  }
}
