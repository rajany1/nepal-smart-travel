# 🔍 Flutter Logs & Debugging Reference

## Expected Logs When Everything Works ✅

### Register Flow - SUCCESS

```
flutter: 🔍 Parsing error: (none - no error)
I/flutter: POST /auth/register - Request sent
I/flutter: Response received:
{
  "success": true,
  "message": "User registered successfully",
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "data": {
    "id": 4,
    "name": "Test User",
    "email": "testuser@gmail.com"
  }
}
I/flutter: ✅ AuthResponse parsed successfully
I/flutter: ✅ access_token found: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
I/flutter: ✅ User authenticated set to true
I/flutter: 📱 Profile fetch async started (non-blocking)
I/flutter: ✅ Redirecting to /email-verification
```

### Login Flow - SUCCESS

```
flutter: 🔍 Parsing error: (none - no error)
I/flutter: POST /auth/login - Request sent
I/flutter: Response received with access_token at root
I/flutter: ✅ access_token found: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
I/flutter: ✅ Token set in secure storage
I/flutter: ✅ User authenticated set to true
I/flutter: 📱 Profile fetch async started (non-blocking)
I/flutter: ✅ Redirecting to /home
I/flutter: [Background] GET /users/me - Profile loaded successfully
```

### Profile Fetch - BACKGROUND SUCCESS

```
I/flutter: 📱 Profile fetch async started (non-blocking)
I/flutter: GET /users/me - Request sent
I/flutter: Response received:
{
  "user_id": 4,
  "name": "Test User",
  "email": "testuser@gmail.com",
  ...
}
I/flutter: ✅ User profile parsed successfully
I/flutter: 📱 UserModel created with id=4, name=Test User
```

---

## Expected Logs When Errors Occur ✅

### Register - Duplicate Email (422 Validation)

```
flutter: 🔍 Parsing error: Response with status: 422
I/flutter: POST /auth/register - Request failed
I/flutter: Error Response:
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email already taken"],
    "phone": ["Phone already registered"]
  }
}
I/flutter: ❌ Validation errors found in response
I/flutter: 📧 Error extracted: "email: Email already taken"
I/flutter: ✅ Showing to user: "email: Email already taken"
```

**What User Sees**: "email: Email already taken" (field-level error)

### Login - Wrong Password (401 Unauthorized)

```
flutter: 🔍 Parsing error: Response with status: 401
I/flutter: POST /auth/login - Request failed
I/flutter: Error Response:
{
  "success": false,
  "message": "Invalid credentials",
  "error": "Unauthorized"
}
I/flutter: ❌ Authentication failed - Status 401
I/flutter: 🔑 Error extracted: "Invalid email or password"
I/flutter: ✅ Showing to user: "Invalid email or password"
```

**What User Sees**: "Invalid email or password"

### Network Error - No Connection

```
flutter: 🔍 Parsing error: SocketException: Failed host lookup
I/flutter: POST /auth/login - Connection failed
I/flutter: ❌ Network error detected
I/flutter: 📡 Error extracted: "Unable to connect to server. Check your internet connection."
I/flutter: ✅ Showing to user: "Unable to connect to server. Check your internet connection."
```

**What User Sees**: "Unable to connect to server. Check your internet connection."

### Server Error (500 Internal Server Error)

```
flutter: 🔍 Parsing error: Response with status: 500
I/flutter: POST /auth/register - Request failed
I/flutter: Error Response:
{
  "success": false,
  "message": "An error occurred",
  "error": "Internal Server Error"
}
I/flutter: ❌ Server error detected - Status 500
I/flutter: ⚠️ Error extracted: "Server error. Please try again later."
I/flutter: ✅ Showing to user: "Server error. Please try again later."
```

**What User Sees**: "Server error. Please try again later."

### Profile Fetch Fails (Non-blocking)

```
I/flutter: 📱 Profile fetch async started (non-blocking)
I/flutter: GET /users/me - Request sent
I/flutter: ❌ Profile fetch failed (non-blocking): DioException: 401 Unauthorized
I/flutter: ⚠️ Profile fetch failed (non-blocking): User token may have expired
I/flutter: ✅ Note: User is still authenticated, profile just missing
I/flutter: 📱 User can continue using app without profile data
```

**What User Sees**: Nothing (silent error, user already logged in)

---

## Debug Logs to Add for Testing

You can add these print statements temporarily for debugging:

```dart
// In AuthProvider.login()
print('🔵 [Auth] Login started with email: $email');

// In AuthResponse.fromJson()
print('🟡 [Auth] Parsing response...');
print('📦 [Auth] Response data: ${json.toString()}');
print('🔑 [Auth] Found access_token: ${json['access_token'] != null ? "YES" : "NO"}');
print('👤 [Auth] Found user_id: ${json['data']?['id'] ?? json['user_id'] != null ? "YES" : "NO"}');

// In _handleAuthSuccess()
print('✅ [Auth] Token set, authenticated: true');
print('🔄 [Auth] Fetching profile in background...');

// In _fetchProfileAsync()
print('📥 [Auth] Profile fetch completed, user: ${_user?.name}');
```

