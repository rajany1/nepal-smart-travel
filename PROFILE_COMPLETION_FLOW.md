# Profile Completion Flow - Implementation Guide

## Overview

This document describes the complete Profile Completion Flow implementation for the Nepal Smart Travel app. Users are now required to complete their profile (add bio and optionally phone) before accessing any app features beyond the home page.

## Architecture

### Flow Diagram

```
Registration/Login
    ↓
Check profile_completed flag
    ↓
If FALSE → Profile Completion Screen (REQUIRED)
    ↓
Complete Form & Submit
    ↓
Backend validates & updates profile_completed = true
    ↓
Redirect to Home Screen (FULL ACCESS)

If TRUE → Direct to Home Screen (FULL ACCESS)
```

---

## Backend Implementation

### 1. Database Changes

**Migration File**: `2026_05_18_000001_add_profile_completed_to_users_table.php`

- Added `profile_completed` boolean column to `users` table
- Default value: `false`
- Users start with incomplete profiles

```sql
ALTER TABLE users ADD profile_completed BOOLEAN DEFAULT FALSE
```

### 2. User Model Updates

**File**: `app/Models/User.php`

- Added `profile_completed` to `$fillable` array
- Added `profile_completed` to `casts` as `'boolean'`

### 3. ProfileCompleted Middleware

**File**: `app/Http/Middleware/ProfileCompleted.php`

- Checks if user has completed their profile
- Returns `403 Forbidden` if profile is incomplete
- Includes list of missing fields in response
- Response Code: `PROFILE_INCOMPLETE`

**Usage in routes.php**:
```php
Route::middleware('profile.completed')->group(function () {
    // Protected endpoints
    Route::post('/alerts', [AlertController::class, 'store']);
});
```

### 4. AuthController Endpoints

#### ✅ New Endpoints:

**POST `/auth/complete-profile`**
- Requires authentication
- Request body:
  ```json
  {
    "bio": "string (required, min: 10 chars, max: 500)",
    "avatar": "string (optional URL)",
    "phone": "string (optional)"
  }
  ```
- Response:
  ```json
  {
    "success": true,
    "message": "Profile completed successfully",
    "data": {
      "user_id": "...",
      "profile_completed": true,
      ...
    }
  }
  ```

**GET `/auth/check-profile-status`**
- Requires authentication
- Returns current profile completion status
- Response:
  ```json
  {
    "profile_completed": boolean,
    "missing_fields": ["bio", "phone"] // Only includes actually missing fields
  }
  ```

#### ✅ Updated Endpoints:

**POST `/auth/login`** and **POST `/auth/register`**
- Now returns `profile_completed` flag in response

**GET `/users/me`**
- Now includes `profile_completed` field

### 5. Backend Validation

- **Bio**: Required, minimum 10 characters, maximum 500 characters
- **Phone**: Optional but validates if provided (7+ characters)
- **Avatar**: Optional, expects URL string

---

## Frontend Implementation

### 1. User Model Updates

**File**: `lib/core/models/user.dart`

- Added `profileCompleted` field (boolean)
- Parses `profile_completed` from API responses
- Defaults to `false` for new users

### 2. API Client Endpoints

**File**: `lib/core/api/api_client.dart`

```dart
// Complete the user profile
Future<Response> completeProfile({
  required String bio,
  String? avatar,
  String? phone,
}) async { ... }

// Check profile completion status
Future<Response> checkProfileStatus() async { ... }
```

### 3. ProfileCompletionProvider

**File**: `lib/providers/profile_completion_provider.dart`

State Management for profile completion:
- `isLoading`: Loading state during submission
- `errorMessage`: Validation or submission errors
- `profileCompleted`: Current completion status
- `missingFields`: Fields that need completion

**Key Methods**:
- `completeProfile()`: Submits form, validates, updates status
- `checkStatus()`: Fetches current status from backend
- `updateFromUser()`: Updates state from UserModel
- `reset()`: Clears all state

**Validation Logic**:
- Bio minimum 10 characters
- Provides user-friendly error messages
- Shows missing fields for UI guidance

### 4. ProfileCompletionScreen

**File**: `lib/features/profile/profile_completion_screen.dart`

User-friendly form screen with:
- ✅ Enforced profile completion (no back button)
- Bio input field (required, min 10 chars)
- Phone input field (optional)
- Avatar selection (optional)
- Error message display
- Loading state during submission
- Info messages about data usage

**Features**:
- Pre-fills with existing data
- Shows real-time character count
- Validates before submission
- Shows network errors gracefully
- Prevents re-submission while loading

### 5. Navigation Guards

**File**: `lib/main.dart`

**_ProfileCompletionGuard Widget**:
- Wraps all protected screens
- Watches AuthProvider for profile completion status
- Auto-redirects to `/profile-completion` if required
- Shows loading indicator during redirect

**Route Protection**:
```dart
'/home': (context) => const _ProfileCompletionGuard(child: HomeScreen()),
'/alerts': (context) => const _ProfileCompletionGuard(child: AlertsScreen()),
'/profile': (context) => const _ProfileCompletionGuard(child: ProfileScreen()),
// ... all other protected screens
```

### 6. Updated Auth Screens

**LoginScreen** (`lib/features/auth/login_screen.dart`):
- Checks `user.profileCompleted` after login
- Redirects to `/profile-completion` if incomplete
- Redirects to `/home` if complete

**RegisterScreen** (`lib/features/auth/register_screen.dart`):
- Redirects to `/profile-completion` after successful registration
- New users always complete profile on first login

### 7. AuthProvider Enhancements

