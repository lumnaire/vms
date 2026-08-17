@extends('layouts.app')

@section('title', 'My Dashboard')
@section('subtitle')
    Personal Inventory Overview · {{ auth()->user()->vendorProfile->stall_number ?? 'No Stall Assigned' }}
@endsection

@section('content')

{{-- ── Welcome Banner ──────────────────────────────────────── --}}
<div class="rounded-xl p-5 mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 overflow-hidden relative"
     style="background: linear-gradient(135deg, #0f2d5e 0%, #1a4a8a 100%); box-shadow: 0 4px 14px rgba(15,45,94,0.3);">
    {{-- Decorative circles --}}
    <div style="position: absolute; right: -20px; top: -20px; width: 120px; height: 120px; border-radius: 50%; background: rgba(255,255,255,0.05);"></div>
    <div style="position: absolute; right: 60px; bottom: -30px; width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.04);"></div>

    <div class="relative">
        <p class="text-blue-300" style="font-size: 11.5px; font-weight: 600;">Good {{ now()->setTimezone('Asia/Manila')->hour < 12 ? 'morning' : (now()->setTimezone('Asia/Manila')->hour < 17 ? 'afternoon' : 'evening') }},</p>
        <h2 class="text-white font-bold mt-0.5" style="font-size: 18px;">{{ auth()->user()->name }}</h2>
        <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-blue-200"
                  style="background: rgba(255,255,255,0.1); font-size: 11px; font-weight: 600;">
                <i class="bi bi-shop"></i>
                {{ auth()->user()->vendorProfile->stall_number ?? 'No Stall Assigned' }}
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full"
                  style="background: rgba(255,255,255,0.1); font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.7);">
                <i class="bi bi-calendar3"></i>
                {{ now()->setTimezone('Asia/Manila')->format('M j, Y') }}
            </span>
        </div>
    </div>

    <div class="relative flex-shrink-0">
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-blue-900 transition-all hover:bg-blue-50"
           style="background: #fff; font-size: 12.5px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);">
            <i class="bi bi-plus-circle"></i>
            Submit Inventory
        </a>
    </div>
</div>

{{-- ── Stat Cards ────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

    {{-- Today's Submissions --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Today's Entries</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $todayEntries ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Items submitted today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #eff6ff;">
                <i class="bi bi-clipboard2-data-fill text-blue-600" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Confirmed --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Confirmed</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $confirmedEntries ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Approved by staff</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #f0fdf4;">
                <i class="bi bi-check-circle-fill text-emerald-500" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Pending --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Pending</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $pendingEntries ?? 0 }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Awaiting staff review</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fffbeb;">
                <i class="bi bi-hourglass-split text-amber-500" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Remaining Stock --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Remaining Stock</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ number_format($remainingStock ?? 0, 1) }} <span style="font-size: 14px; color: #94a3b8; font-weight: 600;">kg</span>
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Unsold today</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background: #fdf4ff;">
                <i class="bi bi-box-seam text-purple-500" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

</div>

{{-- ── Today's Inventory Table ──────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-slate-700 font-bold" style="font-size: 13.5px;">Today's Submissions</h2>
            <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                {{ now()->setTimezone('Asia/Manila')->format('F j, Y') }}
            </p>
        </div>
        <a href="{{ route('vendor.inventory.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 font-semibold transition-colors hover:bg-blue-50"
           style="font-size: 11.5px; border: 1px solid #bfdbfe;">
            <i class="bi bi-plus"></i> Add Entry
        </a>
    </div>

    @if($todayInventory->isEmpty())
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center" style="height: 200px;">
            <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3"
                 style="background: #f8fafc;">
                <i class="bi bi-inbox text-slate-300" style="font-size: 22px;"></i>
            </div>
            <p class="text-slate-500 font-semibold" style="font-size: 13px;">No entries yet today</p>
            <p class="text-slate-400 text-center mt-1" style="font-size: 11.5px;">
                Click "Add Entry" to submit your fish inventory for today.
            </p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-100 text-left">
                        <th class="px-5 py-3 text-slate-400 font-semibold"
                            style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Fish Type</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold"
                            style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Quality</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold"
                            style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Price / kg</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold"
                            style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Stock (kg)</th>
                        <th class="px-5 py-3 text-slate-400 font-semibold"
                            style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach($todayInventory as $item)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-5 py-3 text-slate-700 font-medium" style="font-size: 12.5px;">
                            {{ $item->fishType->name ?? '—' }}
                        </td>
                        <td class="px-5 py-3 text-slate-500" style="font-size: 12px;">
                            {{ $item->quality_class }}
                        </td>
                        <td class="px-5 py-3 text-slate-700" style="font-size: 12px;">
                            ₱{{ number_format($item->price_per_kg, 2) }}
                        </td>
                        <td class="px-5 py-3 text-slate-700" style="font-size: 12px;">
                            {{ number_format($item->stock_kg, 1) }} kg
                        </td>
                        <td class="px-5 py-3">
                            @if($item->status === 'confirmed')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-emerald-700 font-semibold"
                                      style="background: #dcfce7; font-size: 10.5px;">
                                    <i class="bi bi-check-circle-fill"></i> Confirmed
                                </span>
                            @elseif($item->status === 'pending')
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-amber-700 font-semibold"
                                      style="background: #fef9c3; font-size: 10.5px;">
                                    <i class="bi bi-hourglass-split"></i> Pending
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-red-600 font-semibold"
                                      style="background: #fee2e2; font-size: 10.5px;">
                                    <i class="bi bi-x-circle-fill"></i> Rejected
                                </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>

@endsection