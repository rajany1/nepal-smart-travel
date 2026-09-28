import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';
import 'dart:async';
import '../../../../core/services/localization_service.dart';
import '../../../../core/utils/icon_mapper.dart';
import '../../../../providers/report_provider.dart';
import '../../../../core/models/report_category.dart' as rc;
import '../../../../core/services/location_service.dart';
import '../../../../core/services/location_integrity_service.dart';
import '../../../../core/services/camera_service.dart';
import '../../../../core/services/exif_embedder_service.dart';
import '../../../../config/themes/app_theme.dart';
import '../../../../config/constants/app_constants.dart';
import 'preview_sheet.dart';

/// Dynamic form sheet for category-specific report submission
class CategoryFormSheet extends StatefulWidget {
  final rc.ReportCategory category;
  final rc.ReportCategoryOption? preselectedOption;
  final double? initialLat;
  final double? initialLng;
  final String? initialDistrict;

  const CategoryFormSheet({
    super.key,
    required this.category,
    this.preselectedOption,
    this.initialLat,
    this.initialLng,
    this.initialDistrict,
  });

  @override
  State<CategoryFormSheet> createState() => _CategoryFormSheetState();
}

class _CategoryFormSheetState extends State<CategoryFormSheet> {
  final _formKey = GlobalKey<FormState>();
  final Map<String, dynamic> _formValues = {};
  final Map<String, dynamic> _fieldErrors = {};

  // Location
  final LocationService _locationService = LocationService();
  final LocationIntegrityService _integrityService =
      LocationIntegrityService.instance;
  double? _lat;
  double? _lng;
  double? _accuracy;
  DateTime? _locationTimestamp;
  String? _district;
  bool _isLoadingLocation = true;

  // Location integrity (mock-location detection) — evidence for the backend,
  // never blocks the submission. Started early so it is usually settled by
  // the time the user taps submit.
  Future<LocationIntegrityResult>? _integrityFuture;
  LocationIntegrityResult? _locationIntegrity;

  // Photo
  final CameraService _cameraService = CameraService();
  final CaptureLocationService _captureLocationService = CaptureLocationService();
  XFile? _capturedPhoto;
  bool _isCapturingPhoto = false;

  // State
  bool _isSubmitting = false;
  bool _configReady = false;

  // Category (starts as the widget's category, upgraded to the full form
  // config from the backend when fields/options are missing)
  late rc.ReportCategory _category;

  // Selected option (for categories with options)
  rc.ReportCategoryOption? _selectedOption;

  @override
  void initState() {
    super.initState();
    _category = widget.category;
    _initialize();
    if (widget.preselectedOption != null) {
      _selectedOption = widget.preselectedOption;
    }
  }

  Future<void> _initialize() async {
    // Kick off mock-location detection in the background — it must never
    // delay or block opening the report form.
    unawaited(_startIntegrityCheck());

    // Use initial location if provided
    if (widget.initialLat != null && widget.initialLng != null) {
      _lat = widget.initialLat;
      _lng = widget.initialLng;
      _district = widget.initialDistrict;
      _isLoadingLocation = false;
    } else {
      await _getCurrentLocation();
    }

    // Load the full category config when the passed category is incomplete
    if (_category.fields.isEmpty || _category.options.isEmpty) {
      await _loadCategoryConfig();
    } else {
      _configReady = true;
    }

    if (mounted) setState(() {});
  }

  Future<void> _startIntegrityCheck() async {
    _integrityFuture ??= _integrityService.check();
    try {
      final result = await _integrityFuture!;
      if (mounted) {
        setState(() => _locationIntegrity = result);
      } else {
        _locationIntegrity = result;
      }
    } catch (_) {
      // check() never throws in practice — submission has its own fallback.
    }
  }

  Future<void> _getCurrentLocation() async {
    final pos = await _locationService.getCurrentLocation();
    if (pos != null && mounted) {
      setState(() {
        _lat = pos.latitude;
        _lng = pos.longitude;
        _accuracy = pos.accuracy;
        _locationTimestamp = pos.timestamp;
        _isLoadingLocation = false;
      });
      final address = await _locationService.getAddressFromCoordinates(pos.latitude, pos.longitude);
      if (address != null && mounted) {
        final parts = address.split(',');
        if (parts.length >= 2) {
          setState(() => _district = parts[1].trim());
        }
      }
    } else if (mounted) {
      setState(() {
        _lat = null;
        _lng = null;
        _isLoadingLocation = false;
      });
    }
  }

