{{-- resources/views/public/priceboard.blade.php --}}
{{--
    Public price board. This page used to carry its own ocean/teal palette
    (--ocean, --teal, --amber) and ~600 lines of page-local CSS, which made it
    look like a different product from the admin system. It now uses the same
    marine-blue tokens and .vpm-* components as every other page; only the
    genuinely page-specific structure (public header, card grid, vendor
    grouping) lives in the local style block below.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fish Price Board — Virac Public Market</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    @vite(['resources/css/app.css'])

    <style>
        /* ── Public shell ────────────────────────────────────────────────
           Structural rules unique to this page. Colours all come from the
           shared tokens so the board cannot drift from the admin system. */
        .pb-header {
            background-image: linear-gradient(135deg, var(--color-navy-900) 0%, var(--color-navy-800) 55%, var(--color-navy-700) 100%);
            box-shadow: var(--shadow-header);
        }
        .pb-header-inner,
        .pb-stats-inner,
        .pb-main {
            max-width: 1280px;
            margin: 0 auto;
        }
        .pb-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1.125rem 2rem;
        }
        .pb-brand { display: flex; align-items: center; gap: 0.875rem; }
        .pb-logo {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-card);
            border: 1px solid rgb(255 255 255 / 0.25);
            background: rgb(255 255 255 / 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .pb-logo img { width: 100%; height: 100%; object-fit: contain; }
        .pb-title {
            color: #fff;
            font-size: 1.0625rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .pb-sub { color: rgb(255 255 255 / 0.72); font-size: 0.78rem; margin-top: 2px; }

        .pb-datechip {
            background: rgb(255 255 255 / 0.12);
            border: 1px solid rgb(255 255 255 / 0.22);
            border-radius: var(--radius-card);
            padding: 0.5rem 1rem;
            text-align: right;
        }
        .pb-datechip-label {
            color: rgb(255 255 255 / 0.65);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .pb-datechip-value { color: #fff; font-size: 0.875rem; font-weight: 600; }

        .pb-livedot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--color-success-400);
            margin-right: 6px;
            vertical-align: middle;
            animation: pb-pulse 2s ease-in-out infinite;
        }
        @keyframes pb-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: 0.55; transform: scale(0.82); }
        }

        .pb-stats { background: var(--color-surface); border-bottom: 1px solid var(--color-slate-200); }
        .pb-stats-inner {
            display: flex;
            align-items: center;
            gap: 1.75rem;
            flex-wrap: wrap;
            padding: 0.875rem 2rem;
        }
        .pb-stat { display: flex; align-items: center; gap: 0.625rem; }
        .pb-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: var(--radius-control);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }
        .pb-stat-val { font-size: 1rem; font-weight: 700; color: var(--color-slate-800); line-height: 1; }
        .pb-stat-lbl { font-size: 0.7rem; color: var(--color-slate-500); margin-top: 1px; }
        .pb-divider { width: 1px; height: 30px; background: var(--color-slate-200); }

        .pb-main { padding: 1.5rem 2rem 3rem; }

        .pb-controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .pb-search { position: relative; flex: 1; min-width: 200px; max-width: 380px; }
        .pb-search > i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-slate-400);
            font-size: 14px;
            pointer-events: none;
        }
        .pb-result { margin-left: auto; font-size: 0.8125rem; color: var(--color-slate-500); white-space: nowrap; }

        /* Quality-class filter tabs. */
        .pb-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .pb-tab {
            padding: 0.4rem 0.9rem;
            border-radius: var(--radius-pill);
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid var(--color-slate-200);
            background: var(--color-surface);
            color: var(--color-slate-500);
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .pb-tab:hover { border-color: var(--color-brand-300); color: var(--color-brand-600); }
        .pb-tab.is-active {
            background: var(--color-brand-600);
            border-color: var(--color-brand-600);
            color: #fff;
        }

        /* Fish thumbnail with gradient fallback. */
        .pb-thumb {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-control);
            object-fit: cover;
            border: 1px solid var(--color-slate-200);
            flex-shrink: 0;
        }
        .pb-thumb-fallback {
            width: 36px;
            height: 36px;
            border-radius: var(--radius-control);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background-image: linear-gradient(135deg, var(--color-navy-800), var(--color-brand-600));
            color: rgb(255 255 255 / 0.85);
            font-size: 14px;
        }
        .pb-thumb-lg { width: 38px; height: 38px; }

        .pb-stock-low  { color: var(--color-danger-600); }
        .pb-stock-ok   { color: var(--color-slate-700); }

        /* Card view */
        .pb-cardgrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }
        .pb-card { overflow: hidden; }
        .pb-card-top { background-image: linear-gradient(135deg, var(--color-navy-800), var(--color-brand-600)); }
        .pb-cardfish { color: #fff; font-size: 1rem; font-weight: 700; }
        .pb-cardvendor { color: rgb(255 255 255 / 0.72); font-size: 0.75rem; margin-top: 2px; }
        .pb-cardmedia {
            position: relative;
            width: 100%;
            height: 110px;
            overflow: hidden;
        }
        .pb-cardmedia img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .pb-cardmedia::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgb(10 31 60 / 0.88) 0%, rgb(10 31 60 / 0.2) 60%, transparent 100%);
        }
        .pb-cardoverlay {
            position: absolute;
            bottom: 10px;
            left: 14px;
            right: 14px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 0.5rem;
            z-index: 1;
        }
        .pb-pill-onbrand {
            white-space: nowrap;
            font-size: 0.65rem;
            background: rgb(255 255 255 / 0.2);
            color: #fff;
            border: 1px solid rgb(255 255 255 / 0.35);
        }
        .pb-pricerow { display: flex; align-items: baseline; gap: 3px; margin-bottom: 0.75rem; }
        .pb-peso { font-size: 1rem; font-weight: 600; color: var(--color-slate-500); }
        .pb-pricenum { font-size: 1.85rem; font-weight: 800; color: var(--color-slate-900); line-height: 1; }
        .pb-priceunit { font-size: 0.8rem; color: var(--color-slate-400); align-self: flex-end; padding-bottom: 3px; }
        .pb-cardmeta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
        }
        .pb-cardstock { display: flex; align-items: center; gap: 5px; color: var(--color-slate-600); }

        /* Grouped-by-vendor view */
        .pb-vendor-section { margin-bottom: 1.25rem; }
        .pb-vendor-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--color-surface);
            border: 1px solid var(--color-slate-200);
            border-bottom: 2px solid var(--color-brand-600);
            border-radius: var(--radius-card) var(--radius-card) 0 0;
            padding: 0.85rem 1.25rem;
        }
        .pb-vendor-avatar {
            width: 38px;
            height: 38px;
            border-radius: var(--radius-control);
            background: var(--color-brand-50);
            color: var(--color-brand-600);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .pb-vendor-name { font-weight: 700; color: var(--color-slate-900); font-size: 0.95rem; }
        .pb-vendor-stall { font-size: 0.75rem; color: var(--color-slate-500); }
        .pb-vendor-count {
            margin-left: auto;
            background: var(--color-brand-50);
            color: var(--color-brand-600);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: var(--radius-pill);
        }
        .pb-vendor-items {
            background: var(--color-surface);
            border: 1px solid var(--color-slate-200);
            border-top: 0;
            border-radius: 0 0 var(--radius-card) var(--radius-card);
            overflow: hidden;
        }
        .pb-vendor-item {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid var(--color-slate-100);
            font-size: 0.875rem;
        }
        .pb-vendor-item:last-child { border-bottom: 0; }
        .pb-vendor-item:hover { background: var(--color-surface-subtle); }
        .pb-mini-label { font-size: 0.75rem; color: var(--color-slate-500); }

        .pb-footer {
            background: var(--color-navy-950);
            color: rgb(255 255 255 / 0.6);
            text-align: center;
            padding: 1.5rem 2rem;
            font-size: 0.8rem;
        }
        .pb-footer strong { color: rgb(255 255 255 / 0.9); }

        @media (max-width: 640px) {
            .pb-header-inner, .pb-stats-inner { padding-left: 1rem; padding-right: 1rem; }
            .pb-main { padding: 1rem 1rem 2rem; }
            .pb-divider { display: none; }
        }
    </style>
</head>
<body class="bg-slate-50">

{{-- ═══════════════════════════════════════════════════════════
     SITE HEADER
═══════════════════════════════════════════════════════════════ --}}
<header class="pb-header">
    <div class="pb-header-inner">
        <div class="pb-brand">
            <div class="pb-logo">
                <img src="{{ asset('logo.png') }}" alt="VPM Logo">
            </div>
            <div>
                <div class="pb-title">Virac Public Market</div>
                <div class="pb-sub">Fish Section — Live Price Monitoring Board</div>
            </div>
        </div>
        <div class="pb-datechip">
            <div class="pb-datechip-label"><span class="pb-livedot"></span>Live Today</div>
            <div class="pb-datechip-value">{{ \Carbon\Carbon::today()->format('F j, Y') }}</div>
        </div>
    </div>
</header>

{{-- ═══════════════════════════════════════════════════════════
     STATS BAR
═══════════════════════════════════════════════════════════════ --}}
@php
    $totalEntries  = $prices->count();
    $totalVendors  = $prices->pluck('vendor_id')->unique()->count();
    $totalStockKg  = number_format($prices->sum('stock_kg'), 1);
    $fishTypeCount = $prices->pluck('fish_type_id')->unique()->count();
@endphp

<div class="pb-stats">
    <div class="pb-stats-inner">
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon"><x-icon name="bi-tags" /></div>
            <div>
                <div class="pb-stat-val">{{ $totalEntries }}</div>
                <div class="pb-stat-lbl">Price Entries</div>
            </div>
        </div>
        <div class="pb-divider"></div>
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon-success"><x-icon name="bi-shop-window" /></div>
            <div>
                <div class="pb-stat-val">{{ $totalVendors }}</div>
                <div class="pb-stat-lbl">Active Vendors</div>
            </div>
        </div>
        <div class="pb-divider"></div>
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon"><x-icon name="bi-fish" /></div>
            <div>
                <div class="pb-stat-val">{{ $fishTypeCount }}</div>
                <div class="pb-stat-lbl">Fish Varieties</div>
            </div>
        </div>
        <div class="pb-divider"></div>
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon-warning"><x-icon name="bi-weight-hanging" /></div>
            <div>
                <div class="pb-stat-val">{{ $totalStockKg }} kg</div>
                <div class="pb-stat-lbl">Total Stock</div>
            </div>
        </div>
        <div class="pb-divider ml-auto d-none d-md-block"></div>
        <div class="text-[12.5px] text-slate-500">
            <x-icon name="bi-check-circle-fill" class="text-success-500" />
            All prices verified by Market Staff
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT
═══════════════════════════════════════════════════════════════ --}}
<main class="pb-main" x-data="priceBoard()" x-init="init()">

    {{-- Controls Bar --}}
    <div class="pb-controls">
        <div class="pb-search">
            <x-icon name="bi-search" />
            <input type="text"
                   class="vpm-input pl-9"
                   placeholder="Search fish type or vendor..."
                   aria-label="Search prices"
                   x-model="search"
                   @input="applyFilters()">
        </div>

        <select class="vpm-filter-select" x-model="sortBy" @change="applyFilters()" aria-label="Sort prices">
            <option value="fish">Sort by Fish Name</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="stock">Most Stock</option>
            <option value="vendor">Vendor Name</option>
        </select>

        <div class="vpm-segment" role="group" aria-label="View mode">
            <button :aria-pressed="view === 'table'" @click="view='table'" title="Table view">
                <x-icon name="bi-list-ul" />
            </button>
            <button :aria-pressed="view === 'card'" @click="view='card'" title="Card view">
                <x-icon name="bi-grid" />
            </button>
            <button :aria-pressed="view === 'vendor'" @click="view='vendor'" title="By vendor">
                <x-icon name="bi-shop-window" />
            </button>
        </div>

        <span class="pb-result" x-text="filtered.length + ' result' + (filtered.length !== 1 ? 's' : '')"></span>
    </div>

    {{-- Quality Class Tabs --}}
    <div class="pb-tabs" role="group" aria-label="Filter by quality class">
        <button class="pb-tab" :class="{ 'is-active': classFilter === '' }" @click="classFilter=''; applyFilters()">
            All Classes
        </button>
        @foreach(['First Class','Second Class','Third Class','Fourth Class','Special Class'] as $cls)
            <button class="pb-tab"
                    :class="{ 'is-active': classFilter === '{{ $cls }}' }"
                    @click="classFilter='{{ $cls }}'; applyFilters()">
                {{ $cls }}
            </button>
        @endforeach
    </div>

    {{-- ── TABLE VIEW ──────────────────────────────────────── --}}
    <div x-show="view === 'table'">
        <div class="vpm-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="vpm-table">
                    <thead>
                        <tr>
                            <th>Fish Type</th>
                            <th>Quality Class</th>
                            <th>Vendor / Stall</th>
                            <th class="vpm-th-right">Stock Available</th>
                            <th class="vpm-th-right">Price per kg</th>
                            <th class="vpm-th-right">Confirmed At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="filtered.length === 0">
                            <tr>
                                <td colspan="6">
                                    <x-empty-state icon="bi-fish"
                                                   title="No confirmed prices found for today"
                                                   text="Nothing matches your current filters." />
                                </td>
                            </tr>
                        </template>
                        <template x-for="row in filtered" :key="row.id">
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <template x-if="row.fish_image">
                                            <img :src="row.fish_image" :alt="row.fish_name" class="pb-thumb">
                                        </template>
                                        <template x-if="!row.fish_image">
                                            <div class="pb-thumb-fallback"><x-icon name="bi-fish" /></div>
                                        </template>
                                        <span class="vpm-cell-strong" x-text="row.fish_name"></span>
                                    </div>
                                </td>
                                <td>
                                    <span :class="qualityClass(row.quality_class)" x-text="row.quality_class"></span>
                                </td>
                                <td>
                                    <div class="vpm-cell-muted">
                                        <span x-text="row.vendor_name"></span>
                                        <x-badge variant="neutral" class="ml-1">Stall <span x-text="row.stall_number"></span></x-badge>
                                    </div>
                                </td>
                                <td class="vpm-td-right">
                                    <span class="font-semibold"
                                          :class="row.stock_kg < 20 ? 'pb-stock-low' : 'pb-stock-ok'"
                                          x-text="parseFloat(row.stock_kg).toFixed(1) + ' kg'"></span>
                                </td>
                                <td class="vpm-td-right">
                                    <span class="text-[15px] font-bold text-slate-900">
                                        &#8369;<span x-text="parseFloat(row.price_per_kg).toFixed(2)"></span>
                                        <span class="text-[11.5px] font-normal text-slate-400">/kg</span>
                                    </span>
                                </td>
                                <td class="vpm-td-right vpm-cell-muted" x-text="row.confirmed_at"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ── CARD VIEW ───────────────────────────────────────── --}}
    <div x-show="view === 'card'">
        <template x-if="filtered.length === 0">
            <x-empty-state icon="bi-fish"
                           title="No prices available"
                           text="No confirmed price entries match your current filters." />
        </template>
        <div class="pb-cardgrid">
            <template x-for="row in filtered" :key="row.id">
                <div class="vpm-card vpm-card-hover pb-card">
                    <template x-if="row.fish_image">
                        <div class="pb-cardmedia">
                            <img :src="row.fish_image" :alt="row.fish_name">
                            <div class="pb-cardoverlay">
                                <div>
                                    <div class="pb-cardfish" x-text="row.fish_name"></div>
                                    <div class="pb-cardvendor">
                                        <span x-text="row.vendor_name"></span>
                                        &bull; Stall <span x-text="row.stall_number"></span>
                                    </div>
                                </div>
                                <span class="vpm-quality pb-pill-onbrand" x-text="row.quality_class"></span>
                            </div>
                        </div>
                    </template>
                    <template x-if="!row.fish_image">
                        <div class="pb-card-top p-4 pb-3 flex items-start justify-between gap-2">
                            <div>
                                <div class="pb-cardfish" x-text="row.fish_name"></div>
                                <div class="pb-cardvendor">
                                    <span x-text="row.vendor_name"></span>
                                    &bull; Stall <span x-text="row.stall_number"></span>
                                </div>
                            </div>
                            <span class="whitespace-nowrap" :class="qualityClass(row.quality_class)" style="font-size: 0.65rem"></span>
                        </div>
                    </template>
                    <div class="p-4">
                        <div class="pb-pricerow">
                            <span class="pb-peso">&#8369;</span>
                            <span class="pb-pricenum" x-text="parseFloat(row.price_per_kg).toFixed(2)"></span>
                            <span class="pb-priceunit">per kg</span>
                        </div>
                        <div class="pb-cardmeta">
                            <div class="pb-cardstock">
                                <x-icon name="bi-box-seam" class="text-slate-400" />
                                <span :class="row.stock_kg < 20 ? 'pb-stock-low' : ''"
                                      x-text="parseFloat(row.stock_kg).toFixed(1) + ' kg available'"></span>
                            </div>
                            <span class="vpm-badge vpm-badge-success">
                                <x-icon name="bi-check-circle-fill" /> Confirmed
                            </span>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ── BY VENDOR VIEW ──────────────────────────────────── --}}
    <div x-show="view === 'vendor'">
        <template x-if="filtered.length === 0">
            <x-empty-state icon="bi-shop-window"
                           title="No vendors found"
                           text="No confirmed price entries match your current filters." />
        </template>
        <template x-for="vendor in byVendor()" :key="vendor.id">
            <div class="pb-vendor-section">
                <div class="pb-vendor-header">
                    <div class="pb-vendor-avatar" x-text="initials(vendor.name)"></div>
                    <div>
                        <div class="pb-vendor-name" x-text="vendor.name"></div>
                        <div class="pb-vendor-stall">Stall <span x-text="vendor.stall"></span></div>
                    </div>
                    <span class="pb-vendor-count" x-text="vendor.entries.length + ' item' + (vendor.entries.length !== 1 ? 's' : '')"></span>
                </div>
                <div class="pb-vendor-items">
                    <template x-for="row in vendor.entries" :key="row.id">
                        <div class="pb-vendor-item">
                            <div class="flex items-center gap-2.5">
                                <template x-if="row.fish_image">
                                    <img :src="row.fish_image" :alt="row.fish_name" class="pb-thumb pb-thumb-lg">
                                </template>
                                <template x-if="!row.fish_image">
                                    <div class="pb-thumb-fallback pb-thumb-lg"><x-icon name="bi-fish" /></div>
                                </template>
                                <div>
                                    <div class="vpm-cell-strong" x-text="row.fish_name"></div>
                                    <span :class="qualityClass(row.quality_class)" style="margin-top: 4px; display: inline-block;"></span>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="pb-mini-label">Stock</div>
                                <div class="font-semibold"
                                     :class="row.stock_kg < 20 ? 'pb-stock-low' : ''"
                                     x-text="parseFloat(row.stock_kg).toFixed(1) + ' kg'"></div>
                            </div>
                            <div class="text-right min-w-[90px]">
                                <div class="pb-mini-label">Price/kg</div>
                                <div class="text-[17px] font-bold text-slate-900">
                                    &#8369;<span x-text="parseFloat(row.price_per_kg).toFixed(2)"></span>
                                </div>
                            </div>
                            <div>
                                <i class="bi bi-check-circle-fill text-[16px] text-success-500" title="Confirmed"></i>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

