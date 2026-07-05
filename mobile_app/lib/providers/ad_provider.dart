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

  Future<void> fetchActiveAds() async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await _api.getActiveAds();
      final data = (res.data['data'] as List<dynamic>?) ?? [];
      _ads = data.map((e) => AdCampaignModel.fromJson(e as Map<String, dynamic>)).toList();

      // Separate by ad_type
      _placeAds = _ads.where((a) => a.adType == 'promoted_place' || a.adType == 'banner').toList();
      _reportAds = _ads.where((a) => a.adType == 'sponsored_card' || a.adType == 'banner').toList();

      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
    }

    _isLoading = false;
    notifyListeners();
  }

  void clear() {
    _ads = [];
    _placeAds = [];
    _reportAds = [];
    _errorMessage = null;
    notifyListeners();
  }
}
