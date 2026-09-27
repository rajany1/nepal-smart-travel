<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\LegalDocumentAcceptance;
use App\Models\PushToken;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Issue an access token + a long-lived refresh token pair.
     */
    private function issueTokenPair(User $user): array
    {
        // Access token expiry matches Sanctum config (default 30 days)
        $accessExpiryDays = (int) config('sanctum.expiration', 60 * 24 * 30) / (60 * 24);
        $expiresIn = config('sanctum.expiration', 60 * 24 * 30) * 60; // seconds

        return [
            'access_token' => $user->createToken('app')->plainTextToken,
            'refresh_token' => $user->createToken('refresh', ['*'], now()->addDays(30))->plainTextToken,
            'expires_in' => $expiresIn,
        ];
    }

    // REGISTER
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|unique:users',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
            'terms_accepted' => 'required|accepted',
            'privacy_accepted' => 'required|accepted',
            'age_confirmed' => 'required|accepted',
            'app_version' => 'nullable|string|max:20',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password, // hashed via model
            'avatar' => null,
            'bio' => null,
            'badges' => [],
            'expertise_regions' => [],
            'settings' => [],
        ];
        if (Schema::hasColumn('users', 'profile_completed')) {
            $data['profile_completed'] = false;
        }

        $user = User::create($data);

        // Record legal document acceptances
        $this->recordConsent($user, 'terms_conditions', $request);
        $this->recordConsent($user, 'privacy_policy', $request);

        $tokens = $this->issueTokenPair($user);

        $this->sendVerificationOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully. Verification code sent to your email.',
            ...$tokens,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if ($user->status === 'banned') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been permanently banned due to violation of our community guidelines. This action cannot be undone.',
                'reason' => 'banned',
                'code' => 'ACCOUNT_BANNED',
            ], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been temporarily suspended. Please contact support to regain access.',
                'reason' => 'suspended',
                'code' => 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        $tokens = $this->issueTokenPair($user);

        return response()->json([
            'success' => true,
            ...$tokens,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => ($a = $user->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/'.$a)) : null,
                'role' => $user->roleName ?? 'user',
            ],
        ]);
    }

    // PROFILE
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_url' => $user->avatar ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/'.$user->avatar)) : null,
            'bio' => $user->bio,
            'role' => $user->roleName ?? 'user',
            'role_display' => $user->role?->display_name ?? 'User',
            'permissions' => $user->role?->permissions->pluck('name') ?? [],
            'status' => $user->status ?? 'active',
            'email_verified_at' => $user->email_verified_at,
            'profile_completed' => (bool) ($user->profile_completed ?? false),
            'total_xp' => (int) ($user->total_xp ?? 0),
            'current_level' => (int) ($user->current_level ?? 1),
            'created_at' => $user->created_at,
        ]);
    }

    // UPDATE PROFILE (phone/email changes must go through OTP endpoints)
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|string',
            'gender' => 'nullable|string',
            'interest' => 'nullable|string',
            'expertise_regions' => 'nullable|array',
            'expertise_regions.*' => 'string',
        ]);

        // Phone/email changes are NOT allowed here — must use OTP endpoints
        // (requestPhoneChange/verifyPhoneChange, requestEmailChange/verifyEmailChange)

        $user->update($validated);

        // If the user was still profile-incomplete, a completed bio unlocks them
        if (! ($user->profile_completed ?? false)
            && ! empty($validated['bio'])
            && mb_strlen($validated['bio']) >= 10) {
            $user->update(['profile_completed' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => array_merge($user->fresh()->toArray(), [
                'avatar_url' => $user->fresh()->avatar ? (str_starts_with($user->fresh()->avatar, 'http') ? $user->fresh()->avatar : asset('storage/'.$user->fresh()->avatar)) : null,
            ]),
        ]);
    }

    // ── Phone/Email Change with OTP + Yearly Limit ──────────────────────

    /**
     * Request phone change — sends OTP to the new phone number.
     * Only allowed once per year.
     */
    public function requestPhoneChange(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20|unique:users,phone',
        ]);

        $user = $request->user();
        $newPhone = $request->input('phone');

        // Check yearly limit
        if ($user->phone_changed_at && Carbon::parse($user->phone_changed_at)->diffInDays(Carbon::now()) < 365) {
            $daysLeft = 365 - Carbon::parse($user->phone_changed_at)->diffInDays(Carbon::now());

            return response()->json([
                'success' => false,
                'message' => "Phone can only be changed once per year. Try again in {$daysLeft} days.",
            ], 422);
        }

        // Generate OTP (6 digits)
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = hash('sha256', $otp);

        // Store in session with expiry
        session([
            'phone_change_otp' => $otpHash,
            'phone_change_new' => $newPhone,
            'phone_change_expires' => now()->addMinutes(10),
            'phone_change_user_id' => $user->id,
        ]);

        // Send OTP via SMS
        SmsService::send($newPhone, "Your Oripori verification code: {$otp}. Valid for 10 minutes.");

        return response()->json([
            'success' => true,
            'message' => "OTP sent to {$newPhone}",
        ]);
    }

    /**
     * Verify phone change — confirms OTP and updates the phone.
     */
    public function verifyPhoneChange(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Check session data
        $storedHash = session('phone_change_otp');
        $newPhone = session('phone_change_new');
        $expires = session('phone_change_expires');
        $userId = session('phone_change_user_id');

        if (! $storedHash || ! $newPhone || ! $expires || ! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'No phone change request found. Please request a new OTP.',
            ], 422);
        }

        if ($userId != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request.',
            ], 422);
        }

        if (Carbon::parse($expires)->isPast()) {
            session()->forget(['phone_change_otp', 'phone_change_new', 'phone_change_expires', 'phone_change_user_id']);

            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ], 422);
        }

        $otpHash = hash('sha256', $request->input('otp'));
        if (! hash_equals($storedHash, $otpHash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 422);
        }

        // Update phone
        $user->update([
            'phone' => $newPhone,
            'phone_changed_at' => Carbon::now(),
        ]);

        // Clear session
        session()->forget(['phone_change_otp', 'phone_change_new', 'phone_change_expires', 'phone_change_user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Phone number updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Request email change — sends OTP to the new email.
     * Only allowed once per year.
     */
    public function requestEmailChange(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
        ]);

        $user = $request->user();
        $newEmail = $request->input('email');

        // Check yearly limit
        if ($user->email_changed_at && Carbon::parse($user->email_changed_at)->diffInDays(Carbon::now()) < 365) {
            $daysLeft = 365 - Carbon::parse($user->email_changed_at)->diffInDays(Carbon::now());

            return response()->json([
                'success' => false,
                'message' => "Email can only be changed once per year. Try again in {$daysLeft} days.",
            ], 422);
        }

        // Generate OTP (6 digits)
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = hash('sha256', $otp);

        // Store in session with expiry
        session([
            'email_change_otp' => $otpHash,
            'email_change_new' => $newEmail,
            'email_change_expires' => now()->addMinutes(10),
            'email_change_user_id' => $user->id,
        ]);

        // Send OTP via email
        Mail::raw(
            "Your Oripori email verification code: {$otp}\n\nThis code is valid for 10 minutes.\n\nIf you did not request this change, please ignore this email.",
            function ($message) use ($newEmail) {
                $message->to($newEmail)
                    ->subject('Oripori - Email Verification Code');
            }
        );

        return response()->json([
            'success' => true,
            'message' => "OTP sent to {$newEmail}",
        ]);
    }

    /**
     * Verify email change — confirms OTP and updates the email.
     */
    public function verifyEmailChange(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Check session data
        $storedHash = session('email_change_otp');
        $newEmail = session('email_change_new');
        $expires = session('email_change_expires');
        $userId = session('email_change_user_id');

        if (! $storedHash || ! $newEmail || ! $expires || ! $userId) {
            return response()->json([
                'success' => false,
                'message' => 'No email change request found. Please request a new OTP.',
            ], 422);
        }

        if ($userId != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request.',
            ], 422);
        }

        if (Carbon::parse($expires)->isPast()) {
            session()->forget(['email_change_otp', 'email_change_new', 'email_change_expires', 'email_change_user_id']);

            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ], 422);
        }

        $otpHash = hash('sha256', $request->input('otp'));
        if (! hash_equals($storedHash, $otpHash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP. Please try again.',
            ], 422);
        }

        // Update email
        $user->update([
            'email' => $newEmail,
            'email_changed_at' => Carbon::now(),
        ]);

        // Clear session
        session()->forget(['email_change_otp', 'email_change_new', 'email_change_expires', 'email_change_user_id']);

        return response()->json([
            'success' => true,
            'message' => 'Email updated successfully.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Check remaining days before phone/email can be changed.
     */
    public function checkChangeLimits(Request $request)
    {
        $user = $request->user();

        $phoneDays = null;
        if ($user->phone_changed_at && Carbon::parse($user->phone_changed_at)->diffInDays(Carbon::now()) < 365) {
            $phoneDays = 365 - Carbon::parse($user->phone_changed_at)->diffInDays(Carbon::now());
        }

        $emailDays = null;
        if ($user->email_changed_at && Carbon::parse($user->email_changed_at)->diffInDays(Carbon::now()) < 365) {
            $emailDays = 365 - Carbon::parse($user->email_changed_at)->diffInDays(Carbon::now());
        }

        return response()->json([
            'success' => true,
            'data' => [
                'phone_change_days_remaining' => $phoneDays,
                'email_change_days_remaining' => $emailDays,
                'phone_can_change' => $phoneDays === null,
                'email_can_change' => $emailDays === null,
            ],
        ]);
    }

    // DELETE ACCOUNT (anonymize + revoke + purge cascading data)
    public function destroy(Request $request)
    {
        $request->validate([
            'confirmation' => 'required|string',
        ]);

        $user = $request->user();

        // Require explicit confirmation: user must type their email
        if ($request->input('confirmation') !== $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Please type your email address to confirm account deletion.',
            ], 422);
        }

        $service = new AccountDeletionService;
        $result = $service->delete($user, 'user_requested');

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Your account has been deleted. Some financial and audit records are retained in anonymized form as required by law.',
                'retained_items' => $result['retained_items'] ?? [],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'] ?? 'Account deletion failed. Please try again or contact support.',
        ], 500);
    }

    /**
     * Record consent for a legal document.
     */
    private function recordConsent(User $user, string $documentType, Request $request): void
    {
        $document = LegalDocument::where('type', $documentType)
            ->where('is_published', true)
            ->orderByDesc('published_at')
            ->first();

        if (! $document) {
            // No published document exists — record acceptance of the default version
            $version = '1.0';
            $documentId = null;
            $hash = hash('sha256', 'default');
        } else {
            $version = $document->version ?? '1.0';
            $documentId = $document->id;
            $hash = hash('sha256', $document->content ?? '');
        }

        LegalDocumentAcceptance::create([
            'user_id' => $user->id,
            'legal_document_id' => $documentId,
            'document_type' => $documentType,
            'document_version' => $version,
            'document_hash' => $hash,
            'accepted_at' => now(),
            'app_version' => $request->input('app_version'),
            'platform' => $request->input('platform'),
            'ip_address' => $request->ip(),
            'user_agent' => substr($request->userAgent(), 0, 500),
        ]);
    }

    /**
     * Return which legal documents the authenticated user still needs to accept
     * (or re-accept because a newer version was published).
     */
    public function legalAcceptanceStatus(Request $request)
    {
        $user = $request->user();
        $requiredTypes = ['terms_conditions', 'privacy_policy'];
        $pending = [];

        foreach ($requiredTypes as $type) {
            if (LegalDocumentAcceptance::needsReAcceptance($user, $type)) {
                $doc = LegalDocument::getActive($type);
                $pending[] = [
                    'document_type' => $type,
                    'latest_version' => $doc?->version ?? '1.0',
                    'title' => $doc?->title ?? $type,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'requires_acceptance' => count($pending) > 0,
            'pending_documents' => $pending,
        ]);
    }

    /**
     * Record the user's acceptance of the specified legal documents.
     */
    public function acceptLegal(Request $request)
    {
        $request->validate([
            'documents' => 'required|array|min:1',
            'documents.*' => 'string|in:terms_conditions,privacy_policy',
            'app_version' => 'nullable|string|max:20',
            'platform' => 'nullable|string|max:20',
        ]);

        $user = $request->user();

        foreach ($request->input('documents') as $documentType) {
            $this->recordConsent($user, $documentType, $request);
        }

        return response()->json([
            'success' => true,
            'message' => 'Legal acceptance recorded successfully.',
        ]);
    }

    // SOCIAL LOGIN (Google)
    public function socialLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
            'terms_accepted' => 'sometimes|accepted',
            'privacy_accepted' => 'sometimes|accepted',
            'age_confirmed' => 'sometimes|accepted',
            'app_version' => 'nullable|string|max:20',
        ]);

        try {
            // Verify the ID token with Google's public keys
            $payload = $this->verifyGoogleToken($request->id_token);

            if (! $payload || empty($payload['sub'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token',
                ], 401);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? 'Google User';
            $avatar = $payload['picture'] ?? null;

            // Find existing social account or user by email
            $socialAccount = SocialAccount::where('provider', 'google')
                ->where('provider_id', $googleId)
                ->first();

            // A social account whose linked user was deleted (orphan) must not
            // shadow the email lookup — fall back to email so we can re-link.
            $user = ($socialAccount && $socialAccount->user) ? $socialAccount->user : null;

            if (! $user && $email) {
                $user = User::where('email', $email)->first();
            }

            $isNewUser = ! $user;

            // Validate consent for new users (backend determines new-user status)
            if ($isNewUser) {
                $validator = Validator::make($request->all(), [
                    'terms_accepted' => 'required|accepted',
                    'privacy_accepted' => 'required|accepted',
                    'age_confirmed' => 'required|accepted',
                ]);
                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You must accept the Terms of Use, Privacy Policy, and confirm you are 18 or older to create an account.',
                        'errors' => $validator->errors(),
                    ], 422);
                }
            }

            if (! $user) {
                // Create new user. Handle the race where the email was
                // registered between our lookup and this insert.
                try {
                    $user = User::create([
                        'name' => $name,
                        'email' => $email ?? 'google_'.$googleId.'@placeholder.local',
                        'phone' => null,
                        'password' => Str::random(32),
                        'avatar' => $avatar,
                        'bio' => null,
                        'badges' => [],
                        'expertise_regions' => [],
                        'settings' => [],
                        'profile_completed' => $email !== null,
                    ]);
                } catch (QueryException $e) {
                    if ((string) $e->getCode() !== '23000' || ! $email) {
                        throw $e;
                    }
                    $user = User::where('email', $email)->first();
                    if (! $user) {
                        throw $e;
                    }
                }
            }

            if ($socialAccount) {
                // Heal orphaned/desynced link (e.g. linked user was deleted)
                if ((int) $socialAccount->user_id !== (int) $user->id) {
                    $socialAccount->user_id = $user->id;
                    $socialAccount->provider_email = $email;
                    $socialAccount->provider_avatar = $avatar;
                    $socialAccount->save();
                }
            } else {
                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_id' => $googleId,
                    'provider_email' => $email,
                    'provider_avatar' => $avatar,
                ]);
            }

            // Update avatar if not set
            if (empty($user->avatar) && $avatar) {
                $user->update(['avatar' => $avatar]);
            }

            // Record consent for new users
            if ($isNewUser) {
                $this->recordConsent($user, 'terms_conditions', $request);
                $this->recordConsent($user, 'privacy_policy', $request);
            }

            if ($user->status === 'banned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been permanently banned due to violation of our community guidelines. This action cannot be undone.',
                    'reason' => 'banned',
                    'code' => 'ACCOUNT_BANNED',
                ], 403);
            }

            if ($user->status === 'suspended') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been temporarily suspended. Please contact support to regain access.',
                    'reason' => 'suspended',
                    'code' => 'ACCOUNT_SUSPENDED',
                ], 403);
            }

            $tokens = $this->issueTokenPair($user);

            return response()->json([
                'success' => true,
                'message' => 'Logged in with Google successfully',
                ...$tokens,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => ($a = $user->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/'.$a)) : null,
                    'role' => $user->roleName ?? 'user',
                    'is_new_user' => $isNewUser,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Google auth failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed. Please try again.',
            ], 401);
        }
    }

    /**
     * Google pre-check: determine if the Google account maps to a new or existing
     * user WITHOUT creating anything.  Flutter calls this first so it knows
     * whether to show the consent screen before calling socialLogin.
     */
    public function googlePreCheck(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $payload = $this->verifyGoogleToken($request->id_token);

            if (! $payload || empty($payload['sub'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Google token',
                ], 401);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'] ?? null;

            $socialAccount = SocialAccount::where('provider', 'google')
                ->where('provider_id', $googleId)
                ->first();

            if ($socialAccount) {
                $user = $socialAccount->user;
            } elseif ($email) {
                $user = User::where('email', $email)->first();
            } else {
                $user = null;
            }

            return response()->json([
                'success' => true,
                'is_new_user' => ! $user,
            ]);
        } catch (\Exception $e) {
            Log::warning('Google pre-check failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Google pre-check failed. Please try again.',
            ], 401);
        }
    }

    private function verifyGoogleToken(string $idToken): ?array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        $clientId = config('services.google.client_id');
        $androidClientId = config('services.google.android_client_id');
        $aud = $payload['aud'] ?? null;
        $azp = $payload['azp'] ?? null;

        $validAudiences = array_filter([$clientId, $androidClientId, $azp]);

        if (! in_array($aud, $validAudiences)) {
            return null;
        }

        // Validate the issuer
        $iss = $payload['iss'] ?? '';
        if ($iss !== 'accounts.google.com' && $iss !== 'https://accounts.google.com') {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    // LOGOUT
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    // COMPLETE PROFILE
    public function completeProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|string',
            'phone' => 'sometimes|nullable|string|min:7|max:20',
        ]);

        $update = [
            'bio' => $validated['bio'] ?? $user->bio,
            'avatar' => $validated['avatar'] ?? $user->avatar,
            'phone' => $validated['phone'] ?? $user->phone,
        ];
        if (Schema::hasColumn('users', 'profile_completed')) {
            $update['profile_completed'] = true;
        }

        $user->update($update);

        return response()->json([
            'success' => true,
            'message' => 'Profile completed successfully',
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar ? (str_starts_with($user->avatar, 'http') ? $user->avatar : asset('storage/'.$user->avatar)) : null,
                'bio' => $user->bio,
                'profile_completed' => (bool) $user->profile_completed,
            ],
        ]);
    }

    // CHECK PROFILE COMPLETION STATUS
    public function checkProfileStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'profile_completed' => (bool) ($user->profile_completed ?? false),
            'missing_fields' => $this->getMissingProfileFields($user),
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $token = Password::createToken($user);

        $user->sendPasswordResetNotification($token);

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = $password;
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password has been reset successfully.',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => __($status),
        ], 400);
    }

    public function refreshToken(Request $request)
    {
        $plain = $request->input('refresh_token') ?? $request->bearerToken();
        if (! $plain) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is required.',
            ], 401);
        }

        $token = PersonalAccessToken::findToken($plain);
        if (! $token || $token->name !== 'refresh') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid refresh token.',
            ], 401);
        }

        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Refresh token expired. Please log in again.',
            ], 401);
        }

        $user = $token->tokenable;
        if (! $user || in_array($user->status, ['banned', 'suspended'])) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Account is not active.',
                'code' => $user?->status === 'banned' ? 'ACCOUNT_BANNED' : 'ACCOUNT_SUSPENDED',
            ], 403);
        }

        // Rotate: revoke the used refresh token and issue a fresh pair
        $token->delete();
        $tokens = $this->issueTokenPair($user);

        return response()->json([
            'success' => true,
            ...$tokens,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => ($a = $user->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/'.$a)) : null,
                'role' => $user->roleName ?? 'user',
            ],
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|max:6',
        ]);

        $user = $request->user();
        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        // Simple OTP verification - check against stored hash (timing-safe)
        $storedOtp = cache('email_otp_'.$user->id);
        if (! $storedOtp || ! hash_equals($storedOtp, (string) $request->otp)) {
            // Brute-force lock: 5 wrong attempts invalidate the OTP entirely.
            $attemptKey = 'email_otp_attempts_'.$user->id;
            $attempts = (int) cache($attemptKey, 0) + 1;
            cache([$attemptKey => $attempts], now()->addMinutes(15));
            if ($attempts >= 5) {
                cache()->forget('email_otp_'.$user->id);
                cache()->forget($attemptKey);

                return response()->json([
                    'success' => false,
                    'message' => 'Too many incorrect codes. Request a new code.',
                ], 429, ['Retry-After' => '3600']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        // Reset attempt counter on success
        cache()->forget('email_otp_attempts_'.$user->id);
        $user->update(['email_verified_at' => now()]);
        cache()->forget('email_otp_'.$user->id);

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully.',
        ]);
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified.',
            ]);
        }

        $otp = $this->sendVerificationOtp($user);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
        ]);
    }

    /**
     * Generate, cache and (best-effort) email a 6-digit verification OTP.
     * Returns the OTP so clients can proceed without a mail server in dev.
     */
    private function sendVerificationOtp($user): string
    {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        cache(['email_otp_'.$user->id => $otp], now()->addMinutes(10));
        // Fresh code = fresh attempts
        cache()->forget('email_otp_attempts_'.$user->id);

        try {
            Mail::raw(
                "Your Nepal Smart Travel verification code is: {$otp}\n\nIt expires in 10 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Verify your email — Nepal Smart Travel');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('OTP email send failed: '.$e->getMessage());
        }

        return $otp;
    }

    private function getMissingProfileFields($user)
    {
        $missing = [];

        // Bio and phone are now optional — no longer required for profile completion

        return $missing;
    }

    // PHONE VERIFICATION — Send OTP via Firebase Push Notification
    public function sendPhoneOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:20',
        ]);

        $user = $request->user();
        $phone = $request->phone;

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store in Redis for 10 minutes
        Cache::put('phone_otp_'.$user->id, [
            'otp' => $otp,
            'phone' => $phone,
        ], now()->addMinutes(10));

        // Fresh attempts
        Cache::forget('phone_otp_attempts_'.$user->id);

        // Send via Firebase Push Notification to user's devices
        $tokens = PushToken::where('user_id', $user->id)
            ->where('subscribed', true)
            ->pluck('fcm_token')
            ->toArray();

        if (! empty($tokens)) {
            foreach ($tokens as $token) {
                $this->sendFcmNotification($token, [
                    'title' => 'Nepal Smart Travel',
                    'body' => "Your phone verification code is: {$otp}",
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent via app notification',
        ]);
    }

    // PHONE VERIFICATION — Verify OTP
    public function verifyPhone(Request $request)
    {
        $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $cached = Cache::get('phone_otp_'.$user->id);

        if (! $cached) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code expired. Please request a new one.',
            ], 422);
        }

        // Brute-force protection
        $attemptsKey = 'phone_otp_attempts_'.$user->id;
        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.',
            ], 429);
        }

        if (! hash_equals($cached['otp'], (string) $request->otp)) {
            Cache::increment($attemptsKey);
            Cache::put($attemptsKey, Cache::get($attemptsKey, 0), now()->addMinutes(15));

            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. '.(5 - $attempts - 1).' attempts remaining.',
            ], 422);
        }

        // Verified! Update user
        $user->update([
            'phone' => $cached['phone'],
            'phone_verified_at' => now(),
        ]);

        // Clean up
        Cache::forget('phone_otp_'.$user->id);
        Cache::forget($attemptsKey);

        // Award +50 XP for verification
        $user->increment('total_xp', 50);

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully! +50 XP earned!',
            'phone' => $user->phone,
        ]);
    }

    // Send Firebase Cloud Messaging notification
    private function sendFcmNotification(string $fcmToken, array $data): void
    {
        try {
            $serverKey = config('services.firebase.server_key', env('FIREBASE_SERVER_KEY'));
            if (empty($serverKey)) {
                Log::warning('Firebase server key not configured');

                return;
            }

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: key='.$serverKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'to' => $fcmToken,
                    'notification' => $data,
                    'data' => $data,
                    'priority' => 'high',
                ]),
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            Log::warning('FCM notification failed: '.$e->getMessage());
        }
    }
}