</main>

{{-- ═══════════════════════════════════════════════════════════
     FOOTER
═══════════════════════════════════════════════════════════════ --}}
<footer class="pb-footer">
    <strong>Virac Public Market</strong> — Commodity Supply Projection &amp; Price Monitoring System
    &nbsp;|&nbsp; Prices are verified by Market Staff &amp; updated daily
    &nbsp;|&nbsp; Catanduanes State University &copy; {{ date('Y') }}
</footer>

{{-- ═══════════════════════════════════════════════════════════
     ALPINE.JS — CLIENT-SIDE FILTERING / SORTING / VIEW
═══════════════════════════════════════════════════════════════ --}}

{{-- Prepare a plain array for @json — closures inside @json() cause a ParseError --}}
@php
    $priceRows = $prices->map(function ($p) {
        return [
            'id'            => $p->id,
            'fish_name'     => $p->fishType->name ?? '—',
            'fish_type_id'  => $p->fish_type_id,
            'fish_image'    => $p->fishType->image_path
                                ? asset('storage/' . $p->fishType->image_path)
                                : null,
            'quality_class' => $p->quality_class,
            'vendor_id'     => $p->vendor_id,
            'vendor_name'   => $p->vendor->name ?? '—',
            'stall_number'  => $p->vendor->vendorProfile->stall_number ?? '—',
            'price_per_kg'  => (float) $p->price_per_kg,
            'stock_kg'      => (float) $p->stock_kg,
            'confirmed_at'  => $p->confirmed_at
                ? \Carbon\Carbon::parse($p->confirmed_at)->format('h:i A')
                : '—',
        ];
    })->values()->all();
