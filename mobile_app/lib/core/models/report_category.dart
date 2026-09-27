import 'package:flutter/foundation.dart';

/// Represents a report category group (e.g., Travel, Community, Safety, Services)
class ReportCategoryGroup {
  final int id;
  final String name;
  final String slug;
  final String? nameNe;
  final String? description;
  final String? descriptionNe;
  final String? icon;
  final String iconType;
  final String? iconColor;
  final String? iconBackground;
  final int sortOrder;
  final bool isActive;
  final bool isEmergencyGroup;
  final List<ReportCategory> categories;

  ReportCategoryGroup({
    required this.id,
    required this.name,
    required this.slug,
    this.nameNe,
    this.description,
    this.descriptionNe,
    this.icon,
    this.iconType = 'material',
    this.iconColor,
    this.iconBackground,
    this.sortOrder = 0,
    this.isActive = true,
    this.isEmergencyGroup = false,
    this.categories = const [],
  });

  factory ReportCategoryGroup.fromJson(Map<String, dynamic> json) {
    return ReportCategoryGroup(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      nameNe: json['name_ne'],
      description: json['description'],
      descriptionNe: json['description_ne'],
      icon: json['icon'],
      iconType: json['icon_type'] ?? 'material',
      iconColor: json['icon_color'],
      iconBackground: json['icon_background'],
      sortOrder: json['sort_order'] ?? 0,
      isActive: json['is_active'] ?? true,
      isEmergencyGroup: json['is_emergency_group'] ?? false,
      categories: (json['categories'] as List? ?? [])
          .map((c) => ReportCategory.fromJson(c))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'slug': slug,
        'name_ne': nameNe,
        'description': description,
        'description_ne': descriptionNe,
        'icon': icon,
        'icon_type': iconType,
        'icon_color': iconColor,
        'icon_background': iconBackground,
        'sort_order': sortOrder,
        'is_active': isActive,
        'is_emergency_group': isEmergencyGroup,
        'categories': categories.map((c) => c.toJson()).toList(),
      };

  /// Get localized name
  String getLocalizedName(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && nameNe != null && nameNe!.isNotEmpty) {
      return nameNe!;
    }
    return name;
  }

  /// Get localized name using BuildContext
  String getLocalizedName(BuildContext context) {
    return getLocalizedName(context.read<LocalizationService>());
  }

  /// Get localized description
  String? getLocalizedDescription(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && descriptionNe != null && descriptionNe!.isNotEmpty) {
      return descriptionNe;
    }
    return description;
  }

  /// Get localized description using BuildContext
  String? getLocalizedDescription(BuildContext context) {
    return getLocalizedDescription(context.read<LocalizationService>());
  }
}

/// Represents a report category (e.g., Road & Traffic, Weather & Conditions)
class ReportCategory {
  final int id;
  final String name;
  final String slug;
  final String? nameNe;
  final String? description;
  final String? descriptionNe;
  final String? icon;
  final String iconType;
  final String? iconColor;
  final String? iconBackground;
  final bool isFeatured;
  final bool isEmergency;
  final int usageCount;
  final List<ReportCategoryOption> options;
  final List<ReportCategoryField> fields;
  final int? groupId;

  ReportCategory({
    required this.id,
    required this.name,
    required this.slug,
    this.nameNe,
    this.description,
    this.descriptionNe,
    this.icon,
    this.iconType = 'material',
    this.iconColor,
    this.iconBackground,
    this.isFeatured = false,
    this.isEmergency = false,
    this.usageCount = 0,
    this.options = const [],
    this.fields = const [],
    this.groupId,
  });

