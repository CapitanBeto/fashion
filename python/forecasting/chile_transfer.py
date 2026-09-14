"""
Chile 60-day trend forecast — explainable weighted baseline.

This is deliberately NOT a trained ML model (per spec: build an interpretable
baseline first, add ML later once enough historical prediction/outcome pairs
exist for backtesting). Every number in the output is either a real value read
from the database or an explicit "no data yet" — nothing is invented.

Core question this module answers:
    Given current global signal, Chile-specific signal, and how trends have
    historically travelled between countries, how likely is this trend to
    become significant in Chile within the next N days?
"""

import json
import logging
import statistics
from datetime import date, timedelta

from db.client import execute, fetch, fetchrow
from llm.client import complete
from scoring.momentum import clamp, load_weights

logger = logging.getLogger(__name__)

HORIZONS = [7, 14, 30, 60, 90]

# Published analyst estimate from a user-supplied report ("La Cadena de
# Influencia — Del Streetwear Global al Chileno, Pasando por España", Sept
# 2026): "las tendencias de streetwear que hoy son relevantes en España
# llegarán a Chile en 6-12 meses". This is a qualitative estimate from an
# external analysis, NOT computed by this system's own historical data —
# kept as a distinct, low-confidence fallback (see _get_transfer_signal),
# never merged into country_transfer_lags, which is reserved for values this
# system actually calculated from real trend_countries history.
ES_CHILE_ANALYST_LAG_DAYS = 270  # midpoint of the reported 6-12 month range
ES_CHILE_ANALYST_LAG_CONFIDENCE = 20  # deliberately low — an outside estimate, not measured
ES_CHILE_ANALYST_LAG_SOURCE = "streetwear_chile_espana.docx, Sept 2026"

# Same ordering as scoring_parameters group 'lifecycle', reused here to
# classify a *per-country* momentum value the same way the global one is
# classified in scoring/momentum.py::classify_lifecycle_stage.
_STAGE_ORDER = [
    ("EMERGING", "emerging_upper"),
    ("EARLY_ADOPTION", "early_adoption_upper"),
    ("ACCELERATING", "accelerating_upper"),
    ("MAINSTREAM", "mainstream_upper"),
    ("PEAK", "peak_upper"),
]


async def _classify_country_stage(momentum: int, thresholds: dict[str, float]) -> str:
    if momentum <= thresholds.get("death_threshold", 15):
        return "EMERGING"  # no presence yet, not "dead" — it never lived here
    for stage, key in _STAGE_ORDER:
        if momentum <= thresholds.get(key, 100):
            return stage
    return "PEAK"


async def recalculate_transfer_lags(niche_id: int | None = None) -> int:
    """
    Recompute country_transfer_lags from real trend_countries.first_seen_at
    history. For every trend that has appeared in 2+ countries, every ordered
    pair (earlier country -> later country) contributes one lag sample.

    Writes both a niche-specific row and a niche_id=NULL ("all niches") row,
    since early on there usually isn't enough per-niche data yet.

    Returns the number of (source, target[, niche]) rows written.
    """
    rows = await fetch(
        """
        SELECT tc.trend_id, tc.country_id, tc.first_seen_at, t.niche_id
        FROM trend_countries tc
        JOIN trends t ON t.id = tc.trend_id
        WHERE tc.first_seen_at IS NOT NULL
        ORDER BY tc.trend_id, tc.first_seen_at
        """
    )

    if not rows:
        return 0

    by_trend: dict[int, list[dict]] = {}
    for r in rows:
        by_trend.setdefault(r["trend_id"], []).append(dict(r))

    # pair_key -> list[lag_days]; pair_key = (source_country_id, target_country_id, niche_id_or_None)
    samples: dict[tuple, list[int]] = {}

    for trend_id, appearances in by_trend.items():
        appearances.sort(key=lambda a: a["first_seen_at"])
        trend_niche = appearances[0]["niche_id"]
        for i, source in enumerate(appearances):
            for target in appearances[i + 1:]:
                if target["country_id"] == source["country_id"]:
                    continue
                lag_days = (target["first_seen_at"] - source["first_seen_at"]).days
                if lag_days <= 0:
                    continue
                pair = (source["country_id"], target["country_id"])
                samples.setdefault((*pair, trend_niche), []).append(lag_days)
                samples.setdefault((*pair, None), []).append(lag_days)

    min_sample = int((await load_weights("chile_forecast")).get("min_transfer_sample_size", 3))
    written = 0

    for (src, tgt, niche), lags in samples.items():
        n = len(lags)
        confidence = clamp(int((n / max(min_sample, 1)) * 100))
        await execute(
            """
            INSERT INTO country_transfer_lags
                (source_country_id, target_country_id, niche_id, sample_size,
                 median_lag_days, avg_lag_days, min_lag_days, max_lag_days,
                 confidence, calculated_at, created_at, updated_at)
            VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,NOW(),NOW(),NOW())
            ON CONFLICT (source_country_id, target_country_id, niche_id) DO UPDATE
            SET sample_size = EXCLUDED.sample_size,
                median_lag_days = EXCLUDED.median_lag_days,
                avg_lag_days = EXCLUDED.avg_lag_days,
                min_lag_days = EXCLUDED.min_lag_days,
                max_lag_days = EXCLUDED.max_lag_days,
                confidence = EXCLUDED.confidence,
                calculated_at = NOW(),
                updated_at = NOW()
            """,
            src, tgt, niche, n,
            int(statistics.median(lags)), float(statistics.mean(lags)),
            min(lags), max(lags), confidence,
        )
        written += 1

    logger.info(f"Transfer lags recalculated: {written} (source,target,niche) rows from {len(by_trend)} trends")
    return written


