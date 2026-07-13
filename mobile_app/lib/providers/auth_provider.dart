import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:google_sign_in/google_sign_in.dart';
import '../config/constants/app_constants.dart';
import '../core/models/user.dart';
import '../core/api/api_client.dart';
import '../core/services/session_manager.dart';

class AuthProvider extends ChangeNotifier {
  final ApiClient _api = ApiClient.instance;
  final SessionManager _session = SessionManager.instance;

  UserModel? _user;
  bool _isLoading = false;
  bool _isAuthenticated = false;
  String? _errorMessage;
  bool _isInitialized = false;
  
  // Additional auth state tracking
  bool _isEmailVerified = false;
  bool _requiresProfileCompletion = false;
  DateTime? _lastProfileRefresh;

  UserModel? get user => _user;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _isAuthenticated;
  bool get isInitialized => _isInitialized;
  String? get errorMessage => _errorMessage;
  bool get isEmailVerified => _isEmailVerified;
  bool get requiresProfileCompletion => _requiresProfileCompletion;
  
  // ✅ Profile completion check
  bool get isProfileCompletionRequired => _isAuthenticated && _user != null && !_user!.profileCompleted;
  
  // ✅ User display name getter
  String get userDisplayName => _user?.name ?? 'User';
  
  // ✅ User level info getter
  String get userLevelName => _user?.levelName ?? 'Explorer';

  bool canUseFeature(String featureName) {
    return _user?.permissions.contains(featureName) ?? false;
  }

  /// Initialize auth on app startup - restore session from storage
  Future<void> initializeAuth() async {
    if (_isInitialized) return;

    _session.onSessionCleared = _handleForceLogout;
    
    _isLoading = true;
    _isInitialized = false;
    notifyListeners();

    try {
      // Try to restore session from persistent storage
      final sessionRestored = await _session.restoreSession();
      
      if (sessionRestored) {
        final user = await _session.getUser();
        if (user != null) {
          _user = user;
          _isAuthenticated = true;
          _isEmailVerified = user.status == 'active';
          _requiresProfileCompletion = !user.profileCompleted;
          _errorMessage = null;
        }
      } else {
        _user = null;
        _isAuthenticated = false;
      }
    } catch (e) {
      print('❌ Auth initialization failed: $e');
      _user = null;
      _isAuthenticated = false;
      _errorMessage = 'Failed to restore session';
    }

    _isLoading = false;
    _isInitialized = true;
    notifyListeners();
  }

  /// Check auth status and refresh user data from server
  Future<void> checkAuthStatus() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final token = await _session.getAccessToken();

      if (token == null) {
        _user = null;
        _isAuthenticated = false;
        _isLoading = false;
        notifyListeners();
        return;
      }

      final response = await _api.getProfile();
      // ✅ /users/me returns data at ROOT level, not nested in 'data'
      _user = UserModel.fromJson(response.data);
      _isAuthenticated = true;
      _isEmailVerified = _user!.status == 'active';
      _requiresProfileCompletion = !_user!.profileCompleted;
      _lastProfileRefresh = DateTime.now();
      
