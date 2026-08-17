import 'package:flutter/widgets.dart';
import "../../core/services/localization_service.dart";
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api_client.dart';

/// Reactive translation helper for screens: watching the provider means the
/// whole visible tree rebuilds when the language changes.
extension L10n on BuildContext {
  String t(String key) => watch<LocalizationService>().t(key);

  bool get isNepali => watch<LocalizationService>().isNepali;
}

/// Dictionary-driven localization (English <-> Nepali).
///
/// English is the built-in fallback for every key; Nepali translations come
/// from the admin-managed glossary served by GET /v1/translations.
class LocalizationService extends ChangeNotifier {
  static const _prefKey = 'app_language';

  String _language = 'en';
  Map<String, String> _dictionary = {};

  String get language => _language;
  bool get isNepali => _language == 'ne';

  /// Translate [key]: Nepali when the user language is 'ne' and the word is
  /// in the dictionary, otherwise the English key itself.
  String t(String key) {
    if (_language != 'ne') return key;
    return _dictionary[key] ?? key;
  }

  /// Load persisted language then fetch the dictionary from the backend.
  /// Never throws on network errors — English fallback covers missing words.
  Future<void> init() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final stored = prefs.getString(_prefKey);
      if (stored == 'en' || stored == 'ne') _language = stored ?? 'en';
    } catch (_) {}
    await loadDictionary();
    notifyListeners();
  }

  /// Pull the glossary from the backend (public endpoint, works logged out).
  Future<void> loadDictionary() async {
    try {
      final res = await ApiClient.instance.getTranslations();
      if (res.statusCode == 200) {
        final body = res.data;
        final data = body is Map ? body['data'] : null;
        if (data is Map) {
          _dictionary = data.map(
            (k, v) => MapEntry(k.toString(), v.toString()),
          );
          notifyListeners();
        }
      }
    } catch (e) {
      debugPrint('LocalizationService: dictionary fetch failed: $e');
    }
  }

  /// Switch language live and persist locally. Backend persistence is
  /// handled by the settings save flow (updateUserSettings).
  Future<void> setLanguage(String lang) async {
    if (lang != 'en' && lang != 'ne') return;
    _language = lang;
    notifyListeners();
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_prefKey, lang);
    } catch (_) {}
  }

  /// Align with the user's server-side language when a profile loads.
  Future<void> syncFromBackend(String? lang) async {
    if (lang == 'en' || lang == 'ne') {
      await setLanguage(lang ?? 'en');
    }
  }
}
