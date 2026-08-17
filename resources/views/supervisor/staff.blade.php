@extends('layouts.app')

@section('title', 'Staff Management')
@section('subtitle', 'Market Staff Accounts · Supervisor View')

@push('styles')
<style>
    .modal-overlay { animation: fadeIn 0.15s ease; }
    .modal-box     { animation: slideUp 0.2s cubic-bezier(0.16, 1, 0.3, 1); }
    @keyframes fadeIn  { from { opacity: 0; }              to { opacity: 1; }             }
    @keyframes slideUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }

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
        Please correct the following:
    </div>
    <ul class="space-y-0.5 pl-6" style="list-style: disc; font-size: 12.5px; color: #b91c1c;">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── KPI Summary Cards ────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    {{-- Total Staff --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Total Staff</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $totalStaff }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">All registered accounts</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #eff6ff;">
                <i class="bi bi-person-badge-fill text-blue-500" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Active --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Active</p>
                <p class="font-bold mt-1" style="font-size: 28px; line-height: 1; color: #059669;">
                    {{ $activeStaff }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Currently can log in</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #f0fdf4;">
                <i class="bi bi-check-circle-fill" style="font-size: 17px; color: #10b981;"></i>
            </div>
        </div>
    </div>

    {{-- Inactive --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100"
         style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold"
                   style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Inactive</p>
                <p class="text-slate-500 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $inactiveStaff }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Deactivated accounts</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #f8fafc;">
                <i class="bi bi-slash-circle text-slate-400" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

</div>

{{-- ── Staff Table Card ─────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden"
     style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

    {{-- Card Header --}}
    <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">Staff Accounts</h2>
            <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                Manage market staff login credentials and access status
            </p>
        </div>
        <button onclick="openModal('addModal')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white font-semibold transition-colors flex-shrink-0"
                style="background: #2563eb; font-size: 13px;"
                onmouseover="this.style.background='#1d4ed8'"
                onmouseout="this.style.background='#2563eb'">
            <i class="bi bi-plus-lg"></i> Add Staff
        </button>
    </div>

    {{-- Search & Filter Bar --}}
    <form method="GET" action="{{ route('supervisor.staff.index') }}" class="px-5 py-4 border-b border-slate-100">
        <div class="flex flex-col sm:flex-row gap-3 items-end">
            {{-- Search input --}}
            <div class="flex-1">
                <label class="form-label">Search</label>
                <div style="position: relative;">
                    <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Search by name or username..."
                           class="form-input" style="padding-left: 32px;">
                </div>
            </div>

            {{-- Status filter --}}
            <div style="flex: 0 0 auto;">
                <label class="form-label">Status</label>
                <select name="status" class="form-input" style="padding-right: 28px;">
                    <option value="">All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>

            {{-- Search button --}}
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-colors"
                    style="background: #2563eb; color: white; font-size: 13px;"
                    onmouseover="this.style.background='#1d4ed8'"
                    onmouseout="this.style.background='#2563eb'">
                <i class="bi bi-funnel"></i> Filter
            </button>

            {{-- Clear filters button --}}
            @if(request('search') || request('status'))
                <a href="{{ route('supervisor.staff.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-colors"
                   style="border: 1px solid #e2e8f0; color: #475569; background: white; font-size: 13px;"
                   onmouseover="this.style.background='#f8fafc'"
                   onmouseout="this.style.background='white'">
                    <i class="bi bi-arrow-clockwise"></i> Clear
                </a>
            @endif
        </div>
    </form>

    @if($staff->isEmpty())
    {{-- Empty state --}}
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4"
             style="background: #f8fafc;">
            <i class="bi bi-person-badge" style="font-size: 26px; color: #cbd5e1;"></i>
        </div>
        <p class="text-slate-500 font-semibold" style="font-size: 13.5px;">No staff accounts yet</p>
        <p class="text-slate-400 mt-1" style="font-size: 12px; max-width: 280px;">
            Click "Add Staff" to create the first market staff account.
        </p>
    </div>

    @else
    {{-- Data table --}}
    <div class="overflow-x-auto">
        <table class="w-full" style="border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #f1f5f9;">
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold"
                        style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em; width: 44px;">#</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold"
                        style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Full Name</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold"
                        style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Username</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold"
                        style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Status</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold hidden sm:table-cell"
                        style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Date Added</th>
                    <th class="text-right px-5 py-3 text-slate-400 font-semibold"
                        style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staff as $member)
                <tr style="border-bottom: 1px solid #f8fafc; transition: background 0.1s;"
                    onmouseover="this.style.background='#f8fafc'"
                    onmouseout="this.style.background='transparent'">

                    {{-- Row number (accounting for pagination) --}}
                    <td class="px-5 py-4 text-slate-400" style="font-size: 12px;">{{ $staff->firstItem() + $loop->index }}</td>

                    {{-- Name --}}
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 style="background: #f5f3ff;">
                                <i class="bi bi-person-fill" style="font-size: 13px; color: #8b5cf6;"></i>
                            </div>
                            <span class="text-slate-700 font-semibold" style="font-size: 13.5px;">
                                {{ $member->name }}
                            </span>
                        </div>
                    </td>

                    {{-- Username --}}
                    <td class="px-5 py-4">
                        <span class="font-mono"
                              style="font-size: 12.5px; background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 5px;">
                            {{ $member->username }}
                        </span>
                    </td>

                    {{-- Status badge --}}
                    <td class="px-5 py-4">
                        @if($member->status === 'active')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold"
                                  style="font-size: 11px; background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0;">
                                <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold"
                                  style="font-size: 11px; background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0;">
                                <span style="width:6px; height:6px; border-radius:50%; background:#94a3b8; display:inline-block;"></span>
                                Inactive
                            </span>
                        @endif
                    </td>

                    {{-- Date added --}}
                    <td class="px-5 py-4 text-slate-400 hidden sm:table-cell" style="font-size: 12px;">
                        {{ $member->created_at->format('M j, Y') }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2">

                            {{-- Edit button --}}
                            <button onclick="openEditModal(
                                        {{ $member->id }},
                                        '{{ addslashes($member->name) }}',
                                        '{{ $member->username }}'
                                    )"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors"
                                    style="font-size: 12px; border: 1px solid #e2e8f0; color: #475569; background: white;"
                                    onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                                    onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                                <i class="bi bi-pencil-square" style="font-size: 11px;"></i> Edit
                            </button>

                            {{-- Toggle Status --}}
                            <form method="POST"
                                  action="{{ route('supervisor.staff.toggle', $member) }}"
                                  style="display:inline;">
                                @csrf
                                @method('PATCH')

                                @if($member->status === 'active')
                                    <button type="button"
                                            onclick="openDeactivateModal(
                                                {{ $member->id }},
                                                '{{ addslashes($member->name) }}'
                                            )"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors"
                                            style="font-size: 12px; border: 1px solid #fde68a; color: #92400e; background: #fffbeb;"
                                            onmouseover="this.style.background='#fef3c7'"
                                            onmouseout="this.style.background='#fffbeb'">
                                        <i class="bi bi-pause-circle" style="font-size: 11px;"></i> Deactivate
                                    </button>
                                @else
                                    <button type="button"
                                            onclick="openActivateModal(
                                                {{ $member->id }},
                                                '{{ addslashes($member->name) }}'
                                            )"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors"
                                            style="font-size: 12px; border: 1px solid #a7f3d0; color: #065f46; background: #ecfdf5;"
                                            onmouseover="this.style.background='#d1fae5'"
                                            onmouseout="this.style.background='#ecfdf5'">
                                        <i class="bi bi-play-circle" style="font-size: 11px;"></i> Activate
                                    </button>
                                @endif
                            </form>

                            {{-- Delete button (inactive only) --}}
                            @if($member->status === 'inactive')
                                <button onclick="openDeleteModal(
                                            {{ $member->id }},
                                            '{{ addslashes($member->name) }}'
                                        )"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors"
                                        style="font-size: 12px; border: 1px solid #fecaca; color: #991b1b; background: #fef2f2;"
                                        onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'"
                                        onmouseout="this.style.background='#fef2f2'; this.style.borderColor='#fecaca'">
                                    <i class="bi bi-trash3" style="font-size: 11px;"></i> Delete
                                </button>
                            @endif

                        </div>
                    </td>

                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination Info & Controls --}}
    <div class="px-5 py-4 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <p class="text-slate-500" style="font-size: 12px;">
            Showing <span class="font-semibold text-slate-700">{{ $staff->firstItem() }}</span> to
            <span class="font-semibold text-slate-700">{{ $staff->lastItem() }}</span> of
            <span class="font-semibold text-slate-700">{{ $staff->total() }}</span> staff members
        </p>

        {{-- Pagination Links --}}
        <div class="flex items-center gap-2">
            {{-- Previous button --}}
            @if ($staff->onFirstPage())
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg"
                      style="border: 1px solid #e2e8f0; color: #cbd5e1; background: #f8fafc; cursor: not-allowed;">
                    <i class="bi bi-chevron-left" style="font-size: 14px;"></i>
                </span>
            @else
                <a href="{{ $staff->previousPageUrl() . (request('search') ? '&search=' . request('search') : '') . (request('status') ? '&status=' . request('status') : '') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                   style="border: 1px solid #e2e8f0; color: #475569; background: white;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                   onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                    <i class="bi bi-chevron-left" style="font-size: 14px;"></i>
                </a>
            @endif

            {{-- Page numbers --}}
            @foreach ($staff->getUrlRange(1, $staff->lastPage()) as $page => $url)
                @if ($page == $staff->currentPage())
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg font-semibold"
                          style="background: #2563eb; color: white; border: 1px solid #2563eb;">
                        {{ $page }}
                    </span>
                @else
                    <a href="{{ $url . (request('search') ? '&search=' . request('search') : '') . (request('status') ? '&status=' . request('status') : '') }}"
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg font-medium transition-colors"
                       style="border: 1px solid #e2e8f0; color: #475569; background: white;"
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                       onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            {{-- Next button --}}
            @if ($staff->hasMorePages())
                <a href="{{ $staff->nextPageUrl() . (request('search') ? '&search=' . request('search') : '') . (request('status') ? '&status=' . request('status') : '') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                   style="border: 1px solid #e2e8f0; color: #475569; background: white;"
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                   onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                    <i class="bi bi-chevron-right" style="font-size: 14px;"></i>
                </a>
            @else
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg"
                      style="border: 1px solid #e2e8f0; color: #cbd5e1; background: #f8fafc; cursor: not-allowed;">
                    <i class="bi bi-chevron-right" style="font-size: 14px;"></i>
                </span>
            @endif
        </div>
    </div>

    @endif

