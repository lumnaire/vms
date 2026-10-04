<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Forecast Horizon
    |--------------------------------------------------------------------------
    |
    | Number of days projected forward from today. Day 1 of the horizon is
    | tomorrow; day N is today + N. This single value drives the ARIMA engine,
    | the artisan command, the seeder and the supervisor forecast page.
    |
    */

    'horizon' => (int) env('FORECAST_HORIZON', 3),

    /*
    |--------------------------------------------------------------------------
    | Minimum History
    |--------------------------------------------------------------------------
    |
    | Minimum number of daily observations required before a series is
    | forecast. Series with fewer points are skipped.
    |
    */

    'min_history' => (int) env('FORECAST_MIN_HISTORY', 7),

    /*
    |--------------------------------------------------------------------------
    | History Lookback
    |--------------------------------------------------------------------------
    |
    | Number of past days of confirmed vendor inventory used to fit the model.
    |
    */

    'history_days' => (int) env('FORECAST_HISTORY_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Historical Chart Window
    |--------------------------------------------------------------------------
    |
    | Number of past confirmed days overlaid behind the forecast on the
    | supervisor forecast chart.
    |
    */

    'history_chart_days' => (int) env('FORECAST_HISTORY_CHART_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | The ARIMA metrics generated for every fish_type x quality_class pair.
    |   price   — average price_per_kg per day          (unit: PHP/kg)
    |   supply  — total stock_kg brought in per day     (unit: kg)
    |
    | Demand was dropped: it was derived from sold_kg, which is itself filled in
    | from the vendor's end-of-day sale report, so forecasting it was circular.
    |
    */

    'metrics' => ['price', 'supply'],

    /*
    |--------------------------------------------------------------------------
    | Metric Metadata
    |--------------------------------------------------------------------------
    |
    | Display label, short label and unit for each metric, keyed by metric.
    | Used by the supervisor forecast filters, stat cards and chart.
    |
    */

    'metric_meta' => [
        'price' => [
            'label' => 'Price (₱/kg)',
            'short' => 'Price',
            'chart' => 'Price Forecast',
            'unit' => '₱/kg',
            'prefix' => '₱',
        ],
        'supply' => [
            'label' => 'Supply (kg)',
            'short' => 'Supply',
            'chart' => 'Supply Forecast',
            'unit' => 'kg',
            'prefix' => '',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ARIMA Order
    |--------------------------------------------------------------------------
    |
    | Model order used for every series. ARIMA(1,1,1) — one autoregressive
    | term, first differencing, one moving-average term.
    |
    */

    'order' => [
        'p' => 1,
        'd' => 1,
        'q' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Confidence Interval
    |--------------------------------------------------------------------------
    |
    | Z multiplier for the prediction interval. 1.96 gives a 95% interval.
    |
    */

    'z' => 1.96,

    /*
    |--------------------------------------------------------------------------
    | Trend Threshold
    |--------------------------------------------------------------------------
    |
    | Fractional band used to classify a series as upward/downward. A series
    | is "upward" when the last projected value exceeds the first by more
    | than this fraction, and "downward" when it falls further than it.
    |
    */

    'trend_threshold' => 0.02,

];