**File**: `lib/providers/auth_provider.dart`

New getter:
```dart
bool get isProfileCompletionRequired => 
  _isAuthenticated && _user != null && !_user!.profileCompleted;
```

---

## User Flow

### First-Time User (Registration)
1. User fills registration form
2. Account created with `profile_completed = false`
3. Automatically redirected to Profile Completion Screen
4. User fills bio and optional info
5. Backend validates and updates `profile_completed = true`
6. User redirected to Home Screen with full access

### Returning User with Incomplete Profile
1. User logs in with existing account
2. System checks `profile_completed` flag
3. If `false` → Redirected to Profile Completion Screen
4. User completes profile
5. User redirected to Home Screen

### User with Complete Profile
1. User logs in
2. System checks `profile_completed` flag
3. If `true` → Direct to Home Screen
4. Full app access granted

### Attempting to Access Protected Screens
1. User tries to navigate to `/alerts`, `/profile`, `/reports`, etc.
2. ProfileCompletionGuard checks profile status
3. If incomplete → Auto-redirect to Profile Completion Screen
4. If complete → Show requested screen

---

## Forced Redirect Mechanism

The app uses a **two-layer protection system**:

### Layer 1: Route Middleware (Backend)
- Endpoints marked with `profile.completed` middleware
- Returns 403 Forbidden for incomplete profiles
- Prevents API misuse

### Layer 2: Navigation Guard (Frontend)
- _ProfileCompletionGuard wraps protected screens
- Prevents UI access to incomplete profiles
- Works even with direct route access

This ensures users **cannot bypass** the profile completion flow even with:
- Direct URL/route access
- Browser back button
- Direct API calls

---

## Error Handling

### Frontend Validation Errors
- Bio too short: "Bio must be at least 10 characters"
- Bio empty: "Bio cannot be empty"
- Network errors: "Network error. Please check your connection."
- Server errors: "Failed to complete profile"

### Backend Validation Errors
- Invalid bio length: 422 Validation error
- Missing required fields: 422 Validation error
- Database errors: 500 Internal Server Error

### Graceful Degradation
- All errors shown in UI with clear messaging
- Users can retry immediately
- No silent failures

---

## Security Considerations

✅ **Implemented Security**:
1. Backend middleware validates profile completion
2. Cannot be bypassed with direct API calls
3. Frontend provides additional UX protection
4. Profile completion state persisted in database
5. Validation on both frontend and backend
6. No sensitive data in profile completion endpoint

⚠️ **Notes**:
- Middleware runs on authenticated requests only
- Unauthenticated users can't access protected endpoints
- Profile completion is non-reversible (by design)
- Admin override not implemented (can be added if needed)

---

## API Response Examples

### Complete Profile - Success
```json
{
  "success": true,
  "message": "Profile completed successfully",
  "data": {
    "user_id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "9841234567",
    "avatar_url": "https://...",
    "bio": "I love exploring Nepal",
    "profile_completed": true
  }
}
```

### Complete Profile - Validation Error
```json
{
  "message": "The bio field is required. (and 1 more error)",
  "errors": {
    "bio": ["The bio field is required."]
  }
}
```

### Protected Endpoint - Profile Incomplete
```json
{
  "success": false,
  "message": "Profile completion required",
  "code": "PROFILE_INCOMPLETE",
  "profile_completed": false,
  "missing_fields": ["bio"]
}
```

---

## Configuration

### Environment Setup

**Backend (.env)**:
- No special configuration required
- Uses default Laravel settings

**Frontend (pubspec.yaml)**:
- Ensure `provider` package is available
- Ensure `dio` for API calls

### Database Migration

Run migration on deployment:
```bash
cd backend
php artisan migrate
```

### Frontend Build

No special build steps required. Standard Flutter build:
```bash
flutter pub get
flutter run
```

---

## Testing Checklist

- [ ] New user registration redirects to profile completion
- [ ] Incomplete profile users redirected to profile completion on login
- [ ] Profile completion form validates bio (min 10 chars)
- [ ] Form pre-fills with existing phone/bio data
- [ ] Network error handling works
- [ ] After completion, redirects to home successfully
- [ ] Refresh doesn't bypass profile completion
- [ ] Cannot navigate directly to other screens before completion
- [ ] Backend middleware rejects API calls for incomplete profiles
- [ ] Existing users with complete profiles not affected
- [ ] Profile completion endpoint validates input

---

## Future Enhancements

1. **Admin Override**: Allow admins to mark profiles as complete
2. **Profile Expiry**: Require re-completion after certain period
3. **Additional Fields**: Expand required profile fields
4. **Social Verification**: Verify users via social media
5. **Photo Upload**: Direct image upload instead of URL
6. **Profile Progress**: Show completion percentage
7. **Guided Onboarding**: Step-by-step profile setup wizard

---

## Troubleshooting

### Users Not Redirected to Profile Completion
**Solution**: Ensure ProfileCompletionGuard is wrapping the route

### Profile Complete But Still Redirected
**Solution**: 
- Verify `profile_completed` field exists in database
- Check migration ran successfully
- Clear app cache and reinstall

### Middleware Not Working
**Solution**:
- Verify middleware registered in `bootstrap/app.php`
- Check route has correct middleware applied
- Test with Postman to verify backend

### Form Validation Not Working
**Solution**:
- Verify validator in ProfileCompletionProvider
- Check form key is properly set
- Test input values directly

---

## Support

For issues or questions about the Profile Completion Flow:
1. Check troubleshooting section above
2. Review backend logs
3. Check Flutter debugger output
4. Verify all migrations ran successfully
