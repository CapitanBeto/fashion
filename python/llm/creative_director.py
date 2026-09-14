"""
Creative Director module.
Uses the LLM to generate concrete product concepts from trend data.
Reads the prompt from prompt_versions table (key: 'commercial_opportunity').
"""

import json
import logging

from db.client import fetch, fetchrow
from llm.client import complete

logger = logging.getLogger(__name__)


async def generate_product_concept(trend_id: int, country_id: int | None = None) -> dict | None:
    """
    Generate a product concept for a trend using the configured LLM.
    Returns a dict with: product_name, silhouette, colors, graphics,
    price_min, price_max, target_audience, reasoning, risks, evidence.
    """
    # Load trend data
    trend = await fetchrow(
        """
        SELECT t.name, t.lifecycle_stage, t.momentum_score,
               t.commercial_opportunity_score, t.competition_score,
               t.confidence, t.description
        FROM trends t
        WHERE t.id = $1
        """,
        trend_id,
    )

    if not trend:
        logger.warning(f"Trend {trend_id} not found")
        return None

    # Load top colors, silhouettes, brands, creators
    top_colors = await fetch(
        """
        SELECT c.name, tc.frequency
        FROM trend_colors tc JOIN colors c ON c.id = tc.color_id
        WHERE tc.trend_id = $1
        ORDER BY tc.frequency DESC LIMIT 5
        """,
        trend_id,
    )

    top_silhouettes = await fetch(
        """
        SELECT s.name, ts.frequency
        FROM trend_silhouettes ts JOIN silhouettes s ON s.id = ts.silhouette_id
        WHERE ts.trend_id = $1
        ORDER BY ts.frequency DESC LIMIT 4
        """,
        trend_id,
    )

    top_brands = await fetch(
        """
        SELECT b.name, bt.product_count
        FROM brand_trends bt JOIN brands b ON b.id = bt.brand_id
        WHERE bt.trend_id = $1
        ORDER BY bt.product_count DESC LIMIT 5
        """,
        trend_id,
    )

    top_creators = await fetch(
        """
        SELECT c.name, ct.frequency
        FROM creator_trends ct JOIN creators c ON c.id = ct.creator_id
        WHERE ct.trend_id = $1
        ORDER BY ct.frequency DESC LIMIT 5
        """,
        trend_id,
    )

    price_range = await fetchrow(
        """
        SELECT MIN(po.price) as min_price, MAX(po.price) as max_price,
               AVG(po.price) as avg_price
        FROM price_observations po
        JOIN product_trends pt ON pt.product_id = po.product_id
        WHERE pt.trend_id = $1
        """,
        trend_id,
    )

    country_name = "Global"
    if country_id:
        c = await fetchrow("SELECT name FROM countries WHERE id = $1", country_id)
        if c:
            country_name = c["name"]

    # Load prompt from database
    prompt = await fetchrow(
        "SELECT system_prompt, user_template FROM prompt_versions WHERE prompt_key = 'commercial_opportunity' AND active = true"
    )

    if not prompt:
        logger.error("No active 'commercial_opportunity' prompt found in prompt_versions")
        return None

    # Fill template
    price_str = "Unknown"
    if price_range and price_range["min_price"]:
        price_str = f"${price_range['min_price']:.0f}–${price_range['max_price']:.0f}"

    user_message = prompt["user_template"].format(
        trend_name=trend["name"],
        lifecycle_stage=trend["lifecycle_stage"],
        momentum_score=trend["momentum_score"],
        opportunity_score=trend["commercial_opportunity_score"],
        top_colors=", ".join(r["name"] for r in top_colors) or "Not enough data",
        top_silhouettes=", ".join(r["name"] for r in top_silhouettes) or "Not enough data",
        top_graphics="Not analyzed yet",
        price_range=price_str,
        countries=country_name,
        creators=", ".join(r["name"] for r in top_creators) or "None detected",
        brands=", ".join(r["name"] for r in top_brands) or "None detected",
    )

    try:
        result = await complete(
            system=prompt["system_prompt"],
            user=user_message,
            json_mode=True,
        )

        if not isinstance(result, dict):
            logger.error(f"LLM returned non-dict: {result}")
            return None

        return result

    except Exception as e:
        logger.error(f"Creative Director LLM call failed for trend {trend_id}: {e}")
        return None


async def save_commercial_opportunity(
    trend_id: int,
    country_id: int | None,
    concept: dict,
    scores: dict,
) -> int | None:
    """
    Upsert a commercial opportunity record.
    Returns the opportunity id.
    """
    from db.client import fetchrow as db_fetchrow, execute

    row = await db_fetchrow(
        """
        INSERT INTO commercial_opportunities
            (trend_id, country_id, opportunity_score, demand_score, competition_score,
             momentum_score, timing_score, confidence, suggested_product_name,
             suggested_silhouette, suggested_colors, suggested_graphics,
             suggested_price_min, suggested_price_max, suggested_target_audience,
             reasoning, risks, evidence, created_at, updated_at)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11::jsonb,$12::jsonb,$13,$14,$15,$16,$17::jsonb,$18::jsonb,NOW(),NOW())
        ON CONFLICT (trend_id, country_id)
        DO UPDATE SET
            opportunity_score       = EXCLUDED.opportunity_score,
            suggested_product_name  = EXCLUDED.suggested_product_name,
            suggested_silhouette    = EXCLUDED.suggested_silhouette,
            suggested_colors        = EXCLUDED.suggested_colors,
            suggested_price_min     = EXCLUDED.suggested_price_min,
            suggested_price_max     = EXCLUDED.suggested_price_max,
            reasoning               = EXCLUDED.reasoning,
            risks                   = EXCLUDED.risks,
            updated_at              = NOW()
        RETURNING id
        """,
        trend_id,
        country_id,
        scores.get("opportunity_score", 0),
        scores.get("demand_score", 0),
        scores.get("competition_score", 0),
        scores.get("momentum_score", 0),
        scores.get("timing_score", 0),
        scores.get("confidence", 0),
        concept.get("product_name"),
        concept.get("silhouette"),
        json.dumps(concept.get("colors", [])),
        json.dumps(concept.get("graphics", [])),
        concept.get("price_min"),
        concept.get("price_max"),
        concept.get("target_audience"),
        concept.get("reasoning"),
        json.dumps(concept.get("risks", [])),
        json.dumps(concept.get("evidence", [])),
    )

    return row["id"] if row else None
