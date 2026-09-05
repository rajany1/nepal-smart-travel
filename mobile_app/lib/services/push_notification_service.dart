import 'dart:async';
import '../../core/services/localization_service.dart';
import 'package:flutter/material.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:geolocator/geolocator.dart';
import '../core/api/api_client.dart';
import '../core/services/local_notification_service.dart';
import '../core/services/location_service.dart';
import '../features/alerts/alerts_screen.dart';

class PushNotificationService {
  static final PushNotificationService _instance = PushNotificationService._();
  factory PushNotificationService() => _instance;
  PushNotificationService._();

  /// How often the device's stored push-token location is refreshed.
  static const Duration _locationSyncInterval = Duration(minutes: 5);

  String? _fcmToken;
  bool _initialized = false;
  Timer? _locationSyncTimer;
  GlobalKey<NavigatorState>? _navigatorKey;

  String? get fcmToken => _fcmToken;
  bool get isInitialized => _initialized;

  void setNavigatorKey(GlobalKey<NavigatorState> key) {
    _navigatorKey = key;
  }

  Future<void> initialize() async {
    if (_initialized) return;
    try {
      // Local notifications must be ready before the first foreground
      // FCM message arrives (Android 13+ also needs POST_NOTIFICATIONS).
      await LocalNotificationService.instance.initialize();

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

      // Foreground message handler - show a real system notification
      // (FCM data-only messages are silent while the app is open).
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        final title = _getLocalizedTitle(message);
        final body = _getLocalizedBody(message);
        debugPrint('Foreground message: $title - $body');
        if (title.isEmpty && body.isEmpty) return;
        LocalNotificationService.instance.showPush(
          id: (message.messageId ?? message.sentTime?.millisecondsSinceEpoch
                  .toString() ??
              'push')
              .hashCode & 0x7fffffff,
          title: title,
          body: body,
          payload: message.data['type'] ?? '',
        );
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

      _startLocationSync();

      _initialized = true;
    } catch (e) {
      debugPrint('FCM init failed: $e');
    }
  }

  /// Keeps push_tokens.lat/lng fresh so server-side "nearby users"
  /// targeting stays accurate while the user moves.
  void _startLocationSync() {
    _locationSyncTimer?.cancel();
    _locationSyncTimer = Timer.periodic(_locationSyncInterval, (_) {
      unawaited(_syncTokenLocation());
    });
    unawaited(_syncTokenLocation(delayed: true));
  }

  Future<void> _syncTokenLocation({bool delayed = false}) async {
    if (_fcmToken == null) return;
    try {
      if (delayed) {
        // Give the first GPS fix some time after app start.
        await Future<void>.delayed(const Duration(seconds: 20));
        if (_fcmToken == null) return;
      }
      final Position? pos = await LocationService().getLastKnownPosition();
      if (pos == null) return;
      await ApiClient.instance.updatePushTokenLocation(
        fcmToken: _fcmToken,
        latitude: pos.latitude,
        longitude: pos.longitude,
      );
    } catch (e) {
      // Silent - location sync must never disturb the user.
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
    } catch (e) {
      // Push token registration failed silently
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