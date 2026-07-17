import 'package:flutter/foundation.dart';

class AppConstants {
  AppConstants._();

  // App Info
  static const String appName = 'Nepal Smart Travel';
  static const String appTagline = 'Your Trusted Travel Intelligence Platform';
  static const String appVersion = '1.0.0';

  // API
  static const String devEmulator = 'http://10.0.2.2:8000/api/v1';
  static const String devPhone = 'http://192.168.10.68:8000/api/v1';
  static String get prod => const String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://api.nepalsmarttravel.com/api/v1',
  );
  static const Duration apiTimeout = Duration(seconds: 30);
  static const int apiRetryCount = 3;
  static const int itemsPerPage = 20;

  static String get baseUrl {
    if (kReleaseMode) {
      return prod;
    }

    return devPhone;
  }  

  // Location
  static const double defaultLatitude = 27.7172;
  static const double defaultLongitude = 85.3240;
  static const double defaultRadiusKm = 5.0;
  static const double maxSearchRadiusKm = 50.0;

  // Auth
  static const String tokenKey = 'auth_token';
  static const String refreshTokenKey = 'refresh_token';
  static const String userDataKey = 'user_data';

  // Google OAuth — set via --dart-define=GOOGLE_CLIENT_ID=... or env
  static String get googleServerClientId => const String.fromEnvironment(
    'GOOGLE_CLIENT_ID',
    defaultValue: '672688394813-sk0p5l42e05845gsvjghe8f9cuj8ke69.apps.googleusercontent.com',
  );

  // OneSignal Push Notifications — set via --dart-define=ONESIGNAL_APP_ID=... or env
  static String get oneSignalAppId => const String.fromEnvironment(
    'ONESIGNAL_APP_ID',
    defaultValue: '',
  );

  // Cache
  static const Duration cacheDuration = Duration(hours: 1);
  static const Duration locationCacheDuration = Duration(minutes: 5);
  static const Duration reportsCacheDuration = Duration(minutes: 10);

  // XP System
  static const int xpPerApprovedReport = 10;
  static const int xpPerEmergencyAlert = 25;
  static const int xpPerHiddenPlace = 15;
  static const int xpPerImageUpload = 5;
  static const int xpPerHighRatingReport = 5;
  static const int xpPerMostHelpful = 20;
  static const int xpPenaltyFakeReport = -20;
  static const int xpPenaltyDuplicate = -10;
  static const int xpPenaltyOffensive = -30;
  static const int xpPenaltyWarning = -50;

  // User Levels
  static const int explorerMaxLevel = 5;
  static const int contributorMaxLevel = 15;
  static const int trustedLocalMaxLevel = 30;
  static const int regionalGuideMaxLevel = 50;
  static const int communityExpertMaxLevel = 100;

  // Maps
  static const double defaultMapZoom = 14.0;
  static const double minMapZoom = 5.0;
  static const double maxMapZoom = 20.0;

  // Emergency
  static const String ambulanceNumber = '102';
  static const String policeNumber = '100';
  static const String fireNumber = '101';
  static const String disasterNumber = '1149';
  static const String touristPolice = '1144';

  // Offline
  static const String offlineDbName = 'nepal_travel_offline.db';
  static const int offlineDbVersion = 1;

  // Feature Flags
  static const bool enableAIAssistant = true;
  static const bool enableOfflineMaps = true;
  static const bool enableGamification = true;
  static const bool enableEmergencyFeatures = true;
}