      // Persist user data
      await _session.setUser(_user!);
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        await _session.clearSession();
        _user = null;
        _isAuthenticated = false;
      } else {
        print('⚠️ Transient error in auth check (keeping session): $e');
      }
    } catch (e) {
      print('⚠️ Transient error in auth check (keeping session): $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  Future<bool> _handleAuthSuccess(AuthResponse authResponse, {bool fetchProfile = true}) async {
    if (!authResponse.success || authResponse.accessToken == null) {
      _errorMessage = authResponse.getErrorMessage();
      return false;
    }

    try {
      // ✅ Set token with expiration in session
      await _session.setAccessToken(
        authResponse.accessToken!,
        expiresInSeconds: authResponse.expiresIn,
      );

      if (authResponse.refreshToken != null) {
        await _session.setRefreshToken(authResponse.refreshToken!);
      }

      // Track login time
      await _session.setLastLogin();

      // ✅ Always set authenticated if we have a valid token
      _isAuthenticated = true;
      
      // ✅ Parse user data from auth response if available
      if (authResponse.userData != null) {
        try {
          _user = UserModel.fromJson(authResponse.userData!);
          _isEmailVerified = _user!.status == 'active';
          _requiresProfileCompletion = !_user!.profileCompleted;
          
          // Persist user data
          await _session.setUser(_user!);
        } catch (e) {
          print('❌ Error parsing user data from auth response: $e');
          // Don't reset auth state - token is already valid
          // User data will be fetched in background
        }
      }

      // ✅ Fetch profile in background (non-blocking)
      if (fetchProfile && _user != null) {
        _fetchProfileAsync();
      }

      _errorMessage = null;
      return true;
    } catch (e) {
      print('❌ Error handling auth success: $e');
      _errorMessage = _parseError(e);
      return false;
    }
  }

  /// Fetch profile asynchronously without blocking auth flow
  void _fetchProfileAsync() async {
    try {
      final profileResponse = await _api.getProfile();
      // /users/me returns data at root level
      _user = UserModel.fromJson(profileResponse.data);
      _isEmailVerified = _user!.status == 'active';
      _requiresProfileCompletion = !_user!.profileCompleted;
      _lastProfileRefresh = DateTime.now();
      
      // Update persisted user data
      await _session.setUser(_user!);
      notifyListeners();
    } catch (e) {
      print('⚠️ Profile fetch failed (non-blocking): $e');
      // Don't set error - user is already authenticated
    }
  }

  Future<bool> login(String email, String password, {bool rememberMe = false}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.login(email: email, password: password);
      final authResponse = AuthResponse.fromJson(response.data);

      // ✅ Handle auth success (profile fetch is non-blocking)
      final success = await _handleAuthSuccess(authResponse, fetchProfile: true);

      // Store login preference if rememberMe is enabled
      if (success && rememberMe) {
        await _session.setPreference('rememberEmail', email);
      }

      // ✅ Ensure user data is available after login
      // If userData wasn't in the auth response, fetch profile synchronously
      if (success && _user == null && _isAuthenticated) {
        try {
          final profileResponse = await _api.getProfile();
          _user = UserModel.fromJson(profileResponse.data);
          _isEmailVerified = _user!.status == 'active';
          _requiresProfileCompletion = !_user!.profileCompleted;
          await _session.setUser(_user!);
        } catch (e) {
          print('⚠️ Post-login profile fetch failed: $e');
          // User is still authenticated with valid token
        }
      }

      _isLoading = false;
      notifyListeners();
      return success;
    } catch (e) {
      _errorMessage = _parseError(e);
      print('❌ Login error: $e');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> loginWithGoogle() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final googleSignIn = GoogleSignIn.instance;
      await googleSignIn.initialize(
        serverClientId: AppConstants.googleServerClientId,
      );

      final account = await googleSignIn.authenticate();
      if (account == null) {
        _errorMessage = 'Google sign-in was cancelled';
        _isLoading = false;
        notifyListeners();
        return false;
      }
      final authentication = account.authentication;
      final idToken = authentication.idToken;

      if (idToken == null) {
        _errorMessage = 'Failed to get Google authentication token';
        _isLoading = false;
        notifyListeners();
        return false;
      }

      final response = await _api.socialLogin(idToken: idToken);
      final authResponse = AuthResponse.fromJson(response.data);

      final success = await _handleAuthSuccess(authResponse, fetchProfile: true);

      if (success && _user == null && _isAuthenticated) {
        try {
          final profileResponse = await _api.getProfile();
          _user = UserModel.fromJson(profileResponse.data);
          _isEmailVerified = _user!.status == 'active';
          _requiresProfileCompletion = !_user!.profileCompleted;
          await _session.setUser(_user!);
        } catch (e) {
          print('⚠️ Post-Google-login profile fetch failed: $e');
        }
      }

      _isLoading = false;
      notifyListeners();
      return success;
    } catch (e) {
      _errorMessage = _parseError(e);
      print('❌ Google login error: $e');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.register(
        name: name,
        email: email,
        phone: phone,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );

      final authResponse = AuthResponse.fromJson(response.data);

      // ✅ Don't block on profile fetch - register success is immediate
      final success = await _handleAuthSuccess(authResponse, fetchProfile: true);

      _isLoading = false;
      notifyListeners();
      return success;
    } catch (e) {
      _errorMessage = _parseError(e);
      print('❌ Registration error: $e');
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    _session.onSessionCleared = null;

    try {
      // Call logout API endpoint
      await _api.logout();
    } catch (e) {
      print('⚠️ Logout API call failed: $e');
      // Continue with local logout anyway
    }
    
    // Clear session
    await _session.clearSession();
    
    // Reset all state
    _user = null;
    _isAuthenticated = false;
    _isEmailVerified = false;
    _requiresProfileCompletion = false;
    _errorMessage = null;
    _lastProfileRefresh = null;
    
    notifyListeners();
  }

  void _handleForceLogout() {
    _user = null;
    _isAuthenticated = false;
    _isEmailVerified = false;
    _requiresProfileCompletion = false;
    _errorMessage = null;
    _lastProfileRefresh = null;
    notifyListeners();
  }

  Future<void> updateProfile(Map<String, dynamic> data) async {
    _isLoading = true;
    notifyListeners();

    try {
      final response = await _api.updateProfile(data);
      // ✅ Handle multiple response structures:
      // - AuthController@update returns: { success, message, user: {...} }
      // - ProfileController@update returns: { success, message, data: {...} }
      Map<String, dynamic> userData;
      if (response.data['data'] is Map) {
        // ProfileController format
        userData = Map<String, dynamic>.from(response.data['data']);
      } else if (response.data['user'] is Map) {
        // AuthController format
        userData = Map<String, dynamic>.from(response.data['user']);
      } else {
        // Fallback: use the whole response
        userData = Map<String, dynamic>.from(response.data);
      }
      
      _user = UserModel.fromJson(userData);
      _requiresProfileCompletion = !_user!.profileCompleted;
      _errorMessage = null;
      
      // Update persisted user data
      await _session.setUser(_user!);
      notifyListeners(); // Notify immediately after update
    } catch (e) {
      _errorMessage = _parseError(e);
      print('❌ Profile update error: $e');
    }

    _isLoading = false;
    notifyListeners();
  }

  /// Refresh user profile from server
  Future<void> refreshProfile() async {
    try {
      final response = await _api.getProfile();
      _user = UserModel.fromJson(response.data);
      _isEmailVerified = _user!.status == 'active';
      _requiresProfileCompletion = !_user!.profileCompleted;
      _lastProfileRefresh = DateTime.now();
      
      // Update persisted user data
      await _session.setUser(_user!);
      notifyListeners();
    } catch (e) {
      print('❌ Profile refresh failed: $e');
      _errorMessage = _parseError(e);
      notifyListeners();
    }
  }

  Future<bool> verifyEmail(String otp) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.verifyEmail(otp);
      if (response.data['success'] ?? false) {
        // Update email verification status
        if (_user != null) {
          _isEmailVerified = true;
          await _session.updateUserField('status', 'active');
          await _session.updateUserField('email_verified_at', DateTime.now().toIso8601String());
        }
        _isLoading = false;
        notifyListeners();
        return true;
      }
      _errorMessage = 'Invalid OTP';
    } catch (e) {
      _errorMessage = _parseError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<bool> resendVerificationEmail(String email) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.resendVerificationEmail(email);
      if (response.data['success'] ?? false) {
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _errorMessage = _parseError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  Future<String?> sendPasswordReset(String email) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.sendPasswordReset(email);
      if (response.data['success'] ?? false) {
        _isLoading = false;
        notifyListeners();
        return response.data['reset_token'] as String?;
      }
      _errorMessage = 'Failed to send reset link';
    } catch (e) {
      _errorMessage = _parseError(e);
    }

    _isLoading = false;
    notifyListeners();
    return null;
  }

  Future<bool> resetPassword(String email, String token, String newPassword) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final response = await _api.resetPassword(email, token, newPassword);
      if (response.data['success'] ?? false) {
        _isLoading = false;
        notifyListeners();
        return true;
      }
    } catch (e) {
      _errorMessage = _parseError(e);
    }

    _isLoading = false;
    notifyListeners();
    return false;
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  /// Update the local user object without making an API call.
  /// Used after profile completion to immediately mark profile as completed
  /// so it doesn't redirect back on next app open.
  void updateLocalUser(UserModel updatedUser) {
    _user = updatedUser;
    _requiresProfileCompletion = !updatedUser.profileCompleted;
    _session.setUser(updatedUser);
    notifyListeners();
  }

  String _parseError(dynamic error) {
    print('🔍 Parsing error: $error');

    if (error is DioException) {
      final statusCode = error.response?.statusCode;
      final responseData = error.response?.data;

      if (responseData is Map<String, dynamic> && responseData['message'] != null) {
        final serverMsg = responseData['message'] as String;
        final reason = responseData['reason'] as String?;
        if (statusCode == 403) {
          if (reason == 'banned') {
            return '🚫 Account Banned\n\n$serverMsg';
          }
          if (reason == 'suspended') {
            return '⏸️ Account Suspended\n\n$serverMsg';
          }
          return serverMsg;
        }
        if (statusCode == 422) return serverMsg;
        if (statusCode == 401) return 'Invalid email or password.';
        return serverMsg;
      }

      switch (statusCode) {
        case 401:
          return 'Invalid email or password.';
        case 403:
          return 'Access denied. Please contact support.';
        case 404:
          return 'User not found.';
        case 409:
          return 'Email already registered.';
        case 422:
          return 'Validation failed. Please check your input.';
        case 429:
          return 'Too many attempts. Please try again later.';
        case 500:
          return 'Server error. Please try again later.';
      }

      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.receiveTimeout) {
        return 'Connection timeout. Please try again.';
      }
      if (error.type == DioExceptionType.connectionError) {
        return 'Unable to connect to server. Check your internet connection.';
      }
    }

    final errStr = error.toString().toLowerCase();
    if (errStr.contains('socket') || errStr.contains('connection refused') ||
        errStr.contains('failed host lookup')) {
      return 'Unable to connect. Check your internet connection.';
    }
    if (errStr.contains('timeout')) {
      return 'Connection timeout. Please try again.';
    }
    return 'An unexpected error occurred. Please try again.';
  }
}
