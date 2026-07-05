# Authentication System Migration Guide

## Overview
This guide helps you migrate existing screens to use the new enhanced authentication system.

## What Changed

### Before (Old System)
```dart
// Limited auth state
if (await authProvider.checkAuthStatus()) {
  // User was logged in, but minimal user data was available
  print(authProvider.user?.name);
}
```

### After (New System)
```dart
// Rich auth state with full user data
await authProvider.initializeAuth(); // Called automatically on app startup
if (authProvider.isAuthenticated) {
  // Full user data is available and cached
  print(authProvider.user?.totalXp);
  print(authProvider.user?.currentLevel);
  print(authProvider.userLevelName);
}
```

## Migration Steps

### Step 1: Update App Initialization (main.dart)
**No changes needed** - we already updated main.dart to use `AuthInitializationWrapper`

### Step 2: Update Login Screens

#### Before
```dart
Future<bool> login(String email, String password) async {
  _isLoading = true;
  notifyListeners();
  
  try {
    final response = await _api.login(email: email, password: password);
    // ... handle response
    return true;
  } catch (e) {
    return false;
  }
}
```

#### After
```dart
// No changes needed! The enhanced AuthProvider handles this
// Just use:
final success = await authProvider.login(email, password, rememberMe: true);
```

### Step 3: Update Profile Screens

#### Before
```dart
// Limited user data available
Text(user.name),
Text(user.email),
```

#### After
```dart
// Access full user data
Text(user.name),
Text(user.email),
Text('Level: ${authProvider.userLevelName}'),
Text('XP: ${user.totalXp}'),
Text('Reports: ${user.totalReports}'),
```

### Step 4: Update Logout Functionality

#### Before
```dart
await authProvider.logout();
await _api.clearToken(); // Manual token clearing
```

#### After
```dart
// SessionManager handles everything
await authProvider.logout(); // Clears tokens, session, and all data
```

### Step 5: Update Protected Routes

#### Before
```dart
@override
void initState() {
  super.initState();
  WidgetsBinding.instance.addPostFrameCallback((_) {
    if (!authProvider.isAuthenticated) {
      Navigator.pushReplacementNamed(context, '/login');
    }
  });
}
```

#### After
```dart
// Main.dart already handles this with AuthInitializationWrapper
// No changes needed in individual screens!
// But you can still check for safety:
if (!context.read<AuthProvider>().isAuthenticated) {
  Navigator.pushReplacementNamed(context, '/login');
}
```

### Step 6: Add Profile Refresh Where Needed

#### New Feature
```dart
// When user might have stale data, refresh from server
onRefresh: () => context.read<AuthProvider>().refreshProfile(),

// Or in initState
@override
void initState() {
  super.initState();
  // Optionally refresh profile on screen open
  Future.microtask(
    () => context.read<AuthProvider>().refreshProfile(),
  );
}
```

## Common Migration Issues

### Issue 1: "User data is null"
**Before:**
```dart
print(authProvider.user?.name); // Might be null
```

**After:**
```dart
// Check isInitialized first
if (authProvider.isInitialized && authProvider.isAuthenticated) {
  print(authProvider.user!.name); // Safe to use
}
```

### Issue 2: "Token not being sent"
**Before:** Had to manually add token to headers
**After:** SessionManager + AuthInterceptor handles automatically
```dart
// No changes needed! AuthInterceptor does this automatically
```

### Issue 3: "Session not persisting on app restart"
**Before:** Manual session restoration code needed
**After:** Automatic!
```dart
// AuthInitializationWrapper handles this
// No code needed in screens
```

### Issue 4: "Need to track user preferences"
**Before:** No built-in preference storage
**After:** Use SessionManager
```dart
final sessionManager = SessionManager.instance;
await sessionManager.setPreference('theme', 'dark');
final theme = await sessionManager.getPreference('theme', 'light');
```

## Checklist: Migrating a Screen

