"""
Momentum score calculator.
Implements the formula from the spec:
momentum = growth_velocity * w1 + growth_acceleration * w2 + search_growth * w3 + ...
All weights loaded from scoring_parameters table (overridable without code changes).
"""

import logging
from datetime import date, timedelta

from db.client import fetch, fetchrow

logger = logging.getLogger(__name__)


async def load_weights(group: str) -> dict[str, float]:
    """Load scoring weights from the database (scoring_parameters table)."""
    rows = await fetch(
        "SELECT parameter_key, parameter_value FROM scoring_parameters WHERE parameter_group = $1",
        group,
    )
    return {r["parameter_key"]: float(r["parameter_value"]) for r in rows}


def clamp(value: int | float, min_val: int = 0, max_val: int = 100) -> int:
    return max(min_val, min(max_val, int(value)))


async def calculate_momentum_score(trend_id: int, country_id: int | None = None) -> int:
    """
    Calculate momentum score (0-100) for a trend.
    Uses last 8 weekly snapshots to compute velocity and acceleration.
    """
    w = await load_weights("momentum")

    # Get recent snapshots
    snapshots = await fetch(
        """
        SELECT snapshot_date, mention_count, search_interest, post_count,
               brand_count, creator_count, growth_velocity, growth_acceleration
        FROM trend_snapshots
        WHERE trend_id = $1
          AND (country_id = $2 OR ($2 IS NULL AND country_id IS NULL))
        ORDER BY snapshot_date DESC
        LIMIT 8
        """,
        trend_id,
        country_id,
    )

    if len(snapshots) < 2:
        return 0

    # Growth velocity: week-over-week change in mention count
    current  = snapshots[0]
    previous = snapshots[1]

    curr_mentions = current["mention_count"] or 0
    prev_mentions = previous["mention_count"] or 1

    growth_velocity = ((curr_mentions - prev_mentions) / prev_mentions) * 100
    gv_norm = clamp((growth_velocity + 50) * 1.0, 0, 100)  # normalize -100..+100 → 0..100

    # Growth acceleration: change in velocity
    growth_acceleration = float(current["growth_acceleration"] or 0)
    ga_norm = clamp((growth_acceleration + 50) * 1.0, 0, 100)

    # Search growth: current vs average of last 4 weeks
    search_interests = [s["search_interest"] or 0 for s in snapshots]
    avg_search = sum(search_interests[1:4]) / max(len(search_interests[1:4]), 1)
    sg_norm = clamp(((search_interests[0] - avg_search) / max(avg_search, 1)) * 100 + 50, 0, 100)

    # Social growth: post count growth
    curr_posts = current["post_count"] or 0
    prev_posts = previous["post_count"] or 1
    social_growth = ((curr_posts - prev_posts) / prev_posts) * 100
    soc_norm = clamp((social_growth + 50), 0, 100)

    # Creator adoption: new creators in last 4 weeks
    creator_counts = [s["creator_count"] or 0 for s in snapshots]
    creator_growth = max(0, creator_counts[0] - (creator_counts[-1] if len(creator_counts) > 1 else 0))
    cr_norm = clamp(min(creator_growth * 10, 100), 0, 100)

    # Brand adoption: new brands in last 4 weeks
    brand_counts = [s["brand_count"] or 0 for s in snapshots]
    brand_growth = max(0, brand_counts[0] - (brand_counts[-1] if len(brand_counts) > 1 else 0))
    br_norm = clamp(min(brand_growth * 10, 100), 0, 100)

    # Geographic spread (number of countries with data)
    country_count = await fetchrow(
        "SELECT COUNT(DISTINCT country_id) as cnt FROM trend_snapshots WHERE trend_id = $1",
        trend_id,
    )
    geo_norm = clamp(min((country_count["cnt"] or 0) * 20, 100), 0, 100)

    momentum = (
        gv_norm  * w.get("growth_velocity_weight",    0.30) +
        ga_norm  * w.get("growth_acceleration_weight",0.20) +
        sg_norm  * w.get("search_growth_weight",      0.20) +
        soc_norm * w.get("social_growth_weight",      0.10) +
        cr_norm  * w.get("creator_adoption_weight",   0.10) +
        br_norm  * w.get("brand_adoption_weight",     0.05) +
        geo_norm * w.get("geographic_spread_weight",  0.05)
    )

    return clamp(momentum)


