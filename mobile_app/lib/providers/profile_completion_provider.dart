import 'package:flutter/material.dart';
import '../core/models/user.dart';
import '../core/api/api_client.dart';

class ProfileCompletionProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;

  bool _isLoading = false;
  String? _errorMessage;
  bool _profileCompleted = false;
  List<String> _missingFields = [];

  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get profileCompleted => _profileCompleted;
  List<String> get missingFields => _missingFields;
  bool get hasMissingFields => _missingFields.isNotEmpty;

  /// Check current profile completion status
  Future<void> checkStatus() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.checkProfileStatus();
      final data = response.data as Map<String, dynamic>;

      _profileCompleted = data['profile_completed'] ?? false;
      _missingFields = List<String>.from(data['missing_fields'] ?? []);
    } catch (e) {
      print('❌ Error checking profile status: $e');
      _errorMessage = 'Failed to check profile status';
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Complete the user profile
  Future<bool> completeProfile({
    required String bio,
    String? avatar,
    String? phone,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // ✅ Validate bio length
      if (bio.trim().isEmpty) {
        _errorMessage = 'Bio cannot be empty';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      if (bio.trim().length < 10) {
        _errorMessage = 'Bio must be at least 10 characters';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      // Call API to complete profile
      final response = await _api.completeProfile(
        bio: bio.trim(),
        avatar: avatar,
        phone: phone,
      );

      if (response.statusCode == 200) {
        final data = response.data as Map<String, dynamic>;
        _profileCompleted = data['data']?['profile_completed'] ?? true;
        _missingFields = [];
        _errorMessage = null;
        print('✅ Profile completed successfully');
        return true;
      }

      _errorMessage = 'Failed to complete profile';
      return false;
    } catch (e) {
      print('❌ Error completing profile: $e');
      if (e.toString().contains('DioException')) {
        _errorMessage = 'Network error. Please check your connection.';
      } else {
        _errorMessage = 'Failed to complete profile';
      }
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Update profile completion state from user data
  void updateFromUser(UserModel user) {
    _profileCompleted = user.profileCompleted;

    // ✅ Track missing fields for UI guidance
    _missingFields = [];
    if (user.bio == null || user.bio!.isEmpty) {
      _missingFields.add('bio');
    }
    if (user.phone == null || user.phone!.isEmpty) {
      _missingFields.add('phone');
    }

    notifyListeners();
  }

  /// Reset provider state
  void reset() {
    _isLoading = false;
    _errorMessage = null;
    _profileCompleted = false;
    _missingFields = [];
    notifyListeners();
  }
}