  Future<void> _loadCategoryConfig() async {
    final provider = context.read<ReportProvider>();
    final config = await provider.getCategoryFormConfig(_category.id);
    if (config != null && mounted) {
      setState(() => _category = config);
    }
    if (mounted) setState(() => _configReady = true);
  }

  Future<void> _capturePhoto() async {
    setState(() => _isCapturingPhoto = true);
    try {
      final photo = await _cameraService.capturePhoto(
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 88,
      );
      if (photo != null && mounted) {
        if (await CameraService.isWithinSizeLimit(photo)) {
          setState(() => _capturedPhoto = photo);
          unawaited(_refreshCaptureLocation());
        } else {
          await CameraService.cleanUp(photo);
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(context.t('Photo is too large. Max 5MB.')), backgroundColor: AppTheme.errorColor),
            );
          }
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.t('Failed to capture photo.')), backgroundColor: AppTheme.errorColor),
        );
      }
    }
    if (mounted) setState(() => _isCapturingPhoto = false);
  }

  Future<void> _refreshCaptureLocation() async {
    await _captureLocationService.captureLocationAfterPhoto();
    if (!mounted || !_captureLocationService.hasCaptureLocation) return;
    setState(() {
      _lat = _captureLocationService.captureLatitude;
      _lng = _captureLocationService.captureLongitude;
      _accuracy = _captureLocationService.captureAccuracy;
      _locationTimestamp = _captureLocationService.capturedAt;
    });
  }

  Future<void> _retakePhoto() async {
    if (_capturedPhoto != null) await CameraService.cleanUp(_capturedPhoto!);
    setState(() => _capturedPhoto = null);
    await _capturePhoto();
  }

  /// Systematic list of everything required but still empty. Mirrors the
  /// form validators plus photo/location requirements — the preview screen
  /// renders these as the "Missing information" banner and blocks submit.
  List<String> _collectMissingItems() {
    final missing = <String>[];

    final baseDesc = _formValues['description']?.toString().trim() ?? '';
    if (baseDesc.length < 10) {
      missing.add(context.t('Description'));
    }

    for (final field in _category.customFields) {
      if (!field.required) continue;
      final value = _formValues[field.name];
      final isEmpty = value == null ||
          (value is String && value.isEmpty) ||
          (value is List && value.isEmpty);
      if (isEmpty) {
        missing.add(field.getLocalizedLabelFromContext(context));
      }
    }

    final requiresPhoto = _category.options.any((o) => o.requiresPhoto) ||
        _selectedOption?.requiresPhoto == true ||
        _category.fields.any((f) => f.type == 'photo' && f.required);
    if (requiresPhoto && _capturedPhoto == null) {
      missing.add(context.t('Photo'));
    }

    if (_lat == null || _lng == null) {
      missing.add(context.t('Location'));
    }

    return missing;
  }

  /// Returns an error message when submission failed, null on success.
  Future<String?> _submitReport() async {
    final missing = _collectMissingItems();
    if (missing.isNotEmpty) {
      return context.t('Please complete the missing information above');
    }
    if (!_formKey.currentState!.validate()) {
      return context.t('Some fields need attention');
    }

    setState(() => _isSubmitting = true);
    final failMsg = context.tr('Failed to submit report. Please try again.');

    try {
      final description = _buildDescription();
      final categoryId = widget.category.id;
      // Resolve before any await below (use_build_context_synchronously).
      final provider = context.read<ReportProvider>();

      // Location integrity: reuse the background result if ready, otherwise
      // give the native detector one last short chance. On any timeout or
      // failure we submit with an 'cannot_determine' result — integrity
      // issues must never stop a legitimate report from being filed.
      LocationIntegrityResult integrity;
      try {
        integrity = _locationIntegrity ??
            await (_integrityFuture ?? _integrityService.check())
                .timeout(const Duration(seconds: 2));
      } on TimeoutException {
        integrity =
            LocationIntegrityResult.inconclusive('submission_timeout');
      } catch (_) {
        integrity =
            LocationIntegrityResult.inconclusive('submission_error');
      }

      final success = await provider.submitReport(
        description: description,
        categoryId: categoryId,
        latitude: _lat!,
        longitude: _lng!,
        district: _district,
        photoPath: _capturedPhoto?.path,
        captureLatitude: _captureLocationService.captureLatitude,
        captureLongitude: _captureLocationService.captureLongitude,
        locationAccuracy: _accuracy,
        locationTimestamp: _locationTimestamp,
        locationIntegrity: integrity.toRequestPayload(),
      );

      _captureLocationService.clear();

      if (success && _capturedPhoto != null) {
        await CameraService.cleanUp(_capturedPhoto!);
      }

      if (mounted) {
        setState(() => _isSubmitting = false);
        if (success) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(context.t('Report submitted!')), backgroundColor: AppTheme.successColor),
          );
          Navigator.pop(context); // Close preview sheet
          Navigator.pop(context); // Close form sheet
          // Refresh reports
          unawaited(provider.fetchReports(lat: _lat, lng: _lng, radiusKm: 20.0));
          return null;
        }
        return context.tr(provider.submissionErrorMessage ?? failMsg);
      }
      return failMsg;
    } catch (e) {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
      return failMsg;
    }
  }

  String _buildDescription() {
    final parts = <String>[];

    // Add selected option
    if (_selectedOption != null) {
      parts.add('Type: ${_selectedOption!.getLocalizedName(context.read<LocalizationService>())}');
    }

    // Add custom field values
    for (final field in _category.customFields) {
      final value = _formValues[field.name];
      if (value != null && value.toString().isNotEmpty) {
        final label = field.getLocalizedLabel(context.read<LocalizationService>());
        if (field.isMultiSelect && value is List) {
          parts.add('$label: ${value.join(", ")}');
        } else {
          parts.add('$label: $value');
        }
      }
    }

    // Add base description if present
    final baseDesc = _formValues['description']?.toString() ?? '';
    if (baseDesc.isNotEmpty) {
      parts.insert(0, baseDesc);
    }

    return parts.join('\n\n');
  }

  void _showPreview() {
    final description = _buildDescription();
    final missing = _collectMissingItems();
    final baseDesc = _formValues['description']?.toString().trim() ?? '';
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => PreviewSheet(
        category: _category,
        selectedOption: _selectedOption,
        description: description,
        formValues: _formValues,
        photo: _capturedPhoto,
        lat: _lat,
        lng: _lng,
        district: _district,
        onEdit: () => Navigator.pop(ctx),
        onSubmit: _submitReport,
        missingItems: missing,
        descriptionError: baseDesc.length < 10
            ? context.t('Description must be at least 10 characters')
            : null,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final categoryName = _category.getLocalizedNameFromContext(context);

    return DraggableScrollableSheet(
      initialChildSize: 0.95,
      minChildSize: 0.6,
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
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: IconMapper.getCategoryColor(_category.icon).withOpacity(0.12),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(
                        IconMapper.getIconData(_category.icon ?? 'assignment'),
                        size: 22,
                        color: IconMapper.getCategoryColor(_category.icon),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            categoryName,
                            style: const TextStyle(
                              fontSize: AppTheme.textXl,
                              fontWeight: FontWeight.bold,
                              color: AppTheme.textPrimary,
                            ),
                          ),
                          if (_category.description != null)
                            Text(
                              _category.getLocalizedDescriptionFromContext(context) ?? '',
                              style: const TextStyle(
                                fontSize: AppTheme.textSm,
                                color: AppTheme.textSecondary,
                              ),
                            ),
                        ],
                      ),
                    ),
                    IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: const Icon(Icons.close, size: 24),
                      color: AppTheme.textSecondary,
                    ),
                  ],
                ),
              ),

              // Form
              Expanded(
                child: !_configReady
                    ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
                    : Form(
                        key: _formKey,
                        child: ListView(
                          controller: scrollController,
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                          children: [
                            // Option selection (if category has options)
                            if (_category.options.isNotEmpty) ...[
                              _buildOptionSelector(),
                              const SizedBox(height: 16),
                            ],

                            // Custom fields
                            ..._category.customFields.map((field) => Padding(
                                  padding: const EdgeInsets.only(bottom: 16),
                                  child: _buildField(field),
                                )),

                            // Base description field (always required)
                            if (!_category.customFields.any((f) => f.name == 'description')) ...[
                              _buildDescriptionField(),
                              const SizedBox(height: 16),
                            ],

                            // Location
                            _buildLocationCard(),
                            const SizedBox(height: 16),

                            // Photo
                            _buildPhotoSection(),
                            const SizedBox(height: 24),

                            // Submit / Preview Buttons
                            _buildActionButtons(),
                          ],
                        ),
                      ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildOptionSelector() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.t('Type of issue'),
          style: const TextStyle(
            fontSize: AppTheme.textLg,
            fontWeight: FontWeight.w600,
            color: AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: _category.options.map((option) {
            final isSelected = _selectedOption?.id == option.id;
            final optionName = option.getLocalizedNameFromContext(context);
            final iconData = IconMapper.getIconData(option.icon ?? 'help');
            final categoryColor = IconMapper.getCategoryColor(_category.icon);

            return GestureDetector(
              onTap: () => setState(() => _selectedOption = isSelected ? null : option),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                decoration: BoxDecoration(
                  color: isSelected ? categoryColor.withOpacity(0.12) : AppTheme.surfaceColor,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: isSelected ? categoryColor : AppTheme.dividerColor.withOpacity(0.5),
                    width: isSelected ? 2 : 1,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(iconData, size: 18, color: isSelected ? categoryColor : AppTheme.textSecondary),
                    const SizedBox(width: 8),
                    Text(
                      optionName,
                      style: TextStyle(
                        fontSize: AppTheme.textSm,
                        fontWeight: FontWeight.w600,
                        color: isSelected ? categoryColor : AppTheme.textPrimary,
                      ),
                    ),
                    if (isSelected) ...[
                      const SizedBox(width: 6),
                      Icon(Icons.check_circle, size: 16, color: categoryColor),
                    ],
                  ],
                ),
              ),
            );
          }).toList(),
        ),
      ],
    );
  }

  Widget _buildField(rc.ReportCategoryField field) {
    final label = field.getLocalizedLabelFromContext(context);
    final placeholder = field.getLocalizedPlaceholderFromContext(context);
    final helpText = field.getLocalizedHelpTextFromContext(context);
    final error = _fieldErrors[field.name];

    Widget inputWidget;

    switch (field.type) {
      case 'single_select':
        inputWidget = DropdownButtonFormField<String>(
          value: _formValues[field.name] as String?,
          decoration: _inputDecoration(label, placeholder, error),
          items: field.options.map((opt) {
            final optLabel = opt.getLocalizedLabelFromContext(context);
            return DropdownMenuItem(value: opt.value, child: Text(optLabel));
          }).toList(),
          onChanged: (v) => setState(() {
            _formValues[field.name] = v;
            _fieldErrors.remove(field.name);
          }),
          validator: field.required ? (v) => v == null || v.isEmpty ? context.t('Required') : null : null,
        );
        break;

      case 'multi_select':
        final selected = (_formValues[field.name] as List?) ?? [];
        inputWidget = Wrap(
          spacing: 8,
          runSpacing: 8,
          children: field.options.map((opt) {
            final optLabel = opt.getLocalizedLabelFromContext(context);
            final isSelected = selected.contains(opt.value);
            return FilterChip(
              label: Text(optLabel),
              selected: isSelected,
              onSelected: (s) {
                setState(() {
                  final newList = List<String>.from(selected);
                  if (s) {
                    newList.add(opt.value);
                  } else {
                    newList.remove(opt.value);
                  }
                  _formValues[field.name] = newList;
                  _fieldErrors.remove(field.name);
                });
              },
              selectedColor: IconMapper.getCategoryColor(_category.icon).withOpacity(0.2),
              checkmarkColor: IconMapper.getCategoryColor(_category.icon),
            );
          }).toList(),
        );
        if (field.required) {
          inputWidget = Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              inputWidget,
              if (error != null || (field.required && selected.isEmpty))
                Padding(
                  padding: const EdgeInsets.only(top: 4, left: 12),
                  child: Text(
                    error ?? context.t('Select at least one option'),
                    style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.errorColor),
                  ),
                ),
            ],
          );
        }
        break;

      case 'textarea':
        inputWidget = TextFormField(
          initialValue: _formValues[field.name] as String?,
          decoration: _inputDecoration(label, placeholder, error),
          maxLines: 4,
          minLines: 3,
          onChanged: (v) => setState(() {
            _formValues[field.name] = v;
            _fieldErrors.remove(field.name);
          }),
          validator: field.required ? (v) => v == null || v.isEmpty ? context.t('Required') : null : null,
        );
        break;

      case 'number':
        inputWidget = TextFormField(
          initialValue: _formValues[field.name] as String?,
          decoration: _inputDecoration(label, placeholder, error),
          keyboardType: TextInputType.number,
          onChanged: (v) => setState(() {
            _formValues[field.name] = v;
            _fieldErrors.remove(field.name);
          }),
          validator: field.required ? (v) => v == null || v.isEmpty ? context.t('Required') : null : null,
        );
        break;

      case 'yes_no':
        final value = _formValues[field.name] as String?;
        inputWidget = Row(
          children: [
            Expanded(
              child: RadioListTile<String>(
                title: Text(context.t('Yes')),
                value: 'yes',
                groupValue: value,
                onChanged: (v) => setState(() {
                  _formValues[field.name] = v;
                  _fieldErrors.remove(field.name);
                }),
                activeColor: IconMapper.getCategoryColor(_category.icon),
              ),
            ),
            Expanded(
              child: RadioListTile<String>(
                title: Text(context.t('No')),
                value: 'no',
                groupValue: value,
                onChanged: (v) => setState(() {
                  _formValues[field.name] = v;
                  _fieldErrors.remove(field.name);
                }),
                activeColor: IconMapper.getCategoryColor(_category.icon),
              ),
            ),
          ],
        );
        break;

      case 'date':
        inputWidget = InkWell(
          onTap: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: DateTime.now(),
              firstDate: DateTime.now().subtract(const Duration(days: 365)),
              lastDate: DateTime.now().add(const Duration(days: 365)),
            );
            if (date != null) {
              setState(() {
                _formValues[field.name] = date.toIso8601String().split('T')[0];
                _fieldErrors.remove(field.name);
              });
            }
          },
          child: InputDecorator(
            decoration: _inputDecoration(label, placeholder, error),
            child: Text(
              _formValues[field.name] as String? ?? context.t('Select date'),
              style: TextStyle(
                color: _formValues[field.name] != null ? AppTheme.textPrimary : AppTheme.textSecondary,
              ),
            ),
          ),
        );
        break;

      default: // text
        inputWidget = TextFormField(
          initialValue: _formValues[field.name] as String?,
          decoration: _inputDecoration(label, placeholder, error),
          onChanged: (v) => setState(() {
            _formValues[field.name] = v;
            _fieldErrors.remove(field.name);
          }),
          validator: field.required ? (v) => v == null || v.isEmpty ? context.t('Required') : null : null,
        );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        inputWidget,
        if (helpText != null) ...[
          const SizedBox(height: 4),
          Text(
            helpText,
            style: const TextStyle(fontSize: AppTheme.textXs, color: AppTheme.textSecondary),
          ),
        ],
      ],
    );
  }

  Widget _buildDescriptionField() {
    return TextFormField(
      initialValue: _formValues['description'] as String?,
      decoration: _inputDecoration(
        context.t('Description'),
        context.t('Describe what happened...'),
        _fieldErrors['description'],
      ),
      maxLines: 4,
      minLines: 3,
      onChanged: (v) => setState(() {
        _formValues['description'] = v;
        _fieldErrors.remove('description');
      }),
      validator: (v) => v == null || v.trim().length < 10 ? context.t('Description must be at least 10 characters') : null,
    );
  }

  InputDecoration _inputDecoration(String label, String? placeholder, String? error) {
    return InputDecoration(
      labelText: label,
      hintText: placeholder,
      errorText: error,
      filled: true,
      fillColor: AppTheme.surfaceColor,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: error != null ? AppTheme.errorColor : AppTheme.dividerColor),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: error != null ? AppTheme.errorColor : AppTheme.dividerColor),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: IconMapper.getCategoryColor(_category.icon), width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppTheme.errorColor),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    );
  }

  Widget _buildLocationCard() {
    final categoryColor = IconMapper.getCategoryColor(_category.icon);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: (_lat != null ? AppTheme.successColor : AppTheme.errorColor).withOpacity(0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          if (_isLoadingLocation)
            const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
          else
            Icon(
              _lat != null ? Icons.gps_fixed : Icons.gps_off,
              size: 18,
              color: _lat != null ? AppTheme.successColor : AppTheme.errorColor,
            ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              _isLoadingLocation
                  ? context.t('Getting your location...')
                  : _district != null
                      ? _district!
                      : _lat != null
                          ? context.t('Location detected')
                          : context.t('GPS needed for photo & report'),
              style: TextStyle(
                fontSize: AppTheme.textSm,
                fontWeight: FontWeight.w600,
                color: _lat != null ? AppTheme.successColor : AppTheme.errorColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

Widget _buildPhotoSection() {
    final categoryColor = IconMapper.getCategoryColor(_category.icon);

    final photoWidget = _capturedPhoto != null
        ? ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Stack(
              children: [
                Image.file(
                  File(_capturedPhoto!.path),
                  height: 200,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
                Positioned(
                  top: 8,
                  right: 8,
                  child: GestureDetector(
                    onTap: _retakePhoto,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.black54,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Icon(Icons.refresh, color: Colors.white, size: 18),
                    ),
                  ),
                ),
              ],
            ),
          )
        : GestureDetector(
            onTap: _isCapturingPhoto ? null : _capturePhoto,
            child: Container(
              height: 140,
              decoration: BoxDecoration(
                color: AppTheme.primaryLight.withOpacity(0.06),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: categoryColor.withOpacity(0.4),
                  width: 1.5,
                  style: BorderStyle.solid,
                ),
              ),
              child: _isCapturingPhoto
                  ? const Center(child: CircularProgressIndicator(strokeWidth: 2))
                  : Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.photo_camera_outlined, size: 36, color: categoryColor),
                        const SizedBox(height: 8),
                        Text(
                          context.t('Tap to capture live photo'),
                          style: TextStyle(fontSize: AppTheme.textSm, color: AppTheme.textSecondary),
                        ),
                        if (_selectedOption?.requiresPhoto == true) ...[
                          const SizedBox(height: 4),
                          Text(
                            context.t('Required for this type'),
                            style: TextStyle(fontSize: AppTheme.textXs, color: AppTheme.warningColor, fontWeight: FontWeight.w500),
                          ),
                        ],
                      ],
                    ),
              ),
            );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          context.t('Photo'),
          style: const TextStyle(
            fontSize: AppTheme.textLg,
            fontWeight: FontWeight.w600,
            color: AppTheme.textPrimary,
          ),
        ),
        const SizedBox(height: 12),
        photoWidget,
      ],
    );
  }

  Widget _buildActionButtons() {
    return Row(
      children: [
        Expanded(
          child: OutlinedButton(
            onPressed: _isSubmitting ? null : _showPreview,
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
              side: BorderSide(color: AppTheme.primaryColor.withOpacity(0.5)),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              foregroundColor: AppTheme.primaryColor,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.visibility, size: 20),
                const SizedBox(width: 8),
                Text(context.t('Preview'), style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: ElevatedButton(
            onPressed: _isSubmitting ? null : _showPreview, // Preview first, then submit from preview
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 16),
              backgroundColor: IconMapper.getCategoryColor(_category.icon),
              foregroundColor: Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: _isSubmitting
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.send, size: 20),
                      const SizedBox(width: 8),
                      Text(context.t('Continue'), style: const TextStyle(fontSize: AppTheme.textBase, fontWeight: FontWeight.w600)),
                    ],
                  ),
          ),
        ),
      ],
    );
  }
}