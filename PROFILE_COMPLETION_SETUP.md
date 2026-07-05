# Profile Completion Flow - Quick Setup & Verification Guide

## Quick Setup Steps

### Backend Setup (Laravel)

#### Step 1: Run Database Migration
```bash
cd backend
php artisan migrate
```

**What it does**:
- Adds `profile_completed` column to `users` table
- Sets default value to `false`
- All existing users will have `profile_completed = false`

**Verify**:
```bash
php artisan tinker
# Then in tinker:
User::first()->profile_completed  # Should return false
```

#### Step 2: Verify Middleware Registration
Check `bootstrap/app.php` has middleware alias:
```php
$middleware->alias([
    'profile.completed' => \App\Http\Middleware\ProfileCompleted::class,
]);
```

#### Step 3: Test Endpoints with Postman
```
POST /auth/complete-profile
Headers: Authorization: Bearer <token>
Body: {
  "bio": "This is my bio about myself that is definitely long enough",
  "phone": "9841234567"
}

Expected Response:
{
  "success": true,
  "message": "Profile completed successfully",
  "data": { ... }
}
```

### Frontend Setup (Flutter)

#### Step 1: Ensure Providers are Added
In `main.dart`, verify `ProfileCompletionProvider` is added:
```dart
ChangeNotifierProvider(create: (_) => ProfileCompletionProvider()),
```

#### Step 2: Build & Run
```bash
cd mobile_app
flutter pub get
flutter run
```

#### Step 3: Verify Routes are Protected
Try accessing these routes - should redirect to profile completion:
- `/home`
- `/alerts`
- `/profile`
- `/reports`
- `/emergency`

---

## End-to-End Testing

### Test Scenario 1: New User Registration

**Steps**:
1. Open app → Register with email/phone/password
2. Should redirect to Profile Completion Screen
3. Try to go back (should be prevented)
4. Fill bio (test min 10 chars validation)
5. Click Complete Profile
6. Should redirect to Home Screen
7. Verify can access all screens

**Expected**: Full app access after profile completion ✅

### Test Scenario 2: Existing User with Incomplete Profile

**Database Setup** (if testing with existing user):
```bash
php artisan tinker
$user = User::find(1);
$user->update(['profile_completed' => false]);
$user->update(['bio' => null]);
```

**Steps**:
1. Login with existing user credentials
2. Should redirect to Profile Completion Screen
3. Bio field should be empty
4. Phone field should pre-fill if available
5. Fill bio and submit
6. Should redirect to Home
7. Verify profile is complete: `User::find(1)->profile_completed` returns `true`

**Expected**: One-time profile completion required ✅

### Test Scenario 3: User with Complete Profile

**Database Setup**:
```bash
php artisan tinker
$user = User::find(1);
$user->update(['profile_completed' => true, 'bio' => 'Sample bio']);
```

**Steps**:
1. Login with user
2. Should go directly to Home Screen
3. No profile completion required
4. Can access all screens immediately

**Expected**: Direct access to app ✅

### Test Scenario 4: API Protection

**Test Protected Endpoint Without Profile Completion**:
```bash
# With incomplete profile user token
curl -X POST http://localhost:8000/api/v1/alerts \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"title":"Test","description":"Test","severity":"low","type":"road"}'

Expected Response (403):
{
  "success": false,
  "message": "Profile completion required",
  "code": "PROFILE_INCOMPLETE",
  "profile_completed": false,
  "missing_fields": ["bio"]
}
```

**Test Same Endpoint After Profile Completion**:
```bash
# First complete profile via complete-profile endpoint
# Then test alerts endpoint again
# Should return 200 OK and create alert

Expected Response (200):
{
  "success": true,
  "data": { ... }
}
```

**Expected**: Backend properly validates profile completion ✅

### Test Scenario 5: Navigation Guard

**Steps**:
1. Login with incomplete profile user
2. Manually try to navigate to `/home` (e.g., via browser dev tools or deep link)
3. Should be redirected to `/profile-completion`
4. Cannot bypass with back button

**Expected**: Navigation guard prevents direct screen access ✅

### Test Scenario 6: Profile Data Persistence

**Steps**:
1. User completes profile with bio
2. Close and reopen app
3. Login again
4. Check user data shows completed profile
5. Can access home directly

**Expected**: Profile completion state persists ✅

---

## Verification Checklist

### Backend ✓
- [ ] Migration file created: `2026_05_18_000001_add_profile_completed_to_users_table.php`
- [ ] `profile_completed` column exists in users table
- [ ] User model has `profile_completed` in fillable and casts
- [ ] ProfileCompleted middleware exists: `app/Http/Middleware/ProfileCompleted.php`
- [ ] Middleware registered in `bootstrap/app.php`
- [ ] `/auth/complete-profile` endpoint created
- [ ] `/auth/check-profile-status` endpoint created
- [ ] Auth endpoints return `profile_completed` field
- [ ] Protected routes use `profile.completed` middleware
- [ ] Login test shows `profile_completed` in response

### Frontend ✓
- [ ] UserModel has `profileCompleted` field
- [ ] UserModel parses `profile_completed` from JSON
- [ ] ProfileCompletionProvider created
- [ ] ProfileCompletionScreen created
- [ ] ProfileCompletionGuard widget in main.dart
- [ ] AuthProvider has `isProfileCompletionRequired` getter
- [ ] Main.dart has ProfileCompletionProvider in MultiProvider
- [ ] All protected routes wrapped with _ProfileCompletionGuard
- [ ] LoginScreen redirects to profile-completion for incomplete profiles
- [ ] RegisterScreen redirects to profile-completion after registration
- [ ] API client has `completeProfile()` and `checkProfileStatus()` methods

