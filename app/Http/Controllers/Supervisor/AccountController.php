<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    // ─── Show the supervisor's own account settings ──────────────
    public function edit()
    {
        return view('supervisor.account', ['user' => Auth::user()]);
    }

    // ─── Update name / username ──────────────────────────────────
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'username'         => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'current_password' => ['required', 'current_password'],
        ], [
            'username.alpha_dash'      => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique'          => 'That username is already taken.',
            'current_password.current_password' => 'The password you entered is incorrect.',
        ]);

        $oldUsername = $user->username;

        $user->name     = $request->name;
        $user->username = $request->username;
        $user->save();

        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => 'update',
            'description' => $oldUsername === $user->username
                ? "{$user->name} (supervisor) updated their account details."
                : "{$user->name} (supervisor) changed their username from \"{$oldUsername}\" to \"{$user->username}\".",
        ]);

        return redirect()->route('supervisor.account.edit')
            ->with('success', 'Your account details have been updated.');
    }

    // ─── Update password ─────────────────────────────────────────
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'The password you entered is incorrect.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.different' => 'Your new password must be different from your current password.',
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        // Keep this session signed in under the new credentials
        $request->session()->regenerate();

        ActivityLog::create([
            'user_id'     => $user->id,
            'action'      => 'update',
            'description' => "{$user->name} (supervisor) changed their password.",
        ]);

        return redirect()->route('supervisor.account.edit')
            ->with('success', 'Your password has been changed.');
    }
}
