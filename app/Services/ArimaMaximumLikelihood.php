<?php

namespace App\Services;

/**
 * Exact Gaussian maximum likelihood for a stationary, invertible ARMA(1,1)
 * on first differences: x[t] - mu = phi*(x[t-1] - mu) + e[t] + theta*e[t-1].
 *
 * The innovations/Kalman likelihood includes stationary initialization. Drift
 * and innovation variance are profiled analytically; phi/theta are optimized
 * together using the Nelder-Mead algorithm with multiple starting points.
 * This is a PHP implementation of a standard estimator, not an ACF shortcut.
 *
 * Method basis: Mardesci & Fitriani (2025), section 2.1, p. 77 (MLE);
 * https://ejournal.cibnusantara.org/agrivolution/article/download/33/35
 * Likelihood reference: R stats::arima / stats::KalmanLike documentation.
 */
class ArimaMaximumLikelihood
{
    public const VERSION = 'arima111-exact-mle-v1';

    /** @param array<int, float> $differences */
    public function fit(array $differences): array
    {
        $count = count($differences);
        $mean = array_sum($differences) / $count;
        $scale = max(array_map(fn ($value) => abs($value - $mean), $differences));

        // A constant first difference has zero innovation variance: the finite
        // Gaussian MLE is undefined. Expose this deterministic limiting case.
        if ($scale < 1e-12) {
            return [
                'phi' => 0.0, 'theta' => 0.0, 'mean' => $mean, 'sigma2' => 0.0,
                'innovations' => array_fill(0, $count, 0.0),
                'factors' => array_fill(0, $count, 1.0),
                'weighted_sse' => 0.0, 'log_determinant' => 0.0,
                'last_shock' => 0.0, 'last_shock_variance' => 0.0,
                'log_likelihood' => null, 'aic' => null,
                'converged' => true, 'status' => 'deterministic',
            ];
        }

        // Scaling improves numerical conditioning without changing the fitted
        // AR/MA coefficients or the maximum-likelihood estimator.
        $normalized = array_map(fn ($value) => ($value - $mean) / $scale, $differences);
        $objective = fn ($point) => $this->likelihood($normalized, $point[0], $point[1])['objective'];
        $best = null;
        foreach ([-0.6, 0.0, 0.6] as $phi) {
            foreach ([-0.6, 0.0, 0.6] as $theta) {
                $candidate = $this->minimize($objective, [$phi, $theta]);
                if ($best === null || $candidate['value'] < $best['value']) {
                    $best = $candidate;
                }
            }
        }

        $fit = $this->likelihood($normalized, $best['point'][0], $best['point'][1]);
        $sigma2 = $fit['sigma2'] * $scale ** 2;
        $innovations = array_map(fn ($value) => $value * $scale, $fit['innovations']);
        $lastFactor = $fit['factors'][$count - 1];
        $logLikelihood = -$fit['objective'] - $count * log($scale);

        return [
            'phi' => $best['point'][0],
            'theta' => $best['point'][1],
            'mean' => $mean + $fit['mean'] * $scale,
            'sigma2' => $sigma2,
            'innovations' => $innovations,
            'factors' => $fit['factors'],
            'weighted_sse' => $fit['weighted_sse'] * $scale ** 2,
            'log_determinant' => $fit['log_determinant'],
            'last_shock' => $innovations[$count - 1] / $lastFactor,
            'last_shock_variance' => $sigma2 * max(0.0, 1 - 1 / $lastFactor),
            'log_likelihood' => $logLikelihood,
            'aic' => -2 * $logLikelihood + 2 * 4,
            'converged' => $best['converged'],
            'status' => $best['converged'] ? 'converged' : 'not_converged',
        ];
    }

