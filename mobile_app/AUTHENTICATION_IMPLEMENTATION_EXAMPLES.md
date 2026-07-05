# Authentication System - Implementation Examples

> Complete working examples for implementing screens using the enhanced authentication system

## 📱 Example 1: Enhanced Login Screen

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

class EnhancedLoginScreen extends StatefulWidget {
  const EnhancedLoginScreen({super.key});

  @override
  State<EnhancedLoginScreen> createState() => _EnhancedLoginScreenState();
}

class _EnhancedLoginScreenState extends State<EnhancedLoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _rememberMe = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);

    final success = await authProvider.login(
      _emailController.text,
      _passwordController.text,
      rememberMe: _rememberMe,
    );

    if (!mounted) return;

    if (success) {
      // ✅ Check profile completion status
      if (authProvider.isProfileCompletionRequired) {
        Navigator.of(context).pushReplacementNamed('/profile-completion');
      } else {
        Navigator.of(context).pushReplacementNamed('/home');
      }
    } else {
      // Show error
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(authProvider.errorMessage ?? 'Login failed'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 80),
                TextField(
                  controller: _emailController,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    border: OutlineInputBorder(),
                    prefixIcon: Icon(Icons.email),
                  ),
                  enabled: !authProvider.isLoading,
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _passwordController,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    border: OutlineInputBorder(),
                    prefixIcon: Icon(Icons.lock),
                  ),
                  obscureText: true,
                  enabled: !authProvider.isLoading,
                ),
                const SizedBox(height: 16),
                CheckboxListTile(
                  title: const Text('Remember me'),
                  value: _rememberMe,
                  onChanged: authProvider.isLoading
                      ? null
                      : (value) => setState(() => _rememberMe = value ?? false),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: authProvider.isLoading ? null : _handleLogin,
                  child: authProvider.isLoading
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Login'),
                ),
                if (authProvider.errorMessage != null) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.red.shade50,
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.red),
                    ),
                    child: Text(
                      authProvider.errorMessage!,
                      style: const TextStyle(color: Colors.red),
                    ),
                  ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}
```

## 👤 Example 2: Enhanced Profile Screen with Full User Data

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

class EnhancedProfileScreen extends StatelessWidget {
  const EnhancedProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('My Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () =>
                Provider.of<AuthProvider>(context, listen: false).refreshProfile(),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () => _showLogoutDialog(context),
          ),
        ],
      ),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          if (!authProvider.isInitialized) {
            return const Center(child: CircularProgressIndicator());
          }

          if (!authProvider.isAuthenticated) {
            return const Center(child: Text('Not authenticated'));
          }

          final user = authProvider.user;
          if (user == null) {
            return const Center(child: CircularProgressIndicator());
          }

          return ListView(
            children: [
              // Profile Header
              Container(
                padding: const EdgeInsets.all(24),
                color: Colors.blue.shade50,
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 50,
                      backgroundColor: Colors.blue,
                      child: Text(
                        user.name[0].toUpperCase(),
                        style: const TextStyle(
                          fontSize: 32,
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      user.name,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    Text(user.email),
                    const SizedBox(height: 12),
                    Chip(
                      label: Text(authProvider.userLevelName),
                      backgroundColor: Colors.green.shade100,
                    ),
                  ],
                ),
              ),

              // Stats
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Statistics',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    GridView.count(
                      crossAxisCount: 2,
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      mainAxisSpacing: 12,
                      crossAxisSpacing: 12,
                      children: [
                        _StatCard('XP', '${user.totalXp}', Colors.orange),
                        _StatCard('Level', '${user.currentLevel}', Colors.blue),
                        _StatCard('Reports', '${user.totalReports}', Colors.green),
                        _StatCard('Approved', '${user.approvedReports}', Colors.purple),
                      ],
                    ),
                  ],
                ),
              ),

              // Detailed Info
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Information',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    _InfoTile('Name', user.name),
                    _InfoTile('Email', user.email),
                    if (user.phone != null) _InfoTile('Phone', user.phone!),
                    _InfoTile('Status', user.status),
                    _InfoTile('Role', user.role),
                    _InfoTile('Level', authProvider.userLevelName),
                    _InfoTile('Approval Rate', '${(user.approvalRate * 100).toStringAsFixed(1)}%'),
                    _InfoTile('Rank', '#${user.rank}'),
                  ],
                ),
              ),

              // Badges
              if (user.badges.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Badges',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: user.badges
                            .map((badge) => Chip(label: Text(badge)))
                            .toList(),
                      ),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Logout'),
        content: const Text('Are you sure you want to logout?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () async {
              await Provider.of<AuthProvider>(context, listen: false).logout();
              if (context.mounted) {
                Navigator.of(context).pushReplacementNamed('/login');
              }
            },
            child: const Text('Logout'),
          ),
        ],
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _StatCard(this.label, this.value, this.color);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
          const SizedBox(height: 8),
          Text(label, style: const TextStyle(fontSize: 12)),
        ],
      ),
    );
  }
}

class _InfoTile extends StatelessWidget {
  final String label;
  final String value;

  const _InfoTile(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: const TextStyle(fontSize: 14)),
          Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }
}
```

## 🏠 Example 3: Protected Home Screen

