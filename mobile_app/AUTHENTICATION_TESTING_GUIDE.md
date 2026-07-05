# 🧪 Authentication System - Testing Guide

> Complete testing guide for verifying the enhanced authentication system works correctly

## 📋 Pre-Testing Checklist

Before testing, ensure:
- [ ] Flutter emulator is running
- [ ] Backend API is accessible
- [ ] API base URL is correct in `app_constants.dart`
- [ ] Test user account exists in backend (or use registration test)
- [ ] Network connectivity is available

## 🚀 Quick Test Commands

```bash
# In terminal, from mobile_app directory

# Clean build
flutter clean
flutter pub get

# Run on emulator
flutter run

# Run with verbose logging
flutter run -v

# Run specific file
flutter run lib/main.dart
```

## 🧪 Test Suite 1: Authentication Flows

### Test 1.1: Login with Valid Credentials

**Steps:**
1. Launch app
2. You should see initialization screen (2-3 seconds)
3. Should navigate to login screen
4. Enter valid email and password
5. Tap "Login"
6. Wait for API response

**Expected Results:**
- Loading indicator shows during API call
- User data loads and is stored
- Redirects to home screen (if profile complete) or profile completion screen
- User name displays in app

**Debug Output to Look For:**
```
I/flutter: ✅ Auth initialization complete
I/flutter: ✅ Login successful
I/flutter: ✅ User data persisted
```

### Test 1.2: Login with Invalid Credentials

**Steps:**
1. From login screen
2. Enter invalid email and password
3. Tap "Login"

**Expected Results:**
- Loading indicator shows
- Error message displays: "Invalid email or password"
- User stays on login screen
- Can retry login

**Debug Output:**
```
I/flutter: ❌ Login failed: Invalid credentials
I/flutter: 🔍 Error: 401 Unauthorized
```

### Test 1.3: Login with Blank Fields

**Steps:**
1. Tap login without entering credentials

**Expected Results:**
- Validation error shows on empty field
- No API request is made
- User stays on login screen

### Test 1.4: Remember Me Functionality

**Steps:**
1. Check "Remember me"
2. Enter email and password
3. Login successfully
4. Logout
5. Return to login screen

**Expected Results:**
- Email field should be pre-filled with remembered email
- Password field is empty
- Can click login with only password

**Debug Output:**
```
I/flutter: 💾 Preference stored: rememberEmail = user@example.com
```

## 🧪 Test Suite 2: Session Management

### Test 2.1: Session Restoration on App Restart

**Steps:**
1. Login successfully
2. Note the user data displayed
3. Close app (swipe out from recents)
4. Reopen app

**Expected Results:**
- App shows initialization screen (~1-2 seconds)
- Automatically navigates to home screen
- Same user data is displayed (no re-login needed)
- "Session restored from cache" log message

**Debug Output:**
```
I/flutter: 🔄 Restoring session from cache...
I/flutter: ✅ Session restored successfully
I/flutter: 👤 User data: John Doe (john@example.com)
```

### Test 2.2: Session Expiry Handling

**Steps:**
1. Login successfully
2. Make a request to any protected endpoint
3. Wait for token to "expire" (if backend sends 401)
4. Make another request

**Expected Results:**
- First request succeeds
- On 401 response, token is automatically refreshed
- Second request succeeds with new token
- No re-login required
- Seamless user experience

**Debug Output:**
```
I/flutter: 🔐 Token refresh triggered
I/flutter: ✅ Token refreshed successfully
I/flutter: 🔄 Retrying request...
```

### Test 2.3: Session Persistence Across Features

**Steps:**
1. Login
2. Navigate to different screens (Home, Profile, Reports, etc.)
3. Go back to Profile screen
4. Check user data is still available

**Expected Results:**
- User data persists across all screens
- No additional API calls for user data
- Fast access (no loading screens)

## 🧪 Test Suite 3: User Data

### Test 3.1: Full User Data Available

**Steps:**
1. Login successfully
2. Navigate to Profile screen
3. Check that all user information displays

**Expected Results:**
- Name, email, phone display
- Avatar or initial displays
- Level and XP show
- Badges display (if any)
- Report statistics display
- All data is populated

