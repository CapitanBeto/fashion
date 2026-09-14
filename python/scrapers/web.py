"""
Web/article scraper — pulls configured niche fashion media pages via
LocalScraper (already built in scrapers/base.py, previously never wired into
the experiment pipeline) and stores them as raw_documents, exactly like
reddit.py does for posts. Once saved, they flow through the same NLP
entity-extraction step as everything else — no separate processing path.
"""

import hashlib
import logging

from db.client import execute, fetch, fetchrow
from scrapers.base import ScrapeTarget, get_scraper

logger = logging.getLogger(__name__)


async def get_web_targets(niche_id: int | None, source_id: int) -> list[dict]:
    """Active 'url' source_targets configured for this source/niche."""
    rows = await fetch(
        """
        SELECT id, target_value, country_id
        FROM source_targets
        WHERE source_id = $1 AND target_type = 'url' AND active = true
          AND (niche_id = $2 OR niche_id IS NULL)
        ORDER BY priority DESC
        """,
        source_id, niche_id,
    )
    return [dict(r) for r in rows]


async def scrape_web_targets(
    targets: list[dict],
    scrape_run_id: int,
    source_id: int,
) -> dict:
    """
    Scrape each configured URL once, save as a raw_document if new.
    Returns stats: {pages_attempted, pages_saved, duplicates_skipped, blocked_or_failed}
    """
    scraper = get_scraper()
    saved = skipped = failed = 0

    for target in targets:
        url = target["target_value"]
        result = await scraper.scrape(ScrapeTarget(url=url))

        if not result.success:
            failed += 1
            logger.warning(f"Web scrape failed for {url}: {result.error}")
            await execute(
                """
                INSERT INTO scraping_errors (scrape_run_id, url, error_type, error_message, http_status, provider, created_at)
                VALUES ($1, $2, 'scrape_failed', $3, $4, 'local', NOW())
                """,
                scrape_run_id, url, result.error, result.http_status,
            )
            continue

        content = f"{result.title or ''} {result.body or ''}"
        content_hash = hashlib.sha256(content.encode()).hexdigest()

        existing = await fetchrow("SELECT id FROM raw_documents WHERE content_hash = $1", content_hash)
        if existing:
            skipped += 1
            continue

        await execute(
            """
            INSERT INTO raw_documents
                (scrape_run_id, source_id, country_id, source_url, canonical_url, document_type,
                 title, body, content_hash, language, processing_status, scraped_at,
                 metadata, created_at, updated_at)
            VALUES ($1,$2,$3,$4,$5,'article',$6,$7,$8,'es','pending',NOW(),$9::jsonb,NOW(),NOW())
            """,
            scrape_run_id, source_id, target["country_id"], url, result.canonical_url,
            result.title, result.body, content_hash,
            f'{{"word_count": {result.metadata.get("word_count", 0)}}}',
        )
        saved += 1

    logger.info(f"Web: {saved} saved, {skipped} duplicates, {failed} failed/blocked out of {len(targets)} targets")
    return {"pages_attempted": len(targets), "pages_saved": saved, "duplicates_skipped": skipped, "blocked_or_failed": failed}
