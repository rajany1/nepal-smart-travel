import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../config/themes/app_theme.dart';
import '../../config/constants/app_constants.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../core/services/localization_service.dart';

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

  static const Duration _timeout = Duration(seconds: 12);

  Future<void> _init() async {
    final auth = context.read<AuthProvider>();

    try {
      // Hard safety ceiling: even if a restore / network / settings call
      // stalls, we must never leave the user staring at the splash. Whatever
      // finishes first wins; the user is routed to a valid screen either way.
      await auth.initializeAuth().timeout(_timeout);

      if (!mounted) return;

      if (auth.isAuthenticated) {
        final profileProvider = context.read<ProfileProvider>();
        await profileProvider.loadSettings().timeout(_timeout);

        if (!mounted) return;

        await context
            .read<LocalizationService>()
            .syncFromBackend(profileProvider.settings['language'] as String?)
            .timeout(_timeout);

        if (!mounted) return;

        if (auth.isProfileCompletionRequired) {
          Navigator.pushReplacementNamed(context, '/profile-completion');
        } else {
          Navigator.pushReplacementNamed(context, '/home');
        }
      } else {
        Navigator.pushReplacementNamed(context, '/login');
      }
    } catch (e) {
      if (!mounted) return;
      Navigator.pushReplacementNamed(context, '/login');
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppTheme.darkSurfaceColor : AppTheme.backgroundColor;
    final primary = isDark ? AppTheme.primaryLight : AppTheme.primaryColor;
    final text = isDark ? AppTheme.textSecondary.withValues(alpha: 0.8) : AppTheme.textSecondary;

    return Scaffold(
      backgroundColor: bg,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            // Brand mark
            Container(
              width: 88,
              height: 88,
              decoration: BoxDecoration(
                color: primary,
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: primary.withValues(alpha: 0.35),
                    blurRadius: 28,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: const Icon(Icons.travel_explore, color: Colors.white, size: 44),
            ),
            const SizedBox(height: 22),
            Text(
              AppConstants.appName,
              style: GoogleFonts.poppins(
                fontSize: 22,
                fontWeight: FontWeight.w700,
                color: isDark ? AppTheme.textPrimary : AppTheme.textPrimary,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              AppConstants.appTagline,
              textAlign: TextAlign.center,
              style: GoogleFonts.poppins(
                fontSize: AppTheme.textSm,
                color: text,
              ),
            ),
            const SizedBox(height: 34),
            SizedBox(
              height: 20,
              width: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2.5,
                color: primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}