**Debug Output:**
```
I/flutter: 👤 User data loaded:
I/flutter:   - Name: John Doe
I/flutter:   - Email: john@example.com
I/flutter:   - Level: 5 (Contributor)
I/flutter:   - XP: 1500
I/flutter:   - Reports: 10
I/flutter:   - Rank: #42
```

### Test 3.2: Profile Update

**Steps:**
1. On Profile screen
2. Edit profile (if button exists)
3. Update name or bio
4. Save

**Expected Results:**
- Loading indicator shows
- Data updates immediately in state
- Backend receives update
- New data persists after app restart

**Debug Output:**
```
I/flutter: 📝 Updating profile...
I/flutter: ✅ Profile updated successfully
I/flutter: 💾 User data persisted to storage
```

### Test 3.3: Profile Refresh

**Steps:**
1. On Profile screen
2. Tap refresh button
3. Wait for API response

**Expected Results:**
- Loading indicator shows during refresh
- Data updates from server
- User sees latest profile data
- No app restart required

## 🧪 Test Suite 4: Navigation

### Test 4.1: Profile Completion Enforcement

**Steps:**
1. Register new user (profile_completed = false)
2. After registration, try to navigate to home
3. Should redirect to profile completion screen

**Expected Results:**
- Cannot access protected screens without completing profile
- Redirects to profile completion screen
- After completing profile, can access home screen

**Debug Output:**
```
I/flutter: ⚠️ Profile completion required
I/flutter: 🔄 Redirecting to profile completion...
```

### Test 4.2: Protected Route Navigation

**Steps:**
1. Logout completely
2. Try to access /home, /profile, /reports by deep link
3. Should redirect to login

**Expected Results:**
- Cannot access protected routes while logged out
- Automatically redirects to login
- After login, can access the route

### Test 4.3: Auth State Changes

**Steps:**
1. Navigate through app while logged in
2. Trigger logout
3. Should immediately redirect to login

**Expected Results:**
- All screens update immediately
- No lingering data from previous user
- Clean transition to login screen

## 🧪 Test Suite 5: Error Handling

### Test 5.1: Network Error

**Steps:**
1. Disconnect network
2. Try to login
3. Observe error message

**Expected Results:**
- Shows friendly error: "Unable to connect. Check internet connection."
- Can retry when network is available
- No crashes

**Debug Output:**
```
I/flutter: ❌ Network error: SocketException
I/flutter: 📢 Showing user error: Unable to connect...
```

### Test 5.2: Server Error (500)

**Steps:**
1. Trigger server error (if possible in test)
2. Observe response

**Expected Results:**
- Shows friendly error: "Server error. Please try again later."
- Can retry
- No crashes

### Test 5.3: Validation Error (422)

**Steps:**
1. Try to register with invalid data
2. Observe error

**Expected Results:**
- Shows validation error: "Validation failed. Please check your input."
- Fields remain filled for correction
- Can retry

## 🧪 Test Suite 6: Preferences

### Test 6.1: Store Preference

**Steps:**
1. In settings, select theme = "dark"
2. Close and reopen app

**Expected Results:**
- Preference is saved
- After app restart, dark theme is still selected
- No preference selection needed

**Debug Output:**
```
I/flutter: 💾 Preference saved: theme = dark
I/flutter: 🔄 Preference restored: theme = dark
```

### Test 6.2: Preference Persistence

**Steps:**
1. Change language to Nepali
2. Navigate around app
3. Close app
4. Reopen app

**Expected Results:**
- Language setting persists
- UI reflects selected language
- Setting available after restart

## 🧪 Test Suite 7: Data Integrity

### Test 7.1: Data Consistency

**Steps:**
1. Login
2. Update profile
3. Navigate away
4. Come back to profile screen
5. Tap refresh

**Expected Results:**
- Data before refresh = data after login
- After refresh = latest from server
- No data loss or corruption

### Test 7.2: Cache Invalidation

**Steps:**
1. Login user A
2. Logout
3. Login user B
4. Check profile

