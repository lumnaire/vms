@extends('layouts.app')

@section('title', 'My Dashboard')
@section('subtitle')
    Personal Inventory Overview · {{ auth()->user()->vendorProfile->stall_number ?? 'No Stall Assigned' }}
@endsection

@section('content')

<x-alert />

{{-- ── Welcome Banner ──────────────────────────────────────── --}}
<div class="rounded-xl p-5 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 overflow-hidden relative"
     style="background: linear-gradient(135deg, #0f2d5e 0%, #1a4a8a 100%); box-shadow: 0 4px 14px rgba(15,45,94,0.3);">
    {{-- Decorative circles --}}
    <div class="absolute" style="right: -20px; top: -20px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.05)"></div>
    <div class="absolute" style="right: 60px; bottom: -30px; width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.04)"></div>

    <div class="relative">
        <p class="text-blue-300 text-[11.5px] font-semibold">Good {{ now()->setTimezone('Asia/Manila')->hour < 12 ? 'morning' : (now()->setTimezone('Asia/Manila')->hour < 17 ? 'afternoon' : 'evening') }},</p>
        <h2 class="text-white font-bold mt-0.5" style="font-size: 18px;">{{ auth()->user()->name }}</h2>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-blue-200 text-[11px] font-semibold"
                  style="background: rgba(255,255,255,0.1)">
                <i class="bi bi-shop"></i>
                {{ auth()->user()->vendorProfile->stall_number ?? 'No Stall Assigned' }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold"
                  style="background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.7)">
                <i class="bi bi-calendar3"></i>
                {{ now()->setTimezone('Asia/Manila')->format('M j, Y') }}
            </span>
        </div>
    </div>

    <div class="relative flex-shrink-0">
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-blue-900 transition-all hover:bg-blue-50 text-[12.5px]"
           style="background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15)">
            <i class="bi bi-plus-circle"></i>
            Submit Inventory
        </a>
    </div>
</div>

{{-- ── Stat Cards ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Today's Submissions --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Today's Entries</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $todayEntries ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Items submitted today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-brand-50"
                >
                <x-icon name="bi-clipboard2-data-fill" size="lg" class="text-blue-600" />
            </div>
        </div>
    </div>

    {{-- Confirmed --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Confirmed</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $confirmedEntries ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Approved by staff</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50"
                >
                <x-icon name="bi-check-circle-fill" size="lg" class="text-emerald-500" />
            </div>
        </div>
    </div>

    {{-- Pending --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Pending</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ $pendingEntries ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Awaiting staff review</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-warning-50"
                >
                <x-icon name="bi-hourglass-split" size="lg" class="text-amber-500" />
            </div>
        </div>
    </div>

    {{-- Remaining Stock — released minus what today's sale report declared sold --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100 shadow-card">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]">Remaining Stock</p>
                <p class="text-slate-800 font-bold mt-1 text-[28px] leading-[1]">
                    {{ number_format($remainingStock ?? 0, 1) }} <span class="text-[14px] font-semibold text-slate-400">kg</span>
                </p>
                <p class="text-slate-400 mt-1 text-[11px]">Across every batch still on sale</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fdf4ff;">
                <x-icon name="bi-box-seam" size="lg" class="text-purple-500" />
            </div>
        </div>
    </div>

</div>

{{-- ── Today's Inventory Table ──────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">Today's Batches</h2>
            <p class="text-slate-400 text-[11px] mt-px">
                {{ now()->setTimezone('Asia/Manila')->format('F j, Y') }}
            </p>
        </div>
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 font-semibold transition-colors hover:bg-blue-50 text-[11.5px] border border-brand-200"
          >
            <i class="bi bi-plus"></i> Add Entry
        </a>
    </div>

    @if($todayInventory->isEmpty())
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center" style="height: 200px;">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3 bg-surface-subtle"
                >
                <x-icon name="bi-inbox" size="2xl" class="text-slate-300" />
            </div>
            <p class="text-slate-500 font-semibold text-[13px]">No entries yet today</p>
            <p class="text-slate-400 text-center mt-1 text-[11.5px]">
                Click "Add Entry" to submit your fish inventory for today.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-100 text-left">
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Fish Type</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Quality</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Price / kg</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Stock (kg)</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Remaining</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Status</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold text-[10.5px] uppercase tracking-[0.07em]"
                           >Days Left</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($todayInventory as $item)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3 text-slate-700 font-medium text-[12.5px]">
                            {{ $item->fishType->name ?? '—' }}
                            <span class="block text-[11px] text-slate-400 font-normal">{{ $item->batchLabel() }}</span>
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-[12px]">
                            {{ $item->quality_class }}
                        </td>
                        <td class="px-5 py-3 text-slate-700 text-[12px]">
                            ₱{{ number_format($item->price_per_kg, 2) }}
                        </td>
                        <td class="px-5 py-3 text-slate-700 text-[12px]">
                            {{ number_format($item->stock_kg, 1) }} kg
                        </td>
                        {{-- Stock minus what the vendor released as sold, the same figure the price board shows. --}}
                        <td class="px-5 py-3 text-[12px] font-semibold {{ $item->isConfirmed() ? 'text-slate-700' : 'text-slate-300' }}">
                            {{ $item->isConfirmed() ? number_format($item->getRemainingStock(), 1) . ' kg' : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            @if($item->status === 'confirmed')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-emerald-700 font-semibold text-[10.5px]"
                                      style="background: #dcfce7">
                                    <i class="bi bi-check-circle-fill"></i> Confirmed
                                </span>
                            @elseif($item->status === 'pending')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-amber-700 font-semibold text-[10.5px]"
                                      style="background: #fef9c3">
                                    <i class="bi bi-hourglass-split"></i> Pending
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-red-600 font-semibold text-[10.5px]"
                                      style="background: #fee2e2">
                                    <i class="bi bi-x-circle-fill"></i> Rejected
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-slate-500 text-[11.5px] font-semibold whitespace-nowrap">{{ $item->isCountingDown() ? $item->countdownLabel() : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>


@endsection