```dart
class ProtectedHomeScreen extends StatefulWidget {
  const ProtectedHomeScreen({super.key});

  @override
  State<ProtectedHomeScreen> createState() => _ProtectedHomeScreenState();
}

class _ProtectedHomeScreenState extends State<ProtectedHomeScreen> {
  @override
  void initState() {
    super.initState();
    _verifyAuth();
  }

  void _verifyAuth() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      
      // Double-check authentication
      if (!authProvider.isAuthenticated) {
        Navigator.of(context).pushReplacementNamed('/login');
      } else if (authProvider.isProfileCompletionRequired) {
        Navigator.of(context).pushReplacementNamed('/profile-completion');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Consumer<AuthProvider>(
          builder: (context, auth, _) => Text('Welcome, ${auth.userDisplayName}'),
        ),
      ),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          if (!authProvider.isAuthenticated) {
            return const Center(child: Text('Not authenticated'));
          }

          final user = authProvider.user;
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Logged in as: ${user?.name}'),
                Text('Level: ${authProvider.userLevelName}'),
                Text('XP: ${user?.totalXp}'),
              ],
            ),
          );
        },
      ),
    );
  }
}
```

## ⚙️ Example 4: Settings Screen with User Preferences

```dart
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  late SessionManager _sessionManager;
  String? _theme;
  String? _language;

  @override
  void initState() {
    super.initState();
    _sessionManager = SessionManager.instance;
    _loadPreferences();
  }

  Future<void> _loadPreferences() async {
    final theme = await _sessionManager.getPreference('theme', 'light');
    final language = await _sessionManager.getPreference('language', 'en');
    
    setState(() {
      _theme = theme;
      _language = language;
    });
  }

  Future<void> _updateTheme(String value) async {
    await _sessionManager.setPreference('theme', value);
    setState(() => _theme = value);
  }

  Future<void> _updateLanguage(String value) async {
    await _sessionManager.setPreference('language', value);
    setState(() => _language = value);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          ListTile(
            title: const Text('Theme'),
            subtitle: Text(_theme ?? 'Loading...'),
            trailing: DropdownButton<String>(
              value: _theme,
              onChanged: _updateTheme,
              items: const [
                DropdownMenuItem(value: 'light', child: Text('Light')),
                DropdownMenuItem(value: 'dark', child: Text('Dark')),
                DropdownMenuItem(value: 'system', child: Text('System')),
              ],
            ),
          ),
          ListTile(
            title: const Text('Language'),
            subtitle: Text(_language ?? 'Loading...'),
            trailing: DropdownButton<String>(
              value: _language,
              onChanged: _updateLanguage,
              items: const [
                DropdownMenuItem(value: 'en', child: Text('English')),
                DropdownMenuItem(value: 'ne', child: Text('नेपाली')),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
```

## 🔍 Example 5: Navigation Guard

```dart
class AuthGuard extends StatelessWidget {
  final Widget child;

  const AuthGuard({required this.child, super.key});

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, _) {
        // Wait for initialization
        if (!authProvider.isInitialized) {
          return const Scaffold(
            body: Center(child: CircularProgressIndicator()),
          );
        }

        // Check authentication
        if (!authProvider.isAuthenticated) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            Navigator.of(context).pushReplacementNamed('/login');
          });
          return const SizedBox.shrink();
        }

        // Check profile completion
        if (authProvider.isProfileCompletionRequired) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            Navigator.of(context).pushReplacementNamed('/profile-completion');
          });
          return const SizedBox.shrink();
        }

        // All checks passed
        return child;
      },
    );
  }
}

// Usage in routes
'/home': (context) => const AuthGuard(child: HomeScreen()),
'/profile': (context) => const AuthGuard(child: ProfileScreen()),
```

## 🧪 Example 6: Auth State Widget

```dart
class AuthStateWidget extends StatelessWidget {
  final Widget loading;
  final Widget unauthenticated;
  final Widget requiresProfile;
  final Widget Function(UserModel user) authenticated;

  const AuthStateWidget({
    required this.loading,
    required this.unauthenticated,
    required this.requiresProfile,
    required this.authenticated,
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, _) {
        // Loading
        if (!authProvider.isInitialized) {
          return loading;
        }

        // Not authenticated
        if (!authProvider.isAuthenticated) {
          return unauthenticated;
        }

        // Requires profile
        if (authProvider.isProfileCompletionRequired) {
          return requiresProfile;
        }

        // Authenticated with complete profile
        final user = authProvider.user;
        if (user != null) {
          return authenticated(user);
        }

        return loading;
      },
    );
  }
}

// Usage
AuthStateWidget(
  loading: const LoadingScreen(),
  unauthenticated: const LoginScreen(),
  requiresProfile: const ProfileCompletionScreen(),
  authenticated: (user) => HomeScreen(user: user),
)
```

## 📝 Tips & Best Practices

1. **Always check `isInitialized`** before accessing user data
2. **Use `Consumer<AuthProvider>`** for widgets that need to react to auth changes
3. **Handle loading states** with proper UI feedback
4. **Show user-friendly error messages** from `errorMessage`
5. **Call `refreshProfile()`** when user data might be stale
6. **Use SessionManager** for preferences, not SharedPreferences
7. **Test all auth flows** thoroughly before deploying

## 🔗 Related Files

- `session_manager.dart` - Core session management
- `auth_provider.dart` - Auth state management
- `api_client.dart` - API integration
- `AUTHENTICATION_SYSTEM.md` - Full documentation
- `AUTHENTICATION_QUICK_REFERENCE.md` - Quick reference