---

## Flutter Console Output - Full Success Flow

When register succeeds, you should see:

```
flutter: ✅ AuthProvider initialized
flutter: 🟡 [Auth] Parsing response...
flutter: 📦 [Auth] Response data: {"success":true,"access_token":"eyJ...","data":{"id":4,"name":"Test User"}}
flutter: 🔑 [Auth] Found access_token: YES
flutter: 👤 [Auth] Found user_id: YES
flutter: 🟢 [Auth] Registration successful
flutter: ✅ [Auth] Token set, authenticated: true
flutter: 🔄 [Auth] Fetching profile in background...
flutter: ✅ Navigating to /email-verification
flutter: 📥 [Auth] Profile fetch completed, user: Test User
```

---

## Common Log Patterns to Look For

### ✅ SUCCESS Pattern
```
access_token: (has value) → YES ✅
success: true → YES ✅
User authenticated: true → YES ✅
Error message: null → YES ✅
Navigation: to next screen → YES ✅
```

### ❌ FAILURE Pattern (before fix)
```
access_token: null → PROBLEM ❌
success: true but auth fails → Response parsing issue ❌
Error message: "Authentication failed" (generic) → Problem ❌
Profile fetch throws error → Blocking issue ❌
Never navigates → Auth considered failed ❌
```

### ✅ SUCCESS Pattern (after fix)
```
access_token: (has value) → YES ✅
success: true → YES ✅
User authenticated: true (immediate) → YES ✅
Profile fetch: async, non-blocking → YES ✅
Navigation: immediate, doesn't wait for profile → YES ✅
Error message: descriptive (not generic) → YES ✅
```

---

## Testing Commands in Dart DevTools

If you use Dart DevTools, you can watch these values:

```dart
// Watch these in debugger
print('_isAuthenticated: ${provider._isAuthenticated}');
print('_user: ${provider._user?.name}');
print('_errorMessage: ${provider._errorMessage}');
print('_isLoading: ${provider._isLoading}');

// Verify response parsing
final testResponse = {
  "success": true,
  "access_token": "test_token",
  "data": {"id": 1, "name": "Test"}
};
final authResp = AuthResponse.fromJson(testResponse);
print('accessToken: ${authResp.accessToken}');
print('success: ${authResp.success}');
```

---

## Validation Error Log Example

When backend returns validation errors:

```
I/flutter: Error Response received:
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["The email has already been taken.", "Must be valid email"],
    "password": ["The password must be at least 8 characters."],
    "phone": ["The phone must be unique."]
  }
}
I/flutter: ✅ Validation errors parsed:
I/flutter: - email: [
I/flutter:     "The email has already been taken.",
I/flutter:     "Must be valid email"
I/flutter:   ]
I/flutter: - password: [
I/flutter:     "The password must be at least 8 characters."
I/flutter:   ]
I/flutter: - phone: [
I/flutter:     "The phone must be unique."
I/flutter:   ]
I/flutter: 📧 User shown first error: "email: The email has already been taken."
```

---

## Performance Expectations

### Register Flow Timing
```
Register button → API request: ~200-500ms
Response received: ~200-500ms  
Token parsed: <1ms
Token stored: ~10ms
User authenticated: ~1ms
Navigation to email-verification: ~50-100ms
---
Total: ~500-1100ms

[Background continues]
GET /users/me request: <200ms
Profile parsed: <5ms
UI updated with profile: ~50ms
```

### Login Flow Timing
```
Login button → API request: ~200-500ms
Response received: ~200-500ms
Token parsed: <1ms
Token stored: ~10ms
User authenticated: ~1ms
Navigation to home: ~50-100ms
---
Total: ~500-1100ms (IMMEDIATE, doesn't wait for profile)

[Background continues]
GET /users/me request: ~200ms
Profile parsed: <5ms
UI updated with profile: ~50ms
```

---

## Troubleshooting Logs

### If you see: "❌ Login error: DioException: 401 Unauthorized"
**Means**: Credentials are wrong OR token is invalid
**Check**: Email/password correct, backend login endpoint working

### If you see: "⚠️ Profile fetch failed (non-blocking)"
**Means**: Profile load failed, but user is already authenticated
**Expected**: User can continue, just profile data missing
**Check**: Token still valid, /users/me endpoint working

### If you see: "accessToken: null"
**Means**: Backend response structure different than expected
**Check**: 
- Is backend returning `access_token` at root level?
- Not nested inside `data` field?
- Test with Postman/curl first

### If you see: "error parsing validation errors"
**Means**: Backend returned validation errors in unexpected format
**Check**: 
- Backend returns `errors` as object with field names
- Format: `{ "email": ["error msg"], "phone": ["error msg"] }`