async def calculate_convergence_score(trend_id: int, sources_active: list[str]) -> int:
    """
    Calculate convergence score based on how many independent sources confirm the trend.
    Higher convergence = more reliable signal.
    """
    w = await load_weights("convergence")

    source_map = {
        "google_trends": w.get("google_trends_weight", 0.25),
        "reddit":        w.get("reddit_weight",        0.20),
        "brand":         w.get("brand_weight",         0.20),
        "creator":       w.get("creator_weight",       0.15),
        "ecommerce":     w.get("ecommerce_weight",     0.10),
        "blog":          w.get("blog_weight",          0.05),
        "other":         w.get("other_weight",         0.05),
    }

    score = sum(source_map.get(src, 0.0) * 100 for src in sources_active)
    return clamp(score)


async def calculate_death_probability(trend_id: int) -> int:
    """
    Calculate death probability (0-100) based on negative signals.
    """
    snapshots = await fetch(
        """
        SELECT growth_velocity, search_interest, snapshot_date
        FROM trend_snapshots
        WHERE trend_id = $1
        ORDER BY snapshot_date DESC
        LIMIT 8
        """,
        trend_id,
    )

    trend = await fetchrow(
        "SELECT lifecycle_stage, saturation_score FROM trends WHERE id = $1",
        trend_id,
    )

    if not trend or not snapshots:
        return 0

    signals = []

    # Negative growth 3+ consecutive weeks
    velocities = [float(s["growth_velocity"] or 0) for s in snapshots[:4]]
    if len(velocities) >= 3 and all(v < 0 for v in velocities[:3]):
        signals.append(30)

    # Search interest declining
    interests = [s["search_interest"] or 0 for s in snapshots]
    if len(interests) >= 4 and interests[0] < interests[3] * 0.7:
        signals.append(20)

    # Very high saturation
    if (trend["saturation_score"] or 0) > 85:
        signals.append(20)

    # Already in declining stage
    if trend["lifecycle_stage"] in ("DECLINING", "SATURATED"):
        signals.append(25)

    return clamp(sum(signals))


async def classify_lifecycle_stage(trend_id: int) -> str:
    """
    Classify a trend's lifecycle stage based on current scores.
    Uses thresholds from scoring_parameters table.
    """
    thresholds = await load_weights("lifecycle")
    trend = await fetchrow(
        """
        SELECT momentum_score, saturation_score, death_probability,
               first_seen_at, creator_score, brand_score
        FROM trends WHERE id = $1
        """,
        trend_id,
    )

    if not trend:
        return "EMERGING"

    m  = trend["momentum_score"] or 0
    s  = trend["saturation_score"] or 0
    dp = trend["death_probability"] or 0

    if dp >= 80 or m <= thresholds.get("death_threshold", 15):
        return "DEAD"
    if trend["lifecycle_stage"] == "DECLINING" or m <= thresholds.get("decline_upper", 40):
        if s > 50:
            return "DECLINING"
    if s >= thresholds.get("saturation_upper", 75):
        return "SATURATED"
    if m >= thresholds.get("peak_upper", 90):
        return "PEAK"
    if m >= thresholds.get("mainstream_upper", 80):
        return "MAINSTREAM"
    if m >= thresholds.get("accelerating_upper", 65):
        return "ACCELERATING"
    if m >= thresholds.get("early_adoption_upper", 40):
        return "EARLY_ADOPTION"
    return "EMERGING"
