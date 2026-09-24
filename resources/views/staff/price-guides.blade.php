@extends('layouts.app')

@section('title', 'Price Guide')
@section('subtitle', 'Standard Price Brackets · Quality Classification Reference')

@push('styles')
<style>
    .class-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 9px;
        border-radius: 99px;
        font-size: 10.5px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    .class-first   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .class-second  { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }
    .class-third   { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .class-fourth  { background: #fdf2f8; color: #9d174d; border: 1px solid #fbcfe8; }
    .class-special { background: #fefce8; color: #a16207; border: 1px solid #fef08a; }

    .tier-pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: 6px;
        font-size: 11.5px;
        font-weight: 600;
    }
    .tier-cheap    { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .tier-moderate { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .tier-expensive{ background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    .fish-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        overflow: hidden;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
        transition: box-shadow 0.15s ease;
    }
    .fish-card:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.08);
    }
    .fish-card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #f1f5f9;
        background: #fafafa;
    }
    .fish-icon-wrap {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        background: linear-gradient(135deg, #0f2d5e, #1d4ed8);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .price-grid-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .price-grid-row:last-child { border-bottom: none; }
    .price-grid-cell {
        padding: 10px 14px;
        border-right: 1px solid #f1f5f9;
    }
    .price-grid-cell:last-child { border-right: none; }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 60px 20px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
    }

    .search-input {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 9px;
        padding: 8px 12px 8px 36px;
        font-size: 13px;
        color: #334155;
        width: 100%;
        max-width: 280px;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .search-input:focus {
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(147,197,253,0.25);
    }
    .search-input::placeholder { color: #94a3b8; }

    .filter-sel {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 7px 12px;
        font-size: 13px;
        font-weight: 500;
        color: #334155;
        transition: border-color 0.15s, box-shadow 0.15s;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 16 16'%3E%3Cpath fill='%2394a3b8' d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        padding-right: 30px;
        cursor: pointer;
    }
    .filter-sel:focus {
        outline: none;
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }

    @media (max-width: 640px) {
        .price-grid-row { grid-template-columns: 1fr; }
        .price-grid-cell { border-right: none; border-bottom: 1px solid #f1f5f9; }
        .price-grid-cell:last-child { border-bottom: none; }
    }
</style>
@endpush

@section('content')

{{-- ── Summary Stats ────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">

    {{-- Total Guide Entries --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Price Guide Entries</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $totalGuides }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">Active classification rules</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: #eff6ff;">
                <i class="bi bi-tags-fill text-blue-600" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Fish Species --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Fish Species</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">
                    {{ $totalSpecies }}
                </p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">With active price brackets</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: #ecfdf5;">
                <i class="bi bi-water text-emerald-500" style="font-size: 17px;"></i>
            </div>
        </div>
    </div>

    {{-- Quality Classes --}}
    <div class="stat-card bg-white rounded-xl p-5 border border-slate-100" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-slate-400 font-semibold" style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">Quality Classes</p>
                <p class="text-slate-800 font-bold mt-1" style="font-size: 28px; line-height: 1;">5</p>
                <p class="text-slate-400 mt-1" style="font-size: 11px;">First · Second · Third · Fourth · Special</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0" style="background: #fefce8;">
                <i class="bi bi-award-fill" style="font-size: 17px; color: #ca8a04;"></i>
            </div>
        </div>
    </div>

</div>

{{-- ── Filter + Search ─────────────────────────────────── --}}
<div class="bg-white rounded-xl border border-slate-100 px-4 py-3.5 mb-5 flex flex-wrap items-end gap-3"
     style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">

    <form method="GET" action="{{ route('staff.price-guides.index') }}" id="pgFilterForm"
          class="flex flex-wrap items-end gap-3">

        {{-- Category (quality class) --}}
        <div class="flex flex-col gap-1.5 flex-shrink-0" style="min-width:170px;">
            <label class="text-slate-400 font-semibold select-none"
                   style="font-size:10px; text-transform:uppercase; letter-spacing:.08em;">
                <i class="bi bi-grid-3x3-gap-fill" style="margin-right:4px;"></i>Category
            </label>
            <select name="quality_class" id="pg-category" onchange="onPgCategoryChange()" class="filter-sel">
                <option value="">All</option>
                @foreach($qualityClasses as $qc)
                    <option value="{{ $qc }}" {{ $selectedCategory === $qc ? 'selected' : '' }}>{{ $qc }}</option>
                @endforeach
            </select>
        </div>

        {{-- Fish Type (narrowed by the selected category) --}}
        <div class="flex flex-col gap-1.5 flex-shrink-0" style="min-width:170px;">
            <label class="text-slate-400 font-semibold select-none"
                   style="font-size:10px; text-transform:uppercase; letter-spacing:.08em;">
                <i class="bi bi-water" style="margin-right:4px;"></i>Fish Type
            </label>
            <select name="fish_type_id" id="pg-fish-type" onchange="onPgFishTypeChange()" class="filter-sel">
                <option value="">All</option>
                @foreach($categoryFishTypes as $ft)
                    <option value="{{ $ft->id }}" {{ (int) $selectedFishType === (int) $ft->id ? 'selected' : '' }}>{{ $ft->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Filter submit + clear --}}
        <div class="flex items-end gap-2 flex-shrink-0">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold"
                    style="background:#1d4ed8; color:#fff; font-size:13px; cursor:pointer; border:none;">
                <i class="bi bi-funnel"></i> Filter
            </button>
            @if($selectedCategory || $selectedFishType)
                <a href="{{ route('staff.price-guides.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-lg font-semibold"
                   style="border:1px solid #e2e8f0; color:#475569; background:#fff; font-size:13px; text-decoration:none;">
                    <i class="bi bi-arrow-clockwise"></i> Clear
                </a>
            @endif
        </div>

    </form>

    {{-- Search --}}
    <div class="relative flex-shrink-0 ms-auto">
        <i class="bi bi-search" style="position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 13px;"></i>
        <input
            id="search-input"
            type="text"
            class="search-input"
            placeholder="Search fish type…"
            oninput="filterCards(this.value)"
        >
    </div>

</div>

{{-- ── Fish Type Cards Grid ─────────────────────────────────── --}}
@if($fishTypes->isEmpty())

    @if($selectedCategory || $selectedFishType)
    <div class="empty-state">
        <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center mb-4" style="border: 1px solid #e2e8f0;">
            <i class="bi bi-funnel text-slate-300" style="font-size: 24px;"></i>
        </div>
        <p class="text-slate-500 font-semibold" style="font-size: 14px;">No fish types match your filters</p>
        <p class="text-slate-400 text-center mt-1" style="font-size: 12px; max-width: 300px;">
            Try a different category or fish type.
        </p>
        <a href="{{ route('staff.price-guides.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-blue-600 hover:bg-blue-50 transition-colors mt-4"
           style="font-size:12px; font-weight:600; border:1px dashed #93c5fd; text-decoration:none;">
            <i class="bi bi-arrow-clockwise" style="font-size:11px;"></i> Clear filters
        </a>
    </div>
    @else
    <div class="empty-state">
        <div class="w-14 h-14 rounded-full bg-slate-50 flex items-center justify-center mb-4" style="border: 1px solid #e2e8f0;">
            <i class="bi bi-tags text-slate-300" style="font-size: 24px;"></i>
        </div>
        <p class="text-slate-500 font-semibold" style="font-size: 14px;">No price guides found</p>
        <p class="text-slate-400 text-center mt-1" style="font-size: 12px; max-width: 300px;">
            Price guide entries will appear here once fish types and their quality brackets are configured.
        </p>
    </div>
    @endif

@else

    <div id="cards-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

        @foreach($fishTypes as $fishType)

        @php
            $classOrder = [
                'Special Class' => 'special',
                'First Class'   => 'first',
                'Second Class'  => 'second',
                'Third Class'   => 'third',
                'Fourth Class'  => 'fourth',
            ];
        @endphp

        <div class="fish-card" data-fish="{{ strtolower($fishType->name) }}">

            {{-- Card Header --}}
            <div class="fish-card-header">
                <div class="fish-icon-wrap">
                    <i class="bi bi-water text-white" style="font-size: 15px;"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-slate-800 font-bold truncate" style="font-size: 13.5px;">
                        {{ $fishType->name }}
                    </p>
                    <p class="text-slate-400" style="font-size: 11px; margin-top: 1px;">
                        {{ $fishType->priceGuides->count() }}
                        {{ Str::plural('class', $fishType->priceGuides->count()) }} configured
                    </p>
                </div>
                <span class="text-slate-400 flex-shrink-0" style="font-size: 11px; font-weight: 600;">
                    ₱/kg
                </span>
            </div>

            {{-- Column Headers --}}
            @if($fishType->priceGuides->isNotEmpty())
            <div class="price-grid-row" style="background: #f8fafc;">
                <div class="price-grid-cell">
                    <p style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8;">
                        Quality Class
                    </p>
                </div>
                <div class="price-grid-cell">
                    <p style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8;">
                        Cheap up to
                    </p>
                </div>
                <div class="price-grid-cell">
                    <p style="font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8;">
                        Moderate up to
                    </p>
                </div>
            </div>

            {{-- Price Rows --}}
            @foreach($fishType->priceGuides->sortBy(fn($g) => array_search($g->quality_class, array_keys($classOrder))) as $guide)
            @php
                $classKey = $classOrder[$guide->quality_class] ?? 'first';
            @endphp
            <div class="price-grid-row">

                {{-- Quality Class --}}
                <div class="price-grid-cell flex items-center">
                    <span class="class-badge class-{{ $classKey }}">
                        {{ $guide->quality_class }}
                    </span>
                </div>

                {{-- Cheap max --}}
                <div class="price-grid-cell">
                    <div class="flex items-center gap-1.5">
                        <span class="tier-pill tier-cheap" style="font-size: 10.5px; padding: 2px 8px;">
                            ₱ {{ number_format($guide->cheap_max, 2) }}
                        </span>
                    </div>
                    <p style="font-size: 10px; color: #94a3b8; margin-top: 3px;">& below</p>
                </div>

                {{-- Moderate max --}}
                <div class="price-grid-cell">
                    <div class="flex items-center gap-1.5">
                        <span class="tier-pill tier-moderate" style="font-size: 10.5px; padding: 2px 8px;">
                            ₱ {{ number_format($guide->moderate_max, 2) }}
                        </span>
                    </div>
                    <p style="font-size: 10px; color: #94a3b8; margin-top: 3px;">
                        above = <span class="tier-pill tier-expensive" style="font-size: 9.5px; padding: 1px 6px;">Expensive</span>
                    </p>
                </div>

            </div>
            @endforeach

            {{-- Effective Date Footer --}}
            <div style="padding: 9px 14px; background: #fafafa; border-top: 1px solid #f1f5f9;">
                <p style="font-size: 10.5px; color: #94a3b8;">
                    <i class="bi bi-calendar3" style="margin-right: 4px;"></i>
                    Effective:
                    <span style="color: #64748b; font-weight: 600;">
                        {{ $fishType->priceGuides->first()?->effective_date?->format('F j, Y') ?? '—' }}
                    </span>
                </p>
            </div>

            @else

            {{-- No guides for this fish type --}}
            <div style="padding: 24px; text-align: center;">
                <i class="bi bi-dash-circle text-slate-300" style="font-size: 20px; display: block; margin-bottom: 6px;"></i>
                <p style="font-size: 12px; color: #94a3b8;">No price brackets configured</p>
            </div>

            @endif

        </div>
        @endforeach

    </div>

    {{-- No-results message (shown by JS) --}}
    <div id="no-results" class="hidden empty-state mt-4">
        <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center mb-3" style="border: 1px solid #e2e8f0;">
            <i class="bi bi-search text-slate-300" style="font-size: 20px;"></i>
        </div>
        <p class="text-slate-500 font-semibold" style="font-size: 13px;">No fish type matched</p>
        <p class="text-slate-400 mt-1" style="font-size: 12px;">Try a different search term.</p>
    </div>

@endif

@endsection

@push('scripts')
<script>
    // ── Filter bar: category → fish type ─────────────────────────
    const pgFilterForm     = document.getElementById('pgFilterForm');
    const pgFishTypeSelect = document.getElementById('pg-fish-type');

    function onPgCategoryChange() {
        // Drop any stale fish type before submitting on category change
        pgFishTypeSelect.value = '';
        pgFilterForm.submit();
    }
    function onPgFishTypeChange() {
        pgFilterForm.submit();
    }

    function filterCards(query) {
        const term     = query.trim().toLowerCase();
        const cards    = document.querySelectorAll('#cards-grid .fish-card');
        const noResult = document.getElementById('no-results');
        let   visible  = 0;

        cards.forEach(card => {
            const name = card.dataset.fish || '';
            if (!term || name.includes(term)) {
                card.style.display = '';
                visible++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noResult) {
            noResult.classList.toggle('hidden', visible > 0 || !term);
        }
    }
</script>
@endpush