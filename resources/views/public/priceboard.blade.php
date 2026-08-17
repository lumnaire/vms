{{-- resources/views/public/priceboard.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fish Price Board — Virac Public Market</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
        :root {
            --ocean: #0e6e9e;
            --ocean-light: #e8f4fb;
            --ocean-mid: #1a8ec4;
            --teal: #0d7c6b;
            --teal-light: #e3f7f3;
            --amber: #b45309;
            --amber-light: #fef3c7;
            --red: #b91c1c;
            --red-light: #fee2e2;
            --green: #15803d;
            --green-light: #dcfce7;
        }

        body {
            background: #f0f4f8;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* Header */
        .site-header {
            background: linear-gradient(135deg, #0c4a6e 0%, #0e6e9e 60%, #0891b2 100%);
            padding: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .header-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.25rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .header-title {
            color: white;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.01em;
            line-height: 1.2;
        }

        .header-sub {
            color: rgba(255,255,255,0.75);
            font-size: 0.78rem;
            margin-top: 2px;
        }

        .header-date {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            border-radius: 10px;
            padding: 0.5rem 1rem;
            text-align: right;
        }

        .header-date .date-label {
            color: rgba(255,255,255,0.65);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .header-date .date-val {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .live-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #4ade80;
            border-radius: 50%;
            animation: pulse 2s infinite;
            margin-right: 5px;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.85); }
        }

        /* Stats bar */
        .stats-bar {
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }

        .stats-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0.85rem 2rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .stat-icon.blue { background: var(--ocean-light); color: var(--ocean); }
        .stat-icon.teal { background: var(--teal-light); color: var(--teal); }
        .stat-icon.amber { background: var(--amber-light); color: var(--amber); }

        .stat-text .val {
            font-size: 1rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }

        .stat-text .lbl {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 1px;
        }

        .divider-v {
            width: 1px;
            height: 30px;
            background: #e2e8f0;
        }

        /* Main layout */
        .main-wrap {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.5rem 2rem 3rem;
        }

        /* Controls bar */
        .controls {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 200px;
            max-width: 380px;
        }

        .search-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .search-input {
            width: 100%;
            padding: 0.55rem 0.9rem 0.55rem 2.2rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.875rem;
            background: white;
            outline: none;
            transition: border-color 0.15s;
            color: #1e293b;
        }

        .search-input:focus {
            border-color: var(--ocean);
            box-shadow: 0 0 0 3px rgba(14,110,158,0.12);
        }

        .filter-select {
            padding: 0.55rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.875rem;
            background: white;
            color: #1e293b;
            cursor: pointer;
            outline: none;
        }

        .filter-select:focus {
            border-color: var(--ocean);
        }

        .view-toggle {
            display: flex;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            overflow: hidden;
            background: white;
        }

        .view-btn {
            padding: 0.55rem 0.75rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            font-size: 14px;
            transition: background 0.15s, color 0.15s;
        }

        .view-btn.active {
            background: var(--ocean);
            color: white;
        }

        .result-count {
            margin-left: auto;
            font-size: 0.82rem;
            color: #64748b;
            white-space: nowrap;
        }

        /* Class filter tabs */
        .class-tabs {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .class-tab {
            padding: 0.4rem 1rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 500;
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
            background: white;
            color: #64748b;
            border-color: #e2e8f0;
        }

        .class-tab:hover {
            border-color: var(--ocean);
            color: var(--ocean);
        }

        .class-tab.active {
            background: var(--ocean);
            border-color: var(--ocean);
            color: white;
        }

        /* === TABLE VIEW === */
        .price-table-wrap {
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .price-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .price-table thead tr {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        .price-table th {
            padding: 0.85rem 1rem;
            text-align: left;
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            white-space: nowrap;
        }

        .price-table th.right { text-align: right; }

        .price-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s;
        }

        .price-table tbody tr:last-child { border-bottom: none; }
        .price-table tbody tr:hover { background: #f8fafc; }

        .price-table td {
            padding: 0.85rem 1rem;
            color: #1e293b;
            vertical-align: middle;
        }

        .price-table td.right { text-align: right; }

        .fish-name {
            font-weight: 600;
            color: #0f172a;
        }

        .vendor-name {
            font-size: 0.78rem;
            color: #64748b;
        }

        .stall-badge {
            display: inline-block;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 5px;
            margin-left: 5px;
        }

        .class-pill {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 600;
        }

        .class-pill.first    { background: #eff6ff; color: #1d4ed8; }
        .class-pill.second   { background: #f0fdf4; color: #15803d; }
        .class-pill.third    { background: #fefce8; color: #a16207; }
        .class-pill.fourth   { background: #fff7ed; color: #c2410c; }
        .class-pill.special  { background: #fdf4ff; color: #7e22ce; }

        .price-val {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .price-unit {
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 400;
        }

        .stock-val {
            font-weight: 600;
            color: #1e293b;
        }

        .stock-low { color: var(--red); }
        .stock-ok  { color: var(--green); }

        /* === CARD VIEW === */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }

        .price-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.15s, transform 0.15s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .price-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .card-top {
            background: linear-gradient(135deg, #0c4a6e, #0e6e9e);
            padding: 1rem 1.15rem 0.75rem;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .card-fish {
            font-size: 1rem;
            font-weight: 700;
            color: white;
        }

        .card-vendor {
            font-size: 0.75rem;
            color: rgba(255,255,255,0.7);
            margin-top: 2px;
        }

        .card-body {
            padding: 1rem 1.15rem;
        }

        .card-price-row {
            display: flex;
            align-items: baseline;
            gap: 4px;
            margin-bottom: 0.75rem;
        }

        .card-peso {
            font-size: 1rem;
            font-weight: 600;
            color: #475569;
        }

        .card-price-num {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }

        .card-price-unit {
            font-size: 0.8rem;
            color: #94a3b8;
            align-self: flex-end;
            padding-bottom: 3px;
        }

        .card-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.8rem;
        }

        .card-stock {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #475569;
        }

        .card-stock i { font-size: 13px; }

        .card-confirmed {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            color: #16a34a;
        }

        /* === BY VENDOR VIEW === */
        .vendor-section {
            margin-bottom: 1.5rem;
        }

        .vendor-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0;
            padding: 0.85rem 1.25rem;
            border-bottom: 2px solid var(--ocean);
        }

        .vendor-avatar {
            width: 38px;
            height: 38px;
            border-radius: 9px;
            background: var(--ocean-light);
            color: var(--ocean);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .vendor-info .vname {
            font-weight: 700;
            color: #0f172a;
            font-size: 0.95rem;
        }

        .vendor-info .vstall {
            font-size: 0.75rem;
            color: #64748b;
        }

        .vendor-entry-count {
            margin-left: auto;
            background: var(--ocean-light);
            color: var(--ocean);
            font-size: 0.75rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 999px;
        }

        .vendor-items {
            background: white;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 12px 12px;
            overflow: hidden;
        }

        .vendor-item {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            font-size: 0.875rem;
        }

        .vendor-item:last-child { border-bottom: none; }

        .vendor-item:hover { background: #f8fafc; }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-icon {
            font-size: 3.5rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #475569;
            margin-bottom: 0.5rem;
        }

        /* Footer */
        .page-footer {
            background: #0c1e2d;
            color: rgba(255,255,255,0.6);
            text-align: center;
            padding: 1.5rem 2rem;
            font-size: 0.8rem;
            margin-top: 2rem;
        }

        .page-footer strong {
            color: rgba(255,255,255,0.9);
        }

        /* No-data highlight */
        .no-data-row td {
            text-align: center;
            color: #94a3b8;
            padding: 3rem;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .header-inner { padding: 1rem; }
            .stats-inner { padding: 0.75rem 1rem; gap: 1rem; }
            .main-wrap { padding: 1rem; }
            .price-table th:nth-child(4),
            .price-table td:nth-child(4) { display: none; }
        }
    </style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════════
     SITE HEADER
════════════════════════════════════════════════════════════════ --}}
<header class="site-header">
    <div class="header-inner">
        <div class="header-brand">
            <div class="header-icon" style="background: rgba(255,255,255,0.15); padding: 4px;">
                <img src="{{ asset('logo.png') }}" alt="VPM Logo" style="width:100%; height:100%; object-fit:contain; border-radius:6px;">
            </div>
            <div>
                <div class="header-title">Virac Public Market</div>
                <div class="header-sub">Fish Section — Live Price Monitoring Board</div>
            </div>
        </div>
        <div class="header-date">
            <div class="date-label"><span class="live-dot"></span>Live Today</div>
            <div class="date-val">{{ \Carbon\Carbon::today()->format('F j, Y') }}</div>
        </div>
    </div>
</header>

{{-- ═══════════════════════════════════════════════════════════
     STATS BAR
════════════════════════════════════════════════════════════════ --}}
@php
    $totalEntries   = $prices->count();
    $totalVendors   = $prices->pluck('vendor_id')->unique()->count();
    $totalStockKg   = number_format($prices->sum('stock_kg'), 1);
    $fishTypeCount  = $prices->pluck('fish_type_id')->unique()->count();
@endphp

<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item">
            <div class="stat-icon blue"><i class="fas fa-tags"></i></div>
            <div class="stat-text">
                <div class="val">{{ $totalEntries }}</div>
                <div class="lbl">Price Entries</div>
            </div>
        </div>
        <div class="divider-v"></div>
        <div class="stat-item">
            <div class="stat-icon teal"><i class="fas fa-store"></i></div>
            <div class="stat-text">
                <div class="val">{{ $totalVendors }}</div>
                <div class="lbl">Active Vendors</div>
            </div>
        </div>
        <div class="divider-v"></div>
        <div class="stat-item">
            <div class="stat-icon blue"><i class="fas fa-fish"></i></div>
            <div class="stat-text">
                <div class="val">{{ $fishTypeCount }}</div>
                <div class="lbl">Fish Varieties</div>
            </div>
        </div>
        <div class="divider-v"></div>
        <div class="stat-item">
            <div class="stat-icon amber"><i class="fas fa-weight-hanging"></i></div>
            <div class="stat-text">
                <div class="val">{{ $totalStockKg }} kg</div>
                <div class="lbl">Total Stock</div>
            </div>
        </div>
        <div class="divider-v" style="margin-left:auto;"></div>
        <div style="font-size:0.78rem; color:#64748b;">
            <i class="fas fa-check-circle" style="color:#16a34a; margin-right:4px;"></i>
            All prices verified by Market Staff
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════════════════════════════ --}}
<main class="main-wrap" x-data="priceBoard()" x-init="init()">

    {{-- Controls Bar --}}
    <div class="controls">
        <div class="search-wrap">
            <i class="fas fa-search"></i>
            <input
                type="text"
                class="search-input"
                placeholder="Search fish type or vendor..."
                x-model="search"
                @input="applyFilters()"
            />
        </div>

        <select class="filter-select" x-model="sortBy" @change="applyFilters()">
            <option value="fish">Sort by Fish Name</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="stock">Most Stock</option>
            <option value="vendor">Vendor Name</option>
        </select>

        <div class="view-toggle">
            <button class="view-btn" :class="{ active: view === 'table' }" @click="view='table'" title="Table view">
                <i class="fas fa-table-list"></i>
            </button>
            <button class="view-btn" :class="{ active: view === 'card' }" @click="view='card'" title="Card view">
                <i class="fas fa-grip"></i>
            </button>
            <button class="view-btn" :class="{ active: view === 'vendor' }" @click="view='vendor'" title="By vendor">
                <i class="fas fa-store"></i>
            </button>
        </div>

        <span class="result-count" x-text="filtered.length + ' result' + (filtered.length !== 1 ? 's' : '')"></span>
    </div>

    {{-- Quality Class Tabs --}}
    <div class="class-tabs">
        <button class="class-tab" :class="{ active: classFilter === '' }" @click="classFilter=''; applyFilters()">
            All Classes
        </button>
        @foreach(['First Class','Second Class','Third Class','Fourth Class','Special Class'] as $cls)
        <button
            class="class-tab"
            :class="{ active: classFilter === '{{ $cls }}' }"
            @click="classFilter='{{ $cls }}'; applyFilters()"
        >{{ $cls }}</button>
        @endforeach
    </div>

    {{-- ── TABLE VIEW ──────────────────────────────────────── --}}
    <div x-show="view === 'table'">
        <div class="price-table-wrap">
            <table class="price-table">
                <thead>
                    <tr>
                        <th>Fish Type</th>
                        <th>Quality Class</th>
                        <th>Vendor / Stall</th>
                        <th class="right">Stock Available</th>
                        <th class="right">Price per kg</th>
                        <th class="right">Confirmed At</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filtered.length === 0">
                        <tr class="no-data-row">
                            <td colspan="6">
                                <i class="fas fa-fish" style="font-size:2rem; color:#cbd5e1; display:block; margin-bottom:8px;"></i>
                                No confirmed prices found for today matching your filters.
                            </td>
                        </tr>
                    </template>
                    <template x-for="row in filtered" :key="row.id">
                        <tr>
                            <td>
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <template x-if="row.fish_image">
                                        <img :src="row.fish_image" :alt="row.fish_name"
                                             style="width:36px; height:36px; border-radius:8px; object-fit:cover; border:1px solid #e2e8f0; flex-shrink:0;">
                                    </template>
                                    <template x-if="!row.fish_image">
                                        <div style="width:36px; height:36px; border-radius:8px; background:linear-gradient(135deg,#0c4a6e,#0e6e9e); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                            <i class="fas fa-fish" style="color:rgba(255,255,255,0.8); font-size:14px;"></i>
                                        </div>
                                    </template>
                                    <div class="fish-name" x-text="row.fish_name"></div>
                                </div>
                            </td>
                            <td>
                                <span class="class-pill" :class="classPillClass(row.quality_class)" x-text="row.quality_class"></span>
                            </td>
                            <td>
                                <div class="vendor-name">
                                    <span x-text="row.vendor_name"></span>
                                    <span class="stall-badge" x-text="'Stall ' + row.stall_number"></span>
                                </div>
                            </td>
                            <td class="right">
                                <span class="stock-val" :class="row.stock_kg < 20 ? 'stock-low' : 'stock-ok'" x-text="parseFloat(row.stock_kg).toFixed(1) + ' kg'"></span>
                            </td>
                            <td class="right">
                                <div class="price-val">
                                    ₱<span x-text="parseFloat(row.price_per_kg).toFixed(2)"></span>
                                    <span class="price-unit">/kg</span>
                                </div>
                            </td>
                            <td class="right" style="font-size:0.78rem; color:#64748b;" x-text="row.confirmed_at"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── CARD VIEW ───────────────────────────────────────── --}}
    <div x-show="view === 'card'">
        <template x-if="filtered.length === 0">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-fish"></i></div>
                <h3>No prices available</h3>
                <p>No confirmed price entries match your current filters.</p>
            </div>
        </template>
        <div class="card-grid">
            <template x-for="row in filtered" :key="row.id">
                <div class="price-card">
                    <div class="card-top" :style="row.fish_image ? 'background:none; padding:0; position:relative;' : ''">
                        <template x-if="row.fish_image">
                            <div style="position:relative; width:100%; height:110px; overflow:hidden; border-radius:12px 12px 0 0;">
                                <img :src="row.fish_image" :alt="row.fish_name"
                                     style="width:100%; height:100%; object-fit:cover; display:block;">
                                <div style="position:absolute;inset:0; background:linear-gradient(to top, rgba(12,74,110,0.88) 0%, rgba(12,74,110,0.2) 60%, transparent 100%);"></div>
                                <div style="position:absolute; bottom:10px; left:14px; right:14px; display:flex; align-items:flex-end; justify-content:space-between;">
                                    <div>
                                        <div class="card-fish" x-text="row.fish_name"></div>
                                        <div class="card-vendor">
                                            <span x-text="row.vendor_name"></span>
                                            &bull; Stall <span x-text="row.stall_number"></span>
                                        </div>
                                    </div>
                                    <span class="class-pill" :class="classPillClass(row.quality_class)" x-text="row.quality_class" style="white-space:nowrap; font-size:0.65rem; background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.35);"></span>
                                </div>
                            </div>
                        </template>
                        <template x-if="!row.fish_image">
                            <div style="background:linear-gradient(135deg,#0c4a6e,#0e6e9e); padding:1rem 1.15rem 0.75rem; display:flex; align-items:flex-start; justify-content:space-between; width:100%; box-sizing:border-box;">
                                <div>
                                    <div class="card-fish" x-text="row.fish_name"></div>
                                    <div class="card-vendor">
                                        <span x-text="row.vendor_name"></span>
                                        &bull; Stall <span x-text="row.stall_number"></span>
                                    </div>
                                </div>
                                <span class="class-pill" :class="classPillClass(row.quality_class)" x-text="row.quality_class" style="white-space:nowrap; font-size:0.65rem;"></span>
                            </div>
                        </template>
                    </div>
                    <div class="card-body">
                        <div class="card-price-row">
                            <span class="card-peso">₱</span>
                            <span class="card-price-num" x-text="parseFloat(row.price_per_kg).toFixed(2)"></span>
                            <span class="card-price-unit">per kg</span>
                        </div>
                        <div class="card-meta">
                            <div class="card-stock">
                                <i class="fas fa-box" style="color:#64748b;"></i>
                                <span x-text="parseFloat(row.stock_kg).toFixed(1) + ' kg available'" :style="row.stock_kg < 20 ? 'color:var(--red)' : 'color:#475569'"></span>
                            </div>
                            <div class="card-confirmed">
                                <i class="fas fa-circle-check"></i>
                                Confirmed
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ── BY VENDOR VIEW ──────────────────────────────────── --}}
    <div x-show="view === 'vendor'">
        <template x-if="filtered.length === 0">
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-store"></i></div>
                <h3>No vendors found</h3>
                <p>No confirmed price entries match your current filters.</p>
            </div>
        </template>
        <template x-for="vendor in byVendor()" :key="vendor.id">
            <div class="vendor-section">
                <div class="vendor-header">
                    <div class="vendor-avatar" x-text="initials(vendor.name)"></div>
                    <div class="vendor-info">
                        <div class="vname" x-text="vendor.name"></div>
                        <div class="vstall">Stall <span x-text="vendor.stall"></span></div>
                    </div>
                    <span class="vendor-entry-count" x-text="vendor.entries.length + ' item' + (vendor.entries.length !== 1 ? 's' : '')"></span>
                </div>
                <div class="vendor-items">
                    <template x-for="row in vendor.entries" :key="row.id">
                        <div class="vendor-item">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <template x-if="row.fish_image">
                                    <img :src="row.fish_image" :alt="row.fish_name"
                                         style="width:38px; height:38px; border-radius:8px; object-fit:cover; border:1px solid #e2e8f0; flex-shrink:0;">
                                </template>
                                <template x-if="!row.fish_image">
                                    <div style="width:38px; height:38px; border-radius:8px; background:var(--ocean-light); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                        <i class="fas fa-fish" style="color:var(--ocean); font-size:14px;"></i>
                                    </div>
                                </template>
                                <div>
                                    <div class="fish-name" x-text="row.fish_name"></div>
                                    <span class="class-pill" :class="classPillClass(row.quality_class)" x-text="row.quality_class" style="margin-top:4px; display:inline-block;"></span>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.75rem; color:#64748b;">Stock</div>
                                <div class="stock-val" :class="row.stock_kg < 20 ? 'stock-low' : ''" x-text="parseFloat(row.stock_kg).toFixed(1) + ' kg'"></div>
                            </div>
                            <div style="text-align:right; min-width:90px;">
                                <div style="font-size:0.75rem; color:#64748b;">Price/kg</div>
                                <div class="price-val" style="font-size:1.1rem;">₱<span x-text="parseFloat(row.price_per_kg).toFixed(2)"></span></div>
                            </div>
                            <div>
                                <i class="fas fa-circle-check" style="color:#16a34a; font-size:16px;" title="Confirmed"></i>
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
════════════════════════════════════════════════════════════════ --}}
<footer class="page-footer">
    <strong>Virac Public Market</strong> — Commodity Supply Projection &amp; Price Monitoring System
    &nbsp;|&nbsp; Prices are verified by Market Staff &amp; updated daily
    &nbsp;|&nbsp; Catanduanes State University &copy; {{ date('Y') }}
</footer>

{{-- ═══════════════════════════════════════════════════════════
     ALPINE.JS — CLIENT-SIDE FILTERING / SORTING / VIEW
════════════════════════════════════════════════════════════════ --}}

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

<script src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.13.3/cdn.min.js" defer></script>
<script>
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

            classPillClass(cls) {
                const map = {
                    'First Class':   'first',
                    'Second Class':  'second',
                    'Third Class':   'third',
                    'Fourth Class':  'fourth',
                    'Special Class': 'special',
                };
                return map[cls] ?? 'first';
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