  factory ReportCategory.fromJson(Map<String, dynamic> json) {
    return ReportCategory(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      nameNe: json['name_ne'],
      description: json['description'],
      descriptionNe: json['description_ne'],
      icon: json['icon'],
      iconType: json['icon_type'] ?? 'material',
      iconColor: json['icon_color'],
      iconBackground: json['icon_background'],
      isFeatured: json['is_featured'] ?? false,
      isEmergency: json['is_emergency'] ?? false,
      usageCount: json['usage_count'] ?? 0,
      options: (json['options'] as List? ?? [])
          .map((o) => ReportCategoryOption.fromJson(o))
          .toList(),
      fields: (json['fields'] as List? ?? [])
          .map((f) => ReportCategoryField.fromJson(f))
          .toList(),
      groupId: json['group']?['id'],
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'slug': slug,
        'name_ne': nameNe,
        'description': description,
        'description_ne': descriptionNe,
        'icon': icon,
        'icon_type': iconType,
        'icon_color': iconColor,
        'icon_background': iconBackground,
        'is_featured': isFeatured,
        'is_emergency': isEmergency,
        'usage_count': usageCount,
        'options': options.map((o) => o.toJson()).toList(),
        'fields': fields.map((f) => f.toJson()).toList(),
      };

  /// Get localized name
  String getLocalizedName(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && nameNe != null && nameNe!.isNotEmpty) {
      return nameNe!;
    }
    return name;
  }

  /// Get localized name using BuildContext
  String getLocalizedName(BuildContext context) {
    return getLocalizedName(context.read<LocalizationService>());
  }

  /// Get localized description
  String? getLocalizedDescription(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && descriptionNe != null && descriptionNe!.isNotEmpty) {
      return descriptionNe;
    }
    return description;
  }

  /// Get localized description using BuildContext
  String? getLocalizedDescription(BuildContext context) {
    return getLocalizedDescription(context.read<LocalizationService>());
  }

  /// Get the primary subcategory option if any
  ReportCategoryOption? get primaryOption => options.isNotEmpty ? options.first : null;

  /// Check if category has dynamic form fields beyond description
  bool get hasCustomFields => fields.any((f) => f.name != 'description');

  /// Get form fields excluding the base description field
  List<ReportCategoryField> get customFields => fields.where((f) => f.name != 'description').toList();
}

/// Represents a subcategory option within a category (e.g., Pothole, Flooding, Heavy Rain)
class ReportCategoryOption {
  final int id;
  final int categoryId;
  final String name;
  final String slug;
  final String? nameNe;
  final String? description;
  final String? descriptionNe;
  final String? icon;
  final String iconType;
  final int sortOrder;
  final bool isActive;
  final bool requiresPhoto;
  final bool requiresLocation;

  ReportCategoryOption({
    required this.id,
    required this.categoryId,
    required this.name,
    required this.slug,
    this.nameNe,
    this.description,
    this.descriptionNe,
    this.icon,
    this.iconType = 'material',
    this.sortOrder = 0,
    this.isActive = true,
    this.requiresPhoto = false,
    this.requiresLocation = false,
  });

  factory ReportCategoryOption.fromJson(Map<String, dynamic> json) {
    return ReportCategoryOption(
      id: json['id'] ?? 0,
      categoryId: json['category_id'] ?? 0,
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      nameNe: json['name_ne'],
      description: json['description'],
      descriptionNe: json['description_ne'],
      icon: json['icon'],
      iconType: json['icon_type'] ?? 'material',
      sortOrder: json['sort_order'] ?? 0,
      isActive: json['is_active'] ?? true,
      requiresPhoto: json['requires_photo'] ?? false,
      requiresLocation: json['requires_location'] ?? false,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'category_id': categoryId,
        'name': name,
        'slug': slug,
        'name_ne': nameNe,
        'description': description,
        'description_ne': descriptionNe,
        'icon': icon,
        'icon_type': iconType,
        'sort_order': sortOrder,
        'is_active': isActive,
        'requires_photo': requiresPhoto,
        'requires_location': requiresLocation,
      };

  /// Get localized name
  String getLocalizedName(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && nameNe != null && nameNe!.isNotEmpty) {
      return nameNe!;
    }
    return name;
  }

  /// Get localized name using BuildContext
  String getLocalizedName(BuildContext context) {
    return getLocalizedName(context.read<LocalizationService>());
  }

  /// Get localized description
  String? getLocalizedDescription(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && descriptionNe != null && descriptionNe!.isNotEmpty) {
      return descriptionNe;
    }
    return description;
  }

  /// Get localized description using BuildContext
  String? getLocalizedDescription(BuildContext context) {
    return getLocalizedDescription(context.read<LocalizationService>());
  }
}

