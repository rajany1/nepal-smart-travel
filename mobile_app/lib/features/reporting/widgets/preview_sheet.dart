import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../core/models/report_category.dart' as rc;
import '../../../../config/themes/app_theme.dart';

/// Preview sheet showing the report before submission
class PreviewSheet extends StatelessWidget {
  final rc.ReportCategory category;
  final rc.ReportCategoryOption? selectedOption;
  final String description;
  final Map<String, dynamic> formValues;
  final XFile? photo;
  final double? lat;
  final double? lng;
  final String? district;
  final VoidCallback onEdit;
  final Future<void> Function() onSubmit;
  final bool isSubmitting;

  const PreviewSheet({
    super.key,
    required this.category,
    this.selectedOption,
    required this.description,
    required this.formValues,
    this.photo,
    this.lat,
    this.lng,
    this.district,
    required this.onEdit,
    required this.onSubmit,
    this.isSubmitting = false,
  });

  @override
  Widget build(BuildContext context) {
    final categoryName = category.getLocalizedName(context);
    final categoryColor = IconMapper.getCategoryColor(category.icon);

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
                      context.t('Preview Report'),
                      style: const TextStyle(
                        fontSize: AppTheme.text2xl,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.textPrimary,
                      ),
                    ),
                    const Spacer(),
                    IconButton(
                      onPressed: onEdit,
                      icon: const Icon(Icons.edit, size: 24),
                      color: AppTheme.textSecondary,
                      tooltip: context.t('Edit'),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close, size: 24),
                      color: AppTheme.textSecondary,
                    ),
                  ],
                ),
              ),

              // Preview Content
              Expanded(
                child: ListView(
                  controller: scrollController,
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                  children: [
                    // Category Header
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: categoryColor.withOpacity(0.08),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: categoryColor.withOpacity(0.3)),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 48,
                            height: 48,
                            decoration: BoxDecoration(
                              color: categoryColor.withOpacity(0.15),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Icon(
                              IconMapper.getIconData(category.icon ?? 'assignment'),
                              size: 26,
                              color: categoryColor,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  categoryName,
                                  style: TextStyle(
                                    fontSize: AppTheme.textLg,
                                    fontWeight: FontWeight.bold,
                                    color: categoryColor,
                                  ),
                                ),
                                if (selectedOption != null) ...[
                                  const SizedBox(height: 4),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: categoryColor.withOpacity(0.2),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      selectedOption!.getLocalizedName(context),
                                      style: TextStyle(
                                        fontSize: AppTheme.textSm,
                                        fontWeight: FontWeight.w600,
                                        color: categoryColor,
                                      ),
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          // Emergency badge
                          if (category.isEmergency)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: AppTheme.errorColor,
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.bolt, size: 12, color: Colors.white),
                                  const SizedBox(width: 4),
                                  Text(
                                    context.t('EMERGENCY'),
                                    style: const TextStyle(
                                      fontSize: 10,
                                      color: Colors.white,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),

                    // Photo Preview
                    if (photo != null) ...[
                      ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: Image.file(
                          File(photo!.path),
                          height: 200,
                          width: double.infinity,
                          fit: BoxFit.cover,
                        ),
                      ),
                      const SizedBox(height: 20),
                    ],

                    // Description
                    _buildSection(
                      context,
                      context.t('Description'),
                      description.isNotEmpty ? description : context.t('(No description provided)'),
                      Icons.description_outlined,
                    ),

                    // Custom Fields
                    ..._buildCustomFields(context),

                    // Location
                    _buildSection(
                      context,
                      context.t('Location'),
                      _buildLocationText(),
                      Icons.location_on_outlined,
                    ),

                    // Photo requirement indicator
                    if (selectedOption?.requiresPhoto == true || 
                        category.fields.any((f) => f.type == 'photo' && f.required)) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppTheme.warningColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppTheme.warningColor.withOpacity(0.3)),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.info_outline, color: AppTheme.warningColor, size: 20),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                context.t('Photo is required for this report type'),
                                style: TextStyle(
                                  fontSize: AppTheme.textSm,
                                  color: AppTheme.warningColor,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Photo preview in preview
                    if (photo != null) ...[
                      const SizedBox(height: 16),
                      _buildSection(
                        context,
                        context.t('Attached Photo'),
                        '',
                        Icons.photo_outlined,
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            File(photo!.path),
                            height: 180,
                            width: double.infinity,
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                    ],

                    const SizedBox(height: 24),
                  ],
                ),
              ),

              // Action Buttons
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                child: Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: isSubmitting ? null : onEdit,
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          side: BorderSide(color: AppTheme.primaryColor.withOpacity(0.5)),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                          foregroundColor: AppTheme.primaryColor,
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(Icons.edit, size: 20),
                            const SizedBox(width: 8),
                            Text(context.t('Edit'), style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w600)),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: isSubmitting ? null : () => onSubmit(),
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          backgroundColor: categoryColor,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: isSubmitting
                            ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(Icons.send, size: 20),
                                  const SizedBox(width: 8),
                                  Text(context.t('Submit Report'), style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w600)),
                                ],
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildSection(
    BuildContext context,
    String title,
    String content,
    IconData icon, {
    Widget? child,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, size: 20, color: AppTheme.textSecondary),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                fontSize: AppTheme.textLg,
                fontWeight: FontWeight.w600,
                color: AppTheme.textPrimary,
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        child ??
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: AppTheme.surfaceColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppTheme.dividerColor.withOpacity(0.5)),
              ),
              child: Text(
                content,
                style: const TextStyle(
                  fontSize: AppTheme.textBase,
                  color: AppTheme.textPrimary,
                  height: 1.5,
                ),
              ),
            ),
        const SizedBox(height: 20),
      ],
    );
  }

  List<Widget> _buildCustomFields(BuildContext context) {
    final widgets = <Widget>[];
    
    for (final field in category.customFields) {
      final value = formValues[field.name];
      if (value == null || (value is String && value.isEmpty) || (value is List && value.isEmpty)) continue;

      final label = field.getLocalizedLabel(context);
      String displayValue;

      if (field.isMultiSelect && value is List) {
        displayValue = value.map((v) {
          final opt = field.options.firstWhere((o) => o.value == v, orElse: () => null);
          return opt?.getLocalizedLabel(context) ?? v.toString();
        }).join(', ');
      } else if (field.isSelectField && field.options.isNotEmpty) {
        final opt = field.options.firstWhere((o) => o.value == value, orElse: () => null);
        displayValue = opt?.getLocalizedLabel(context) ?? value.toString();
      } else {
        displayValue = value.toString();
      }

      widgets.add(
        _buildSection(
          context,
          label,
          displayValue,
          _getFieldIcon(field.type),
        ),
      );
    }

    return widgets;
  }

  IconData _getFieldIcon(String type) {
    switch (type) {
      case 'single_select':
      case 'multi_select':
        return Icons.list_alt_outlined;
      case 'textarea':
        return Icons.description_outlined;
      case 'number':
        return Icons.numbers_outlined;
      case 'date':
        return Icons.calendar_today_outlined;
      case 'yes_no':
        return Icons.help_outline;
      default:
        return Icons.text_fields_outlined;
    }
  }

  String _buildLocationText() {
    if (lat != null && lng != null) {
      final parts = <String>[];
      if (district != null) parts.add(district!);
      parts.add('${lat!.toStringAsFixed(6)}, ${lng!.toStringAsFixed(6)}');
      return parts.join('\n');
    }
    return context.t('Location not available');
  }
}