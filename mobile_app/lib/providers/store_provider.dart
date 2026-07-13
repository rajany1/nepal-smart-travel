import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import '../core/api/api_client.dart';

class SponsorInfo {
  final int id;
  final String name;
  final String? logo;
  final String? description;
  final String? address;
  final String? website;
  final double? latitude;
  final double? longitude;

  SponsorInfo({
    required this.id,
    required this.name,
    this.logo,
    this.description,
    this.address,
    this.website,
    this.latitude,
    this.longitude,
  });

  bool get hasLocation => latitude != null && longitude != null;

  factory SponsorInfo.fromJson(Map<String, dynamic> json) {
    return SponsorInfo(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      logo: json['logo_url'] ?? json['logo'],
      description: json['description'],
      address: json['address'],
      website: json['website'],
      latitude: (json['latitude'] is num) ? (json['latitude'] as num).toDouble() : double.tryParse(json['latitude']?.toString() ?? ''),
      longitude: (json['longitude'] is num) ? (json['longitude'] as num).toDouble() : double.tryParse(json['longitude']?.toString() ?? ''),
    );
  }
}

class ShopItem {
  final int id;
  final String name;
  final String? description;
  final String icon;
  final SponsorInfo? sponsor;
  final String rewardType;
  final String? discountType;
  final double? discountValue;
  final int priceXp;
  final int minLevel;
  final String stockType;
  final int stockQty;
  final bool isActive;
  final int sortOrder;
  final String? terms;
  final int? expiryDays;
  final int? usageLimitPerUser;
  final String? redemptionInstructions;

  ShopItem({
    required this.id,
    required this.name,
    this.description,
    this.icon = 'fa-gift',
    this.sponsor,
    this.rewardType = 'voucher',
    this.discountType,
    this.discountValue,
    required this.priceXp,
    this.minLevel = 1,
    this.stockType = 'unlimited',
    this.stockQty = 0,
    this.isActive = true,
    this.sortOrder = 0,
    this.terms,
    this.expiryDays,
    this.usageLimitPerUser,
    this.redemptionInstructions,
  });

  factory ShopItem.fromJson(Map<String, dynamic> json) {
    final sponsorJson = json['sponsor'];
    return ShopItem(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      description: json['description'],
      icon: json['icon'] ?? 'fa-gift',
      sponsor: sponsorJson != null ? SponsorInfo.fromJson(sponsorJson) : null,
      rewardType: json['reward_type'] ?? 'voucher',
      discountType: json['discount_type'],
      discountValue: double.tryParse(json['discount_value']?.toString() ?? ''),
      priceXp: json['price_xp'] ?? 0,
      minLevel: json['min_level'] ?? 1,
      stockType: json['stock_type'] ?? 'unlimited',
      stockQty: json['stock_qty'] ?? 0,
      isActive: json['is_active'] ?? true,
      sortOrder: json['sort_order'] ?? 0,
      terms: json['terms'],
      expiryDays: json['expiry_days'],
      usageLimitPerUser: json['usage_limit_per_user'],
      redemptionInstructions: json['redemption_instructions'],
    );
  }

  IconData get iconData {
    switch (icon) {
      case 'fa-mobile-alt':
      case 'fa-mobile':
        return Icons.phone_android;
      case 'fa-credit-card':
        return Icons.credit_card;
      case 'fa-gift-card':
      case 'fa-gift':
        return Icons.card_giftcard;
      case 'fa-palette':
        return Icons.palette;
      case 'fa-star':
        return Icons.star;
      case 'fa-coffee':
        return Icons.free_breakfast;
      case 'fa-hiking':
      case 'fa-mountain':
        return Icons.terrain;
      case 'fa-hotel':
        return Icons.hotel;
      case 'fa-utensils':
        return Icons.restaurant;
      default:
        return Icons.card_giftcard;
    }
  }

  String get rewardLabel {
    switch (rewardType) {
      case 'discount':
        return 'Discount';
      case 'free_item':
        return 'Free Item';
      case 'voucher':
        return 'Voucher';
      case 'special_offer':
        return 'Special Offer';
      default:
        return rewardType;
    }
  }
}

class Purchase {
  final int id;
  final int userId;
  final int shopItemId;
  final int xpSpent;
  final String status;
  final String? fulfillmentNote;
  final String? fulfilledAt;
  final String? cancelledAt;
  final String? cancellationReason;
  final int? shopCodeId;
  final ShopItem? shopItem;
  final String? code;
  final String? codeStatus; // 'assigned', 'applied', 'consumed'
  final String createdAt;

