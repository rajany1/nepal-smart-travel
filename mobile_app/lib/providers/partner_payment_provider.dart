import 'package:flutter/material.dart';
import '../core/api/api_client.dart';

class PartnerPaymentProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<Map<String, dynamic>> _partners = [];
  List<Map<String, dynamic>> _myPayments = [];
  bool _isLoading = false;
  bool _isLoadingPartners = false;
  String? _error;
  int _currentPage = 1;
  bool _hasMore = true;

  List<Map<String, dynamic>> get partners => _partners;
  List<Map<String, dynamic>> get myPayments => _myPayments;
  bool get isLoading => _isLoading;
  bool get isLoadingPartners => _isLoadingPartners;
  String? get error => _error;
  bool get hasMore => _hasMore;

  Future<void> loadPartners() async {
    _isLoadingPartners = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.getPartnerList();
      if (response.data['success'] == true) {
        _partners = List<Map<String, dynamic>>.from(response.data['data']);
      }
    } catch (e) {
      _error = 'Failed to load partners';
    } finally {
      _isLoadingPartners = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> initiatePayment({
    required int partnerId,
    required double amount,
    required String paymentMethod,
    String? description,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.initiatePartnerPayment(
        partnerId: partnerId,
        amount: amount,
        paymentMethod: paymentMethod,
        description: description,
      );
      if (response.data['success'] == true) {
        _isLoading = false;
        notifyListeners();
        return response.data['data'];
      }
    } catch (e) {
      _error = 'Payment failed. Please try again.';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
    return null;
  }

  Future<void> loadMyPayments({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
      _hasMore = true;
      _myPayments = [];
    }
    if (!_hasMore) return;

    _isLoading = _myPayments.isEmpty;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.getMyPayments(page: _currentPage);
      if (response.data['success'] == true) {
        final data = response.data['data'];
        final items = List<Map<String, dynamic>>.from(data['data'] ?? []);
        if (refresh) {
          _myPayments = items;
        } else {
          _myPayments.addAll(items);
        }
        _currentPage++;
        _hasMore = items.isNotEmpty;
      }
    } catch (e) {
      _error = 'Failed to load payments';
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}
