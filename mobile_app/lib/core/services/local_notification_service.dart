import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Thin wrapper around flutter_local_notifications so alerts are visible
/// while the app is in the foreground (FCM data-only pushes are silent
/// otherwise) and so the client-side proximity engine can surface
/// "you are entering an alert zone" warnings.
class LocalNotificationService {
  static final LocalNotificationService instance =
      LocalNotificationService._();

  LocalNotificationService._();

  static const _proximityChannelId = 'proximity_alerts';
  static const _pushChannelId = 'push_alerts';

  final FlutterLocalNotificationsPlugin _plugin =
      FlutterLocalNotificationsPlugin();
  bool _initialized = false;

  Future<void> initialize() async {
    if (_initialized) return;
    try {
      const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
      const initSettings = InitializationSettings(android: androidInit);
      await _plugin.initialize(initSettings);

      final android = _plugin.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
      // Android 13+ requires a runtime request; POST_NOTIFICATIONS is also
      // declared in the manifest.
      await android?.requestNotificationsPermission();

      await _createChannel(
        const AndroidNotificationChannel(
          _proximityChannelId,
          'Nearby Alerts',
          description: 'Warnings when you approach reported hazards',
          importance: Importance.high,
        ),
      );
      await _createChannel(
        const AndroidNotificationChannel(
          _pushChannelId,
          'Travel Alerts',
          description: 'Alerts and approved reports near you',
          importance: Importance.high,
        ),
      );

      _initialized = true;
    } catch (e) {
      debugPrint('Local notifications init failed: $e');
    }
  }

  Future<void> _createChannel(AndroidNotificationChannel channel) async {
    try {
      await _plugin
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(channel);
    } catch (e) {
      debugPrint('Notification channel create failed: $e');
    }
  }

  /// Proximity warning triggered by the on-device location check.
  Future<void> showProximityAlert({
    required int id,
    required String title,
    required String body,
    String? payload,
  }) =>
      _show(
        id: id,
        channelId: _proximityChannelId,
        title: title,
        body: body,
        payload: payload,
      );

  /// Foreground display of an FCM push.
  Future<void> showPush({
    required int id,
    required String title,
    required String body,
    String? payload,
  }) =>
      _show(
        id: id,
        channelId: _pushChannelId,
        title: title,
        body: body,
        payload: payload,
      );

  Future<void> _show({
    required int id,
    required String channelId,
    required String title,
    required String body,
    String? payload,
  }) async {
    if (!_initialized) await initialize();
    if (!_initialized) return;
    try {
      final details = NotificationDetails(
        android: AndroidNotificationDetails(
          channelId,
          channelId == _proximityChannelId ? 'Nearby Alerts' : 'Travel Alerts',
          channelDescription: channelId == _proximityChannelId
              ? 'Warnings when you approach reported hazards'
              : 'Alerts and approved reports near you',
          importance: Importance.high,
          priority: Priority.high,
          visibility: NotificationVisibility.public,
          autoCancel: true,
        ),
      );
      await _plugin.show(id, title, body, details, payload: payload);
    } catch (e) {
      debugPrint('Local notification show failed: $e');
    }
  }
}
