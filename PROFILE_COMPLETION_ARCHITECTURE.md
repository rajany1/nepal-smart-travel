# Profile Completion Flow - Architecture & Data Flow

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         NEPAL SMART TRAVEL APP                      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────┐                    ┌──────────────────┐     │
│  │  AUTH SCREENS    │                    │  PROFILE COMP    │     │
│  │  • Login         │───────────────────→│  • Form Input    │     │
│  │  • Register      │  profile_completed │  • Validation    │     │
│  │  • Forgot Pwd    │     = false        │  • Submit        │     │
│  └──────────────────┘                    └──────────────────┘     │
│                                                    ↓                │
│                                            ✅ Validated           │
│                                            ↓                       │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │         PROTECTED SCREENS (with Guard)                   │     │
│  │  • Home Screen          ← ProfileCompletionGuard        │     │
│  │  • Alerts               ← Checks profile_completed      │     │
│  │  • Profile              ← If false → Redirect to form   │     │
│  │  • Reports                                              │     │
│  │  • Emergency                                            │     │
│  │  • Places                                               │     │
│  │  • Assistant                                            │     │
│  └──────────────────────────────────────────────────────────┘     │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Data Flow Diagram

```
FRONTEND (Flutter)                  BACKEND (Laravel)          DATABASE
┌──────────────────┐                ┌──────────────┐           ┌────────┐
│ User Action      │                │   Server     │           │ users  │
│                  │                │              │           │        │
│ 1. REGISTER      │                │              │           │        │
│    or LOGIN      │───POST Auth────→ AuthController           │        │
└──────────────────┘                     ↓                      │        │
         ↓                          Validate User              │        │
         │                               ↓                     │        │
         │                          GET User Data             │        │
         │                               ↓                     │        │
         │                          SELECT * FROM users        └────┬───┘
         │                                                          │
         │◄────Response (includes profile_completed flag)──────────┘
         │
         ↓
┌──────────────────────────┐
│ Check profile_completed  │
│                          │
│ if false → Redirect to   │
│   Profile Completion     │
│   Screen                 │
└──────────────────────────┘
         ↓
┌──────────────────────────┐
│ ProfileCompletionScreen  │
│                          │
│ Form:                    │
│  - Bio (required)        │
│  - Phone (optional)      │
│  - Avatar (optional)     │
│                          │
│ User Input → Validate    │
│ Frontend Validation ✓    │
│              ↓           │
│         Submit           │
└──────────────────────────┘
         ↓
         │
         ├─POST /auth/complete-profile─────→ AuthController
         │                                        ↓
         │                              Backend Validation:
         │                              ✓ Bio min 10 chars
         │                              ✓ Bio max 500 chars
         │                              ✓ Phone format (if provided)
         │                                     ↓
         │                              UPDATE users
         │                              SET profile_completed = true,
         │                                  bio = ?,
         │                                  phone = ?
         │                              WHERE id = ?
         │                                     ↓
         │                          ┌─────────────────────┐
         │◄─Response: 200 OK────────│ Update Successful   │
         │ {success: true,          │ profile_completed   │
         │  data: {...}             │ = true              │
         │ }                         └─────────────────────┘
         │
         ↓
┌──────────────────────────┐
│ Update Local State       │
│ • AuthProvider.user      │
│ • profileCompleted=true  │
└──────────────────────────┘
         ↓
┌──────────────────────────┐
│ Navigate to /home        │
│                          │
│ ProfileCompletionGuard   │
│ checks: profile_completed│
│ = true ✓                 │
│                          │
│ → Show HomeScreen        │
│ → Full Access Granted    │
└──────────────────────────┘
```

---

## API Endpoint Flow

