import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const periods = ['daily', 'monthly', 'yearly'];
const data = Object.fromEntries(periods.map((period, index) => {
    const value = 10 ** index;
    return [period, {
        caption: period,
        labels: [period],
        series: { supply: [value], sold: [value / 2], sales: [value * 150] },
        top_vendors: [{ name: period, stall: 'A-01', kg: value }],
        by_class: { 'First Class': value },
        outcome: { sold: value / 2, pulled_out: value / 4, expired: 0, on_stall: value / 4, sell_through: 66.7 },
    }];
}));

function inlineScript(path, marker) {
    const source = readFileSync(new URL(path, import.meta.url), 'utf8');
    return [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)]
        .map(match => match[1]).find(script => script.includes(marker));
}

function dashboard(saved = null, storageUnavailable = false) {
    const charts = new Map();
    class Chart {
        static defaults = { font: {}, plugins: { legend: { labels: {} }, tooltip: {} } };
        constructor(element, config) {
            this.data = config.data;
            this.updates = 0;
            charts.set(element.id, this);
        }
        update() { this.updates++; }
    }

    const buttons = periods.map(period => ({
        dataset: { period },
        setAttribute(name, value) { this[name] = value; },
        addEventListener(event, handler) { this[event] = handler; },
    }));
    const cards = periods.flatMap(period => Array.from({ length: 3 }, () => ({
        dataset: { supervisorPeriod: period }, hidden: period !== 'monthly',
    })));
    const elements = Object.fromEntries([
        'periodCaption', 'outcomeFoot', 'supplySoldChart', 'salesChart',
        'topVendorsChart', 'classChart', 'outcomeChart',
    ].map(id => [id, { id }]));
    elements.supPeriods = {
        querySelector: selector => buttons.find(button => selector === `[data-period="${button.dataset.period}"]`),
        querySelectorAll: () => buttons,
    };
    const storage = new Map(saved ? [['vpm-period-/supervisor/dashboard-supPeriods', saved]] : []);
    const context = vm.createContext({
        Chart,
        document: {
            getElementById: id => elements[id],
            querySelectorAll: selector => selector === '[data-supervisor-period]' ? cards : [],
        },
        location: { pathname: '/supervisor/dashboard' },
        localStorage: {
            getItem(key) {
                if (storageUnavailable) throw new Error('Storage unavailable');
                return storage.get(key);
            },
            setItem(key, value) {
                if (storageUnavailable) throw new Error('Storage unavailable');
                storage.set(key, value);
            },
        },
    });
    context.window = context;
    vm.runInContext(inlineScript('../../resources/views/partials/chart-kit.blade.php', 'window.VPM'), context);
    vm.runInContext(inlineScript('../../resources/views/supervisor/dashboard.blade.php', 'const DATA')
        .replace('@json($charts)', JSON.stringify(data))
        .replace('@json(App\\Services\\MarketAnalytics::CLASS_COLORS)', JSON.stringify({ 'First Class': '#2563eb' })), context);

    function expectPeriod(period) {
        assert.equal(elements.periodCaption.textContent, data[period].caption);
        assert.deepEqual(cards.filter(card => !card.hidden).map(card => card.dataset.supervisorPeriod), Array(3).fill(period));
        assert.deepEqual(buttons.filter(button => button['aria-pressed'] === 'true').map(button => button.dataset.period), [period]);
        const values = id => JSON.parse(JSON.stringify(charts.get(id).data.datasets.map(dataset => dataset.data)));
        assert.deepEqual(values('supplySoldChart'), [data[period].series.supply, data[period].series.sold]);
        assert.deepEqual(values('salesChart'), [data[period].series.sales]);
        assert.deepEqual(values('topVendorsChart'), [[data[period].top_vendors[0].kg]]);
        assert.deepEqual(values('classChart'), [[data[period].by_class['First Class']]]);
        assert.deepEqual(values('outcomeChart'), [[data[period].outcome.sold, data[period].outcome.pulled_out, 0]]);
        assert.equal(charts.get('salesChart').data.labels[0], period);
    }
    return { expectPeriod, click: period => buttons.find(button => button.dataset.period === period).click() };
}

test('monthly, yearly and daily clicks update cards, captions and all five charts', () => {
    const page = dashboard();
    page.expectPeriod('monthly');
    for (const period of ['yearly', 'daily', 'monthly', 'yearly', 'monthly']) {
        page.click(period);
        page.expectPeriod(period);
    }
});

test('a saved yearly selection is applied on load', () => {
    dashboard('yearly').expectPeriod('yearly');
});

test('a saved quarterly selection falls back to monthly', () => {
    dashboard('quarterly').expectPeriod('monthly');
});

test('filters still work when browser storage is unavailable', () => {
    const page = dashboard(null, true);
    page.expectPeriod('monthly');
    page.click('yearly');
    page.expectPeriod('yearly');
});
