import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../core/models/report_category.dart' as rc;
import '../../../../config/themes/app_theme.dart';

/// Preview sheet showing the report before submission.
///
/// Shows a systematic "Missing information" banner listing every required
/// item that is still empty (description, required fields, photo, location)
/// and blocks submission until the list is empty. Submission errors coming
/// back from the provider are rendered as an error banner on this screen.
class PreviewSheet extends StatefulWidget {
  final rc.ReportCategory category;
  final rc.ReportCategoryOption? selectedOption;
  final String description;
  final Map<String, dynamic> formValues;
  final XFile? photo;
  final double? lat;
  final double? lng;
  final String? district;
  final VoidCallback onEdit;

  /// Returns an error message to display, or null when submission succeeded.
  final Future<String?> Function() onSubmit;

  /// Localized labels of everything still missing (already empty when valid).
  final List<String> missingItems;

  /// Localized inline error for the description (null when valid).
  final String? descriptionError;

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
    this.missingItems = const [],
    this.descriptionError,
  });

  @override
  State<PreviewSheet> createState() => _PreviewSheetState();
}

class _PreviewSheetState extends State<PreviewSheet> {
  bool _submitting = false;
  String? _submitError;
  ScrollController? _sheetScrollController;

  Future<void> _submit() async {
    if (widget.missingItems.isNotEmpty) {
      _highlightMissing();
      return;
    }
    setState(() {
      _submitting = true;
      _submitError = null;
    });
    String? error;
    try {
      error = await widget.onSubmit();
    } catch (_) {
      error = 'Failed to submit report. Please try again.';
    }
    if (!mounted) return;
    setState(() => _submitting = false);
    if (error != null) {
      setState(() => _submitError = error);
      _scrollTop();
    }
  }

