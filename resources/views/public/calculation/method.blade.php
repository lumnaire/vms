<section id="arima-method" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">04 · The forecasting method</div>
    <h2>How ARIMA works here</h2>
    <p class="calc-lead">The system fits <strong>ARIMA(1,1,1) with drift using exact Gaussian maximum likelihood</strong>.
        It estimates the AR and MA coefficients together from the observations.</p>
    <div class="calc-grid calc-grid-three">
        <article class="calc-card"><div class="calc-eyebrow">AR · p = 1</div><h3>One previous change</h3><p class="calc-small">φ weights the previous change relative to the fitted drift.</p></article>
        <article class="calc-card"><div class="calc-eyebrow">I · d = 1</div><h3>Difference once</h3><p class="calc-small">Model consecutive daily changes, then integrate them back into levels.</p></article>
        <article class="calc-card"><div class="calc-eyebrow">MA · q = 1</div><h3>One previous shock</h3><p class="calc-small">θ weights the last filtered shock. Future shocks have expected value zero.</p></article>
    </div>
    <h3>Which data enters the model?</h3>
    <ul class="calc-list">
        <li>Fit a separate model for each <strong>fish type × quality class × metric</strong>.</li>
        <li>Read confirmed batches before today, within the last <strong>{{ $settings['history'] }} calendar days</strong>.</li>
        <li>Daily price is the unweighted mean of batch prices; daily supply is total incoming kilograms.</li>
        <li>Require at least <strong>{{ $settings['minimum'] }} consecutive recorded daily observations ending at the latest actual record within the window</strong>. This is an operational minimum, not proof of statistical sufficiency.</li>
        <li>Produce <strong>{{ $settings['horizon'] }} published forecasts starting tomorrow</strong>. First advance through every unobserved day since the last actual record.</li>
        <li>The outputs are price and incoming supply. Sales, revenue and demand are separate quantities.</li>
    </ul>
    <div class="calc-note calc-note-warm"><strong>Missing dates remain missing.</strong> Training uses the latest consecutive recorded segment.
        An internal gap ends that segment. A gap after the final actual record increases the forecast lead instead of preventing generation.
        Chart-filled prices and zeros do not enter fitting, and an insufficient segment produces no new forecast.</div>
    <h3>The full calculation, in order</h3>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">Step</th><th scope="col">Calculation</th><th scope="col">Meaning</th></tr></thead>
        <tbody>
            <tr><td>1. Difference</td><td>xₜ = yₜ − yₜ₋₁</td><td>Daily changes.</td></tr>
            <tr><td>2. Fit parameters</td><td>Maximize ℓ(μ, φ, θ, σ²)</td><td>Joint Gaussian maximum likelihood with stationary initialization.</td></tr>
            <tr><td>3. Estimate φ</td><td>−1 &lt; φ &lt; 1</td><td>Optimized AR coefficient; keeps the differenced model stationary.</td></tr>
            <tr><td>4. Filter innovations</td><td>vₜ = xₜ − E[xₜ | earlier observations]</td><td>The Kalman filter accounts for AR and MA terms.</td></tr>
            <tr><td>5. Estimate θ</td><td>−1 &lt; θ &lt; 1</td><td>Jointly optimized MA coefficient; constrained for invertibility.</td></tr>
            <tr><td>6. Innovation variance</td><td>σ² = Σ(vₜ² / Fₜ) / m</td><td>Fₜ is the unit-variance innovation factor; m = n − 1.</td></tr>
            <tr><td>7. Predict a change</td><td>δₕ = μ + φ(previous Δ − μ) + θ(previous filtered shock)</td><td>Future shocks have expected value zero.</td></tr>
            <tr><td>8. Rebuild the level</td><td>Raw ŷₕ = previous raw level + δₕ</td><td>Retain raw levels in recursion; clip published negative values to zero.</td></tr>
            <tr><td>9. Prediction interval</td><td>Raw ŷₕ ± z × √Vₕ</td><td>Use fitted AR/MA impulse responses and filtered-state uncertainty.</td></tr>
            <tr><td>10. Classify trend</td><td>Compare final prediction with B × (1 ± {{ $settings['threshold'] / 100 }})</td><td>B is the last {{ $settings['baseline_days'] }} actual observations’ mean.</td></tr>
        </tbody>
    </table></div>
    <p class="calc-small">μ is fitted drift and can differ from the arithmetic mean change. A numerical optimizer searches φ and θ together;
        neither coefficient is replaced by an autocorrelation.</p>
    <div class="calc-note">Illustrative examples treat their last observation as the forecast origin. Stored forecasts retain the actual training-end date.
        If that date is yesterday, tomorrow is model step 2. If the last record is November 17 and today is November 24,
        tomorrow’s November 25 prediction is model step 8. Its variance includes all eight steps.
        Intervals condition on fitted parameters; parameter-estimation uncertainty is excluded.</div>
    @include('public.calculation.method-example', ['sample' => $methodExample])
    <details class="calc-details"><summary>Active settings</summary><div class="calc-details-body">
        <div class="calc-table-wrap"><table class="calc-table"><tbody>
            <tr><td>Training window</td><td>{{ $settings['history'] }} calendar days</td></tr>
            <tr><td>Minimum consecutive observations</td><td>{{ $settings['minimum'] }}</td></tr>
            <tr><td>Published horizon</td><td>{{ $settings['horizon'] }} days, tomorrow onward</td></tr>
            <tr><td>Model</td><td>ARIMA(1,1,1), fitted drift, exact Gaussian maximum likelihood</td></tr>
            <tr><td>Interval multiplier</td><td>{{ $settings['z'] }}</td></tr>
        </tbody></table></div>
        <p class="calc-small">The engine implements this fixed order. Changing order metadata alone does not implement another model.</p>
    </div></details>
</section>
