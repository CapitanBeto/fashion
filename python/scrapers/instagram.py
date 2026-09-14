"""
Instagram ingestion through Bright Data.

Reads Instagram accounts from source_targets, asks Bright Data to discover
recent posts from those profiles, normalizes the returned records and stores
them in raw_documents + raw_posts.

The existing NLP pipeline then processes the resulting raw_documents exactly
like Reddit and Web content.
"""

import hashlib
import json
import logging
import os
from datetime import datetime
from typing import Any

from db.client import execute, fetch, fetchrow
from scrapers.brightdata import (
    discover_posts_by_profiles,
    is_configured,
)

logger = logging.getLogger(__name__)

DEFAULT_BATCH_SIZE = int(
    os.getenv("BRIGHTDATA_BATCH_SIZE", "50")
)

DEFAULT_POSTS_PER_ACCOUNT = int(
    os.getenv("BRIGHTDATA_POSTS_PER_ACCOUNT", "20")
)


def _instagram_profile_url(value: str) -> str:
    """
    Normalize either an Instagram handle or an Instagram profile URL.
    """

    value = (value or "").strip()

    if not value:
        return ""

    if value.startswith("http://") or value.startswith("https://"):
        return value.rstrip("/") + "/"

    handle = value.lstrip("@").strip("/")

    return f"https://www.instagram.com/{handle}/"


def _parse_datetime(value: Any) -> datetime | None:
    """Convert Bright Data's ISO timestamp to a Python datetime."""

    if not value:
        return None

    if isinstance(value, datetime):
        return value

    try:
        text = str(value).strip()

        if text.endswith("Z"):
            text = text[:-1] + "+00:00"

        return datetime.fromisoformat(text)

    except (ValueError, TypeError):
        logger.warning(
            "Could not parse Instagram date: %r",
            value,
        )
        return None


def _normalize_post(raw: dict[str, Any]) -> dict[str, Any]:
    """
    Normalize Bright Data's Instagram Posts response.

    Bright Data's current Instagram Posts output includes fields such as:
    url, user_posted, description, hashtags, num_comments, date_posted,
    likes and photos.
    """

    url = (
        raw.get("url")
        or raw.get("post_url")
        or raw.get("source_url")
    )

    author = (
        raw.get("user_posted")
        or raw.get("username")
        or raw.get("account")
        or ""
    )

    caption = (
        raw.get("description")
        or raw.get("post_content")
        or raw.get("caption")
        or ""
    )

    platform_id = (
        raw.get("post_id")
        or raw.get("pk")
        or raw.get("content_id")
        or raw.get("shortcode")
        or ""
    )

    if not platform_id and url:
        # Last-resort stable-ish identifier based on URL.
        platform_id = hashlib.sha256(
            url.encode("utf-8")
        ).hexdigest()[:32]

    photos = (
        raw.get("photos")
        or raw.get("images")
        or []
    )

    if isinstance(photos, str):
        photos = [photos]

    return {
        "platform_id": str(platform_id),
        "author": str(author),
        "caption": str(caption),
        "title": None,
        "url": url,
        "likes": raw.get("likes"),
        "comment_count": raw.get("num_comments"),
        "published_at": _parse_datetime(
            raw.get("date_posted")
        ),
        "image_urls": photos,
        "hashtags": raw.get("hashtags") or [],
        "raw": raw,
    }


async def get_instagram_targets(
    niche_id: int | None,
    source_id: int,
) -> list[dict]:
    """
    Read active Instagram account targets from source_targets.
    """

    rows = await fetch(
        """
        SELECT id, target_type, target_value, country_id,
               priority, frequency, depth, config
        FROM source_targets
        WHERE source_id = $1
          AND target_type = 'instagram_account'
          AND active = true
          AND (niche_id = $2 OR niche_id IS NULL)
        ORDER BY priority DESC, id ASC
        """,
        source_id,
        niche_id,
    )

    return [dict(row) for row in rows]


async def _save_post(
    post: dict[str, Any],
    scrape_run_id: int,
    source_id: int,
    country_id: int | None,
) -> bool:
    """
    Save one Instagram post to raw_documents + raw_posts.

    Returns:
        True if newly saved.
        False if already present.
    """

    platform_id = post["platform_id"]

    if not platform_id:
        return False

    # Primary Instagram deduplication.
    existing_post = await fetchrow(
        """
        SELECT id
        FROM raw_posts
        WHERE platform = 'instagram'
          AND platform_id = $1
        LIMIT 1
        """,
        platform_id,
    )

    if existing_post:
        return False

    content = (
        f"{post['author']} "
        f"{post['caption']} "
        f"{post['url'] or ''}"
    )

    content_hash = hashlib.sha256(
        content.encode("utf-8")
    ).hexdigest()

    # Secondary raw-document deduplication.
    existing_document = await fetchrow(
        """
        SELECT id
        FROM raw_documents
        WHERE content_hash = $1
        LIMIT 1
        """,
        content_hash,
    )

    if existing_document:
        return False

    metadata = {
        "provider": "brightdata",
        "platform": "instagram",
        "hashtags": post["hashtags"],
        "likes": post["likes"],
        "photos": post["image_urls"],
        "brightdata": post["raw"],
    }

    document_row = await fetchrow(
        """
        INSERT INTO raw_documents
            (
                scrape_run_id,
                source_id,
                country_id,
                source_url,
                canonical_url,
                document_type,
                title,
                body,
                content_hash,
                language,
                processing_status,
                scraped_at,
                published_at,
                author,
                metadata,
                created_at,
                updated_at
            )
        VALUES
            (
                $1,
                $2,
                $3,
                $4,
                $4,
                'post',
                $5,
                $6,
                $7,
                'und',
                'pending',
                NOW(),
                $8,
                $9,
                $10::jsonb,
                NOW(),
                NOW()
            )
        RETURNING id
        """,
        scrape_run_id,
        source_id,
        country_id,
        post["url"],
        post["title"],
        post["caption"],
        content_hash,
        post["published_at"],
        post["author"],
        json.dumps(metadata, default=str),
    )

    if not document_row:
        return False

    document_id = document_row["id"]

    await execute(
        """
        INSERT INTO raw_posts
            (
                raw_document_id,
                platform,
                platform_id,
                community,
                author,
                title,
                body,
                score,
                upvote_ratio,
                comment_count,
                is_nsfw,
                flair,
                link_url,
                image_urls,
                published_at,
                scraped_at,
                metadata,
                created_at
            )
        VALUES
            (
                $1,
                'instagram',
                $2,
                NULL,
                $3,
                NULL,
                $4,
                $5,
                NULL,
                $6,
                false,
                NULL,
                $7,
                $8::jsonb,
                $9,
                NOW(),
                $10::jsonb,
                NOW()
            )
        ON CONFLICT DO NOTHING
        """,
        document_id,
        platform_id,
        post["author"],
        post["caption"],
        post["likes"],
        post["comment_count"],
        post["url"],
        json.dumps(post["image_urls"], default=str),
        post["published_at"],
        json.dumps(metadata, default=str),
    )

    await execute(
        """
        UPDATE scrape_runs
        SET records_collected = records_collected + 1
        WHERE id = $1
        """,
        scrape_run_id,
    )

    return True