</div>


{{-- ══════════════════════════════════════════════ MODALS ════════ --}}

{{-- ── Add Staff Modal ─────────────────────────────────────────── --}}
<div id="addModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     style="background: rgba(0,0,0,0.45);"
     onclick="if(event.target===this) closeModal('addModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-md overflow-hidden"
         style="box-shadow: 0 24px 60px rgba(0,0,0,0.18);">

        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-slate-800 font-bold" style="font-size: 15px;">Add Staff Account</h3>
                <p class="text-slate-400" style="font-size: 11.5px; margin-top: 1px;">
                    Create a new market staff login credential
                </p>
            </div>
            <button onclick="closeModal('addModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors"
                    style="line-height: 1;">
                <i class="bi bi-x-lg" style="font-size: 16px;"></i>
            </button>
        </div>

        {{-- Modal form --}}
        <form method="POST" action="{{ route('supervisor.staff.store') }}" class="px-6 py-5">
            @csrf

            <div class="space-y-4">

                {{-- Full Name --}}
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="e.g. Juan dela Cruz"
                           class="form-input">
                </div>

                {{-- Username --}}
                <div>
                    <label class="form-label">Username</label>
                    <div style="position: relative;">
                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px;">@</span>
                        <input type="text" name="username" value="{{ old('username') }}" required
                               placeholder="marketstaff"
                               class="form-input" style="padding-left: 28px;">
                    </div>
                    <p class="text-slate-400 mt-1" style="font-size: 11px;">
                        Letters, numbers, underscores, and dashes only.
                    </p>
                </div>

                {{-- Password --}}
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" name="password" required
                           placeholder="Minimum 8 characters"
                           class="form-input">
                </div>

                {{-- Confirm Password --}}
                <div>
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" required
                           placeholder="Repeat password"
                           class="form-input">
                </div>

            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('addModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors"
                        style="font-size: 13px; border: 1px solid #e2e8f0; color: #64748b; background: white;"
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors"
                        style="font-size: 13px; background: #2563eb;"
                        onmouseover="this.style.background='#1d4ed8'"
                        onmouseout="this.style.background='#2563eb'">
                    <i class="bi bi-plus-lg mr-1"></i> Create Account
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ── Edit Staff Modal ─────────────────────────────────────────── --}}
<div id="editModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     style="background: rgba(0,0,0,0.45);"
     onclick="if(event.target===this) closeModal('editModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-md overflow-hidden"
         style="box-shadow: 0 24px 60px rgba(0,0,0,0.18);">

        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-slate-800 font-bold" style="font-size: 15px;">Edit Staff Account</h3>
                <p class="text-slate-400" style="font-size: 11.5px; margin-top: 1px;">
                    Update staff details and credentials
                </p>
            </div>
            <button onclick="closeModal('editModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors"
                    style="line-height: 1;">
                <i class="bi bi-x-lg" style="font-size: 16px;"></i>
            </button>
        </div>

        {{-- Modal form --}}
        <form id="editForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('PUT')

            <div class="space-y-4">

                {{-- Full Name --}}
                <div>
                    <label class="form-label">Full Name</label>
                    <input type="text" id="editName" name="name" required class="form-input">
                </div>

                {{-- Username --}}
                <div>
                    <label class="form-label">Username</label>
                    <div style="position: relative;">
                        <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:13px;">@</span>
                        <input type="text" id="editUsername" name="username" required
                               class="form-input" style="padding-left: 28px;">
                    </div>
                </div>

                {{-- Password change notice --}}
                <div class="rounded-lg px-3 py-2.5" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                    <p style="font-size: 11.5px; color: #64748b;">
                        <i class="bi bi-lock-fill mr-1" style="color: #94a3b8;"></i>
                        <strong>Change Password</strong> &mdash;
                        <span style="font-weight: 400;">leave blank to keep the current password.</span>
                    </p>
                </div>

                {{-- New Password --}}
                <div>
                    <label class="form-label">New Password <span style="font-weight:400; color:#94a3b8;">(optional)</span></label>
                    <input type="password" id="editPassword" name="password"
                           placeholder="Leave blank to keep current"
                           class="form-input">
                </div>

                {{-- Confirm New Password --}}
                <div>
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="password_confirmation"
                           placeholder="Repeat new password"
                           class="form-input">
                </div>

            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('editModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors"
                        style="font-size: 13px; border: 1px solid #e2e8f0; color: #64748b; background: white;"
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors"
                        style="font-size: 13px; background: #2563eb;"
                        onmouseover="this.style.background='#1d4ed8'"
                        onmouseout="this.style.background='#2563eb'">
                    <i class="bi bi-check-lg mr-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Delete Staff Modal ─────────────────────────────────────── --}}
<div id="deleteModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     style="background: rgba(0,0,0,0.45);"
     onclick="if(event.target===this) closeModal('deleteModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-sm overflow-hidden"
         style="box-shadow: 0 24px 60px rgba(0,0,0,0.18);">

        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold" style="font-size: 15px; color: #991b1b;">
                    <i class="bi bi-trash3-fill mr-1.5" style="font-size: 14px;"></i>
                    Delete Staff Account
                </h3>
                <p class="text-slate-400" style="font-size: 11.5px; margin-top: 1px;">
                    This action is permanent and cannot be undone.
                </p>
            </div>
            <button onclick="closeModal('deleteModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors"
                    style="line-height: 1;">
                <i class="bi bi-x-lg" style="font-size: 16px;"></i>
            </button>
        </div>

        <form id="deleteForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('DELETE')

            {{-- Warning banner --}}
            <div class="mb-4 rounded-xl px-4 py-3" style="background: #fef2f2; border: 1px solid #fecaca;">
                <div class="flex items-start gap-2.5">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-0.5" style="color: #ef4444; font-size: 14px;"></i>
                    <p style="font-size: 13px; color: #991b1b; line-height: 1.5;">
                        You are about to permanently delete
                        <strong id="deleteStaffName" style="font-weight: 700;"></strong>.
                        All account data will be removed.
                    </p>
                </div>
            </div>

            {{-- CONFIRM input --}}
            <div>
                <label class="form-label">
                    Type
                    <span style="font-family: monospace; font-weight: 700; color: #ef4444; background: #fef2f2; padding: 1px 6px; border-radius: 4px; border: 1px solid #fecaca;">CONFIRM</span>
                    to proceed
                </label>
                <input type="text" id="deleteConfirmInput"
                       placeholder="Type CONFIRM here"
                       class="form-input"
                       autocomplete="off"
                       oninput="toggleDeleteBtn()">
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeModal('deleteModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors"
                        style="font-size: 13px; border: 1px solid #e2e8f0; color: #64748b; background: white;"
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit" id="deleteSubmitBtn" disabled
                        class="px-5 py-2 rounded-lg text-white font-semibold"
                        style="font-size: 13px; background: #ef4444; opacity: 0.4; cursor: not-allowed; transition: opacity 0.15s;">
                    <i class="bi bi-trash3 mr-1"></i> Delete Account
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Deactivate Staff Modal ──────────────────────────────────── --}}
<div id="deactivateModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     style="background: rgba(0,0,0,0.45);"
     onclick="if(event.target===this) closeModal('deactivateModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-sm overflow-hidden"
         style="box-shadow: 0 24px 60px rgba(0,0,0,0.18);">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold" style="font-size: 15px; color: #92400e;">
                    <i class="bi bi-pause-circle-fill mr-1.5" style="font-size: 14px;"></i>
                    Deactivate Staff Member
                </h3>
                <p class="text-slate-400" style="font-size: 11.5px; margin-top: 1px;">
                    The staff member will lose access until reactivated.
                </p>
            </div>
            <button onclick="closeModal('deactivateModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors"
                    style="line-height: 1;">
                <i class="bi bi-x-lg" style="font-size: 16px;"></i>
            </button>
        </div>

        <form id="deactivateForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('PATCH')

            <div class="mb-5 rounded-xl px-4 py-3" style="background: #fffbeb; border: 1px solid #fde68a;">
                <div class="flex items-start gap-2.5">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-0.5" style="color: #d97706; font-size: 14px;"></i>
                    <p style="font-size: 13px; color: #92400e; line-height: 1.5;">
                        <strong id="deactivateStaffName" style="font-weight: 700;"></strong>
                        will no longer be able to log in or manage vendor records.
                        You can reactivate them at any time.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('deactivateModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors"
                        style="font-size: 13px; border: 1px solid #e2e8f0; color: #64748b; background: white;"
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors"
                        style="font-size: 13px; background: #d97706;"
                        onmouseover="this.style.background='#b45309'"
                        onmouseout="this.style.background='#d97706'">
                    <i class="bi bi-pause-circle mr-1"></i> Yes, Deactivate
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Activate Staff Modal ────────────────────────────────────── --}}
<div id="activateModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     style="background: rgba(0,0,0,0.45);"
     onclick="if(event.target===this) closeModal('activateModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-sm overflow-hidden"
         style="box-shadow: 0 24px 60px rgba(0,0,0,0.18);">

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold" style="font-size: 15px; color: #065f46;">
                    <i class="bi bi-play-circle-fill mr-1.5" style="font-size: 14px;"></i>
                    Activate Staff Member
                </h3>
                <p class="text-slate-400" style="font-size: 11.5px; margin-top: 1px;">
                    The staff member will regain access to the system.
                </p>
            </div>
            <button onclick="closeModal('activateModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors"
                    style="line-height: 1;">
                <i class="bi bi-x-lg" style="font-size: 16px;"></i>
            </button>
        </div>

        <form id="activateForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('PATCH')

            <div class="mb-5 rounded-xl px-4 py-3" style="background: #ecfdf5; border: 1px solid #a7f3d0;">
                <div class="flex items-start gap-2.5">
                    <i class="bi bi-check-circle-fill flex-shrink-0 mt-0.5" style="color: #059669; font-size: 14px;"></i>
                    <p style="font-size: 13px; color: #065f46; line-height: 1.5;">
                        <strong id="activateStaffName" style="font-weight: 700;"></strong>
                        will be able to log in and manage vendor records again.
                        You can deactivate them at any time.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('activateModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors"
                        style="font-size: 13px; border: 1px solid #e2e8f0; color: #64748b; background: white;"
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors"
                        style="font-size: 13px; background: #059669;"
                        onmouseover="this.style.background='#047857'"
                        onmouseout="this.style.background='#059669'">
                    <i class="bi bi-play-circle mr-1"></i> Yes, Activate
                </button>
            </div>
        </form>
    </div>
</div>

@endsection


@push('scripts')
<script>
    // ── Modal helpers ─────────────────────────────────────────────
    function openModal(id) {
        const el = document.getElementById(id);
        el.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // Re-trigger animation
        el.querySelector('.modal-box').style.animation = 'none';
        el.querySelector('.modal-box').offsetHeight; // reflow
        el.querySelector('.modal-box').style.animation = '';
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = '';
    }

    // ── Populate & open Edit modal ────────────────────────────────
    function openEditModal(id, name, username) {
        document.getElementById('editForm').action = `/supervisor/staff/${id}`;
        document.getElementById('editName').value    = name;
        document.getElementById('editUsername').value = username;
        // Clear password fields every time
        document.getElementById('editModal')
            .querySelectorAll('input[type="password"]')
            .forEach(el => el.value = '');
        openModal('editModal');
    }

    // ── Open Delete modal ──────────────────────────────────────────
    function openDeleteModal(id, name) {
        document.getElementById('deleteForm').action = `/supervisor/staff/${id}`;
        document.getElementById('deleteStaffName').textContent = name;
        document.getElementById('deleteConfirmInput').value = '';
        const btn = document.getElementById('deleteSubmitBtn');
        btn.disabled    = true;
        btn.style.opacity = '0.4';
        btn.style.cursor  = 'not-allowed';
        openModal('deleteModal');
    }

    // ── Enable/disable Delete button based on CONFIRM input ───────
    function toggleDeleteBtn() {
        const val = document.getElementById('deleteConfirmInput').value;
        const btn = document.getElementById('deleteSubmitBtn');
        const ready = val === 'CONFIRM';
        btn.disabled      = !ready;
        btn.style.opacity = ready ? '1'            : '0.4';
        btn.style.cursor  = ready ? 'pointer'      : 'not-allowed';
    }

    // ── ESC key closes any open modal ─────────────────────────────
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeModal('addModal');
            closeModal('editModal');
            closeModal('deactivateModal');
            closeModal('activateModal');
            closeModal('deleteModal');
        }
    });

    // ── Open Deactivate modal ─────────────────────────────────────
    function openDeactivateModal(id, name) {
        document.getElementById('deactivateForm').action = `/supervisor/staff/${id}/toggle`;
        document.getElementById('deactivateStaffName').textContent = name;
        openModal('deactivateModal');
    }

    // ── Open Activate modal ───────────────────────────────────────
    function openActivateModal(id, name) {
        document.getElementById('activateForm').action = `/supervisor/staff/${id}/toggle`;
        document.getElementById('activateStaffName').textContent = name;
        openModal('activateModal');
    }

    // ── Re-open Add modal if there were validation errors ─────────
    // (only fires when the form was submitted, not on page load)
    @if($errors->any() && !old('_method'))
        openModal('addModal');
    @endif
</script>
@endpush