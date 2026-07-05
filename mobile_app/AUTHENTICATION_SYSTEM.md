# Enhanced Authentication System Documentation

## Overview
This document describes the improved authentication system for the Nepal Smart Travel mobile app. The new system provides:

- **Persistent Session Management**: User sessions are automatically restored on app startup
- **Full User Data in State**: Complete user information is cached and accessible throughout the app
- **Centralized Token Management**: All token operations go through SessionManager
- **Better Error Handling**: Improved error messages and logging
- **Auto Token Refresh**: Automatic token refresh on 401 responses
- **User Preferences Storage**: Store and retrieve user preferences (theme, language, etc.)
- **Session Tracking**: Track login time, session expiry, and device information

## Architecture

### Core Components

#### 1. **SessionManager** (`core/services/session_manager.dart`)
Centralized service for all session-related operations:

**Token Management:**
- `setAccessToken(token, expiresInSeconds?)` - Store access token with optional expiration
- `getAccessToken()` - Retrieve access token (returns null if expired)
- `setRefreshToken(token)` - Store refresh token
- `getRefreshToken()` - Retrieve refresh token
- `clearSession()` - Clear all session data

**User Data Management:**
- `setUser(user)` - Persist user data with in-memory caching
- `getUser()` - Get cached user data (fast in-memory access)
- `updateUserField(field, value)` - Update single user field
- `clearUser()` - Clear user data

**Session Management:**
- `isSessionActive()` - Check if session is active and not expired
- `getSessionExpiry()` - Get session expiration time
- `getSessionTimeRemaining()` - Get remaining session duration
- `restoreSession()` - Restore session from storage on app startup

**User Preferences:**
- `setUserPreferences(preferences)` - Store preferences as JSON
- `getUserPreferences()` - Get all preferences
- `setPreference(key, value)` - Set single preference
- `getPreference(key, defaultValue?)` - Get single preference

**Device & Tracking:**
- `setDeviceId(deviceId)` - Store device ID for tracking
- `getDeviceId()` - Retrieve device ID
- `setLastLogin()` - Track login timestamp
- `getLastLogin()` - Get last login time

**Debugging:**
- `getSessionSummary()` - Get complete session summary for debugging

#### 2. **Enhanced AuthProvider** (`providers/auth_provider.dart`)
State management for authentication with full user data:

**State Properties:**
- `user` - Current authenticated user with full data
- `isAuthenticated` - Whether user is logged in
- `isInitialized` - Whether auth initialization is complete
- `isLoading` - Loading state for async operations
- `errorMessage` - Last error message
- `isEmailVerified` - Email verification status
- `requiresProfileCompletion` - Profile completion status

**Key Methods:**

```dart
// Initialize on app startup - restores session from storage
Future<void> initializeAuth()

// Check auth status and refresh user data from server
Future<void> checkAuthStatus()

// Login with optional "remember me" feature
Future<bool> login(email, password, {rememberMe})

// Register new user
Future<bool> register({
  name, email, phone, password, passwordConfirmation
})

// Logout and clear session
Future<void> logout()

// Update user profile
Future<void> updateProfile(data)

// Refresh user profile from server
Future<void> refreshProfile()

// Verify email with OTP
Future<bool> verifyEmail(otp)

// Resend verification email
Future<bool> resendVerificationEmail(email)

// Send password reset
Future<bool> sendPasswordReset(email)

// Reset password with token
Future<bool> resetPassword(token, newPassword)
```

**Helper Getters:**
- `userDisplayName` - User's display name
- `userLevelName` - User's level name (Explorer, Contributor, etc.)
- `isProfileCompletionRequired` - Check if profile completion is needed

#### 3. **API Client Updates** (`core/api/api_client.dart`)
Now uses SessionManager for token management:

- All token operations delegated to SessionManager
- AuthInterceptor uses SessionManager for token handling
- Automatic token refresh on 401 responses
- Better error handling and logging

## Flow Diagrams

### App Initialization Flow
```
App Start
    ↓
AuthInitializationWrapper (initializeAuth)
    ↓
SessionManager.restoreSession()
    ↓
├─ Session Active? 
│   ├─ YES → Load cached user → Navigate to home/profile-completion
│   └─ NO → Navigate to login
└─ Error → Navigate to login
```

### Login Flow
```
User submits login
    ↓
AuthProvider.login(email, password)
    ↓
API: POST /auth/login
    ↓
Parse AuthResponse
    ↓
SessionManager.setAccessToken(token, expiresIn)
SessionManager.setRefreshToken(refreshToken)
SessionManager.setUser(userData)
SessionManager.setLastLogin()
SessionManager.setPreference('rememberEmail', email)
    ↓
Fetch full profile in background
    ↓
Navigate based on profile completion status
```

