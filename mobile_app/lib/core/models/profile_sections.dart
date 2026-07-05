// Profile display sections configuration
// Defines which sections render on the profile display screen

class ProfileSection {
  final String id;
  final String type;
  final String title;
  final bool visible;
  final int order;
  final String? description;
  final List<SectionField>? fields;
  final Map<String, dynamic>? stats;
  final int? limit;
  final bool? showSeeMore;

  ProfileSection({
    required this.id,
    required this.type,
    required this.title,
    required this.visible,
    required this.order,
    this.description,
    this.fields,
    this.stats,
    this.limit,
    this.showSeeMore,
  });

  factory ProfileSection.fromJson(Map<String, dynamic> json) {
    return ProfileSection(
      id: json['id'] as String? ?? '',
      type: json['type'] as String? ?? '',
      title: json['title'] as String? ?? '',
      visible: json['visible'] as bool? ?? true,
      order: json['order'] as int? ?? 0,
      description: json['description'] as String?,
      fields: (json['fields'] as List<dynamic>?)
          ?.map((f) => SectionField.fromJson(f as Map<String, dynamic>))
          .toList(),
      stats: json['stats'] as Map<String, dynamic>?,
      limit: json['limit'] as int?,
      showSeeMore: json['show_see_more'] as bool?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'type': type,
      'title': title,
      'visible': visible,
      'order': order,
      'description': description,
      'fields': fields?.map((f) => f.toJson()).toList(),
      'stats': stats,
      'limit': limit,
      'show_see_more': showSeeMore,
    };
  }
}

class SectionField {
  final String key;
  final String label;
  final String? icon;
  final String? unit;

  SectionField({
    required this.key,
    required this.label,
    this.icon,
    this.unit,
  });

  factory SectionField.fromJson(Map<String, dynamic> json) {
    return SectionField(
      key: json['key'] as String? ?? '',
      label: json['label'] as String? ?? '',
      icon: json['icon'] as String?,
      unit: json['unit'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'key': key,
      'label': label,
      'icon': icon,
      'unit': unit,
    };
  }
}

class ProfileSectionsResponse {
  final bool success;
  final List<ProfileSection> sections;

  ProfileSectionsResponse({
    required this.success,
    required this.sections,
  });

  factory ProfileSectionsResponse.fromJson(Map<String, dynamic> json) {
    return ProfileSectionsResponse(
      success: json['success'] as bool? ?? false,
      sections: (json['data'] as List<dynamic>?)
          ?.map((s) => ProfileSection.fromJson(s as Map<String, dynamic>))
          .toList()
          .where((s) => s.visible)
          .toList()
          ?? [],
    );
  }
}
