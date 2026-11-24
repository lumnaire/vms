<section id="trends-accuracy" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">06 · Read the forecast correctly</div>
    <h2>Up, down, stable—and how accurate?</h2>
    <p class="calc-lead">The line, the overall trend label and the uncertainty range describe different things. Read them together.</p>
    <h3>What determines the trend?</h3>
    <p>Let <strong>B</strong> be the mean of the last {{ $settings['baseline_days'] }} actual observations, and <strong>F</strong> the final forecast in the horizon, rounded to 2 decimals.</p>
    <div class="calc-grid calc-grid-three">
        <article class="calc-card"><span class="calc-trend calc-trend-upward">↗ Upward</span><h3>F &gt; B × (1 + {{ $settings['threshold'] / 100 }})</h3><p class="calc-small">The final estimate is more than {{ $fmt($settings['threshold'], 1) }}% above the recent actual average.</p></article>
        <article class="calc-card"><span class="calc-trend calc-trend-downward">↘ Downward</span><h3>F &lt; B × (1 − {{ $settings['threshold'] / 100 }})</h3><p class="calc-small">The final estimate is more than {{ $fmt($settings['threshold'], 1) }}% below the recent actual average.</p></article>
        <article class="calc-card"><span class="calc-trend">→ Stable</span><h3>Between the thresholds</h3><p class="calc-small">The final estimate stays inside the band, including equality at either boundary. Stable does not mean every forecast value is identical.</p></article>
    </div>
    <div class="calc-equation">With B = 100, the active stable band is {{ $fmt(100 * (1 - $settings['threshold'] / 100)) }} to {{ $fmt(100 * (1 + $settings['threshold'] / 100)) }} inclusive.<br>F = {{ $fmt(100 * (1 + $settings['threshold'] / 100) + 2) }} → upward<br>F = {{ $fmt(max(0, 100 * (1 - $settings['threshold'] / 100) - 2)) }} → downward<br>F = 100.00 → stable</div>
    <ul class="calc-list">
        <li>All rows share the <strong>same overall label</strong>. A day-to-day dip can occur within an upward forecast, and vice versa.</li>
        <li>The comparison uses the final forecast against <strong>recent actual history</strong>, not forecast day 1 against forecast day 3.</li>
        <li>The threshold is fractional: {{ $settings['threshold'] / 100 }} means {{ $fmt($settings['threshold'], 1) }}%, not {{ $settings['threshold'] / 100 }}%.</li>
        <li>Do not infer direction from the sign of φ or θ alone. The final forecast combines the drift, AR term, MA term and previous level.</li>
        <li>If B = 0, a positive F is upward and F = 0 is stable. Percentage change is undefined; the value comparisons still work.</li>
        <li>For price, upward means a higher estimated ₱/kg. For supply, upward means more incoming kg. These are separate models; one does not cause the other inside this implementation.</li>
    </ul>
    <h3>Highest and lowest forecast values</h3>
    <p>The highest and lowest cards compare only the published forecast values for the selected future dates.
        They exclude historical values, the average reference line and the prediction-range boundaries.</p>
    <div class="calc-equation">Example future values: 100, 110, 90<br>Highest forecast = max(100, 110, 90) = <strong>110</strong><br>Lowest forecast = min(100, 110, 90) = <strong>90</strong></div>
    <p class="calc-small">A historical value of 300 or an upper range boundary of 130 does not change that highest forecast of 110.
        The graph joins daily points with straight segments and labels the same High/Low values used by the cards and table.
        The shaded band describes the separate prediction range.</p>
    <h3>The forecast table’s Δ Change column</h3>
    <div class="calc-equation">First displayed Δ = first prediction − last real actual visible in the historical chart<br>Later displayed Δ = current prediction − previous prediction</div>
    <p>This is a difference in {{ $metric === 'price' ? '₱/kg' : 'kg' }}, not a percentage. If the historical chart has no actual value, the first comparison is unavailable.
        For example, a last actual of 110 followed by forecasts of 113.41 and 112.63 gives <strong>+3.41</strong> and <strong>−0.78</strong>.
        Those local movements can coexist with an overall upward trend. The price forecast’s “vs Tariff” column compares each predicted price with the current tariff using the same tariff formula described earlier.</p>
    <h3>Why is there a prediction range?</h3>
    <div class="calc-equation">Half-width at model step h = {{ $settings['z'] }} × √Vₕ<br>
        Lower = max(0, raw prediction − half-width)<br>Upper = max(0, raw prediction + half-width)</div>
    <p>Vₕ is calculated from the fitted AR/MA impulse responses and filtered-state uncertainty. It is not simply σ²h.
        Intervals assume the fitted Gaussian model, condition on its parameters, and omit parameter-estimation uncertainty.
        Published bounds are clipped at zero. Coverage on real market data must be evaluated separately.</p>
    <h3>Forecast accuracy: mean absolute percentage error (MAPE)</h3>
    <p>Once a target day has passed, the system can compare an earlier prediction with the actual recorded daily value.</p>
    <div class="calc-equation">Absolute percentage error = |prediction − actual| / actual × 100<br>MAPE = sum of eligible daily percentage errors / eligible day count</div>
    <div class="calc-table-wrap"><table class="calc-table">
        <caption>Worked accuracy example</caption>
        <thead><tr><th scope="col">Day</th><th scope="col" class="calc-num">Actual</th><th scope="col" class="calc-num">Earlier prediction</th><th scope="col">Absolute percentage error</th></tr></thead>
        <tbody><tr><td>A</td><td class="calc-num">100</td><td class="calc-num">110</td><td>|110 − 100| / 100 × 100 = 10%</td></tr><tr><td>B</td><td class="calc-num">200</td><td class="calc-num">180</td><td>|180 − 200| / 200 × 100 = 10%</td></tr><tr><td>Mean</td><td colspan="3"><strong>(10% + 10%) / 2 = 10.0% MAPE</strong></td></tr></tbody>
    </table></div>
    <p class="calc-small">Lower MAPE means smaller percentage errors on the evaluated days. It is not a probability of correctness, and this system does not convert it into an “accuracy = 100 − MAPE” score.</p>
    <p><strong>RMSE</strong> = √mean((prediction − actual)²), and <strong>MAE</strong> = mean(|prediction − actual|), in the selected metric’s units.
        These measures include recorded zero actuals. MAPE excludes zero actuals because its denominator would be zero.
        Scores use only the current model version and training-window setting. Old forecast rows remain archived.</p>
    <p class="calc-small">The latest eligible prediction for each date is used, so these operational scores can combine different forecast leads.
        A research evaluation should also report the leads tested and reserve later observations for testing.</p>
    <h3>Signed error on each historical prediction</h3>
    <div class="calc-equation">Signed error % = (prediction − actual) / actual × 100<br>Prediction 110, actual 100 → +10.0% (overestimate)<br>Prediction 180, actual 200 → −10.0% (underestimate)</div>
    <p class="calc-small">The historical table preserves the sign. Its color uses absolute error: at most 5% is green, above 5% through 15% is amber, and above 15% is red.
        These are display thresholds, separate from the overall trend band and the forecast range. Missing/filled or nonpositive actuals have no signed percentage error.
        MAPE removes the signs before averaging, so overestimates and underestimates cannot cancel out.</p>
    <details class="calc-details"><summary>Which dates can enter MAPE?</summary><div class="calc-details-body">
        <ul class="calc-list">
            <li>The target date must be in the historical chart window and before today.</li>
            <li>The prediction must have been generated <strong>before the start of its target date</strong>. When multiple eligible runs predicted that date, use the latest such run.</li>
            <li>The date must have a real observed daily point, not a chart-filled point, and actual must be greater than 0.</li>
            <li>Average the remaining daily errors and round MAPE to 1 decimal. If there are no eligible pairs, the percentage is unavailable, not 0%.</li>
        </ul>
        <p class="calc-small">MAPE is sensitive to small positive actual values, and excluding zero-actual days means it does not evaluate performance on those dates. Report the eligible day count alongside the error.</p>
    </div></details>
    <h3>Historical chart versus training history</h3>
    <p>The forecast chart displays the last {{ $settings['chart_days'] }} calendar days before today. Within that display, a date without confirmed batches is flagged as filled:
        supply shows 0 kg; price carries forward the latest known daily price, including a prior price from before the window if available.
        With no earlier price, price is blank. If the entire window has no actual batches, the historical overlay is empty.</p>
    <div class="calc-note">These filled chart values are for display. Training uses the most recent consecutive recorded segment ending yesterday. Filled chart values are <strong>not inserted into the fitting series</strong> and are <strong>excluded from MAPE</strong>.
        A chart-filled zero is not proof that data collection established zero supply.</div>
</section>
