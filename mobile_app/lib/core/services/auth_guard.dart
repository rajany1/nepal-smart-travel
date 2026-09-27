import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../providers/auth_provider.dart';

/// Guest-mode auth guard.
///
/// Architecture: the app opens in guest mode (no login wall). Users can
/// freely explore / browse nearby / see emergency info. But any *action*
/// (report / save / post / claim) requires an account. Call [requireLogin]
/// from an action handler: if the user is not signed in it shows a prompt and
/// routes to the login screen.
///
/// Returns `true` if the user is (or became) authenticated, `false` if the
/// user cancelled or login failed.
Future<bool> requireLogin(BuildContext context) async {
  final auth = context.read<AuthProvider>();
  if (auth.isAuthenticated) return true;

  final shouldLogin = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Login Required'),
      content: const Text(
          'You need an account to do that. Please log in or register to continue.'),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx, false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, true),
          child: const Text('Login / Register'),
        ),
      ],
    ),
  );

  if (shouldLogin == true && context.mounted) {
    await Navigator.pushNamed(context, '/login');
  }

  // Re-check auth state after returning from login flow.
  // The login screen sets AuthProvider._isAuthenticated = true on success
  // before navigating away, so by the time we return here the state is
  // already updated.
  if (!context.mounted) return false;
  return context.read<AuthProvider>().isAuthenticated;
}