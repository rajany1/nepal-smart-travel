import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import '../core/api/api_client.dart';
import '../core/models/ad_campaign.dart';

enum AdFeed { place, report }

class AdProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<AdCampaignModel> _placeAds = [];
  List<AdCampaignModel> _reportAds = [];
  bool _isLoading = false;
  String? _errorMessage;

  List<AdCampaignModel> get placeAds => _placeAds;
  List<AdCampaignModel> get reportAds => _reportAds;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchActiveAds({
    AdFeed feed = AdFeed.place,
    String? adContext,
    String? district,
    String? category,
    int? limit,
    bool persistent = false,
  }) async {
    _isLoading = true;
    notifyListeners();

    try {
      final res = await _api.getActiveAds(
        adContext: adContext,
        district: district,
        category: category,
        limit: limit,
        persistent: persistent,
      );
      final data = (res.data['data'] as List<dynamic>?) ?? [];
      final ads = data.map((e) => AdCampaignModel.fromJson(e as Map<String, dynamic>)).toList();

      if (feed == AdFeed.report) {
        _reportAds = ads;
      } else {
        _placeAds = ads;
      }

      _errorMessage = null;
    } catch (e) {
      _errorMessage = e.toString();
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<AdCampaignModel?> fetchReportAd(dynamic reportId) async {
    try {
      final res = await _api.getReportAd(reportId);
      final data = res.data['data'];
      if (data == null) return null;
      return AdCampaignModel.fromJson(data as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  void clear() {
    _placeAds = [];
    _reportAds = [];
    _errorMessage = null;
    notifyListeners();
  }
}
