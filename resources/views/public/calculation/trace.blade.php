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
    <div><dt>Mean change μ</dt><dd>{{ $fmt($mean, 4) }} <small>{{ $unit }}/step</small></dd></div>
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
<p class="calc-small">Open a step to inspect the arithmetic. Intermediate numbers are displayed to 6 decimals; the calculation retains full precision.
    Saved model parameters use 4 decimals. Published predictions and bounds use 2 decimals.</p>

<details class="calc-details" open><summary>Step 1 · Start with the daily observations</summary><div class="calc-details-body">
    <div class="calc-equation">y = [{{ implode(', ', $trace['observations']) }}]<br>n = {{ count($trace['observations']) }} observations; m = n − 1 = {{ count($trace['differences']) }} differences</div>
    <p class="calc-small">For price, each y is that day’s unweighted mean of confirmed batch prices. For supply, each y is that day’s total incoming kg.
        The example starts after that daily aggregation has already happened.</p>
</div></details>
<details class="calc-details" open><summary>Step 2 · Difference the series and calculate the drift μ</summary><div class="calc-details-body">
    <div class="calc-equation">First difference = {{ $trace['observations'][1] }} − {{ $trace['observations'][0] }} = {{ $fmt($trace['differences'][0], 6) }}<br>Δy = [{{ implode(', ', $trace['differences']) }}]<br>μ = sum of differences / {{ count($trace['differences']) }}<br>= {{ $fmt(array_sum($trace['differences']), 6) }} / {{ count($trace['differences']) }} = <strong>{{ $fmt($mean, 6) }}</strong></div>
    <p class="calc-small">Positive drift means the observations rose on average. Negative drift means they fell. Drift alone does not determine the final forecast or its trend label.</p>
</div></details>
<details class="calc-details"><summary>Step 3 · Estimate the autoregressive coefficient φ</summary><div class="calc-details-body">
    <p>Center each difference around μ. Multiply neighboring centered differences for the numerator, and add all squared centered differences for the denominator.</p>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">i</th><th scope="col" class="calc-num">Δyᵢ</th><th scope="col" class="calc-num">Δyᵢ − μ</th><th scope="col" class="calc-num">× next centered value</th><th scope="col" class="calc-num">Squared deviation</th></tr></thead>
        <tbody>@foreach ($trace['differences'] as $index => $difference)<tr><td>{{ $index + 1 }}</td><td class="calc-num">{{ $fmt($difference, 6) }}</td><td class="calc-num">{{ $fmt($difference - $mean, 6) }}</td><td class="calc-num">{{ isset($trace['differences'][$index + 1]) ? $fmt(($difference - $mean) * ($trace['differences'][$index + 1] - $mean), 6) : '—' }}</td><td class="calc-num">{{ $fmt(($difference - $mean) ** 2, 6) }}</td></tr>@endforeach</tbody>
    </table></div>
    <div class="calc-equation">Numerator = {{ $fmt($trace['phi_fit']['numerator'], 6) }}<br>Denominator = {{ $fmt($trace['phi_fit']['denominator'], 6) }}<br>Ratio = {{ $fmt($trace['phi_fit']['ratio'], 6) }}{{ $trace['phi_fit']['denominator'] == 0 ? ' (zero denominator → coefficient 0)' : '' }}<br>φ = clamp(ratio, −0.99, 0.99) = <strong>{{ $fmt($phi, 6) }}</strong></div>
    <p class="calc-small">A positive φ tends to continue deviations from the mean change; a negative φ tends to reverse them. Its sign alone does not mean prices or supply will go up or down.</p>
