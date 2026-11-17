<section id="arima-lab" class="calc-section" data-guide-section>
    <div class="calc-eyebrow">05 · Follow the actual arithmetic</div>
    <h2>Solve a forecast, step by step.</h2>
    <p class="calc-lead">Choose an example or enter your own daily observations, oldest first. The same production service computes the model,
        the prediction range and the up/down label. Each example runs independently of live market records.</p>
    <nav class="calc-presets" aria-label="Forecast examples">
        @foreach ($examples as $key => $item)
            <a href="{{ route('calculation', ['example' => $key, 'metric' => $metric]) }}#arima-lab" @if (!$custom && $example === $key) aria-current="true" @endif>{{ $item['label'] }}</a>
        @endforeach
    </nav>
    @if ($inputErrors)
        <div class="calc-error" role="alert" id="calculation-errors">
            <strong>Your input needs a correction.</strong>
            <ul class="calc-list">@foreach ($inputErrors as $inputError)<li>{{ $inputError }}</li>@endforeach</ul>
            <p>The working below uses the selected example instead. Your invalid input was not calculated.</p>
        </div>
    @endif
    <form method="GET" action="{{ route('calculation') }}#arima-lab" class="calc-form">
        <label for="calculation-series">Daily observations, oldest → newest</label>
        <textarea id="calculation-series" name="series" maxlength="4000" aria-describedby="calculation-series-help" @if ($inputErrors) aria-invalid="true" @endif>{{ $seriesInput !== '' ? $seriesInput : implode(', ', $trace['observations']) }}</textarea>
        <p class="calc-form-hint" id="calculation-series-help">Enter {{ $settings['minimum'] }}–{{ $maximum }} nonnegative numbers, separated by commas, spaces or new lines.
            Each number is one already-aggregated day. Use a dot for decimals and no thousands separators. Maximum value: 1,000,000,000.</p>
        <div class="calc-form-row">
            <div><label for="calculation-metric">These observations represent</label><select id="calculation-metric" name="metric"><option value="price" @selected($metric === 'price')>Average daily price (₱/kg)</option><option value="supply" @selected($metric === 'supply')>Total daily incoming supply (kg)</option></select></div>
            <button class="calc-btn calc-btn-primary" type="submit"><x-icon name="bi-calculator" /> Calculate &amp; explain</button>
        </div>
        <p class="calc-form-hint">Switching the metric changes the interpretation and unit, not the numeric observations. No inventory or forecast records are saved.</p>
    </form>
    <div class="calc-actions calc-no-print" style="margin-top: 18px">
        <button type="button" class="calc-btn" data-guide-expand aria-expanded="false">Expand detailed working</button>
        <span class="calc-small">Results below: {{ $custom ? 'your custom example' : $examples[$example]['label'] }} · {{ $unit }}</span>
    </div>
    @include('public.calculation.trace')
</section>