async def _get_chile_country_id() -> int | None:
    row = await fetchrow("SELECT id FROM countries WHERE iso2 = 'CL'")
    return row["id"] if row else None


async def _get_transfer_signal(trend_id: int, chile_id: int, niche_id: int | None) -> dict:
    """
    Find the fastest known historical path into Chile from wherever this
    specific trend already has real presence. Returns confidence=0 and
    lag_days=None (never invented) when there isn't enough history yet.
    """
    source_rows = await fetch(
        """
        SELECT country_id, strength FROM trend_countries
        WHERE trend_id = $1 AND country_id != $2 AND strength > 0
        """,
        trend_id, chile_id,
    )

    if not source_rows:
        return {
            "lag_days": None, "confidence": 0, "source_country_ids": [],
            "note": "Trend has no recorded presence outside Chile yet — no transfer signal available.",
        }

    source_ids = [r["country_id"] for r in source_rows]

    lag_rows = await fetch(
        """
        SELECT source_country_id, target_country_id, niche_id, sample_size,
               median_lag_days, confidence
        FROM country_transfer_lags
        WHERE target_country_id = $1
          AND source_country_id = ANY($2::bigint[])
          AND (niche_id = $3 OR niche_id IS NULL)
        ORDER BY (niche_id IS NOT NULL) DESC, confidence DESC
        """,
        chile_id, source_ids, niche_id,
    )

    if not lag_rows:
        # No lag computed yet from our own trend_countries history. Rather than
        # a bare "no data", fall back to a published analyst estimate when
        # Spain is among the source countries — clearly attributed, low
        # confidence, and never presented as something this system computed.
        es_row = await fetchrow("SELECT id FROM countries WHERE iso2 = 'ES'")
        if es_row and es_row["id"] in source_ids:
            return {
                "lag_days": ES_CHILE_ANALYST_LAG_DAYS,
                "confidence": ES_CHILE_ANALYST_LAG_CONFIDENCE,
                "source_country_ids": source_ids,
                "note": f"No historical transfer data computed yet by this system. Falling back to a "
                        f"published analyst estimate ({ES_CHILE_ANALYST_LAG_SOURCE}): Spain-to-Chile "
                        f"streetwear trends typically reach Chile in 6-12 months — used here as a "
                        f"low-confidence prior, not a measured result.",
            }

        return {
            "lag_days": None, "confidence": 0, "source_country_ids": source_ids,
            "note": f"Trend has presence in {len(source_ids)} other country(ies), but no historical "
                    f"transfer data exists yet to estimate a lag.",
        }

    best = lag_rows[0]
    return {
        "lag_days": best["median_lag_days"],
        "confidence": best["confidence"],
        "sample_size": best["sample_size"],
        "source_country_ids": source_ids,
        "note": f"Based on {best['sample_size']} historical case(s) of trends moving into Chile "
                f"from a country where this trend is already present (median lag {best['median_lag_days']} days).",
    }


