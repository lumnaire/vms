<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    // ─── List all staff accounts ─────────────────────────────────
    public function index(Request $request)
    {
        $totalStaff    = User::where('role', 'staff')->count();
        $activeStaff   = User::where('role', 'staff')->where('status', 'active')->count();
        $inactiveStaff = User::where('role', 'staff')->where('status', 'inactive')->count();

        // Build query with search and filter
        $query = User::where('role', 'staff');

        // Search by name or username
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            if (in_array($status, ['active', 'inactive'])) {
                $query->where('status', $status);
            }
        }

        $staff = $query->latest()->paginate(10);

        return view('supervisor.staff', compact(
            'staff',
            'totalStaff',
            'activeStaff',
            'inactiveStaff',
        ));
    }

    // ─── Create a new staff account ──────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique'     => 'That username is already taken.',
            'password.confirmed'  => 'Password confirmation does not match.',
        ]);

        User::create([
            'name'       => $request->name,
            'username'   => $request->username,
            'password'   => Hash::make($request->password),
            'role'       => 'staff',
            'status'     => 'active',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('supervisor.staff.index')
            ->with('success', "Staff account for \"{$request->name}\" created successfully.");
    }

    // ─── Update an existing staff account ────────────────────────
    public function update(Request $request, User $user)
    {
        abort_if($user->role !== 'staff', 403, 'You can only edit staff accounts.');

        $request->validate([
            'name'     => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique'     => 'That username is already taken.',
            'password.confirmed'  => 'Password confirmation does not match.',
        ]);

        $user->name     = $request->name;
        $user->username = $request->username;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()->route('supervisor.staff.index')
            ->with('success', "Staff account for \"{$user->name}\" updated successfully.");
    }

    // ─── Toggle active / inactive status ─────────────────────────
    public function toggleStatus(User $user)
    {
        abort_if($user->role !== 'staff', 403, 'You can only toggle staff accounts.');

        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $action = $user->status === 'active' ? 'activated' : 'deactivated';

        return redirect()->route('supervisor.staff.index')
            ->with('success', "\"{$user->name}\" has been {$action}.");
    }
    // ─── Delete a staff account (only inactive accounts) ────────
    public function destroy(User $user)
    {
        abort_if($user->role !== 'staff', 403, 'You can only delete staff accounts.');
        abort_if($user->status !== 'inactive', 403, 'You can only delete inactive staff accounts.');

        $name = $user->name;
        $user->delete();

        return redirect()->route('supervisor.staff.index')
            ->with('success', "Staff account for \"$name\" has been permanently deleted.");
    }}