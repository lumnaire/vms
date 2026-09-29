@extends('layouts.app')

@section('title', 'Price Confirmations')
@section('subtitle', 'Vendor Entry Review · Price Confirmation Workflow')

@push('styles')
<style>
    .price-label-cheap    { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .price-label-moderate { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .price-label-expensive{ background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .progress-bar-track   { background: #f1f5f9; border-radius: 99px; overflow: hidden; height: 6px; }
    .progress-bar-fill    { border-radius: 99px; height: 100%; transition: width 0.5s ease; }
</style>
@endpush

@section('content')

{{-- ── Flash Messages ────────────────────────────────────────────── --}}
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
@if(session('error'))
<div class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-[13.5px] bg-danger-50 border border-danger-200 text-danger-800"
    >
    <x-icon name="bi-x-circle-fill" size="base" class="flex-shrink-0 text-danger-500" />
    <span class="font-medium">{{ session('error') }}</span>
    <button onclick="this.parentElement.remove()"
            class="ml-auto hover:opacity-60 transition-opacity" style="color: #fca5a5;">
        <x-icon name="bi-x-lg" size="md" />
    </button>
</div>
@endif

{{-- ── Progress Tracker ─────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 p-5 mb-6 shadow-card"
    >

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
        <div>
            <h2 class="text-slate-700 font-bold text-[14px]">Today's Confirmation Progress</h2>
            <p class="text-slate-400 text-[11.5px] mt-px">
                {{ now()->format('l, F j, Y') }}
            </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
            <span class="text-slate-600 font-semibold text-[13px]">
                {{ $confirmedCount }} / {{ $totalToday }} processed
            </span>
        </div>
    </div>

    {{-- Progress bar --}}
    @php
        $pct = $totalToday > 0 ? round(($confirmedCount / $totalToday) * 100) : 0;
    @endphp
    <div class="progress-bar-track mb-4">
        <div class="progress-bar-fill bg-success-600" style="width: {{ $pct }}%"></div>
    </div>

    {{-- Counts row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <x-stat-card tinted tone="neutral" label="Total"     :value="$totalToday"     decimals="0" />
        <x-stat-card tinted tone="warning" label="Pending"   :value="$pendingCount"   decimals="0" />
        <x-stat-card tinted tone="success" label="Confirmed" :value="$confirmedCount" decimals="0" />
        <x-stat-card tinted tone="danger"  label="Rejected"  :value="$rejectedCount"  decimals="0" />
    </div>
</div>

{{-- ── Pending Entries ──────────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card"
    >

    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <div>
            <h2 class="text-slate-700 font-bold text-[13.5px]">
                Pending Review
                @if($pendingCount > 0)
                    <span class="ml-2 inline-flex items-center justify-center w-5 h-5 rounded-full text-white font-bold text-[10px]"
                          style="background: #f59e0b">{{ $pendingCount }}</span>
                @endif
            </h2>
            <p class="text-slate-400 text-[11px] mt-px">
                Review each entry and approve or reject before it is published to the public price board.
            </p>
        </div>
    </div>

    @if($pendingEntries->isEmpty())
    <div class="flex flex-col items-center justify-center py-16 text-center">
        <div class="w-14 h-14 rounded-full flex items-center justify-center mb-4 bg-success-50"
            >
            <x-icon name="bi-check2-all" size="2xl" class="text-success-500" />
        </div>
        <p class="text-slate-600 font-semibold text-[14px]">All entries reviewed!</p>
        <p class="text-slate-400 mt-1 text-[12px]" style="max-width: 280px">
            No pending submissions for today. Check back when vendors log their entries.
        </p>
    </div>

    @else

    {{-- ── Entry Cards ─────────────────────────────────────────── --}}
    <div class="divide-y divide-slate-50">

        @foreach($pendingEntries as $entry)
        @php
            $guideKey = $entry->fish_type_id . '_' . $entry->quality_class;
            $guide    = $priceGuides->get($guideKey);
            $compPeers = $confirmedToday->get($guideKey, collect());

            // Price label
            $priceLabel = null;
            $priceLabelClass = '';
            if ($guide) {
                $priceLabel = $guide->getPriceLabel($entry->price_per_kg);
                $priceLabelClass = match($priceLabel) {
                    'Cheap'      => 'price-label-cheap',
                    'Moderate'   => 'price-label-moderate',
                    'Expensive'  => 'price-label-expensive',
                    default      => '',
                };
            }
        @endphp

        <div class="px-5 py-5" style="transition: background 0.1s;"
             onmouseover="this.style.background='#fafbfc'"
             onmouseout="this.style.background='transparent'">

            <div class="flex flex-col lg:flex-row lg:items-start gap-4">

                {{-- Left: Entry details --}}
                <div class="flex-1 min-w-0">

                    {{-- Vendor info --}}
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-brand-50"
                            >
                            <x-icon name="bi-person-fill" size="md" class="text-blue-500" />
                        </div>
                        <div>
                            <p class="text-slate-700 font-bold text-[13px]">
                                {{ $entry->vendor->name }}
                            </p>
                            @if($entry->vendor->vendorProfile)
                            <p class="text-slate-400 text-[11px]">
                                Stall {{ $entry->vendor->vendorProfile->stall_number }}
                            </p>
                            @endif
                        </div>
                        <span class="ml-auto text-slate-400 text-[11px]">
                            {{ $entry->created_at->format('g:i A') }}
                        </span>
                    </div>

                    {{-- Entry data grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

                        <div class="rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                            <p class="text-slate-400 text-[10px] font-semibold uppercase" style="letter-spacing: 0.06em">Fish Type</p>
                            <p class="text-slate-700 font-semibold mt-0.5 text-[13px]">{{ $entry->fishType->name }}</p>
                        </div>

                        <div class="rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                            <p class="text-slate-400 text-[10px] font-semibold uppercase" style="letter-spacing: 0.06em">Quality</p>
                            <p class="text-slate-700 font-semibold mt-0.5 text-[13px]">{{ $entry->quality_class }}</p>
                        </div>

                        <div class="rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                            <p class="text-slate-400 text-[10px] font-semibold uppercase" style="letter-spacing: 0.06em">Price/kg</p>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <p class="text-slate-700 font-bold text-[14px]">₱{{ number_format($entry->price_per_kg, 2) }}</p>
                                @if($priceLabel)
                                <span class="{{ $priceLabelClass }} inline-flex px-1.5 py-0.5 rounded font-semibold"
                                      style="font-size: 9.5px;">{{ $priceLabel }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                            <p class="text-slate-400 text-[10px] font-semibold uppercase" style="letter-spacing: 0.06em">Stock</p>
                            <p class="text-slate-700 font-semibold mt-0.5 text-[13px]">
                                {{ number_format($entry->stock_kg, 1) }} kg
                                <span class="text-slate-400 text-[11px]" style="font-weight: 400">
                                    ({{ number_format($entry->released_kg, 1) }} released)
                                </span>
                            </p>
                        </div>

                    </div>

                    {{-- Price comparison row --}}
                    @if($guide || $compPeers->isNotEmpty())
                    <div class="mt-3 rounded-lg px-3 py-2.5"
                         style="background: #f0f9ff; border: 1px solid #bae6fd;">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1">

                            @if($guide)
                            <div class="flex items-center gap-1.5 text-info-600 text-[11px]">
                                <i class="bi bi-tags-fill"></i>
                                <span class="font-semibold">Guide:</span>
                                <span>
                                    Cheap ≤ ₱{{ number_format($guide->cheap_max, 2) }} ·
                                    Moderate ≤ ₱{{ number_format($guide->moderate_max, 2) }} ·
                                    Expensive > ₱{{ number_format($guide->moderate_max, 2) }}
                                </span>
                            </div>
                            @endif

                            @if($compPeers->isNotEmpty())
                            <div class="flex items-center gap-1.5 text-info-600 text-[11px]">
                                <i class="bi bi-people-fill"></i>
                                <span class="font-semibold">Other confirmed:</span>
                                <span>
                                    ₱{{ number_format($compPeers->min('price_per_kg'), 2) }}
                                    – ₱{{ number_format($compPeers->max('price_per_kg'), 2) }}
                                    ({{ $compPeers->count() }} {{ Str::plural('vendor', $compPeers->count()) }})
                                </span>
                            </div>
                            @endif

                        </div>
                    </div>
                    @endif

                </div>

                {{-- Right: Action buttons --}}
                <div class="flex flex-row lg:flex-col gap-2 lg:w-36 flex-shrink-0">

                    {{-- Approve --}}
                    <button class="text-[13px] flex-1 lg:flex-none w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-semibold transition-colors bg-success-600" type="button"
                            onclick="openConfirmModal(this)"
                            data-action="approve"
                            data-url="{{ route('staff.confirmations.approve', $entry) }}"
                            data-fish="{{ $entry->fishType->name }}"
                            data-quality="{{ $entry->quality_class }}"
                            data-price="{{ number_format($entry->price_per_kg, 2) }}"
                            data-vendor="{{ $entry->vendor->name }}"
                            data-stall="{{ $entry->vendor->vendorProfile->stall_number ?? '' }}"
                            data-stock="{{ number_format($entry->stock_kg, 1) }}"
                            data-label="{{ $priceLabel ?? '' }}"
                            data-labelclass="{{ $priceLabelClass ?? '' }}"
                            onmouseover="this.style.background='#047857'"
                            onmouseout="this.style.background='#059669'" style="color: white; border: none; cursor: pointer">
                        <x-icon name="bi-check2-circle" size="md" />
                        Approve
                    </button>

                    {{-- Reject --}}
                    <button class="text-[13px] flex-1 lg:flex-none w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg font-semibold transition-colors" type="button"
                            onclick="openConfirmModal(this)"
                            data-action="reject"
                            data-url="{{ route('staff.confirmations.reject', $entry) }}"
                            data-fish="{{ $entry->fishType->name }}"
                            data-quality="{{ $entry->quality_class }}"
                            data-price="{{ number_format($entry->price_per_kg, 2) }}"
                            data-vendor="{{ $entry->vendor->name }}"
                            style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; cursor: pointer"
                            onmouseover="this.style.background='#fee2e2'"
                            onmouseout="this.style.background='#fef2f2'">
                        <x-icon name="bi-x-circle" size="md" />
                        Reject
                    </button>

                </div>

            </div>
        </div>
        @endforeach

    </div>

    @endif

</div>


{{-- ── Confirmation Modal ───────────────────────────────────────── --}}
<div id="confirmModal"
     class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden"
    
     aria-modal="true" role="dialog" style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px)">

    <div id="modalCard"
         class="bg-white rounded-2xl w-full overflow-hidden"
         style="max-width: 430px; box-shadow: 0 25px 60px rgba(0,0,0,0.22); transform: scale(0.94); transition: transform 0.15s cubic-bezier(0.34,1.56,0.64,1), opacity 0.15s ease; opacity: 0;">

        {{-- Header --}}
        <div id="modalHeader" class="px-6 pt-6 pb-5 border-b border-slate-100">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div id="modalIconWrap" class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i id="modalIcon" style="font-size: 20px;"></i>
                    </div>
                    <div>
                        <h3 id="modalTitle" class="text-slate-800 font-bold" style="font-size: 15.5px;"></h3>
                        <p id="modalSubtitle" class="text-slate-400 text-[11.5px] mt-0.5"></p>
                    </div>
                </div>
                <button onclick="closeConfirmModal()"
                        class="w-7 h-7 rounded-full flex items-center justify-center hover:bg-slate-100 transition-colors flex-shrink-0 mt-0.5 text-slate-400"
                        style="border: none; cursor: pointer; background: transparent">
                    <x-icon name="bi-x-lg" size="sm" />
                </button>
            </div>
        </div>

        {{-- Entry detail grid --}}
        <div class="px-6 pt-5 pb-2">
            <div class="rounded-xl overflow-hidden border border-slate-100 mb-4">
                <div class="grid grid-cols-2 bg-surface-subtle">
                    <div class="px-4 py-3 border-b border-r border-slate-100">
                        <p class="text-slate-400 font-semibold uppercase tracking-[0.07em]" style="font-size: 9.5px">Fish Type</p>
                        <p id="mFish" class="text-slate-700 font-semibold mt-0.5 text-[13px]"></p>
                    </div>
                    <div class="px-4 py-3 border-b border-slate-100">
                        <p class="text-slate-400 font-semibold uppercase tracking-[0.07em]" style="font-size: 9.5px">Quality Class</p>
                        <p id="mQuality" class="text-slate-700 font-semibold mt-0.5 text-[13px]"></p>
                    </div>
                    <div class="px-4 py-3 border-r border-slate-100">
                        <p class="text-slate-400 font-semibold uppercase tracking-[0.07em]" style="font-size: 9.5px">Price / kg</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <p id="mPrice" class="text-slate-700 font-bold" style="font-size: 15px;"></p>
                            <span id="mLabel" class="inline-flex px-1.5 py-0.5 rounded font-semibold hidden" style="font-size: 9.5px"></span>
                        </div>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-slate-400 font-semibold uppercase tracking-[0.07em]" style="font-size: 9.5px">Vendor</p>
                        <p id="mVendor" class="text-slate-700 font-semibold mt-0.5 text-[13px]"></p>
                        <p id="mStall" class="text-slate-400 text-[10.5px]"></p>
                    </div>
                </div>
            </div>

            {{-- Contextual note --}}
            <div id="modalNote" class="rounded-xl px-4 py-3 mb-5 text-[12px] leading-[1.6]"></div>
        </div>

        {{-- Actions --}}
        <div class="px-6 pb-6 flex gap-3">
            <button type="button"
                    onclick="closeConfirmModal()"
                    class="flex-1 px-4 py-2.5 rounded-xl font-semibold transition-colors bg-surface-muted text-[13px] text-slate-600"
                   
                    onmouseover="this.style.background='#e2e8f0'"
                    onmouseout="this.style.background='#f1f5f9'" style="border: none; cursor: pointer">
                Cancel
            </button>
            <form id="modalForm" method="POST" class="flex-1" style="margin: 0;">
                @csrf
                <input type="hidden" name="_method" value="PATCH">
                <button type="submit"
                        id="modalConfirmBtn"
                        class="w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-semibold transition-colors text-[13px]"
                        style="border: none; color: white; cursor: pointer">
                    <i class="text-[14px]" id="modalBtnIcon"></i>
                    <span id="modalBtnText"></span>
                </button>
            </form>
        </div>

    </div>
</div>

@push('scripts')
<script>
    const _modal     = document.getElementById('confirmModal');
    const _modalCard = document.getElementById('modalCard');

    function openConfirmModal(btn) {
        const isApprove = btn.dataset.action === 'approve';

        // ── Populate entry details ─────────────────────────────
        document.getElementById('mFish').textContent    = btn.dataset.fish;
        document.getElementById('mQuality').textContent = btn.dataset.quality + ' Class';
        document.getElementById('mPrice').textContent   = '₱' + btn.dataset.price;
        document.getElementById('mVendor').textContent  = btn.dataset.vendor;

        const stallEl = document.getElementById('mStall');
        stallEl.textContent  = btn.dataset.stall ? 'Stall ' + btn.dataset.stall : '';
        stallEl.style.display = btn.dataset.stall ? '' : 'none';

        const labelEl = document.getElementById('mLabel');
        if (btn.dataset.label && btn.dataset.labelclass) {
            labelEl.textContent = btn.dataset.label;
            labelEl.className   = btn.dataset.labelclass + ' inline-flex px-1.5 py-0.5 rounded font-semibold';
            labelEl.style.display = '';
        } else {
            labelEl.style.display = 'none';
        }

        // ── Approve mode ───────────────────────────────────────
        if (isApprove) {
            document.getElementById('modalIconWrap').style.background = '#dcfce7';
            document.getElementById('modalIcon').className             = 'bi bi-check2-circle';
            document.getElementById('modalIcon').style.color           = '#059669';
            document.getElementById('modalTitle').textContent          = 'Approve this entry?';
            document.getElementById('modalSubtitle').textContent       = btn.dataset.fish + ' · ' + btn.dataset.quality + ' Class · ₱' + btn.dataset.price + '/kg';
            document.getElementById('modalNote').style.cssText         = 'background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:12px; padding:12px 16px; font-size:12px; line-height:1.6; margin-bottom:20px;';
            document.getElementById('modalNote').innerHTML             = '<i class="bi bi-broadcast text-success-600" style="margin-right:6px"></i>This entry will be <strong>published to the public price board</strong> and counted toward today\'s confirmed supply.';
            document.getElementById('modalConfirmBtn').style.background = '#059669';
            document.getElementById('modalBtnIcon').className           = 'bi bi-check2-circle';
            document.getElementById('modalBtnText').textContent         = 'Yes, Approve';
        }
        // ── Reject mode ────────────────────────────────────────
        else {
            document.getElementById('modalIconWrap').style.background = '#fee2e2';
            document.getElementById('modalIcon').className             = 'bi bi-x-circle-fill';
            document.getElementById('modalIcon').style.color           = '#dc2626';
            document.getElementById('modalTitle').textContent          = 'Reject this entry?';
            document.getElementById('modalSubtitle').textContent       = 'From ' + btn.dataset.vendor + (btn.dataset.stall ? ' · Stall ' + btn.dataset.stall : '');
            document.getElementById('modalNote').style.cssText         = 'background:#fef2f2; border:1px solid #fecaca; color:#991b1b; border-radius:12px; padding:12px 16px; font-size:12px; line-height:1.6; margin-bottom:20px;';
            document.getElementById('modalNote').innerHTML             = '<i class="bi bi-exclamation-triangle-fill text-danger-600" style="margin-right:6px"></i>This entry will be <strong>marked as rejected</strong>. The vendor will need to resubmit their inventory for today.';
            document.getElementById('modalConfirmBtn').style.background = '#dc2626';
            document.getElementById('modalBtnIcon').className           = 'bi bi-x-circle';
            document.getElementById('modalBtnText').textContent         = 'Yes, Reject';
        }

        // ── Set form action & show ─────────────────────────────
        document.getElementById('modalForm').action = btn.dataset.url;
        _modal.style.display = 'flex';
        requestAnimationFrame(() => {
            _modalCard.style.transform = 'scale(1)';
            _modalCard.style.opacity   = '1';
        });
    }

    function closeConfirmModal() {
        _modalCard.style.transform = 'scale(0.94)';
        _modalCard.style.opacity   = '0';
        setTimeout(() => { _modal.style.display = 'none'; }, 140);
    }

    // Backdrop click
    _modal.addEventListener('click', e => { if (e.target === _modal) closeConfirmModal(); });

    // Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && _modal.style.display === 'flex') closeConfirmModal();
    });
</script>
@endpush

@endsection