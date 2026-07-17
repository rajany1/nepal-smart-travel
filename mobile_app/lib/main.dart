import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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
import 'providers/store_provider.dart';
import 'providers/ad_provider.dart';
import 'providers/booking_provider.dart';

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
import 'features/places/nearby_places_screen.dart';
import 'features/reporting/reports_list_screen.dart';
import 'features/emergency/emergency_screen.dart';
import 'features/assistant/assistant_screen.dart';
import 'features/profile/profile_screen.dart';
import 'features/alerts/alerts_screen.dart';
import 'features/leaderboard/leaderboard_screen.dart';

// Consumer feature screens
import 'features/partners/partners_list_screen.dart';
import 'features/bookings/my_bookings_screen.dart';
import 'features/sponsors/sponsors_screen.dart';
import 'features/subscriptions/subscription_plans_screen.dart';
import 'features/store/store_screen.dart';

import 'services/push_notification_service.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
  ));

  final authProvider = AuthProvider();
  await authProvider.initializeAuth();

  final pushService = PushNotificationService();
  await pushService.initialize();

  runApp(NepalSmartTravelApp(authProvider: authProvider));
}

class NepalSmartTravelApp extends StatelessWidget {
  final AuthProvider authProvider;

  const NepalSmartTravelApp({
    super.key,
    required this.authProvider,
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
        ChangeNotifierProvider(create: (_) => StoreProvider()),
        ChangeNotifierProvider(create: (_) => AdProvider()),
        ChangeNotifierProvider(create: (_) => BookingProvider()),
      ],
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: AppConstants.appName,
        theme: AppTheme.lightTheme,
        darkTheme: AppTheme.darkTheme,
        themeMode: ThemeMode.light,

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
            case '/profile-setup':
              return MaterialPageRoute(builder: (_) => const ProfileEditScreen(), settings: settings);
            case '/profile-completion':
              return MaterialPageRoute(builder: (_) => const ProfileCompletionScreen(), settings: settings);
            case '/settings':
              return MaterialPageRoute(builder: (_) => const SettingsScreen(), settings: settings);
            case '/policies':
              return MaterialPageRoute(builder: (_) => const PoliciesScreen(), settings: settings);
            case '/home':
              return MaterialPageRoute(builder: (_) => const HomeScreen(), settings: settings);
            case '/nearby-places':
              return MaterialPageRoute(builder: (_) => const NearbyPlacesScreen(), settings: settings);
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
            case '/partners':
              return MaterialPageRoute(builder: (_) => const PartnersListScreen(), settings: settings);
            case '/bookings':
              return MaterialPageRoute(builder: (_) => const MyBookingsScreen(), settings: settings);
            case '/sponsors':
              return MaterialPageRoute(builder: (_) => const SponsorsScreen(), settings: settings);
            case '/subscriptions':
              return MaterialPageRoute(builder: (_) => const SubscriptionPlansScreen(), settings: settings);
            case '/store':
              return MaterialPageRoute(builder: (_) => const StoreScreen(), settings: settings);
            default:
              return null;
          }
        },
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