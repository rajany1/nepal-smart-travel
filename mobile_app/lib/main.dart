import 'package:flutter/material.dart';
import 'dart:async';
import "../../core/services/localization_service.dart";
import 'package:flutter/services.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:provider/provider.dart';
import 'config/themes/app_theme.dart';
import 'config/constants/app_constants.dart';

import 'providers/auth_provider.dart';
import 'providers/alert_provider.dart';
import 'providers/place_provider.dart';
import 'providers/place_details_provider.dart';
import 'providers/profile_completion_provider.dart';
import 'providers/profile_provider.dart';
import 'providers/report_provider.dart';
import 'providers/leaderboard_provider.dart';
import 'providers/map_view_provider.dart';
import 'providers/ad_provider.dart';
import 'providers/offer_provider.dart';
import 'providers/route_provider.dart';
import 'providers/theme_provider.dart';
import 'providers/wallet_provider.dart';
import 'providers/partner_payment_provider.dart';
import 'providers/around_me_provider.dart';
import 'providers/sos_provider.dart';
import 'core/services/app_settings_service.dart';

import 'features/auth/login_screen.dart';
import 'features/auth/register_screen.dart';
import 'features/auth/email_verification_screen.dart';
import 'features/auth/forgot_password_screen.dart';
import 'features/auth/reset_password_screen.dart';

import 'features/profile/profile_edit_screen.dart';
import 'features/profile/profile_completion_screen.dart';
import 'features/profile/settings_screen.dart';
import 'features/profile/policies_screen.dart';
import 'features/profile/legal_document_screen.dart';

import 'features/auth/splash_screen.dart';

import 'features/map/home_screen.dart';
import 'features/places/nearby_map_screen.dart';
import 'features/routes/routes_screen.dart';
import 'features/reporting/reports_list_screen.dart';
import 'features/emergency/emergency_screen.dart';
import 'features/assistant/assistant_screen.dart';
import 'features/profile/profile_screen.dart';
import 'features/alerts/alerts_screen.dart';
import 'features/leaderboard/leaderboard_screen.dart';

// Consumer feature screens
import 'features/subscriptions/subscription_plans_screen.dart';
import 'features/store/store_screen.dart';
import 'features/wallet/wallet_screen.dart';

import 'services/push_notification_service.dart';
import 'core/services/local_notification_service.dart';
import 'core/services/sync_service.dart';

/// Background/terminated FCM handler. Must be top-level and annotated so
/// the AOT snapshot keeps it. Runs in its own isolate - re-initializes
/// what it needs.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  final title = message.notification?.title ??
      message.data['title_en'] ??
      'Travel Alert';
  final body = message.notification?.body ?? message.data['body_en'] ?? '';
  if (title.isEmpty && body.isEmpty) return;
  await LocalNotificationService.instance.showPush(
    id: (message.messageId ?? message.sentTime?.millisecondsSinceEpoch
            .toString() ?? 'bg')
        .hashCode & 0x7fffffff,
    title: title,
    body: body,
  );
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
  ));

  SyncService.instance.startMonitoring();

  // Apply Data Saver image-cache limits before the first frame
  AppSettingsService.applyDataSaverLimits();

  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
  } catch (e) {
    debugPrint('Firebase init failed: $e');
  }

  final authProvider = AuthProvider();
  final localizationService = LocalizationService();
  final pushService = PushNotificationService();
  final navigatorKey = GlobalKey<NavigatorState>();
  pushService.setNavigatorKey(navigatorKey);

  // FL-32: give push taps a navigator so FCM deep links can open screens.
  // Render the very first frame immediately — see the comment below.
  runApp(NepalSmartTravelApp(
    authProvider: authProvider,
    localizationService: localizationService,
    navigatorKey: navigatorKey,
  ));

  // === Cold-start performance fix ===
  // Previously the app `await`-ed auth restore, the translations fetch and the
  // push-notification setup BEFORE runApp(), so the native (platform) splash
  // stayed frozen until every network call and the OS permission dialog
  // finished — easily 1 minute+ on slow links. That made the launch feel
  // hung.
  //
  // Now runApp() is reached immediately (Flutter splash shows at once) and the
  // heavy bootstrapping runs in the background. Auth routing is driven by the
  // splash screen itself (AuthInitializationWrapper), so these do not delay the
  // first frame:
  //   * pushService.initialize() — FCM permission dialog + token network call
  //   * localizationService.init() — fetches the translation dictionary
  unawaited(pushService.initialize());
  unawaited(localizationService.init());
}

class NepalSmartTravelApp extends StatelessWidget {
  final AuthProvider authProvider;
  final LocalizationService localizationService;
  final GlobalKey<NavigatorState> navigatorKey;

