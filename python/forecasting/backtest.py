"""
Walk-forward historical backtest.

Instead of waiting months for real predictions to mature, we exploit the
fact that Google Trends returns up to 12 months of REAL historical search
interest in a single call. We "stand" at many past checkpoints within that
real series, compute what our momentum-style score would have been using
only data available up to that checkpoint, and immediately compare it to
what actually happened ~60 days later — which we already know, because it's
history. No numbers are invented: every point comes from real, dated Google
Trends interest values.

This produces real prediction/outcome pairs today instead of in 2 months,
which is what predictions:evaluate needs to build genuine calibration data
(does a 70%+ score actually correlate with real subsequent growth?).
"""

import logging
import statistics
from datetime import timedelta

from db.client import execute, fetchrow
from scoring.momentum import clamp

logger = logging.getLogger(__name__)

CHECKPOINT_STEP_WEEKS = 2
HORIZON_DAYS = 60
HORIZON_WEEKS = HORIZON_DAYS // 7  # ~8-9 weeks of "future" needed per checkpoint


def _momentum_proxy(interests: list[int], idx: int) -> int:
    """
    Same normalization style as scoring/momentum.py's search_growth term:
    current value vs. trailing 4-week average, mapped to 0-100.
    """
    window = interests[max(0, idx - 3):idx + 1]
    if len(window) < 2:
        return 0
    current = window[-1]
    avg_prev = sum(window[:-1]) / max(len(window[:-1]), 1)
    growth = ((current - avg_prev) / max(avg_prev, 1)) * 100
    return clamp(growth + 50)


async def run_historical_backtest(trend_id: int, keyword: str, geo: str, chile_id: int) -> dict:
    """
    Fetch real historical Google Trends interest for `keyword` in `geo`,
    walk through checkpoints, and write real (prediction, outcome) pairs.
    Returns a summary — never fabricates a result if there isn't enough
    real historical data to work with.
    """
    from scrapers.google_trends import fetch_google_trends

    data = await fetch_google_trends([keyword], geo, "today 12-m")
    rows = data.get(keyword, [])

    if len(rows) < CHECKPOINT_STEP_WEEKS + HORIZON_WEEKS + 2:
        return {
            "trend_id": trend_id, "keyword": keyword, "geo": geo,
            "checkpoints_created": 0,
            "note": f"Only {len(rows)} weeks of real history available — not enough to backtest a {HORIZON_DAYS}-day horizon honestly.",
        }

    rows.sort(key=lambda r: r["date"])
    interests = [r["interest"] for r in rows]
    dates = [r["date"] for r in rows]

    created = 0
    accuracies = []

    last_checkpoint_idx = len(rows) - HORIZON_WEEKS - 1

    for idx in range(3, last_checkpoint_idx, CHECKPOINT_STEP_WEEKS):
        checkpoint_date = dates[idx]
        future_idx = idx + HORIZON_WEEKS
        if future_idx >= len(rows):
            break

        baseline_interest = interests[idx]
        future_interest = interests[future_idx]

        predicted_probability = _momentum_proxy(interests, idx)

        actual_growth = ((future_interest - baseline_interest) / max(baseline_interest, 1)) * 100
        actual_as_probability = clamp(50 + actual_growth)
        forecast_error = predicted_probability - actual_as_probability
        accuracy = clamp(100 - abs(forecast_error))
        accuracies.append(accuracy)

        target_date = checkpoint_date + timedelta(days=HORIZON_DAYS)

        pred_row = await fetchrow(
            """
            INSERT INTO trend_predictions
                (trend_id, country_id, model_name, model_version, prediction_horizon,
                 prediction_date, target_date, growth_probability, decline_probability,
                 saturation_probability, death_probability, momentum_forecast,
                 confidence, reasoning, input_snapshot, created_at)
            VALUES ($1,$2,'historical_backtest','1.0',$3,$4,$5,$6,0,0,0,'{}',$7,$8,$9::jsonb,NOW())
            RETURNING id
            """,
            trend_id, chile_id, HORIZON_DAYS, checkpoint_date, target_date,
            predicted_probability, 40,
            f"[BACKTEST] Walk-forward checkpoint at {checkpoint_date}: real search interest "
            f"was {baseline_interest} at checkpoint, momentum proxy scored {predicted_probability}%. "
            f"Real interest {HORIZON_DAYS} days later ({target_date}) was {future_interest}.",
            '{"is_backtest": true}',
        )

        await execute(
            """
            INSERT INTO prediction_outcomes
                (prediction_id, actual_growth, actual_lifecycle_stage, accuracy_score, forecast_error)
            VALUES ($1,$2,NULL,$3,$4)
            """,
            pred_row["id"], round(actual_growth, 4), round(accuracy, 2), round(forecast_error, 4),
        )

        created += 1

    summary = {
        "trend_id": trend_id, "keyword": keyword, "geo": geo,
        "checkpoints_created": created,
        "weeks_of_real_history": len(rows),
        "mean_accuracy": round(statistics.mean(accuracies), 1) if accuracies else None,
        "median_accuracy": round(statistics.median(accuracies), 1) if accuracies else None,
    }
    logger.info(f"Backtest for '{keyword}' ({geo}): {summary}")
    return summary
