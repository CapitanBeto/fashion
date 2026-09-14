"""
Google Trends scraper using pytrends.
Fetches interest over time, related queries, and related topics.
Writes results directly to search_metrics and trend_snapshots tables.
"""

import asyncio
import hashlib
import logging
import time
from datetime import date, datetime, timedelta

from pytrends.request import TrendReq

from db.client import execute, fetch, fetchrow

logger = logging.getLogger(__name__)

# pytrends is synchronous — we run it in a thread pool
import concurrent.futures
_executor = concurrent.futures.ThreadPoolExecutor(max_workers=2)


def _fetch_interest_sync(keywords: list[str], timeframe: str, geo: str) -> dict:
    """Synchronous pytrends call — runs in executor thread."""
    pt = TrendReq(hl="en-US", tz=360, timeout=(10, 30), retries=3, backoff_factor=2)
    # pytrends allows max 5 keywords per request
    batches = [keywords[i:i+5] for i in range(0, len(keywords), 5)]
    results = {}

    for batch in batches:
        try:
            pt.build_payload(batch, cat=0, timeframe=timeframe, geo=geo, gprop="")
            interest = pt.interest_over_time()

            if not interest.empty:
                for kw in batch:
                    if kw in interest.columns:
                        results[kw] = {
                            "interest_over_time": interest[kw].to_dict(),
                            # related_queries() intentionally not called: it hits a
                            # far more aggressively throttled Google endpoint than
                            # interest_over_time() (confirmed directly — a 5-keyword
                            # interest_over_time() call succeeds every time in
                            # isolation, but immediately gets HTTP 429 the moment
                            # related_queries() is called right after). Worse, the
                            # original code wrapped both calls in one try/except, so
                            # a related_queries() failure was silently discarding
                            # already-successful interest data too. Not worth the
                            # trade for a nice-to-have field nothing downstream uses.
                            "related_queries": None,
                        }
        except Exception as e:
            logger.warning(f"pytrends error for batch {batch}: {e}")

        # A short politeness gap between batches — no longer trying to dodge
        # a throttle, since related_queries() (removed above) was the actual
        # cause, not request pacing.
        time.sleep(3)

    return results


async def fetch_google_trends(
    keywords: list[str],
    country_iso2: str | None,
    timeframe: str = "today 12-m",
) -> dict[str, list[dict]]:
    """
    Fetch Google Trends data for a list of keywords.
    Returns {keyword: [{date, interest, related_queries}]}
    """
    geo = country_iso2.upper() if country_iso2 else ""
    loop = asyncio.get_event_loop()

    logger.info(f"Fetching Google Trends: {len(keywords)} keywords, geo={geo or 'worldwide'}")

    try:
        raw = await loop.run_in_executor(
            _executor,
            _fetch_interest_sync,
            keywords,
            timeframe,
            geo,
        )
    except Exception as e:
        logger.error(f"Google Trends fetch failed: {e}")
        return {}

    # Transform to list of dicts
    results: dict[str, list[dict]] = {}
    for kw, data in raw.items():
        rows = []
        for ts, interest in data["interest_over_time"].items():
            rows.append({
                "date":            ts.date() if hasattr(ts, "date") else ts,
                "interest":        int(interest),
                "related_queries": data.get("related_queries"),
            })
        results[kw] = rows

    return results


async def save_search_metrics(
    trend_id: int,
    keyword: str,
    country_id: int | None,
    rows: list[dict],
) -> int:
    """Upsert search_metrics rows and return count saved."""
    saved = 0
    for row in rows:
        metric_date = row["date"]
        if isinstance(metric_date, str):
            metric_date = datetime.strptime(metric_date, "%Y-%m-%d").date()

        related = row.get("related_queries")
        related_json = "[]"
        if related is not None:
            import json
            try:
                if hasattr(related, "to_dict"):
                    related_json = json.dumps(related.to_dict(orient="records")[:10])
                else:
                    related_json = json.dumps(related)
            except Exception:
                pass

        await execute(
            """
            INSERT INTO search_metrics
                (trend_id, country_id, keyword, metric_date, interest, related_queries, created_at)
            VALUES ($1, $2, $3, $4, $5, $6::jsonb, NOW())
            ON CONFLICT (trend_id, country_id, keyword, metric_date)
            DO UPDATE SET interest = EXCLUDED.interest
            """,
            trend_id,
            country_id,
            keyword,
            metric_date,
            row["interest"],
            related_json,
        )
        saved += 1

    return saved


async def update_trend_search_score(trend_id: int, country_id: int | None) -> int:
    """
    Calculate search_score (0-100) for a trend from its recent search_metrics.
    Uses the average of the last 4 weeks relative to the max ever seen.
    """
    rows = await fetch(
        """
        SELECT interest, metric_date
        FROM search_metrics
        WHERE trend_id = $1
          AND (country_id = $2 OR ($2 IS NULL AND country_id IS NULL))
        ORDER BY metric_date DESC
        LIMIT 52
        """,
        trend_id,
        country_id,
    )

    if not rows:
        return 0

    interests = [r["interest"] for r in rows]
    max_interest = max(interests) or 1
    recent_avg = sum(interests[:4]) / min(len(interests), 4)
    score = int((recent_avg / max_interest) * 100)

    await execute(
        "UPDATE trends SET search_score = $1, last_updated_at = NOW() WHERE id = $2",
        score,
        trend_id,
    )

    # The single trends.search_score column above gets overwritten by every
    # country in the loop, so it only ever reflects whichever country ran
    # last. Also persist a per-country value in trend_countries — this is
    # what the Chile forecast reads (chile_momentum), and what previously
    # never got written anywhere.
    if country_id is not None:
        await execute(
            """
            INSERT INTO trend_countries (trend_id, country_id, strength, first_seen_at, created_at)
            VALUES ($1, $2, $3, NOW(), NOW())
            ON CONFLICT (trend_id, country_id) DO UPDATE
            SET strength = EXCLUDED.strength
            """,
            trend_id,
            country_id,
            score,
        )

    return score
