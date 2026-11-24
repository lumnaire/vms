<section id="glossary" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">08 · A notation reference</div>
    <h2>Variable glossary</h2>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">Symbol / field</th><th scope="col">Meaning</th><th scope="col">Unit</th></tr></thead>
        <tbody>
            @foreach ([
                ['Q / stock_kg', 'Original incoming kilograms declared on a batch.', 'kg'],
                ['released_kg', 'Initial available batch quantity, set equal to incoming stock on submission.', 'kg'],
                ['sold_kg', 'Cumulative kilograms the vendor has recorded as sold.', 'kg'],
                ['pulled_out_kg', 'Cumulative kilograms removed without being sold.', 'kg'],
                ['Remaining', 'Initial available minus sold and pulled out, clipped at zero; zero after expiry/carry-out.', 'kg'],
                ['P / price_per_kg', 'Vendor asking price for a batch.', '₱/kg'],
                ['T / tariff_price', 'Official reference price; historical batches retain their submission-time snapshot.', '₱/kg'],
                ['yᵢ', 'One observed daily value, sorted oldest to newest.', '₱/kg or kg'],
                ['n / m', 'Number of observations / number of first differences (n − 1).', 'counts'],
                ['Δyᵢ', 'Change between consecutive recorded daily observations.', 'metric units per step'],
                ['μ (mu)', 'Drift estimated jointly with AR and MA parameters by Gaussian maximum likelihood.', 'metric units per step'],
                ['φ (phi)', 'Maximum-likelihood AR coefficient, constrained inside (−1, 1).', 'unitless'],
                ['eᵢ / ē', 'Kalman innovation / arithmetic mean of innovations.', 'metric units per step'],
                ['θ (theta)', 'Jointly estimated maximum-likelihood MA coefficient, constrained inside (−1, 1).', 'unitless'],
                ['s² / σ (sigma)', 'Maximum-likelihood innovation variance / its square root.', 'squared residual units / residual units'],
                ['p, d, q', 'Autoregressive order, differencing order, moving-average order; each is 1 here.', 'orders'],
                ['h / δₕ / ŷₕ', 'Future step index / predicted change / predicted level.', 'step / metric units per step / metric units'],
                ['z / bₕ', 'Normal interval multiplier / half-width z√Vₕ.', 'unitless / metric units'],
                ['B / F', 'Recent actual mean used as baseline / final rounded forecast.', '₱/kg or kg'],
                ['Trend threshold', 'Fraction above or below the baseline needed to classify the overall direction.', 'fraction; displayed as %'],
                ['Σ / x̄ / √ / |x|', 'Sum / arithmetic mean / square root / absolute value.', 'depends on the expression'],
                ['max(0, x)', 'Clip a published prediction or interval bound at zero; raw levels remain in the model recursion.', 'same units as x'],
                ['MAPE', 'Mean absolute percentage error over eligible past predicted/actual pairs.', '%'],
            ] as [$symbol, $meaning, $units])
                <tr><td><strong>{{ $symbol }}</strong></td><td>{{ $meaning }}</td><td>{{ $units }}</td></tr>
            @endforeach
        </tbody>
    </table></div>
</section>

