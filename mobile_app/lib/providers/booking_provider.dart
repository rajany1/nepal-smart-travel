import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/models/booking_model.dart';

class BookingProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  List<BookingModel> _bookings = [];
  bool _isLoading = false;
  String? _error;
  String _statusFilter = '';

  List<BookingModel> get bookings => _bookings;
  bool get isLoading => _isLoading;
  String? get error => _error;
  String get statusFilter => _statusFilter;

  List<BookingModel> get filteredBookings {
    if (_statusFilter.isEmpty) return _bookings;
    return _bookings.where((b) => b.status == _statusFilter).toList();
  }

  int get countAll => _bookings.length;
  int get countPending => _bookings.where((b) => b.isPending).length;
  int get countConfirmed => _bookings.where((b) => b.isConfirmed).length;
  int get countCompleted => _bookings.where((b) => b.isCompleted).length;
  int get countCancelled => _bookings.where((b) => b.isCancelled).length;

  void setStatusFilter(String filter) {
    _statusFilter = filter;
    notifyListeners();
  }

  Future<void> loadMyBookings() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final res = await _api.getMyBookings();
      final data = res.data['data'];
      final List<dynamic> rawList;
      if (data is List) {
        rawList = data;
      } else if (data is Map && data['data'] is List) {
        rawList = data['data'];
      } else {
        rawList = [];
      }
      _bookings = rawList
          .map((j) => BookingModel.fromJson(j as Map<String, dynamic>))
          .toList();
    } catch (e) {
      _error = e.toString();
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> cancelBooking(int bookingId) async {
    try {
      await _api.cancelBooking(bookingId);
      await loadMyBookings();
      return true;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> removeCoupon(int bookingId) async {
    try {
      await _api.removeBookingCoupon(bookingId);
      await loadMyBookings();
      return true;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }
}
