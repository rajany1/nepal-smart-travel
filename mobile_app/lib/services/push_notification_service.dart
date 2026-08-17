import 'dart:async';
import '../../core/services/localization_service.dart';
import 'package:flutter/material.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:geolocator/geolocator.dart';
import '../core/api/api_client.dart';
import '../core/services/location_service.dart';
import '../features/alerts/alerts_screen.dart';

class PushNotificationService {
  static final PushNotificationService _instance = PushNotificationService._();
  factory PushNotificationService() => _instance;
  PushNotificationService._();

  String? _fcmToken;
  bool _initialized = false;
  GlobalKey<NavigatorState>? _navigatorKey;

  String? get fcmToken => _fcmToken;
  bool get isInitialized => _initialized;

  void setNavigatorKey(GlobalKey<NavigatorState> key) {
    _navigatorKey = key;
  }

  Future<void> initialize() async {
    if (_initialized) return;
    try {
      final messaging = FirebaseMessaging.instance;

      NotificationSettings settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      if (settings.authorizationStatus == AuthorizationStatus.denied) {
        debugPrint('Push notification permission denied.');
        return;
      }

      _fcmToken = await messaging.getToken();
      if (_fcmToken != null) {
        await _registerToken();
      }

      messaging.onTokenRefresh.listen((newToken) {
        _fcmToken = newToken;
        unawaited(_registerToken());
      });

      // Foreground message handler
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        final title = _getLocalizedTitle(message);
        final body = _getLocalizedBody(message);
        debugPrint('Foreground message: $title - $body');
        // TODO: Show local notification (e.g., flutter_local_notifications)
      });

      // Background/terminated message opened
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        final data = message.data;
        if (data.isNotEmpty) {
          _navigatorKey?.currentState?.push(
            MaterialPageRoute(builder: (_) => const AlertsScreen()),
          );
        }
      });

      _initialized = true;
    } catch (e) {
      debugPrint('FCM init failed: $e');
    }
  }

  /// Get localized title from FCM message data
  String _getLocalizedTitle(RemoteMessage message) {
    final data = message.data;
    // Check if we have Nepali translation in data and user prefers Nepali
    final loc = LocalizationService();
    if (loc.isNepali && data.containsKey('title_ne')) {
      return data['title_ne']!;
    }
    // Fallback to notification title or data title_en
    return data['title_en'] ?? message.notification?.title ?? '';
  }

  /// Get localized body from FCM message data
  String _getLocalizedBody(RemoteMessage message) {
    final data = message.data;
    final loc = LocalizationService();
    if (loc.isNepali && data.containsKey('body_ne')) {
      return data['body_ne']!;
    }
    return data['body_en'] ?? message.notification?.body ?? '';
  }

  Future<void> _registerToken() async {
    if (_fcmToken == null) return;
    try {
      final Position? pos = await LocationService().getCurrentLocation();
      await ApiClient.instance.registerPushToken(
        _fcmToken!,
        latitude: pos?.latitude,
        longitude: pos?.longitude,
      );
      debugPrint('FCM token registered: $_fcmToken');
    } catch (e) {
      debugPrint('Failed to register push token: $e');
    }
  }

  Future<void> setSubscription(bool enabled) async {
    if (!_initialized) return;
    try {
      if (enabled) {
        if (_fcmToken != null) {
          await _registerToken();
        }
      } else {
        if (_fcmToken != null) {
          await ApiClient.instance.unsubscribePushToken(_fcmToken!);
        }
      }
    } catch (e) {
      debugPrint('FCM subscription toggle failed: $e');
    }
  }
}