  void _highlightMissing() {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(context.t('Please complete the missing information above')),
        backgroundColor: AppTheme.errorColor,
      ),
    );
    _scrollTop();
  }

  void _scrollTop() {
    _sheetScrollController?.animateTo(
      0,
      duration: const Duration(milliseconds: 300),
      curve: Curves.easeOut,
    );
  }

  @override
  Widget build(BuildContext context) {
    final categoryName = widget.category.getLocalizedNameFromContext(context);
    final categoryColor = IconMapper.getCategoryColor(widget.category.icon);
    final requiresPhoto =
        widget.category.options.any((o) => o.requiresPhoto) ||
            widget.selectedOption?.requiresPhoto == true ||
            widget.category.fields.any((f) => f.type == 'photo' && f.required);

    return DraggableScrollableSheet(
      initialChildSize: 0.9,
      minChildSize: 0.5,
      maxChildSize: 0.95,
      expand: false,
      builder: (context, scrollController) {
        _sheetScrollController = scrollController;
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
                      onPressed: widget.onEdit,
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
                    // Missing information banner (systematic validation)
                    if (widget.missingItems.isNotEmpty) ...[
                      _buildMissingBanner(context),
                    ],

                    // Submission error banner
                    if (_submitError != null) ...[
                      _buildSubmitErrorBanner(context),
                      const SizedBox(height: 16),
                    ],

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
                              IconMapper.getIconData(widget.category.icon ?? 'assignment'),
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
                                if (widget.selectedOption != null) ...[
                                  const SizedBox(height: 4),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: categoryColor.withOpacity(0.2),
                                      borderRadius: BorderRadius.circular(4),
                                    ),
                                    child: Text(
                                      widget.selectedOption!.getLocalizedNameFromContext(context),
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
                          if (widget.category.isEmergency)
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

                    // Photo Preview (top image)
                    if (widget.photo != null) ...[
                      ClipRRect(
                        borderRadius: BorderRadius.circular(16),
                        child: Image.file(
                          File(widget.photo!.path),
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
                      widget.description.isNotEmpty
                          ? widget.description
                          : context.t('(No description provided)'),
                      Icons.description_outlined,
                      errorText: widget.descriptionError,
                    ),

                    // Custom Fields
                    ..._buildCustomFields(context),

                    // Location
                    _buildSection(
                      context,
                      context.t('Location'),
                      _buildLocationText(context),
                      Icons.location_on_outlined,
                    ),

                    // Photo requirement indicator (only while photo is missing)
                    if (requiresPhoto && widget.photo == null) ...[
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
                    if (widget.photo != null) ...[
                      const SizedBox(height: 16),
                      _buildSection(
                        context,
                        context.t('Attached Photo'),
                        '',
                        Icons.photo_outlined,
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.file(
                            File(widget.photo!.path),
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
                        onPressed: _submitting ? null : widget.onEdit,
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
                        onPressed: _submitting ? null : _submit,
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 16),
                          backgroundColor: categoryColor,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                        ),
                        child: _submitting
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

  Widget _buildMissingBanner(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.errorColor.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.errorColor.withOpacity(0.5), width: 1.5),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.error_outline, color: AppTheme.errorColor, size: 22),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  context.t('Missing information'),
                  style: const TextStyle(
                    fontSize: AppTheme.textLg,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.errorColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            context.t('Please complete the following before submitting:'),
            style: const TextStyle(
              fontSize: AppTheme.textSm,
              color: AppTheme.textSecondary,
              height: 1.4,
            ),
          ),
          const SizedBox(height: 8),
          ...widget.missingItems.map(
            (item) => Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Row(
                children: [
                  const Icon(Icons.circle, size: 8, color: AppTheme.errorColor),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      item,
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
          ),
        ],
      ),
    );
  }

  Widget _buildSubmitErrorBanner(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppTheme.errorColor.withOpacity(0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppTheme.errorColor.withOpacity(0.5), width: 1.5),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.warning_amber_outlined, color: AppTheme.errorColor, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  context.t('Submission failed'),
                  style: const TextStyle(
                    fontSize: AppTheme.textSm,
                    fontWeight: FontWeight.w700,
                    color: AppTheme.errorColor,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  context.t(_submitError!),
                  style: const TextStyle(
                    fontSize: AppTheme.textSm,
                    color: AppTheme.textPrimary,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSection(
    BuildContext context,
    String title,
    String content,
    IconData icon, {
    Widget? child,
    String? errorText,
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
        if (errorText != null) ...[
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(Icons.error_outline, size: 14, color: AppTheme.errorColor),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  errorText,
                  style: const TextStyle(
                    fontSize: AppTheme.textSm,
                    color: AppTheme.errorColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: 20),
      ],
    );
  }

  List<Widget> _buildCustomFields(BuildContext context) {
    final widgets = <Widget>[];

    for (final field in widget.category.customFields) {
      final value = widget.formValues[field.name];
      final isEmptyValue = value == null ||
          (value is String && value.isEmpty) ||
          (value is List && value.isEmpty);

      if (isEmptyValue) {
        // Required-but-empty fields are listed instead of hidden.
        if (field.required) {
          widgets.add(
            _buildSection(
              context,
              field.getLocalizedLabelFromContext(context),
              '',
              _getFieldIcon(field.type),
              errorText: context.t('Required'),
            ),
          );
        }
        continue;
      }

      final label = field.getLocalizedLabelFromContext(context);
      String displayValue;

      if (field.isMultiSelect && value is List) {
        displayValue = value.map((v) {
          final opt = field.options.firstWhere(
            (o) => o.value == v,
            orElse: () => rc.FieldOption(value: v, label: v),
          );
          return opt.getLocalizedLabelFromContext(context) ?? v.toString();
        }).join(', ');
      } else if (field.isSelectField && field.options.isNotEmpty) {
        final opt = field.options.firstWhere(
          (o) => o.value == value,
          orElse: () => rc.FieldOption(value: value, label: value.toString()),
        );
        displayValue = opt.getLocalizedLabelFromContext(context) ?? value.toString();
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

  String _buildLocationText(BuildContext context) {
    if (widget.lat != null && widget.lng != null) {
      final parts = <String>[];
      if (widget.district != null) parts.add(widget.district!);
      parts.add('${widget.lat!.toStringAsFixed(6)}, ${widget.lng!.toStringAsFixed(6)}');
      return parts.join('\n');
    }
    return context.t('Location not available');
  }
}
