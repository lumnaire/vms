@php
    $sampleList = fn ($values, $places = 2) => implode(', ', array_map(fn ($value) => $fmt($value, $places), $values));
    $fit = $sample['fit'];
    $sampleValues = array_column($sample['projections'], 'value');
    $sampleSum = implode(' + ', array_map(fn ($value) => $fmt($value), $sampleValues));
@endphp
<div id="arima-worked-example" aria-labelledby="arima-worked-example-title">
    <h3 id="arima-worked-example-title">Sample computation: Pusit · Special Class</h3>
    <p>These are <strong>illustrative daily average prices in ₱/kg</strong>, not live Pusit records.
        Every displayed calculation comes from the production forecasting service.</p>
    <div class="calc-equation">Daily prices y = [{{ $sampleList($sample['observations']) }}]</div>
    <h3>Step 1 · Difference the prices</h3>
    <div class="calc-equation">{{ $fmt($sample['observations'][1]) }} − {{ $fmt($sample['observations'][0]) }} = {{ $fmt($sample['differences'][0], 6) }}<br>
        Changes = [{{ $sampleList($sample['differences'], 6) }}]</div>
    <h3>Step 2 · Fit drift and both coefficients by maximum likelihood</h3>
    <div class="calc-equation">Sample mean change = {{ $fmt($sample['sample_mean_diff'], 6) }}<br>
        Fitted drift μ = <strong>{{ $fmt($fit['mean'], 6) }}</strong><br>
        Fit status = {{ $fit['status'] }}<br>
        Log likelihood = {{ $fit['log_likelihood'] === null ? 'undefined for a zero-variance sequence' : $fmt($fit['log_likelihood'], 6) }}</div>
    <h3>Step 3 · Read the optimized AR coefficient</h3>
    <div class="calc-equation">φ = <strong>{{ $fmt($fit['phi'], 6) }}</strong></div>
    <p class="calc-small">This value is a likelihood optimum, not the lag-1 autocorrelation.</p>
    <h3>Step 4 · Filter the prediction errors</h3>
    <div class="calc-table-wrap"><table class="calc-table">
        <thead><tr><th>Change</th><th>Fitted change</th><th>Innovation vₜ</th><th>Factor Fₜ</th></tr></thead>
        <tbody>@foreach ($sample['difference_steps'] as $step)<tr>
            <td>{{ $fmt($step['difference'], 6) }}</td><td>{{ $fmt($step['fitted_difference'], 6) }}</td>
            <td>{{ $fmt($step['residual'], 6) }}</td><td>{{ $fmt($step['innovation_factor'], 6) }}</td>
        </tr>@endforeach</tbody>
    </table></div>
    <h3>Step 5 · Read the jointly optimized MA coefficient</h3>
    <div class="calc-equation">θ = <strong>{{ $fmt($fit['theta'], 6) }}</strong><br>
        Last filtered shock = last innovation / last factor = {{ $fmt($fit['last_shock'], 6) }}</div>
    <h3>Step 6 · Estimate innovation variance</h3>
    <div class="calc-equation">σ² = Σ(vₜ² / Fₜ) / m = {{ $fmt($fit['weighted_sse'], 6) }} / {{ count($sample['differences']) }}<br>
        = {{ $fmt($fit['sigma2'], 6) }}<br>σ = <strong>{{ $fmt($sample['sigma'], 6) }}</strong></div>
    <h3>Step 7 · Predict the next changes</h3>
    @foreach ($sample['forecast_steps'] as $step)
        <div class="calc-equation">Day {{ $step['h'] }}: δ = μ + φ(previous Δ − μ) + θ(previous shock)<br>
            = {{ $fmt($fit['mean'], 6) }} + ({{ $fmt($step['ar_contribution'], 6) }}) + ({{ $fmt($step['ma_contribution'], 6) }})
            = <strong>{{ $fmt($step['difference'], 6) }}</strong></div>
    @endforeach
    <h3>Step 8 · Rebuild the price levels</h3>
    @foreach ($sample['forecast_steps'] as $step)
        <div class="calc-equation">Day {{ $step['h'] }}: {{ $fmt($step['previous_value'], 6) }} + ({{ $fmt($step['difference'], 6) }})
            = {{ $fmt($step['unclipped_value'], 6) }} → published <strong>₱{{ $fmt($step['value']) }}</strong></div>
    @endforeach
    <h3>Step 9 · Calculate model-based prediction intervals</h3>
    @foreach ($sample['forecast_steps'] as $index => $step)
        <div class="calc-equation">Day {{ $step['h'] }} variance Vₕ = {{ $fmt($step['forecast_variance'], 6) }}<br>
            Half-width = {{ $sample['z'] }} × √{{ $fmt($step['forecast_variance'], 6) }} = {{ $fmt($step['band'], 6) }}<br>
            Published interval = <strong>₱{{ $fmt($sample['projections'][$index]['min']) }}–₱{{ $fmt($sample['projections'][$index]['max']) }}</strong></div>
    @endforeach
    <h3>Step 10 · Compare with the recent actual baseline</h3>
    <div class="calc-equation">B = {{ $fmt(array_sum($sample['baseline_values']), 6) }} / {{ count($sample['baseline_values']) }}
        = {{ $fmt($sample['baseline'], 6) }}<br>
        Stable band = {{ $fmt($sample['lower_threshold'], 6) }}–{{ $fmt($sample['upper_threshold'], 6) }}<br>
        Final published forecast = <strong>₱{{ $fmt($sample['final']) }}</strong> → <strong>{{ ucfirst($sample['trend']) }}</strong></div>
    <div class="calc-result"><h3>What the supervisor summary would show</h3>
        <p><strong>Avg Forecast:</strong> ({{ $sampleSum }}) ÷ {{ count($sampleValues) }} = <strong>₱{{ $fmt(array_sum($sampleValues) / count($sampleValues)) }}/kg</strong>.<br>
            <strong>Lowest:</strong> ₱{{ $fmt(min($sampleValues)) }}/kg on Day {{ array_search(min($sampleValues), $sampleValues, true) + 1 }}.<br>
            <strong>Highest:</strong> ₱{{ $fmt(max($sampleValues)) }}/kg on Day {{ array_search(max($sampleValues), $sampleValues, true) + 1 }}.</p>
    </div>
    <p class="calc-small">Intermediate values are displayed to 6 decimals; the service retains full precision. The illustrative origin is its last observation.
        Production forecasts also bridge today before publishing tomorrow onward.</p>
</div>