```
POST /auth/login
├─ Input: { email, password }
├─ Response: { access_token, data: {..., profile_completed: false} }
├─ Frontend: Check profile_completed
├─ If false → Redirect to /profile-completion
└─ If true → Redirect to /home


POST /auth/register
├─ Input: { name, email, phone, password }
├─ Backend: Create user with profile_completed = false
├─ Response: { access_token, data: {..., profile_completed: false} }
├─ Frontend: Auto-redirect to /profile-completion
└─ No choice - profile must be completed


POST /auth/complete-profile
├─ Input: { bio, phone?, avatar? }
├─ Auth: Required ✓
├─ Validation:
│  ├─ bio: required, min 10, max 500 chars
│  ├─ phone: optional, min 7 chars if provided
│  └─ avatar: optional, string URL
├─ Backend: 
│  ├─ Validate all fields
│  ├─ UPDATE users SET profile_completed = true
│  └─ Response: { success: true, data: {...} }
├─ Frontend: Update AuthProvider
└─ Navigation: /profile-completion → /home


GET /auth/check-profile-status
├─ Auth: Required ✓
├─ Response: {
│    profile_completed: true/false,
│    missing_fields: ["bio", "phone"]  // only actually missing
│  }
└─ Used to sync state on app startup


GET /users/me
├─ Auth: Required ✓
├─ Response includes: { ..., profile_completed: true/false }
└─ Profile completion flag persisted


POST /alerts (PROTECTED - requires profile_completed)
├─ Auth: Required ✓
├─ Middleware: ProfileCompleted checks flag
├─ If profile_completed = false:
│  ├─ Response: 403 Forbidden
│  ├─ Code: PROFILE_INCOMPLETE
│  └─ Missing Fields: ["bio"]
├─ If profile_completed = true:
│  ├─ Process request normally
│  └─ Response: 200 OK
└─ Other protected endpoints follow same pattern
```

---

## Middleware Protection Layer

```
REQUEST TO PROTECTED ENDPOINT
        ↓
┌──────────────────────────────────┐
│ Sanctum Auth Middleware          │
│ (Check: Is user authenticated?)  │
└──────────────────────────────────┘
        ↓
  User authenticated? YES
        ↓
┌──────────────────────────────────┐
│ ProfileCompleted Middleware      │
│ (Check: Is profile completed?)   │
└──────────────────────────────────┘
        ↓
  profile_completed == true? YES
        ↓
┌──────────────────────────────────┐
│ Execute Request Handler          │
│ (Process API endpoint normally)  │
└──────────────────────────────────┘
        ↓
    Return 200 OK


----- ERROR PATHS -----

NOT AUTHENTICATED?
        ↓
    Return 401 Unauthorized

PROFILE NOT COMPLETED?
        ↓
┌──────────────────────────────────┐
│ Return 403 Forbidden             │
│ {                                │
│   "success": false,              │
│   "message": "Profile...",       │
│   "code": "PROFILE_INCOMPLETE",  │
│   "profile_completed": false,    │
│   "missing_fields": ["bio"]      │
│ }                                │
└──────────────────────────────────┘
```

---

## Frontend State Management

```
MAIN CONTEXT (MultiProvider)
│
├─ AuthProvider
│  ├─ _user: UserModel?
│  │  ├─ profileCompleted: bool
│  │  ├─ name, email, bio, phone
│  │  └─ role, level, xp, etc.
│  │
│  ├─ _isAuthenticated: bool
│  ├─ _isLoading: bool
│  ├─ _errorMessage: String?
│  │
│  ├─ Methods:
│  │  ├─ login()
│  │  ├─ register()
│  │  ├─ logout()
│  │  ├─ checkAuthStatus()
│  │  └─ isProfileCompletionRequired (getter)
│  │
│  └─ UI Listeners: LoginScreen, HomeScreen
│
├─ ProfileCompletionProvider
│  ├─ _profileCompleted: bool
│  ├─ _missingFields: List<String>
│  ├─ _isLoading: bool
│  ├─ _errorMessage: String?
│  │
│  ├─ Methods:
│  │  ├─ completeProfile()
│  │  ├─ checkStatus()
│  │  ├─ updateFromUser()
│  │  └─ reset()
│  │
│  └─ UI Listeners: ProfileCompletionScreen
│
├─ AlertProvider
│  └─ [Existing - no changes]
│
└─ PlaceProvider
   └─ [Existing - no changes]


CONSUMER PATTERN
│
├─ ProfileCompletionGuard
│  └─ Watches: AuthProvider
│     └─ Checks: isProfileCompletionRequired
│        └─ Actions:
│           ├─ If true → Redirect to /profile-completion
│           └─ If false → Show protected screen
│
└─ ProfileCompletionScreen
   └─ Watches: ProfileCompletionProvider
      └─ Updates:
         ├─ On submit → Call completeProfile()
         ├─ On error → Show error message
         └─ On success → Navigate to /home
```

---

## Database Schema

