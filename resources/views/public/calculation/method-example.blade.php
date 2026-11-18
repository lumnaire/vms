@php
    $sampleList = fn ($values, $places = 2) => implode(', ', array_map(fn ($value) => $fmt($value, $places), $values));
    $sampleFirst = $sample['difference_steps'][0];
    $sampleLast = $sample['difference_steps'][count($sample['difference_steps']) - 1];
    $samplePhi = $sample['phi_fit']['coefficient'];
    $sampleTheta = $sample['theta_fit']['coefficient'];
    $sampleMean = $sample['mean_diff'];
    $sampleCentered = array_map(fn ($difference) => $difference - $sampleMean, $sample['differences']);
    $sampleFirstPair = $sampleCentered[0] * $sampleCentered[1];
    $sampleValues = array_column($sample['projections'], 'value');
    $sampleLow = min($sampleValues);
    $sampleHigh = max($sampleValues);
    $sampleAverage = round(array_sum($sampleValues) / count($sampleValues), 2);
    $sampleSum = implode(' + ', array_map(fn ($value) => $fmt($value), $sampleValues));
@endphp
<div id="arima-worked-example" aria-labelledby="arima-worked-example-title">
    <h3 id="arima-worked-example-title">Sample computation: Pusit · Special Class</h3>
    <p>Follow the same ten formulas above using these <strong>illustrative daily average prices in ₱/kg</strong>.
        These are sample observations, not live Pusit records. Every result is calculated by the system’s forecasting service.</p>
    <div class="calc-equation">Daily prices y = [{{ $sampleList($sample['observations']) }}]<br>
        n = {{ count($sample['observations']) }} recorded days; m = n − 1 = {{ count($sample['differences']) }} changes</div>

    <h3>Step 1 · Substitute the prices into the difference formula</h3>
    <div class="calc-equation">First change = {{ $fmt($sampleFirst['value']) }} − {{ $fmt($sampleFirst['previous_value']) }} = <strong>{{ $fmt($sampleFirst['difference'], 6) }}</strong><br>
        All changes Δy = [{{ $sampleList($sample['differences']) }}]</div>
    <p class="calc-small">For example, the price moves from ₱300.00 to ₱302.00, so the first difference is +₱2.00/kg.</p>

    <h3>Step 2 · Compute the mean change μ</h3>
    <div class="calc-equation">Sum of changes = {{ $fmt(array_sum($sample['differences']), 6) }}<br>
        μ = {{ $fmt(array_sum($sample['differences']), 6) }} ÷ {{ count($sample['differences']) }} = <strong>{{ $fmt($sampleMean, 6) }}</strong></div>
    <p class="calc-small">The drift is the average change between recorded observations, not the average of the original prices.</p>

    <h3>Step 3 · Compute the AR coefficient φ</h3>
    <p class="calc-small">Subtract μ from each change, multiply neighboring centered changes, then divide the sum of those products by the sum of all squared centered changes.</p>
    <div class="calc-equation">First centered change = {{ $fmt($sample['differences'][0], 6) }} − {{ $fmt($sampleMean, 6) }} = {{ $fmt($sampleCentered[0], 6) }}<br>
        Next centered change = {{ $fmt($sample['differences'][1], 6) }} − {{ $fmt($sampleMean, 6) }} = {{ $fmt($sampleCentered[1], 6) }}<br>
        First neighboring product = ({{ $fmt($sampleCentered[0], 6) }}) × ({{ $fmt($sampleCentered[1], 6) }}) = {{ $fmt($sampleFirstPair, 6) }}<br>
        Sum of neighboring products = {{ $fmt($sample['phi_fit']['numerator'], 6) }}<br>
        Sum of squared centered changes = {{ $fmt($sample['phi_fit']['denominator'], 6) }}<br>
        φ = clamp({{ $fmt($sample['phi_fit']['numerator'], 6) }} ÷ {{ $fmt($sample['phi_fit']['denominator'], 6) }}, −0.99, 0.99) = <strong>{{ $fmt($samplePhi, 6) }}</strong></div>

    <h3>Step 4 · Compute the residuals</h3>
    <p class="calc-small">Use the previous actual change in each residual formula. For the first residual only, initialize that previous change to μ.</p>
    <div class="calc-equation">First residual = ({{ $fmt($sampleFirst['difference'], 6) }} − {{ $fmt($sampleMean, 6) }}) − ({{ $fmt($samplePhi, 6) }}) × ({{ $fmt($sampleMean, 6) }} − {{ $fmt($sampleMean, 6) }})<br>
        = <strong>{{ $fmt($sampleFirst['residual'], 6) }}</strong><br>
        Last residual = ({{ $fmt($sampleLast['difference'], 6) }} − {{ $fmt($sampleMean, 6) }}) − ({{ $fmt($samplePhi, 6) }}) × ({{ $fmt($sampleLast['previous_difference'], 6) }} − {{ $fmt($sampleMean, 6) }})<br>
        = <strong>{{ $fmt($sampleLast['residual'], 6) }}</strong><br>
        All residuals e = [{{ $sampleList($sample['residuals'], 6) }}]</div>

    <h3>Step 5 · Compute the MA coefficient θ</h3>
    <p class="calc-small">Repeat the neighboring-product calculation on the residuals, centered around their own mean ē.</p>
    <div class="calc-equation">ē = {{ $fmt(array_sum($sample['residuals']), 6) }} ÷ {{ count($sample['residuals']) }} = {{ $fmt($sample['residual_mean'], 6) }}<br>
        First centered residual = {{ $fmt($sample['residuals'][0], 6) }} − {{ $fmt($sample['residual_mean'], 6) }} = {{ $fmt($sample['residuals'][0] - $sample['residual_mean'], 6) }}<br>
        Sum of neighboring centered-residual products = {{ $fmt($sample['theta_fit']['numerator'], 6) }}<br>
        Sum of squared centered residuals = {{ $fmt($sample['theta_fit']['denominator'], 6) }}<br>
        θ = clamp({{ $fmt($sample['theta_fit']['numerator'], 6) }} ÷ {{ $fmt($sample['theta_fit']['denominator'], 6) }}, −0.99, 0.99) = <strong>{{ $fmt($sampleTheta, 6) }}</strong></div>

    <h3>Step 6 · Compute the residual spread σ</h3>
    <div class="calc-equation">Sample variance = {{ $fmt($sample['theta_fit']['denominator'], 6) }} ÷ ({{ count($sample['residuals']) }} − 1)<br>
        = {{ $fmt($sample['residual_variance'], 6) }}<br>
        σ = √{{ $fmt($sample['residual_variance'], 6) }} = <strong>{{ $fmt($sample['sigma'], 6) }}</strong></div>

    <h3>Step 7 · Substitute the numbers for each future change</h3>
    @foreach ($sample['forecast_steps'] as $sampleStep)
        <div class="calc-equation"><strong>Forecast Day {{ $sampleStep['h'] }}</strong><br>
            δ = {{ $fmt($sampleMean, 6) }} + ({{ $fmt($samplePhi, 6) }}) × ({{ $fmt($sampleStep['previous_difference'], 6) }} − {{ $fmt($sampleMean, 6) }}) + ({{ $fmt($sampleTheta, 6) }}) × {{ $fmt($sampleStep['previous_residual'], 6) }}<br>
            = {{ $fmt($sampleMean, 6) }} + ({{ $fmt($sampleStep['ar_contribution'], 6) }}) + ({{ $fmt($sampleStep['ma_contribution'], 6) }})<br>
            = <strong>{{ $fmt($sampleStep['difference'], 6) }} ₱/kg</strong></div>
    @endforeach
    <p class="calc-small">The first step uses the last fitted residual. Later steps use zero for the unknown future residual and carry forward the previous unrounded forecast change.</p>

    <h3>Step 8 · Add each change back to the price</h3>
    <div class="calc-table-wrap"><table class="calc-table">
        <caption>Rebuilding the predicted price · Pusit, Special Class</caption>
        <thead><tr><th scope="col">Day</th><th scope="col">Substitute into max(0, previous price + change)</th><th scope="col" class="calc-num">Published price (₱/kg)</th></tr></thead>
        <tbody>
            @foreach ($sample['forecast_steps'] as $sampleIndex => $sampleStep)
                <tr><td>{{ $sampleStep['h'] }}</td><td>max(0, {{ $fmt($sampleStep['previous_value'], 6) }} + ({{ $fmt($sampleStep['difference'], 6) }})) = {{ $fmt($sampleStep['value'], 6) }}</td><td class="calc-num"><strong>₱{{ $fmt($sample['projections'][$sampleIndex]['value']) }}</strong></td></tr>
            @endforeach
        </tbody>
    </table></div>
    <p class="calc-small">Later days start from the unrounded prediction in the previous row. Only the published prices are rounded to 2 decimals.</p>

    <h3>Step 9 · Compute the approximate prediction ranges</h3>
    @foreach ($sample['forecast_steps'] as $sampleIndex => $sampleStep)
        <div class="calc-equation"><strong>Forecast Day {{ $sampleStep['h'] }}</strong><br>
            Half-width = {{ $sample['z'] }} × {{ $fmt($sample['sigma'], 6) }} × √{{ $sampleStep['h'] }} = {{ $fmt($sampleStep['band'], 6) }}<br>
            Lower = max(0, {{ $fmt($sampleStep['value'], 6) }} − {{ $fmt($sampleStep['band'], 6) }}) → <strong>₱{{ $fmt($sample['projections'][$sampleIndex]['min']) }}</strong><br>
            Upper = {{ $fmt($sampleStep['value'], 6) }} + {{ $fmt($sampleStep['band'], 6) }} → <strong>₱{{ $fmt($sample['projections'][$sampleIndex]['max']) }}</strong></div>
    @endforeach
    <p class="calc-small">These are approximate ranges around each prediction, not the lowest and highest forecast values.</p>

    <h3>Step 10 · Compute the overall trend</h3>
    <div class="calc-equation">Recent actual prices = [{{ $sampleList($sample['baseline_values']) }}]<br>
        B = {{ $fmt(array_sum($sample['baseline_values']), 6) }} ÷ {{ count($sample['baseline_values']) }} = <strong>{{ $fmt($sample['baseline'], 6) }}</strong><br>
        Lower threshold = {{ $fmt($sample['baseline'], 6) }} × (1 − {{ $settings['threshold'] / 100 }}) = {{ $fmt($sample['lower_threshold'], 6) }}<br>
        Upper threshold = {{ $fmt($sample['baseline'], 6) }} × (1 + {{ $settings['threshold'] / 100 }}) = {{ $fmt($sample['upper_threshold'], 6) }}<br>
        Final published forecast = <strong>₱{{ $fmt($sample['final']) }}</strong><br>
        @if ($sample['trend'] === 'upward')
            {{ $fmt($sample['final']) }} &gt; {{ $fmt($sample['upper_threshold'], 6) }} → <strong>Upward</strong>
        @elseif ($sample['trend'] === 'downward')
            {{ $fmt($sample['final']) }} &lt; {{ $fmt($sample['lower_threshold'], 6) }} → <strong>Downward</strong>
        @else
            {{ $fmt($sample['lower_threshold'], 6) }} ≤ {{ $fmt($sample['final']) }} ≤ {{ $fmt($sample['upper_threshold'], 6) }} → <strong>Stable</strong>
        @endif
    </div>

    <div class="calc-result">
        <h3>What the supervisor summary would show</h3>
        <p><strong>Avg Forecast:</strong> ({{ $sampleSum }}) ÷ {{ count($sampleValues) }} = {{ $fmt(array_sum($sampleValues)) }} ÷ {{ count($sampleValues) }} = <strong>₱{{ $fmt($sampleAverage) }}/kg</strong>.<br>
            <strong>Lowest:</strong> ₱{{ $fmt($sampleLow) }}/kg on Day {{ array_search($sampleLow, $sampleValues, true) + 1 }}.<br>
            <strong>Highest:</strong> ₱{{ $fmt($sampleHigh) }}/kg on Day {{ array_search($sampleHigh, $sampleValues, true) + 1 }}.<br>
            <strong>{{ count($sampleValues) }}-Day Trend:</strong> {{ ucfirst($sample['trend']) }}.</p>
    </div>
    <p class="calc-small">Intermediate numbers above are displayed to 6 decimals for readability. The service retains full precision internally.
        The separate calculator below lets you try another series or inspect every intermediate table.</p>
</div>
