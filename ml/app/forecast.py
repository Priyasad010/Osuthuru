"""Daily sales forecasting with a simple fallback for short histories."""
import warnings
from datetime import date, timedelta

import numpy as np
import pandas as pd
from statsmodels.tsa.holtwinters import ExponentialSmoothing

MIN_DAYS = 30  # below this a moving average is used
WINDOW = 7
Z95 = 1.96


def daily_series(sales: pd.DataFrame, column: str, today: date) -> np.ndarray:
    """Totals for every day from the first sale to yesterday, including empty days."""
    if sales.empty:
        return np.zeros(0)
    days = pd.to_datetime(sales["order_date"]).dt.normalize()
    totals = sales[column].astype(float).groupby(days).sum()
    end = pd.Timestamp(today) - pd.Timedelta(days=1)  # today is not complete yet
    if totals.index.min() > end:
        return np.zeros(0)
    index = pd.date_range(totals.index.min(), end, freq="D")
    return totals.reindex(index, fill_value=0.0).to_numpy()


def _moving_average(series: np.ndarray, horizon: int):
    recent = series[-WINDOW:]
    level = float(recent.mean()) if recent.size else 0.0
    spread = float(series.std(ddof=1)) if series.size > 1 else level
    values = np.full(horizon, level)
    return values, np.full(horizon, spread)


def _holt_winters(series: np.ndarray, horizon: int):
    with warnings.catch_warnings():
        warnings.simplefilter("ignore")  # convergence notes on sparse data
        model = ExponentialSmoothing(
            series, trend="add", damped_trend=True, seasonal="add", seasonal_periods=7,
            initialization_method="estimated",
        ).fit()
    values = np.asarray(model.forecast(horizon), dtype=float)
    spread = float(np.std(series - model.fittedvalues, ddof=1))
    if not np.isfinite(values).all() or not np.isfinite(spread):
        raise ValueError("unstable fit")
    # Uncertainty grows the further ahead the day is
    return values, spread * np.sqrt(1 + np.arange(horizon) / 7)


def forecast(series: np.ndarray, horizon: int, today: date) -> dict:
    method = "moving_average"
    values = spread = None
    if series.size >= MIN_DAYS and series.any():
        try:
            values, spread = _holt_winters(series, horizon)
            method = "holt_winters"
        except Exception:  # fall back rather than fail
            values = None
    if values is None:
        values, spread = _moving_average(series, horizon)

    values = np.clip(values, 0, None)
    lower = np.clip(values - Z95 * spread, 0, None)
    upper = values + Z95 * spread
    return {
        "method": method,
        "history_days": int(series.size),
        "days": [
            {
                "date": (today + timedelta(days=i)).isoformat(),
                "value": round(float(values[i]), 2),
                "lower": round(float(lower[i]), 2),
                "upper": round(float(upper[i]), 2),
            }
            for i in range(horizon)
        ],
    }


def stock_outlook(series: np.ndarray, stock: int, today: date, horizon: int = 30) -> dict:
    result = forecast(series, horizon, today)
    demand = sum(d["value"] for d in result["days"]) / horizon
    if stock <= 0:
        days_left = 0
    elif demand < 0.01:
        days_left = None  # no measurable demand
    else:
        days_left = int(stock / demand)
    return {
        "method": result["method"],
        "history_days": result["history_days"],
        "daily_demand": round(demand, 3),
        "stock": stock,
        "days_until_stockout": days_left,
    }
