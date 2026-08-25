@extends('layouts.app')

@section('title', 'My Account')
@section('subtitle', 'Manage your login credentials')

@push('styles')
<style>
    .form-input {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 13.5px;
        color: #334155;
        outline: none;
        transition: border-color 0.15s, box-shadow 0.15s;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .form-input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
    }
    .form-input::placeholder { color: #cbd5e1; }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        letter-spacing: 0.01em;
    }
</style>
@endpush

@section('content')

{{-- ── Flash Message ───────────────────────────────────────────── --}}
@if(session('success'))
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl"
     style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; font-size: 13.5px;">
    <i class="bi bi-check-circle-fill flex-shrink-0" style="color: #10b981; font-size: 15px;"></i>
    <span class="font-medium">{{ session('success') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #34d399;">
        <i class="bi bi-x-lg" style="font-size: 13px;"></i>
    </button>
</div>
@endif

{{-- ── Validation Errors ───────────────────────────────────────── --}}
@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl"
     style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13px;">
    <div class="flex items-center gap-2 font-semibold mb-1.5" style="font-size: 13.5px;">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0" style="color: #ef4444;"></i>
        Please fix the following:
    </div>
    <ul style="list-style: disc; padding-left: 26px; line-height: 1.7;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Account Summary ─────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 mb-6 px-5 py-5"
     style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
    <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="w-14 h-14 rounded-full flex items-center justify-center flex-shrink-0"
             style="background: #fffbeb; border: 1px solid #fde68a;">
            <i class="bi bi-shield-fill-check" style="font-size: 22px; color: #d97706;"></i>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-slate-800 font-bold" style="font-size: 16px;">{{ $user->name }}</p>
            <p class="text-slate-400 mt-0.5" style="font-size: 12px;">
                <span class="font-mono" style="background: #f1f5f9; color: #475569; padding: 2px 7px; border-radius: 5px;">{{ '@' . $user->username }}</span>
                &nbsp;&bull;&nbsp; Supervisor account since {{ $user->created_at->format('M j, Y') }}
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold flex-shrink-0"
              style="font-size: 11px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
            <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span>
            {{ ucfirst($user->status) }}
        </span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

    {{-- ── Account Details ─────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">
                <i class="bi bi-person-badge mr-1.5" style="color: #0369a1;"></i> Account Details
            </h2>
            <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                Change your display name and the username you sign in with
            </p>
        </div>

        <form method="POST" action="{{ route('supervisor.account.profile') }}" class="px-5 py-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="profile">

            <div class="space-y-4">

                {{-- Full Name --}}
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" required
                           value="{{ old('_form') === 'profile' ? old('name') : $user->name }}"
                           placeholder="e.g. Juan Dela Cruz"
                           class="form-input">
                </div>

                {{-- Username --}}
                <div>
                    <label class="form-label">Username</label>
                    <div style="position: relative;">
                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px;">{{ '@' }}</span>
                        <input type="text" name="username" required
                               value="{{ old('_form') === 'profile' ? old('username') : $user->username }}"
                               placeholder="supervisor_username"
                               class="form-input" style="padding-left: 28px;">
                    </div>
                    <p class="text-slate-400 mt-1" style="font-size: 11px;">
                        Letters, numbers, underscores, and dashes only. You will sign in with this from now on.
                    </p>
                </div>

                {{-- Current password confirmation --}}
                <div>
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" required
                           placeholder="Confirm with your password"
                           class="form-input" autocomplete="current-password">
                    <p class="text-slate-400 mt-1" style="font-size: 11px;">
                        Required — your username is a login credential.
                    </p>
                </div>

            </div>

            <div class="flex justify-end mt-6">
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors"
                        style="font-size: 13px; background: #059669;"
                        onmouseover="this.style.background='#047857'"
                        onmouseout="this.style.background='#059669'">
                    <i class="bi bi-check-lg mr-1"></i> Save Details
                </button>
            </div>
        </form>
    </div>

    {{-- ── Change Password ─────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

        <div class="px-5 py-4 border-b border-slate-100">
            <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">
                <i class="bi bi-key-fill mr-1.5" style="color: #d97706;"></i> Change Password
            </h2>
            <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                Use at least 8 characters and keep it private
            </p>
        </div>

        <form method="POST" action="{{ route('supervisor.account.password') }}" class="px-5 py-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="password">

            <div class="space-y-4">

                {{-- Current Password --}}
                <div>
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" required
                           placeholder="Your password right now"
                           class="form-input" autocomplete="current-password">
                </div>

                {{-- New Password --}}
                <div>
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" required
                           placeholder="Minimum 8 characters"
                           class="form-input" autocomplete="new-password">
                </div>

                {{-- Confirm New Password --}}
                <div>
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirmation" required
                           placeholder="Repeat new password"
                           class="form-input" autocomplete="new-password">
                </div>

            </div>

            <div class="rounded-xl px-4 py-3 mt-4" style="background: #fffbeb; border: 1px solid #fde68a;">
                <div class="flex items-start gap-2.5">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-0.5" style="color: #d97706; font-size: 13px;"></i>
                    <p style="font-size: 12px; color: #92400e; line-height: 1.5;">
                        You will stay signed in on this device. Use the new password the next time you log in.
                    </p>
                </div>
            </div>

            <div class="flex justify-end mt-5">
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors"
                        style="font-size: 13px; background: #d97706;"
                        onmouseover="this.style.background='#b45309'"
                        onmouseout="this.style.background='#d97706'">
                    <i class="bi bi-key mr-1"></i> Update Password
                </button>
            </div>
        </form>
    </div>

</div>

@endsection
