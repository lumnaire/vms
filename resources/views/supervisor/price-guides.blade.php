@extends('layouts.app')

@section('title', 'Price Guide Management')
@section('subtitle', 'Configure price brackets per fish type & quality class')

@push('styles')
<style>
    .class-badge {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 2px 9px; border-radius: 99px;
        font-size: 10.5px; font-weight: 700; letter-spacing: 0.04em;
        text-transform: uppercase; flex-shrink: 0;
    }
    .class-first   { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
    .class-second  { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }
    .class-third   { background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; }
    .class-fourth  { background:#fdf2f8; color:#9d174d; border:1px solid #fbcfe8; }
    .class-special { background:#fefce8; color:#a16207; border:1px solid #fef08a; }

    .tier-pill {
        display:inline-flex; align-items:center; gap:4px;
        padding:3px 10px; border-radius:6px; font-size:11.5px; font-weight:600;
    }
    .tier-cheap    { background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; }
    .tier-moderate { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
    .tier-expensive{ background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }

    .fish-card {
        background:#fff; border:1px solid #e2e8f0; border-radius:14px;
        overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.05);
        transition:box-shadow 0.15s ease;
    }
    .fish-card:hover { box-shadow:0 4px 16px rgba(0,0,0,0.08); }
    .fish-card-header {
        display:flex; align-items:center; gap:12px;
        padding:14px 16px; border-bottom:1px solid #f1f5f9; background:#fafafa;
    }
    .fish-icon-wrap {
        width:36px; height:36px; border-radius:10px;
        background:linear-gradient(135deg,#0f2d5e,#1d4ed8);
        display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .price-grid-row {
        display:grid; grid-template-columns:1.4fr 1fr 1fr 90px;
        gap:0; border-bottom:1px solid #f1f5f9;
    }
    .price-grid-row:last-child { border-bottom:none; }
    .price-grid-cell { padding:10px 14px; border-right:1px solid #f1f5f9; }
    .price-grid-cell:last-child { border-right:none; }

    /* Modal overlay */
    .modal-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,0.45);
        z-index:1000; display:flex; align-items:center; justify-content:center;
        padding:16px;
    }
    .modal-box {
        background:#fff; border-radius:16px; width:100%; max-width:480px;
        box-shadow:0 20px 60px rgba(0,0,0,0.2); overflow:hidden;
    }
    .modal-header {
        padding:18px 20px 14px; border-bottom:1px solid #f1f5f9;
        display:flex; align-items:center; justify-content:space-between;
    }
    .modal-body   { padding:20px; }
    .modal-footer {
        padding:14px 20px; border-top:1px solid #f1f5f9;
        display:flex; justify-content:flex-end; gap:10px; background:#fafafa;
    }
    .form-label-pg { font-size:12px; font-weight:600; color:#475569; margin-bottom:5px; display:block; }
    .form-input-pg {
        width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:9px;
        font-size:13px; color:#334155; outline:none;
        transition:border-color 0.15s, box-shadow 0.15s;
    }
    .form-input-pg:focus { border-color:#93c5fd; box-shadow:0 0 0 3px rgba(147,197,253,0.25); }
    .form-input-pg.is-invalid { border-color:#f87171; }
    .btn-pg-primary {
        padding:9px 18px; border-radius:9px; font-size:13px; font-weight:600;
        background:#1d4ed8; color:#fff; border:none; cursor:pointer;
        transition:background 0.15s;
    }
    .btn-pg-primary:hover { background:#1e40af; }
    .btn-pg-cancel {
        padding:9px 18px; border-radius:9px; font-size:13px; font-weight:600;
        background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; cursor:pointer;
        transition:background 0.15s;
    }
    .btn-pg-cancel:hover { background:#e2e8f0; }
</style>
@endpush

@section('content')

{{-- ── Flash Messages ──────────────────────────────────────────── --}}
@if(session('success'))
    <div class="flex items-center gap-3 px-4 py-3 mb-5 rounded-xl border"
         style="background:#f0fdf4; border-color:#bbf7d0;">
        <i class="bi bi-check-circle-fill text-emerald-500" style="font-size:15px;"></i>
        <p class="text-emerald-700 font-semibold" style="font-size:13px;">{{ session('success') }}</p>
    </div>
@endif

{{-- ── Stats Row ────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.07em;">Total Brackets</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size:28px; line-height:1;">{{ $totalGuides }}</p>
                <p class="text-slate-400 mt-1" style="font-size:11px;">Active classification rules</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#eff6ff;">
                <i class="bi bi-tags-fill text-blue-600" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.07em;">Configured</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size:28px; line-height:1;">{{ $totalConfigured }}</p>
                <p class="text-slate-400 mt-1" style="font-size:11px;">Fish types with brackets</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#f0fdf4;">
                <i class="bi bi-check-circle-fill text-emerald-500" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size:10.5px; text-transform:uppercase; letter-spacing:0.07em;">Not Yet Set</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size:28px; line-height:1;">{{ $totalMissing }}</p>
                <p class="text-slate-400 mt-1" style="font-size:11px;">Fish types needing setup</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:#fff1f2;">
                <i class="bi bi-exclamation-circle-fill text-rose-400" style="font-size:17px;"></i>
            </div>
        </div>
    </div>

</div>

{{-- ── Legend + Add Button ──────────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 p-5 mb-5 flex flex-col md:flex-row md:items-center justify-between gap-4"
     style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div>
        <p class="text-slate-700 font-semibold mb-2" style="font-size:12.5px;">How price brackets work:</p>
        <div class="flex flex-wrap gap-2 items-center">
            <span class="tier-pill tier-cheap"><i class="bi bi-circle-fill" style="font-size:7px;"></i> Cheap</span>
            <span class="text-slate-400" style="font-size:11.5px;">≤ cheap_max</span>
            <span class="text-slate-200 mx-1">|</span>
            <span class="tier-pill tier-moderate"><i class="bi bi-circle-fill" style="font-size:7px;"></i> Moderate</span>
            <span class="text-slate-400" style="font-size:11.5px;">≤ moderate_max</span>
            <span class="text-slate-200 mx-1">|</span>
            <span class="tier-pill tier-expensive"><i class="bi bi-circle-fill" style="font-size:7px;"></i> Expensive</span>
            <span class="text-slate-400" style="font-size:11.5px;">above moderate_max</span>
        </div>
    </div>
    <button onclick="openAddModal(null)"
            class="btn-pg-primary flex items-center gap-2 flex-shrink-0">
        <i class="bi bi-plus-lg"></i> Add Price Bracket
    </button>
</div>

{{-- ── Fish Type Cards ─────────────────────────────────────────── --}}
@php
    $classMap = [
        'Special Class' => 'special',
        'First Class'   => 'first',
        'Second Class'  => 'second',
        'Third Class'   => 'third',
        'Fourth Class'  => 'fourth',
    ];
    $classOrder = array_keys($classMap);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

    @foreach($fishTypes as $fishType)
    <div class="fish-card">

        {{-- Card Header --}}
        <div class="fish-card-header">
            <div class="fish-icon-wrap">
                <i class="bi bi-water text-white" style="font-size:15px;"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-slate-800 font-bold truncate" style="font-size:13.5px;">{{ $fishType->name }}</p>
                <p class="text-slate-400" style="font-size:11px; margin-top:1px;">
                    {{ $fishType->priceGuides->count() }} {{ Str::plural('class', $fishType->priceGuides->count()) }} configured
                </p>
            </div>
            <button onclick="openAddModal({{ $fishType->id }})"
                    title="Add bracket for {{ $fishType->name }}"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors flex-shrink-0"
                    style="font-size:11.5px; font-weight:600; border:1px solid #bfdbfe;">
                <i class="bi bi-plus-lg" style="font-size:11px;"></i> Add
            </button>
        </div>

        @if($fishType->priceGuides->isNotEmpty())

            {{-- Column Headers --}}
            <div class="price-grid-row" style="background:#f8fafc;">
                <div class="price-grid-cell">
                    <p style="font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8;">Quality Class</p>
                </div>
                <div class="price-grid-cell">
                    <p style="font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8;">Cheap up to</p>
                </div>
                <div class="price-grid-cell">
                    <p style="font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8;">Moderate up to</p>
                </div>
                <div class="price-grid-cell">
                    <p style="font-size:9.5px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:#94a3b8;">Actions</p>
                </div>
            </div>

            {{-- Bracket Rows --}}
            @foreach($fishType->priceGuides->sortBy(fn($g) => array_search($g->quality_class, $classOrder)) as $guide)
            @php $classKey = $classMap[$guide->quality_class] ?? 'first'; @endphp
            <div class="price-grid-row">

                <div class="price-grid-cell flex items-center">
                    <span class="class-badge class-{{ $classKey }}">{{ $guide->quality_class }}</span>
                </div>

                <div class="price-grid-cell">
                    <span class="tier-pill tier-cheap" style="font-size:10.5px; padding:2px 8px;">
                        ₱ {{ number_format($guide->cheap_max, 2) }}
                    </span>
                    <p style="font-size:10px; color:#94a3b8; margin-top:3px;">& below</p>
                </div>

                <div class="price-grid-cell">
                    <span class="tier-pill tier-moderate" style="font-size:10.5px; padding:2px 8px;">
                        ₱ {{ number_format($guide->moderate_max, 2) }}
                    </span>
                    <p style="font-size:10px; color:#94a3b8; margin-top:3px;">
                        above = <span class="tier-pill tier-expensive" style="font-size:9.5px; padding:1px 6px;">Expensive</span>
                    </p>
                </div>

                <div class="price-grid-cell flex items-center gap-1.5">
                    {{-- Edit --}}
                    <button onclick="openEditModal(
                                {{ $guide->id }},
                                {{ $guide->cheap_max }},
                                {{ $guide->moderate_max }},
                                '{{ $guide->effective_date->format('Y-m-d') }}',
                                '{{ route('supervisor.price-guides.update', $guide) }}',
                                '{{ $guide->quality_class }}',
                                '{{ $fishType->name }}'
                            )"
                            title="Edit"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-blue-500 hover:bg-blue-50 transition-colors"
                            style="border:1px solid #bfdbfe;">
                        <i class="bi bi-pencil-fill" style="font-size:11px;"></i>
                    </button>

                    {{-- Delete --}}
                    <button type="button"
                            title="Delete"
                            onclick="openDeleteModal(
                                '{{ route('supervisor.price-guides.destroy', $guide) }}',
                                '{{ addslashes($guide->quality_class) }}',
                                '{{ addslashes($fishType->name) }}'
                            )"
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-rose-400 hover:bg-rose-50 transition-colors"
                            style="border:1px solid #fecaca;">
                        <i class="bi bi-trash-fill" style="font-size:11px;"></i>
                    </button>
                </div>

            </div>
            @endforeach

            {{-- Effective date footer --}}
            <div style="padding:9px 14px; background:#fafafa; border-top:1px solid #f1f5f9;">
                <p style="font-size:10.5px; color:#94a3b8;">
                    <i class="bi bi-calendar3" style="margin-right:4px;"></i>
                    Effective:
                    <span style="color:#64748b; font-weight:600;">
                        {{ $fishType->priceGuides->first()?->effective_date?->format('F j, Y') ?? '—' }}
                    </span>
                </p>
            </div>

        @else

            {{-- Empty state --}}
            <div style="padding:28px 20px; text-align:center;">
                <i class="bi bi-dash-circle text-slate-300" style="font-size:20px; display:block; margin-bottom:8px;"></i>
                <p style="font-size:12px; color:#94a3b8; margin-bottom:10px;">No price brackets configured</p>
                <button onclick="openAddModal({{ $fishType->id }})"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors"
                        style="font-size:12px; font-weight:600; border:1px dashed #93c5fd;">
                    <i class="bi bi-plus-lg" style="font-size:11px;"></i> Configure now
                </button>
            </div>

        @endif

    </div>
    @endforeach

</div>


{{-- ════════════════════════════════ ADD MODAL ════════════════════════════════ --}}
<div id="addModal" class="modal-overlay hidden" onclick="handleOverlayClick(event,'addModal')">
    <div class="modal-box">

        <div class="modal-header">
            <div>
                <p class="text-slate-800 font-bold" style="font-size:15px;">Add Price Bracket</p>
                <p class="text-slate-400" style="font-size:11.5px; margin-top:2px;">Set cheap & moderate thresholds for a quality class</p>
            </div>
            <button onclick="closeModal('addModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="bi bi-x-lg" style="font-size:16px;"></i>
            </button>
        </div>

        <form action="{{ route('supervisor.price-guides.store') }}" method="POST">
            @csrf
            <div class="modal-body" style="display:flex; flex-direction:column; gap:16px;">

                {{-- Validation error --}}
                @if($errors->has('quality_class') && !session('open_edit_modal'))
                    <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg" style="background:#fef2f2; border:1px solid #fecaca;">
                        <i class="bi bi-exclamation-circle-fill text-rose-400 mt-0.5" style="font-size:13px; flex-shrink:0;"></i>
                        <p class="text-rose-600" style="font-size:12px;">{{ $errors->first('quality_class') }}</p>
                    </div>
                @endif

                {{-- Fish Type --}}
                <div>
                    <label class="form-label-pg">Fish Type <span class="text-rose-400">*</span></label>
                    <select name="fish_type_id" id="add-fish-type-select"
                            class="form-input-pg {{ $errors->has('fish_type_id') ? 'is-invalid' : '' }}"
                            required>
                        <option value="">— Select fish type —</option>
                        @foreach($fishTypes as $ft)
                            <option value="{{ $ft->id }}" {{ old('fish_type_id', session('open_add_modal')) == $ft->id ? 'selected' : '' }}>
                                {{ $ft->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Quality Class --}}
                <div>
                    <label class="form-label-pg">Quality Class <span class="text-rose-400">*</span></label>
                    <select name="quality_class" class="form-input-pg {{ $errors->has('quality_class') ? 'is-invalid' : '' }}" required>
                        <option value="">— Select class —</option>
                        @foreach($qualityClasses as $qc)
                            <option value="{{ $qc }}" {{ old('quality_class') === $qc ? 'selected' : '' }}>{{ $qc }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Price Inputs --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label-pg">Cheap Max (₱/kg) <span class="text-rose-400">*</span></label>
                        <input type="number" name="cheap_max" step="0.01" min="0.01"
                               value="{{ old('cheap_max') }}"
                               placeholder="e.g. 150.00"
                               class="form-input-pg {{ $errors->has('cheap_max') ? 'is-invalid' : '' }}"
                               required>
                        <p style="font-size:10.5px; color:#94a3b8; margin-top:4px;">Prices at or below this = Cheap</p>
                    </div>
                    <div>
                        <label class="form-label-pg">Moderate Max (₱/kg) <span class="text-rose-400">*</span></label>
                        <input type="number" name="moderate_max" step="0.01" min="0.01"
                               value="{{ old('moderate_max') }}"
                               placeholder="e.g. 220.00"
                               class="form-input-pg {{ $errors->has('moderate_max') ? 'is-invalid' : '' }}"
                               required>
                        <p style="font-size:10.5px; color:#94a3b8; margin-top:4px;">Above this = Expensive</p>
                    </div>
                </div>

                {{-- Effective Date --}}
                <div>
                    <label class="form-label-pg">Effective Date <span class="text-rose-400">*</span></label>
                    <input type="date" name="effective_date"
                           value="{{ old('effective_date', now()->toDateString()) }}"
                           class="form-input-pg {{ $errors->has('effective_date') ? 'is-invalid' : '' }}"
                           required>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-pg-cancel" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-pg-primary"><i class="bi bi-plus-lg me-1"></i> Add Bracket</button>
            </div>
        </form>

    </div>
</div>


{{-- ════════════════════════════════ EDIT MODAL ════════════════════════════════ --}}
<div id="editModal" class="modal-overlay hidden" onclick="handleOverlayClick(event,'editModal')">
    <div class="modal-box">

        <div class="modal-header">
            <div>
                <p class="text-slate-800 font-bold" style="font-size:15px;">Edit Price Bracket</p>
                <p id="edit-modal-subtitle" class="text-slate-400" style="font-size:11.5px; margin-top:2px;">Adjust the thresholds</p>
            </div>
            <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="bi bi-x-lg" style="font-size:16px;"></i>
            </button>
        </div>

        <form id="edit-form" method="POST">
            @csrf @method('PUT')
            <div class="modal-body" style="display:flex; flex-direction:column; gap:16px;">

                @if($errors->has('cheap_max') || $errors->has('moderate_max'))
                    <div class="flex items-start gap-2 px-3 py-2.5 rounded-lg" style="background:#fef2f2; border:1px solid #fecaca;">
                        <i class="bi bi-exclamation-circle-fill text-rose-400 mt-0.5" style="font-size:13px; flex-shrink:0;"></i>
                        <p class="text-rose-600" style="font-size:12px;">
                            {{ $errors->first('cheap_max') ?: $errors->first('moderate_max') }}
                        </p>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label-pg">Cheap Max (₱/kg) <span class="text-rose-400">*</span></label>
                        <input type="number" id="edit-cheap-max" name="cheap_max" step="0.01" min="0.01"
                               class="form-input-pg" required>
                        <p style="font-size:10.5px; color:#94a3b8; margin-top:4px;">Prices at or below = Cheap</p>
                    </div>
                    <div>
                        <label class="form-label-pg">Moderate Max (₱/kg) <span class="text-rose-400">*</span></label>
                        <input type="number" id="edit-moderate-max" name="moderate_max" step="0.01" min="0.01"
                               class="form-input-pg" required>
                        <p style="font-size:10.5px; color:#94a3b8; margin-top:4px;">Above this = Expensive</p>
                    </div>
                </div>

                <div>
                    <label class="form-label-pg">Effective Date <span class="text-rose-400">*</span></label>
                    <input type="date" id="edit-effective-date" name="effective_date"
                           class="form-input-pg" required>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn-pg-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-pg-primary"><i class="bi bi-check-lg me-1"></i> Save Changes</button>
            </div>
        </form>

    </div>
</div>

{{-- \u2500\u2500 Delete Confirmation Modal \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 --}}
<div id="deleteModal"
     class="modal-overlay hidden"
     onclick="handleOverlayClick(event, 'deleteModal')">
    <div class="modal-box" style="max-width:400px;">
        <form id="delete-form" method="POST">
            @csrf @method('DELETE')

            <div class="modal-header" style="border-bottom:1px solid #fef2f2;">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                         style="background:#fef2f2; border:1px solid #fecaca;">
                        <i class="bi bi-trash-fill text-rose-500" style="font-size:15px;"></i>
                    </div>
                    <div>
                        <p class="text-slate-800 font-bold" style="font-size:14px;">Remove Price Bracket</p>
                        <p class="text-slate-400" style="font-size:11px;">This action cannot be undone</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('deleteModal')"
                        class="w-7 h-7 rounded-lg flex items-center justify-center hover:bg-slate-100 transition-colors"
                        style="border:none;background:transparent;cursor:pointer;color:#94a3b8;">
                    <i class="bi bi-x-lg" style="font-size:13px;"></i>
                </button>
            </div>

            <div class="modal-body">
                <div class="rounded-xl p-4 mb-1" style="background:#fef2f2; border:1px solid #fecaca;">
                    <p class="text-rose-700 font-semibold" style="font-size:13px;">
                        Are you sure you want to remove the
                        <span id="delete-modal-class" class="font-bold"></span> bracket
                        for <span id="delete-modal-fish" class="font-bold"></span>?
                    </p>
                    <p class="text-rose-500 mt-1" style="font-size:11.5px;">
                        Vendors will no longer be able to submit entries under this bracket.
                    </p>
                </div>
            </div>

            <div class="modal-footer" style="background:#fafafa;">
                <button type="button" onclick="closeModal('deleteModal')" class="btn-pg-cancel">
                    Cancel
                </button>
                <button type="submit"
                        style="padding:9px 18px; border-radius:9px; font-size:13px; font-weight:600;
                               background:#dc2626; color:#fff; border:none; cursor:pointer;
                               display:inline-flex; align-items:center; gap:6px;
                               transition:background 0.15s;"
                        onmouseover="this.style.background='#b91c1c'"
                        onmouseout="this.style.background='#dc2626'">
                    <i class="bi bi-trash-fill" style="font-size:12px;"></i> Yes, Remove
                </button>
            </div>

        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── Modal helpers ────────────────────────────────────────────
    function openModal(id) {
        document.getElementById(id).classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.body.style.overflow = '';
    }
    function handleOverlayClick(e, id) {
        if (e.target === document.getElementById(id)) closeModal(id);
    }

    // ── Open Add Modal (optionally pre-select a fish type) ───────
    function openAddModal(fishTypeId) {
        if (fishTypeId) {
            document.getElementById('add-fish-type-select').value = fishTypeId;
        }
        openModal('addModal');
    }

    // ── Open Edit Modal and populate fields ──────────────────────
    function openEditModal(id, cheapMax, moderateMax, effectiveDate, actionUrl, qualityClass, fishName) {
        document.getElementById('edit-cheap-max').value    = cheapMax;
        document.getElementById('edit-moderate-max').value = moderateMax;
        document.getElementById('edit-effective-date').value = effectiveDate;
        document.getElementById('edit-form').action        = actionUrl;
        document.getElementById('edit-modal-subtitle').textContent =
            fishName + ' — ' + qualityClass;
        openModal('editModal');
    }

    // ── Open Delete Modal ────────────────────
    function openDeleteModal(actionUrl, qualityClass, fishName) {
        document.getElementById('delete-form').action = actionUrl;
        document.getElementById('delete-modal-class').textContent = qualityClass;
        document.getElementById('delete-modal-fish').textContent = fishName;
        openModal('deleteModal');
    }

    // ── Auto-open add modal on validation error ──────────────────
    @if($errors->isNotEmpty() && !session('open_edit_modal'))
        document.addEventListener('DOMContentLoaded', function () {
            openAddModal({{ session('open_add_modal', 'null') }});
        });
    @endif
</script>
@endpush