</div></details>
<details class="calc-details"><summary>Step 4 · Calculate each residual e from the lagged difference</summary><div class="calc-details-body">
    <div class="calc-equation">eᵢ = (Δyᵢ − μ) − φ(previous Δy − μ)<br>For the first residual, previous Δy = μ, so its AR contribution is zero.</div>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">Observation</th><th scope="col" class="calc-num">Previous y</th><th scope="col" class="calc-num">Current y</th><th scope="col" class="calc-num">Δy</th><th scope="col" class="calc-num">Previous Δy</th><th scope="col" class="calc-num">Residual e</th></tr></thead>
        <tbody>@foreach ($trace['difference_steps'] as $step)<tr><td>{{ $step['observation'] }}</td><td class="calc-num">{{ $fmt($step['previous_value'], 6) }}</td><td class="calc-num">{{ $fmt($step['value'], 6) }}</td><td class="calc-num">{{ $fmt($step['difference'], 6) }}</td><td class="calc-num">{{ $fmt($step['previous_difference'], 6) }}</td><td class="calc-num">{{ $fmt($step['residual'], 6) }}</td></tr>@endforeach</tbody>
    </table></div>
    <p class="calc-small">The prior difference is the actual preceding difference, not the current one. The last residual, used for the first prediction, is <strong>{{ $fmt($lastResidual, 6) }}</strong>.</p>
</div></details>
<details class="calc-details"><summary>Step 5 · Estimate the moving-average coefficient θ</summary><div class="calc-details-body">
    <p>Apply the same lag-1 autocorrelation formula to the residuals. Center them on their own mean ē, rather than assuming their mean is zero.</p>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th scope="col">i</th><th scope="col" class="calc-num">Residual eᵢ</th><th scope="col" class="calc-num">eᵢ − ē</th><th scope="col" class="calc-num">× next centered residual</th><th scope="col" class="calc-num">Squared deviation</th></tr></thead>
        <tbody>@foreach ($trace['residuals'] as $index => $residual)<tr><td>{{ $index + 1 }}</td><td class="calc-num">{{ $fmt($residual, 6) }}</td><td class="calc-num">{{ $fmt($residual - $trace['residual_mean'], 6) }}</td><td class="calc-num">{{ isset($trace['residuals'][$index + 1]) ? $fmt(($residual - $trace['residual_mean']) * ($trace['residuals'][$index + 1] - $trace['residual_mean']), 6) : '—' }}</td><td class="calc-num">{{ $fmt(($residual - $trace['residual_mean']) ** 2, 6) }}</td></tr>@endforeach</tbody>
    </table></div>
    <div class="calc-equation">ē = {{ $fmt($trace['residual_mean'], 6) }}<br>Numerator = {{ $fmt($trace['theta_fit']['numerator'], 6) }}<br>Denominator = {{ $fmt($trace['theta_fit']['denominator'], 6) }}<br>Ratio = {{ $fmt($trace['theta_fit']['ratio'], 6) }}{{ $trace['theta_fit']['denominator'] == 0 ? ' (zero denominator → coefficient 0)' : '' }}<br>θ = clamp(ratio, −0.99, 0.99) = <strong>{{ $fmt($theta, 6) }}</strong></div>
</div></details>
<details class="calc-details"><summary>Step 6 · Measure the residual spread σ</summary><div class="calc-details-body">
    <p>Use the squared deviations from the previous table. This is the <strong>sample</strong> variance, so divide by residual count minus one.</p>
    <div class="calc-equation">Residual count m = {{ count($trace['residuals']) }}<br>Sum of squared residual deviations = {{ $fmt($trace['theta_fit']['denominator'], 6) }}<br>s² = {{ $fmt($trace['theta_fit']['denominator'], 6) }} / {{ count($trace['residuals']) - 1 }} = {{ $fmt($trace['residual_variance'], 6) }}<br>σ = √{{ $fmt($trace['residual_variance'], 6) }} = <strong>{{ $fmt($trace['sigma'], 6) }}</strong></div>
    <p class="calc-small">A larger residual spread produces a wider approximate forecast range. Constant observations or perfectly constant changes can produce σ = 0 in this implementation.</p>
