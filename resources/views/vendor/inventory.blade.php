@extends('layouts.app')

@section('title', 'My Inventory')
@section('subtitle', 'Fish Batches · Vendor View')

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
        background: white;
    }
    .form-input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96,165,250,0.15);
    }
    .form-input::placeholder { color: #cbd5e1; }
    .form-input:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }
    .form-input.is-over-guide,
    .form-input.is-over-guide:focus {
        border-color: #f87171;
        color: #991b1b;
        -webkit-text-fill-color: #991b1b;
        font-weight: 600;
        box-shadow: 0 0 0 3px rgba(248,113,113,0.15);
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 6px;
        letter-spacing: 0.01em;
    }
    .th-cell {
        padding: 12px 16px;
        color: #94a3b8;
        font-weight: 600;
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        white-space: nowrap;
    }
    .batch-row { border-bottom: 1px solid #f1f5f9; transition: background 0.1s; }
    .batch-row:hover { background: #f8faff; }

    /* State pills */
    .state-pill {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 999px;
        font-size: 10.5px; font-weight: 600; white-space: nowrap;
    }
    .state-pending     { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .state-on_sale     { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .state-sold_out    { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .state-rejected    { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .state-expired     { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }

    /* Countdown to automatic deletion, e.g. "2 days left" */
    .days-pill {
        display: inline-block; padding: 2px 8px; border-radius: 6px;
        font-size: 11px; font-weight: 700; white-space: nowrap;
        background: #f1f5f9; color: #475569;
    }
    .days-pill.is-last  { background: #fffbeb; color: #b45309; }

    /* ⋮ batch menu, positioned against the viewport so the scrolling table
       cannot clip it */
    .kebab-btn {
        width: 30px; height: 30px; border-radius: 8px;
        display: inline-flex; align-items: center; justify-content: center;
        color: #64748b; border: 1px solid transparent; background: transparent; cursor: pointer;
    }
    .kebab-btn:hover, .kebab-btn[aria-expanded="true"] { background: #f1f5f9; border-color: #e2e8f0; color: #1e293b; }
    #batchMenu {
        position: fixed; z-index: 60; width: 220px;
        background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
        box-shadow: 0 12px 32px rgba(15,23,42,0.16); padding: 6px;
    }
    /* openBatchMenu() hides the actions a batch does not offer with .hidden.
       These rules are unlayered, so they outrank Tailwind's .hidden utility:
       without the second rule every action showed on every batch, and "Cancel
       batch" on a confirmed one posted to an empty URL. */
    .menu-item {
        width: 100%; display: flex; align-items: center; gap: 8px;
        padding: 8px 10px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
        color: #334155; background: none; border: 0; cursor: pointer; text-align: left;
    }
    .menu-item:hover { background: #f1f5f9; }
    .menu-item.is-danger { color: #b91c1c; }
    .menu-item.is-danger:hover { background: #fef2f2; }
    .menu-stat {
        display: flex; justify-content: space-between; align-items: baseline;
        padding: 7px 10px; font-size: 12px; color: #64748b;
    }
    .menu-stat strong { color: #0f172a; font-size: 13px; }
    .menu-sep { height: 1px; background: #f1f5f9; margin: 4px 2px; }
    #batchMenu .hidden { display: none; }

    .existing-batches {
        border: 1px solid #fde68a; background: #fffbeb; color: #92400e;
        border-radius: 10px; padding: 10px 12px; font-size: 11.5px; line-height: 1.5;
    }
    .existing-batches ul { margin: 6px 0 8px; padding-left: 16px; list-style: disc; }
</style>
@endpush

@section('content')

@php
    $releaseFailed = (bool) old('release_entry');
    $formOld = fn (string $key) => $releaseFailed ? null : old($key);
    $freshness = \App\Models\VendorInventory::freshnessDays();
@endphp

{{-- ── Flash Messages ───────────────────────────────────────────── --}}
<x-alert />

@if($errors->any() && ! $releaseFailed)
<div class="mb-5 px-4 py-3 rounded-xl text-[13px] bg-danger-50 border border-danger-200 text-danger-800">
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

{{-- ── Stats ────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-6">
    <x-stat-card label="Remaining Stock" :value="$remainingKg" unit="kg" tone="neutral" />
    <x-stat-card label="Batches on Sale" :value="$onSaleCount" decimals="0" tone="success" />
    <x-stat-card label="Pending" :value="$pendingCount" decimals="0" tone="warning" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

    {{-- ── Submit a Batch (left column) ────────────────────────── --}}
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">

            <div class="px-5 py-4 border-b border-slate-100"
                 style="background: linear-gradient(135deg, #0f2d5e, #0a1f3c);">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background: rgba(96,165,250,0.2);">
                        <x-icon name="bi-plus-circle-fill" size="base" class="text-blue-300" />
                    </div>
                    <div>
                        <h2 class="text-white font-bold text-[13.5px]">Submit a Batch</h2>
                        <p class="text-blue-300 text-[11px] mt-px">{{ now()->format('l, F j, Y') }}</p>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('vendor.inventory.store') }}" class="px-5 py-5" id="batchForm">
                @csrf

                <div class="space-y-4">

                    {{-- Quality Class --}}
                    <div>
                        <label class="form-label">
                            Quality Class <span class="text-danger-500">*</span>
                        </label>
                        <select name="quality_class" id="qualityClassSelect" required class="form-input">
                            <option value="">— Select class —</option>
                            @foreach(\App\Models\FishType::QUALITY_CLASSES as $class)
                                <option value="{{ $class }}" @selected($formOld('quality_class') == $class)>{{ $class }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Fish Type (filtered by the selected quality class) --}}
                    <div>
                        <label class="form-label">
                            Fish Type <span class="text-danger-500">*</span>
                        </label>
                        <select name="fish_type_id" id="fishTypeSelect" required class="form-input">
                            <option value="">— Select fish type —</option>
                            @foreach($fishTypes as $fish)
                                <option value="{{ $fish->id }}"
                                    data-quality-class="{{ $fish->quality_class }}"
                                    @selected($formOld('fish_type_id') == $fish->id)>
                                    {{ $fish->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Already have this fish: a reminder shown by script when the
                         chosen fish has batches on the stall or waiting for staff.
                         Nothing to tick; submitting makes the next batch. --}}
                    <div id="existingBatches" class="existing-batches hidden" role="status">
                        <p class="font-semibold flex items-center gap-1.5">
                            <x-icon name="bi-layers-fill" size="xs" />
                            You already have <span id="existingFish"></span>:
                            <span id="existingKg"></span> remaining
                        </p>
                        <ul id="existingList"></ul>
                        <p class="font-semibold text-amber-900">
                            This will be submitted as <span id="nextBatchLabel">another batch</span>.
                        </p>
                    </div>

                    {{-- Price per kg --}}
                    <div>
                        <label class="form-label" for="pricePerKgInput">
                            Price per kg (₱) <span class="text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute text-[13px] font-semibold left-3 top-1/2 -translate-y-1/2 text-slate-400">₱</span>
                            <input type="number" name="price_per_kg" id="pricePerKgInput"
                                   value="{{ $formOld('price_per_kg') }}"
                                   step="0.01" min="0.01" max="99999.99"
                                   placeholder="0.00"
                                   oninput="checkPriceGuide()"
                                   required class="form-input" style="padding-left: 28px;">
                        </div>
                        <p class="mt-1 text-slate-400 text-[11px]" id="priceGuideHint">Select a fish type to see its price guideline.</p>

                        {{-- Shown when the entered price is above the guideline --}}
                        <div id="priceGuideWarning" class="hidden mt-2">
                            <div class="flex items-start gap-1.5 rounded-lg px-2.5 py-2 text-[11px] leading-[1.45]"
                                 style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                                <span class="flex-shrink-0 mt-px" id="priceGuideWarningIcon" title="Price exceeds the price guideline.">
                                    <x-icon name="bi-exclamation-triangle-fill" size="xs" />
                                </span>
                                <span>
                                    <strong class="font-semibold">Price exceeds the price guideline.</strong>
                                    <span id="priceGuideWarningText"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Stock kg: everything brought is for sale --}}
                    <div>
                        <label class="form-label">
                            Stock (kg) <span class="text-danger-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="number" name="stock_kg" id="stockKgInput"
                                   value="{{ $formOld('stock_kg') }}"
                                   step="0.1" min="0.1" max="99999.99"
                                   placeholder="0.0"
                                   required class="form-input">
                            <span class="absolute text-[12px] top-1/2 -translate-y-1/2 text-slate-400" style="right:12px">kg</span>
                        </div>
                        <p class="mt-1 text-slate-400 text-[11px]">All of it goes on sale once staff confirm the batch.</p>
                    </div>

                </div>

                <button type="submit"
                        class="mt-6 w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-white font-semibold transition-colors text-[13.5px] bg-success-600 hover:bg-success-700">
                    <x-icon name="bi-send-fill" size="md" />
                    Submit Batch
                </button>

                <div class="mt-3 rounded-lg px-3 py-2.5 bg-surface-subtle border border-slate-200">
                    <p class="text-[11px] leading-[1.5] text-slate-500">
                        <x-icon name="bi-info-circle-fill" class="mr-1 text-slate-400" />
                        Unsold batches are <strong>removed automatically after {{ $freshness }} days</strong>.
                        Use <strong>⋮ → Release</strong> on a batch to record the kilograms you sold.
                    </p>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Batches (right column) ──────────────────────────────── --}}
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">

            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h2 class="text-slate-700 font-bold text-[13.5px]">My Batches</h2>
                    <p class="text-slate-400 text-[11px] mt-px">
                        Today's submissions and every batch still on your stall &middot;
                        {{ $batches->count() }} {{ Str::plural('batch', $batches->count()) }}
                    </p>
                </div>
            </div>

            @if($batches->isEmpty())
            <div class="flex flex-col items-center justify-center py-14 text-center">
                <div class="w-12 h-12 rounded-full flex items-center justify-center mb-3 bg-surface-subtle">
                    <x-icon name="bi-inbox" size="2xl" class="text-slate-300" />
                </div>
                <p class="text-slate-500 font-semibold text-[13px]">No batches on your stall</p>
                <p class="text-slate-400 mt-1 text-[12px]">Use the form on the left to submit your first batch.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full" style="border-collapse: collapse; min-width: 720px;">
                    <thead>
                        <tr class="bg-surface-subtle" style="border-bottom: 1px solid #f1f5f9">
                            <th class="th-cell text-left">Fish / Batch</th>
                            <th class="th-cell text-left">Class</th>
                            <th class="th-cell text-right">Price/kg</th>
                            <th class="th-cell text-right">Stock</th>
                            <th class="th-cell text-right">Remaining</th>
                            <th class="th-cell text-center">Days Left</th>
                            <th class="th-cell text-center">Status</th>
                            <th class="th-cell text-center"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batches as $entry)
                            @include('vendor.partials.batch-row', ['entry' => $entry, 'showDate' => false])
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- ── Closed batches (past 7 days) ─────────────────────── --}}
        @if($history->isNotEmpty())
        <div class="mt-5 bg-white rounded-xl border border-slate-100 overflow-hidden shadow-card">
            <div class="px-5 py-4 border-b border-slate-100">
                <h2 class="text-slate-700 font-bold text-[13.5px]">Closed Batches &middot; Past 7 Days</h2>
                <p class="text-slate-400 text-[11px] mt-px">Sold out or rejected.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full" style="border-collapse: collapse; min-width: 720px;">
                    <thead>
                        <tr class="bg-surface-subtle" style="border-bottom: 1px solid #f1f5f9">
                            <th class="th-cell text-left">Fish / Batch</th>
                            <th class="th-cell text-left">Class</th>
                            <th class="th-cell text-right">Price/kg</th>
                            <th class="th-cell text-right">Stock</th>
                            <th class="th-cell text-right">Sold</th>
                            <th class="th-cell text-center">Days Left</th>
                            <th class="th-cell text-center">Status</th>
                            <th class="th-cell"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $entry)
                            @include('vendor.partials.batch-row', ['entry' => $entry, 'showDate' => true, 'closed' => true])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ── ⋮ Batch menu (one, shared by every row) ─────────────────── --}}
<div id="batchMenu" class="hidden" role="menu">
    <button type="button" class="menu-item" data-menu="release" role="menuitem">
        <x-icon name="bi-cart-check-fill" size="sm" class="text-success-600" /> Release (record sale)
    </button>
    <div class="menu-sep" data-menu="release-sep"></div>
    <div class="menu-stat"><span>Total sold</span><strong id="menuSold"></strong></div>
    <div class="menu-stat"><span>Remaining</span><strong id="menuRemaining"></strong></div>
    <div class="menu-sep" data-menu="extra-sep"></div>
    <button type="button" class="menu-item is-danger" data-menu="cancel" role="menuitem">
        <x-icon name="bi-x-circle-fill" size="sm" /> Cancel batch
    </button>
</div>

{{-- ── Release modal: record kilograms sold ─────────────────────── --}}
<div id="releaseModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="releaseTitle"
     style="background: rgba(15,23,42,0.55); backdrop-filter: blur(4px)"
     onclick="if (event.target === this) closeModal('releaseModal')">
    <div class="bg-white rounded-2xl w-full max-w-sm overflow-hidden shadow-modal">
        <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 bg-success-50">
                    <x-icon name="bi-cart-check-fill" size="lg" class="text-success-600" />
                </div>
                <div class="min-w-0">
                    <h3 id="releaseTitle" class="text-slate-800 font-bold text-[15px]">Release</h3>
                    <p id="releaseSubtitle" class="text-slate-400 text-[11.5px] mt-0.5 truncate"></p>
                </div>
            </div>
            <button type="button" onclick="closeModal('releaseModal')" aria-label="Close"
                    class="w-7 h-7 rounded-full flex items-center justify-center hover:bg-slate-100 text-slate-400">
                <x-icon name="bi-x-lg" size="sm" />
            </button>
        </div>
        <form id="releaseForm" method="POST" class="px-5 py-5 space-y-4">
            @csrf
            <input type="hidden" name="release_entry" id="releaseEntry">

            @if($releaseFailed && $errors->has('release_kg'))
                <div class="rounded-lg px-3 py-2.5 text-[12px] bg-danger-50 border border-danger-200 text-danger-800">
                    {{ $errors->first('release_kg') }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-2 text-[12px]">
                <div class="rounded-lg bg-surface-subtle border border-slate-200 px-3 py-2">
                    <p class="text-slate-400 text-[10px] font-semibold uppercase tracking-[0.06em]">Sold so far</p>
                    <p id="releaseSold" class="text-slate-700 font-bold"></p>
                </div>
                <div class="rounded-lg bg-surface-subtle border border-slate-200 px-3 py-2">
                    <p class="text-slate-400 text-[10px] font-semibold uppercase tracking-[0.06em]">Remaining</p>
                    <p id="releaseRemaining" class="text-slate-700 font-bold"></p>
                </div>
            </div>

            <div>
                <label class="form-label" for="releaseKg">Kilograms sold <span class="text-danger-500">*</span></label>
                <div class="relative">
                    <input type="number" name="release_kg" id="releaseKg" class="form-input"
                           step="0.01" min="0.01" placeholder="0.0" required>
                    <span class="absolute text-[12px] top-1/2 -translate-y-1/2 text-slate-400" style="right:12px">kg</span>
                </div>
                <p class="mt-1 text-slate-400 text-[11px]">This is taken off the batch's remaining stock and the public price board.</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" onclick="closeModal('releaseModal')" class="vpm-btn vpm-btn-secondary vpm-btn-sm">Cancel</button>
                <button type="submit" class="vpm-btn vpm-btn-success vpm-btn-sm">
                    <x-icon name="bi-check2" size="xs" /> Release
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Cancel a pending batch: submitted from the ⋮ menu --}}
<form id="cancelForm" method="POST" class="hidden">
    @csrf
    @method('DELETE')