### Token Refresh Flow
```
API Request
    ↓
AuthInterceptor.onRequest()
    ↓
Add "Authorization: Bearer {token}" header
    ↓
Request executed
    ↓
Response 401 (Unauthorized)?
    ↓
├─ YES → AuthInterceptor.onError()
│   ├─ Get refresh token from SessionManager
│   ├─ POST /auth/refresh
│   ├─ Store new access token
│   ├─ Retry original request with new token
│   └─ Return response
│
└─ NO → Return response directly
```

## User Data State Structure

The `UserModel` contains:

```dart
class UserModel {
  final String id;                    // Unique user ID
  final String name;                  // User's full name
  final String email;                 // Email address
  final String? phone;                // Phone number (optional)
  final String? avatarUrl;            // Profile avatar URL
  final String? bio;                  // User bio
  final int totalXp;                  // Total experience points
  final int currentLevel;             // Current user level
  final String verificationTick;      // Verification status
  final List<String> badges;          // Earned badges
  final List<String> expertiseRegions;// Expertise areas
  final int totalReports;             // Number of reports submitted
  final int approvedReports;          // Number of approved reports
  final double approvalRate;          // Report approval rate
  final int rank;                     // User rank in community
  final DateTime? lastContributionAt;// Last contribution timestamp
  final String status;                // Account status (active, pending, etc.)
  final String role;                  // User role (user, moderator, admin)
  final bool profileCompleted;        // Profile completion status
  final DateTime createdAt;           // Account creation timestamp
}
```

## Usage Examples

### In Widgets

```dart
// Access auth state
Consumer<AuthProvider>(
  builder: (context, authProvider, _) {
    if (!authProvider.isInitialized) {
      return const LoadingScreen();
    }
    
    if (!authProvider.isAuthenticated) {
      return const LoginScreen();
    }
    
    return Column(
      children: [
        Text('Welcome, ${authProvider.userDisplayName}'),
        Text('Level: ${authProvider.userLevelName}'),
        Text('XP: ${authProvider.user?.totalXp}'),
      ],
    );
  }
)
```

### Login with Remember Me

```dart
final success = await Provider.of<AuthProvider>(context, listen: false)
  .login(email, password, rememberMe: true);
```

### Update User Preferences

```dart
final sessionManager = SessionManager.instance;

// Store theme preference
await sessionManager.setPreference('theme', 'dark');

// Retrieve theme preference
final theme = await sessionManager.getPreference('theme', 'light');
```

### Get Session Information

```dart
final sessionManager = SessionManager.instance;

// Check if session is active
final isActive = await sessionManager.isSessionActive();

// Get time remaining
final remaining = await sessionManager.getSessionTimeRemaining();
print('Session expires in: ${remaining?.inMinutes} minutes');

// Get debug info
final summary = await sessionManager.getSessionSummary();
print('Session Summary: $summary');
```

### Manual Profile Refresh

```dart
// When user might be stale, refresh from server
await Provider.of<AuthProvider>(context, listen: false).refreshProfile();
```

## Storage Details

### Secure Storage (FlutterSecureStorage)
- Access Token
- Refresh Token
- User Data (JSON)
- Session Expiry
- Device ID
- Last Login
- User Preferences

### In-Memory Cache
- Current user data (for fast access)
- Session expiry time
- User preferences

## Error Handling

Errors are automatically parsed and converted to user-friendly messages:

- **422**: "Validation failed. Please check your input."
- **401**: "Invalid email or password"
- **404**: "User not found"
- **409**: "Email already registered"
- **Connection errors**: "Unable to connect to server. Check your internet connection."
- **Timeout**: "Connection timeout. Please try again."
- **500**: "Server error. Please try again later."

## Security Considerations

1. **Tokens are stored securely** using FlutterSecureStorage
2. **Session expiry is tracked** to prevent using expired tokens
3. **Automatic token refresh** prevents re-authentication on every request
4. **Session is cleared on logout** removing all sensitive data
5. **In-memory cache** is cleared on app termination (by default)

## Migration Guide

If migrating from the old auth system:

1. Old code using `AuthProvider.user` still works (property preserved)
2. Old token methods still work but now use SessionManager internally
3. New code should use `initializeAuth()` on app startup
4. New code can access full user data and preferences via SessionManager

## Future Enhancements

- Add biometric authentication
- Add social login (Google, Facebook)
- Add multi-factor authentication (MFA)
- Add password change functionality
- Add account deactivation
- Add activity logging
- Add device management (logout other devices)
