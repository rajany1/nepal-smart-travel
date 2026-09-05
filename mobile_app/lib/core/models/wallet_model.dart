class WalletData {
  final double balance;
  final double totalEarned;
  final double totalWithdrawn;
  final String formattedBalance;

  WalletData({
    required this.balance,
    required this.totalEarned,
    required this.totalWithdrawn,
    required this.formattedBalance,
  });

  factory WalletData.fromJson(Map<String, dynamic> json) {
    return WalletData(
      balance: _parseDouble(json['balance']),
      totalEarned: _parseDouble(json['total_earned']),
      totalWithdrawn: _parseDouble(json['total_withdrawn']),
      formattedBalance: json['formatted_balance'] ?? '0.00',
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class TodayEarnings {
  final int impressions;
  final int clicks;
  final double earned;
  final double dailyCap;

  TodayEarnings({
    required this.impressions,
    required this.clicks,
    required this.earned,
    required this.dailyCap,
  });

  factory TodayEarnings.fromJson(Map<String, dynamic> json) {
    return TodayEarnings(
      impressions: json['impressions'] ?? 0,
      clicks: json['clicks'] ?? 0,
      earned: _parseDouble(json['earned']),
      dailyCap: _parseDouble(json['daily_cap']) > 0 ? _parseDouble(json['daily_cap']) : 500,
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class WithdrawalMethod {
  final double minWithdrawal;
  final String label;

  WithdrawalMethod({
    required this.minWithdrawal,
    required this.label,
  });

  factory WithdrawalMethod.fromJson(Map<String, dynamic> json) {
    return WithdrawalMethod(
      minWithdrawal: _parseDouble(json['min_withdrawal']),
      label: json['label'] ?? '',
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}

class CoinTransaction {
  final int id;
  final String type;
  final double amount;
  final String? description;
  final String? createdAt;
  final Map<String, dynamic>? adCampaign;
  final Map<String, dynamic>? report;

  CoinTransaction({
    required this.id,
    required this.type,
    required this.amount,
    this.description,
    this.createdAt,
    this.adCampaign,
    this.report,
  });

  factory CoinTransaction.fromJson(Map<String, dynamic> json) {
    return CoinTransaction(
      id: json['id'] ?? 0,
      type: json['type'] ?? '',
      amount: _parseDouble(json['amount']),
      description: json['description'],
      createdAt: json['created_at'],
      adCampaign: json['ad_campaign'],
      report: json['report'],
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  String get typeLabel {
    switch (type) {
      case 'impression_earning':
        return 'Ad View Earning';
      case 'click_earning':
        return 'Ad Click Earning';
      case 'withdrawal':
        return 'Withdrawal';
      case 'admin_adjustment':
        return 'Admin Adjustment';
      default:
        return type;
    }
  }

  bool get isEarning => type == 'impression_earning' || type == 'click_earning';
}

class WithdrawalRequest {
  final int id;
  final double amount;
  final String method;
  final String status;
  final String? createdAt;

  WithdrawalRequest({
    required this.id,
    required this.amount,
    required this.method,
    required this.status,
    this.createdAt,
  });

  factory WithdrawalRequest.fromJson(Map<String, dynamic> json) {
    return WithdrawalRequest(
      id: json['id'] ?? 0,
      amount: _parseDouble(json['amount']),
      method: json['method'] ?? '',
      status: json['status'] ?? 'pending',
      createdAt: json['created_at'],
    );
  }

  static double _parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }
}