async def _chile_search_growth(trend_id: int, chile_id: int) -> int:
    """WoW-style growth of Chile-specific Google Trends interest, 0-100 normalized like momentum.py."""
    rows = await fetch(
        """
        SELECT interest FROM search_metrics
        WHERE trend_id = $1 AND country_id = $2
        ORDER BY metric_date DESC LIMIT 8
        """,
        trend_id, chile_id,
    )
    if len(rows) < 2:
        return 0
    interests = [r["interest"] for r in rows]
    current = interests[0]
    avg_prev = sum(interests[1:4]) / max(len(interests[1:4]), 1)
    growth = ((current - avg_prev) / max(avg_prev, 1)) * 100
    return clamp(growth + 50)


async def _chile_adoption_counts(trend_id: int, chile_id: int) -> tuple[int, int]:
    """(chile_creator_count, chile_brand_count) — real counts, not estimates."""
    creators = await fetchrow(
        """
        SELECT COUNT(DISTINCT ct.creator_id) AS cnt
        FROM creator_trends ct JOIN creators c ON c.id = ct.creator_id
        WHERE ct.trend_id = $1 AND c.country_id = $2
        """,
        trend_id, chile_id,
    )
    brands = await fetchrow(
        """
        SELECT COUNT(DISTINCT bt.brand_id) AS cnt
        FROM brand_trends bt JOIN brands b ON b.id = bt.brand_id
        WHERE bt.trend_id = $1 AND b.country_id = $2
        """,
        trend_id, chile_id,
    )
    return (creators["cnt"] if creators else 0), (brands["cnt"] if brands else 0)


def _horizon_curve(score_60d: int, lag_days: int | None) -> dict[str, int]:
    """
    Scale the 60-day baseline to other horizons. If we have a real estimated
    lag for this trend reaching Chile, use it to shape the ramp (little
    chance before the typical lag, rising sharply around it, roughly holding
    afterward). Otherwise fall back to a fixed, clearly-heuristic ratio table.
    Either way this is explicit interpolation, not a trained forecaster.
    """
    if lag_days and lag_days > 0:
        curve = {}
        for h in HORIZONS:
            ratio = clamp(int((h / lag_days) * 100), 5, 130) / 100
            curve[str(h)] = clamp(int(score_60d * ratio))
        return curve

    ratios = {7: 0.55, 14: 0.65, 30: 0.82, 60: 1.00, 90: 1.05}
    return {str(h): clamp(int(score_60d * r)) for h, r in ratios.items()}


_REASONING_SYSTEM_PROMPT = """You are a fashion trend forecasting analyst with deep expertise in the
fashion cycle — how looks move from niche/subculture origin through early adopters, creator/brand
amplification, mainstream diffusion, saturation, and decline or revival (Rogers' diffusion-of-
innovations segments: innovators, early adopters, early majority, late majority, laggards).

When judging whether a trend is likely to transfer into Chile specifically, apply the "trinity of
cultural transfer" framework: (1) symbolic compatibility — can Chilean audiences resignify the trend's
code without it feeling foreign (shared Spanish language is a strong enabler for Spain-origin trends);
(2) aesthetic adaptability — are the materials/silhouettes/palette reproducible with local production;
(3) musical anchor — is there a local music scene (e.g. Chilean trap) that can legitimize and carry the
aesthetic the way Spanish trap carried Spanish streetwear. A trend meeting more of these three travels
faster and more durably than one meeting none.

Write ONE short paragraph (3-5 sentences) explaining a Chile trend-adoption forecast using ONLY the
evidence and numbers given to you — do not invent any new fact, brand, date, or data point. Your job is
expert framing and synthesis of the given evidence (optionally through the lens above when it fits),
not adding new claims. Return plain text, no markdown, no JSON."""


def _fallback_reasoning(trend_name: str, horizon_days: int, probability: int, evidence: list[str], contradictions: list[str]) -> str:
    return (
        f"Chile {horizon_days}-day forecast for '{trend_name}': {probability}%. "
        + " ".join(evidence)
        + (f" Contradicting signals: {' '.join(contradictions)}" if contradictions else "")
    )


