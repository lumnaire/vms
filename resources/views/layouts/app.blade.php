<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VPM — @yield('title', 'Dashboard')</title>
    <link rel="icon" href="{{ asset('logo.png') }}" type="image/png">

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    {{-- Bootstrap Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    {{--
        Tailwind CSS via CDN.
        NOTE: If you already have Tailwind set up via Vite/npm,
        remove this CDN script and keep your @vite() directive instead.
    --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        navy: {
                            950: '#060f1f',
                            900: '#0a1f3c',
                            800: '#0f2d5e',
                            700: '#14407f',
                        },
                    },
                }
            }
        }
    </script>

    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }

        /* Sidebar scrollbar */
        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-track { background: transparent; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 99px; }

        /* Nav item transitions */
        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            border-radius: 8px;
            margin-bottom: 2px;
            color: rgba(255,255,255,0.6);
            font-size: 13.5px;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
            border-left: 3px solid transparent;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.9);
        }
        .nav-link.active {
            background: rgba(59,130,246,0.2);
            color: #fff;
            border-left-color: #60a5fa;
        }
        .nav-link .nav-icon {
            font-size: 15px;
            width: 18px;
            text-align: center;
            flex-shrink: 0;
        }

        /* Stat card hover */
        .stat-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        /* Sidebar slide transition */
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
           class="fixed inset-y-0 left-0 z-50
                  lg:static lg:inset-auto lg:z-auto lg:flex-shrink-0
                  w-64 lg:w-60 flex flex-col overflow-hidden
                  -translate-x-full lg:translate-x-0"
           style="background: linear-gradient(175deg, #0a1f3c 0%, #0d2a52 60%, #0f2d5e 100%); box-shadow: 4px 0 20px rgba(0,0,0,0.25);">

        {{-- Branding --}}
        <div class="px-5 py-4 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex-shrink-0 overflow-hidden bg-white/10 p-0.5">
                    <img src="{{ asset('logo.png') }}" alt="VPM Logo" class="w-full h-full object-contain rounded-lg">
                </div>
                <div class="leading-tight">
                    <p class="text-white font-bold" style="font-size: 12px; letter-spacing: 0.02em;">Virac Public Market</p>
                    <p class="text-blue-300" style="font-size: 10px; font-weight: 500;">Price Monitoring System</p>
                </div>
                {{-- Close button (mobile only) --}}
                <button onclick="closeSidebar()"
                        class="ml-auto lg:hidden text-white/40 hover:text-white/80 transition-colors">
                    <i class="bi bi-x-lg" style="font-size: 16px;"></i>
                </button>
            </div>
        </div>

        {{-- Role Badge --}}
        <div class="px-5 py-2.5 border-b border-white/10">
            @php $role = auth()->user()->role; @endphp

            @if($role === 'supervisor')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-amber-300"
                      style="background: rgba(251,191,36,0.15); font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">
                    <i class="bi bi-shield-fill-check" style="font-size: 9px;"></i> Supervisor
                </span>
            @elseif($role === 'staff')
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-emerald-300"
                      style="background: rgba(52,211,153,0.15); font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">
                    <i class="bi bi-person-badge-fill" style="font-size: 9px;"></i> Market Staff
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-blue-300"
                      style="background: rgba(96,165,250,0.15); font-size: 10px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;">
                    <i class="bi bi-shop" style="font-size: 9px;"></i> Vendor
                </span>
            @endif
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 overflow-y-auto sidebar-nav">

            <p style="font-size: 9.5px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.3); padding: 0 12px; margin-bottom: 8px;">
                Main Menu
            </p>

            {{-- ── Supervisor Nav ── --}}
            @if($role === 'supervisor')
                <a href="{{ route('supervisor.dashboard') }}"
                   class="nav-link {{ request()->routeIs('supervisor.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 nav-icon"></i> Dashboard
                </a>
                <a href="{{ route('supervisor.vendors.index') }}"
                   class="nav-link {{ request()->routeIs('supervisor.vendors.*') ? 'active' : '' }}">
                    <i class="bi bi-people nav-icon"></i> Vendors
                </a>
                <a href="{{ route('supervisor.staff.index') }}"
                   class="nav-link {{ request()->routeIs('supervisor.staff.*') ? 'active' : '' }}">
                    <i class="bi bi-person-badge nav-icon"></i> Staff
                </a>

                <p style="font-size: 9.5px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.3); padding: 0 12px; margin: 14px 0 8px;">
                    Configuration
                </p>

                <a href="{{ route('supervisor.fish-types.index') }}"
                   class="nav-link {{ request()->routeIs('supervisor.fish-types.*') ? 'active' : '' }}">
                    <i class="bi bi-water nav-icon"></i> Fish Types
                </a>
                <a href="{{ route('supervisor.price-guides.index') }}"
                   class="nav-link {{ request()->routeIs('supervisor.price-guides.*') ? 'active' : '' }}">
                    <i class="bi bi-tags nav-icon"></i> Price Guides
                </a>

                <p style="font-size: 9.5px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.3); padding: 0 12px; margin: 14px 0 8px;">
                    Analytics
                </p>

                <a href="{{ route('supervisor.forecasts.index') }}"
                   class="nav-link {{ request()->routeIs('supervisor.forecasts.*') ? 'active' : '' }}">
                    <i class="bi bi-graph-up-arrow nav-icon"></i> Forecasts
                </a>
                <a href="{{ route('supervisor.reports.index') }}"
                   class="nav-link {{ request()->routeIs('supervisor.reports.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-bar-graph nav-icon"></i> Reports
                </a>

            {{-- ── Staff Nav ── --}}
            @elseif($role === 'staff')
                <a href="{{ route('staff.dashboard') }}"
                   class="nav-link {{ request()->routeIs('staff.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 nav-icon"></i> Dashboard
                </a>
                <a href="{{ route('staff.confirmations.index') }}"
                   class="nav-link {{ request()->routeIs('staff.confirmations.*') ? 'active' : '' }}">
                    <i class="bi bi-check2-circle nav-icon"></i> Confirmations
                </a>
                <a href="{{ route('staff.vendors.index') }}"
                   class="nav-link {{ request()->routeIs('staff.vendors.*') ? 'active' : '' }}">
                    <i class="bi bi-people nav-icon"></i> Vendors
                </a>

                <p style="font-size: 9.5px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.3); padding: 0 12px; margin: 14px 0 8px;">
                    Records
                </p>

                <a href="{{ route('staff.price-guides.index') }}"
                   class="nav-link {{ request()->routeIs('staff.price-guides.*') ? 'active' : '' }}">
                    <i class="bi bi-tags nav-icon"></i> Price Guide
                </a>
                <a href="{{ route('staff.reports.index') }}"
                   class="nav-link {{ request()->routeIs('staff.reports.*') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text nav-icon"></i> Reports
                </a>

            {{-- ── Vendor Nav ── --}}
            @else
                <a href="{{ route('vendor.dashboard') }}"
                   class="nav-link {{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-speedometer2 nav-icon"></i> Dashboard
                </a>
                <a href="{{ route('vendor.inventory.index') }}"
                   class="nav-link {{ request()->routeIs('vendor.inventory.*') ? 'active' : '' }}">
                    <i class="bi bi-box-seam nav-icon"></i> My Inventory
                </a>
            @endif

        </nav>

        {{-- User Info + Logout --}}
        <div class="px-4 py-3.5 border-t border-white/10">
            <div class="flex items-center gap-3">
                {{-- Avatar --}}
                <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                     style="background: rgba(59,130,246,0.25);">
                    <i class="bi bi-person-fill text-blue-300" style="font-size: 13px;"></i>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-white font-semibold truncate" style="font-size: 11.5px;">{{ auth()->user()->name }}</p>
                    <p class="text-white/35 truncate" style="font-size: 10px;">{{ auth()->user()->username }}</p>
                </div>

                {{-- Logout --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            title="Logout"
                            class="text-white/30 hover:text-red-400 transition-colors"
                            style="line-height: 1;">
                        <i class="bi bi-box-arrow-right" style="font-size: 16px;"></i>
                    </button>
                </form>
            </div>
        </div>

    </aside>

    {{-- ═══════════════════════════════════════════ MAIN AREA --}}
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

        {{-- Top Header --}}
        <header class="bg-white border-b border-slate-200 flex-shrink-0"
                style="padding: 14px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.06);">
            <div class="flex items-center justify-between gap-3">

                {{-- Left: Hamburger + Title --}}
                <div class="flex items-center gap-3 min-w-0">
                    {{-- Hamburger (mobile only) --}}
                    <button onclick="openSidebar()"
                            class="lg:hidden flex-shrink-0 p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 transition-colors">
                        <i class="bi bi-list" style="font-size: 20px; line-height: 1;"></i>
                    </button>

                    <div class="min-w-0">
                        <h1 class="text-slate-800 font-bold truncate" style="font-size: 15px;">
                            @yield('title', 'Dashboard')
                        </h1>
                        <p class="text-slate-400 hidden sm:block" style="font-size: 11.5px; margin-top: 1px;">
                            @yield('subtitle', 'Virac Public Market · Catanduanes State University')
                        </p>
                    </div>
                </div>

                {{-- Right: Date/Time (hidden on mobile) --}}
                <div class="hidden md:flex items-center gap-3 flex-shrink-0">
                    <div class="text-right">
                        <p id="header-date" class="text-slate-600 font-medium" style="font-size: 12px;">
                            {{ now()->format('l, F j, Y') }}
                        </p>
                        <p id="header-time" class="text-slate-400 text-right" style="font-size: 11px;">
                            {{ now()->setTimezone('Asia/Manila')->format('g:i:s A') }} PHT
                        </p>
                    </div>
                    <div class="w-px h-8 bg-slate-200"></div>
                    <div class="w-8 h-8 rounded-full bg-navy-800 flex items-center justify-center"
                         style="background: #0f2d5e;">
                        <i class="bi bi-building text-blue-300" style="font-size: 13px;"></i>
                    </div>
                </div>

            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-y-auto" style="padding: 16px; background: #f1f5f9;">
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
    document.querySelectorAll('#sidebar .nav-link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) closeSidebar();
        });
    });
</script>

@stack('scripts')
</body>
</html>