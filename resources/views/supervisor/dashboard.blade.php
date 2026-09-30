@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('subtitle', 'Market Overview · Virac Public Market')

@section('content')

    {{-- ── Greeting + Account Shortcut ───────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
        <div>
            <p class="text-slate-800 font-bold" style="font-size: 16px;">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }},
                {{ auth()->user()->name }}
            </p>
            <p class="text-slate-400 text-[11.5px] mt-px">
                Signed in as <span class="font-mono bg-surface-muted text-slate-600"
                    style="padding:1px 6px; border-radius:4px">{{ '@' . auth()->user()->username }}</span>
            </p>
        </div>
        <a href="{{ route('supervisor.account.edit') }}"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold transition-colors flex-shrink-0 text-[13px] border border-slate-200 text-slate-600 bg-white"
            onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'"
            onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'">
            <i class="bi bi-person-gear"></i> My Account
        </a>
    </div>

    {{-- ── Stat Cards ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

        {{-- Total Vendors --}}
        <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Total Vendors</p>
                    <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                        {{ $totalVendors ?? 0 }}
                    </p>
                    <p class="text-slate-400 mt-1 text-[11px]">Registered in system</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-brand-50">
                    <x-icon name="bi-people-fill" size="lg" class="text-blue-600" />
                </div>
            </div>
        </div>

        {{-- Total Stalls --}}
        <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Total Stalls</p>
                    <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                        {{ $totalStalls ?? 0 }}
                    </p>
                    <p class="text-slate-400 mt-1 text-[11px]">Active stall assignments</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50">
                    <x-icon name="bi-shop" size="lg" class="text-emerald-600" />
                </div>
            </div>
        </div>

        {{-- Total Stock --}}
        <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Today's Stock</p>
                    <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                        {{ number_format($totalStockKg ?? 0, 1) }} <span
                            class="text-[14px] font-semibold text-slate-400">kg</span>
                    </p>
                    <p class="text-slate-400 mt-1 text-[11px]">Confirmed supply today</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                    style="background: #fefce8;">
                    <x-icon name="bi-box-seam-fill" size="lg" class="text-amber-500" />
                </div>
            </div>
        </div>

        {{-- Active Staff --}}
        <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Active Staff</p>
                    <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                        {{ $activeStaff ?? 0 }}
                    </p>
                    <p class="text-slate-400 mt-1 text-[11px]">Market staff accounts</p>
                </div>
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                    style="background: #fdf4ff;">
                    <x-icon name="bi-person-badge-fill" size="lg" class="text-purple-600" />
                </div>
            </div>
        </div>

    </div>

    {{-- ── Next Section ─────────────────────────────────────────────
         The activity log used to sit here. It is an account-level record
         rather than an operational figure, so it now renders on My
         Account (see supervisor/account.blade.php). --}}

@endsection
