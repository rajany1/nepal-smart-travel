import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'dart:convert';
import '../models/user.dart';

/// Centralized session management service
/// Handles user session persistence, token management, and user preferences
class SessionManager {
  static SessionManager? _instance;
  late final FlutterSecureStorage _storage;

  /// Called whenever clearSession() is triggered (e.g., from auth interceptor).
  /// AuthProvider registers here to reset in-memory state on forced logout.
  void Function()? onSessionCleared;

  // Storage keys
  static const String _tokenKey = 'access_token';
  static const String _refreshTokenKey = 'refresh_token';
  static const String _userDataKey = 'user_data';
  static const String _deviceIdKey = 'device_id';
  static const String _sessionExpiryKey = 'session_expiry';
  static const String _userPreferencesKey = 'user_preferences';
  static const String _lastLoginKey = 'last_login';

  // In-memory cache
  UserModel? _cachedUser;
  DateTime? _sessionExpiry;
  Map<String, dynamic>? _userPreferences;

  SessionManager._() {
    _storage = const FlutterSecureStorage();
  }

  static SessionManager get instance {
    _instance ??= SessionManager._();
    return _instance!;
  }

  // ============ Token Management ============

  /// Store access token
  Future<void> setAccessToken(String token, {int? expiresInSeconds}) async {
    await _storage.write(key: _tokenKey, value: token);
    
    // Calculate session expiry if expiration time is provided
    if (expiresInSeconds != null) {
      final expiry = DateTime.now().add(Duration(seconds: expiresInSeconds));
      _sessionExpiry = expiry;
      await _storage.write(
        key: _sessionExpiryKey,
        value: expiry.toIso8601String(),
      );
    }
  }

  /// Get access token
  Future<String?> getAccessToken() async {
    final token = await _storage.read(key: _tokenKey);
    
    // Check if token has expired
    if (token != null && await _isSessionExpired()) {
      await clearSession();
      return null;
    }
    
    return token;
  }

  /// Store refresh token
  Future<void> setRefreshToken(String token) async {
    await _storage.write(key: _refreshTokenKey, value: token);
  }

  /// Get refresh token
  Future<String?> getRefreshToken() async {
    return await _storage.read(key: _refreshTokenKey);
  }

  // ============ User Data Management ============

  /// Store user data persistently and cache it
  Future<void> setUser(UserModel user) async {
    _cachedUser = user;
    final userData = user.toJson();
    final encoded = jsonEncode(userData);
    await _storage.write(key: _userDataKey, value: encoded);
  }

  /// Get cached user data (in-memory first, then disk)
  Future<UserModel?> getUser() async {
    if (_cachedUser != null) {
      return _cachedUser;
    }

    try {
      final encoded = await _storage.read(key: _userDataKey);
      if (encoded != null) {
        final decoded = jsonDecode(encoded) as Map<String, dynamic>;
        _cachedUser = UserModel.fromJson(decoded);
        return _cachedUser;
      }
    } catch (e) {
      print('❌ Error retrieving cached user: $e');
    }

    return null;
  }

  /// Update specific user field without losing other data
  Future<void> updateUserField(String field, dynamic value) async {
    if (_cachedUser != null) {
      final userData = _cachedUser!.toJson();
      userData[field] = value;
      _cachedUser = UserModel.fromJson(userData);
      
      final encoded = jsonEncode(userData);
      await _storage.write(key: _userDataKey, value: encoded);
    }
  }

  /// Clear user data from cache and storage
  Future<void> clearUser() async {
    _cachedUser = null;
    await _storage.delete(key: _userDataKey);
  }

  // ============ Session Management ============

  /// Check if session is active
  Future<bool> isSessionActive() async {
    final token = await _storage.read(key: _tokenKey);
    if (token == null) return false;

    // Check if session has expired
    if (await _isSessionExpired()) {
      return false;
    }

    return true;
  }

  /// Check if session has expired
  Future<bool> _isSessionExpired() async {
    try {
      final expiryStr = await _storage.read(key: _sessionExpiryKey);
      if (expiryStr != null) {
        final expiry = DateTime.parse(expiryStr);
        return DateTime.now().isAfter(expiry);
      }
    } catch (e) {
      print('❌ Error checking session expiry: $e');
    }
    return false;
  }

