import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../providers/report_provider.dart';
import '../../../../core/models/report_category.dart' as rc;
import '../../../../config/themes/app_theme.dart';

/// Full-screen bottom sheet for browsing all categories with search and groups
class MoreCategoriesSheet extends StatefulWidget {
  final List<rc.ReportCategoryGroup> categoryGroups;
  final Function(rc.ReportCategory) onCategorySelected;

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
  List<rc.ReportCategory> _searchResults = [];
  bool _isSearching = false;
  String _lastQuery = '';

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
    final results = await provider.searchCategories(query);
    if (mounted) {
      setState(() {
        _searchResults = results;
        _isSearching = false;
      });
    }
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
        final category = _searchResults[index];
        return _SearchResultCard(
          category: category,
          onTap: () => widget.onCategorySelected(category),
        );
      },
    );
  }

  Widget _buildCategoryGroups(ScrollController scrollController) {
    return ListView.builder(
      controller: scrollController,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: widget.categoryGroups.length,
      itemBuilder: (context, index) {
        final group = widget.categoryGroups[index];
        if (group.categories.isEmpty) return const SizedBox.shrink();

        final groupName = group.getLocalizedName(context);

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
            // Categories Grid
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 0.85,
              ),
              itemCount: group.categories.length,
              itemBuilder: (context, catIndex) {
                final category = group.categories[catIndex];
                return _GroupCategoryCard(
                  category: category,
                  isFeatured: category.isFeatured,
                  onTap: () => widget.onCategorySelected(category),
                );
              },
            ),
            const SizedBox(height: 8),
          ],
        );
      },
    );
  }
}

/// Search result card with group context
class _SearchResultCard extends StatelessWidget {
  final rc.ReportCategory category;
  final VoidCallback onTap;

  const _SearchResultCard({
    required this.category,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final displayName = category.getLocalizedName(context);
    final iconData = IconMapper.getIconData(category.icon ?? 'assignment');
    final categoryColor = IconMapper.getCategoryColor(category.icon);

    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: AppTheme.surfaceColor,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
        ),
        child: Material(
          color: Colors.transparent,
          borderRadius: BorderRadius.circular(16),
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: onTap,
            child: Padding(
              padding: const EdgeInsets.all(14),
              child: Row(
                children: [
                  // Icon
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: categoryColor.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(iconData, size: 22, color: categoryColor),
                  ),
                  const SizedBox(width: 12),
                  // Info
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          displayName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: AppTheme.textBase,
                            fontWeight: FontWeight.w600,
                            color: AppTheme.textPrimary,
                          ),
                        ),
                        if (category.description != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            category.getLocalizedDescription(context) ?? '',
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
                  // Arrow
                  Icon(Icons.chevron_right, color: AppTheme.textSecondary.withOpacity(0.5)),
                ],
              ),
            ),
          ),
        ),
      );
    }
  }
}

/// Category card for group grids
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
}