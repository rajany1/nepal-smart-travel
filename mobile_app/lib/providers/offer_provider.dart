import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/offer_model.dart';

class OfferProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<OfferModel> _offers = [];
  List<OfferRedemptionModel> _myRedemptions = [];
  bool _isLoading = false;
  bool _isLoadingMine = false;
  String? _errorMessage;
  String? _claimingOfferId;

  List<OfferModel> get offers => _offers;
  List<OfferRedemptionModel> get myRedemptions => _myRedemptions;
  bool get isLoading => _isLoading;
  bool get isLoadingMine => _isLoadingMine;
  String? get errorMessage => _errorMessage;
  String? get claimingOfferId => _claimingOfferId;

  Future<void> fetchOffers() async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await _api.getOffers();
      final data = (res.data['offers'] as List<dynamic>?) ?? [];
      _offers = data
          .map((e) => OfferModel.fromJson(e as Map<String, dynamic>))
          .where((o) => !o.isExpired)
          .toList();
      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> fetchMyRedemptions() async {
    _isLoadingMine = true;
    notifyListeners();

    try {
      final res = await _api.getMyOffers();
      final data = (res.data['redemptions'] as List<dynamic>?) ?? [];
      _myRedemptions = data
          .map((e) => OfferRedemptionModel.fromJson(e as Map<String, dynamic>))
          .toList();
      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
    }

    _isLoadingMine = false;
    notifyListeners();
  }

  /// Returns the redemption on success, throws ApiException-like message on failure.
  Future<OfferRedemptionModel> claimOffer(int offerId) async {
    _claimingOfferId = '$offerId';
    notifyListeners();

    try {
      final res = await _api.claimOffer(offerId);
      final redemption = OfferRedemptionModel.fromJson(
        (res.data['redemption'] as Map<String, dynamic>?) ?? {},
      );
      _myRedemptions.insert(0, redemption);
      _claimingOfferId = null;
      notifyListeners();
      return redemption;
    } catch (e) {
      _claimingOfferId = null;
      notifyListeners();
      rethrow;
    }
  }

  void clear() {
    _offers = [];
    _myRedemptions = [];
    _errorMessage = null;
    notifyListeners();
  }
}
