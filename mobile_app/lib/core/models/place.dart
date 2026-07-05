class Place {
  final String id;
  final String name;
  final String? description;
  final String category;
  final double latitude;
  final double longitude;
  final String? address;
  final String? district;
  final String? phone;
  final String? email;
  final String? website;
  final Map<String, dynamic>? operatingHours;
  final double averageRating;
  final int totalReviews;
  final List<String> images;
  final List<String> amenities;
  final double distanceKm;
  final bool isVerified;
  final bool isFeatured;
  final String source;

  Place({
    required this.id,
    required this.name,
    this.description,
    required this.category,
    required this.latitude,
    required this.longitude,
    this.address,
    this.district,
    this.phone,
    this.email,
    this.website,
    this.operatingHours,
    this.averageRating = 0,
    this.totalReviews = 0,
    this.images = const [],
    this.amenities = const [],
    this.distanceKm = 0,
    this.isVerified = false,
    this.isFeatured = false,
    this.source = 'admin',
  });

  factory Place.fromJson(Map<String, dynamic> json) {
    final location = json['location'] ?? {};
    return Place(
      id: json['place_id'] ?? json['id'] ?? '',
      name: json['name'] ?? '',
      description: json['description'],
      category: json['category'] ?? 'General',
      latitude: double.tryParse((location['lat'] ?? json['latitude'] ?? 0).toString()) ?? 0.0,
      longitude: double.tryParse((location['lng'] ?? json['longitude'] ?? 0).toString()) ?? 0.0,
      address: json['address'],
      district: json['district'],
      phone: json['phone'],
      email: json['email'],
      website: json['website'],
      operatingHours: json['operating_hours'],
      averageRating: double.tryParse((json['average_rating'] ?? 0).toString()) ?? 0.0,
      totalReviews: json['total_reviews'] ?? 0,
      images: List<String>.from(json['images'] ?? []),
      amenities: List<String>.from(json['amenities'] ?? []),
      distanceKm: double.tryParse((json['distance_km'] ?? 0).toString()) ?? 0.0,
      isVerified: json['is_verified'] ?? false,
      isFeatured: json['is_featured'] ?? false,
      source: json['source'] ?? 'admin',
    );
  }

  Map<String, dynamic> toJson() => {
    'place_id': id,
    'name': name,
    'description': description,
    'category': category,
    'location': {'lat': latitude, 'lng': longitude},
    'address': address,
    'district': district,
    'phone': phone,
    'email': email,
    'website': website,
    'operating_hours': operatingHours,
    'average_rating': averageRating,
    'total_reviews': totalReviews,
    'images': images,
    'amenities': amenities,
    'distance_km': distanceKm,
    'is_verified': isVerified,
    'is_featured': isFeatured,
    'source': source,
  };
}

class Review {
  final String id;
  final String placeId;
  final String userId;
  final String userName;
  final String? userAvatar;
  final String? title;
  final String? description;
  final int rating;
  final List<String> images;
  final DateTime createdAt;

  Review({
    required this.id,
    required this.placeId,
    required this.userId,
    required this.userName,
    this.userAvatar,
    this.title,
    this.description,
    required this.rating,
    this.images = const [],
    DateTime? createdAt,
  }) : createdAt = createdAt ?? DateTime.now();

  factory Review.fromJson(Map<String, dynamic> json) {
    return Review(
      id: json['review_id'] ?? json['id'] ?? '',
      placeId: json['place_id'] ?? '',
      userId: json['user_id'] ?? '',
      userName: json['user_name'] ?? '',
      userAvatar: json['user_avatar'],
      title: json['title'],
      description: json['description'],
      rating: json['rating'] ?? 0,
      images: List<String>.from(json['images'] ?? []),
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'])
          : DateTime.now(),
    );
  }
}