<section id="research-notes" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">09 · Reproducibility &amp; scope</div>
    <h2>Research notes</h2>
    <p class="calc-lead">Describe the implementation that actually ran, preserve its configuration, and compare predictions with observations that became available later.</p>
    <h3>What this implementation estimates</h3>
    <p>The system implements exact Gaussian maximum-likelihood estimation for ARIMA(1,1,1) with drift in PHP.
        Its stationary Kalman initialization, joint numerical coefficient search and model-based forecast variance replace the earlier autocorrelation shortcut.
        Numerical results are checked against independent statsmodels fixtures. The order is fixed by the research design;
        automatic model selection and stationarity hypothesis tests are not performed.</p>
    <h3>Published methodology and thesis alignment</h3>
    <p>Mardesci, H., &amp; Fitriani, D. (2025). <a href="https://doi.org/10.64570/agrivolution.v1i2.33" target="_blank" rel="noopener noreferrer">Performance evaluation of ARIMA and ANN models for forecasting oil palm production trends</a>.
        <em>Agricultural Revolution Journal, 1</em>(2), 75–83. Section 2.1 on page 77 explicitly uses maximum likelihood.
        That study concerns annual oil-palm production; this project adapts the estimation method to daily fish records.</p>
    <p>The supplied thesis specifies rolling ARIMA(1,1,1) on printed page 33 and a 30-day window on pages 8 and 13.
        Chapters 1–3 take precedence over the inconsistent 90-day entry on page 159, following the researchers’ selected configuration.
        The original thesis bibliography needs the complete Mardesci and Fitriani reference.</p>
    <p>For the likelihood algorithm, see <a href="https://stat.ethz.ch/R-manual/R-devel/library/stats/html/arima.html" target="_blank" rel="noopener noreferrer">R’s ARIMA documentation</a>.
        The PHP implementation is project code implementing a standard estimator. It is not the original authors’ software.</p>
    <ul class="calc-list">
        <li>No weather, fishing conditions, holidays or external predictors enter the model. Sudden disruptions may not be reflected in the history.</li>
        <li>There is no automatic seasonal model or price–supply causal model. Each fish/class/metric history is fitted independently.</li>
        <li>The minimum observation count is a processing rule, not evidence that the sample is statistically sufficient for every research question.</li>
        <li>Training uses the latest consecutive recorded segment within the window. Internal gaps shorten it; trailing gaps increase the actual forecast lead. Chart-filled dates are excluded.</li>
        <li>The range is approximate, lower bounds are clipped at zero, and empirical coverage should be evaluated separately.</li>
        <li>Historical supply remains the original incoming weight after sale or expiry. Remaining stock is a different quantity.</li>
        <li>Full-precision coefficients, training dates and model version are stored for audit; display coefficients use 4 decimals and predictions/bounds use 2. Rounding can affect borderline trend decisions.</li>
    </ul>
    <h3>Generation, reruns and historical evaluation</h3>
    <p>The application schedules forecast generation at <strong>00:01</strong>, stock expiry at <strong>00:02</strong>, and previous-day entry locking at <strong>00:05</strong>
        in its configured schedule time zone. The host must run Laravel’s scheduler for scheduled execution to happen.
        Opening the supervisor forecast page can also generate that selected series if it has no run from today.</p>
    <p>Each run stores its generation time, target dates, predictions, bounds, direction and model parameters.
        Rerunning the same series on the same day replaces that day’s run; runs from earlier days are retained.
        The actual last observation anchors the model, which advances across all unobserved days before publishing tomorrow onward.
        The stored training dates, unobserved-day count and model steps preserve that distinction. For backtesting, only predictions produced before their target date count as eligible.</p>
    <details class="calc-details"><summary>Checklist for reproducing a result in a research paper</summary><div class="calc-details-body">
        <ol class="calc-list" style="list-style-type: decimal">
            <li>Identify fish type, quality class, metric, unit and the application time zone.</li>
            <li>Record the generation date, lookback window, eligible confirmed batches and the daily aggregation rule.</li>
            <li>List the actual daily observations in chronological order, noting dates with no observations.</li>
            <li>Record active horizon, minimum history, trend baseline length, trend band and z multiplier.</li>
            <li>Fit the exact Gaussian likelihood and record drift, AR/MA coefficients, innovation variance, likelihood, convergence and model version.</li>
            <li>Roll forward using raw unrounded levels and filtered shocks; include the current-day bridge and integrated forecast-error variance.</li>
            <li>Round published predictions/bounds, compare the final rounded value with the unrounded recent baseline thresholds, and record the overall label.</li>
            <li>Evaluate against later actuals using eligible pre-date predictions from this model version and window. Report RMSE, MAE, MAPE, sample sizes and MAPE’s zero-actual exclusions.</li>
        </ol>
    </div></details>
    <details class="calc-details"><summary>Source map for reviewing the implementation</summary><div class="calc-details-body">
        <div class="calc-table-wrap"><table class="calc-table">
            <thead><tr><th scope="col">Area</th><th scope="col">Project source</th></tr></thead>
            <tbody>
                <tr><td>ARIMA fitting, trace, dates and stored runs</td><td><code>app/Services/ArimaService.php</code></td></tr>
                <tr><td>Forecast summary and MAPE eligibility</td><td><code>app/Http/Controllers/Supervisor/ForecastController.php</code></td></tr>
                <tr><td>Supply, sales, tubo, windows and rankings</td><td><code>app/Services/MarketAnalytics.php</code></td></tr>
                <tr><td>Stock balance, freshness, batch numbering and releases</td><td><code>app/Models/VendorInventory.php</code>; <code>app/Models/BatchRelease.php</code></td></tr>
                <tr><td>Tariff snapshots and comparison</td><td><code>app/Models/PriceGuide.php</code>; vendor inventory controller</td></tr>
                <tr><td>Weighted supply reports and PDF data</td><td><code>app/Services/SupplyReport.php</code></td></tr>
                <tr><td>Supervisor summaries and history export</td><td><code>app/Http/Controllers/Supervisor/ReportController.php</code></td></tr>
                <tr><td>Live board and account-specific counts</td><td>Public priceboard controller; supervisor, staff and vendor dashboard controllers</td></tr>
                <tr><td>Active settings and schedule</td><td><code>config/forecast.php</code>; <code>config/inventory.php</code>; <code>bootstrap/app.php</code></td></tr>
            </tbody>
        </table></div>
        <p class="calc-small">This map identifies the project logic for a researcher reviewing the repository. The public guide does not serve those source files or expose database credentials.</p>
    </div></details>
</section>
