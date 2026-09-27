import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../providers/profile_provider.dart';
import '../../providers/travel_context_provider.dart';
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
  static const Duration _timeout = Duration(seconds: 12);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    _initialized = true;
    WidgetsBinding.instance.addPostFrameCallback((_) => _init());
  }

  Future<void> _init() async {
    final auth = context.read<AuthProvider>();
    try {
      await auth.initializeAuth().timeout(_timeout);
    } catch (_) {}

    if (!mounted) return;

    if (auth.isAuthenticated) {
      // Authenticated cold start: restore saved route (guests skip).
      await context.read<TravelContextProvider>().handleLoggedIn();
      try {
        final profileProvider = context.read<ProfileProvider>();
        await profileProvider.loadSettings().timeout(_timeout);
        if (!mounted) return;
        await context
            .read<LocalizationService>()
            .syncFromBackend(profileProvider.settings['language'] as String?)
            .timeout(_timeout);
      } catch (_) {}
      if (!mounted) return;
      Navigator.pushReplacementNamed(context, '/home');
      return;
    }

    if (!mounted) return;
    Navigator.pushReplacementNamed(context, '/home');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text(
              'ORIPORI',
              style: TextStyle(
                fontSize: 32,
                fontWeight: FontWeight.bold,
                color: Color(0xFF031A38),
                letterSpacing: 2,
              ),
            ),
            const SizedBox(height: 12),
            const Text(
              'Coming soon...',
              style: TextStyle(
                fontSize: 16,
                color: Colors.grey,
              ),
            ),
            const SizedBox(height: 40),
            SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(
                strokeWidth: 2.5,
                color: Colors.grey.shade400,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