/// Represents a dynamic form field for a category
class ReportCategoryField {
  final String name;
  final String label;
  final String? labelNe;
  final String? placeholder;
  final String? placeholderNe;
  final String type; // single_select, multi_select, text, textarea, number, photo, location, severity, date, time, yes_no
  final bool required;
  final int sortOrder;
  final List<FieldOption> options;
  final Map<String, dynamic>? validation;
  final String? helpText;
  final String? helpTextNe;
  final bool showInPreview;

  ReportCategoryField({
    required this.name,
    required this.label,
    this.labelNe,
    this.placeholder,
    this.placeholderNe,
    required this.type,
    this.required = false,
    this.sortOrder = 0,
    this.options = const [],
    this.validation,
    this.helpText,
    this.helpTextNe,
    this.showInPreview = true,
  });

  factory ReportCategoryField.fromJson(Map<String, dynamic> json) {
    final optionsList = json['options'] as List? ?? [];
    return ReportCategoryField(
      name: json['name'] ?? '',
      label: json['label'] ?? '',
      labelNe: json['label_ne'],
      placeholder: json['placeholder'],
      placeholderNe: json['placeholder_ne'],
      type: json['type'] ?? 'text',
      required: json['required'] ?? false,
      sortOrder: json['sort_order'] ?? 0,
      options: optionsList.map((o) => FieldOption.fromJson(o)).toList(),
      validation: json['validation'] != null ? Map<String, dynamic>.from(json['validation']) : null,
      helpText: json['help_text'],
      helpTextNe: json['help_text_ne'],
      showInPreview: json['show_in_preview'] ?? true,
    );
  }

  Map<String, dynamic> toJson() => {
        'name': name,
        'label': label,
        'label_ne': labelNe,
        'placeholder': placeholder,
        'placeholder_ne': placeholderNe,
        'type': type,
        'required': required,
        'sort_order': sortOrder,
        'options': options.map((o) => o.toJson()).toList(),
        'validation': validation,
        'help_text': helpText,
        'help_text_ne': helpTextNe,
        'show_in_preview': showInPreview,
      };

  /// Get localized label
  String getLocalizedLabel(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && labelNe != null && labelNe!.isNotEmpty) {
      return labelNe!;
    }
    return label;
  }

  /// Get localized placeholder
  String? getLocalizedPlaceholder(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && placeholderNe != null && placeholderNe!.isNotEmpty) {
      return placeholderNe;
    }
    return placeholder;
  }

  /// Get localized help text
  String? getLocalizedHelpText(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && helpTextNe != null && helpTextNe!.isNotEmpty) {
      return helpTextNe;
    }
    return helpText;
  }

  /// Check if this is a select field
  bool get isSelectField => type == 'single_select' || type == 'multi_select';

  /// Check if this field allows multiple selections
  bool get isMultiSelect => type == 'multi_select';

  /// Get validation rules as a list of strings
  List<String> get validationRules {
    if (validation == null) return [];
    return validation!.values.expand((v) => v.toString().split('|')).toList();
  }
}

/// Represents an option for select fields
class FieldOption {
  final String value;
  final String label;
  final String? labelNe;

  FieldOption({
    required this.value,
    required this.label,
    this.labelNe,
  });

  factory FieldOption.fromJson(Map<String, dynamic> json) {
    return FieldOption(
      value: json['value']?.toString() ?? '',
      label: json['label']?.toString() ?? '',
      labelNe: json['label_ne']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'value': value,
        'label': label,
        'label_ne': labelNe,
      };

  /// Get localized label
  String getLocalizedLabel(LocalizationService localization) {
    if (localization.currentLocale == 'ne' && labelNe != null && labelNe!.isNotEmpty) {
      return labelNe!;
    }
    return label;
  }

  /// Get localized label using BuildContext
  String getLocalizedLabel(BuildContext context) {
    return getLocalizedLabel(context.read<LocalizationService>());
  }
}