- [ ] Remove manual `checkAuthStatus()` calls
- [ ] Remove manual token checking
- [ ] Update UI to show full user data
- [ ] Add profile refresh button if needed
- [ ] Use `Consumer<AuthProvider>` for auth-dependent UI
- [ ] Check `isInitialized` before using auth data
- [ ] Test login/logout flow
- [ ] Test app restart (session restoration)
- [ ] Test profile update
- [ ] Test error handling

## Examples of Migrated Screens

### Profile Screen Migration

#### Before
```dart
class ProfileScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, _) {
        if (!authProvider.isAuthenticated) {
          return Center(child: Text('Not authenticated'));
        }
        
        return Column(
          children: [
            Text(authProvider.user?.name ?? ''),
            Text(authProvider.user?.email ?? ''),
            // Limited data
          ],
        );
      },
    );
  }
}
```

#### After
```dart
class ProfileScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
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
            Text(user.name),
            Text(user.email),
            Text('Level: ${authProvider.userLevelName}'),
            Text('XP: ${user.totalXp}'),
            Text('Reports: ${user.totalReports}'),
            Text('Rank: #${user.rank}'),
            Text('Approval Rate: ${(user.approvalRate * 100).toStringAsFixed(1)}%'),
            // Rich user data available!
          ],
        );
      },
    );
  }
}
```

### Reports Screen Migration

#### Before
```dart
Future<List<Report>> _getReports() async {
  final token = await _api.getToken();
  if (token == null) throw Exception('Not authenticated');
  return await _api.getReports();
}
```

#### After
```dart
Future<List<Report>> _getReports() async {
  final authProvider = context.read<AuthProvider>();
  if (!authProvider.isAuthenticated) {
    throw Exception('Not authenticated');
  }
  // Token is automatically added by AuthInterceptor
  return await _api.getReports();
}
```

### Home Screen Migration

#### Before
```dart
class HomeScreen extends StatefulWidget {
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  void _checkAuth() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      if (!authProvider.isAuthenticated) {
        Navigator.pushReplacementNamed(context, '/login');
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // ...
    );
  }
}
```

#### After
```dart
// No initState needed! AuthInitializationWrapper handles navigation
class HomeScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // Use user data with confidence
      // AuthProvider is already initialized and authenticated
    );
  }
}
```

## Testing Migration

### Test Cases
1. **Login persistence**
   - Login to app
   - Restart app
   - User should still be logged in with same data

2. **Session expiry**
   - Set token expiration to 1 minute
   - Wait for expiration
   - Make API request
   - Token should auto-refresh

3. **Logout**
   - Login to app
   - Logout
   - Restart app
   - Should be on login screen

4. **Profile update**
   - Update profile information
   - Data should be immediately available in state
   - Refresh profile should fetch latest from server

5. **Error handling**
   - Test with invalid credentials
   - Test with network error
   - Test with expired token
   - All should show appropriate error messages

## Rollback Plan

If you need to rollback:
1. The old methods are still available (backward compatible)
2. Old `_api.getToken()` and `_api.setToken()` still work
3. No breaking changes to UserModel
4. Just remove AuthInitializationWrapper if needed

## Performance Improvements

With the new system:
- **Faster initial load**: Session is restored from cache (no API call)
- **Fewer API calls**: User data is cached and reused
- **Better memory**: In-memory cache + secure storage only
- **Automatic token refresh**: No more "Unauthorized" errors
- **Preferences cached**: User preferences are in-memory

## Next Steps

1. Update your login screens to use new error handling
2. Add profile refresh buttons where needed
3. Display full user data in profile screens
4. Use SessionManager for preferences
5. Test all auth flows thoroughly

## Support

If you encounter issues during migration:
1. Check the AUTHENTICATION_SYSTEM.md documentation
2. Review AUTHENTICATION_INTEGRATION_GUIDE.md for examples
3. Check console logs for detailed error messages
4. Verify SessionManager initialization
5. Ensure AuthProvider is in the widget tree
