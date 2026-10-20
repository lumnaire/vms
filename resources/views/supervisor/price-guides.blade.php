@extends('layouts.app')

@section('title', 'Tariff (Price Guide)')
@section('subtitle', 'Official tariff per kilo · enter it from the municipal tariff list')

@section('content')

<x-alert />

@if($errors->any())
    <x-alert variant="error" :message="$errors->first('effective_date') ?: 'Some tariffs could not be saved. Check the highlighted rows.'" />
@endif

{{-- ── Stats ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <x-stat-card label="Tariffs Set" :value="$stats['set']" decimals="0" icon="bi-tags-fill"
                 foot="of {{ $stats['set'] + $stats['missing'] }} fish types" />
    <x-stat-card label="Not Yet Set" :value="$stats['missing']" decimals="0" icon="bi-exclamation-circle-fill"
                 tone="danger" foot="Fish types without a tariff" />
    <x-stat-card label="Changed This Month" :value="$stats['changed_month']" decimals="0" icon="bi-clock-history"
                 tone="neutral" foot="Older versions kept as history" />
    <x-stat-card label="Above Tariff Now" :value="$stats['above_now']" decimals="0" icon="bi-graph-up-arrow"
                 tone="warning" foot="Stalls pricing above it on the board" />
</div>

{{-- ── How the tariff works ─────────────────────────────────────── --}}
<div class="vpm-card px-5 py-4 mb-5 flex items-start gap-3">
    <x-icon name="bi-info-circle-fill" size="lg" class="text-brand-600 mt-0.5" />
    <div class="vpm-body leading-relaxed">
        <p class="font-semibold text-slate-700">This table is the official tariff (taripa).</p>
        <p class="text-slate-500 text-[12px]">
            Type the price per kilo and English name from the tariff list, then save. Changing a tariff keeps the old one in its history.
            A fish missing from a class can be added with <strong>Add to …</strong>; use the pencil to rename or move one, and the bin to remove it.
            Vendors may sell above the tariff. The difference is their markup (tubo), which staff, the supervisor and consumers can all see.
        </p>
    </div>
</div>

{{-- ── Filters ──────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('supervisor.price-guides.index') }}" id="pgFilterForm" class="vpm-card vpm-filter-bar">
    <x-filter-field label="Category" width="170px">
        <select name="quality_class" id="pg-category" class="vpm-filter-select"
                onchange="document.getElementById('pg-fish-type').value=''; this.form.submit()">
            <option value="">All</option>
            @foreach($qualityClasses as $qc)
                <option value="{{ $qc }}" {{ $selectedCategory === $qc ? 'selected' : '' }}>{{ $qc }}</option>
            @endforeach
        </select>
    </x-filter-field>
    <x-filter-field label="Fish Type" width="170px">
        <select name="fish_type_id" id="pg-fish-type" class="vpm-filter-select" onchange="this.form.submit()">
            <option value="">All</option>
            @foreach($categoryFishTypes as $ft)
                <option value="{{ $ft->id }}" {{ (int) $selectedFishType === (int) $ft->id ? 'selected' : '' }}>{{ $ft->name }}</option>
            @endforeach
        </select>
    </x-filter-field>
    <x-filter-field label="Search" width="200px">
        <input type="search" id="tariffSearch" class="vpm-input" placeholder="Local or English name">
    </x-filter-field>
    @if($selectedCategory || $selectedFishType)
        <a href="{{ route('supervisor.price-guides.index') }}" class="vpm-btn vpm-btn-secondary">
            <x-icon name="bi-arrow-clockwise" size="sm" /> Clear
        </a>
    @endif
    <button type="button" class="vpm-btn vpm-btn-primary ml-auto"
            onclick="openFishModal('add', { quality_class: @js($selectedCategory ?? '') })">
        <x-icon name="bi-plus-lg" size="sm" /> Add fish
    </button>
</form>

@if($groups->isEmpty())
    <x-empty-state icon="bi-funnel" title="No fish types match your filters" text="Try a different category or fish type." />
@else
    @include('partials.tariff-table', ['editable' => true])

    <form method="POST" action="{{ route('supervisor.price-guides.store', request()->only('quality_class', 'fish_type_id')) }}" id="tariffForm">
        @csrf

        {{-- Save bar: stays in view while the list is long --}}
        <div class="vpm-card sticky bottom-0 mt-4 px-4 py-3 flex flex-wrap items-end gap-3 justify-between shadow-card-hover">
            <div class="flex flex-wrap items-end gap-3">
                <x-filter-field label="Effective date">
                    <input type="date" name="effective_date" max="{{ today()->toDateString() }}"
                           value="{{ old('effective_date', today()->toDateString()) }}"
                           class="vpm-input {{ $errors->has('effective_date') ? 'is-invalid' : '' }}" required>
                </x-filter-field>
                <p class="vpm-meta pb-2" id="tariffChangeCount">No changes yet</p>
            </div>
            <button type="submit" class="vpm-btn vpm-btn-primary" id="tariffSave" disabled>
                <x-icon name="bi-check-lg" size="sm" /> Save tariff
            </button>
        </div>
    </form>
@endif

{{-- ── Removed fish: hidden from the list, history kept ─────────── --}}
@if($removedFishTypes->isNotEmpty())
    <x-card class="mt-6" :padded="false" title="Removed Fish"
            subtitle="Taken off the list. Their past batches stay in reports and forecasts. Restore to bring one back.">
        <ul class="divide-y divide-slate-100">
            @foreach($removedFishTypes as $removed)
                <li class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-slate-700 font-semibold text-[12.5px]">
                            {{ $removed->name }}
                            @if($removed->english_name)<span class="text-slate-400 font-normal">· {{ $removed->english_name }}</span>@endif
                        </p>
                        <p class="vpm-meta">{{ $removed->quality_class }} · {{ $removed->vendor_inventories_count }} {{ Str::plural('batch', $removed->vendor_inventories_count) }} on record</p>
                    </div>
                    <form method="POST" action="{{ route('supervisor.price-guides.fish.restore', $removed) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="vpm-btn vpm-btn-secondary vpm-btn-sm">
                            <x-icon name="bi-arrow-counterclockwise" size="xs" /> Restore
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    </x-card>
@endif

{{-- ── Add / edit a fish ─────────────────────────────────────────── --}}
@php
    $fishErrors = $errors->getBag('fish');
    $fishModal = session('fish_modal');
@endphp
<div id="fishModal" class="vpm-modal-backdrop hidden" onclick="if (event.target === this) closeFishModal()">
    <div class="vpm-modal" role="dialog" aria-modal="true" aria-labelledby="fishModalTitle">
        <form method="POST" id="fishForm" action="{{ route('supervisor.price-guides.fish.store') }}">
            @csrf
            <input type="hidden" name="_method" id="fishFormMethod" value="POST">
            <div class="vpm-modal-header">
                <div>
                    <p class="vpm-modal-title" id="fishModalTitle">Add fish</p>
                    <p class="vpm-section-sub" id="fishModalSub">Add a fish that is missing from the tariff list.</p>
                </div>
                <button type="button" class="vpm-icon-btn" onclick="closeFishModal()" aria-label="Close">
                    <x-icon name="bi-x-lg" size="base" />
                </button>
            </div>
            <div class="vpm-modal-body space-y-4">
                @if($fishErrors->any())
                    <div class="vpm-alert vpm-alert-error" role="alert">
                        <x-icon name="bi-exclamation-circle-fill" class="vpm-alert-icon" />
                        <div>@foreach($fishErrors->all() as $message)<p>{{ $message }}</p>@endforeach</div>
                    </div>
                @endif
                <label class="block">
                    <span class="vpm-label-text">Local / commercial name <span class="text-danger-500">*</span></span>
                    <input type="text" name="name" id="fishName" maxlength="100" required placeholder="e.g. Hipon (Medium)"
                           value="{{ old('name') }}" class="vpm-input {{ $fishErrors->has('name') ? 'is-invalid' : '' }}">
                </label>
                <label class="block">
                    <span class="vpm-label-text">English name <span class="font-normal text-slate-400">(optional)</span></span>
                    <input type="text" name="english_name" id="fishEnglish" maxlength="100" placeholder="e.g. Shrimp"
                           value="{{ old('english_name') }}" class="vpm-input">
                </label>
                <label class="block">
                    <span class="vpm-label-text">Class <span class="text-danger-500">*</span></span>
                    <select name="quality_class" id="fishClass" required class="vpm-select {{ $fishErrors->has('quality_class') ? 'is-invalid' : '' }}">
                        <option value="">— Choose a class —</option>
                        @foreach($qualityClasses as $qc)
                            <option value="{{ $qc }}" @selected(old('quality_class') === $qc)>{{ $qc }}</option>
                        @endforeach
                    </select>
                    <span class="vpm-meta block mt-1" id="fishClassNote">Moving a fish to another class takes its tariff and batches with it.</span>
                </label>
                <label class="block" id="fishTariffField">
                    <span class="vpm-label-text">Tariff per kilo (₱) <span class="font-normal text-slate-400">(optional, can be set later)</span></span>
                    <input type="number" name="tariff_price" id="fishTariff" step="0.01" min="0.01" max="99999.99" placeholder="0.00"
                           value="{{ old('tariff_price') }}" class="vpm-input vpm-num {{ $fishErrors->has('tariff_price') ? 'is-invalid' : '' }}">
                </label>
            </div>
            <div class="vpm-modal-footer">
                <button type="button" class="vpm-btn vpm-btn-secondary" onclick="closeFishModal()">Cancel</button>
                <button type="submit" class="vpm-btn vpm-btn-primary" id="fishSubmit"><x-icon name="bi-check-lg" size="sm" /> Add fish</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Remove a fish ─────────────────────────────────────────────── --}}