    /**
     * Concentrated exact likelihood. Apply the same linear innovations filter
     * to the observations and to a vector of ones to obtain the GLS drift.
     * Unit-variance Kalman state is [centered change, current shock]. Its
     * stationary covariance is [[(1+theta²+2*phi*theta)/(1-phi²), 1], [1, 1]].
     */
    private function likelihood(array $values, float $phi, float $theta): array
    {
        if (abs($phi) >= 1 - 1e-8 || abs($theta) >= 1 - 1e-8) {
            return ['objective' => INF];
        }

        $factor = (1 + $theta ** 2 + 2 * $phi * $theta) / (1 - $phi ** 2);
        $dataState = 0.0;
        $meanState = 0.0;
        $numerator = 0.0;
        $denominator = 0.0;
        $logDeterminant = 0.0;
        $dataErrors = $meanErrors = $factors = [];

        foreach ($values as $value) {
            if ($factor <= 0 || ! is_finite($factor)) {
                return ['objective' => INF];
            }
            $dataError = $value - $dataState;
            $meanError = 1 - $meanState;
            $numerator += $dataError * $meanError / $factor;
            $denominator += $meanError ** 2 / $factor;
            $logDeterminant += log($factor);
            $dataErrors[] = $dataError;
            $meanErrors[] = $meanError;
            $factors[] = $factor;
            $dataState = $phi * $value + $theta * $dataError / $factor;
            $meanState = $phi + $theta * $meanError / $factor;
            $factor = 1 + $theta ** 2 * max(0.0, 1 - 1 / $factor);
        }

        $mean = $numerator / $denominator;
        $weightedSse = 0.0;
        $innovations = [];
        foreach ($dataErrors as $index => $error) {
            $innovation = $error - $mean * $meanErrors[$index];
            $innovations[] = $innovation;
            $weightedSse += $innovation ** 2 / $factors[$index];
        }
        $count = count($values);
        $sigma2 = max(PHP_FLOAT_MIN, $weightedSse / $count);

        return [
            'objective' => 0.5 * ($count * (log(2 * M_PI) + 1 + log($sigma2)) + $logDeterminant),
            'mean' => $mean, 'sigma2' => $sigma2, 'innovations' => $innovations,
            'factors' => $factors, 'weighted_sse' => $weightedSse,
            'log_determinant' => $logDeterminant,
        ];
    }

    /** Two-dimensional Nelder-Mead minimization; no coefficient ACF estimates. */
    private function minimize(callable $objective, array $start): array
    {
        $vertices = [$start, [$start[0] + 0.08, $start[1]], [$start[0], $start[1] + 0.08]];
        $simplex = array_map(fn ($point) => ['point' => $point, 'value' => $objective($point)], $vertices);
        $converged = false;
        for ($iteration = 0; $iteration < 600; $iteration++) {
            usort($simplex, fn ($a, $b) => $a['value'] <=> $b['value']);
            $spread = max(
                abs($simplex[2]['point'][0] - $simplex[0]['point'][0]),
                abs($simplex[2]['point'][1] - $simplex[0]['point'][1]),
                abs($simplex[1]['point'][0] - $simplex[0]['point'][0]),
                abs($simplex[1]['point'][1] - $simplex[0]['point'][1]),
            );
            if ($spread < 1e-8 && abs($simplex[2]['value'] - $simplex[0]['value']) < 1e-8) {
                $converged = true;
                break;
            }

            $centroid = [
                ($simplex[0]['point'][0] + $simplex[1]['point'][0]) / 2,
                ($simplex[0]['point'][1] + $simplex[1]['point'][1]) / 2,
            ];
            $trial = function (float $factor) use ($centroid, $simplex, $objective): array {
                $point = [
                    $centroid[0] + $factor * ($centroid[0] - $simplex[2]['point'][0]),
                    $centroid[1] + $factor * ($centroid[1] - $simplex[2]['point'][1]),
                ];

                return ['point' => $point, 'value' => $objective($point)];
            };
            $reflected = $trial(1.0);
            if ($reflected['value'] < $simplex[0]['value']) {
                $expanded = $trial(2.0);
                $simplex[2] = $expanded['value'] < $reflected['value'] ? $expanded : $reflected;
            } elseif ($reflected['value'] < $simplex[1]['value']) {
                $simplex[2] = $reflected;
            } else {
                $outside = $reflected['value'] < $simplex[2]['value'];
                $contracted = $trial($outside ? 0.5 : -0.5);
                if ($contracted['value'] < ($outside ? $reflected['value'] : $simplex[2]['value'])) {
                    $simplex[2] = $contracted;
                } else {
                    foreach ([1, 2] as $index) {
                        $point = [
                            ($simplex[0]['point'][0] + $simplex[$index]['point'][0]) / 2,
                            ($simplex[0]['point'][1] + $simplex[$index]['point'][1]) / 2,
                        ];
                        $simplex[$index] = ['point' => $point, 'value' => $objective($point)];
                    }
                }
            }
        }
        usort($simplex, fn ($a, $b) => $a['value'] <=> $b['value']);

        return $simplex[0] + ['converged' => $converged];
    }
}
