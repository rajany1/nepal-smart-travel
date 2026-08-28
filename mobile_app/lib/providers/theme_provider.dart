import 'package:flutter/material.dart';
import "../../core/services/localization_service.dart";
import 'package:shared_preferences/shared_preferences.dart';

class ThemeProvider extends ChangeNotifier {
  static const _prefKey = 'theme_mode';

  ThemeMode _mode = ThemeMode.light;

  ThemeMode get mode => _mode;

  bool get isDarkMode {
    switch (_mode) {
      case ThemeMode.dark:
        return true;
      case ThemeMode.system:
        return WidgetsBinding.instance.platformDispatcher.platformBrightness == Brightness.dark;
      default:
        return false;
    }
  }

  ThemeProvider() {
    _load();
  }

  Future<void> _load() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final stored = prefs.getString(_prefKey);
      switch (stored) {
        case 'dark':
          _mode = ThemeMode.dark;
        case 'system':
          _mode = ThemeMode.system;
        default:
          _mode = ThemeMode.light;
      }
    } catch (_) {
      _mode = ThemeMode.light;
    }
    notifyListeners();
  }

  Future<void> setMode(ThemeMode mode) async {
    _mode = mode;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _prefKey,
        mode == ThemeMode.dark
            ? 'dark'
            : mode == ThemeMode.system
                ? 'system'
                : 'light',
      );
    } catch (_) {}
  }
}