async def _synthesize_reasoning(
    trend_name: str, horizon_days: int, probability: int, chile_stage: str,
    predicted_stage: str, evidence: list[str], contradictions: list[str],
) -> str:
    """
    Expert-toned narrative synthesis of the real, already-computed evidence.
    Falls back to the plain template on any LLM failure so a forecast never
    goes unsaved just because the write-up call failed.
    """
    fallback = _fallback_reasoning(trend_name, horizon_days, probability, evidence, contradictions)
    try:
        user = (
            f"Trend: {trend_name}\n"
            f"Chile {horizon_days}-day adoption probability: {probability}%\n"
            f"Current Chile lifecycle stage: {chile_stage}\n"
            f"Predicted stage at horizon: {predicted_stage}\n"
            f"Supporting evidence: {' | '.join(evidence) if evidence else 'none'}\n"
            f"Contradicting evidence: {' | '.join(contradictions) if contradictions else 'none'}\n\n"
            "Write the forecast paragraph now, placing this trend on the fashion cycle using only the above."
        )
        text = await complete(system=_REASONING_SYSTEM_PROMPT, user=user, json_mode=False)
        text = str(text).strip()
        return text if text else fallback
    except Exception as e:
        logger.warning(f"Reasoning synthesis failed for '{trend_name}', using template fallback: {e}")
        return fallback


