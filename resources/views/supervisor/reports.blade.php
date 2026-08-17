@extends('layouts.app')

@section('title', 'Daily Market Report — ' . \Carbon\Carbon::parse($reportDate)->format('M d, Y'))

@push('styles')
<style>
    .report-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        box-shadow: 0 1px 3px 0 rgb(0 0 0/.06);
    }
    .stat-value   { font-size: 1.75rem; font-weight: 700; color: #111827; line-height: 1.2; }
    .stat-label   { font-size: 0.7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; }
    .section-heading {
        font-size: 0.8125rem; font-weight: 600; color: #374151;
        padding: 0.875rem 1.25rem; border-bottom: 1px solid #f3f4f6;
    }
    .rpt-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
    .rpt-table thead th {
        background: #f9fafb; padding: 0.625rem 1rem;
        font-size: 0.7rem; font-weight: 700; letter-spacing: .05em;
        text-transform: uppercase; color: #6b7280;
        border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    .rpt-table thead th:not(:first-child) { text-align: right; }
    .rpt-table thead th:first-child,
    .rpt-table thead th:nth-child(2),
    .rpt-table thead th:nth-child(3),
    .rpt-table thead th:nth-child(4) { text-align: left; }
    .rpt-table tbody tr { border-bottom: 1px solid #f3f4f6; transition: background .1s; }
    .rpt-table tbody tr:hover   { background: #eff6ff; }
    .rpt-table tbody tr:nth-child(even) { background: #f9fafb; }
    .rpt-table tbody tr:nth-child(even):hover { background: #eff6ff; }
    .rpt-table td { padding: 0.625rem 1rem; color: #374151; }
    .rpt-table tfoot td {
        padding: 0.625rem 1rem; background: #f3f4f6;
        border-top: 2px solid #d1d5db; font-weight: 700; color: #111827;
    }
    .badge {
        display: inline-flex; align-items: center;
        padding: 0.125rem 0.5rem; border-radius: 0.25rem;
        font-size: 0.7rem; font-weight: 600;
    }
    .badge-a  { background: #dcfce7; color: #166534; }
    .badge-b  { background: #fef9c3; color: #854d0e; }
    .badge-c  { background: #f3f4f6; color: #374151; }
    .btn-pdf {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: #1e3a8a; color: #fff; padding: 0.5rem 1.125rem;
        border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600;
        border: none; cursor: pointer; transition: background .15s;
    }
    .btn-pdf:hover { background: #1e40af; }
    .btn-pdf:disabled { background: #93c5fd; cursor: not-allowed; }
    .btn-filter {
        display: inline-flex; align-items: center; gap: 0.4rem;
        background: #2563eb; color: #fff; padding: 0.5rem 1rem;
        border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600;
        border: none; cursor: pointer; transition: background .15s;
    }
    .btn-filter:hover { background: #1d4ed8; }
    .date-input {
        border: 1px solid #d1d5db; border-radius: 0.5rem;
        padding: 0.45rem 0.75rem; font-size: 0.8125rem; color: #374151;
        outline: none;
    }
    .date-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px #bfdbfe55; }
    @media print { body * { visibility: hidden; } }
</style>
@endpush

@php
    /* Pre-process data for jsPDF — clean arrays only, no Eloquent overhead */
    $vendorRowsJs = $vendorSummaries->map(fn($v) => [
        $v['vendor']->vendorProfile?->stall_number ?? '—',
        $v['vendor']->name,
        $v['fish_list'] ?: '—',
        number_format($v['total_stock_kg'], 2),
        '₱' . number_format($v['avg_price'] ?? 0, 2),
        (string)$v['total_entries'],
    ])->values()->toArray();

    $priceRowsJs = $priceBreakdown->map(fn($p) => [
        $p['fish_name'] ?? '—',
        'Class ' . $p['quality_class'],
        '₱' . number_format($p['avg_price'], 2),
        '₱' . number_format($p['min_price'], 2),
        '₱' . number_format($p['max_price'], 2),
        number_format($p['total_kg'], 2),
        (string)$p['vendor_count'],
    ])->values()->toArray();
@endphp

@section('content')
<div style="min-height:100vh; background:#f8fafc; padding:1.5rem;">
<div style="max-width:1200px; margin:0 auto;">

    {{-- ── Page Header ───────────────────────────────────────── --}}
    <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div style="font-size:0.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#2563eb; margin-bottom:.25rem;">
                Supervisor Portal
            </div>
            <h1 style="font-size:1.5rem; font-weight:700; color:#111827; margin:0 0 .25rem;">
                Daily Market Report
            </h1>
            <p style="font-size:0.8125rem; color:#6b7280; margin:0;">
                {{ \Carbon\Carbon::parse($reportDate)->format('l, F d, Y') }}
            </p>
        </div>

        <div style="display:flex; flex-wrap:wrap; align-items:center; gap:.625rem;">
            <form method="GET" action="{{ request()->url() }}"
                  style="display:flex; align-items:center; gap:.5rem;">
                <input type="date" name="date" value="{{ $reportDate }}" class="date-input">
                <button type="submit" class="btn-filter">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Filter
                </button>
            </form>

            <button id="btnGeneratePdf" class="btn-pdf" onclick="generateSupervisorPdf()">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span id="pdfBtnText">Generate PDF</span>
            </button>
        </div>
    </div>

    {{-- ── Summary Cards ──────────────────────────────────────── --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.5rem;">
        <div class="report-card" style="padding:1.125rem 1.25rem;">
            <p class="stat-label" style="margin-bottom:.25rem;">Total Market Volume</p>
            <p class="stat-value">{{ number_format($marketTotalKg, 2) }}
                <span style="font-size:.875rem; font-weight:400; color:#6b7280;">kg</span>
            </p>
        </div>
        <div class="report-card" style="padding:1.125rem 1.25rem;">
            <p class="stat-label" style="margin-bottom:.25rem;">Active Vendors Today</p>
            <p class="stat-value">{{ $activeVendors }}</p>
        </div>
        <div class="report-card" style="padding:1.125rem 1.25rem;">
            <p class="stat-label" style="margin-bottom:.25rem;">Fish Types Recorded</p>
            <p class="stat-value">{{ $priceBreakdown->pluck('fish_name')->unique()->count() }}</p>
        </div>
        <div class="report-card" style="padding:1.125rem 1.25rem; border-left:3px solid #2563eb;">
            <p class="stat-label" style="margin-bottom:.25rem;">Report Date</p>
            <p style="font-size:1rem; font-weight:700; color:#1e40af;">
                {{ \Carbon\Carbon::parse($reportDate)->format('M d, Y') }}
            </p>
        </div>
    </div>

    {{-- ── Vendor Sales Summary ────────────────────────────────── --}}
    <div class="report-card" style="margin-bottom:1.5rem; overflow:hidden;">
        <div class="section-heading">
            <svg style="display:inline; width:14px; height:14px; margin-right:6px; vertical-align:-2px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
            </svg>
            Vendor Sales Summary
        </div>
        <div style="overflow-x:auto;">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th style="text-align:center; width:50px;">#</th>
                        <th>Stall No.</th>
                        <th>Vendor Name</th>
                        <th>Fish Types</th>
                        <th>Total Stock (kg)</th>
                        <th>Avg. Price / kg</th>
                        <th>Entries</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendorSummaries as $i => $vs)
                    <tr>
                        <td style="text-align:center; color:#9ca3af; font-size:0.75rem;">{{ $i + 1 }}</td>
                        <td style="font-family:monospace; font-size:.8rem; color:#4b5563;">
                            {{ $vs['vendor']->vendorProfile?->stall_number ?? '—' }}
                        </td>
                        <td style="font-weight:600; color:#111827;">{{ $vs['vendor']->name }}</td>
                        <td style="color:#6b7280; font-size:.775rem;">{{ $vs['fish_list'] ?: '—' }}</td>
                        <td style="text-align:right; font-weight:600;">{{ number_format($vs['total_stock_kg'], 2) }}</td>
                        <td style="text-align:right; color:#374151;">₱{{ number_format($vs['avg_price'] ?? 0, 2) }}</td>
                        <td style="text-align:right; color:#6b7280;">{{ $vs['total_entries'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:2.5rem; color:#9ca3af; font-size:.875rem;">
                            No vendor activity recorded for this date.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
                @if($vendorSummaries->count())
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right; font-size:.75rem; letter-spacing:.05em; text-transform:uppercase;">
                            Market Total
                        </td>
                        <td style="text-align:right;">{{ number_format($marketTotalKg, 2) }} kg</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ── Price Breakdown ─────────────────────────────────────── --}}
    <div class="report-card" style="overflow:hidden;">
        <div class="section-heading">
            <svg style="display:inline; width:14px; height:14px; margin-right:6px; vertical-align:-2px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>
            </svg>
            Price Breakdown by Fish Type &amp; Quality Class
        </div>
        <div style="overflow-x:auto;">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th>Fish Type</th>
                        <th>Quality Class</th>
                        <th>Avg. Price / kg</th>
                        <th>Min. Price / kg</th>
                        <th>Max. Price / kg</th>
                        <th>Total Volume (kg)</th>
                        <th>Vendors</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($priceBreakdown as $i => $pb)
                    <tr>
                        <td style="font-weight:600; color:#111827;">{{ $pb['fish_name'] ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $pb['quality_class'] === 'A' ? 'badge-a' : ($pb['quality_class'] === 'B' ? 'badge-b' : 'badge-c') }}">
                                Class {{ $pb['quality_class'] }}
                            </span>
                        </td>
                        <td style="text-align:right; font-weight:600; color:#111827;">₱{{ number_format($pb['avg_price'], 2) }}</td>
                        <td style="text-align:right; color:#6b7280;">₱{{ number_format($pb['min_price'], 2) }}</td>
                        <td style="text-align:right; color:#6b7280;">₱{{ number_format($pb['max_price'], 2) }}</td>
                        <td style="text-align:right; font-weight:600;">{{ number_format($pb['total_kg'], 2) }}</td>
                        <td style="text-align:right; color:#6b7280;">{{ $pb['vendor_count'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" style="text-align:center; padding:2.5rem; color:#9ca3af; font-size:.875rem;">
                            No price data available for this date.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>{{-- /max-width --}}
</div>{{-- /min-height --}}
@endsection

@push('scripts')
{{-- jsPDF + AutoTable from CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

<script>
(function () {
    /* ─── PHP → JS data ────────────────────────────────────── */
    const REPORT_DATE   = '{{ \Carbon\Carbon::parse($reportDate)->format("F d, Y") }}';
    const MARKET_KG     = '{{ number_format($marketTotalKg, 2) }}';
    const ACTIVE_VENDORS = '{{ $activeVendors }}';
    const FISH_COUNT    = '{{ $priceBreakdown->pluck("fish_name")->unique()->count() }}';
    const GENERATED     = new Date().toLocaleString('en-PH', { dateStyle:'medium', timeStyle:'short' });

    const VENDOR_ROWS = @json($vendorRowsJs);
    const PRICE_ROWS  = @json($priceRowsJs);

    /* ─── Colours ──────────────────────────────────────────── */
    const C_BLUE      = [30,  64, 175];   // header bg
    const C_BLUE_LITE = [219, 234, 254];   // alt row
    const C_WHITE     = [255, 255, 255];
    const C_FOOT_BG   = [241, 245, 249];
    const C_FOOT_TXT  = [30,  64, 175];
    const C_GREY_HDR  = [249, 250, 251];
    const C_LINE      = [209, 213, 219];

    /* ─── Helpers ──────────────────────────────────────────── */
    function drawPageHeader(doc, title) {
        const W = doc.internal.pageSize.getWidth();
        doc.setFillColor(...C_BLUE);
        doc.rect(0, 0, W, 22, 'F');

        doc.setTextColor(...C_WHITE);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(12);
        doc.text('FISH MARKET MANAGEMENT SYSTEM', 14, 9);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.text(title, 14, 16);

        doc.setFont('helvetica', 'italic');
        doc.setFontSize(8);
        doc.text('Report Date: ' + REPORT_DATE, W - 14, 16, { align: 'right' });
    }

    function drawPageFooter(doc, pageNum, pageTotal) {
        const W = doc.internal.pageSize.getWidth();
        const H = doc.internal.pageSize.getHeight();
        doc.setDrawColor(...C_LINE);
        doc.setLineWidth(0.3);
        doc.line(14, H - 11, W - 14, H - 11);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(120, 120, 120);
        doc.text('Generated: ' + GENERATED, 14, H - 6);
        doc.text('CONFIDENTIAL — For authorized personnel only', W / 2, H - 6, { align: 'center' });
        doc.text('Page ' + pageNum + ' of ' + pageTotal, W - 14, H - 6, { align: 'right' });
    }

    function addFootersToAllPages(doc) {
        const total = doc.internal.getNumberOfPages();
        for (let i = 1; i <= total; i++) {
            doc.setPage(i);
            drawPageFooter(doc, i, total);
        }
    }

    /* ─── Main PDF generator ───────────────────────────────── */
    window.generateSupervisorPdf = function () {
        const btn     = document.getElementById('btnGeneratePdf');
        const btnText = document.getElementById('pdfBtnText');
        btn.disabled  = true;
        btnText.textContent = 'Generating…';

        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
            const W   = doc.internal.pageSize.getWidth();   // 297 mm

            /* ══ PAGE 1 ══════════════════════════════════════ */
            drawPageHeader(doc, 'DAILY MARKET REPORT — SUPERVISOR OVERVIEW');

            /* Market Summary mini-table */
            doc.setTextColor(55, 65, 81);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8.5);
            doc.text('MARKET SUMMARY', 14, 30);

            doc.autoTable({
                startY        : 33,
                tableWidth    : 140,
                margin        : { left: 14 },
                head          : [['Indicator', 'Value']],
                body          : [
                    ['Total Market Volume', MARKET_KG + ' kg'],
                    ['Active Vendors',      ACTIVE_VENDORS],
                    ['Fish Types on Record', FISH_COUNT],
                    ['Report Date',         REPORT_DATE],
                ],
                theme         : 'grid',
                headStyles    : {
                    fillColor: C_BLUE, textColor: C_WHITE,
                    fontStyle: 'bold', fontSize: 8, halign: 'left',
                },
                bodyStyles    : { fontSize: 8.5 },
                columnStyles  : {
                    0: { fontStyle: 'bold', cellWidth: 70 },
                    1: { cellWidth: 70, halign: 'right' },
                },
                alternateRowStyles: { fillColor: C_BLUE_LITE },
            });

            /* Vendor Sales Summary table */
            const vsY = doc.lastAutoTable.finalY + 8;

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8.5);
            doc.setTextColor(55, 65, 81);
            doc.text('VENDOR SALES SUMMARY', 14, vsY);

            if (VENDOR_ROWS.length === 0) {
                doc.setFont('helvetica', 'italic');
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text('No vendor activity recorded for this date.', 14, vsY + 5);
            } else {
                const vendorFoot = [['', 'MARKET TOTAL', '', MARKET_KG + ' kg', '', '']];
                doc.autoTable({
                    startY     : vsY + 3,
                    margin     : { left: 14, right: 14 },
                    head       : [['Stall No.', 'Vendor Name', 'Fish Types', 'Total Stock (kg)', 'Avg. Price/kg', 'Entries']],
                    body       : VENDOR_ROWS,
                    foot       : vendorFoot,
                    showFoot   : 'lastPage',
                    theme      : 'striped',
                    headStyles : {
                        fillColor: C_BLUE, textColor: C_WHITE,
                        fontStyle: 'bold', fontSize: 7.5,
                    },
                    bodyStyles : { fontSize: 8 },
                    footStyles : {
                        fillColor: C_FOOT_BG, textColor: C_FOOT_TXT,
                        fontStyle: 'bold', fontSize: 8,
                    },
                    alternateRowStyles: { fillColor: C_BLUE_LITE },
                    columnStyles: {
                        0: { cellWidth: 20,  halign: 'center' },
                        1: { cellWidth: 55 },
                        2: { cellWidth: 'auto' },
                        3: { cellWidth: 38,  halign: 'right' },
                        4: { cellWidth: 32,  halign: 'right' },
                        5: { cellWidth: 20,  halign: 'center' },
                    },
                    didDrawPage: (data) => {
                        if (data.pageNumber > 1) drawPageHeader(doc, 'DAILY MARKET REPORT — SUPERVISOR OVERVIEW (cont.)');
                    },
                });
            }

            /* ══ PAGE 2 — Price Breakdown ════════════════════ */
            doc.addPage();
            drawPageHeader(doc, 'PRICE BREAKDOWN BY FISH TYPE & QUALITY CLASS');

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(8.5);
            doc.setTextColor(55, 65, 81);
            doc.text('PRICE BREAKDOWN BY FISH TYPE & QUALITY CLASS', 14, 30);

            if (PRICE_ROWS.length === 0) {
                doc.setFont('helvetica', 'italic');
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text('No price data available for this date.', 14, 36);
            } else {
                doc.autoTable({
                    startY     : 33,
                    margin     : { left: 14, right: 14 },
                    head       : [['Fish Type', 'Quality Class', 'Avg. Price/kg', 'Min. Price/kg', 'Max. Price/kg', 'Total Volume (kg)', 'Vendors']],
                    body       : PRICE_ROWS,
                    theme      : 'striped',
                    headStyles : {
                        fillColor: C_BLUE, textColor: C_WHITE,
                        fontStyle: 'bold', fontSize: 7.5,
                    },
                    bodyStyles : { fontSize: 8 },
                    alternateRowStyles: { fillColor: C_BLUE_LITE },
                    columnStyles: {
                        0: { cellWidth: 55 },
                        1: { cellWidth: 30, halign: 'center' },
                        2: { cellWidth: 35, halign: 'right' },
                        3: { cellWidth: 35, halign: 'right' },
                        4: { cellWidth: 35, halign: 'right' },
                        5: { cellWidth: 40, halign: 'right' },
                        6: { cellWidth: 20, halign: 'center' },
                    },
                    didDrawPage: (data) => {
                        if (data.pageNumber > 1) drawPageHeader(doc, 'PRICE BREAKDOWN — SUPERVISOR (cont.)');
                    },
                });
            }

            /* Footers on all pages */
            addFootersToAllPages(doc);

            const filename = 'supervisor-report-' + REPORT_DATE.replace(/\s/g, '-') + '.pdf';
            doc.save(filename);
        } finally {
            btn.disabled      = false;
            btnText.textContent = 'Generate PDF';
        }
    };
})();
</script>
@endpush