"""
Experiment runner endpoint.
POST /experiments/run — executes the full pipeline for an experiment.
This is what Laravel's RunExperimentJob calls.
"""

import asyncio
import json
import logging
import time
from datetime import datetime

from fastapi import APIRouter, HTTPException, Request
from pydantic import BaseModel

from db.client import execute, fetch, fetchrow
from llm.creative_director import generate_product_concept, save_commercial_opportunity
from nlp.entity_extractor import extract_entities, match_and_save_trend_mentions
from scrapers.google_trends import (
    fetch_google_trends,
    save_search_metrics,
    update_trend_search_score,
)
from scrapers.reddit import is_configured as reddit_configured, scrape_subreddit
from scrapers.web import get_web_targets, scrape_web_targets
from scoring.momentum import (
    calculate_convergence_score,
    calculate_death_probability,
    calculate_momentum_score,
    classify_lifecycle_stage,
)

router = APIRouter()
logger = logging.getLogger(__name__)


class ExperimentConfig(BaseModel):
    experiment_key: str
    config: dict


class ExperimentResult(BaseModel):
    experiment_key: str
    trends_found: int
    documents_scraped: int
    documents_processed: int
    mentions_created: int
    duration_seconds: float
    sources_used: list[str]


@router.post("/experiments/run", response_model=ExperimentResult)
async def run_experiment(body: ExperimentConfig, request: Request):
    """
    Full experiment pipeline:
    1. Google Trends → search_metrics + update trends
    2. Reddit → raw_documents + raw_posts
    3. NLP → entity extraction → trend_mentions
    4. Scoring → momentum, lifecycle, commercial opportunity
    5. LLM Creative Director → commercial_opportunities
    """
    cfg         = body.config
    key         = body.experiment_key
    start_time  = time.time()

    keywords    = cfg.get("keywords", [])
    subreddits  = cfg.get("subreddits", [])
    countries   = cfg.get("countries", ["US"])
    period_days = cfg.get("period_days", 180)
    niche_slug  = cfg.get("niche", "streetwear")

    logger.info(f"[{key}] Starting experiment: {len(keywords)} keywords, "
                f"{len(subreddits)} subreddits, countries={countries}")

    # ── Resolve IDs ──────────────────────────────────────────────────────────
    niche_id = None
    if niche_slug:
        row = await fetchrow("SELECT id FROM niches WHERE slug = $1", niche_slug)
        niche_id = row["id"] if row else None

    # No implicit "worldwide" (geo="") fetch — it doubled every Google Trends
    # request for no benefit and was a direct contributor to getting rate
    # limited. Only fetch the countries actually configured for the experiment.
    country_map: dict[str, int | None] = {}
    for iso2 in countries:
        row = await fetchrow("SELECT id FROM countries WHERE iso2 = $1", iso2.upper())
        if row:
            country_map[iso2.upper()] = row["id"]

    # ── Ensure trends exist for each keyword ─────────────────────────────────
    trend_ids: dict[str, int] = {}
    for kw in keywords:
        slug = kw.lower().replace(" ", "-")
        existing = await fetchrow("SELECT id FROM trends WHERE slug = $1", slug)
        if existing:
            trend_ids[kw] = existing["id"]
        else:
            row = await fetchrow(
                """
                INSERT INTO trends (name, slug, niche_id, detection_method, keywords,
                                    lifecycle_stage, first_seen_at, last_updated_at,
                                    created_at, updated_at)
                VALUES ($1, $2, $3, 'known', $4::jsonb, 'EMERGING', NOW(), NOW(), NOW(), NOW())
                RETURNING id
                """,
                kw,
                slug,
                niche_id,
                json.dumps([kw]),
            )
            trend_ids[kw] = row["id"]
            logger.info(f"[{key}] Created trend: {kw} (id={row['id']})")

    # ── Create scrape run record ──────────────────────────────────────────────
    import uuid
    run_row = await fetchrow(
        """
        INSERT INTO scrape_runs (uuid, experiment_name, provider, status, started_at, created_at, updated_at)
        VALUES ($1, $2, 'local', 'running', NOW(), NOW(), NOW())
        RETURNING id
        """,
        str(uuid.uuid4()),
        key,
    )
    run_id = run_row["id"]

    docs_scraped     = 0
    docs_processed   = 0
    mentions_created = 0
    sources_used     = []

    # ─────────────────────────────────────────────────────────────────────────
    # STEP 1 — Google Trends
    # ─────────────────────────────────────────────────────────────────────────
    logger.info(f"[{key}] Step 1/5: Google Trends ({len(keywords)} keywords)")
    try:
        timeframe = f"today {period_days}-d" if period_days <= 270 else "today 12-m"

        for i, (iso2, country_id) in enumerate(country_map.items()):
            if i > 0:
                # Short politeness gap between countries — the real cause of
                # the earlier HTTP 400s was the related_queries() call
                # (removed in google_trends.py), not request pacing.
                await asyncio.sleep(3)
            geo = iso2 if iso2 != "global" else None
            trends_data = await fetch_google_trends(keywords, geo, timeframe)

            for kw, rows in trends_data.items():
                if kw in trend_ids and rows:
                    saved = await save_search_metrics(trend_ids[kw], kw, country_id, rows)
                    docs_scraped += saved
                    await update_trend_search_score(trend_ids[kw], country_id)

                    # Create a snapshot for today
                    latest = rows[-1] if rows else None
                    if latest:
                        await execute(
                            """
                            INSERT INTO trend_snapshots
                                (trend_id, country_id, snapshot_date, search_interest,
                                 mention_count, momentum_score, created_at)
                            VALUES ($1, $2, $3, $4, 0, 0, NOW())
                            ON CONFLICT (trend_id, country_id, snapshot_date) DO UPDATE
                            SET search_interest = EXCLUDED.search_interest
                            """,
                            trend_ids[kw],
                            country_id,
                            datetime.now().date(),
                            latest["interest"],
                        )

        sources_used.append("google_trends")
        logger.info(f"[{key}] Google Trends: {docs_scraped} data points saved")
    except Exception as e:
        logger.error(f"[{key}] Google Trends failed: {e}")

    # ─────────────────────────────────────────────────────────────────────────
    # STEP 2 — Reddit
    # ─────────────────────────────────────────────────────────────────────────
    logger.info(f"[{key}] Step 2/5: Reddit ({len(subreddits)} subreddits)")
    reddit_source = await fetchrow("SELECT id FROM sources WHERE slug = 'reddit'")
    reddit_source_id = reddit_source["id"] if reddit_source else None

    if reddit_configured() and subreddits:
        for sub in subreddits:
            try:
                stats = await scrape_subreddit(
                    subreddit_name=sub,
                    scrape_run_id=run_id,
                    source_id=reddit_source_id,
                    country_id=None,
                    sort="top",
                    time_filter="year",
                    limit=cfg.get("max_records", 100),
                )
                docs_scraped += stats["posts_saved"]
                sources_used.append("reddit")
            except Exception as e:
                logger.error(f"[{key}] Reddit r/{sub} failed: {e}")
    else:
        if not reddit_configured():
            logger.warning(f"[{key}] Reddit skipped: no API credentials in .env")
        else:
            logger.info(f"[{key}] Reddit skipped: no subreddits configured")

    # ─────────────────────────────────────────────────────────────────────────
    # STEP 3 — Web (niche fashion media configured as source_targets)
    # ─────────────────────────────────────────────────────────────────────────
    logger.info(f"[{key}] Step 3/5: Web")
    try:
        web_source = await fetchrow("SELECT id FROM sources WHERE slug = 'web-crawler'")
        web_source_id = web_source["id"] if web_source else None

        if web_source_id:
            web_targets = await get_web_targets(niche_id, web_source_id)
            if web_targets:
                web_stats = await scrape_web_targets(web_targets, run_id, web_source_id)
                docs_scraped += web_stats["pages_saved"]
                if web_stats["pages_saved"] > 0:
                    sources_used.append("web")
                logger.info(f"[{key}] Web: {web_stats}")
            else:
                logger.info(f"[{key}] Web skipped: no url source_targets configured for this niche")
        else:
            logger.warning(f"[{key}] Web skipped: 'web-crawler' source not seeded")
    except Exception as e:
        logger.error(f"[{key}] Web scraping failed: {e}")

    # ─────────────────────────────────────────────────────────────────────────
    # STEP 4 — NLP: process raw documents → trend mentions
    # ─────────────────────────────────────────────────────────────────────────
    logger.info(f"[{key}] Step 4/5: NLP processing")
    translations = cfg.get("keyword_translations", {})
    trend_keywords_list = [
        (tid, [kw] + kw.split() + translations.get(kw, []))
        for kw, tid in trend_ids.items()
    ]

    pending_docs = await fetch(
        """
        SELECT id, title, body, source_id, country_id, published_at
        FROM raw_documents
        WHERE processing_status = 'pending'
          AND scrape_run_id = $1
        LIMIT 500
        """,
        run_id,
    )

    for doc in pending_docs:
        try:
            text = f"{doc['title'] or ''} {doc['body'] or ''}"
            entities = await extract_entities(text)

            created = await match_and_save_trend_mentions(
                document_id=doc["id"],
                entities=entities,
                trend_keywords=trend_keywords_list,
                source_id=doc["source_id"],
                country_id=doc["country_id"],
                published_at=doc["published_at"],
            )

            mentions_created += created

            await execute(
                "UPDATE raw_documents SET processing_status = 'processed' WHERE id = $1",
                doc["id"],
            )
            docs_processed += 1

        except Exception as e:
            logger.warning(f"[{key}] Failed to process doc {doc['id']}: {e}")
            await execute(
                "UPDATE raw_documents SET processing_status = 'failed' WHERE id = $1",
                doc["id"],
            )

    logger.info(f"[{key}] NLP: {docs_processed} docs processed, {mentions_created} mentions")

    # calculate_momentum_score() (scoring/momentum.py) reads growth off
    # trend_snapshots.mention_count — nothing wrote to that column before,
    # so real mentions never moved momentum no matter how many were created.
    # Stamp today's global (country_id IS NULL) snapshot with the current
    # total mention count for every trend touched this run.
    for trend_id in trend_ids.values():
        mention_total = await fetchrow(
            "SELECT COUNT(*) AS cnt FROM trend_mentions WHERE trend_id = $1", trend_id
        )
        await execute(
            """
            INSERT INTO trend_snapshots (trend_id, country_id, snapshot_date, mention_count, created_at)
            VALUES ($1, NULL, $2, $3, NOW())
            ON CONFLICT (trend_id, country_id, snapshot_date) DO UPDATE
            SET mention_count = EXCLUDED.mention_count
            """,
            trend_id, datetime.now().date(), mention_total["cnt"],
        )

    # ─────────────────────────────────────────────────────────────────────────
    # STEP 4 — Scoring + Creative Director
    # ─────────────────────────────────────────────────────────────────────────
    logger.info(f"[{key}] Step 5/5: Scoring and LLM analysis")

    for kw, trend_id in trend_ids.items():
        try:
            # Momentum
            momentum = await calculate_momentum_score(trend_id)

            # Convergence
            convergence = await calculate_convergence_score(trend_id, sources_used)

            # Death probability
            death_prob = await calculate_death_probability(trend_id)

            # Lifecycle
            stage = await classify_lifecycle_stage(trend_id)

            # Update trend scores
            mention_count = await fetchrow(
                "SELECT COUNT(*) as cnt FROM trend_mentions WHERE trend_id = $1",
                trend_id,
            )
            mentions = mention_count["cnt"] if mention_count else 0

            await execute(
                """
                UPDATE trends SET
                    momentum_score    = $1,
                    convergence_score = $2,
                    death_probability = $3,
                    lifecycle_stage   = $4,
                    last_updated_at   = NOW(),
                    updated_at        = NOW()
                WHERE id = $5
                """,
                momentum,
                convergence,
                death_prob,
                stage,
                trend_id,
            )

            # LLM Creative Director: generate product concept
            if momentum >= 20:  # Only if there's enough signal
                concept = await generate_product_concept(trend_id)
                if concept:
                    scores = {
                        "opportunity_score": min(momentum + convergence // 2, 100),
                        "demand_score":      momentum,
                        "competition_score": 30,  # default until competitor data
                        "momentum_score":    momentum,
                        "timing_score":      70,  # default
                        "confidence":        min(docs_processed * 5, 80),
                    }
                    await save_commercial_opportunity(trend_id, None, concept, scores)
                    logger.info(f"[{key}] Generated product concept for: {kw}")

        except Exception as e:
            logger.error(f"[{key}] Scoring failed for '{kw}': {e}")

    # ── Finalize scrape run ───────────────────────────────────────────────────
    duration = time.time() - start_time
    await execute(
        """
        UPDATE scrape_runs SET
            status            = 'completed',
            finished_at       = NOW(),
            duration_seconds  = $1,
            records_collected = $2,
            records_processed = $3,
            updated_at        = NOW()
        WHERE id = $4
        """,
        int(duration),
        docs_scraped,
        docs_processed,
        run_id,
    )

    logger.info(f"[{key}] Experiment completed in {duration:.1f}s")

    return ExperimentResult(
        experiment_key=key,
        trends_found=len(trend_ids),
        documents_scraped=docs_scraped,
        documents_processed=docs_processed,
        mentions_created=mentions_created,
        duration_seconds=round(duration, 1),
        sources_used=list(set(sources_used)),
    )