async def forecast_chile_trend(trend_id: int, horizon_days: int = 60) -> dict:
    """
    The core function: forecastChileTrend(trend, horizon=60).
    Computes an explainable Chile-adoption probability, persists it as a new
    trend_predictions row (predictions are an append-only log — each run is
    a dated record so predictions:evaluate can backtest it later), and
    returns the full breakdown.
    """
    trend = await fetchrow(
        """
        SELECT id, name, niche_id, momentum_score, convergence_score,
               saturation_score, competition_score, lifecycle_stage, death_probability
        FROM trends WHERE id = $1
        """,
        trend_id,
    )
    if not trend:
        raise ValueError(f"Trend {trend_id} not found")

    chile_id = await _get_chile_country_id()
    if chile_id is None:
        raise ValueError("Chile (CL) is not present in the countries table")

    await recalculate_transfer_lags(trend["niche_id"])

    chile_row = await fetchrow(
        "SELECT strength, stage FROM trend_countries WHERE trend_id = $1 AND country_id = $2",
        trend_id, chile_id,
    )
    chile_momentum = chile_row["strength"] if chile_row else 0

    w = await load_weights("chile_forecast")
    lifecycle_thresholds = await load_weights("lifecycle")

    search_growth = await _chile_search_growth(trend_id, chile_id)
    creator_count, brand_count = await _chile_adoption_counts(trend_id, chile_id)
    creator_adoption = clamp(min(creator_count * 25, 100))
    brand_adoption = clamp(min(brand_count * 25, 100))
    transfer = await _get_transfer_signal(trend_id, chile_id, trend["niche_id"])
    transfer_similarity = transfer["confidence"]  # 0-100, honest: 0 means "no historical basis"

    weighted = (
        trend["momentum_score"]  * w.get("global_momentum_weight", 0.20) +
        chile_momentum           * w.get("chile_momentum_weight", 0.20) +
        trend["convergence_score"] * w.get("cross_source_convergence_weight", 0.12) +
        creator_adoption          * w.get("creator_adoption_weight", 0.10) +
        brand_adoption            * w.get("brand_adoption_weight", 0.10) +
        search_growth             * w.get("search_growth_weight", 0.13) +
        transfer_similarity       * w.get("historical_transfer_weight", 0.15)
    )

    chile_stage = await _classify_country_stage(chile_momentum, lifecycle_thresholds)

    penalties = 0.0
    contradictions = []

    if trend["saturation_score"] > 60:
        penalties += trend["saturation_score"] * w.get("saturation_penalty_weight", 0.20)
        contradictions.append(f"Global saturation score is high ({trend['saturation_score']}/100).")

    if trend["competition_score"] > 60:
        penalties += trend["competition_score"] * w.get("competition_penalty_weight", 0.10)
        contradictions.append(f"Global competition score is high ({trend['competition_score']}/100).")

    if trend["lifecycle_stage"] in ("DECLINING", "SATURATED", "DEAD"):
        penalties += 100 * w.get("declining_penalty_weight", 0.25)
        contradictions.append(f"Global lifecycle stage is already {trend['lifecycle_stage']}.")

    if chile_stage in ("PEAK", "MAINSTREAM"):
        penalties += 100 * w.get("already_peaked_chile_penalty", 0.30)
        contradictions.append(f"Trend already appears {chile_stage} in Chile — limited further upside.")

    probability_60d = clamp(weighted - penalties)
    curve = _horizon_curve(probability_60d, transfer["lag_days"])
    probability_at_horizon = curve.get(str(horizon_days), probability_60d)

    evidence = []
    if search_growth > 55:
        evidence.append(f"Chile Google Trends search interest is growing (score {search_growth}/100).")
    if creator_count > 0:
        evidence.append(f"{creator_count} Chile-based creator(s) already associated with this trend.")
    if brand_count > 0:
        evidence.append(f"{brand_count} Chile-based brand(s) already adopting this trend.")
    if trend["momentum_score"] > 50:
        evidence.append(f"Strong global momentum ({trend['momentum_score']}/100).")
    evidence.append(transfer["note"])
    if not evidence:
        evidence.append("Limited signal available so far — this reflects genuinely low current evidence, not a data error.")

    confidence = clamp(int(
        (0.4 * (50 if chile_row else 0)) +
        (0.3 * transfer_similarity) +
        (0.3 * min(trend["momentum_score"], 100))
    ))

    # Rough one-stage-forward heuristic for the predicted stage — explicit and
    # simple, not a trained sequence model.
    stage_names = [s for s, _ in _STAGE_ORDER]
    if probability_60d > chile_momentum + 15 and chile_stage in stage_names:
        idx = min(stage_names.index(chile_stage) + 1, len(stage_names) - 1)
        predicted_stage = stage_names[idx]
    else:
        predicted_stage = chile_stage

    prediction_date = date.today()
    target_date = prediction_date + timedelta(days=horizon_days)

    input_snapshot = {
        "global_momentum": trend["momentum_score"],
        "chile_momentum": chile_momentum,
        "cross_source_convergence": trend["convergence_score"],
        "creator_adoption": creator_adoption,
        "brand_adoption": brand_adoption,
        "search_growth": search_growth,
        "historical_transfer_similarity": transfer_similarity,
        "transfer_lag_days_used": transfer["lag_days"],
        "transfer_sample_size": transfer.get("sample_size", 0),
        "penalties_applied": round(penalties, 1),
        "current_chile_stage": chile_stage,
        "predicted_stage": predicted_stage,
        "horizon_curve": curve,
        "evidence": evidence,
        "contradictions": contradictions,
    }

    reasoning = await _synthesize_reasoning(
        trend["name"], horizon_days, probability_at_horizon,
        chile_stage, predicted_stage, evidence, contradictions,
    )

    pred_row = await fetchrow(
        """
        INSERT INTO trend_predictions
            (trend_id, country_id, model_name, model_version, prediction_horizon,
             prediction_date, target_date, growth_probability, decline_probability,
             saturation_probability, death_probability, momentum_forecast,
             confidence, reasoning, input_snapshot, created_at)
        VALUES ($1,$2,'chile_transfer_baseline','1.0',$3,$4,$5,$6,$7,$8,$9,$10::jsonb,$11,$12,$13::jsonb,NOW())
        RETURNING id
        """,
        trend_id, chile_id, horizon_days, prediction_date, target_date,
        probability_at_horizon,
        clamp(100 - probability_at_horizon) if trend["lifecycle_stage"] in ("DECLINING", "SATURATED") else 0,
        trend["saturation_score"], trend["death_probability"],
        json.dumps(curve), confidence, reasoning, json.dumps(input_snapshot),
    )

    await execute(
        """
        INSERT INTO trend_countries (trend_id, country_id, strength, stage, first_seen_at, created_at)
        VALUES ($1,$2,$3,$4,NOW(),NOW())
        ON CONFLICT (trend_id, country_id) DO UPDATE
        SET stage = EXCLUDED.stage
        """,
        trend_id, chile_id, chile_momentum, chile_stage,
    )

    logger.info(
        f"Chile forecast for trend {trend_id} ('{trend['name']}'): "
        f"{probability_at_horizon}% in {horizon_days}d (confidence {confidence}%)"
    )

    return {
        "trend_id": trend_id,
        "trend_name": trend["name"],
        "country": "Chile",
        "horizon_days": horizon_days,
        "prediction_id": pred_row["id"] if pred_row else None,
        "probability": probability_at_horizon,
        "current_chile_stage": chile_stage,
        "predicted_stage": predicted_stage,
        "confidence": confidence,
        "horizon_curve": curve,
        "reasoning": reasoning,
        "evidence": evidence,
        "contradictions": contradictions,
        **input_snapshot,
    }
