import 'package:flutter/material.dart';
import '../models/form_field_config.dart';
import '../models/report.dart';
import '../../config/themes/app_theme.dart';

/// Dynamically renders a form field based on its FormFieldConfig
class DynamicFormField extends StatelessWidget {
  final FormFieldConfig config;
  final dynamic currentValue;
  final ValueChanged<dynamic> onChanged;
  final String? Function(dynamic)? customValidator;
  final List<ReportCategory>? categories;
  final Map<String, List<FormFieldOption>>? externalOptions;

  const DynamicFormField({
    super.key,
    required this.config,
    this.currentValue,
    required this.onChanged,
    this.customValidator,
    this.categories,
    this.externalOptions,
  });

  @override
  Widget build(BuildContext context) {
    switch (config.type) {
      case 'select':
        return _buildDropdown();
      case 'textarea':
        return _buildTextArea();
      case 'text':
      case 'email':
      case 'phone':
      default:
        return _buildTextField();
    }
  }

  Widget _buildTextField() {
    return TextFormField(
      initialValue: currentValue?.toString() ?? '',
      decoration: InputDecoration(
        labelText: config.label,
        hintText: config.placeholder.isNotEmpty ? config.placeholder : null,
        prefixIcon: Icon(
          config.iconData,
          size: 20,
          color: AppTheme.primaryColor,
        ),
      ),
      keyboardType: config.type == 'email'
          ? TextInputType.emailAddress
          : config.type == 'phone'
              ? TextInputType.phone
              : TextInputType.text,
      validator: (v) {
        if (config.required && (v == null || v.trim().isEmpty)) {
          return '${config.label} is required';
        }
        if (customValidator != null) {
          return customValidator!(v);
        }
        return null;
      },
      onChanged: (v) => onChanged(v),
    );
  }

  Widget _buildTextArea() {
    return TextFormField(
      initialValue: currentValue?.toString() ?? '',
      maxLines: config.rows ?? 3,
      decoration: InputDecoration(
        labelText: config.label,
        hintText: config.placeholder.isNotEmpty ? config.placeholder : null,
        prefixIcon: config.iconData != Icons.edit_square
            ? Icon(config.iconData, size: 20, color: AppTheme.primaryColor)
            : null,
        alignLabelWithHint: true,
      ),
      validator: (v) {
        if (config.required && (v == null || v.trim().isEmpty)) {
          return '${config.label} is required';
        }
        if (customValidator != null) {
          return customValidator!(v);
        }
        return null;
      },
      onChanged: (v) => onChanged(v),
    );
  }

  Widget _buildDropdown() {
    // Determine the dropdown items based on options_source or inline options
    final items = _buildDropdownItems();

    return DropdownButtonFormField<dynamic>(
      value: currentValue,
      hint: Text(config.placeholder.isNotEmpty
          ? config.placeholder
          : 'Select ${config.label}'),
      isExpanded: true,
      items: items,
      onChanged: (v) => onChanged(v),
      decoration: InputDecoration(
        labelText: config.label,
        prefixIcon: Icon(config.iconData, size: 20, color: AppTheme.primaryColor),
      ),
      validator: (v) {
        if (config.required && v == null) {
          return 'Please select ${config.label.toLowerCase()}';
        }
        if (customValidator != null) {
          return customValidator!(v);
        }
        return null;
      },
    );
  }

  List<DropdownMenuItem<dynamic>> _buildDropdownItems() {
    // If options_source is 'categories', use the categories from provider
    if (config.optionsSource == 'categories' && categories != null) {
      return categories!.map((cat) {
        return DropdownMenuItem(
          value: cat.id,
          child: Row(
            children: [
              Icon(
                _getIconForCategory(cat.icon),
                size: 18,
                color: AppTheme.primaryColor,
              ),
              const SizedBox(width: 8),
              Text(cat.name),
            ],
          ),
        );
      }).toList();
    }

    // If external options are provided for this field name
    if (externalOptions != null && externalOptions!.containsKey(config.name)) {
      return externalOptions![config.name]!.map((opt) {
        return DropdownMenuItem(
          value: opt.value,
          child: Row(
            children: [
              Icon(opt.iconData, size: 18, color: opt.parsedColor),
              const SizedBox(width: 8),
              Text(opt.label),
            ],
          ),
        );
      }).toList();
    }

    // If inline options are provided
    if (config.options != null && config.options!.isNotEmpty) {
      return config.options!.map((opt) {
        return DropdownMenuItem(
          value: opt.value,
          child: Row(
            children: [
              if (opt.icon != null)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: Icon(opt.iconData, size: 18, color: opt.parsedColor),
                ),
              Text(opt.label),
            ],
          ),
        );
      }).toList();
    }

    return [];
  }

  IconData _getIconForCategory(String? icon) {
    switch (icon) {
      case 'road':
        return Icons.traffic;
      case 'warning':
        return Icons.warning_amber;
      case 'ac_unit':
        return Icons.ac_unit;
      case 'directions_bus':
        return Icons.directions_bus;
      case 'explore':
        return Icons.explore;
      case 'local_gas_station':
        return Icons.local_gas_station;
      case 'event':
        return Icons.event;
      case 'info':
        return Icons.info_outline;
      default:
        return Icons.assignment;
    }
  }
}