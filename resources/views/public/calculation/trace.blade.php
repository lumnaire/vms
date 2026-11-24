@php
    $phi = $trace['phi_fit']['coefficient'];
    $theta = $trace['theta_fit']['coefficient'];
    $mean = $trace['mean_diff'];
    $lastResidual = $trace['residuals'][count($trace['residuals']) - 1];
    $trendText = ['upward' => 'Upward', 'downward' => 'Downward', 'stable' => 'Stable'][$trace['trend']];
    $trendSymbol = ['upward' => '↗', 'downward' => '↘', 'stable' => '→'][$trace['trend']];

    $shownHistory = array_slice($trace['observations'], -20);
    $historyOffset = count($trace['observations']) - count($shownHistory);
    $chartValues = array_merge($shownHistory, array_column($trace['projections'], 'min'), array_column($trace['projections'], 'max'), [$trace['baseline']]);
    $chartSpan = max(1, max($chartValues) - min($chartValues));
    $chartMin = max(0, min($chartValues) - $chartSpan * .12);
    $chartMax = max($chartMin + 1, max($chartValues) + $chartSpan * .12);
    $chartCount = count($shownHistory) + count($trace['projections']);
    $cx = fn ($index) => round(70 + $index / max(1, $chartCount - 1) * 808, 2);
    $cy = fn ($value) => round(230 - ($value - $chartMin) / ($chartMax - $chartMin) * 210, 2);
    $point = fn ($index, $value) => $cx($index).','.$cy($value);
    $historyPoints = [];
    foreach ($shownHistory as $index => $value) { $historyPoints[] = $point($index, $value); }
    $anchor = $point(count($shownHistory) - 1, end($shownHistory));
    $forecastPoints = [$anchor];
    $upperPoints = [$anchor];
    $lowerPoints = [];
    foreach ($trace['projections'] as $index => $projection) {
        $position = count($shownHistory) + $index;
        $forecastPoints[] = $point($position, $projection['value']);
        $upperPoints[] = $point($position, $projection['max']);
        $lowerPoints[] = $point($position, $projection['min']);
    }
    $bandPoints = array_merge($upperPoints, array_reverse($lowerPoints), [$anchor]);
@endphp
<dl class="calc-stats" aria-label="Forecast example summary">
    <div><dt>Observations</dt><dd>{{ count($trace['observations']) }} <small>recorded days</small></dd></div>
    <div><dt>Fitted drift μ</dt><dd>{{ $fmt($mean, 4) }} <small>{{ $unit }}/step</small></dd></div>
    <div><dt>Tomorrow’s estimate</dt><dd>{{ $fmt($trace['projections'][0]['value']) }} <small>{{ $unit }}</small></dd></div>
    <div><dt>Overall trend</dt><dd><span class="calc-trend calc-trend-{{ $trace['trend'] }}">{{ $trendSymbol }} {{ $trendText }}</span></dd></div>
