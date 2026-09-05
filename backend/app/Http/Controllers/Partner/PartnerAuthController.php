<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TravelPartner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class PartnerAuthController extends Controller
{
    private const BUSINESS_TYPES = [
        'hotel', 'restaurant', 'cafe', 'shop', 'vehicle_rental', 'guide', 'adventure', 'other',
    ];

    public function showRegister()
    {
        if (Auth::check()) {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
        return view('partner.register', ['types' => self::BUSINESS_TYPES]);
    }

    public function register(Request $request)
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|unique:users,phone',
            'password' => 'required|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/',
            'business_name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', self::BUSINESS_TYPES),
            'address' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
        ]);

        $businessRole = Role::where('name', 'business')->first();
        if (!$businessRole) {
            return back()->withErrors(['email' => 'Business role not configured.'])->onlyInput('email');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role_id' => $businessRole->id,
        ]);

        TravelPartner::create([
            'user_id' => $user->id,
            'name' => $data['business_name'],
            'type' => $data['type'],
            'address' => $data['address'] ?? null,
            'district' => $data['district'] ?? null,
            'description' => $data['description'] ?? null,
            'website' => $data['website'] ?? null,
            'phone' => $data['phone'],
            'email' => $data['email'],
            'verification_status' => 'pending',
            'is_active' => true,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('partner.wizard');
    }

    public function wizard()
    {
        $user = Auth::user();

        // Auto-approve when email verified
        if ($user->email_verified_at) {
            $partner = $user->business;
            if ($partner && $partner->verification_status !== 'verified') {
                $partner->update(['verification_status' => 'verified', 'verified_at' => now()]);
            }
            Cache::forget("partner_reg_" . $user->id);
            return redirect()->route('partner.dashboard');
        }

        $partner = $user->business;
        return view('partner.wizard', compact('user', 'partner'));
    }

    public function sendEmailOtp(Request $request)
    {
        $user = $request->user();
        $email = $user->email;

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("partner_email_otp_" . $user->id, $otp, now()->addMinutes(15));
        Cache::forget("partner_email_otp_attempts_" . $user->id);

        \Mail::to($email)->send(new \App\Mail\PartnerOtpMail($otp, $user->name));

        return back()->with('success', "Verification code sent to {$email}. Check your inbox.");
    }

    public function verifyEmailOtp(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $user = $request->user();
        $cached = Cache::get("partner_email_otp_" . $user->id);

        if (!$cached) {
            return back()->withErrors(['otp' => 'Code expired. Request a new one.']);
        }

        $attemptsKey = "partner_email_otp_attempts_" . $user->id;
        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many failed attempts.']);
        }

        if ($cached !== $request->otp) {
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(15));
            $remaining = 5 - $attempts - 1;
            return back()->withErrors(['otp' => "Invalid code. {$remaining} attempts left."]);
        }

        $user->update(['email_verified_at' => now()]);
        Cache::forget("partner_email_otp_" . $user->id);
        Cache::forget($attemptsKey);

        return redirect()->route('partner.wizard')->with('success', 'Email verified!');
    }

    public function sendPhoneOtp(Request $request)
    {
        $user = $request->user();
        $phone = $user->phone;

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("partner_phone_otp_" . $user->id, $otp, now()->addMinutes(10));
        Cache::forget("partner_phone_otp_attempts_" . $user->id);

        // Send via push notification to mobile app
        $tokens = \App\Models\PushToken::where('user_id', $user->id)
            ->where('subscribed', true)
            ->pluck('fcm_token')
            ->toArray();

        foreach ($tokens as $token) {
            $this->sendFcmNotification($token, [
                'title' => 'Nepal Smart Travel',
                'body' => "Your verification code is: {$otp}",
            ]);
        }

        return back()->with('success', "Verification code sent to {$phone}.");
    }

    public function verifyPhone(Request $request)
    {
        $request->validate(['otp' => 'required|string|size:6']);

        $user = $request->user();
        $cached = Cache::get("partner_phone_otp_" . $user->id);

        if (!$cached) {
            return back()->withErrors(['otp' => 'Code expired. Request a new one.']);
        }

        $attemptsKey = "partner_phone_otp_attempts_" . $user->id;
        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many failed attempts.']);
        }

        if ($cached !== $request->otp) {
            Cache::put($attemptsKey, $attempts + 1, now()->addMinutes(15));
            $remaining = 5 - $attempts - 1;
            return back()->withErrors(['otp' => "Invalid code. {$remaining} attempts left."]);
        }

        $user->update(['phone_verified_at' => now()]);
        Cache::forget("partner_phone_otp_" . $user->id);
        Cache::forget($attemptsKey);

        // Auto-approve
        $partner = $user->business;
        if ($partner && $partner->verification_status !== 'verified') {
            $partner->update(['verification_status' => 'verified', 'verified_at' => now()]);
        }

        return redirect()->route('partner.dashboard')->with('success', 'Phone verified! Account is now active.');
    }

    public function showLogin()
    {
        if (Auth::check()) return redirect()->route('partner.dashboard');
        return view('partner.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();
            if (!$user->isBusiness()) {
                Auth::logout();
                return back()->withErrors(['email' => 'No business partner access.'])->onlyInput('email');
            }
            if (!$user->business) {
                return redirect()->route('partner.business-form');
            }
            if (!$user->email_verified_at) {
                return redirect()->route('partner.wizard');
            }
            return redirect()->intended(route('partner.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('partner.login');
    }

    public function businessForm()
    {
        $user = Auth::user();
        return view('partner.business_form', [
            'types' => self::BUSINESS_TYPES,
            'partner' => $user?->business,
        ]);
    }

    public function submitBusinessForm(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isBusiness()) abort(403);

        $data = $request->validate([
            'business_name' => 'required|string|max:255',
            'type' => 'required|in:' . implode(',', self::BUSINESS_TYPES),
            'address' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'website' => 'nullable|url|max:255',
            'phone' => 'required|string|max:30',
        ]);

        $partner = $user->business;
        if ($partner) {
            $partner->update($data + [
                'name' => $data['business_name'],
                'verification_status' => 'pending',
                'rejected_reason' => null,
                'email' => $user->email,
            ]);
        } else {
            $partner = TravelPartner::create([
                'user_id' => $user->id,
                'name' => $data['business_name'],
                'type' => $data['type'],
                'address' => $data['address'] ?? null,
                'district' => $data['district'] ?? null,
                'description' => $data['description'] ?? null,
                'website' => $data['website'] ?? null,
                'phone' => $data['phone'],
                'email' => $user->email,
                'verification_status' => 'pending',
                'is_active' => true,
            ]);
        }

        return redirect()->route('partner.pending')->with('success', 'Business profile submitted.');
    }

    public function pending()
    {
        $user = Auth::user();
        $partner = $user?->business;
        if ($partner && $partner->verification_status === 'verified') {
            return redirect()->route('partner.dashboard');
        }
        return view('partner.pending', compact('partner'));
    }

    private function sendFcmNotification(string $fcmToken, array $data): void
    {
        try {
            $serverKey = config('services.firebase.server_key', env('FIREBASE_SERVER_KEY'));
            if (empty($serverKey)) return;

            $ch = curl_init('https://fcm.googleapis.com/fcm/send');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: key=' . $serverKey,
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
            \Illuminate\Support\Facades\Log::warning('FCM notification failed: ' . $e->getMessage());
        }
    }
}
