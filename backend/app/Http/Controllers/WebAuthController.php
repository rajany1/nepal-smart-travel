<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ModeratorService;

class WebAuthController extends Controller
{
    private ModeratorService $moderatorService;

    public function __construct(ModeratorService $moderatorService)
    {
        $this->moderatorService = $moderatorService;
    }

    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isAdmin() || $user->isModerator()) {
                return redirect()->route('admin.dashboard');
            }
            Auth::logout();
        }
        return view('admin.login');
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
            if (!$user->isAdmin() && !$user->isModerator()) {
                $this->moderatorService->logSecurity(
                    'security.unauthorized-login',
                    "Regular user '{$user->email}' tried to access admin panel",
                    $user
                );
                Auth::logout();
                return back()->withErrors(['email' => 'You do not have admin or moderator access.'])->onlyInput('email');
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        $this->moderatorService->logSecurity(
            'security.login-failed',
            "Failed login attempt for '{$request->email}'"
        );

        return back()->withErrors(['email' => 'Invalid credentials.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
}