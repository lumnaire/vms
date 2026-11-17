<section id="sales-tariffs" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">03 · Pesos, prices &amp; comparisons</div>
    <h2>Sales &amp; tariffs</h2>
    <p class="calc-lead">Sales measure money attributed to fish recorded as sold. The official tariff is the reference price used to show how much a vendor charges above or below it.</p>
    <div class="calc-grid">
        <article class="calc-card"><h3>Sales value</h3><div class="calc-equation">Sales = Σ(sold release kg × batch price)<br>12 kg × ₱200/kg = <strong>₱2,400</strong></div><p class="calc-small">Sum sold releases by their recorded date. An unsold pull-out earns no sales value. Fish submitted yesterday and sold today contributes supply yesterday and sales today.</p></article>
        <article class="calc-card"><h3>Markup per kilogram</h3><div class="calc-equation">Markup = vendor price − tariff<br>₱200 − ₱180 = <strong>₱20/kg</strong><br>Markup % = 20 / 180 × 100 = <strong>11.1%</strong></div><p class="calc-small">The percentage uses the tariff as its denominator. Pricing above the tariff is recorded and allowed; it does not automatically reject a batch.</p></article>
        <article class="calc-card"><h3>Tubo on fish sold</h3><div class="calc-equation">Tubo = sold kg × (batch price − saved tariff)<br>12 × (200 − 180) = <strong>₱240</strong></div><p class="calc-small">This is the markup over the tariff attributable to sold fish. It can be negative below the tariff. A batch without a saved tariff contributes 0 to the system’s tubo total.</p></article>
        <article class="calc-card"><h3>Above, at or below?</h3><div class="calc-equation">Difference = round(price − tariff, 2)<br>Positive → Above tariff<br>Zero → At tariff<br>Negative → Below tariff</div><p class="calc-small">Currency differences are rounded to 2 decimals; comparisons use a ±0.004 tolerance. The percentage is rounded to 1 decimal. A missing or nonpositive tariff gives no comparison.</p></article>
    </div>
    <div class="calc-note calc-note-warm"><strong>Tubo is not accounting profit.</strong> The system does not subtract acquisition cost, transport, ice, spoilage expenses, rent or wages.
        A municipal tariff is a reference selling price, not the vendor’s measured cost. Do not present this number as net profit.</div>
    <h3>Current tariff versus the tariff saved on a batch</h3>
    <p>The public board compares current asking prices with the <strong>current official tariff</strong>.
        A batch also saves the tariff that was in force when it was submitted. Historical markup and tubo use that saved value, so a later tariff change does not rewrite those figures.</p>
    <h3>How averages differ across the system</h3>
    <div class="calc-table-wrap"><table class="calc-table">
        <caption>Use the formula for the page you are interpreting</caption>
        <thead><tr><th scope="col">Figure</th><th scope="col">Formula</th><th scope="col">Interpretation</th></tr></thead>
        <tbody>
            <tr><td>Forecast daily price; board average; supervisor report average price</td><td>ΣP / number of batches</td><td>Each eligible batch price has equal weight.</td></tr>
            <tr><td>Staff supply report / PDF average price</td><td>Σ(Q × P) / ΣQ</td><td>Bigger incoming batches carry more weight. Zero total supply produces 0.</td></tr>
            <tr><td>Markup leaderboard average ₱/kg</td><td>Σ[Q × (P − T)] / ΣQ</td><td>Weight by incoming kg, using confirmed batches with a positive saved tariff.</td></tr>
            <tr><td>Markup leaderboard average %</td><td>100 × Σ[Q × (P − T) / T] / ΣQ</td><td>Average each batch’s percentage with quantity weights. It is not the ratio of average price to average tariff.</td></tr>
            <tr><td>Supervisor vendor report average markup</td><td>Σ(P − T) / tariff-bearing batch count</td><td>Unweighted mean of batch markup values with a saved tariff.</td></tr>
        </tbody>
    </table></div>
    <p>For the earlier 12 kg at ₱180, 8 kg at ₱190 and 5 kg at ₱200 example, the forecast’s average is
        <strong>₱190/kg</strong>, while the supply report’s weighted average is
        <strong>(12 × 180 + 8 × 190 + 5 × 200) / 25 = ₱187.20/kg</strong>.</p>
    <details class="calc-details"><summary>Worked example: the markup leaderboard</summary><div class="calc-details-body">
        <p>A vendor supplies 10 kg at ₱200 with a ₱180 tariff, and 30 kg at ₱230 with a ₱200 tariff.</p>
        <div class="calc-equation">Weighted markup = (10 × 20 + 30 × 30) / 40 = <strong>₱27.50/kg</strong><br>Weighted percentage = [(10 × 20/180) + (30 × 30/200)] / 40 × 100 = <strong>14.0%</strong><br>Largest batch markup = max(20, 30) = <strong>₱30/kg</strong></div>
        <p class="calc-small">“Batches above tariff” counts eligible batches whose vendor price exceeds the saved tariff. The supervisor leaderboard orders by total tubo from sold releases, then average markup. The staff leaderboard uses average markup, then largest markup, without revealing sales.</p>
    </div></details>
    <details class="calc-details"><summary>How public board prices and vendor listings are combined</summary><div class="calc-details-body">
        <ul class="calc-list">
            <li>A vendor’s batches are grouped by fish and quality. Their remaining kilograms are added, and their lowest-to-highest price is shown as a range.</li>
            <li>For tariff comparison, a stall uses its <strong>highest open-batch price</strong> for that fish. Across the market, min/max prices come from all open batches; the average is an unweighted batch average.</li>
            <li>Stall counts use distinct vendors for that fish. The largest markup flag goes to a stall with the highest asking price only when that price is above the current tariff.</li>
            <li>The markup watch selects the largest percentage markups. These comparisons do not change ARIMA’s forecast, which is fitted from observed history.</li>
        </ul>
    </div></details>
</section>
