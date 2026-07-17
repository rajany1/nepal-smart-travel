import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import '../core/api/api_client.dart';
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

      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        debugPrint('Foreground message: ${message.notification?.title}');
      });

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

  Future<void> _registerToken() async {
    if (_fcmToken == null) return;
    try {
      await ApiClient.instance.registerPushToken(_fcmToken!);
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
          await ApiClient.instance.registerPushToken(_fcmToken!);
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
