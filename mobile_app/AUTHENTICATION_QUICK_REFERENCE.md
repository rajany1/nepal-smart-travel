# Quick Reference Guide - Authentication System

## 🚀 Quick Start

### 1. App Initialization (Already Done in main.dart)
```dart
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const NepalSmartTravelApp());
}

// AuthInitializationWrapper automatically initializes auth on app startup
```

### 2. Use AuthProvider in Widgets
```dart
Consumer<AuthProvider>(
  builder: (context, authProvider, _) {
    if (!authProvider.isInitialized) return LoadingScreen();
    if (!authProvider.isAuthenticated) return LoginScreen();
    
    // Access user data
    print(authProvider.user?.name);
    return HomeScreen();
  }
)
```

### 3. Access User Data Anywhere
```dart
// Current user
final user = context.read<AuthProvider>().user;

// User level name
final level = context.read<AuthProvider>().userLevelName;

// User display name
final name = context.read<AuthProvider>().userDisplayName;

// Check profile completion
final needsProfile = context.read<AuthProvider>().isProfileCompletionRequired;
```

## 📋 Common Operations

### Login
```dart
final success = await Provider.of<AuthProvider>(context, listen: false)
  .login(email, password, rememberMe: true);
```

### Logout
```dart
await Provider.of<AuthProvider>(context, listen: false).logout();
Navigator.pushReplacementNamed(context, '/login');
```

### Update Profile
```dart
await Provider.of<AuthProvider>(context, listen: false)
  .updateProfile({'name': 'New Name', 'bio': 'New Bio'});
```

### Refresh User Data
```dart
await Provider.of<AuthProvider>(context, listen: false).refreshProfile();
```

### Verify Email
```dart
final success = await Provider.of<AuthProvider>(context, listen: false)
  .verifyEmail(otpCode);
```

### Manage Preferences
```dart
final sessionManager = SessionManager.instance;

// Set preference
await sessionManager.setPreference('theme', 'dark');

// Get preference
final theme = await sessionManager.getPreference('theme', 'light');
```

## 🔍 Check Auth States

```dart
// Is auth initialized?
authProvider.isInitialized

// Is user authenticated?
authProvider.isAuthenticated

// Is email verified?
authProvider.isEmailVerified

// Does profile need completion?
authProvider.requiresProfileCompletion
authProvider.isProfileCompletionRequired

// Is loading?
authProvider.isLoading

// Any errors?
authProvider.errorMessage
```

## 📦 User Data Available

```dart
User user = authProvider.user!;

// Basic
user.id              // "uuid"
user.name            // "John Doe"
user.email           // "john@example.com"
user.phone           // "+977..."
user.avatarUrl       // "https://..."
user.bio             // "Bio text"

// Experience
user.totalXp         // 1500
user.currentLevel    // 5
user.levelName       // "Contributor"

// Contributions
user.totalReports    // 10
user.approvedReports // 8
user.approvalRate    // 0.8

// Status
user.status          // "active"
user.role            // "user"
user.profileCompleted // true
user.rank            // 42

// Achievements
user.badges          // ["badge1", "badge2"]
user.expertiseRegions // ["Kathmandu", "Pokhara"]
user.lastContributionAt // DateTime
user.createdAt       // DateTime
```

## 🛡️ Auth Status Check Pattern

```dart
@override
Widget build(BuildContext context) {
  return Consumer<AuthProvider>(
    builder: (context, authProvider, _) {
      // 1. Check initialization
      if (!authProvider.isInitialized) {
        return const Center(child: CircularProgressIndicator());
      }

      // 2. Check authentication
      if (!authProvider.isAuthenticated) {
        return const LoginScreen();
      }

      // 3. Check profile completion
      if (authProvider.isProfileCompletionRequired) {
        return const ProfileCompletionScreen();
      }

      // 4. Show protected content
      return const HomeScreen();
    },
  );
}
```

## 🎯 Navigation Pattern

```dart
// After successful login
if (authProvider.isProfileCompletionRequired) {
  Navigator.pushReplacementNamed(context, '/profile-completion');
} else {
  Navigator.pushReplacementNamed(context, '/home');
}

// After logout
await authProvider.logout();
Navigator.pushReplacementNamed(context, '/login');

// On app startup - AuthInitializationWrapper handles this automatically
```

## 📊 Session Info

```dart
final sessionManager = SessionManager.instance;

// Is session active?
final isActive = await sessionManager.isSessionActive();

// When does it expire?
final expiry = await sessionManager.getSessionExpiry();

// How much time left?
final remaining = await sessionManager.getSessionTimeRemaining();

// Get debug summary
final summary = await sessionManager.getSessionSummary();
print(summary);
```