  Purchase({
    required this.id,
    required this.userId,
    required this.shopItemId,
    required this.xpSpent,
    required this.status,
    this.fulfillmentNote,
    this.fulfilledAt,
    this.cancelledAt,
    this.cancellationReason,
    this.shopCodeId,
    this.shopItem,
    this.code,
    this.codeStatus,
    required this.createdAt,
  });

  factory Purchase.fromJson(Map<String, dynamic> json) {
    final shopItemJson = json['shop_item'] ?? json['shopItem'];
    final shopCodeJson = json['shop_code'] ?? json['shopCode'];
    String? codeStatus;
    if (shopCodeJson != null) {
      if (shopCodeJson['consumed_at'] != null) {
        codeStatus = 'consumed';
      } else if (shopCodeJson['booking_id'] != null) {
        codeStatus = 'applied';
      } else {
        codeStatus = 'assigned';
      }
    }
    return Purchase(
      id: json['id'] ?? 0,
      userId: json['user_id'] ?? 0,
      shopItemId: json['shop_item_id'],
      xpSpent: json['xp_spent'] ?? 0,
      status: json['status'] ?? 'pending',
      fulfillmentNote: json['fulfillment_note'],
      fulfilledAt: json['fulfilled_at'],
      cancelledAt: json['cancelled_at'],
      cancellationReason: json['cancellation_reason'],
      shopCodeId: json['shop_code_id'],
      shopItem: shopItemJson != null ? ShopItem.fromJson(shopItemJson) : null,
      code: shopCodeJson != null ? shopCodeJson['code'] : null,
      codeStatus: codeStatus,
      createdAt: json['created_at'] ?? '',
    );
  }
}

class StoreProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<ShopItem> _items = [];
  List<Purchase> _purchases = [];
  List<Map<String, dynamic>> _availableCodes = [];
  bool _isLoading = false;
  bool _isPurchasing = false;
  String? _errorMessage;

  List<ShopItem> get items => _items;
  List<Purchase> get purchases => _purchases;
  List<Map<String, dynamic>> get availableCodes => _availableCodes;
  bool get isLoading => _isLoading;
  bool get isPurchasing => _isPurchasing;
  String? get errorMessage => _errorMessage;

  Future<void> loadItems() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.getStoreItems();
      final data = response.data['data'] as List? ?? [];
      _items = data.map((j) => ShopItem.fromJson(j as Map<String, dynamic>)).toList();
    } catch (e) {
      _errorMessage = 'Failed to load store items';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadPurchases() async {
    try {
      final response = await _api.getMyPurchases();
      final data = response.data['data'] as List? ?? [];
      _purchases = data.map((j) => Purchase.fromJson(j as Map<String, dynamic>)).toList();
      notifyListeners();
    } catch (e) {
      print(' Failed to load purchases: $e');
    }
  }

  Future<void> loadAvailableCodes() async {
    try {
      final response = await _api.getAvailableCodes();
      final data = response.data['data'] as List? ?? [];
      _availableCodes = data.cast<Map<String, dynamic>>();
      notifyListeners();
    } catch (e) {
      print(' Failed to load available codes: $e');
    }
  }

  Future<Map<String, dynamic>?> purchaseItem(int itemId) async {
    _isPurchasing = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.purchaseItem(itemId);
      final purchase = Purchase.fromJson(response.data['data']);
      _purchases.insert(0, purchase);
      _isPurchasing = false;
      notifyListeners();
      return {
        'success': true,
        'message': response.data['message'] ?? 'Purchase successful!',
        'purchase': purchase,
      };
    } catch (e) {
      String msg = 'Purchase failed';
      if (e is DioException && e.response?.data is Map) {
        msg = (e.response!.data as Map)['message']?.toString() ?? msg;
      } else if (e is Exception) {
        final errStr = e.toString();
        final msgMatch = RegExp(r"message["":\s]+([^""']+)").firstMatch(errStr);
        if (msgMatch != null) {
          msg = msgMatch.group(1)?.trim() ?? msg;
        }
      }
      _errorMessage = msg;
      _isPurchasing = false;
      notifyListeners();
      return {'success': false, 'message': msg};
    }
  }
}
