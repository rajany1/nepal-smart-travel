import 'package:flutter/material.dart';

/// Field type enum for different form field types
enum ProfileFieldType {
  text,
  email,
  phone,
  textarea,
  select,
  multiselect,
  checkbox,
  date,
  number,
}

/// Single profile field option (for dropdowns)
class FieldOption {
  final String value;
  final String label;

  FieldOption({
    required this.value,
    required this.label,
  });

  factory FieldOption.fromJson(Map<String, dynamic> json) {
    return FieldOption(
      value: json['value']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() => {
    'value': value,
    'label': label,
  };
}

/// Profile field definition (schema for dynamic form building)
class ProfileFieldDefinition {
  final String name;
  final String label;
  final ProfileFieldType type;
  final bool required;
  final String? placeholder;
  final String? validation;
  final String? icon;
  final bool readonly;
  final int? rows;
  final int? maxLength;
  final int? maxItems;
  final String? optionsKey;
  final List<FieldOption> options;
  final dynamic defaultValue;

  ProfileFieldDefinition({
    required this.name,
    required this.label,
    required this.type,
    this.required = false,
    this.placeholder,
    this.validation,
    this.icon,
    this.readonly = false,
    this.rows,
    this.maxLength,
    this.maxItems,
    this.optionsKey,
    this.options = const [],
    this.defaultValue,
  });

  factory ProfileFieldDefinition.fromJson(Map<String, dynamic> json) {
    final typeStr = json['type']?.toString().toLowerCase() ?? 'text';
    ProfileFieldType fieldType = ProfileFieldType.text;

    switch (typeStr) {
      case 'email':
        fieldType = ProfileFieldType.email;
        break;
      case 'phone':
        fieldType = ProfileFieldType.phone;
        break;
      case 'textarea':
        fieldType = ProfileFieldType.textarea;
        break;
      case 'select':
        fieldType = ProfileFieldType.select;
        break;
      case 'multiselect':
        fieldType = ProfileFieldType.multiselect;
        break;
      case 'checkbox':
        fieldType = ProfileFieldType.checkbox;
        break;
      case 'date':
        fieldType = ProfileFieldType.date;
        break;
      case 'number':
        fieldType = ProfileFieldType.number;
        break;
      default:
        fieldType = ProfileFieldType.text;
    }

    final optionsList = (json['options'] as List?)
        ?.map((o) => FieldOption.fromJson(o is Map ? Map<String, dynamic>.from(o) : {}))
        .toList() ?? [];

    return ProfileFieldDefinition(
      name: json['name']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      type: fieldType,
      required: json['required'] ?? false,
      placeholder: json['placeholder']?.toString(),
      validation: json['validation']?.toString(),
      icon: json['icon']?.toString(),
      readonly: json['readonly'] ?? false,
      rows: json['rows'],
      maxLength: json['maxLength'],
      maxItems: json['max_items'],
      optionsKey: json['options_key']?.toString(),
      options: optionsList,
      defaultValue: json['default_value'],
    );
  }

  Map<String, dynamic> toJson() => {
    'name': name,
    'label': label,
    'type': type.toString().split('.').last,
    'required': required,
    'placeholder': placeholder,
    'validation': validation,
    'icon': icon,
    'readonly': readonly,
    'rows': rows,
    'maxLength': maxLength,
    'max_items': maxItems,
    'options_key': optionsKey,
    'options': options.map((o) => o.toJson()).toList(),
  };

  /// Get validation error message for given value
  String? validateValue(dynamic value) {
    // Check required
    if (required) {
      if (value == null || (value is String && value.isEmpty)) {
        return '$label is required';
      }
      if (value is List && value.isEmpty) {
        return '$label must have at least one item';
      }
    }

    // Parse validation rules
    if (validation != null && value != null && value is String && value.isNotEmpty) {
      final rules = validation!.split(',');
      for (final rule in rules) {
        final ruleName = rule.trim();

        if (ruleName.startsWith('min:')) {
          final minLength = int.tryParse(ruleName.substring(4)) ?? 0;
          if (value.length < minLength) {
            return '$label must be at least $minLength characters';
          }
        }

        if (ruleName.startsWith('max:')) {
          final maxLength = int.tryParse(ruleName.substring(4)) ?? 0;
          if (value.length > maxLength) {
            return '$label must be at most $maxLength characters';
          }
        }

        if (ruleName == 'email' && type == ProfileFieldType.email) {
          if (!RegExp(r'^[^@]+@[^@]+\.[^@]+$').hasMatch(value)) {
            return 'Please enter a valid email';
          }
        }
      }
    }

    return null;
  }
}

/// Field options collection (all available options for dropdowns)
class ProfileFieldOptions {
  final List<FieldOption> genders;
  final List<FieldOption> interests;
  final List<FieldOption> expertiseRegions;

  ProfileFieldOptions({
    this.genders = const [],
    this.interests = const [],
    this.expertiseRegions = const [],
  });

  factory ProfileFieldOptions.fromJson(Map<String, dynamic> json) {
    return ProfileFieldOptions(
      genders: (json['genders'] as List?)
          ?.map((o) => FieldOption.fromJson(o is Map ? Map<String, dynamic>.from(o) : {}))
          .toList() ?? [],
      interests: (json['interests'] as List?)
          ?.map((o) => FieldOption.fromJson(o is Map ? Map<String, dynamic>.from(o) : {}))
          .toList() ?? [],
      expertiseRegions: (json['expertise_regions'] as List?)
          ?.map((o) => FieldOption.fromJson(o is Map ? Map<String, dynamic>.from(o) : {}))
          .toList() ?? [],
    );
  }

  Map<String, dynamic> toJson() => {
    'genders': genders.map((o) => o.toJson()).toList(),
    'interests': interests.map((o) => o.toJson()).toList(),
    'expertise_regions': expertiseRegions.map((o) => o.toJson()).toList(),
  };

  /// Get options for a specific field by optionsKey
  List<FieldOption> getOptionsForField(String? optionsKey) {
    switch (optionsKey?.toLowerCase()) {
      case 'genders':
        return genders;
      case 'interests':
        return interests;
      case 'expertise_regions':
        return expertiseRegions;
      default:
        return [];
    }
  }
}

/// Response wrapper for field definitions
class ProfileFieldDefinitionsResponse {
  final bool success;
  final List<ProfileFieldDefinition> fields;

  ProfileFieldDefinitionsResponse({
    required this.success,
    required this.fields,
  });

  factory ProfileFieldDefinitionsResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] ?? {};
    final fieldsList = (data['fields'] as List?)
        ?.map((f) => ProfileFieldDefinition.fromJson(f is Map ? Map<String, dynamic>.from(f) : {}))
        .toList() ?? [];

    return ProfileFieldDefinitionsResponse(
      success: json['success'] ?? false,
      fields: fieldsList,
    );
  }
}

/// Response wrapper for field options
class ProfileFieldOptionsResponse {
  final bool success;
  final ProfileFieldOptions options;

  ProfileFieldOptionsResponse({
    required this.success,
    required this.options,
  });

  factory ProfileFieldOptionsResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] ?? {};
    return ProfileFieldOptionsResponse(
      success: json['success'] ?? false,
      options: ProfileFieldOptions.fromJson(data),
    );
  }
}
