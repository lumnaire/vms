@extends('layouts.app')

@section('title', 'Staff Dashboard')
@section('subtitle', 'Price Confirmation & Vendor Management')

@section('content')

{{-- ── Stat Cards ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Pending Confirmations (Action Required) --}}
    <div class="stat-card rounded-xl p-5 border overflow-hidden relative"
         style="background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%); border-color: #1d4ed8; box-shadow: 0 4px 14px rgba(29,78,216,0.3);">
        {{-- Decorative circle --}}
        <div class="absolute" style="top: -12px; right: -12px; width: 70px; height: 70px; border-radius: 50%; background: rgba(255,255,255,0.08)"></div>
        <div class="flex items-start justify-between relative">
            <div>
                <p class="text-blue-200 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Pending</p>
                <p class="text-white font-bold mt-1 text-[28px] leading-[1]">
                    {{ $pendingCount ?? 0 }}
                </p>
                <p class="text-blue-200 mt-1 text-[11px]">Awaiting your review</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: rgba(255,255,255,0.15);">
                <x-icon name="bi-hourglass-split" size="lg" class="text-white" />
            </div>
        </div>
        @if(($pendingCount ?? 0) > 0)
            <a href="{{ route('staff.confirmations.index') }}"
               class="inline-flex items-center gap-1 mt-3 text-blue-100 hover:text-white transition-colors text-[11.5px] font-semibold"
              >
                Review now <i class="bi bi-arrow-right"></i>
            </a>
        @endif
    </div>

    {{-- Confirmed Today --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Confirmed Today</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $confirmedToday ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Entries approved today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50"
                >
                <x-icon name="bi-check-circle-fill" size="lg" class="text-emerald-500" />
            </div>
        </div>
    </div>

    {{-- Rejected Today --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Rejected Today</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $rejectedToday ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Entries rejected today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fff1f2;">
                <x-icon name="bi-x-circle-fill" size="lg" class="text-rose-500" />
            </div>
        </div>
    </div>

    {{-- Total Vendors --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Total Vendors</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $totalVendors ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Managed by you</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-brand-50"
                >
                <x-icon name="bi-people-fill" size="lg" class="text-blue-600" />
            </div>
        </div>
    </div>

</div>

{{-- ── Pending Entries + Repeat Submissions ───────────────────── --}}
<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

    {{-- Pending Queue --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">Pending Queue</h2>
                <p class="text-slate-400 text-[11px] mt-px">Awaiting confirmation</p>
            </div>
            <a href="{{ route('staff.confirmations.index') }}"
               class="text-blue-600 hover:text-blue-700 font-semibold transition-colors text-[11.5px]"
              >
                View all <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        @if($pendingEntries->isNotEmpty())
            <ul class="divide-y divide-slate-50 overflow-y-auto" style="max-height: 360px;">
                @foreach($pendingEntries as $entry)
                    <li class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
                        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 bg-brand-50"
                            >
                            <x-icon name="bi-hourglass-split" size="sm" class="text-brand-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-700 font-medium truncate text-[12px]">
                                {{ $entry->fishType->name ?? '—' }}
                                <span class="text-slate-400 font-normal">&bull; {{ $entry->quality_class }}</span>
                                <x-session-badge :session="$entry->session()" class="ml-1" />
                            </p>
                            <p class="text-slate-400 mt-0.5 text-[10.5px]">
                                {{ $entry->vendor->name ?? 'Unknown vendor' }}
                                @if($entry->vendor->vendorProfile)
                                    &bull; Stall {{ $entry->vendor->vendorProfile->stall_number }}
                                @endif
                                &bull; ₱{{ number_format($entry->price_per_kg, 2) }}/kg
                            </p>
                        </div>
                        <a href="{{ route('staff.confirmations.index') }}"
                           class="flex-shrink-0 text-blue-500 hover:text-blue-700 transition-colors text-[11px]"
                          >
                            Review <i class="bi bi-arrow-right"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="flex flex-col items-center justify-center" style="height: 360px;">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 bg-success-50"
                    >
                    <x-icon name="bi-check2-all" size="lg" class="text-emerald-400" />
                </div>
                <p class="text-slate-500 font-medium text-[12px]">All caught up!</p>
                <p class="text-slate-300 mt-0.5 text-[11px]">No pending entries today</p>
            </div>
        @endif
    </div>

    {{-- Repeat Submissions: the same vendor logging the same fish and class
         more than once today, AM or PM, with what the lines add up to. --}}
    <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-slate-700 font-bold text-[13.5px]">
                    Multiple Submissions Today
                    @if($repeatSubmissions->isNotEmpty())
                        <span class="ml-1.5 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-white font-bold text-[10px] bg-info-600">{{ $repeatSubmissions->count() }}</span>
                    @endif
                </h2>
                <p class="text-slate-400 text-[11px] mt-px">Same vendor, same fish and class &mdash; every AM and PM line side by side</p>
            </div>
        </div>

        @if($repeatSubmissions->isNotEmpty())
            <div class="divide-y divide-slate-100 overflow-y-auto" style="max-height: 360px;">
                @foreach($repeatSubmissions as $group)
                    <div class="px-5 py-3.5">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <div class="min-w-0">
                                <p class="text-slate-700 font-semibold text-[12.5px] truncate">
                                    {{ $group['fish']?->name ?? '—' }}
                                    <span class="text-slate-400 font-normal">&bull; {{ $group['quality_class'] }}</span>
                                </p>
                                <p class="text-slate-400 text-[10.5px]">
                                    {{ $group['vendor']?->name ?? 'Unknown vendor' }}
                                    @if($group['vendor']?->vendorProfile)
                                        &bull; Stall {{ $group['vendor']->vendorProfile->stall_number }}
                                    @endif
                                    &bull; {{ $group['lines']->count() }} submissions
                                </p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-slate-800 font-bold text-[13px]">{{ number_format($group['confirmed_kg'], 1) }} kg</p>
                                <p class="text-slate-400 text-[10px]">confirmed total</p>
                            </div>
                        </div>

                        <ul class="space-y-1">
                            @foreach($group['lines'] as $line)
                                <li class="flex items-center gap-2 text-[11.5px] {{ $line->isRejected() ? 'opacity-50 line-through' : '' }}">
                                    <x-session-badge :session="$line->session()" />
                                    <span class="font-semibold text-slate-700">{{ number_format((float) $line->released_kg, 1) }} kg</span>
                                    <span class="text-slate-400">@ ₱{{ number_format((float) $line->price_per_kg, 2) }}</span>
                                    <span class="text-slate-300">&bull;</span>
                                    <span class="text-slate-400">{{ $line->created_at->format('g:i A') }}</span>
                                    <span class="ml-auto">
                                        @if($line->isConfirmed())
                                            <x-badge variant="success">Confirmed</x-badge>
                                        @elseif($line->isPending())
                                            <x-badge variant="warning">Pending</x-badge>
                                        @else
                                            <x-badge variant="danger">Rejected</x-badge>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-2 text-[10.5px] text-slate-500">
                            AM {{ number_format($group['am_kg'], 1) }} kg
                            &bull; PM {{ number_format($group['pm_kg'], 1) }} kg
                            &bull; <span class="font-semibold text-slate-600">{{ number_format($group['remaining_kg'], 1) }} kg left on the board</span>
                            @if($group['pending_count'] > 0)
                                &bull; <a href="{{ route('staff.confirmations.index') }}" class="text-warning-700 font-semibold hover:underline">{{ number_format($group['pending_kg'], 1) }} kg pending review</a>
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center justify-center" style="height: 360px;">
                <div class="w-10 h-10 rounded-full flex items-center justify-center mb-3 bg-surface-subtle">
                    <x-icon name="bi-layers" size="lg" class="text-slate-300" />
                </div>
                <p class="text-slate-500 font-medium text-[12px]">No repeat submissions</p>
                <p class="text-slate-300 mt-0.5 text-[11px]">Each vendor has logged each fish once today</p>
            </div>
        @endif
    </div>

</div>

@endsection