### Database ✓
- [ ] Migration ran successfully
- [ ] `profile_completed` column visible in users table
- [ ] Default value is `false`
- [ ] All existing users have `profile_completed = false`

### Testing ✓
- [ ] New user registration flow works
- [ ] Profile completion screen displays correctly
- [ ] Form validation works (bio min 10 chars)
- [ ] Can submit and complete profile
- [ ] After completion, redirected to home
- [ ] Existing incomplete users redirected to profile completion
- [ ] Can't bypass profile completion with back button
- [ ] Can't access other screens before completion
- [ ] API returns 403 for incomplete profiles
- [ ] API returns 200 after profile completion
- [ ] Profile data persists after app restart

---

## Common Issues & Solutions

### Issue: "profile_completed" column not found

**Solution**:
```bash
# Run migration
cd backend
php artisan migrate

# If already ran, reset:
php artisan migrate:fresh --seed  # WARNING: Clears all data!
```

**Verify**:
```bash
php artisan tinker
\Schema::hasColumn('users', 'profile_completed')  # Should be true
```

---

### Issue: Middleware not working, endpoints not protected

**Solution**:
1. Verify middleware registered:
   ```php
   // bootstrap/app.php
   $middleware->alias([
       'profile.completed' => \App\Http\Middleware\ProfileCompleted::class,
   ]);
   ```

2. Verify routes use middleware:
   ```php
   // routes/api.php
   Route::middleware('profile.completed')->group(function () {
       Route::post('/alerts', [AlertController::class, 'store']);
   });
   ```

3. Test with Postman:
   ```
   POST /api/v1/alerts (with incomplete user token)
   Expected: 403 Forbidden with code PROFILE_INCOMPLETE
   ```

---

### Issue: Flutter app not redirecting to profile completion

**Solution**:
1. Verify ProfileCompletionGuard is wrapping routes:
   ```dart
   '/home': (context) => const _ProfileCompletionGuard(child: HomeScreen()),
   ```

2. Verify ProfileCompletionProvider added:
   ```dart
   ChangeNotifierProvider(create: (_) => ProfileCompletionProvider()),
   ```

3. Check app logs for errors:
   ```bash
   flutter run --verbose
   ```

4. Force refresh:
   ```bash
   flutter clean
   flutter pub get
   flutter run
   ```

---

### Issue: Users can still access protected screens

**Solution**:
1. Verify `profile_completed` is being returned from `/users/me` endpoint
2. Check UserModel is correctly parsing the field
3. Verify `isProfileCompletionRequired` getter logic:
   ```dart
   bool get isProfileCompletionRequired => 
     _isAuthenticated && _user != null && !_user!.profileCompleted;
   ```

4. Test with Postman:
   ```
   GET /users/me
   Should include: "profile_completed": true/false
   ```

---

### Issue: Validation not working on profile completion form

**Solution**:
1. Verify form validators are correctly implemented
2. Check TextFormField has validator:
   ```dart
   TextFormField(
     validator: (value) {
       if (value == null || value.trim().isEmpty) return 'Bio is required';
       if (value.trim().length < 10) return 'Bio must be at least 10 characters';
       return null;
     },
   )
   ```

3. Check form key before submit:
   ```dart
   if (_formKey.currentState!.validate()) {
     // Submit
   }
   ```

---

## Performance Considerations

✅ **Optimized for**:
- Minimal database queries (1 SELECT per login)
- No N+1 queries
- Efficient middleware checks
- Quick navigation with Provider state management
- Lazy screen loading in HomeScreen

⚠️ **Not optimized for** (but can be):
- Multiple simultaneous profile completion attempts (add rate limiting)
- Large profile data (profile fields are minimal by design)
- High-traffic profile completion endpoint (add caching)

---

## Rollback Instructions

If you need to revert the profile completion feature:

### Backend Rollback
```bash
# Revert migration
cd backend
php artisan migrate:rollback

# Remove middleware file
rm app/Http/Middleware/ProfileCompleted.php

# Remove endpoints from AuthController
# Update routes/api.php to remove middleware
```

### Frontend Rollback
```dart
// In main.dart - remove guard wrapper
'/home': (context) => const HomeScreen(),  // No guard

// Remove ProfileCompletionProvider from MultiProvider
// Remove ProfileCompletionScreen route

// Update LoginScreen to redirect directly to /home
if (success && mounted) {
  Navigator.of(context).pushReplacementNamed('/home');
}
```

**Note**: Existing users with `profile_completed = false` will still exist in database. Either run migration rollback or manually update them:
```bash
php artisan tinker
User::where('profile_completed', false)->update(['profile_completed' => true]);
```

---

## Support & Debugging

### Enable Debug Logging

**Backend** (Laravel):
```php
// In AuthController
Log::info('Profile completion attempt', [
    'user_id' => $user->id,
    'bio' => $validated['bio'],
]);
```

**Frontend** (Flutter):
```dart
// In ProfileCompletionProvider
print('✅ Profile completed: $success');
print('❌ Error: ${provider.errorMessage}');
```

### View Logs

**Backend**:
```bash
tail -f storage/logs/laravel.log
```

**Frontend**:
```bash
flutter run --verbose | grep -i "profile\|completion"
```

---

## Documentation References

- Full Implementation: [PROFILE_COMPLETION_FLOW.md](PROFILE_COMPLETION_FLOW.md)
- Backend Auth: [app/Http/Controllers/AuthController.php](backend/app/Http/Controllers/AuthController.php)
- Frontend Provider: [lib/providers/profile_completion_provider.dart](mobile_app/lib/providers/profile_completion_provider.dart)
- Profile Screen: [lib/features/profile/profile_completion_screen.dart](mobile_app/lib/features/profile/profile_completion_screen.dart)
