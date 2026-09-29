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
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-[13.5px] bg-success-50"
     style="border: 1px solid #a7f3d0; color: #065f46">
    <x-icon name="bi-check-circle-fill" size="base" class="flex-shrink-0 text-success-500" />
    <span class="font-medium">{{ session('success') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #34d399;">
        <x-icon name="bi-x-lg" size="md" />
    </button>
</div>
@endif

{{-- ── Error Message ───────────────────────────────────────────── --}}
@if(session('error'))
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-[13.5px] bg-danger-50 border border-danger-200 text-danger-800"
    >
    <x-icon name="bi-exclamation-octagon-fill" size="base" class="flex-shrink-0 text-danger-500" />
    <span class="font-medium">{{ session('error') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #f87171;">
        <x-icon name="bi-x-lg" size="md" />
    </button>
</div>
@endif

{{-- ── Validation Errors ───────────────────────────────────────── --}}
@if($errors->any())
<div class="mb-5 px-4 py-3 rounded-xl text-[13px] bg-danger-50 border border-danger-200 text-danger-800"
    >
    <div class="flex items-center gap-2 font-semibold mb-1.5 text-[13.5px]">
        <x-icon name="bi-exclamation-circle-fill" class="flex-shrink-0 text-danger-500" />
        Please correct the following:
    </div>
    <ul class="space-y-0.5 pl-6 text-[12.5px] text-danger-700" style="list-style: disc">
        @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── KPI Summary Cards ────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    {{-- Total Staff --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card"
        >
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                  >Total Staff</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $totalStaff }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">All registered accounts</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-brand-50"
                >
                <x-icon name="bi-person-badge-fill" size="lg" class="text-blue-500" />
            </div>
        </div>
    </div>

    {{-- Active --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card"
        >
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                  >Active</p>
                <p class="font-bold mt-1 text-[28px] leading-[1] text-success-600">
                    {{ $activeStaff }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Currently can log in</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50"
                >
                <x-icon name="bi-check-circle-fill" size="lg" class="text-success-500" />
            </div>
        </div>
    </div>

    {{-- Inactive --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card"
        >
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                  >Inactive</p>
                <p class="text-slate-500 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $inactiveStaff }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Deactivated accounts</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-surface-subtle"
                >
                <x-icon name="bi-slash-circle" size="lg" class="text-slate-400" />
            </div>
        </div>
    </div>

</div>

{{-- ── Staff Table Card ─────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card"
    >

    {{-- Card Header --}}
    <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">Staff Accounts</h2>
            <p class="text-slate-400 text-[11px] mt-px">
                Manage market staff login credentials and access status
            </p>
        </div>
        <button onclick="openModal('addModal')"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white font-semibold transition-colors flex-shrink-0 text-[13px]"
                style="background: #2563eb"
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
                <div class="relative">
                    <i class="bi bi-search text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
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
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-colors text-[13px]"
                    style="background: #2563eb; color: white"
                    onmouseover="this.style.background='#1d4ed8'"
                    onmouseout="this.style.background='#2563eb'">
                <i class="bi bi-funnel"></i> Filter
            </button>

            {{-- Clear filters button --}}
            @if(request('search') || request('status'))
                <a href="{{ route('supervisor.staff.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-colors text-[13px] border border-slate-200 text-slate-600 bg-white"
                  
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
        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4 bg-surface-subtle"
            >
            <x-icon name="bi-person-badge" size="2xl" class="text-slate-300" />
        </div>
        <p class="text-slate-500 font-semibold text-[13.5px]">No staff accounts yet</p>
        <p class="text-slate-400 mt-1 text-[12px]" style="max-width: 280px">
            Click "Add Staff" to create the first market staff account.
        </p>
    </div>

    @else
    {{-- Data table --}}
    <div class="overflow-x-auto">
        <table class="w-full" style="border-collapse: collapse; min-width: 600px;">
            <thead>
                <tr class="bg-surface-subtle" style="border-bottom: 1px solid #f1f5f9">
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                        style="width: 44px">#</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                       >Full Name</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                       >Username</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                       >Status</th>
                    <th class="text-left px-5 py-3 text-slate-400 font-semibold hidden sm:table-cell text-[10.5px] uppercase tracking-[0.07em]"
                       >Date Added</th>
                    <th class="text-right px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                       >Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($staff as $member)
                <tr style="border-bottom: 1px solid #f8fafc; transition: background 0.1s;"
                    onmouseover="this.style.background='#f8fafc'"
                    onmouseout="this.style.background='transparent'">

                    {{-- Row number (accounting for pagination) --}}
                    <td class="px-5 py-4 text-slate-400 text-[12px]">{{ $staff->firstItem() + $loop->index }}</td>

                    {{-- Name --}}
                    <td class="px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center flex-shrink-0">
                                <i class="bi bi-person-fill"></i>
                            </div>
                            <span class="text-slate-700 font-semibold text-[13.5px]">
                                {{ $member->name }}
                            </span>
                        </div>
                    </td>

                    {{-- Username --}}
                    <td class="px-5 py-4">
                        <span class="font-mono text-[12.5px] bg-surface-muted text-slate-600"
                              style="padding: 3px 8px; border-radius: 5px">
                            {{ $member->username }}
                        </span>
                    </td>

                    {{-- Status badge --}}
                    <td class="px-5 py-4">
                        @if($member->status === 'active')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold text-[11px] bg-success-50"
                                  style="color: #065f46; border: 1px solid #a7f3d0">
                                <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                                Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-semibold text-[11px] bg-surface-subtle text-slate-500 border border-slate-200"
                                 >
                                <span style="width:6px; height:6px; border-radius:50%; background:#94a3b8; display:inline-block;"></span>
                                Inactive
                            </span>
                        @endif
                    </td>

                    {{-- Date added --}}
                    <td class="px-5 py-4 text-slate-400 hidden sm:table-cell text-[12px]">
                        {{ $member->created_at->format('M j, Y') }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-5 py-4">
                        <div class="flex items-center justify-end gap-2">

                            {{-- Edit button --}}
                            <button class="text-[12px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors border border-slate-200 text-slate-600 bg-white" onclick="openEditModal(
                                        {{ $member->id }},
                                        '{{ addslashes($member->name) }}',
                                        '{{ $member->username }}'
                                    )"
                                    onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                                    onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                                <x-icon name="bi-pencil-square" size="xs" /> Edit
                            </button>

                            {{-- Toggle Status --}}
                            <form method="POST"
                                  action="{{ route('supervisor.staff.toggle', $member) }}"
                                  style="display:inline;">
                                @csrf
                                @method('PATCH')

                                @if($member->status === 'active')
                                    <button class="text-[12px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors border border-warning-200 text-warning-800 bg-warning-50" type="button"
                                            onclick="openDeactivateModal(
                                                {{ $member->id }},
                                                '{{ addslashes($member->name) }}'
                                            )"
                                            onmouseover="this.style.background='#fef3c7'"
                                            onmouseout="this.style.background='#fffbeb'">
                                        <x-icon name="bi-pause-circle" size="xs" /> Deactivate
                                    </button>
                                @else
                                    <button class="text-[12px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors bg-success-50" type="button"
                                            onclick="openActivateModal(
                                                {{ $member->id }},
                                                '{{ addslashes($member->name) }}'
                                            )"
                                            onmouseover="this.style.background='#d1fae5'"
                                            onmouseout="this.style.background='#ecfdf5'" style="border: 1px solid #a7f3d0; color: #065f46">
                                        <x-icon name="bi-play-circle" size="xs" /> Activate
                                    </button>
                                @endif
                            </form>

                            {{-- Delete button (inactive only) --}}
                            @if($member->status === 'inactive')
                                <button class="text-[12px] inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-medium transition-colors border border-danger-200 text-danger-800 bg-danger-50" onclick="openDeleteModal(
                                            {{ $member->id }},
                                            '{{ addslashes($member->name) }}'
                                        )"
                                        onmouseover="this.style.background='#fee2e2'; this.style.borderColor='#fca5a5'"
                                        onmouseout="this.style.background='#fef2f2'; this.style.borderColor='#fecaca'">
                                    <x-icon name="bi-trash3" size="xs" /> Delete
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
        <p class="text-slate-500 text-[12px]">
            Showing <span class="font-semibold text-slate-700">{{ $staff->firstItem() }}</span> to
            <span class="font-semibold text-slate-700">{{ $staff->lastItem() }}</span> of
            <span class="font-semibold text-slate-700">{{ $staff->total() }}</span> staff members
        </p>

        {{-- Pagination Links --}}
        <div class="flex items-center gap-2">
            {{-- Previous button --}}
            @if ($staff->onFirstPage())
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-surface-subtle border border-slate-200"
                      style="color: #cbd5e1; cursor: not-allowed">
                    <x-icon name="bi-chevron-left" size="md" />
                </span>
            @else
                <a href="{{ $staff->previousPageUrl() . (request('search') ? '&search=' . request('search') : '') . (request('status') ? '&status=' . request('status') : '') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors border border-slate-200 text-slate-600 bg-white"
                  
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                   onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                    <x-icon name="bi-chevron-left" size="md" />
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
                       class="inline-flex items-center justify-center w-8 h-8 rounded-lg font-medium transition-colors border border-slate-200 text-slate-600 bg-white"
                      
                       onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                       onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                        {{ $page }}
                    </a>
                @endif
            @endforeach

            {{-- Next button --}}
            @if ($staff->hasMorePages())
                <a href="{{ $staff->nextPageUrl() . (request('search') ? '&search=' . request('search') : '') . (request('status') ? '&status=' . request('status') : '') }}"
                   class="inline-flex items-center justify-center w-8 h-8 rounded-lg transition-colors border border-slate-200 text-slate-600 bg-white"
                  
                   onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
                   onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
                    <x-icon name="bi-chevron-right" size="md" />
                </a>
            @else
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-surface-subtle border border-slate-200"
                      style="color: #cbd5e1; cursor: not-allowed">
                    <x-icon name="bi-chevron-right" size="md" />
                </span>
            @endif
        </div>
    </div>

    @endif

</div>


{{-- ══════════════════════════════════════════════ MODALS ════════ --}}

{{-- ── Add Staff Modal ─────────────────────────────────────────── --}}
<div id="addModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/45"
    
     onclick="if(event.target===this) closeModal('addModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-modal"
        >

        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-slate-800 font-bold" style="font-size: 15px;">Add Staff Account</h3>
                <p class="text-slate-400 text-[11.5px] mt-px">
                    Create a new market staff login credential
                </p>
            </div>
            <button onclick="closeModal('addModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors leading-[1]"
                   >
                <x-icon name="bi-x-lg" size="base" />
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
                    <div class="relative">
                        <span class="absolute text-[13px] left-3 top-1/2 -translate-y-1/2 text-slate-400">@</span>
                        <input type="text" name="username" value="{{ old('username') }}" required
                               placeholder="marketstaff"
                               class="form-input" style="padding-left: 28px;">
                    </div>
                    <p class="text-slate-400 mt-1 text-[11px]">
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
                        class="px-4 py-2 rounded-lg font-semibold transition-colors text-[13px] border border-slate-200 text-slate-500 bg-white"
                       
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors text-[13px]"
                        style="background: #2563eb"
                        onmouseover="this.style.background='#1d4ed8'"
                        onmouseout="this.style.background='#2563eb'">
                    <x-icon name="bi-plus-lg" class="mr-1" /> Create Account
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ── Edit Staff Modal ─────────────────────────────────────────── --}}
<div id="editModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/45"
    
     onclick="if(event.target===this) closeModal('editModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-modal"
        >

        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-slate-800 font-bold" style="font-size: 15px;">Edit Staff Account</h3>
                <p class="text-slate-400 text-[11.5px] mt-px">
                    Update staff details and credentials
                </p>
            </div>
            <button onclick="closeModal('editModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors leading-[1]"
                   >
                <x-icon name="bi-x-lg" size="base" />
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
                    <div class="relative">
                        <span class="absolute text-[13px] left-3 top-1/2 -translate-y-1/2 text-slate-400">@</span>
                        <input type="text" id="editUsername" name="username" required
                               class="form-input" style="padding-left: 28px;">
                    </div>
                </div>

                {{-- Password change notice --}}
                <div class="rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                    <p class="text-[11.5px] text-slate-500">
                        <x-icon name="bi-lock-fill" class="mr-1 text-slate-400" />
                        <strong>Change Password</strong> &mdash;
                        <span style="font-weight: 400;">leave blank to keep the current password.</span>
                    </p>
                </div>

                {{-- New Password --}}
                <div>
                    <label class="form-label">New Password <span class="text-slate-400" style="font-weight:400">(optional)</span></label>
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
                        class="px-4 py-2 rounded-lg font-semibold transition-colors text-[13px] border border-slate-200 text-slate-500 bg-white"
                       
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors text-[13px]"
                        style="background: #2563eb"
                        onmouseover="this.style.background='#1d4ed8'"
                        onmouseout="this.style.background='#2563eb'">
                    <x-icon name="bi-check-lg" class="mr-1" /> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Delete Staff Modal ─────────────────────────────────────── --}}
<div id="deleteModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/45"
    
     onclick="if(event.target===this) closeModal('deleteModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-modal"
        >

        {{-- Modal header --}}
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-danger-800" style="font-size: 15px">
                    <x-icon name="bi-trash3-fill" size="md" class="mr-1.5" />
                    Delete Staff Account
                </h3>
                <p class="text-slate-400 text-[11.5px] mt-px">
                    This action is permanent and cannot be undone.
                </p>
            </div>
            <button onclick="closeModal('deleteModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors leading-[1]"
                   >
                <x-icon name="bi-x-lg" size="base" />
            </button>
        </div>

        <form id="deleteForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('DELETE')

            {{-- Warning banner --}}
            <div class="mb-4 rounded-xl px-4 py-3 bg-danger-50 border border-danger-200">
                <div class="flex items-start gap-2.5">
                    <x-icon name="bi-exclamation-triangle-fill" size="md" class="flex-shrink-0 mt-0.5 text-danger-500" />
                    <p class="text-[13px] leading-[1.5] text-danger-800">
                        You are about to permanently delete
                        <strong class="font-bold" id="deleteStaffName"></strong>.
                        All account data will be removed.
                    </p>
                </div>
            </div>

            {{-- CONFIRM input --}}
            <div>
                <label class="form-label">
                    Type
                    <span class="font-bold text-danger-500 bg-danger-50 border border-danger-200" style="font-family: monospace; padding: 1px 6px; border-radius: 4px">CONFIRM</span>
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
                        class="px-4 py-2 rounded-lg font-semibold transition-colors text-[13px] border border-slate-200 text-slate-500 bg-white"
                       
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit" id="deleteSubmitBtn" disabled
                        class="px-5 py-2 rounded-lg text-white font-semibold text-[13px]"
                        style="background: #ef4444; opacity: 0.4; cursor: not-allowed; transition: opacity 0.15s">
                    <x-icon name="bi-trash3" class="mr-1" /> Delete Account
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Deactivate Staff Modal ──────────────────────────────────── --}}
<div id="deactivateModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/45"
    
     onclick="if(event.target===this) closeModal('deactivateModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-modal"
        >

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-warning-800" style="font-size: 15px">
                    <x-icon name="bi-pause-circle-fill" size="md" class="mr-1.5" />
                    Deactivate Staff Member
                </h3>
                <p class="text-slate-400 text-[11.5px] mt-px">
                    The staff member will lose access until reactivated.
                </p>
            </div>
            <button onclick="closeModal('deactivateModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors leading-[1]"
                   >
                <x-icon name="bi-x-lg" size="base" />
            </button>
        </div>

        <form id="deactivateForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('PATCH')

            <div class="mb-5 rounded-xl px-4 py-3 bg-warning-50 border border-warning-200">
                <div class="flex items-start gap-2.5">
                    <x-icon name="bi-exclamation-triangle-fill" size="md" class="flex-shrink-0 mt-0.5 text-warning-600" />
                    <p class="text-[13px] leading-[1.5] text-warning-800">
                        <strong class="font-bold" id="deactivateStaffName"></strong>
                        will no longer be able to log in or manage vendor records.
                        You can reactivate them at any time.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('deactivateModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors text-[13px] border border-slate-200 text-slate-500 bg-white"
                       
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors text-[13px] bg-warning-600"
                       
                        onmouseover="this.style.background='#b45309'"
                        onmouseout="this.style.background='#d97706'">
                    <x-icon name="bi-pause-circle" class="mr-1" /> Yes, Deactivate
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Activate Staff Modal ────────────────────────────────────── --}}
<div id="activateModal"
     class="modal-overlay fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/45"
    
     onclick="if(event.target===this) closeModal('activateModal')">

    <div class="modal-box bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-modal"
        >

        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold" style="font-size: 15px; color: #065f46;">
                    <x-icon name="bi-play-circle-fill" size="md" class="mr-1.5" />
                    Activate Staff Member
                </h3>
                <p class="text-slate-400 text-[11.5px] mt-px">
                    The staff member will regain access to the system.
                </p>
            </div>
            <button onclick="closeModal('activateModal')"
                    class="text-slate-300 hover:text-slate-500 transition-colors leading-[1]"
                   >
                <x-icon name="bi-x-lg" size="base" />
            </button>
        </div>

        <form id="activateForm" method="POST" action="" class="px-6 py-5">
            @csrf
            @method('PATCH')

            <div class="mb-5 rounded-xl px-4 py-3 bg-success-50" style="border: 1px solid #a7f3d0">
                <div class="flex items-start gap-2.5">
                    <x-icon name="bi-check-circle-fill" size="md" class="flex-shrink-0 mt-0.5 text-success-600" />
                    <p class="text-[13px] leading-[1.5]" style="color: #065f46">
                        <strong class="font-bold" id="activateStaffName"></strong>
                        will be able to log in and manage vendor records again.
                        You can deactivate them at any time.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('activateModal')"
                        class="px-4 py-2 rounded-lg font-semibold transition-colors text-[13px] border border-slate-200 text-slate-500 bg-white"
                       
                        onmouseover="this.style.background='#f8fafc'"
                        onmouseout="this.style.background='white'">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold transition-colors text-[13px] bg-success-600"
                       
                        onmouseover="this.style.background='#047857'"
                        onmouseout="this.style.background='#059669'">
                    <x-icon name="bi-play-circle" class="mr-1" /> Yes, Activate
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