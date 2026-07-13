<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\SocialAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // REGISTER
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password, // hashed via model
            'avatar' => null,
            'bio' => null,
        ];
        if (Schema::hasColumn('users', 'profile_completed')) {
            $data['profile_completed'] = false;
        }

        $user = User::create($data);

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'access_token' => $token,
            'data' => $user
        ]);
    }

    // LOGIN
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
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

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'data' => $user
        ]);
    }

    // PROFILE
    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
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
            'avatar_url' => $user->avatar,
            'bio' => $user->bio,
            'role' => $user->roleName ?? 'user',
            'role_display' => $user->role?->display_name ?? 'User',
            'permissions' => $user->role?->permissions->pluck('name') ?? [],
            'status' => $user->status ?? 'active',
            'profile_completed' => (bool)($user->profile_completed ?? false),
            'total_xp' => (int)($user->total_xp ?? 0),
            'current_level' => (int)($user->current_level ?? 1),
            'created_at' => $user->created_at,
        ]);
    }

    // UPDATE PROFILE
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $request->user()->id,
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|string',
            'gender' => 'nullable|string',
            'interest' => 'nullable|string',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()
        ]);
    }

    // SOCIAL LOGIN (Google)
    public function socialLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            // Verify the ID token with Google's public keys
            $payload = $this->verifyGoogleToken($request->id_token);

            if (!$payload || empty($payload['sub'])) {
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

            if ($socialAccount) {
                $user = $socialAccount->user;
            } elseif ($email) {
                $user = User::where('email', $email)->first();
            } else {
                $user = null;
            }

            if (!$user) {
                // Create new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email ?? 'google_' . $googleId . '@placeholder.local',
                    'phone' => null,
                    'password' => Str::random(32),
                    'avatar' => $avatar,
                    'bio' => null,
                    'profile_completed' => $email !== null,
                ]);

                // Link social account
                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_id' => $googleId,
                    'provider_email' => $email,
                    'provider_avatar' => $avatar,
                ]);
            } elseif (!$socialAccount) {
                // Link existing user to this Google account
                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_id' => $googleId,
                    'provider_email' => $email,
                    'provider_avatar' => $avatar,
                ]);

                // Update avatar if not set
                if (empty($user->avatar) && $avatar) {
                    $user->update(['avatar' => $avatar]);
                }
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

            $token = $user->createToken('app')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Logged in with Google successfully',
                'access_token' => $token,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed: ' . $e->getMessage(),
            ], 401);
        }
    }

    private function verifyGoogleToken(string $idToken): ?array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $payload = $response->json();

        $clientId = config('services.google.client_id');
        $androidClientId = config('services.google.android_client_id');
        $aud = $payload['aud'] ?? null;
        $azp = $payload['azp'] ?? null;

        $validAudiences = array_filter([$clientId, $androidClientId, $azp]);

        if (!in_array($aud, $validAudiences)) {
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
            'message' => 'Logged out successfully'
        ]);
    }

    // COMPLETE PROFILE
    public function completeProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'bio' => 'required|string|min:10|max:500',
            'avatar' => 'nullable|string',
            'phone' => 'sometimes|string|min:7|max:20',
        ]);

        $update = [
            'bio' => $validated['bio'],
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
                'avatar_url' => $user->avatar,
                'bio' => $user->bio,
                'profile_completed' => (bool)$user->profile_completed,
            ]
        ]);
    }

    // CHECK PROFILE COMPLETION STATUS
    public function checkProfileStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'profile_completed' => (bool)($user->profile_completed ?? false),
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
            'password' => 'required|string|min:8|confirmed',
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
        $user = $request->user();
        $user->tokens()->delete();
        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'data' => $user,
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

        // Simple OTP verification - check against stored hash
        $storedOtp = cache('email_otp_' . $user->id);
        if (!$storedOtp || $storedOtp !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP.',
            ], 400);
        }

        $user->update(['email_verified_at' => now()]);
        cache()->forget('email_otp_' . $user->id);

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

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        cache(['email_otp_' . $user->id => $otp], now()->addMinutes(10));

        // TODO: Send OTP via email using Mail facade
        // Mail::to($user->email)->send(new EmailVerificationOtp($otp));

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email.',
        ]);
    }

    private function getMissingProfileFields($user)
    {
        $missing = [];

        if (empty($user->bio)) {
            $missing[] = 'bio';
        }

        if (empty($user->phone)) {
            $missing[] = 'phone';
        }

        return $missing;
    }
}