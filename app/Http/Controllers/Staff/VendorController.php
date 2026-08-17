<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class VendorController extends Controller
{
    // ─── List all vendor accounts ─────────────────────────────────
    public function index(Request $request)
    {
        $totalVendors    = User::where('role', 'vendor')->count();
        $activeVendors   = User::where('role', 'vendor')->where('status', 'active')->count();
        $inactiveVendors = User::where('role', 'vendor')->where('status', 'inactive')->count();

        $query = User::where('role', 'vendor')->with('vendorProfile');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhereHas('vendorProfile', fn($sub) => $sub->where('stall_number', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            if (in_array($status, ['active', 'inactive'])) {
                $query->where('status', $status);
            }
        }

        $vendors = $query->latest()->paginate(10);

        return view('staff.vendors', compact(
            'vendors',
            'totalVendors',
            'activeVendors',
            'inactiveVendors',
        ));
    }

    // ─── Create a new vendor account ──────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'username'     => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'stall_number' => ['required', 'string', 'max:20', 'unique:vendor_profiles,stall_number'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique'     => 'That username is already taken.',
            'stall_number.unique' => 'That stall number is already assigned to another vendor.',
            'password.confirmed'  => 'Password confirmation does not match.',
        ]);

        $vendor = User::create([
            'name'       => $request->name,
            'username'   => $request->username,
            'password'   => Hash::make($request->password),
            'role'       => 'vendor',
            'status'     => 'active',
            'created_by' => Auth::id(),
        ]);

        $vendor->vendorProfile()->create([
            'stall_number' => strtoupper(trim($request->stall_number)),
        ]);

        return redirect()->route('staff.vendors.index')
            ->with('success', "Vendor account for \"{$request->name}\" created successfully.");
    }

    // ─── Update an existing vendor account ────────────────────────
    public function update(Request $request, User $user)
    {
        abort_if($user->role !== 'vendor', 403, 'You can only edit vendor accounts.');

        $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'username'     => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user->id)],
            'stall_number' => ['required', 'string', 'max:20',
                               Rule::unique('vendor_profiles', 'stall_number')->ignore($user->vendorProfile?->id)],
            'password'     => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'username.alpha_dash' => 'Username may only contain letters, numbers, dashes, and underscores.',
            'username.unique'     => 'That username is already taken.',
            'stall_number.unique' => 'That stall number is already assigned to another vendor.',
            'password.confirmed'  => 'Password confirmation does not match.',
        ]);

        $user->name     = $request->name;
        $user->username = $request->username;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        $stallNum = strtoupper(trim($request->stall_number));

        if ($user->vendorProfile) {
            $user->vendorProfile->update(['stall_number' => $stallNum]);
        } else {
            $user->vendorProfile()->create(['stall_number' => $stallNum]);
        }

        return redirect()->route('staff.vendors.index')
            ->with('success', "Vendor account for \"{$user->name}\" updated successfully.");
    }

    // ─── Toggle active / inactive status ─────────────────────────
    public function toggleStatus(User $user)
    {
        abort_if($user->role !== 'vendor', 403, 'You can only toggle vendor accounts.');

        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $action = $user->status === 'active' ? 'activated' : 'deactivated';

        return redirect()->route('staff.vendors.index')
            ->with('success', "\"{$user->name}\" has been {$action}.");
    }

    // ─── Delete an inactive vendor account ────────────────────────
    public function destroy(User $user)
    {
        abort_if($user->role !== 'vendor', 403, 'You can only delete vendor accounts.');
        abort_if($user->status !== 'inactive', 403, 'You can only delete inactive vendor accounts.');

        $name = $user->name;
        $user->delete();

        return redirect()->route('staff.vendors.index')
            ->with('success', "Vendor account for \"$name\" has been permanently deleted.");
    }
}