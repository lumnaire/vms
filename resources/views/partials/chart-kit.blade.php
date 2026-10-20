{{--
    Chart kit shared by the three dashboards: Chart.js, the house styling for
    every chart, and the period toggle.

    Colours: series use the validated categorical order (blue, green, amber,
    purple, red); stock states use status colours (sold green, pulled out amber,
    expired red) and always carry a text label, never colour alone. One y-axis
    per chart: pesos and kilograms are never mixed on the same chart.

    Include once per page inside @push('scripts'), before the page's own script.
--}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
window.VPM = (function () {
    const C = {
        blue: '#2563eb', green: '#059669', amber: '#d97706', purple: '#7e22ce', red: '#dc2626',
        slate: '#94a3b8', grid: 'rgba(226,232,240,0.7)', ink: '#64748b', surface: '#ffffff',
    };
    const FONT = '"Plus Jakarta Sans", sans-serif';

    if (window.Chart) {
        Chart.defaults.font.family = FONT;
        Chart.defaults.font.size = 11;
        Chart.defaults.color = C.ink;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 8;
        Chart.defaults.plugins.legend.labels.boxHeight = 8;
        Chart.defaults.plugins.legend.position = 'bottom';
        Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
        Chart.defaults.plugins.tooltip.titleColor = '#94a3b8';
        Chart.defaults.plugins.tooltip.bodyColor = '#f1f5f9';
        Chart.defaults.plugins.tooltip.padding = 10;
        Chart.defaults.plugins.tooltip.boxPadding = 4;
        Chart.defaults.maintainAspectRatio = false;
    }

    const peso = (n, d = 2) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: d, maximumFractionDigits: d });
    const kg = n => Number(n || 0).toLocaleString('en-PH', { maximumFractionDigits: 1 }) + ' kg';
    const compact = (n, prefix = '') => {
        const v = Math.abs(n);
        if (v >= 1e6) return prefix + (n / 1e6).toFixed(1) + 'M';
        if (v >= 1e3) return prefix + (n / 1e3).toFixed(v >= 1e4 ? 0 : 1) + 'k';
        return prefix + Math.round(n);
    };

    /** Axis + tooltip options for one measure. fmt formats a value for the tooltip. */
    function axes({ prefix = '', fmt = v => v, stacked = false, horizontal = false, legend = true } = {}) {
        const valueAxis = {
            beginAtZero: true,
            stacked,
            grid: { color: C.grid },
            border: { display: false },
            ticks: { callback: v => compact(v, prefix), maxTicksLimit: 6 },
        };
        const catAxis = {
            stacked,
            grid: { display: false },
            border: { display: false },
            ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: horizontal ? 10 : 8 },
        };
        return {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            interaction: { mode: horizontal ? 'nearest' : 'index', intersect: false },
            plugins: {
                legend: { display: legend },
                tooltip: { callbacks: { label: i => ' ' + i.dataset.label + ': ' + fmt(i.parsed[horizontal ? 'x' : 'y']) } },
            },
            scales: horizontal ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis },
        };
    }

    const line = (label, data, color, extra = {}) => ({
        label, data, borderColor: color, backgroundColor: color + '1f', borderWidth: 2,
        pointRadius: 2.5, pointHoverRadius: 5, pointBackgroundColor: color, tension: 0.3, fill: false, ...extra,
    });
    const bar = (label, data, color, extra = {}) => ({
        label, data, backgroundColor: color, borderColor: C.surface, borderWidth: 1,
        borderRadius: 4, borderSkipped: 'start', maxBarThickness: 28, ...extra,
    });

    /** Doughnut with labelled percentages in the tooltip. */
    function doughnut(el, labels, values, colors, fmt = v => v) {
        return new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data: values, backgroundColor: colors, borderColor: C.surface, borderWidth: 2, hoverOffset: 4 }] },
            options: {
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: i => {
                        const total = i.dataset.data.reduce((a, b) => a + b, 0) || 1;
                        return ' ' + i.label + ': ' + fmt(i.parsed) + ' (' + (i.parsed / total * 100).toFixed(1) + '%)';
                    } } },
                },
            },
        });
    }

    /**
     * Wire a .vpm-segment of [data-period] buttons. Calls render(period) now and
     * on every click, and remembers the choice per page for this viewer.
     */
    function periods(groupId, render, fallback = 'monthly') {
        const group = document.getElementById(groupId);
        if (!group) return;
        const key = 'vpm-period-' + location.pathname + '-' + groupId;
        let current = fallback;
        try { current = localStorage.getItem(key) || fallback; } catch (e) {}
        if (!group.querySelector('[data-period="' + current + '"]')) current = fallback;

        const apply = p => {
            group.querySelectorAll('[data-period]').forEach(b => b.setAttribute('aria-pressed', b.dataset.period === p ? 'true' : 'false'));
            render(p);
            try { localStorage.setItem(key, p); } catch (e) {}
        };
        group.querySelectorAll('[data-period]').forEach(b => b.addEventListener('click', () => apply(b.dataset.period)));
        apply(current);
    }

    /** Replace a chart's labels and dataset values in place. */
    function update(chart, labels, values) {
        chart.data.labels = labels;
        values.forEach((v, i) => { chart.data.datasets[i].data = v; });
        chart.update();
    }

    return { C, peso, kg, compact, axes, line, bar, doughnut, periods, update };
})();
</script>
