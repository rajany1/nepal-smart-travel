import 'package:flutter/material.dart';

class BookingPartner {
  final int id;
  final String name;
  final String? type;
  final String? phone;
  final String? email;
  final String? logo;
  final String? district;

  BookingPartner({
    required this.id,
    required this.name,
    this.type,
    this.phone,
    this.email,
    this.logo,
    this.district,
  });

  factory BookingPartner.fromJson(Map<String, dynamic> json) {
    return BookingPartner(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      type: json['type'],
      phone: json['phone'],
      email: json['email'],
      logo: json['logo_url'] ?? json['logo'],
      district: json['district'],
    );
  }
}

class BookingShopCode {
  final int id;
  final String? code;
  final String? itemName;
  final String? discountType;
  final double? discountValue;

  BookingShopCode({
    required this.id,
    this.code,
    this.itemName,
    this.discountType,
    this.discountValue,
  });

  factory BookingShopCode.fromJson(Map<String, dynamic> json) {
    final item = json['shop_item'] as Map<String, dynamic>?;
    return BookingShopCode(
      id: json['id'] ?? 0,
      code: json['code'],
      itemName: item?['name'] as String?,
      discountType: item?['discount_type'] as String?,
      discountValue: double.tryParse(item?['discount_value']?.toString() ?? ''),
    );
  }
}

class BookingModel {
  final int id;
  final int travelPartnerId;
  final int? userId;
  final String customerName;
  final String? customerPhone;
  final String? customerEmail;
  final double amount;
  final double commissionEarned;
  final double rewardPoolShare;
  final double discountAmount;
  final String status;
  final String? notes;
  final DateTime? bookedAt;
  final DateTime? confirmedAt;
  final DateTime? completedAt;
  final DateTime? cancelledAt;
  final DateTime? createdAt;
  final BookingPartner? travelPartner;
  final BookingShopCode? shopCode;

  BookingModel({
    required this.id,
    required this.travelPartnerId,
    this.userId,
    required this.customerName,
    this.customerPhone,
    this.customerEmail,
    this.amount = 0,
    this.commissionEarned = 0,
    this.rewardPoolShare = 0,
    this.discountAmount = 0,
    this.status = 'pending',
    this.notes,
    this.bookedAt,
    this.confirmedAt,
    this.completedAt,
    this.cancelledAt,
    this.createdAt,
    this.travelPartner,
    this.shopCode,
  });

  factory BookingModel.fromJson(Map<String, dynamic> json) {
    return BookingModel(
      id: json['id'] ?? 0,
      travelPartnerId: json['travel_partner_id'] ?? 0,
      userId: json['user_id'],
      customerName: json['customer_name'] ?? '',
      customerPhone: json['customer_phone'],
      customerEmail: json['customer_email'],
      amount: double.tryParse(json['amount']?.toString() ?? '0') ?? 0,
      commissionEarned: double.tryParse(json['commission_earned']?.toString() ?? '0') ?? 0,
      rewardPoolShare: double.tryParse(json['reward_pool_share']?.toString() ?? '0') ?? 0,
      discountAmount: double.tryParse(json['discount_amount']?.toString() ?? '0') ?? 0,
      status: json['status'] ?? 'pending',
      notes: json['notes'],
      bookedAt: json['booked_at'] != null ? DateTime.parse(json['booked_at']) : null,
      confirmedAt: json['confirmed_at'] != null ? DateTime.parse(json['confirmed_at']) : null,
      completedAt: json['completed_at'] != null ? DateTime.parse(json['completed_at']) : null,
      cancelledAt: json['cancelled_at'] != null ? DateTime.parse(json['cancelled_at']) : null,
      createdAt: json['created_at'] != null ? DateTime.parse(json['created_at']) : null,
      travelPartner: json['travel_partner'] != null
          ? BookingPartner.fromJson(json['travel_partner'] as Map<String, dynamic>)
          : (json['travelPartner'] != null
              ? BookingPartner.fromJson(json['travelPartner'] as Map<String, dynamic>)
              : null),
      shopCode: json['shop_code'] != null
          ? BookingShopCode.fromJson(json['shop_code'] as Map<String, dynamic>)
          : null,
    );
  }

  double get finalAmount => (amount - discountAmount).clamp(0, amount);

  bool get isPending => status == 'pending';
  bool get isConfirmed => status == 'confirmed';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';

  Color get statusColor {
    switch (status) {
      case 'confirmed':
        return const Color(0xFF1976D2);
      case 'completed':
        return const Color(0xFF388E3C);
      case 'cancelled':
        return const Color(0xFFD32F2F);
      default:
        return const Color(0xFFFF8F00);
    }
  }

  String get statusLabel {
    switch (status) {
      case 'confirmed':
        return 'Confirmed';
      case 'completed':
        return 'Completed';
      case 'cancelled':
        return 'Cancelled';
      default:
        return 'Pending';
    }
  }

  String get timeAgo {
    final dt = createdAt;
    if (dt == null) return '';
    final now = DateTime.now();
    final diff = now.difference(dt);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    if (diff.inDays < 7) return '${diff.inDays}d ago';
    return '${dt.day}/${dt.month}/${dt.year}';
  }
}