  const NepalSmartTravelApp({
    super.key,
    required this.authProvider,
    required this.localizationService,
    required this.navigatorKey,
  });

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthProvider>.value(value: authProvider),
        ChangeNotifierProvider(create: (_) => AlertProvider()),
        ChangeNotifierProvider(create: (_) => PlaceProvider()),
        ChangeNotifierProvider(create: (_) => PlaceDetailsProvider()),
        ChangeNotifierProvider(create: (_) => ProfileCompletionProvider()),
        ChangeNotifierProvider(create: (_) => ProfileProvider()),
        ChangeNotifierProvider(create: (_) => ReportProvider()),
        ChangeNotifierProvider(create: (_) => LeaderboardProvider()),
        ChangeNotifierProvider(create: (_) => MapViewProvider()),
        ChangeNotifierProvider(create: (_) => AdProvider()),
        ChangeNotifierProvider(create: (_) => OfferProvider()),
        ChangeNotifierProvider(create: (_) => RouteProvider()),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        ChangeNotifierProvider(create: (_) => WalletProvider()),
        ChangeNotifierProvider(create: (_) => PartnerPaymentProvider()),
        ChangeNotifierProvider(create: (_) => AroundMeProvider()),
        ChangeNotifierProvider(create: (_) => SosProvider()),
        ChangeNotifierProvider<LocalizationService>.value(value: localizationService),
      ],
      child: Consumer<ThemeProvider>(
        builder: (context, themeProvider, _) {
          // Update status bar icons based on theme
          SystemChrome.setSystemUIOverlayStyle(SystemUiOverlayStyle(
            statusBarIconBrightness: themeProvider.isDarkMode ? Brightness.light : Brightness.dark,
            statusBarBrightness: themeProvider.isDarkMode ? Brightness.dark : Brightness.light,
            statusBarColor: Colors.transparent,
          ));
          return Consumer<LocalizationService>(
            builder: (context, localization, _) => MaterialApp(
              debugShowCheckedModeBanner: false,
              navigatorKey: navigatorKey,
              title: AppConstants.appName,
              theme: AppTheme.lightTheme,
              darkTheme: AppTheme.darkTheme,
              themeMode: themeProvider.mode,
              home: const AuthInitializationWrapper(),
              onGenerateRoute: (settings) {
                switch (settings.name) {
                  case '/login':
                    return MaterialPageRoute(builder: (_) => const LoginScreen(), settings: settings);
                  case '/register':
                    return MaterialPageRoute(builder: (_) => const RegisterScreen(), settings: settings);
                  case '/forgot-password':
                    return MaterialPageRoute(builder: (_) => const ForgotPasswordScreen(), settings: settings);
                  case '/reset-password':
                    final args = settings.arguments as Map<String, dynamic>;
                    return MaterialPageRoute(
                      builder: (_) => ResetPasswordScreen(
                        email: args['email'] as String,
                        resetToken: args['reset_token'] as String,
                      ),
                      settings: settings,
                    );
                  case '/profile-edit':
                    return MaterialPageRoute(builder: (_) => const ProfileEditScreen(), settings: settings);
                  case '/profile-setup':
                  case '/profile-completion':
                    return MaterialPageRoute(builder: (_) => const ProfileCompletionScreen(), settings: settings);
                  case '/settings':
                    return MaterialPageRoute(builder: (_) => const SettingsScreen(), settings: settings);
                  case '/policies':
                    return MaterialPageRoute(builder: (_) => const PoliciesScreen(), settings: settings);
                  case '/home':
                    return MaterialPageRoute(builder: (_) => const HomeScreen(), settings: settings);
                  case '/nearby-places':
                    return MaterialPageRoute(builder: (_) => const NearbyMapScreen(), settings: settings);
                  case '/routes':
                    return MaterialPageRoute(builder: (_) => const RoutesScreen(), settings: settings);
                  case '/reports':
                    return MaterialPageRoute(builder: (_) => const ReportsListScreen(), settings: settings);
                  case '/emergency':
                    return MaterialPageRoute(builder: (_) => const EmergencyScreen(), settings: settings);
                  case '/assistant':
                    return MaterialPageRoute(builder: (_) => const AssistantScreen(), settings: settings);
                  case '/profile':
                    return MaterialPageRoute(builder: (_) => const ProfileScreen(), settings: settings);
                  case '/alerts':
                    return MaterialPageRoute(builder: (_) => const AlertsScreen(), settings: settings);
                  case '/leaderboard':
                    return MaterialPageRoute(builder: (_) => const LeaderboardScreen(), settings: settings);
                  case '/email-verification':
                    return MaterialPageRoute(
                      builder: (_) => EmailVerificationScreen(
                        email: settings.arguments as String? ?? '',
                      ),
                      settings: settings,
                    );
                  case '/subscriptions':
                    return MaterialPageRoute(builder: (_) => const SubscriptionPlansScreen(), settings: settings);
                  case '/store':
                    return MaterialPageRoute(builder: (_) => const StoreScreen(), settings: settings);
                  case '/wallet':
                    return MaterialPageRoute(builder: (_) => const WalletScreen(), settings: settings);
                  case '/legal':
                    final type = settings.arguments as String? ?? 'privacy_policy';
                    return MaterialPageRoute(builder: (_) => LegalDocumentScreen(type: type), settings: settings);
                  default:
                    return null;
                }
              },
            ),
          );
        },
      ),
    );
  }
}