// Presentation-only pagination: all records stay in the document and reappear on
// mobile, without changing dashboard queries, totals or chart datasets.
const laptop = window.matchMedia('(min-width: 1024px)');

// A side legend keeps every stock/quality label visible in short laptop plots.
// Chart data and tooltips retain their original labels and values.
document.querySelectorAll('.dashboard canvas').forEach((canvas) => {
    const chart = window.Chart?.getChart(canvas);
    if (!chart) return;
    if (chart.options.indexAxis === 'y') {
        const ticks = chart.options.scales.y.ticks;
        const callback = ticks.callback;
        const autoSkip = ticks.autoSkip;
        const font = ticks.font;
        ticks.callback = function (value, index, values) {
            const label = this.getLabelForValue(value);
            if (laptop.matches) return label.length > 12 ? label.slice(0, 11) + '…' : label;
            return callback ? callback.call(this, value, index, values) : label;
        };
        const layoutLabels = () => {
            ticks.autoSkip = laptop.matches ? false : autoSkip;
            ticks.font = laptop.matches ? { ...font, size: 9 } : font;
            chart.update('none');
        };
        laptop.addEventListener('change', layoutLabels);
        layoutLabels();
    }
    if (chart.config.type !== 'doughnut') return;
    const legend = chart.options.plugins.legend;
    const position = legend.position;
    const padding = legend.labels.padding;
    const font = legend.labels.font;
    function layoutLegend() {
        legend.position = laptop.matches ? 'right' : position;
        legend.labels.padding = laptop.matches ? 6 : padding;
        legend.labels.font = laptop.matches ? { ...font, size: 9 } : font;
        chart.update('none');
    }
    laptop.addEventListener('change', layoutLegend);
    layoutLegend();
});

document.querySelectorAll('.dashboard [data-dashboard-page-size]').forEach((list) => {
    const items = Array.from(list.children);
    const size = Number(list.dataset.dashboardPageSize);
    if (items.length <= size) return;

    let page = 0;
    const pages = Math.ceil(items.length / size);
    const nav = document.createElement('nav');
    nav.className = 'dashboard-pager';
    const table = list.closest('table');
    const panel = list.closest('.vpm-card, .dashboard-batches, .dashboard-queue, .dashboard-repeats');
    const label = list.getAttribute('aria-label') || panel?.querySelector('h2')?.textContent.trim() || 'Dashboard records';
    nav.setAttribute('aria-label', `${label} pages`);
    const previous = document.createElement('button');
    previous.type = 'button';
    previous.textContent = 'Previous';
    previous.setAttribute('aria-label', `Previous page of ${label}`);
    const status = document.createElement('span');
    status.setAttribute('aria-live', 'polite');
    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = 'Next';
    next.setAttribute('aria-label', `Next page of ${label}`);
    nav.append(previous, status, next);
    (table || list).after(nav);

    function render() {
        items.forEach((item, index) => {
            item.hidden = laptop.matches && Math.floor(index / size) !== page;
        });
        nav.hidden = !laptop.matches;
        status.textContent = `${page + 1} / ${pages} · ${items.length} records`;
        previous.disabled = page === 0;
        next.disabled = page === pages - 1;
    }
    previous.addEventListener('click', () => { page = Math.max(0, page - 1); render(); });
    next.addEventListener('click', () => { page = Math.min(pages - 1, page + 1); render(); });
    laptop.addEventListener('change', render);
    render();
});
