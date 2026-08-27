import 'package:flutter/material.dart';
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
import 'core/services/app_settings_service.dart';
import 'core/services/localization_service.dart';

import 'features/auth/login_screen.dart';
import 'features/auth/register_screen.dart';
import 'features/auth/email_verification_screen.dart';
import 'features/auth/forgot_password_screen.dart';
import 'features/auth/reset_password_screen.dart';

import 'features/profile/profile_edit_screen.dart';
import 'features/profile/profile_completion_screen.dart';
import 'features/profile/settings_screen.dart';
import 'features/profile/policies_screen.dart';

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
  await authProvider.initializeAuth();

  final localizationService = LocalizationService();
  await localizationService.init();

  final pushService = PushNotificationService();
  await pushService.initialize();

  // FL-32: give push taps a navigator so FCM deep links can open screens
  final navigatorKey = GlobalKey<NavigatorState>();
  pushService.setNavigatorKey(navigatorKey);

  runApp(NepalSmartTravelApp(
    authProvider: authProvider,
    localizationService: localizationService,
    navigatorKey: navigatorKey,
  ));
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
        ChangeNotifierProvider<LocalizationService>.value(value: localizationService),
      ],
      child: Consumer<ThemeProvider>(
        builder: (context, themeProvider, _) => Consumer<LocalizationService>(
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
            default:
              return null;
          }
        },
        ),
        ),
      ),
    );
  }
}

class AuthInitializationWrapper extends StatefulWidget {
  const AuthInitializationWrapper({super.key});

  @override
  State<AuthInitializationWrapper> createState() =>
      _AuthInitializationWrapperState();
}

class _AuthInitializationWrapperState
    extends State<AuthInitializationWrapper> {
  bool _initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    if (_initialized) return;
    _initialized = true;

    _init();
  }

  Future<void> _init() async {
    final auth = context.read<AuthProvider>();

    try {
      await auth.initializeAuth();

      if (!mounted) return;

      if (auth.isAuthenticated) {
        // Preload user settings (notifications, theme, language) so screens
        // reflect the server-side values on first open.
        final profileProvider = context.read<ProfileProvider>();
        await profileProvider.loadSettings();
        // Align the app language with the server-side setting.
        await context
            .read<LocalizationService>()
            .syncFromBackend(profileProvider.settings['language'] as String?);

        if (auth.isProfileCompletionRequired) {
          Navigator.pushReplacementNamed(
              context, '/profile-completion');
        } else {
          Navigator.pushReplacementNamed(context, '/home');
        }
      } else {
        Navigator.pushReplacementNamed(context, '/login');
      }
    } catch (e) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            CircularProgressIndicator(),
            SizedBox(height: 20),
            Text("Initializing app..."),
          ],
        ),
      ),
    );
  }
}