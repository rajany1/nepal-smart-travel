import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/ad_campaign.dart';

class AdProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<AdCampaignModel> _ads = [];
  List<AdCampaignModel> _placeAds = [];
  List<AdCampaignModel> _reportAds = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<AdCampaignModel> get ads => _ads;
  List<AdCampaignModel> get placeAds => _placeAds;
  List<AdCampaignModel> get reportAds => _reportAds;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchActiveAds({
    String? adContext,
    String? district,
    String? category,
    int? limit,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await _api.getActiveAds(
        adContext: adContext,
        district: district,
        category: category,
        limit: limit,
      );
      final data = (res.data['data'] as List<dynamic>?) ?? [];
      _ads = data.map((e) => AdCampaignModel.fromJson(e as Map<String, dynamic>)).toList();

      // All ad types may appear in any feed slot
      _placeAds = List.of(_ads);
      _reportAds = List.of(_ads);

      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> trackImpression(AdCampaignModel ad) {
    return _api.trackAdImpression(ad.id).then((_) {}).catchError((_) {});
  }

  Future<void> trackClick(AdCampaignModel ad) {
    return _api.trackAdClick(ad.id).then((_) {}).catchError((_) {});
  }

  void clear() {
    _ads = [];
    _placeAds = [];
    _reportAds = [];
    _errorMessage = null;
    notifyListeners();
  }
}
