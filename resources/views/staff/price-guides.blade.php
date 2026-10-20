@extends('layouts.app')

@section('title', 'Tariff (Price Guide)')
@section('subtitle', 'Official tariff vs vendor prices on the board')

@section('content')

{{-- ── Stats ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
    <x-stat-card label="Tariffs Set" :value="$stats['set']" decimals="0" icon="bi-tags-fill"
                 foot="of {{ $stats['set'] + $stats['missing'] }} fish types" />
    <x-stat-card label="Not Yet Set" :value="$stats['missing']" decimals="0" icon="bi-exclamation-circle-fill"
                 tone="danger" foot="The supervisor sets these" />
    <x-stat-card label="Fish on the Board" :value="$board->count()" decimals="0" icon="bi-shop"
                 tone="success" foot="Kinds of fish on sale now" />
    <x-stat-card label="Above Tariff Now" :value="$stats['above_now']" decimals="0" icon="bi-graph-up-arrow"
                 tone="warning" foot="Stalls pricing above it" />
</div>

<div class="vpm-card px-5 py-4 mb-5 flex items-start gap-3">
    <x-icon name="bi-info-circle-fill" size="lg" class="text-brand-600 mt-0.5" />
    <p class="vpm-body text-slate-500 text-[12px] leading-relaxed">
        <span class="font-semibold text-slate-700">Standard price vs vendor price.</span>
        The tariff is the official price per kilo set by the supervisor. "On the board now" is what vendors are charging,
        and "Highest markup" is the stall adding the most above the tariff (their tubo), with the vendor's reason when they gave one.
    </p>
</div>

<form method="GET" action="{{ route('staff.price-guides.index') }}" class="vpm-card vpm-filter-bar">
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
        <a href="{{ route('staff.price-guides.index') }}" class="vpm-btn vpm-btn-secondary">
            <x-icon name="bi-arrow-clockwise" size="sm" /> Clear
        </a>
    @endif
</form>

@if($groups->isEmpty())
    <x-empty-state icon="bi-funnel" title="No fish types match your filters" text="Try a different category or fish type." />
@else
    @include('partials.tariff-table', ['editable' => false])
@endif

@endsection
