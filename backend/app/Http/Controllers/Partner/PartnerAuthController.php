<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\TravelPartner;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PartnerAuthController extends Controller
{
    private const BUSINESS_TYPES = [
        'hotel', 'restaurant', 'cafe', 'shop', 'vehicle_rental', 'guide', 'adventure', 'other',
    ];

    public function showRegister()
    {
        if (Auth::check()) return redirect()->route('partner.dashboard');
        return view('partner.register', ['types' => self::BUSINESS_TYPES]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'required|unique:users',
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
            return back()->withErrors(['email' => 'Business role not configured. Contact admin.'])->onlyInput('email');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role_id' => $businessRole->id,
            'avatar' => null,
            'bio' => null,
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

        return redirect()->route('partner.pending');
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
                return back()->withErrors(['email' => 'This account does not have business partner access.'])->onlyInput('email');
            }
            if (!$user->business) {
                return redirect()->route('partner.business-form');
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

        return redirect()->route('partner.pending')->with('success', 'Business profile submitted. Waiting for admin verification.');
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
}
