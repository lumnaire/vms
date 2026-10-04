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
        .pb-header-actions { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
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

        /* ── Navbar sign-in ──────────────────────────────────────────────
           The board is the home page and the only place an account holder
           signs in from, so the credentials live in the header rather than
           on a separate page. On translucent navy the inputs need their own
           light treatment rather than the white .vpm-input. */
        .pb-signin { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
        .pb-signin-row { display: flex; align-items: center; gap: 6px; }
        .pb-signin-label {
            color: rgb(255 255 255 / 0.65);
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-right: 2px;
        }
        .pb-signin-input {
            width: 132px;
            padding: 0.4rem 0.6rem;
            border-radius: var(--radius-control);
            border: 1px solid rgb(255 255 255 / 0.25);
            background: rgb(255 255 255 / 0.12);
            color: #fff;
            font-size: 0.78rem;
            outline: none;
            transition: border-color 0.15s ease, background-color 0.15s ease;
        }
        .pb-signin-input::placeholder { color: rgb(255 255 255 / 0.5); }
        .pb-signin-input:focus {
            border-color: rgb(255 255 255 / 0.7);
            background: rgb(255 255 255 / 0.2);
        }
        .pb-signin-input.has-error { border-color: rgb(253 164 175 / 0.9); }
        .pb-signin-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-control);
            background: #fff;
            color: var(--color-navy-800);
            border: none;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: opacity 0.15s ease;
        }
        .pb-signin-btn:hover { opacity: 0.88; }

        /* Shown in place of the form once signed in. */
        .pb-account {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pb-account-text {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            line-height: 1.25;
        }
        .pb-account-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: #fff;
            max-width: 190px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pb-account-role {
            font-size: 0.62rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgb(255 255 255 / 0.6);
        }
        .pb-account-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 0.4rem 0.85rem;
            border-radius: var(--radius-control);
            background: #fff;
            color: var(--color-navy-800);
            border: none;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            text-decoration: none;
            transition: opacity 0.15s ease;
        }
        .pb-account-btn:hover { opacity: 0.88; }
        .pb-account-signout { background: rgb(255 255 255 / 0.14); color: #fff; }

        .pb-signin-error {
            color: rgb(253 164 175);
            font-size: 0.7rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Info tooltip: states who is allowed to sign in. Visible on hover and
           on keyboard focus so it is not mouse-only. */
        .pb-tip-wrap { position: relative; display: inline-flex; }
        .pb-tip-wrap > i { color: rgb(255 255 255 / 0.65); font-size: 0.85rem; cursor: help; }
        .pb-tip-wrap:focus-visible > i { outline: 2px solid #fff; outline-offset: 2px; border-radius: 50%; }
        .pb-tip {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 250px;
            padding: 10px 12px;
            border-radius: var(--radius-control);
            background: var(--color-navy-950);
            color: rgb(255 255 255 / 0.92);
            border: 1px solid rgb(255 255 255 / 0.18);
            box-shadow: 0 10px 30px rgb(0 0 0 / 0.35);
            font-size: 0.72rem;
            line-height: 1.45;
            text-align: left;
            text-transform: none;
            letter-spacing: 0;
            font-weight: 500;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: opacity 0.15s ease, transform 0.15s ease, visibility 0.15s;
            z-index: 30;
            pointer-events: none;
        }
        .pb-tip strong { color: #fff; font-weight: 700; }
        .pb-tip-wrap:hover .pb-tip,
        .pb-tip-wrap:focus-visible .pb-tip,
        .pb-tip-wrap:focus-within .pb-tip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
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

        /* ── Vendor cards ────────────────────────────────────────────────
           One card per vendor, listing every fish they sell today. Each fish
           shows its AM / PM deliveries and what is still for sale across them. */
        .pb-vgrid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1rem;
            align-items: start;
        }
        @media (max-width: 420px) {
            .pb-vgrid { grid-template-columns: 1fr; }
        }
        .pb-vcard { overflow: hidden; }
        .pb-vhead {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.9rem 1.1rem;
            background-image: linear-gradient(135deg, var(--color-navy-800), var(--color-brand-600));
        }
        .pb-vavatar {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-control);
            background: rgb(255 255 255 / 0.16);
            border: 1px solid rgb(255 255 255 / 0.28);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .pb-vname { color: #fff; font-weight: 700; font-size: 0.98rem; line-height: 1.2; }
        .pb-vstall { color: rgb(255 255 255 / 0.72); font-size: 0.74rem; margin-top: 2px; }
        .pb-vtotal { margin-left: auto; text-align: right; flex-shrink: 0; }
        .pb-vtotal-num { color: #fff; font-size: 1.15rem; font-weight: 800; line-height: 1; }
        .pb-vtotal-lbl { color: rgb(255 255 255 / 0.7); font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.07em; margin-top: 3px; }

        .pb-fish { padding: 0.85rem 1.1rem; border-bottom: 1px solid var(--color-slate-100); }
        .pb-fish:last-child { border-bottom: 0; }
        .pb-fish.is-soldout { background: var(--color-surface-subtle); }
        .pb-fish-head { display: flex; align-items: center; gap: 0.65rem; }
        .pb-fish-name { font-weight: 700; color: var(--color-slate-900); font-size: 0.92rem; line-height: 1.2; }
        .pb-fish-price { margin-left: auto; text-align: right; flex-shrink: 0; }
        .pb-fish-price-num { font-size: 1.05rem; font-weight: 800; color: var(--color-slate-900); line-height: 1; }
        .pb-fish-price-unit { font-size: 0.7rem; color: var(--color-slate-400); }

        .pb-lines { margin: 0.6rem 0 0; padding: 0; list-style: none; display: grid; gap: 4px; }
        .pb-line {
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.8rem;
            color: var(--color-slate-600);
            padding: 4px 8px;
            border-radius: var(--radius-control);
            background: var(--color-surface-subtle);
        }
        .pb-line.is-soldout { opacity: 0.55; }
        .pb-line-kg { font-weight: 700; color: var(--color-slate-800); }
        .pb-line-of { color: var(--color-slate-400); font-size: 0.74rem; }
        .pb-line-meta { color: var(--color-slate-400); font-size: 0.72rem; white-space: nowrap; }

        .pb-fish-total {
            display: flex;
            align-items: baseline;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.55rem;
            padding-top: 0.5rem;
            border-top: 1px dashed var(--color-slate-200);
            font-size: 0.78rem;
            color: var(--color-slate-500);
        }
        .pb-fish-total-num { font-size: 0.95rem; font-weight: 800; color: var(--color-success-700); }
        .pb-fish-total-num.is-zero { color: var(--color-danger-600); }

        .pb-session-pill { font-size: 0.66rem; padding: 1px 7px; min-width: 34px; justify-content: center; }
        .pb-soldout-tag {
            font-size: 0.66rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--color-danger-600);
        }
        .pb-legend { font-size: 0.75rem; color: var(--color-slate-500); margin: -0.5rem 0 1rem; }
        [x-cloak] { display: none !important; }

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
            .pb-header-actions { width: 100%; }
            .pb-signin { align-items: flex-start; width: 100%; }
            .pb-signin-row { flex-wrap: wrap; width: 100%; }
            .pb-signin-label { width: 100%; }
            .pb-signin-input { flex: 1; width: auto; min-width: 0; }
            .pb-account { width: 100%; }
            .pb-account-text { align-items: flex-start; }
            .pb-account-name { max-width: 100%; }
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
        <div class="pb-header-actions">
            {{-- ── Sign-in ───────────────────────────────────────────────
                 Only vendor, staff and supervisor accounts have credentials,
                 so the form is hidden from anyone already signed in and the
                 tooltip spells out the rule next to it. --}}
            @guest
            <form method="POST" action="{{ route('login') }}" class="pb-signin" autocomplete="off">
                @csrf
                <div class="pb-signin-row">
                    <span class="pb-signin-label">Sign in</span>
                    <input type="text" name="username"
                           class="pb-signin-input @error('username') has-error @enderror"
                           placeholder="Username"
                           aria-label="Username"
                           value="{{ old('username') }}"
                           required>
                    <input type="password" name="password"
                           class="pb-signin-input @error('username') has-error @enderror"
                           placeholder="Password"
                           aria-label="Password"
                           required>
                    <button type="submit" class="pb-signin-btn">
                        <i class="bi bi-box-arrow-in-right"></i> Log In
                    </button>
                    <span class="pb-tip-wrap" tabindex="0" role="note" aria-describedby="pb-login-tip">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <span class="pb-tip" id="pb-login-tip" role="tooltip">
                            <strong>Vendor, staff and supervisor accounts only.</strong>
                            There is no consumer sign-in &mdash; this price board
                            is public. Signing in here takes you to your own
                            dashboard.
                        </span>
                    </span>
                </div>
                @error('username')
                    <span class="pb-signin-error">
                        <i class="bi bi-exclamation-circle-fill"></i> {{ $message }}
                    </span>
                @elseif(session('auth_intent'))
                    <span class="pb-signin-error">
                        <i class="bi bi-lock-fill"></i> {{ session('auth_intent') }}
                    </span>
                @endif
            </form>
            @endguest

            {{-- ── Already signed in ───────────────────────────────────────
                 The form above is hidden for anyone signed in. Without this the
                 header just went blank, which reads as a broken page rather than
                 as "you are already in" — and left no way back out from here. --}}
            @auth
            @php
                $dashboardRoute = match (\Illuminate\Support\Facades\Auth::user()->role) {
                    'supervisor' => 'supervisor.dashboard',
                    'staff'      => 'staff.dashboard',
                    'vendor'     => 'vendor.dashboard',
                    default      => null,
                };
            @endphp
            <div class="pb-account">
                <div class="pb-account-text">
                    <span class="pb-account-name">{{ \Illuminate\Support\Facades\Auth::user()->name }}</span>
                    <span class="pb-account-role">{{ \Illuminate\Support\Str::title(\Illuminate\Support\Facades\Auth::user()->role) }}</span>
                </div>
                @if($dashboardRoute)
                <a href="{{ route($dashboardRoute) }}" class="pb-account-btn">
                    <i class="bi bi-speedometer2"></i> My Dashboard
                </a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="pb-account-btn pb-account-signout">
                        <i class="bi bi-box-arrow-right"></i> Sign Out
                    </button>
                </form>
            </div>
            @endauth

            <div class="pb-datechip">
                <div class="pb-datechip-label"><span class="pb-livedot"></span>Live Today</div>
                <div class="pb-datechip-value">{{ \Carbon\Carbon::today()->format('F j, Y') }}</div>
            </div>
        </div>
    </div>
</header>


{{-- ═══════════════════════════════════════════════════════════
     STATS BAR
═══════════════════════════════════════════════════════════════ --}}
<div class="pb-stats">
    <div class="pb-stats-inner">
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon-success"><x-icon name="bi-shop-window" /></div>
            <div>
                <div class="pb-stat-val">{{ $stats['vendors'] }}</div>
                <div class="pb-stat-lbl">Vendors Selling</div>
            </div>
        </div>
        <div class="pb-divider"></div>
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon"><x-icon name="bi-tags" /></div>
            <div>
                <div class="pb-stat-val">{{ $stats['listings'] }}</div>
                <div class="pb-stat-lbl">Fish Listings</div>
            </div>
        </div>
        <div class="pb-divider"></div>
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon"><x-icon name="bi-fish" /></div>
            <div>
                <div class="pb-stat-val">{{ $stats['varieties'] }}</div>
                <div class="pb-stat-lbl">Fish Varieties</div>
            </div>
        </div>
        <div class="pb-divider"></div>
        <div class="pb-stat">
            <div class="pb-stat-icon vpm-stat-icon-warning"><x-icon name="bi-box-seam" /></div>
            <div>
                <div class="pb-stat-val">{{ number_format($stats['remaining_kg'], 1) }} kg</div>
                <div class="pb-stat-lbl">Available Now</div>
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
<main class="pb-main" x-data="priceBoard()" x-cloak>

    {{-- Controls Bar --}}
    <div class="pb-controls">
        <div class="pb-search">
            <x-icon name="bi-search" />
            <input type="text"
                   class="vpm-input pl-9"
                   placeholder="Search fish, vendor or stall..."
                   aria-label="Search prices"
                   x-model.debounce.150ms="search">
        </div>

        <select class="vpm-filter-select" x-model="sortBy" aria-label="Sort vendors">
            <option value="vendor">Sort by Vendor Name</option>
            <option value="stock">Most Fish Available</option>
            <option value="price_asc">Lowest Price First</option>
            <option value="stall">Stall Number</option>
        </select>

        {{-- Trading session: what was delivered in the morning, the afternoon, or both. --}}
        <div class="vpm-segment" role="group" aria-label="Trading session">
            <button :aria-pressed="session === ''" @click="session=''" title="Whole day">All Day</button>
            <button :aria-pressed="session === 'AM'" @click="session='AM'" title="Morning deliveries">
                <x-icon name="bi-sunrise-fill" /> AM
            </button>
            <button :aria-pressed="session === 'PM'" @click="session='PM'" title="Afternoon deliveries">
                <x-icon name="bi-sunset-fill" /> PM
            </button>
        </div>

        <div class="vpm-segment" role="group" aria-label="View mode">
            <button :aria-pressed="view === 'vendor'" @click="view='vendor'" title="Vendor cards">
                <x-icon name="bi-grid" />
            </button>
            <button :aria-pressed="view === 'table'" @click="view='table'" title="Table view">
                <x-icon name="bi-list-ul" />
            </button>
        </div>

        <span class="pb-result"
              x-text="vendors.length + ' vendor' + (vendors.length !== 1 ? 's' : '') + ' · ' + listingCount + ' listing' + (listingCount !== 1 ? 's' : '')"></span>
    </div>

    {{-- Quality Class Tabs --}}
    <div class="pb-tabs" role="group" aria-label="Filter by quality class">
        <button class="pb-tab" :class="{ 'is-active': classFilter === '' }" @click="classFilter=''">
            All Classes
        </button>
        @foreach(\App\Models\FishType::QUALITY_CLASSES as $cls)
            <button class="pb-tab"
                    :class="{ 'is-active': classFilter === '{{ $cls }}' }"
                    @click="classFilter='{{ $cls }}'">
                {{ $cls }}
            </button>
        @endforeach
    </div>

    <p class="pb-legend">
        <x-icon name="bi-info-circle" />
        Quantities are what is <strong>still for sale</strong> &mdash; each vendor's declared sales are already taken off.
    </p>

    {{-- ── VENDOR CARDS ────────────────────────────────────── --}}
    <div x-show="view === 'vendor'">
        <template x-if="vendors.length === 0">
            <x-empty-state icon="bi-shop-window"
                           title="No fish on the board right now"
                           text="No confirmed listings match your current filters." />
        </template>

        <div class="pb-vgrid">
            <template x-for="vendor in vendors" :key="vendor.id">
                <article class="vpm-card pb-vcard">
                    <header class="pb-vhead">
                        <div class="pb-vavatar" x-text="initials(vendor.name)"></div>
                        <div class="min-w-0">
                            <div class="pb-vname truncate" x-text="vendor.name"></div>
                            <div class="pb-vstall">
                                Stall <span x-text="vendor.stall"></span>
                                &bull; <span x-text="vendor.fish.length + ' fish'"></span>
                            </div>
                        </div>
                        <div class="pb-vtotal">
                            <div class="pb-vtotal-num" x-text="kg(vendor.remaining_kg)"></div>
                            <div class="pb-vtotal-lbl">available</div>
                        </div>
                    </header>

                    <template x-for="fish in vendor.fish" :key="fish.key">
                        <section class="pb-fish" :class="{ 'is-soldout': fish.remaining_kg <= 0 }">
                            <div class="pb-fish-head">
                                <template x-if="fish.fish_image">
                                    <img :src="fish.fish_image" :alt="fish.fish_name" class="pb-thumb">
                                </template>
                                <template x-if="!fish.fish_image">
                                    <div class="pb-thumb-fallback"><x-icon name="bi-fish" /></div>
                                </template>
                                <div class="min-w-0">
                                    <div class="pb-fish-name" x-text="fish.fish_name"></div>
                                    <span :class="qualityClass(fish.quality_class)" class="mt-1 inline-block" x-text="fish.quality_class"></span>
                                </div>
                                <div class="pb-fish-price">
                                    <div class="pb-fish-price-num" x-text="priceText(fish)"></div>
                                    <div class="pb-fish-price-unit">per kg</div>
                                </div>
                            </div>

                            {{-- Every delivery of this fish today, AM first --}}
                            <ul class="pb-lines">
                                <template x-for="line in fish.lines" :key="line.id">
                                    <li class="pb-line" :class="{ 'is-soldout': line.remaining_kg <= 0 }">
                                        <span class="vpm-badge pb-session-pill"
                                              :class="line.session === 'PM' ? 'vpm-badge-info' : 'vpm-badge-warning'"
                                              x-text="line.session"></span>
                                        <span>
                                            <template x-if="line.remaining_kg <= 0">
                                                <span class="pb-soldout-tag">Sold out</span>
                                            </template>
                                            <template x-if="line.remaining_kg > 0">
                                                <span class="pb-line-kg" x-text="kg(line.remaining_kg)"></span>
                                            </template>
                                            <span class="pb-line-of" x-show="line.sold_kg > 0"
                                                  x-text="'of ' + kg(line.released_kg) + ' · ' + kg(line.sold_kg) + ' sold'"></span>
                                        </span>
                                        <span class="pb-line-meta">
                                            <span x-show="fish.min_price !== fish.max_price" x-text="peso(line.price_per_kg) + ' · '"></span>
                                            <span x-text="line.time"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>

                            <div class="pb-fish-total">
                                <span>
                                    <template x-if="session === '' && fish.am_kg > 0 && fish.pm_kg > 0">
                                        <span x-text="'AM ' + kg(fish.am_kg) + ' + PM ' + kg(fish.pm_kg)"></span>
                                    </template>
                                    <template x-if="!(session === '' && fish.am_kg > 0 && fish.pm_kg > 0)">
                                        <span x-text="session ? session + ' available' : 'Available now'"></span>
                                    </template>
                                </span>
                                <span class="pb-fish-total-num" :class="{ 'is-zero': fish.remaining_kg <= 0 }"
                                      x-text="fish.remaining_kg > 0 ? kg(fish.remaining_kg) : 'Sold out'"></span>
                            </div>
                        </section>
                    </template>
                </article>
            </template>
        </div>
    </div>

    {{-- ── TABLE VIEW: one row per vendor and fish ───────────── --}}
    <div x-show="view === 'table'">
        <div class="vpm-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="vpm-table">
                    <thead>
                        <tr>
                            <th>Fish Type</th>
                            <th>Quality Class</th>
                            <th>Vendor / Stall</th>
                            <th class="vpm-th-right">AM Left</th>
                            <th class="vpm-th-right">PM Left</th>
                            <th class="vpm-th-right">Total Available</th>
                            <th class="vpm-th-right">Price per kg</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-if="tableRows.length === 0">
                            <tr>
                                <td colspan="7">
                                    <x-empty-state icon="bi-fish"
                                                   title="No confirmed prices found for today"
                                                   text="Nothing matches your current filters." />
                                </td>
                            </tr>
                        </template>
                        <template x-for="row in tableRows" :key="row.vendor.id + '_' + row.fish.key">
                            <tr>
                                <td>
                                    <div class="flex items-center gap-2.5">
                                        <template x-if="row.fish.fish_image">
                                            <img :src="row.fish.fish_image" :alt="row.fish.fish_name" class="pb-thumb">
                                        </template>
                                        <template x-if="!row.fish.fish_image">
                                            <div class="pb-thumb-fallback"><x-icon name="bi-fish" /></div>
                                        </template>
                                        <span class="vpm-cell-strong" x-text="row.fish.fish_name"></span>
                                    </div>
                                </td>
                                <td>
                                    <span :class="qualityClass(row.fish.quality_class)" x-text="row.fish.quality_class"></span>
                                </td>
                                <td>
                                    <div class="vpm-cell-muted">
                                        <span x-text="row.vendor.name"></span>
                                        <x-badge variant="neutral" class="ml-1">Stall <span x-text="row.vendor.stall"></span></x-badge>
                                    </div>
                                </td>
                                <td class="vpm-td-right vpm-cell-muted" x-text="row.fish.am_kg > 0 ? kg(row.fish.am_kg) : '—'"></td>
                                <td class="vpm-td-right vpm-cell-muted" x-text="row.fish.pm_kg > 0 ? kg(row.fish.pm_kg) : '—'"></td>
                                <td class="vpm-td-right">
                                    <span class="font-semibold"
                                          :class="row.fish.remaining_kg <= 0 ? 'pb-stock-low' : 'pb-stock-ok'"
                                          x-text="row.fish.remaining_kg > 0 ? kg(row.fish.remaining_kg) : 'Sold out'"></span>
                                </td>
                                <td class="vpm-td-right">
                                    <span class="text-[15px] font-bold text-slate-900" x-text="priceText(row.fish)"></span>
                                    <span class="text-[11.5px] font-normal text-slate-400">/kg</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
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
     The vendor cards are built in PriceboardController; this only narrows
     and reorders them, and re-totals a fish when one session is picked.
═══════════════════════════════════════════════════════════════ --}}
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

    const BOARD_VENDORS = @json($vendors);

    function priceBoard() {
        const round2 = n => Math.round(n * 100) / 100;
        const sum = (arr, key) => round2(arr.reduce((t, x) => t + x[key], 0));

        return {
            all: BOARD_VENDORS,
            search: '',
            sortBy: 'vendor',
            classFilter: '',
            session: '',
            view: 'vendor',

            // Vendor cards after search, class and session filters, re-totalled.
            get vendors() {
                const q = this.search.trim().toLowerCase();

                const out = this.all.map(vendor => {
                    const vendorHit = q && (vendor.name.toLowerCase().includes(q)
                        || String(vendor.stall).toLowerCase().includes(q));

                    const fish = vendor.fish
                        .filter(f => !this.classFilter || f.quality_class === this.classFilter)
                        .filter(f => !q || vendorHit || f.fish_name.toLowerCase().includes(q))
                        .map(f => {
                            const lines = this.session ? f.lines.filter(l => l.session === this.session) : f.lines;
                            const prices = lines.map(l => l.price_per_kg);
                            return {
                                ...f,
                                lines,
                                remaining_kg: sum(lines, 'remaining_kg'),
                                min_price: Math.min(...prices),
                                max_price: Math.max(...prices),
                            };
                        })
                        .filter(f => f.lines.length > 0);

                    return { ...vendor, fish, remaining_kg: sum(fish, 'remaining_kg') };
                }).filter(v => v.fish.length > 0);

                const cheapest = v => Math.min(...v.fish.map(f => f.min_price));
                switch (this.sortBy) {
                    case 'stock':     out.sort((a, b) => b.remaining_kg - a.remaining_kg); break;
                    case 'price_asc': out.sort((a, b) => cheapest(a) - cheapest(b)); break;
                    case 'stall':     out.sort((a, b) => String(a.stall).localeCompare(String(b.stall), undefined, { numeric: true })); break;
                    default:          out.sort((a, b) => a.name.localeCompare(b.name));
                }

                return out;
            },

            get tableRows() {
                return this.vendors.flatMap(vendor => vendor.fish.map(fish => ({ vendor, fish })));
            },

            get listingCount() {
                return this.vendors.reduce((t, v) => t + v.fish.length, 0);
            },

            kg(n) {
                return Number(n).toLocaleString('en-PH', { minimumFractionDigits: 1, maximumFractionDigits: 2 }) + ' kg';
            },

            peso(n) {
                return '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            },

            // One price when every delivery shares it, otherwise the range.
            priceText(fish) {
                return fish.min_price === fish.max_price
                    ? this.peso(fish.min_price)
                    : this.peso(fish.min_price) + '–' + this.peso(fish.max_price).slice(1);
            },

            {{-- Resolves a quality class to the shared .vpm-quality-* tone, the
                 same single source of truth <x-quality-badge> uses. --}}
            qualityClass(cls) {
                return QUALITY_TONES[cls] || 'vpm-quality vpm-quality-neutral';
            },

            initials(name) {
                return name.split(' ').filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
            },
        };
    }
</script>
</body>
</html>
