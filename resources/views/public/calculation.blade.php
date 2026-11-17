<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A step-by-step research guide to Virac Market System calculations: ARIMA forecasts, supply, stocks, sales, tariffs, trends and reports.">
    <title>Calculations explained — Virac Public Market</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="calc-page" data-calculation-guide>
@php
    $fmt = fn ($value, $places = 2) => number_format((float) $value, $places, '.', ',');
    $unit = $metric === 'price' ? '₱/kg' : 'kg';
    $sections = [
        'overview' => 'Start here', 'stock-supply' => 'Stock & supply',
        'sales-tariffs' => 'Sales & tariffs', 'arima-method' => 'How ARIMA works',
        'arima-lab' => 'Solve a forecast', 'trends-accuracy' => 'Trends & accuracy',
        'reports' => 'Dashboards & reports', 'glossary' => 'Variable glossary',
        'research-notes' => 'Research notes',
    ];
@endphp
<a href="#guide-content" class="calc-skip">Skip to the calculation guide</a>
<header class="calc-header">
    <div class="calc-header-inner">
        <a href="{{ route('home') }}" class="calc-brand">
            <span class="calc-brand-icon" aria-hidden="true"><x-icon name="bi-water" /></span>
            <span><strong>Virac Public Market</strong><small>Price &amp; supply monitoring</small></span>
        </a>
        <nav class="calc-header-links" aria-label="Main navigation">
            <a href="{{ route('home') }}">Price board</a>
            <a href="{{ route('calculation') }}" aria-current="page">Calculation guide</a>
            @auth
                <a href="{{ route(auth()->user()->role.'.dashboard') }}">My dashboard</a>
            @endauth
        </nav>
    </div>
</header>
<div class="calc-wrap">
    <div class="calc-hero">
        <div>
            <div class="calc-eyebrow">Research companion · Methods &amp; worked examples</div>
            <h1>Every number,<br>explained.</h1>
            <p>From a vendor’s first batch to the next supply forecast. Follow the formulas,
                understand the variables, and work through the same ARIMA calculation used by this system.</p>
        </div>
        <div class="calc-actions">
            <a class="calc-btn calc-btn-primary" href="#arima-lab"><x-icon name="bi-calculator" /> Solve an example</a>
            <button class="calc-btn" type="button" data-guide-print><x-icon name="bi-printer" /> Print / save PDF</button>
        </div>
    </div>
    <div class="calc-layout">
        <aside class="calc-sidebar">
            <p class="calc-sidebar-title">Inside this guide</p>
            <nav aria-label="Calculation topics" data-guide-nav>
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" @if ($loop->first) aria-current="location" @endif><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $label }}</a>
                @endforeach
            </nav>
            <p class="calc-sidebar-note">New to forecasting? Start with stock &amp; supply, then follow the ARIMA worked example. Open the detailed working to check every intermediate value.</p>
        </aside>
        <main id="guide-content" class="calc-content">
            @include('public.calculation.overview')
            @include('public.calculation.commerce')
            @include('public.calculation.method')
            @include('public.calculation.lab')
            @include('public.calculation.interpretation')
            @include('public.calculation.reports')
            @include('public.calculation.reference')
        </main>
    </div>
    <footer class="calc-footer">
        <span>Virac Public Market · Calculation &amp; methodology guide · Application time zone: {{ $settings['timezone'] }}</span>
        <a href="{{ route('home') }}">Return to the live price board →</a>
    </footer>
</div>
</body>
</html>