## 🔄 Token Management

```dart
final sessionManager = SessionManager.instance;

// Get access token
final token = await sessionManager.getAccessToken();

// Get refresh token
final refreshToken = await sessionManager.getRefreshToken();

// Check if expired
final isExpired = !(await sessionManager.isSessionActive());

// Logout (clears tokens)
await sessionManager.clearSession();
```

## ⚠️ Error Handling

```dart
Consumer<AuthProvider>(
  builder: (context, authProvider, _) {
    if (authProvider.errorMessage != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(authProvider.errorMessage!),
          backgroundColor: Colors.red,
        ),
      );
      authProvider.clearError(); // Clear after showing
    }
    // ... rest of widget
  },
)
```

## 🧪 Test Scenarios

```dart
// Test 1: Login
test('login with valid credentials', () async {
  final success = await authProvider.login(
    'test@example.com',
    'password123'
  );
  expect(success, true);
  expect(authProvider.isAuthenticated, true);
  expect(authProvider.user, isNotNull);
});

// Test 2: Session restoration
test('session restored on app restart', () async {
  await authProvider.initializeAuth();
  expect(authProvider.isAuthenticated, true);
  expect(authProvider.user, isNotNull);
});

// Test 3: Logout
test('logout clears all data', () async {
  await authProvider.logout();
  expect(authProvider.isAuthenticated, false);
  expect(authProvider.user, null);
});
```

## 📝 Best Practices

✅ **DO:**
- Always check `isInitialized` before using auth data
- Use `Consumer<AuthProvider>` for auth-dependent UI
- Call `refreshProfile()` when data might be stale
- Handle loading states with proper UI feedback
- Show user-friendly error messages
- Use SessionManager for preferences

❌ **DON'T:**
- Directly access token from storage
- Assume user is authenticated without checking
- Make API calls before auth initialization
- Ignore loading states
- Mix old and new token management code
- Store sensitive data in regular SharedPreferences

## 🔗 Files Reference

| File | Purpose |
|------|---------|
| `session_manager.dart` | Session & token management |
| `auth_provider.dart` | Auth state & operations |
| `api_client.dart` | API integration |
| `main.dart` | App initialization |
| `AUTHENTICATION_SYSTEM.md` | Full documentation |
| `AUTHENTICATION_INTEGRATION_GUIDE.md` | Integration examples |
| `AUTHENTICATION_MIGRATION_GUIDE.md` | Migration help |

## 💡 Tips & Tricks

### Get User Avatar Safely
```dart
final avatarUrl = authProvider.user?.avatarUrl ?? '';
if (avatarUrl.isNotEmpty) {
  Image.network(avatarUrl);
} else {
  CircleAvatar(child: Text(authProvider.user?.name[0] ?? ''));
}
```

### Format Last Contribution Date
```dart
final lastContrib = authProvider.user?.lastContributionAt;
if (lastContrib != null) {
  Text(DateFormat('MMM dd, yyyy').format(lastContrib));
}
```

### Check User Permissions
```dart
final isAdmin = authProvider.user?.role == 'admin';
final isModerator = authProvider.user?.role == 'moderator';
final isRegularUser = authProvider.user?.role == 'user';
```

### Get User Progress
```dart
final progress = authProvider.user?.levelProgress ?? 0.0;
LinearProgressIndicator(value: progress);
```

### Auto-logout on Session Expiry
```dart
Timer.periodic(Duration(minutes: 5), (timer) async {
  final isActive = await SessionManager.instance.isSessionActive();
  if (!isActive) {
    authProvider.logout();
    Navigator.pushReplacementNamed(context, '/login');
  }
});
```

## 🆘 Troubleshooting Quick Fixes

| Problem | Solution |
|---------|----------|
| "user is null" | Check `isInitialized` and `isAuthenticated` first |
| "token not sent" | Verify ApiClient is using SessionManager |
| "session not restored" | Ensure AuthInitializationWrapper is used |
| "can't find user data" | Call `refreshProfile()` to fetch from server |
| "token expired" | Auto-handled by AuthInterceptor |
| "preferences not saving" | Use SessionManager instead of SharedPreferences |

## 📞 Support

- 📖 Read: `AUTHENTICATION_SYSTEM.md`
- 💻 Examples: `AUTHENTICATION_INTEGRATION_GUIDE.md`
- 🔄 Migrate: `AUTHENTICATION_MIGRATION_GUIDE.md`
- 📋 Summary: `AUTHENTICATION_IMPLEMENTATION_SUMMARY.md`
