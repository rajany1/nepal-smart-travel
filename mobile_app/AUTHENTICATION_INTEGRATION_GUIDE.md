# Authentication Integration Guide

## Best Practices for Using the New Auth System

### 1. Login Screen Implementation

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';

class EnhancedLoginScreen extends StatefulWidget {
  const EnhancedLoginScreen({super.key});

  @override
  State<EnhancedLoginScreen> createState() => _EnhancedLoginScreenState();
}

class _EnhancedLoginScreenState extends State<EnhancedLoginScreen> {
  final emailController = TextEditingController();
  final passwordController = TextEditingController();
  bool rememberMe = false;

  @override
  void dispose() {
    emailController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    
    // Validate inputs
    if (emailController.text.isEmpty || passwordController.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill all fields')),
      );
      return;
    }

    // Attempt login with remember me option
    final success = await authProvider.login(
      emailController.text,
      passwordController.text,
      rememberMe: rememberMe,
    );

    if (!mounted) return;

    if (success) {
      // Navigation is handled by AuthProvider state changes
      // Profile completion requirement is checked automatically
      if (authProvider.isProfileCompletionRequired) {
        Navigator.of(context).pushReplacementNamed('/profile-completion');
      } else {
        Navigator.of(context).pushReplacementNamed('/home');
      }
    } else {
      // Show error message from provider
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
      appBar: AppBar(title: const Text('Login')),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                TextField(
                  controller: emailController,
                  decoration: const InputDecoration(
                    labelText: 'Email',
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.emailAddress,
                  enabled: !authProvider.isLoading,
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: passwordController,
                  decoration: const InputDecoration(
                    labelText: 'Password',
                    border: OutlineInputBorder(),
                  ),
                  obscureText: true,
                  enabled: !authProvider.isLoading,
                ),
                const SizedBox(height: 16),
                CheckboxListTile(
                  title: const Text('Remember me'),
                  value: rememberMe,
                  onChanged: authProvider.isLoading
                      ? null
                      : (value) => setState(() => rememberMe = value ?? false),
                ),
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: authProvider.isLoading ? null : _handleLogin,
                    child: authProvider.isLoading
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Login'),
                  ),
                ),
                const SizedBox(height: 16),
                TextButton(
                  onPressed: authProvider.isLoading
                      ? null
                      : () => Navigator.of(context)
                          .pushNamed('/forgot-password'),
                  child: const Text('Forgot Password?'),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
```

### 2. Profile Screen with User Data

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../core/services/session_manager.dart';

class EnhancedProfileScreen extends StatelessWidget {
  const EnhancedProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () =>
                Provider.of<AuthProvider>(context, listen: false).refreshProfile(),
          ),
          IconButton(
            icon: const Icon(Icons.logout),
            onPressed: () =>
                _handleLogout(context),
          ),
        ],
      ),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          final user = authProvider.user;

          if (user == null) {
            return const Center(
              child: Text('No user data available'),
            );
          }

          return ListView(
            children: [
              // Profile Header
              Container(
                padding: const EdgeInsets.all(16),
                color: Colors.blue.shade50,
                child: Column(
                  children: [
                    if (user.avatarUrl != null)
                      CircleAvatar(
                        radius: 50,
                        backgroundImage: NetworkImage(user.avatarUrl!),
                      )
                    else
                      CircleAvatar(
                        radius: 50,
                        child: Text(user.name[0].toUpperCase()),
                      ),
                    const SizedBox(height: 16),
                    Text(
                      user.name,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    Text(user.email),
                    const SizedBox(height: 8),
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
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _StatCard(
                      label: 'Reports',
                      value: '${user.totalReports}',
                    ),
                    _StatCard(
                      label: 'Approved',
                      value: '${user.approvedReports}',
                    ),
                    _StatCard(
                      label: 'Level',
                      value: '${user.currentLevel}',
                    ),
                    _StatCard(
                      label: 'Rank',
                      value: '#${user.rank}',
                    ),
                  ],
                ),
              ),

              // Detailed Info
              _InfoSection(
                title: 'Personal Information',
                children: [
                  _InfoTile(label: 'Name', value: user.name),
                  _InfoTile(label: 'Email', value: user.email),
                  if (user.phone != null)
                    _InfoTile(label: 'Phone', value: user.phone!),
                  if (user.bio != null)
                    _InfoTile(label: 'Bio', value: user.bio!),
                ],
              ),

              // Achievements
              _InfoSection(
                title: 'Achievements',
                children: [
                  if (user.badges.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Wrap(
                        spacing: 8,
                        children: user.badges
                            .map((badge) => Chip(label: Text(badge)))
                            .toList(),
                      ),
                    )
                  else
                    const Padding(
                      padding: EdgeInsets.all(16),
                      child: Text('No badges yet'),
                    ),
                ],
              ),

              // Account Actions
              _InfoSection(
                title: 'Account',
                children: [
                  ListTile(
                    title: const Text('Email Verified'),
                    trailing: Icon(
                      authProvider.isEmailVerified
                          ? Icons.check_circle
                          : Icons.error,
                      color: authProvider.isEmailVerified
                          ? Colors.green
                          : Colors.red,
                    ),
                  ),
                  ListTile(
                    title: const Text('Joined'),
                    subtitle: Text(
                      _formatDate(user.createdAt),
                    ),
                  ),
                ],
              ),
            ],
          );
        },
      ),
    );
  }

  void _handleLogout(BuildContext context) {
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

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;

  const _StatCard({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          value,
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        Text(label),
      ],
    );
  }
}

class _InfoSection extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _InfoSection({required this.title, required this.children});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium,
          ),
        ),
        ...children,
      ],
    );
  }
}

class _InfoTile extends StatelessWidget {
  final String label;
  final String value;

  const _InfoTile({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      title: Text(label),
      subtitle: Text(value),
    );
  }
}
```

### 3. Protected Screen with Auth Check

```dart
class ProtectedScreen extends StatefulWidget {
  const ProtectedScreen({super.key});

  @override
  State<ProtectedScreen> createState() => _ProtectedScreenState();
}

class _ProtectedScreenState extends State<ProtectedScreen> {
  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  void _checkAuth() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      
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
      appBar: AppBar(title: const Text('Protected Screen')),
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, _) {
          // Double-check authentication
          if (!authProvider.isAuthenticated) {
            return const Center(
              child: Text('Not authenticated'),
            );
          }

          final user = authProvider.user;
          if (user == null) {
            return const Center(
              child: CircularProgressIndicator(),
            );
          }

          return Center(
            child: Text('Welcome, ${user.name}'),
          );
        },
      ),
    );
  }
}
```

### 4. Managing User Preferences

```dart
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  late SessionManager _sessionManager;
  String? _selectedTheme;
  String? _selectedLanguage;

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
      _selectedTheme = theme;
      _selectedLanguage = language;
    });
  }

  Future<void> _saveThemePreference(String value) async {
    await _sessionManager.setPreference('theme', value);
    setState(() => _selectedTheme = value);
  }

  Future<void> _saveLanguagePreference(String value) async {
    await _sessionManager.setPreference('language', value);
    setState(() => _selectedLanguage = value);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          ListTile(
            title: const Text('Theme'),
            subtitle: Text(_selectedTheme ?? 'Loading...'),
            trailing: DropdownButton<String>(
              value: _selectedTheme,
              onChanged: _saveThemePreference,
              items: const [
                DropdownMenuItem(value: 'light', child: Text('Light')),
                DropdownMenuItem(value: 'dark', child: Text('Dark')),
                DropdownMenuItem(value: 'system', child: Text('System')),
              ],
            ),
          ),
          ListTile(
            title: const Text('Language'),
            subtitle: Text(_selectedLanguage ?? 'Loading...'),
            trailing: DropdownButton<String>(
              value: _selectedLanguage,
              onChanged: _saveLanguagePreference,
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

## Key Points to Remember

1. **Always check `isInitialized`** before showing authenticated content
2. **Use `Consumer<AuthProvider>`** to listen to auth state changes
3. **Access user data via `authProvider.user`** - it's always in sync
4. **Use SessionManager directly** for preferences and advanced operations
5. **Handle profile completion requirement** - redirect to profile completion if needed
6. **Always handle loading states** with proper UI feedback
7. **Validate input** before calling auth methods
8. **Show user-friendly error messages** from `authProvider.errorMessage`
9. **Use refreshProfile()** when you need fresh data from server
10. **Never hardcode navigation routes** - always check auth state

## Testing Auth Flows

### Test Checklist
- [ ] Login with valid credentials
- [ ] Login with invalid credentials shows error
- [ ] Remember me stores email preference
- [ ] Profile loads on authenticated screens
- [ ] Logout clears all data
- [ ] App restart restores session
- [ ] Token refresh works on 401
- [ ] Profile completion is enforced
- [ ] Session expiry is respected
- [ ] Device ID is tracked correctly