<div id="removeFishModal" class="vpm-modal-backdrop hidden" onclick="if (event.target === this) closeRemoveFish()">
    <div class="vpm-modal" role="dialog" aria-modal="true" aria-labelledby="removeFishTitle">
        <form method="POST" id="removeFishForm">
            @csrf @method('DELETE')
            <div class="vpm-modal-header">
                <p class="vpm-modal-title" id="removeFishTitle">Remove fish</p>
                <button type="button" class="vpm-icon-btn" onclick="closeRemoveFish()" aria-label="Close">
                    <x-icon name="bi-x-lg" size="base" />
                </button>
            </div>
            <div class="vpm-modal-body">
                <p class="vpm-body" id="removeFishText"></p>
            </div>
            <div class="vpm-modal-footer">
                <button type="button" class="vpm-btn vpm-btn-secondary" onclick="closeRemoveFish()">Cancel</button>
                <button type="submit" class="vpm-btn vpm-btn-danger"><x-icon name="bi-trash" size="sm" /> Remove</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── Add / edit a fish ─────────────────────────────────────────
    const FISH_STORE_URL = @json(route('supervisor.price-guides.fish.store'));

    function openFishModal(mode, fish, keepValues) {
        const isEdit = mode === 'edit';
        const form = document.getElementById('fishForm');
        form.action = isEdit ? fish.url : FISH_STORE_URL;
        document.getElementById('fishFormMethod').value = isEdit ? 'PUT' : 'POST';
        document.getElementById('fishModalTitle').textContent = isEdit ? 'Edit ' + fish.name : 'Add fish';
        document.getElementById('fishModalSub').textContent = isEdit
            ? 'Rename it, fix its English name, or move it to another class.'
            : 'Add a fish that is missing from the tariff list.';
        document.getElementById('fishSubmit').lastChild.textContent = isEdit ? ' Save changes' : ' Add fish';
        document.getElementById('fishTariffField').classList.toggle('hidden', isEdit);
        document.getElementById('fishClassNote').classList.toggle('hidden', !isEdit);
        if (!keepValues) {
            document.getElementById('fishName').value = fish.name || '';
            document.getElementById('fishEnglish').value = fish.english_name || '';
            document.getElementById('fishClass').value = fish.quality_class || '';
            document.getElementById('fishTariff').value = '';
        }
        document.getElementById('fishModal').classList.remove('hidden');
        setTimeout(() => document.getElementById('fishName').focus(), 30);
    }

    function closeFishModal() {
        document.getElementById('fishModal').classList.add('hidden');
    }

    // ── Remove a fish ─────────────────────────────────────────────
    function openRemoveFish(fish) {
        document.getElementById('removeFishForm').action = fish.url;
        document.getElementById('removeFishTitle').textContent = 'Remove ' + fish.name + '?';
        document.getElementById('removeFishText').textContent = fish.has_history
            ? fish.name + ' has batches on record, so it will be hidden from the list, vendors and the board, but its sales history stays for reports and forecasts. You can restore it from Removed Fish.'
            : fish.name + ' has never been sold, so it will be deleted together with its tariff.';
        document.getElementById('removeFishModal').classList.remove('hidden');
    }

    function closeRemoveFish() {
        document.getElementById('removeFishModal').classList.add('hidden');
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { closeFishModal(); closeRemoveFish(); }
    });

    // Reopen the dialog the server refused, with what was typed.
    @if($fishModal)
        @php
            $failedFish = ($fishModal['mode'] ?? '') === 'edit' ? App\Models\FishType::find($fishModal['id'] ?? 0) : null;
        @endphp
        document.addEventListener('DOMContentLoaded', () => openFishModal(
            @js($failedFish ? 'edit' : 'add'),
            @js($failedFish ? ['name' => $failedFish->name, 'url' => route('supervisor.price-guides.fish.update', $failedFish)] : []),
            true
        ));
    @endif

    // ── Highlight what changed and count it on the save bar ────────
    (function () {
        const form = document.getElementById('tariffForm');
        if (!form) return;
        const inputs = Array.from(document.querySelectorAll('.tariff-input[form="tariffForm"]'));
        const save = document.getElementById('tariffSave');
        const count = document.getElementById('tariffChangeCount');

        function normalise(input) {
            const v = input.value.trim();
            return input.type === 'number' && v !== '' ? Number(v).toFixed(2) : v;
        }

        function refresh() {
            let changed = 0;
            inputs.forEach(input => {
                const isChanged = normalise(input) !== (input.dataset.original || '');
                input.classList.toggle('is-changed', isChanged);
                if (isChanged) changed++;
            });
            save.disabled = changed === 0;
            count.textContent = changed === 0 ? 'No changes yet' : changed + (changed === 1 ? ' change' : ' changes') + ' to save';
        }

        inputs.forEach(input => input.addEventListener('input', refresh));
        refresh();

        // Leaving with unsaved tariffs asks first.
        let submitting = false;
        form.addEventListener('submit', () => { submitting = true; });
        window.addEventListener('beforeunload', e => {
            if (!submitting && !save.disabled) { e.preventDefault(); e.returnValue = ''; }
        });
    })();
</script>
@endpush
