<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('role:supervisor')
     *   ->middleware('role:supervisor,staff')   ← multiple allowed roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Not logged in → back to the consumer board, which carries the navbar
        // login form. There is no standalone /login page any more, and no
        // separate "you must sign in" page either: one page does both jobs.
        if (! Auth::check()) {
            return redirect()
                ->route('home')
                ->with('auth_intent', 'Sign in to reach that page.');
        }

        $user = Auth::user();

        // Inactive account → force logout
        if ($user->status !== 'active') {
            Auth::logout();

            return redirect()
                ->route('home')
                ->withErrors(['username' => 'Your account has been deactivated.']);
        }

        // Role not allowed → 403
        if (! in_array($user->role, $roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