  /// Get session expiry time
  Future<DateTime?> getSessionExpiry() async {
    try {
      final expiryStr = await _storage.read(key: _sessionExpiryKey);
      if (expiryStr != null) {
        return DateTime.parse(expiryStr);
      }
    } catch (e) {
      print('❌ Error getting session expiry: $e');
    }
    return null;
  }

  /// Get time remaining in session
  Future<Duration?> getSessionTimeRemaining() async {
    final expiry = await getSessionExpiry();
    if (expiry != null) {
      final remaining = expiry.difference(DateTime.now());
      if (remaining.isNegative) return Duration.zero;
      return remaining;
    }
    return null;
  }

  // ============ User Preferences ============

  /// Store user preferences (theme, language, etc.)
  Future<void> setUserPreferences(Map<String, dynamic> preferences) async {
    _userPreferences = preferences;
    final encoded = jsonEncode(preferences);
    await _storage.write(key: _userPreferencesKey, value: encoded);
  }

  /// Get user preferences
  Future<Map<String, dynamic>> getUserPreferences() async {
    if (_userPreferences != null) {
      return _userPreferences!;
    }

    try {
      final encoded = await _storage.read(key: _userPreferencesKey);
      if (encoded != null) {
        _userPreferences = jsonDecode(encoded) as Map<String, dynamic>;
        return _userPreferences!;
      }
    } catch (e) {
      print('❌ Error retrieving preferences: $e');
    }

    return {};
  }

  /// Update single preference
  Future<void> setPreference(String key, dynamic value) async {
    final prefs = await getUserPreferences();
    prefs[key] = value;
    await setUserPreferences(prefs);
  }

  /// Get single preference
  Future<dynamic> getPreference(String key, {dynamic defaultValue}) async {
    final prefs = await getUserPreferences();
    return prefs[key] ?? defaultValue;
  }

  // ============ Device & Tracking ============

  /// Set device ID for tracking
  Future<void> setDeviceId(String deviceId) async {
    await _storage.write(key: _deviceIdKey, value: deviceId);
  }

  /// Get device ID
  Future<String?> getDeviceId() async {
    return await _storage.read(key: _deviceIdKey);
  }

  /// Track last login time
  Future<void> setLastLogin() async {
    await _storage.write(
      key: _lastLoginKey,
      value: DateTime.now().toIso8601String(),
    );
  }

  /// Get last login time
  Future<DateTime?> getLastLogin() async {
    try {
      final lastLoginStr = await _storage.read(key: _lastLoginKey);
      if (lastLoginStr != null) {
        return DateTime.parse(lastLoginStr);
      }
    } catch (e) {
      print('❌ Error getting last login: $e');
    }
    return null;
  }

  // ============ Session Cleanup ============

  /// Clear entire session (logout)
  Future<void> clearSession() async {
    _cachedUser = null;
    _sessionExpiry = null;
    
    await Future.wait([
      _storage.delete(key: _tokenKey),
      _storage.delete(key: _refreshTokenKey),
      _storage.delete(key: _userDataKey),
      _storage.delete(key: _sessionExpiryKey),
      // Keep preferences and device ID for next login
    ]);

    onSessionCleared?.call();
  }

  /// Restore session from storage (called on app startup)
  Future<bool> restoreSession() async {
    try {
      final token = await getAccessToken();
      if (token != null) {
        final user = await getUser();
        if (user != null) {
          return true;
        }
      }
    } catch (e) {
      print('❌ Error restoring session: $e');
    }
    return false;
  }

  /// Get session summary for debugging
  Future<Map<String, dynamic>> getSessionSummary() async {
    return {
      'isSessionActive': await isSessionActive(),
      'hasToken': (await getAccessToken()) != null,
      'hasRefreshToken': (await getRefreshToken()) != null,
      'user': await getUser(),
      'sessionExpiry': await getSessionExpiry(),
      'timeRemaining': await getSessionTimeRemaining(),
      'lastLogin': await getLastLogin(),
      'deviceId': await getDeviceId(),
    };
  }
}