```
┌─────────────────────────────────────┐
│           USERS TABLE               │
├─────────────────────────────────────┤
│ Column              │ Type          │
├─────────────────────────────────────┤
│ id                  │ INT PK        │
│ uuid                │ UUID          │
│ name                │ VARCHAR(255)  │
│ email               │ VARCHAR(255)  │
│ phone               │ VARCHAR(20)   │
│ password            │ VARCHAR(255)  │
│ avatar              │ VARCHAR(255)  │
│ bio                 │ TEXT          │
│ role                │ ENUM          │
│ status              │ ENUM          │
│ profile_completed   │ BOOLEAN ✨    │ ← NEW COLUMN
│ total_xp            │ INT           │
│ current_level       │ INT           │
│ verification_tick   │ ENUM          │
│ badges              │ JSON          │
│ expertise_regions   │ JSON          │
│ total_reports       │ INT           │
│ approved_reports    │ INT           │
│ rejected_reports    │ INT           │
│ approval_rate       │ DECIMAL       │
│ rank                │ INT           │
│ created_at          │ TIMESTAMP     │
│ updated_at          │ TIMESTAMP     │
│ last_contribution_at│ TIMESTAMP     │
└─────────────────────────────────────┘

KEY CHANGES:
├─ profile_completed: New boolean column
├─ Default: false (all new users incomplete)
├─ Non-null: Always has value (false or true)
├─ Indexed: Yes (for frequent queries)
└─ Reversible: Yes (migration can rollback)
```

---

## Screen Navigation Flow

```
START APP
    ↓
┌──────────────┐
│ SplashScreen │  (or direct to LoginScreen based on token)
└──────────────┘
    ↓
┌──────────────────────────────┐
│    Authentication Check      │
│  • Read stored token         │
│  • Verify with backend       │
└──────────────────────────────┘
    ├─ Not Authenticated? → LoginScreen
    └─ Authenticated? → Check next step
         ↓
      ┌──────────────────────────────┐
      │ Check Profile Completion     │
      │ user.profileCompleted?       │
      └──────────────────────────────┘
         ├─ FALSE → ProfileCompletionScreen
         │           ├─ User fills form
         │           ├─ Submit to backend
         │           ├─ Backend validates & updates DB
         │           └─ Redirect to HomeScreen
         │
         └─ TRUE → HomeScreen (Full Access)
                    ├─ Explore Tab
                    ├─ Nearby Places
                    ├─ Reports List
                    ├─ Emergency
                    └─ Profile

DEEP LINKING/DIRECT ROUTES
┌──────────────────────────┐
│ /home, /alerts, etc.     │
│ (Protected routes)       │
└──────────────────────────┘
         ↓
┌──────────────────────────┐
│ ProfileCompletionGuard   │
│ Intercepts navigation    │
└──────────────────────────┘
         ↓
    Check profileCompleted
         ├─ FALSE → Redirect to /profile-completion
         └─ TRUE → Allow access to screen

USER CAN ESCAPE?
├─ Back button? NO ✗ (Screen prevents it)
├─ Direct URL/deep link? NO ✗ (Guard catches it)
├─ API call? NO ✗ (Middleware blocks it)
└─ Modify local storage? NO ✗ (Server validates)
```

---

## Error Handling Flow

```
FRONTEND VALIDATION ERROR
User Input → Validator
    ├─ Bio empty? → "Bio cannot be empty"
    ├─ Bio < 10 chars? → "Bio must be at least 10 characters"
    ├─ Bio > 500 chars? → Prevented by maxLength
    └─ Phone < 7 chars? → "Please enter a valid phone number"

Show error in red box below field
User corrects input
Retry submission


BACKEND VALIDATION ERROR (422)
POST /auth/complete-profile
Body validation fails
    ↓
Return 422 Unprocessable Entity
{
  "message": "Validation failed",
  "errors": {
    "bio": ["The bio field is required."]
  }
}
    ↓
Frontend: Parse error → Show to user
"The bio field is required."
    ↓
User corrects input → Retry


PROFILE INCOMPLETE ERROR (403)
Request to /alerts with incomplete profile
    ↓
ProfileCompleted Middleware
    ├─ Check: profile_completed == true?
    └─ Result: FALSE
    ↓
Return 403 Forbidden
{
  "success": false,
  "message": "Profile completion required",
  "code": "PROFILE_INCOMPLETE",
  "profile_completed": false,
  "missing_fields": ["bio"]
}
    ↓
Frontend App: 
  ├─ If logged in → Redirect to /profile-completion
  └─ If not logged in → Redirect to /login


NETWORK ERROR
POST /auth/complete-profile
Network timeout or connection failed
    ↓
Frontend Exception Handler
    ├─ Catch DioException
    ├─ Parse error type
    ├─ Map to user-friendly message
    └─ Show in error box
    ↓
Error Messages:
├─ Timeout: "Connection timeout. Please try again."
├─ No internet: "Check your internet connection"
├─ Server down: "Server error. Try again later."
└─ Others: "Network error. Please try again."
    ↓
User can retry immediately


VALIDATION FIELD TRACKING
Incomplete profile has missing fields:
    ├─ bio → Required
    └─ phone → Optional

Missing fields array helps:
├─ Show relevant error messages
├─ Highlight which fields to fill
├─ Guide users through completion
└─ Track progress
```

