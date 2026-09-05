import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/wallet_model.dart';

class WalletProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  WalletData? _wallet;
  TodayEarnings? _todayEarnings;
  Map<String, WithdrawalMethod> _withdrawalMethods = {};
  List<CoinTransaction> _transactions = [];
  List<WithdrawalRequest> _recentWithdrawals = [];
  bool _isLoading = false;
  bool _isLoadingTransactions = false;
  String? _error;
  bool _hasMore = true;

  WalletData? get wallet => _wallet;
  TodayEarnings? get todayEarnings => _todayEarnings;
  Map<String, WithdrawalMethod> get withdrawalMethods => _withdrawalMethods;
  List<CoinTransaction> get transactions => _transactions;
  List<WithdrawalRequest> get recentWithdrawals => _recentWithdrawals;
  bool get isLoading => _isLoading;
  bool get isLoadingTransactions => _isLoadingTransactions;
  String? get error => _error;
  bool get hasMore => _hasMore;

  Future<void> loadWallet() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.getWallet();
      final data = response.data;

      _wallet = WalletData.fromJson(data['wallet'] ?? {});
      _todayEarnings = TodayEarnings.fromJson(data['today_earnings'] ?? {});

      final methods = data['withdrawal_methods'] ?? {};
      _withdrawalMethods = {};
      methods.forEach((key, value) {
        _withdrawalMethods[key] = WithdrawalMethod.fromJson(value);
      });

      _recentWithdrawals = (data['recent_withdrawals'] as List? ?? [])
          .map((w) => WithdrawalRequest.fromJson(w))
          .toList();
    } catch (e) {
      _error = 'Failed to load wallet: $e';
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<void> loadTransactions({bool refresh = false}) async {
    if (refresh) {
      _transactions = [];
      _hasMore = true;
    }

    if (!_hasMore) return;

    _isLoadingTransactions = true;
    notifyListeners();

    try {
      final response = await _api.getWalletTransactions(
        limit: 20,
        offset: _transactions.length,
      );

      final data = response.data['data'] as List? ?? [];
      final newTransactions = data.map((t) => CoinTransaction.fromJson(t)).toList();

      if (newTransactions.length < 20) {
        _hasMore = false;
      }

      _transactions = [..._transactions, ...newTransactions];
    } catch (e) {
      _error = 'Failed to load transactions: $e';
    }

    _isLoadingTransactions = false;
    notifyListeners();
  }

  Future<Map<String, dynamic>> requestWithdrawal({
    required double amount,
    required String method,
    required Map<String, dynamic> accountDetails,
  }) async {
    try {
      final response = await _api.requestWithdrawal(
        amount: amount,
        method: method,
        accountDetails: accountDetails,
      );

      final result = response.data;

      // Refresh wallet balance
      await loadWallet();

      return {'success': true, 'message': result['message'] ?? 'Withdrawal submitted'};
    } catch (e) {
      String message = 'Withdrawal failed';
      if (e is Exception) {
        message = e.toString().replaceAll('Exception: ', '');
      }
      return {'success': false, 'error': message};
    }
  }

  Future<Map<String, dynamic>> cancelWithdrawal(int id) async {
    try {
      final response = await _api.cancelWithdrawal(id);

      // Refresh wallet
      await loadWallet();

      return {'success': true, 'message': 'Withdrawal cancelled'};
    } catch (e) {
      return {'success': false, 'error': 'Failed to cancel withdrawal'};
    }
  }
}
