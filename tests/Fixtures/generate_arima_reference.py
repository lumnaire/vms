"""Generate independent numerical references; Python is not a production dependency.

Run with NumPy, SciPy and statsmodels installed:
    python tests/Fixtures/generate_arima_reference.py
"""

import json
import warnings
from pathlib import Path

import numpy as np
import statsmodels
from statsmodels.tsa.arima.model import ARIMA


def reference(levels):
    levels = np.asarray(levels, dtype=float)
    changes = np.diff(levels)
    model = ARIMA(changes, order=(1, 0, 1), trend="c", concentrate_scale=True)
    candidates = []
    for phi in (-0.6, 0.0, 0.6):
        for theta in (-0.6, 0.0, 0.6):
            with warnings.catch_warnings():
                warnings.simplefilter("ignore")
                result = model.fit(
                    start_params=[changes.mean(), phi, theta],
                    method_kwargs={"method": "bfgs", "maxiter": 2000, "gtol": 1e-9, "disp": 0},
                )
            candidates.append(result)
    result = max(candidates, key=lambda item: item.llf)
    drift, phi, theta = result.params
    # Independent state-space integration supplies level forecast variances,
    # including AR/MA effects. Approximate diffuse initialization adds a tiny
    # numerical difference relative to conditioning on the first level.
    integrated = ARIMA(levels, order=(1, 1, 1), trend="t").filter(
        [drift, phi, theta, result.scale]
    )
    forecast = integrated.get_forecast(steps=4)
    return {
        "series": levels.tolist(),
        "phi": float(phi), "theta": float(theta), "mean": float(drift),
        "sigma2": float(result.scale), "log_likelihood": float(result.llf),
        "forecast": forecast.predicted_mean.tolist(),
        "forecast_variance": forecast.var_pred_mean.tolist(),
    }


rng = np.random.default_rng(20261124)
noise = rng.normal(size=80)
changes = []
previous = previous_noise = 0.0
for innovation in noise:
    previous = 0.45 * previous + innovation + 0.35 * previous_noise
    previous_noise = innovation
    changes.append(previous + 0.8)
levels = np.r_[200.0, 200.0 + np.cumsum(changes[-29:])]
output = {
    "source": "statsmodels ARIMA maximum likelihood; ARMA(1,1) on first differences with a fitted mean",
    "statsmodels_version": statsmodels.__version__,
    "cases": {
        "worked": reference([100, 101, 103, 102, 105, 104, 108, 107, 111, 110]),
        "thirty_days": reference(levels),
    },
}
Path(__file__).with_name("arima_mle_reference.json").write_text(
    json.dumps(output, indent=2) + "\n", encoding="utf-8"
)
print("Wrote statsmodels reference fixtures")