async def scrape_instagram_targets(
    targets: list[dict],
    scrape_run_id: int,
    source_id: int,
    max_posts_per_account: int = DEFAULT_POSTS_PER_ACCOUNT,
) -> dict:
    """
    Discover and save recent Instagram posts for configured accounts.
    """

    empty_stats = {
        "accounts_attempted": 0,
        "posts_scraped": 0,
        "posts_saved": 0,
        "duplicates_skipped": 0,
        "failed": 0,
    }

    if not is_configured():
        logger.warning(
            "Instagram skipped: BRIGHTDATA_API_TOKEN is not configured."
        )
        return empty_stats

    if not targets:
        return empty_stats

    profile_urls = []

    country_by_url: dict[str, int | None] = {}

    for target in targets:
        url = _instagram_profile_url(
            target["target_value"]
        )

        if not url:
            continue

        profile_urls.append(url)
        country_by_url[url.rstrip("/")] = target["country_id"]

    # Remove duplicate accounts while preserving order.
    profile_urls = list(dict.fromkeys(profile_urls))

    if not profile_urls:
        return empty_stats

    stats = dict(empty_stats)
    stats["accounts_attempted"] = len(profile_urls)

    for start in range(
        0,
        len(profile_urls),
        DEFAULT_BATCH_SIZE,
    ):
        batch = profile_urls[
            start:start + DEFAULT_BATCH_SIZE
        ]

        logger.info(
            "Instagram/Bright Data: discovering posts for %s accounts",
            len(batch),
        )

        try:
            results = await discover_posts_by_profiles(batch)

            await execute(
                """
                UPDATE scrape_runs
                SET requests_made = requests_made + 1
                WHERE id = $1
                """,
                scrape_run_id,
            )

        except Exception as exc:
            stats["failed"] += len(batch)

            logger.exception(
                "Bright Data Instagram batch failed: %s",
                exc,
            )

            for profile_url in batch:
                await execute(
                    """
                    INSERT INTO scraping_errors
                        (
                            scrape_run_id,
                            url,
                            error_type,
                            error_message,
                            provider,
                            created_at
                        )
                    VALUES
                        (
                            $1,
                            $2,
                            'brightdata_request_failed',
                            $3,
                            'brightdata',
                            NOW()
                        )
                    """,
                    scrape_run_id,
                    profile_url,
                    str(exc),
                )

            continue

        stats["posts_scraped"] += len(results)

        # The discovery endpoint can return multiple posts per account.
        # Limit each account's contribution to keep one experiment bounded.
        per_account_count: dict[str, int] = {}

        for raw_post in results:
            post = _normalize_post(raw_post)

            if not post["url"]:
                continue

            author_key = post["author"].lower()

            current_count = per_account_count.get(
                author_key,
                0,
            )

            if current_count >= max_posts_per_account:
                continue

            per_account_count[author_key] = current_count + 1

            country_id = None

            # Prefer exact author/profile matching.
            for profile_url in batch:
                handle = profile_url.rstrip("/").split("/")[-1].lower()

                if handle == author_key:
                    country_id = country_by_url.get(
                        profile_url.rstrip("/")
                    )
                    break

            try:
                saved = await _save_post(
                    post=post,
                    scrape_run_id=scrape_run_id,
                    source_id=source_id,
                    country_id=country_id,
                )

                if saved:
                    stats["posts_saved"] += 1
                else:
                    stats["duplicates_skipped"] += 1

            except Exception as exc:
                stats["failed"] += 1

                logger.exception(
                    "Failed to save Instagram post %s: %s",
                    post["url"],
                    exc,
                )

                await execute(
                    """
                    INSERT INTO scraping_errors
                        (
                            scrape_run_id,
                            url,
                            error_type,
                            error_message,
                            provider,
                            created_at
                        )
                    VALUES
                        (
                            $1,
                            $2,
                            'save_failed',
                            $3,
                            'brightdata',
                            NOW()
                        )
                    """,
                    scrape_run_id,
                    post["url"],
                    str(exc),
                )

    logger.info(
        "Instagram: %s posts scraped, %s saved, %s duplicates, %s failed",
        stats["posts_scraped"],
        stats["posts_saved"],
        stats["duplicates_skipped"],
        stats["failed"],
    )

    return stats