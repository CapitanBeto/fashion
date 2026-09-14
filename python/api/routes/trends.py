"""
Trend scoring endpoints.
Called by Laravel's CalculateScoresJob and ForecastChileTrendJob.
"""

import logging

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from db.client import execute, fetchrow
from forecasting.backtest import run_historical_backtest
from forecasting.chile_transfer import forecast_chile_trend
from scoring.momentum import (
    calculate_convergence_score,
    calculate_death_probability,
    calculate_momentum_score,
    classify_lifecycle_stage,
)

router  = APIRouter()
logger  = logging.getLogger(__name__)


class ForecastRequest(BaseModel):
    country_id: int | None = None  # unused for now — forecast is Chile-specific
    horizon_days: int = 60


class BacktestRequest(BaseModel):
    keyword: str
    geo: str = "CL"


class ScoreResult(BaseModel):
    trend_id:                    int
    momentum_score:              int
    growth_score:                int
    convergence_score:           int
    saturation_score:            int
    virality_score:              int
    commercial_opportunity_score: int
    competition_score:           int
    death_probability:           int
    confidence:                  int
    lifecycle_stage:             str
    stage_confidence:            int


@router.post("/trends/{trend_id}/scores", response_model=ScoreResult)
async def calculate_scores(trend_id: int):
    """Recalculate all scores for a single trend and persist them."""
    trend = await fetchrow("SELECT id, keywords FROM trends WHERE id = $1", trend_id)
    if not trend:
        raise HTTPException(status_code=404, detail=f"Trend {trend_id} not found")

    # Determine which sources have data for this trend
    sources_used = []

    has_search = await fetchrow(
        "SELECT 1 FROM search_metrics WHERE trend_id = $1 LIMIT 1", trend_id
    )
    if has_search:
        sources_used.append("google_trends")

    has_reddit = await fetchrow(
        """
        SELECT 1 FROM trend_mentions tm
        JOIN sources s ON s.id = tm.source_id
        WHERE tm.trend_id = $1 AND s.type = 'reddit'
        LIMIT 1
        """,
        trend_id,
    )
    if has_reddit:
        sources_used.append("reddit")

    has_brand = await fetchrow(
        "SELECT 1 FROM brand_trends WHERE trend_id = $1 LIMIT 1", trend_id
    )
    if has_brand:
        sources_used.append("brand")

    # Calculate all scores
    momentum    = await calculate_momentum_score(trend_id)
    convergence = await calculate_convergence_score(trend_id, sources_used)
    death_prob  = await calculate_death_probability(trend_id)
    stage       = await classify_lifecycle_stage(trend_id)

    # Commercial opportunity: simplified formula
    opp_score = min(int(momentum * 0.4 + convergence * 0.4 + (100 - death_prob) * 0.2), 100)

    # Confidence based on data availability
    data_points = await fetchrow(
        "SELECT COUNT(*) as cnt FROM trend_mentions WHERE trend_id = $1", trend_id
    )
    n = data_points["cnt"] if data_points else 0
    confidence = min(int((n / 50) * 100), 100)  # 50 mentions = 100% confidence

    result = {
        "trend_id":                    trend_id,
        "momentum_score":              momentum,
        "growth_score":                momentum,  # same base for MVP
        "convergence_score":           convergence,
        "saturation_score":            0,
        "virality_score":              0,
        "commercial_opportunity_score": opp_score,
        "competition_score":           30,
        "death_probability":           death_prob,
        "confidence":                  confidence,
        "lifecycle_stage":             stage,
        "stage_confidence":            confidence,
    }

    # Persist to DB
    await execute(
        """
        UPDATE trends SET
            momentum_score               = $1,
            growth_score                 = $2,
            convergence_score            = $3,
            commercial_opportunity_score = $4,
            death_probability            = $5,
            confidence                   = $6,
            lifecycle_stage              = $7,
            stage_confidence             = $8,
            last_updated_at              = NOW(),
            updated_at                   = NOW()
        WHERE id = $9
        """,
        momentum, momentum, convergence, opp_score,
        death_prob, confidence, stage, confidence, trend_id,
    )

    logger.info(f"Scored trend {trend_id}: momentum={momentum}, stage={stage}, opp={opp_score}")
    return ScoreResult(**result)


@router.post("/trends/{trend_id}/forecast")
async def forecast_trend(trend_id: int, body: ForecastRequest):
    """
    Chile 60-day (by default) trend forecast. Persists a new trend_predictions
    row every call (predictions are an append-only, dated log so
    predictions:evaluate can backtest them later) and returns the full
    explainable breakdown.
    """
    try:
        return await forecast_chile_trend(trend_id, horizon_days=body.horizon_days)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))


@router.post("/trends/{trend_id}/backtest")
async def backtest_trend(trend_id: int, body: BacktestRequest):
    """
    Walk-forward historical backtest using real Google Trends history — see
    forecasting/backtest.py for the full explanation. Writes real
    trend_predictions + prediction_outcomes rows immediately (no waiting),
    since the "future" for each historical checkpoint is already known.
    """
    chile = await fetchrow("SELECT id FROM countries WHERE iso2 = 'CL'")
    if not chile:
        raise HTTPException(status_code=404, detail="Chile (CL) not found in countries table")

    return await run_historical_backtest(trend_id, body.keyword, body.geo, chile["id"])