</div></details>
<details class="calc-details" open><summary>Step 7 · Solve every future step and its range</summary><div class="calc-details-body">
    <p>For the first step, the previous level is the final actual observation. Later steps use the previous unrounded prediction.
        Only the first step uses the final fitted residual; future residuals are zero.</p>
    @foreach ($trace['forecast_steps'] as $index => $step)
        <h3>Forecast step {{ $step['h'] }}{{ $step['h'] === 1 ? ' · tomorrow' : '' }}</h3>
        <div class="calc-equation">δ = μ + φ(previous Δ − μ) + θ(previous e)<br>= {{ $fmt($mean, 6) }} + ({{ $fmt($phi, 6) }}) × ({{ $fmt($step['previous_difference'], 6) }} − {{ $fmt($mean, 6) }}) + ({{ $fmt($theta, 6) }}) × {{ $fmt($step['previous_residual'], 6) }}<br>= {{ $fmt($mean, 6) }} + ({{ $fmt($step['ar_contribution'], 6) }}) + ({{ $fmt($step['ma_contribution'], 6) }}) = <strong>{{ $fmt($step['difference'], 6) }}</strong><br>ŷ = max(0, {{ $fmt($step['previous_value'], 6) }} + {{ $fmt($step['difference'], 6) }}) = {{ $fmt($step['value'], 6) }}<br>Band = {{ $trace['z'] }} × {{ $fmt($trace['sigma'], 6) }} × √{{ $step['h'] }} = {{ $fmt($step['band'], 6) }}<br>Published estimate = <strong>{{ $fmt($trace['projections'][$index]['value']) }} {{ $unit }}</strong><br>Lower = round(max(0, {{ $fmt($step['value'], 6) }} − {{ $fmt($step['band'], 6) }}), 2) = <strong>{{ $fmt($trace['projections'][$index]['min']) }}</strong><br>Upper = round({{ $fmt($step['value'], 6) }} + {{ $fmt($step['band'], 6) }}, 2) = <strong>{{ $fmt($trace['projections'][$index]['max']) }}</strong></div>
    @endforeach
    <p class="calc-small">Use full-precision values if reproducing this calculation in a spreadsheet. Reusing only the rounded coefficients or the displayed 6-decimal working can slightly change the last digit.</p>
</div></details>
<details class="calc-details" open><summary>Step 8 · Solve the upward / downward / stable label</summary><div class="calc-details-body">
    <div class="calc-equation">Recent actual values = [{{ implode(', ', $trace['baseline_values']) }}]<br>B = {{ $fmt(array_sum($trace['baseline_values']), 6) }} / {{ count($trace['baseline_values']) }} = <strong>{{ $fmt($trace['baseline'], 6) }}</strong><br>Lower threshold = B × (1 − {{ $settings['threshold'] / 100 }}) = {{ $fmt($trace['lower_threshold'], 6) }}<br>Upper threshold = B × (1 + {{ $settings['threshold'] / 100 }}) = {{ $fmt($trace['upper_threshold'], 6) }}<br>Final rounded forecast = <strong>{{ $fmt($trace['final']) }}</strong><br>@if ($trace['change_percent'] !== null)Change from baseline = ({{ $fmt($trace['final']) }} − {{ $fmt($trace['baseline'], 6) }}) / {{ $fmt($trace['baseline'], 6) }} × 100 = <strong>{{ $fmt($trace['change_percent'], 4) }}%</strong>@elsePercentage change is undefined because the baseline is 0. The value comparisons still determine the label.@endif</div>
    <div class="calc-result"><span class="calc-trend calc-trend-{{ $trace['trend'] }}">{{ $trendSymbol }} {{ $trendText }}</span>
        <h3>@if ($trace['trend'] === 'upward')The final prediction is above the upper threshold.@elseif ($trace['trend'] === 'downward')The final prediction is below the lower threshold.@elseThe final prediction is inside the trend band.@endif</h3>
        <p><strong>{{ $fmt($trace['final']) }} {{ $unit }}</strong> is compared with the recent actual baseline of <strong>{{ $fmt($trace['baseline']) }} {{ $unit }}</strong>.
            Every forecast row receives this one overall label. The label does not promise a continuous rise or fall between forecast steps.</p>
    </div>
</div></details>
