<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VPM — @yield('title', 'Dashboard')</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">

    {{-- Typography: one family for the whole product (see @theme in app.css) --}}
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&amp;display=swap" rel="stylesheet">

    {{-- The single icon set. Sizing lives in .vpm-icon-* / <x-icon>. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{-- Design tokens + component classes. Replaces the Tailwind Play CDN,
         which regenerated the whole framework in the browser on every page load. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        #sidebar {
            transition: transform 0.3s ease;
        }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-50">

{{-- Mobile sidebar overlay --}}
<div id="sidebar-overlay"
     class="fixed inset-0 bg-black/50 z-40 lg:hidden hidden"
     onclick="closeSidebar()">
</div>

<div class="flex h-screen overflow-hidden">

    {{-- ═══════════════════════════════════════════════════════ SIDEBAR --}}
    <aside id="sidebar"
           class="vpm-sidebar fixed inset-y-0 left-0 z-50
                  lg:static lg:inset-auto lg:z-auto lg:flex-shrink-0
                  w-64 lg:w-60 flex flex-col overflow-hidden
                  -translate-x-full lg:translate-x-0">

        {{-- Branding --}}
        <div class="px-5 py-4 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex-shrink-0 overflow-hidden bg-white/10 p-0.5">
                    <img src="{{ asset('logo.png') }}" alt="VPM Logo" class="w-full h-full object-contain rounded-lg">
                </div>
                <div class="leading-tight">
                <p class="text-white font-bold text-[12px] tracking-[0.02em]">Virac Public Market</p>
                </div>
                {{-- Close button (mobile only) --}}
                <button onclick="closeSidebar()"
                        class="ml-auto lg:hidden text-white/40 hover:text-white/80 transition-colors">
                    <x-icon name="bi-x-lg" size="xl" class="text-white/60" />
                </button>
            </div>
        </div>

        {{-- Role Badge --}}
        <div class="px-5 py-2.5 border-b border-white/10">
            @php $role = auth()->user()->role; @endphp

            @if($role === 'supervisor')
                <span class="vpm-role-pill text-amber-300" style="background: rgba(251,191,36,0.15);">
                    <x-icon name="bi-shield-fill-check" size="2xs" /> Supervisor
                </span>
            @elseif($role === 'staff')
                <span class="vpm-role-pill text-emerald-300" style="background: rgba(52,211,153,0.15);">
                    <x-icon name="bi-person-badge-fill" size="2xs" /> Market Staff
                </span>
            @else
                <span class="vpm-role-pill text-blue-300" style="background: rgba(96,165,250,0.15);">
                    <x-icon name="bi-shop" size="2xs" /> Vendor
                </span>
            @endif
        </div>

        {{-- Navigation --}}
        <nav class="vpm-sidebar-nav flex-1 px-3 py-4 overflow-y-auto">

            <p class="vpm-nav-heading">
                Main Menu
            </p>

            {{-- ── Supervisor Nav ── --}}
            @if($role === 'supervisor')
                <a href="{{ route('supervisor.dashboard') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.dashboard') ? 'is-active' : '' }}">
                    <x-icon name="bi-speedometer2" class="vpm-nav-icon" /> Dashboard
                </a>
                <a href="{{ route('supervisor.vendors.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.vendors.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-people" class="vpm-nav-icon" /> Vendors
                </a>
                <a href="{{ route('supervisor.staff.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.staff.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-person-badge" class="vpm-nav-icon" /> Staff
                </a>

                <p class="vpm-nav-heading">
                    Configuration
                </p>

                <a href="{{ route('supervisor.fish-types.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.fish-types.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-water" class="vpm-nav-icon" /> Fish Types
                </a>
                <a href="{{ route('supervisor.price-guides.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.price-guides.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-tags" class="vpm-nav-icon" /> Price Guides
                </a>

                <p class="vpm-nav-heading">
                    Analytics
                </p>

                <a href="{{ route('supervisor.forecasts.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.forecasts.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-graph-up-arrow" class="vpm-nav-icon" /> Forecasts
                </a>
                <a href="{{ route('supervisor.reports.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.reports.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-file-earmark-bar-graph" class="vpm-nav-icon" /> Reports
                </a>
                <a href="{{ route('supervisor.sale-reports.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.sale-reports.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-clipboard-data" class="vpm-nav-icon" /> Sale Reports
                </a>

                <p class="vpm-nav-heading">
                    Account
                </p>

                <a href="{{ route('supervisor.account.edit') }}"
                   class="vpm-nav-link {{ request()->routeIs('supervisor.account.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-person-gear" class="vpm-nav-icon" /> My Account
                </a>

            {{-- ── Staff Nav ── --}}
            @elseif($role === 'staff')
                <a href="{{ route('staff.dashboard') }}"
                   class="vpm-nav-link {{ request()->routeIs('staff.dashboard') ? 'is-active' : '' }}">
                    <x-icon name="bi-speedometer2" class="vpm-nav-icon" /> Dashboard
                </a>
                <a href="{{ route('staff.confirmations.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('staff.confirmations.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-check2-circle" class="vpm-nav-icon" /> Confirmations
                </a>
                <a href="{{ route('staff.vendors.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('staff.vendors.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-people" class="vpm-nav-icon" /> Vendors
                </a>

                <p class="vpm-nav-heading">
                    Records
                </p>

                <a href="{{ route('staff.price-guides.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('staff.price-guides.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-tags" class="vpm-nav-icon" /> Price Guide
                </a>
                <a href="{{ route('staff.reports.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('staff.reports.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-file-earmark-text" class="vpm-nav-icon" /> Reports
                </a>
                <a href="{{ route('staff.sale-reports.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('staff.sale-reports.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-clipboard-data" class="vpm-nav-icon" /> Sale Reports
                </a>

            {{-- ── Vendor Nav ── --}}
            @else
                <a href="{{ route('vendor.dashboard') }}"
                   class="vpm-nav-link {{ request()->routeIs('vendor.dashboard') ? 'is-active' : '' }}">
                    <x-icon name="bi-speedometer2" class="vpm-nav-icon" /> Dashboard
                </a>
                <a href="{{ route('vendor.inventory.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('vendor.inventory.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-box-seam" class="vpm-nav-icon" /> My Inventory
                </a>
                <a href="{{ route('vendor.my-stock.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('vendor.my-stock.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-boxes" class="vpm-nav-icon" /> My Stock
                </a>
                <a href="{{ route('vendor.sale-report.index') }}"
                   class="vpm-nav-link {{ request()->routeIs('vendor.sale-report.*') ? 'is-active' : '' }}">
                    <x-icon name="bi-clipboard-check" class="vpm-nav-icon" /> Sale Report
                </a>
            @endif

        </nav>

        {{-- User Info + Logout --}}
        <div class="px-4 py-3.5 border-t border-white/10">
            <div class="flex items-center gap-3">
                {{-- Avatar --}}
                <div class="w-8 h-8 rounded-full vpm-avatar flex items-center justify-center flex-shrink-0">
                    <x-icon name="bi-person-fill" size="sm" class="text-blue-300" />
                </div>

                @if($role === 'supervisor')
                    <a href="{{ route('supervisor.account.edit') }}" class="flex-1 min-w-0 hover:opacity-80 transition-opacity" title="Manage my account">
                        <p class="vpm-user-name truncate">{{ auth()->user()->name }}</p>
                        <p class="vpm-user-handle truncate">{{ auth()->user()->username }}</p>
                    </a>
                @else
                    <div class="flex-1 min-w-0">
                        <p class="vpm-user-name truncate">{{ auth()->user()->name }}</p>
                        <p class="vpm-user-handle truncate">{{ auth()->user()->username }}</p>
                    </div>
                @endif

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            title="Logout"
                            class="text-white/30 hover:text-red-400 transition-colors leading-[1]"
                           >
                        <x-icon name="bi-box-arrow-right" size="xl" />
                    </button>
                </form>
            </div>
        </div>

    </aside>

    {{-- ═══════════════════════════════════════════ MAIN AREA --}}
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

        {{-- Top Header --}}
        <header class="vpm-header">
            <div class="flex items-center justify-between gap-3">

                {{-- Left: Hamburger + Title --}}
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Hamburger (mobile only) --}}
                    <button onclick="openSidebar()"
                            class="lg:hidden flex-shrink-0 p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                        <x-icon name="bi-list" size="2xl" />
                    </button>

                    <div class="min-w-0">
                        <h1 class="vpm-page-title truncate">
                            @yield('title', 'Dashboard')
                        </h1>
                        <p class="text-slate-400 hidden sm:block text-[11.5px] mt-px">
                            @yield('subtitle', 'Virac Public Market · Catanduanes State University')
                        </p>
                    </div>
                </div>

                {{-- Right: Date/Time (hidden on mobile) --}}
                <div class="hidden md:flex items-center gap-3 flex-shrink-0">
                    <div class="text-right">
                        <p id="header-date" class="text-slate-600 font-medium text-[12px]">
                            {{ now()->format('l, F j, Y') }}
                        </p>
                        <p id="header-time" class="text-slate-400 text-right text-[11px]">
                            {{ now()->setTimezone('Asia/Manila')->format('g:i:s A') }} PHT
                        </p>
                    </div>
                    <div class="vpm-divider"></div>
                   
                </div>

            </div>
        </header>

        {{-- Page Content --}}
        <main class="vpm-main">
            <div class="max-w-screen-2xl mx-auto">
                @yield('content')
            </div>
        </main>

    </div>

</div>

<script>
    // ── Live clock (Asia/Manila / PHT) ──────────────────────────
    (function () {
        const dateEl = document.getElementById('header-date');
        const timeEl = document.getElementById('header-time');

        const DATE_FMT = {
            timeZone: 'Asia/Manila',
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        };
        const TIME_FMT = {
            timeZone: 'Asia/Manila',
            hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true
        };

        function tick() {
            const now = new Date();
            if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', DATE_FMT);
            if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', TIME_FMT) + ' PHT';
        }

        tick(); // run immediately so there's no 1-second blank flash
        setInterval(tick, 1000);
    })();

    // ── Sidebar toggle ──────────────────────────────────────────
    function openSidebar() {
        document.getElementById('sidebar').classList.remove('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        document.getElementById('sidebar').classList.add('-translate-x-full');
        document.getElementById('sidebar-overlay').classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Close sidebar when a nav link is clicked on mobile
            document.querySelectorAll('#sidebar .vpm-nav-link').forEach(link => {

        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) closeSidebar();
        });
    });
</script>

@stack('scripts')
</body>
</html>