</dl>
<figure class="calc-chart">
    <div class="calc-chart-legend" aria-hidden="true"><span><i class="calc-chart-key"></i>Observed</span><span><i class="calc-chart-key calc-chart-key-forecast"></i>Forecast</span><span><i class="calc-chart-key calc-chart-key-band"></i>Approximate range</span><span><i class="calc-chart-key calc-chart-key-baseline"></i>Recent baseline</span></div>
    <svg viewBox="0 0 900 280" role="img" aria-labelledby="forecast-example-title forecast-example-description">
        <title id="forecast-example-title">{{ $trendText }} example: observations and {{ $settings['horizon'] }} forecast steps in {{ $unit }}</title>
        <desc id="forecast-example-description">The solid line shows the last {{ count($shownHistory) }} illustrative observations. The dashed line shows future predictions and the shaded area shows their approximate ranges. The recent actual baseline is {{ $fmt($trace['baseline']) }}. All forecast values and ranges are in the table below.</desc>
        @for ($tick = 0; $tick <= 4; $tick++)
            @php
                $tickValue = $chartMin + ($chartMax - $chartMin) * $tick / 4;
                $tickLabel = $tickValue >= 1000000 ? $fmt($tickValue / 1000000, 1).'M' : $fmt($tickValue, $chartMax < 10 ? 2 : 0);
            @endphp
            <line class="calc-chart-grid" x1="70" y1="{{ $cy($tickValue) }}" x2="878" y2="{{ $cy($tickValue) }}" />
            <text x="60" y="{{ $cy($tickValue) + 4 }}" text-anchor="end">{{ $tickLabel }}</text>
        @endfor
        <polygon class="calc-chart-band" points="{{ implode(' ', $bandPoints) }}" />
        <line class="calc-chart-baseline" x1="70" y1="{{ $cy($trace['baseline']) }}" x2="878" y2="{{ $cy($trace['baseline']) }}" />
        <polyline class="calc-chart-history" points="{{ implode(' ', $historyPoints) }}" />
        <polyline class="calc-chart-forecast" points="{{ implode(' ', $forecastPoints) }}" />
        <text x="70" y="254">Obs. {{ $historyOffset + 1 }}</text>
        <text x="{{ $cx(count($shownHistory) - 1) }}" y="254" text-anchor="end">Last actual</text>
        @foreach ($trace['projections'] as $index => $projection)
            <circle class="calc-chart-dot" cx="{{ $cx(count($shownHistory) + $index) }}" cy="{{ $cy($projection['value']) }}" r="4"><title>Step {{ $index + 1 }}: {{ $fmt($projection['value']) }} {{ $unit }}; range {{ $fmt($projection['min']) }}–{{ $fmt($projection['max']) }}</title></circle>
            <text x="{{ $cx(count($shownHistory) + $index) }}" y="254" text-anchor="middle">+{{ $index + 1 }}</text>
        @endforeach
    </svg>
    <figcaption class="calc-small">Observed sequence → forecast steps. {{ count($trace['observations']) > 20 ? 'For readability, the chart displays only the final 20 observations; the model uses every submitted observation.' : 'The model uses every observation shown.' }} The horizontal axis represents observation order, not missing calendar dates.</figcaption>
</figure>
<div class="calc-table-wrap"><table class="calc-table">
    <caption>Forecast results · illustrative {{ $metric === 'price' ? 'daily average price' : 'incoming supply' }}</caption>
    <thead><tr><th scope="col">Target step</th><th scope="col" class="calc-num">Prediction ({{ $unit }})</th><th scope="col" class="calc-num">Lower</th><th scope="col" class="calc-num">Upper</th><th scope="col">Overall label</th></tr></thead>
    <tbody>@foreach ($trace['projections'] as $index => $projection)<tr><td>{{ $index === 0 ? 'Tomorrow' : 'Today + '.($index + 1).' days' }}<br><span class="calc-small">{{ today()->addDays($index + 1)->format('M j, Y') }}</span></td><td class="calc-num"><strong>{{ $fmt($projection['value']) }}</strong></td><td class="calc-num">{{ $fmt($projection['min']) }}</td><td class="calc-num">{{ $fmt($projection['max']) }}</td><td><span class="calc-trend calc-trend-{{ $projection['trend'] }}">{{ $trendSymbol }} {{ $trendText }}</span></td></tr>@endforeach</tbody>
</table></div>

<h3>The solution behind this result</h3>
<p>Coefficients are fitted together by exact Gaussian maximum likelihood. Values below are shown to six decimals; the service retains full precision.</p>
<details class="calc-details" open><summary>Step 1 · Difference the daily observations</summary><div class="calc-details-body">
    <div class="calc-equation">y = [{{ implode(', ', $trace['observations']) }}]<br>
        Δy = [{{ implode(', ', $trace['differences']) }}]<br>Sample mean change = {{ $fmt($trace['sample_mean_diff'], 6) }}</div>
</div></details>
<details class="calc-details" open><summary>Step 2 · Maximize the Gaussian likelihood</summary><div class="calc-details-body">
    <div class="calc-equation">ℓ = −½[m log(2π) + m log(σ²) + Σlog(Fₜ) + Σ(vₜ²/Fₜ)/σ²]<br>
        Fitted drift μ = {{ $fmt($mean, 6) }}<br>φ = {{ $fmt($phi, 6) }}<br>θ = {{ $fmt($theta, 6) }}<br>
        Log likelihood = {{ $trace['fit']['log_likelihood'] === null ? 'undefined (zero innovation variance)' : $fmt($trace['fit']['log_likelihood'], 6) }}<br>
        Fit status = {{ $trace['fit']['status'] }}</div>
    <p>Stationary initialization accounts for uncertainty before the first observed change. A multi-start numerical optimizer searches φ and θ jointly;
        drift and variance are profiled analytically. The restrictions −1 &lt; φ, θ &lt; 1 enforce stationarity and invertibility.</p>