---

## Security Checkpoints

```
┌─────────────────────────────┐
│  SECURITY LAYER 1           │
│  Frontend Navigation Guard  │
├─────────────────────────────┤
│                             │
│  _ProfileCompletionGuard    │
│  ├─ Watches: AuthProvider  │
│  ├─ Check: profile_completed
│  ├─ If false:              │
│  │  └─ Prevent screen access
│  │     Redirect to form    │
│  └─ If true:               │
│     └─ Allow screen access │
│                             │
│  Protects against:          │
│  ✓ Direct route access      │
│  ✓ Browser back button      │
│  ✓ Deep linking             │
│  ✓ Tab bar taps             │
│                             │
└─────────────────────────────┘
         ↓
┌─────────────────────────────┐
│  SECURITY LAYER 2           │
│  API Middleware             │
├─────────────────────────────┤
│                             │
│  ProfileCompleted           │
│  Middleware                 │
│  ├─ Runs on every request  │
│  ├─ Check: profile_completed
│  ├─ If false:              │
│  │  └─ Return 403 Forbidden
│  │     + Code: PROFILE_...│
│  │     + Missing fields   │
│  └─ If true:               │
│     └─ Allow request       │
│                             │
│  Protects against:          │
│  ✓ Direct API calls         │
│  ✓ Postman/curl requests    │
│  ✓ Mobile app bypass        │
│  ✓ Web3/blockchain attempts │
│  ✓ Local storage tampering  │
│                             │
└─────────────────────────────┘


ATTACK SCENARIOS & RESPONSES

Scenario 1: Try to bypass form
└─ Edit localStorage to set flag
   └─ Frontend auth passes
      └─ API call fails: 403 Forbidden ✓

Scenario 2: Direct API call
└─ POST /alerts without profile
   └─ Middleware blocks it
      └─ Response: 403 with PROFILE_INCOMPLETE code ✓

Scenario 3: Deep link to /home
└─ ProfileCompletionGuard intercepts
   └─ Checks isProfileCompletionRequired
      └─ Redirect to /profile-completion ✓

Scenario 4: Back button from form
└─ Screen prevents back navigation
   └─ AppBar has automaticallyImplyLeading: false ✓

Scenario 5: Modify network response
└─ Next request to API fails
   └─ Server state is source of truth ✓
```

---

## Performance Optimization

```
QUERY OPTIMIZATION
┌──────────────────────────────┐
│ SELECT * FROM users          │
│ WHERE id = ?                 │
│ ✓ Indexed lookup: O(log n)   │
│ ✓ Single query per request   │
│ ✓ No N+1 queries             │
│ ✓ Minimal database load      │
└──────────────────────────────┘

STATE MANAGEMENT
┌──────────────────────────────┐
│ Provider Pattern             │
│ ✓ Local state caching        │
│ ✓ No redundant fetches       │
│ ✓ Efficient rebuilds         │
│ ✓ Minimal widget updates     │
└──────────────────────────────┘

LAZY LOADING
┌──────────────────────────────┐
│ HomeScreen Screens           │
│ ✓ Created at init (fast ui)  │
│ ✓ IndexedStack (efficient)   │
│ ✓ Minimal memory overhead    │
└──────────────────────────────┘

API CACHING
┌──────────────────────────────┐
│ Profile data cached          │
│ ✓ Stored in UserModel        │
│ ✓ Only fetch on demand       │
│ ✓ Background refresh         │
└──────────────────────────────┘
```
