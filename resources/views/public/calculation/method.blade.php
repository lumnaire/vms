<section id="arima-method" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">04 · The forecasting method</div>
    <h2>How ARIMA works here</h2>
    <p class="calc-lead">ARIMA stands for AutoRegressive Integrated Moving Average. This system uses a custom
        <strong>ARIMA(1,1,1)-style model with drift</strong>: learn the changes in a sequence, then roll those changes forward.</p>
    <div class="calc-grid calc-grid-three">
        <article class="calc-card"><div class="calc-eyebrow">AR · p = 1</div><h3>One previous change</h3><p class="calc-small">The autoregressive term uses the last change relative to the average change. Its coefficient is φ (phi).</p></article>
        <article class="calc-card"><div class="calc-eyebrow">I · d = 1</div><h3>Difference once</h3><p class="calc-small">Subtract each observation from the next. Model the movement instead of directly modeling the original price or supply level.</p></article>
        <article class="calc-card"><div class="calc-eyebrow">MA · q = 1</div><h3>One previous surprise</h3><p class="calc-small">The moving-average term uses the last estimated residual, weighted by θ (theta). “Moving average” here is not a rolling average of prices.</p></article>
    </div>
    <h3>Which data enters the model?</h3>
    <ul class="calc-list">
        <li>Fit a separate series for each <strong>fish type × quality class × metric</strong>. Price is in ₱/kg; supply is in kg. They are never added together.</li>
        <li>Read confirmed batches with entry dates <strong>before today</strong>, inside the last <strong>{{ $settings['history'] }} calendar days</strong>. Today’s partial entries do not enter the training history.</li>
        <li>Group each recorded day into its unweighted average batch price or its total incoming kilograms, then put the days in chronological order.</li>
        <li>Require at least <strong>{{ $settings['minimum'] }} recorded daily observations</strong>, not {{ $settings['minimum'] }} batches. Ten batches on one date still make one observation.</li>
        <li>Generate <strong>{{ $settings['horizon'] }} forecast steps</strong>. Stored target dates start tomorrow, followed by subsequent calendar days.</li>
        <li>The system forecasts <strong>price and incoming supply</strong>. It does not currently forecast demand, sold kilograms, revenue or profit.</li>
    </ul>
    <div class="calc-note calc-note-warm"><strong>Missing dates need careful interpretation.</strong>
        The fitting series contains only dates with confirmed batches; it does not insert missing days or zeros.
        Adjacent observations may therefore be several calendar days apart. The recurrence still treats them as adjacent steps and labels future steps starting tomorrow;
        it does not adjust for gaps between the final observation and those target dates.</div>
    <h3>The full calculation, in order</h3>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">Step</th><th scope="col">Formula used by this system</th><th scope="col">Plain meaning</th></tr></thead>
        <tbody>
            <tr><td>1. Difference</td><td>Δyᵢ = yᵢ − yᵢ₋₁</td><td>How much the observed value moved.</td></tr>
            <tr><td>2. Mean change</td><td>μ = ΣΔyᵢ / m, where m = n − 1</td><td>The drift: the average movement between observations.</td></tr>
            <tr><td>3. Estimate φ</td><td>C(x) = Σ[(xᵢ − x̄)(xᵢ₊₁ − x̄)] / Σ[(xᵢ − x̄)²]<br>φ = clamp(C(Δy), −0.99, 0.99)</td><td>Lag-1 autocorrelation of the differenced sequence.</td></tr>
            <tr><td>4. Residuals</td><td>eᵢ = (Δyᵢ − μ) − φ(Δyᵢ₋₁ − μ)</td><td>The centered change that the AR term did not explain. For the first residual only, the previous change is initialized to μ.</td></tr>
            <tr><td>5. Estimate θ</td><td>θ = clamp(C(e), −0.99, 0.99)</td><td>Apply the same lag-1 formula to the residual sequence, using its own mean.</td></tr>
            <tr><td>6. Residual spread</td><td>s² = Σ(eᵢ − ē)² / (m − 1)<br>σ = √s²</td><td>Sample variance and standard deviation of the residuals.</td></tr>
            <tr><td>7. Predict a change</td><td>δₕ = μ + φ(δₕ₋₁ − μ) + θeₕ₋₁</td><td>Combine drift, the previous centered change and the previous surprise.</td></tr>
            <tr><td>8. Rebuild the level</td><td>ŷₕ = max(0, previous level + δₕ)</td><td>Add the predicted change back to the level; prevent negative price or supply.</td></tr>
            <tr><td>9. Approximate range</td><td>bₕ = zσ√h<br>Lower = max(0, ŷₕ − bₕ)<br>Upper = ŷₕ + bₕ</td><td>Show the residual-based uncertainty range around each prediction.</td></tr>
            <tr><td>10. Classify trend</td><td>Compare the final rounded forecast with B × (1 ± {{ $settings['threshold'] / 100 }})</td><td>B is the mean of the last {{ $settings['baseline_days'] }} actual observations, or all available observations if fewer.</td></tr>
        </tbody>
    </table></div>
    <p class="calc-small">In C(x), the numerator sums neighboring pairs from i = 1 to m − 1, while the denominator sums all m squared deviations.
        If that denominator is zero, the coefficient is 0. With fewer than two values, the coefficient and sample variance are 0.</p>
    <div class="calc-note"><strong>How steps connect.</strong> Start with the last actual level, last actual difference and last residual.
        After each forecast, feed its <em>unrounded</em> level and difference into the next step. The future residual is set to 0 after step 1,
        so the MA term contributes only to the first forecast’s expected value.</div>
    <details class="calc-details"><summary>Active configuration used by the examples and the system</summary><div class="calc-details-body">
        <div class="calc-table-wrap"><table class="calc-table">
            <thead><tr><th scope="col">Setting</th><th scope="col">Active value</th><th scope="col">Effect</th></tr></thead>
            <tbody>
                <tr><td>Model order</td><td>p = 1, d = 1, q = 1</td><td>The implementation follows one AR term, first differences and one MA term.</td></tr>
                <tr><td>Minimum history</td><td>{{ $settings['minimum'] }} observations</td><td>Shorter series are skipped.</td></tr>
                <tr><td>Lookback</td><td>{{ $settings['history'] }} calendar days</td><td>Restricts batch entry dates used for fitting.</td></tr>
                <tr><td>Forecast horizon</td><td>{{ $settings['horizon'] }} steps</td><td>Tomorrow onward, according to the generation date.</td></tr>
                <tr><td>Historical chart window</td><td>{{ $settings['chart_days'] }} days</td><td>Separate from the model’s fitting window.</td></tr>
                <tr><td>Trend baseline</td><td>Last {{ $settings['baseline_days'] }} actual observations</td><td>Recent average used by every row’s trend label.</td></tr>
                <tr><td>Trend band</td><td>±{{ $fmt($settings['threshold'], 1) }}%</td><td>Strictly outside this band means upward or downward.</td></tr>
                <tr><td>Range multiplier z</td><td>{{ $settings['z'] }}</td><td>At z = 1.96 this is conventionally described as an approximate 95% range; see the limitations below.</td></tr>
            </tbody>
        </table></div>
        <p class="calc-small">These values are read from the running application’s configuration. Changing order metadata alone does not implement another ARIMA order.</p>
    </div></details>
</section>