</div></details>
<details class="calc-details"><summary>Step 3 · Inspect the Kalman innovations</summary><div class="calc-details-body">
    <div class="calc-table-wrap"><table class="calc-table"><thead><tr><th>Observation</th><th>Change</th><th>Fitted change</th><th>Innovation vₜ</th><th>Factor Fₜ</th></tr></thead><tbody>
    @foreach ($trace['difference_steps'] as $step)<tr><td>{{ $step['observation'] }}</td><td>{{ $fmt($step['difference'], 6) }}</td>
        <td>{{ $fmt($step['fitted_difference'], 6) }}</td><td>{{ $fmt($step['residual'], 6) }}</td><td>{{ $fmt($step['innovation_factor'], 6) }}</td></tr>@endforeach
    </tbody></table></div>
    <p>The final filtered shock is vₘ/Fₘ = {{ $fmt($trace['fit']['last_shock'], 6) }}. With finite initialization it can differ from the last innovation.</p>
</div></details>
<details class="calc-details"><summary>Step 4 · Estimate the innovation variance</summary><div class="calc-details-body">
    <div class="calc-equation">σ² = Σ(vₜ²/Fₜ)/m = {{ $fmt($trace['fit']['weighted_sse'], 6) }} / {{ count($trace['differences']) }}<br>
        = {{ $fmt($trace['fit']['sigma2'], 6) }}<br>σ = {{ $fmt($trace['sigma'], 6) }}</div>
    <p>This is maximum-likelihood variance, with divisor m. A sequence with constant changes is a deterministic limiting case:
        coefficients are not identifiable, innovation variance is zero, and no finite Gaussian likelihood is reported.</p>
</div></details>
<details class="calc-details" open><summary>Step 5 · Forecast changes, levels and intervals</summary><div class="calc-details-body">
    @foreach ($trace['forecast_steps'] as $index => $step)
        <h3>Forecast step {{ $step['h'] }}</h3>
        <div class="calc-equation">δ = μ + φ(previous Δ − μ) + θ(previous filtered shock)<br>
            = {{ $fmt($mean, 6) }} + ({{ $fmt($step['ar_contribution'], 6) }}) + ({{ $fmt($step['ma_contribution'], 6) }}) = {{ $fmt($step['difference'], 6) }}<br>
            Raw level = {{ $fmt($step['previous_value'], 6) }} + ({{ $fmt($step['difference'], 6) }}) = {{ $fmt($step['unclipped_value'], 6) }}<br>
            Forecast variance Vₕ = {{ $fmt($step['forecast_variance'], 6) }}<br>
            Half-width = {{ $trace['z'] }} × √Vₕ = {{ $fmt($step['band'], 6) }}<br>
            Published prediction = {{ $fmt($trace['projections'][$index]['value']) }} {{ $unit }}<br>
            Published interval = {{ $fmt($trace['projections'][$index]['min']) }}–{{ $fmt($trace['projections'][$index]['max']) }} {{ $unit }}</div>
    @endforeach
    <p>Future shocks are zero in the conditional mean. Raw unrounded levels feed the next step. Published predictions and both bounds are clipped at zero.
        Model-based intervals condition on the fitted parameters and omit parameter-estimation uncertainty.</p>
</div></details>
<details class="calc-details" open><summary>Step 6 · Compare the final prediction with recent observations</summary><div class="calc-details-body">
    <div class="calc-equation">Recent values = [{{ implode(', ', $trace['baseline_values']) }}]<br>
        B = {{ $fmt($trace['baseline'], 6) }}<br>Stable band = {{ $fmt($trace['lower_threshold'], 6) }}–{{ $fmt($trace['upper_threshold'], 6) }}<br>
        Final prediction = {{ $fmt($trace['final']) }} → {{ ucfirst($trace['trend']) }}<br>
        @if ($trace['change_percent'] === null)Percentage change is undefined because the baseline is 0.
        @else Change from baseline = {{ $fmt($trace['change_percent'], 4) }}% @endif</div>
</div></details>