**Expected Results:**
- User A data completely cleared
- Only User B data visible
- No data leakage between users

### Test 7.3: Session Security

**Steps:**
1. Login
2. Check storage with debugging tools
3. Verify tokens are encrypted

**Expected Results:**
- Tokens not visible in plain text
- FlutterSecureStorage working correctly
- Data encrypted at rest

## 🧪 Test Suite 8: Performance

### Test 8.1: App Initialization Speed

**Steps:**
1. Fresh app start
2. Measure time to home screen

**Expected Results:**
- Session restored from cache: <500ms
- Full navigation: <1s
- Fast enough for production

**Debug Output:**
```
I/flutter: ⏱️ Initialization time: 342ms
I/flutter: ⏱️ Navigation time: 156ms
```

### Test 8.2: API Call Performance

**Steps:**
1. Make API request
2. Observe response time

**Expected Results:**
- API calls respond in reasonable time
- No timeout errors
- Smooth user experience

### Test 8.3: Memory Usage

**Steps:**
1. Open DevTools memory profiler
2. Login and navigate
3. Check memory growth

**Expected Results:**
- Memory usage is stable
- No memory leaks
- Reasonable memory footprint

## 📊 Test Results Template

```
Test Date: ___________
Flutter Version: ___________ 
API Backend: ___________
Device: Emulator / Physical

Test Suite Results:
- [x] Authentication Flows: PASS/FAIL
- [x] Session Management: PASS/FAIL
- [x] User Data: PASS/FAIL
- [x] Navigation: PASS/FAIL
- [x] Error Handling: PASS/FAIL
- [x] Preferences: PASS/FAIL
- [x] Data Integrity: PASS/FAIL
- [x] Performance: PASS/FAIL

Issues Found:
1. ________________
2. ________________
3. ________________

Overall Status: PASS/FAIL
Ready for Production: YES/NO
```

## 🐛 Debugging Tips

### Enable Verbose Logging
```bash
flutter run -v
```

### Check Device Logs
```bash
flutter logs
```

### Use DevTools
```bash
flutter pub global activate devtools
devtools
# Then open in browser and connect app
```

### Inspect Local Storage
```bash
flutter run
# Press 'v' for DevTools
# Navigate to Storage section
```

### Monitor Network Requests
```bash
# Enable network logging in ApiClient
// Already enabled with LogInterceptor in api_client.dart
```

## ✅ Pre-Deployment Checklist

- [ ] All 8 test suites pass
- [ ] No memory leaks
- [ ] Performance is acceptable
- [ ] Error messages are user-friendly
- [ ] Session restoration works
- [ ] Token refresh works
- [ ] Logout clears all data
- [ ] Profile completion enforced
- [ ] No console errors
- [ ] No platform-specific issues

## 🚀 Deployment Readiness

After passing all tests, the app is ready for:
- [ ] Internal testing
- [ ] Beta testing
- [ ] App Store submission
- [ ] Production deployment

## 📞 Troubleshooting Test Failures

### "Session not restored after restart"
- Check SessionManager initialization
- Verify FlutterSecureStorage working
- Check app logs for errors

### "Token refresh not working"
- Verify refresh endpoint on backend
- Check AuthInterceptor configuration
- Ensure refresh token is stored

### "User data is null"
- Verify API response includes user data
- Check UserModel.fromJson() parsing
- Look for API response format issues

### "Preferences not persisting"
- Check SessionManager preferences storage
- Verify async operations are awaited
- Check storage permissions

### "Performance is slow"
- Profile with DevTools
- Check for unnecessary rebuilds
- Optimize large lists with pagination
- Check API response sizes

## 📝 Notes

- All tests should pass before production deployment
- Test on both Android and iOS
- Test with real backend API
- Test with real network conditions
- Test edge cases and error scenarios
- Document any issues for follow-up

## 🎊 Success Criteria

- ✅ All test suites pass
- ✅ No crashes or errors
- ✅ Performance meets requirements
- ✅ User data secure
- ✅ Session properly managed
- ✅ Error handling graceful
- ✅ All features work as intended

---

**Ready to test?** Start with Test Suite 1 and work your way through!
