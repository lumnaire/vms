<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // ─── Show Login Form ─────────────────────────────────────────
    public function showForm()
    {
        // Redirect already logged-in users to their dashboard
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user()->role);
        }

        return view('auth.login');
    }

    // ─── Handle Login ────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = $request->only('username', 'password');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Invalid username or password.']);
        }

        $user = Auth::user();

        // Block inactive accounts
        if ($user->status !== 'active') {
            Auth::logout();
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Your account is inactive. Please contact the supervisor.']);
        }

        $request->session()->regenerate();

        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => 'login',
            'description' => "{$user->name} ({$user->role}) logged in.",
        ]);

        return $this->redirectByRole($user->role);
    }

    // ─── Handle Logout ───────────────────────────────────────────
    public function logout(Request $request)
    {
        $userId   = Auth::id();
        $userName = Auth::user()?->name;
        $userRole = Auth::user()?->role;
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        ActivityLog::create([
            'user_id'     => $userId,
            'action'      => 'logout',
            'description' => "{$userName} ({$userRole}) logged out.",
        ]);

        return redirect()->route('login');
    }

    // ─── Role-Based Redirect ─────────────────────────────────────
    private function redirectByRole(string $role)
    {
        return match ($role) {
            'supervisor' => redirect()->route('supervisor.dashboard'),
            'staff'      => redirect()->route('staff.dashboard'),
            'vendor'     => redirect()->route('vendor.dashboard'),
            default      => redirect('/'),
        };
    }
}