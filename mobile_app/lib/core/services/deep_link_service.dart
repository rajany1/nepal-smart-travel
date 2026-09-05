import 'dart:async';
import 'package:flutter/material.dart';
import 'package:app_links/app_links.dart';

class DeepLinkService {
  static final DeepLinkService _instance = DeepLinkService._();
  factory DeepLinkService() => _instance;
  DeepLinkService._();

  final AppLinks _appLinks = AppLinks();
  StreamSubscription<Uri>? _sub;
  GlobalKey<NavigatorState>? _navigatorKey;

  void init(GlobalKey<NavigatorState> navigatorKey) {
    _navigatorKey = navigatorKey;

    // Handle link when app is already running
    _sub = _appLinks.uriLinkStream.listen((Uri uri) {
      _handleLink(uri);
    }, onError: (err) {
      debugPrint('Deep link error: $err');
    });
  }

  /// Handle a deep link URI
  void _handleLink(Uri uri) {
    final path = uri.path;
    final segments = path.split('/').where((s) => s.isNotEmpty).toList();

    if (segments.isEmpty) return;

    final navigator = _navigatorKey?.currentState;
    if (navigator == null) return;

    switch (segments[0]) {
      case 'places':
        if (segments.length >= 2) {
          // Navigate to place details — push home first then place
          navigator.pushNamedAndRemoveUntil('/home', (route) => false);
          // Small delay to let home load, then navigate
          Future.delayed(const Duration(milliseconds: 500), () {
            navigator.pushNamed('/nearby-places');
          });
        }
        break;
      case 'reports':
        navigator.pushNamedAndRemoveUntil('/home', (route) => false);
        Future.delayed(const Duration(milliseconds: 500), () {
          navigator.pushNamed('/reports');
        });
        break;
      case 'alerts':
        navigator.pushNamedAndRemoveUntil('/home', (route) => false);
        Future.delayed(const Duration(milliseconds: 500), () {
          navigator.pushNamed('/alerts');
        });
        break;
    }
  }

  void dispose() {
    _sub?.cancel();
  }
}
