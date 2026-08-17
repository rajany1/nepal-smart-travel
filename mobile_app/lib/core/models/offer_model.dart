class OfferBusinessModel {
  final int id;
  final String name;
  final String type;
  final String? district;
  final String? address;

  OfferBusinessModel({
    required this.id,
    required this.name,
    required this.type,
    this.district,
    this.address,
  });

  factory OfferBusinessModel.fromJson(Map<String, dynamic> json) {
    return OfferBusinessModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      type: json['type'] ?? '',
      district: json['district'],
      address: json['address'],
    );
  }
}

class OfferModel {
  final int id;
  final String title;
  final String offerType; // percentage_off, fixed_off, free_item, buy_one_get_one
  final num? discountValue;
  final String label;
  final String? description;
  final String? terms;
  final String? startsAt;
  final String? endsAt;
  final int usageLimit;
  final int usedCount;
  final int priceXp;
  final OfferBusinessModel? business;

  OfferModel({
    required this.id,
    required this.title,
    required this.offerType,
    this.discountValue,
    required this.label,
    this.description,
    this.terms,
    this.startsAt,
    this.endsAt,
    this.usageLimit = 0,
    this.usedCount = 0,
    this.priceXp = 0,
    this.business,
  });

  factory OfferModel.fromJson(Map<String, dynamic> json) {
    return OfferModel(
      id: json['id'] ?? 0,
      title: json['title'] ?? '',
      offerType: json['offer_type'] ?? '',
      discountValue: json['discount_value'],
      label: json['label'] ?? (json['title'] ?? ''),
      description: json['description'],
      terms: json['terms'],
      startsAt: json['starts_at'],
      endsAt: json['ends_at'],
      usageLimit: json['usage_limit'] ?? 0,
      usedCount: json['used_count'] ?? 0,
      priceXp: json['price_xp'] ?? 0,
      business: json['business'] != null
          ? OfferBusinessModel.fromJson(json['business'] as Map<String, dynamic>)
          : null,
    );
  }

  bool get isExpired {
    if (endsAt == null) return false;
    final end = DateTime.tryParse(endsAt!);
    if (end == null) return false;
    return end.isBefore(DateTime.now());
  }

  bool get isUnlimited => usageLimit <= 0;

  String get remainingLabel =>
      isUnlimited ? 'Unlimited' : '${usageLimit - usedCount} left';
}

class OfferRedemptionModel {
  final int id;
  final int offerId;
  final String code;
  final String status; // claimed, used, expired
  final String? claimedAt;
  final String? usedAt;
  final String? appliedAt;
  final String? consumedAt;
  final int? bookingId;
  final OfferModel? offer;

  OfferRedemptionModel({
    required this.id,
    required this.offerId,
    required this.code,
    required this.status,
    this.claimedAt,
    this.usedAt,
    this.appliedAt,
    this.consumedAt,
    this.bookingId,
    this.offer,
  });

  factory OfferRedemptionModel.fromJson(Map<String, dynamic> json) {
    return OfferRedemptionModel(
      id: json['id'] ?? 0,
      offerId: json['offer_id'] ?? 0,
      code: json['code'] ?? '',
      status: json['status'] ?? 'claimed',
      claimedAt: json['claimed_at'],
      usedAt: json['used_at'],
      appliedAt: json['applied_at'],
      consumedAt: json['consumed_at'],
      bookingId: json['booking_id'],
      offer: json['offer'] != null
          ? OfferModel.fromJson(json['offer'] as Map<String, dynamic>)
          : null,
    );
  }

  bool get isAvailable =>
      status == 'claimed' && bookingId == null && consumedAt == null;
}

