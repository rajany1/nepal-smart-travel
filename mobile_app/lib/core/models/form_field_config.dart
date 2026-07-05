import 'package:flutter/material.dart';

/// Represents a single field definition from the dynamic form config API
class FormFieldConfig {
  final String name;
  final String label;
  final String type;
  final bool required;
  final String validation;
  final String placeholder;
  final String icon;
  final int order;
  final String? optionsSource;
  final List<FormFieldOption>? options;
  final int? rows;

  FormFieldConfig({
    required this.name,
    required this.label,
    required this.type,
    required this.required,
    this.validation = '',
    this.placeholder = '',
    this.icon = '',
    this.order = 0,
    this.optionsSource,
    this.options,
    this.rows,
  });

  factory FormFieldConfig.fromJson(Map<String, dynamic> json) {
    return FormFieldConfig(
      name: json['name'] ?? '',
      label: json['label'] ?? '',
      type: json['type'] ?? 'text',
      required: json['required'] ?? false,
      validation: json['validation'] ?? '',
      placeholder: json['placeholder'] ?? '',
      icon: json['icon'] ?? '',
      order: json['order'] ?? 0,
      optionsSource: json['options_source'],
      options: json['options'] != null
          ? (json['options'] as List)
              .map((o) => FormFieldOption.fromJson(o))
              .toList()
          : null,
      rows: json['rows'],
    );
  }

  /// Map the icon string from backend to an Flutter IconData
  IconData get iconData {
    switch (icon) {
      case 'title':
        return Icons.title;
      case 'category':
        return Icons.category;
      case 'flag':
        return Icons.flag;
      case 'description':
        return Icons.description;
      case 'person':
        return Icons.person;
      case 'email':
        return Icons.email;
      case 'phone':
        return Icons.phone;
      case 'location_on':
        return Icons.location_on;
      case 'favorite':
        return Icons.favorite;
      case 'wc':
        return Icons.wc;
      case 'info':
        return Icons.info_outline;
      case 'warning':
        return Icons.warning_amber;
      case 'road':
        return Icons.traffic;
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
      case 'info_outline':
        return Icons.info_outline;
      default:
        return Icons.edit_square;
    }
  }
}

/// Represents an option in a select/dropdown/multiselect field
class FormFieldOption {
  final String value;
  final String label;
  final String? icon;
  final String? color;

  FormFieldOption({
    required this.value,
    required this.label,
    this.icon,
    this.color,
  });

  factory FormFieldOption.fromJson(Map<String, dynamic> json) {
    return FormFieldOption(
      value: json['value'] ?? '',
      label: json['label'] ?? '',
      icon: json['icon'],
      color: json['color'],
    );
  }

  /// Map the icon string to Flutter IconData
  IconData get iconData {
    switch (icon) {
      case 'info_outline':
        return Icons.info_outline;
      case 'warning':
        return Icons.warning_amber;
      case 'check_circle':
        return Icons.check_circle;
      default:
        return Icons.circle;
    }
  }

  /// Parse hex color string to Flutter Color
  Color get parsedColor {
    if (color == null || color!.isEmpty) return Colors.grey;
    try {
      final hex = color!.replaceFirst('#', '');
      final value = int.parse(hex, radix: 16);
      return Color(0xFF000000 | value);
    } catch (_) {
      return Colors.grey;
    }
  }
}

/// The complete form configuration from the backend
class ReportFormConfig {
  final List<FormFieldConfig> fields;
  final String submitButtonText;
  final String notice;

  ReportFormConfig({
    required this.fields,
    this.submitButtonText = 'Submit Report',
    this.notice = '',
  });

  factory ReportFormConfig.fromJson(Map<String, dynamic> json) {
    return ReportFormConfig(
      fields: (json['fields'] as List? ?? [])
          .map((f) => FormFieldConfig.fromJson(f))
          .toList()
        ..sort((a, b) => a.order.compareTo(b.order)),
      submitButtonText: json['submit_button_text'] ?? 'Submit Report',
      notice: json['notice'] ?? '',
    );
  }

  /// Get a field config by its name
  FormFieldConfig? field(String name) {
    try {
      return fields.firstWhere((f) => f.name == name);
    } catch (_) {
      return null;
    }
  }
}