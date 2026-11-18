<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\VendorInventory;
use App\Services\ArimaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalculationController extends Controller
{
    public function index(Request $request, ArimaService $arima)
    {
        $settings = [
            'horizon' => (int) config('forecast.horizon'),
            'minimum' => (int) config('forecast.min_history'),
            'history' => (int) config('forecast.history_days'),
            'chart_days' => (int) config('forecast.history_chart_days'),
            'baseline_days' => (int) config('forecast.trend_baseline_days'),
            'threshold' => (float) config('forecast.trend_threshold') * 100,
            'z' => (float) config('forecast.z'),
            'freshness' => VendorInventory::freshnessDays(),
            'timezone' => config('app.timezone'),
        ];

        $maximum = max($settings['minimum'], $settings['history']);
        $examples = $this->examples(max(10, $settings['minimum']));
        $validator = Validator::make($request->query(), [
            'example' => ['sometimes', 'string', 'in:'.implode(',', array_keys($examples))],
            'metric' => ['sometimes', 'string', 'in:price,supply'],
            'series' => ['nullable', 'string', 'max:4000'],
        ]);

        $inputErrors = $validator->errors()->all();
        $example = $validator->fails() ? 'worked' : $request->query('example', 'worked');
        $metric = $validator->fails() ? 'price' : $request->query('metric', 'price');
        $seriesInput = is_string($request->query('series')) ? $request->query('series') : '';
        $series = $examples[$example]['values'];
        $custom = false;

        if ($inputErrors === [] && trim($seriesInput) !== '') {
            $values = preg_split('/[\s,;]+/', trim($seriesInput), -1, PREG_SPLIT_NO_EMPTY);
            $seriesValidator = Validator::make(['values' => $values], [
                'values' => ['required', 'array', 'min:'.$settings['minimum'], 'max:'.$maximum],
                'values.*' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            ], [
                'values.min' => 'Enter at least '.$settings['minimum'].' daily observations.',
                'values.max' => 'Use no more than '.$maximum.' daily observations.',
                'values.*.numeric' => 'Every observation must be a number. Separate values with commas or spaces.',
                'values.*.min' => 'Observations cannot be negative.',
                'values.*.max' => 'Each observation must be no greater than 1,000,000,000.',
            ]);

            $inputErrors = array_values(array_unique($seriesValidator->errors()->all()));
            if ($inputErrors === []) {
                $series = array_map('floatval', $values);
                $custom = true;
            }
        }

        // These are illustrative observations only. Never read or write a
        // vendor's inventory, sales, or stored forecasts from this public page.
        $trace = $arima->explain($series);
        $methodExample = $arima->explain(array_map(
            fn ($value) => 100 + 2 * $value,
            $examples['worked']['values'],
        ));

        return response()->view('public.calculation', compact(
            'settings', 'examples', 'example', 'metric', 'seriesInput',
            'inputErrors', 'custom', 'trace', 'maximum', 'methodExample',
        ), $inputErrors === [] ? 200 : 422);
    }

    private function examples(int $length): array
    {
        $worked = [100, 101, 103, 102, 105, 104, 108, 107, 111, 110];
        while (count($worked) < $length) {
            $worked[] = end($worked) + 1;
        }

        return [
            'worked' => ['label' => 'Worked example', 'values' => $worked],
            'upward' => ['label' => 'Rising history', 'values' => array_map(fn ($i) => 100 + 2 * $i, range(0, $length - 1))],
            'downward' => ['label' => 'Falling history', 'values' => array_reverse($worked)],
            'stable' => ['label' => 'Stable history', 'values' => array_fill(0, $length, 100)],
            'zero' => ['label' => 'All-zero history', 'values' => array_fill(0, $length, 0)],
        ];
    }
}