@endphp

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
<script>
    {{-- Tier -> tone map, derived from the canonical list so this JS copy can
         never drift from <x-quality-badge>. --}}
    const QUALITY_TONES = @json(
        collect(App\Models\FishType::QUALITY_CLASSES)
            ->mapWithKeys(fn ($q) => [
                $q => 'vpm-quality vpm-quality-' . str($q)->lower()->replace(' class', ''),
            ])
    );

    function priceBoard() {
        const raw = @json($priceRows);


        return {
            all: raw,
            filtered: raw,
            search: '',
            sortBy: 'fish',
            classFilter: '',
            view: 'card',

            init() {
                this.applyFilters();
            },

            applyFilters() {
                let data = [...this.all];

                // Search filter
                if (this.search.trim()) {
                    const q = this.search.toLowerCase();
                    data = data.filter(r =>
                        r.fish_name.toLowerCase().includes(q) ||
                        r.vendor_name.toLowerCase().includes(q) ||
                        r.stall_number.toLowerCase().includes(q)
                    );
                }

                // Class filter
                if (this.classFilter) {
                    data = data.filter(r => r.quality_class === this.classFilter);
                }

                // Sort
                switch (this.sortBy) {
                    case 'fish':
                        data.sort((a, b) => a.fish_name.localeCompare(b.fish_name));
                        break;
                    case 'price_asc':
                        data.sort((a, b) => a.price_per_kg - b.price_per_kg);
                        break;
                    case 'price_desc':
                        data.sort((a, b) => b.price_per_kg - a.price_per_kg);
                        break;
                    case 'stock':
                        data.sort((a, b) => b.stock_kg - a.stock_kg);
                        break;
                    case 'vendor':
                        data.sort((a, b) => a.vendor_name.localeCompare(b.vendor_name));
                        break;
                }

                this.filtered = data;
            },

            {{-- Resolves a quality class to the shared .vpm-quality-* tone. The
                 map is emitted from FishType::QUALITY_CLASSES below so the tier
                 list has a single source of truth, exactly as the server-rendered
                 <x-quality-badge> does. --}}
            qualityClass(cls) {
                return QUALITY_TONES[cls] || 'vpm-quality vpm-quality-neutral';
            },

            byVendor() {
                const map = {};
                this.filtered.forEach(row => {
                    if (!map[row.vendor_id]) {
                        map[row.vendor_id] = {
                            id:      row.vendor_id,
                            name:    row.vendor_name,
                            stall:   row.stall_number,
                            entries: [],
                        };
                    }
                    map[row.vendor_id].entries.push(row);
                });
                return Object.values(map).sort((a, b) => a.name.localeCompare(b.name));
            },

            initials(name) {
                return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
            },
        };
    }
</script>
</body>
</html>
