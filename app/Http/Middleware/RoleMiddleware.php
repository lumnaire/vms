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
        // Not logged in → redirect to login
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Inactive account → force logout
        if ($user->status !== 'active') {
            Auth::logout();
            return redirect()->route('login')
                ->withErrors(['username' => 'Your account has been deactivated.']);
        }

        // Role not allowed → 403
        if (!in_array($user->role, $roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}