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

  return false;
}