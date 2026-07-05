import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';
import '../core/api/api_client.dart';
import '../config/constants/app_constants.dart';
import '../features/alerts/alerts_screen.dart';

class OneSignalService {
  static final OneSignalService _instance = OneSignalService._();
  factory OneSignalService() => _instance;
  OneSignalService._();

  String? _playerId;
  bool _initialized = false;
  GlobalKey<NavigatorState>? _navigatorKey;

  String? get playerId => _playerId;
  bool get isInitialized => _initialized;

  void setNavigatorKey(GlobalKey<NavigatorState> key) {
    _navigatorKey = key;
  }

  Future<void> initialize() async {
    if (_initialized) return;
    try {
      OneSignal.initialize(AppConstants.oneSignalAppId);
      await OneSignal.Notifications.requestPermission(true);
      _initialized = true;

      // Register observer FIRST so it catches ID changes when SDK resolves async
      OneSignal.User.pushSubscription
          .addObserver((OSPushSubscriptionChangedState state) {
        _playerId = state.current.id;
        if (_playerId != null) {
          unawaited(_registerToken());
        }
      });

      // Also try immediately — ID might already be resolved
      _playerId = OneSignal.User.pushSubscription.id;
      if (_playerId != null) {
        await _registerToken();
      }

      // Handle notification tap — navigate to alerts
      OneSignal.Notifications.addClickListener((event) {
        final data = event.notification.additionalData;
        if (data == null) return;
        _navigatorKey?.currentState?.push(
          MaterialPageRoute(builder: (_) => const AlertsScreen()),
        );
      });
    } catch (e) {
      debugPrint('OneSignal init failed: $e');
    }
  }

  Future<void> _registerToken() async {
    if (_playerId == null) return;
    try {
      await ApiClient.instance.registerPushToken(_playerId!);
      debugPrint('OneSignal token registered: $_playerId');
    } catch (e) {
      debugPrint('Failed to register push token: $e');
    }
  }

  Future<void> setSubscription(bool enabled) async {
    if (!_initialized) return;
    try {
      if (enabled) {
        await OneSignal.User.addTagWithKey('notifications', 'enabled');
        if (_playerId != null) {
          await ApiClient.instance.registerPushToken(_playerId!);
        }
      } else {
        await OneSignal.User.removeTag('notifications');
        if (_playerId != null) {
          await ApiClient.instance.unsubscribePushToken(_playerId!);
        }
      }
    } catch (e) {
      debugPrint('OneSignal subscription toggle failed: $e');
    }
  }
}