</form>

@endsection

@push('scripts')
<script>
    // ── Submission form ─────────────────────────────────────────────
    const qcSelect    = document.getElementById('qualityClassSelect');
    const fishSelect  = document.getElementById('fishTypeSelect');
    const allFishOpts = Array.from(fishSelect.options).filter(o => o.value !== '');

    // Active price guidelines, keyed by "<fish_type_id>_<quality class>"
    const PRICE_GUIDES  = {!! json_encode($priceGuides) !!};
    // What the vendor already has of each fish, keyed the same way
    const EXISTING      = @json($existingBatches);

    const priceInput    = document.getElementById('pricePerKgInput');
    const guideHint     = document.getElementById('priceGuideHint');
    const guideWarning  = document.getElementById('priceGuideWarning');
    const guideWarnText = document.getElementById('priceGuideWarningText');
    const guideWarnIcon = document.getElementById('priceGuideWarningIcon');
    const peso = (n) => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const kg   = (n) => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 2 }) + ' kg';

    function filterFishTypes() {
        const selectedClass = qcSelect.value;
        const previousValue = fishSelect.value;

        fishSelect.innerHTML = '<option value="">— Select fish type —</option>';
        allFishOpts.forEach(opt => {
            if (!selectedClass || opt.dataset.qualityClass === selectedClass) {
                fishSelect.add(opt.cloneNode(true));
            }
        });

        const hasPrevious = Array.from(fishSelect.options).some(o => o.value === previousValue);
        fishSelect.value = hasPrevious ? previousValue : '';

        checkPriceGuide();
        showExistingBatches();
    }

    // Reminder: "You already have Bammer: 15 kg remaining"
    function showExistingBatches() {
        const box  = document.getElementById('existingBatches');
        const opt  = fishSelect.options[fishSelect.selectedIndex];
        const info = fishSelect.value ? EXISTING[fishSelect.value + '_' + (opt?.dataset.qualityClass || qcSelect.value)] : null;

        box.classList.toggle('hidden', !info);
        if (!info) return;

        document.getElementById('existingFish').textContent = opt.textContent.trim();
        document.getElementById('existingKg').textContent = kg(info.remaining);
        document.getElementById('nextBatchLabel').textContent = 'Batch ' + info.next_batch;

        const list = document.getElementById('existingList');
        list.innerHTML = '';
        info.batches.forEach(b => {
            const li = document.createElement('li');
            li.textContent = b.label + ' — ' + kg(b.kg) + ' (' + b.status + ')';
            list.appendChild(li);
        });
    }

    // Flags the price red + shows the warning when it is above the guideline
    function checkPriceGuide() {
        const guide = fishSelect.value ? PRICE_GUIDES[fishSelect.value + '_' + qcSelect.value] : undefined;
        const price = parseFloat(priceInput.value);

        if (!guide) {
            priceInput.classList.remove('is-over-guide');
            guideWarning.classList.add('hidden');
            guideHint.textContent = fishSelect.value
                ? 'No price guideline is set for this fish type and quality class.'
                : 'Select a fish type to see its price guideline.';
            return;
        }

        guideHint.textContent = 'Guideline: Cheap ≤ ' + peso(guide.cheap) + ' · Moderate ≤ ' + peso(guide.moderate) + ' per kg.';

        const isOver = Number.isFinite(price) && price > guide.moderate;
        priceInput.classList.toggle('is-over-guide', isOver);
        guideWarning.classList.toggle('hidden', !isOver);

        if (isOver) {
            guideWarnText.textContent = ' The guideline for this fish and quality class is up to '
                + peso(guide.moderate) + ' per kg. You may still submit, but staff will see this as an expensive price.';
            guideWarnIcon.setAttribute('title', 'Price exceeds the price guideline (max ' + peso(guide.moderate) + ' per kg).');
        }
    }

    qcSelect.addEventListener('change', filterFishTypes);
    fishSelect.addEventListener('change', checkPriceGuide);
    fishSelect.addEventListener('change', showExistingBatches);
    filterFishTypes(); // run once so old() input is preserved after validation errors

    // ── Modals ──────────────────────────────────────────────────────
    function openModal(id) {
        const el = document.getElementById(id);
        el.classList.remove('hidden');
        el.classList.add('flex');
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        el.classList.add('hidden');
        el.classList.remove('flex');
    }

    // ── ⋮ Batch menu ────────────────────────────────────────────────
    const menu = document.getElementById('batchMenu');
    let menuBtn = null;

    function closeMenu() {
        menu.classList.add('hidden');
        menuBtn?.setAttribute('aria-expanded', 'false');
        menuBtn = null;
    }

    function openBatchMenu(btn) {
        if (menuBtn === btn) { closeMenu(); return; }
        closeMenu();
        menuBtn = btn;
        const d = btn.dataset;

        document.getElementById('menuSold').textContent = kg(d.sold);
        document.getElementById('menuRemaining').textContent = kg(d.remaining);

        const show = (key, on) => menu.querySelectorAll('[data-menu="' + key + '"]').forEach(el => el.classList.toggle('hidden', !on));
        show('release', d.canRelease === '1');
        show('release-sep', d.canRelease === '1');
        show('cancel', !!d.cancelUrl);
        show('extra-sep', !!d.cancelUrl);

        menu.classList.remove('hidden');
        const r = btn.getBoundingClientRect();
        const top = (r.bottom + 6 + menu.offsetHeight > window.innerHeight) ? r.top - menu.offsetHeight - 6 : r.bottom + 6;
        menu.style.top  = Math.max(8, top) + 'px';
        menu.style.left = Math.max(8, r.right - menu.offsetWidth) + 'px';
        btn.setAttribute('aria-expanded', 'true');
    }

    function openRelease(d) {
        document.getElementById('releaseForm').action = d.releaseUrl;
        document.getElementById('releaseEntry').value = d.id;
        document.getElementById('releaseSubtitle').textContent = d.label;
        document.getElementById('releaseSold').textContent = kg(d.sold);
        document.getElementById('releaseRemaining').textContent = kg(d.remaining);
        const input = document.getElementById('releaseKg');
        input.max = d.remaining;
        openModal('releaseModal');
        setTimeout(() => input.focus(), 50);
    }

    menu.addEventListener('click', e => {
        const item = e.target.closest('[data-menu]');
        if (!item || !menuBtn) return;
        const d = { ...menuBtn.dataset };
        closeMenu();

        if (item.dataset.menu === 'release' && d.canRelease === '1') openRelease(d);
        if (item.dataset.menu === 'cancel' && d.cancelUrl && confirm('Cancel ' + d.label + '? It has not been confirmed yet.')) {
            const form = document.getElementById('cancelForm');
            form.action = d.cancelUrl;
            form.submit();
        }
    });

    document.addEventListener('click', e => {
        if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !e.target.closest('.kebab-btn')) closeMenu();
    });
    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        closeMenu();
        closeModal('releaseModal');
    });

    // Reopen the dialog the server just refused, on the same batch.
    @if($releaseFailed)
    (function () {
        const id  = @json((int) old('release_entry'));
        const btn = document.querySelector('.kebab-btn[data-id="' + id + '"]');
        if (!btn) return;
        openRelease(btn.dataset);
        document.getElementById('releaseKg').value = @json(old('release_kg'));
    })();
    @endif
</script>
@endpush
