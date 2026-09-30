<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    // ─── Handle Login ────────────────────────────────────────────
    public function login(Request $request)
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = $request->only('username', 'password');

        // No "remember me": the session cookie is issued without an expiry so the
        // browser throws it away when the window closes (config/session.php,
        // expire_on_close). Passing `false` here guarantees Laravel never adds
        // the long-lived remember cookie that would survive a browser restart.
        if (!Auth::attempt($credentials, false)) {
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

        // A fresh id for the new session, so a pre-login cookie cannot be
        // replayed. The new cookie also inherits the session-only (no expiry)
        // attribute from the previous one.
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

        // Back to the consumer board, which is also where the login form lives.
        return redirect()->route('home');
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