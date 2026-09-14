"""
Reddit scraper using PRAW (official Python Reddit API Wrapper).
Fetches top posts from configured subreddits.
Stores raw documents and raw posts in the database.
Respects Reddit API rate limits (60 requests/minute on free tier).
"""

import asyncio
import hashlib
import json
import logging
import os
from datetime import datetime

import praw
from praw.models import Submission

from db.client import execute, fetchrow

logger = logging.getLogger(__name__)

REDDIT_CLIENT_ID     = os.getenv("REDDIT_CLIENT_ID", "")
REDDIT_CLIENT_SECRET = os.getenv("REDDIT_CLIENT_SECRET", "")
REDDIT_USER_AGENT    = os.getenv("REDDIT_USER_AGENT", "FashionIntelligence/1.0")

_reddit: praw.Reddit | None = None


def get_reddit() -> praw.Reddit:
    global _reddit
    if _reddit is None:
        if not REDDIT_CLIENT_ID or not REDDIT_CLIENT_SECRET:
            raise RuntimeError(
                "Reddit API credentials not set. "
                "Add REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET to .env\n"
                "Get them at: https://www.reddit.com/prefs/apps"
            )
        _reddit = praw.Reddit(
            client_id=REDDIT_CLIENT_ID,
            client_secret=REDDIT_CLIENT_SECRET,
            user_agent=REDDIT_USER_AGENT,
            ratelimit_seconds=1,  # PRAW handles rate limiting automatically
        )
        _reddit.read_only = True  # We only read, never write
    return _reddit


async def scrape_subreddit(
    subreddit_name: str,
    scrape_run_id: int,
    source_id: int,
    country_id: int | None = None,
    sort: str = "top",
    time_filter: str = "year",
    limit: int = 100,
) -> dict:
    """
    Scrape top posts from a subreddit.
    PRAW is synchronous — runs in thread executor.
    Returns stats: {posts_scraped, posts_saved, duplicates_skipped}
    """
    loop = asyncio.get_event_loop()
    import concurrent.futures
    with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
        posts = await loop.run_in_executor(
            pool,
            _fetch_posts_sync,
            subreddit_name,
            sort,
            time_filter,
            limit,
        )

    saved = 0
    skipped = 0

    for post_data in posts:
        was_saved = await _save_post(post_data, scrape_run_id, source_id, country_id, subreddit_name)
        if was_saved:
            saved += 1
        else:
            skipped += 1

    logger.info(f"r/{subreddit_name}: {saved} saved, {skipped} duplicates")

    return {
        "subreddit":          subreddit_name,
        "posts_scraped":      len(posts),
        "posts_saved":        saved,
        "duplicates_skipped": skipped,
    }


def _fetch_posts_sync(
    subreddit_name: str,
    sort: str,
    time_filter: str,
    limit: int,
) -> list[dict]:
    """Synchronous Reddit fetch — runs in thread pool."""
    reddit   = get_reddit()
    sub      = reddit.subreddit(subreddit_name)
    listings = {
        "top":  sub.top(time_filter=time_filter, limit=limit),
        "hot":  sub.hot(limit=limit),
        "new":  sub.new(limit=limit),
    }
    posts = []

    for submission in listings.get(sort, listings["top"]):
        if submission.is_self or submission.url:
            posts.append(_submission_to_dict(submission))

    return posts


def _submission_to_dict(sub: Submission) -> dict:
    return {
        "platform_id":   sub.id,
        "title":         sub.title,
        "body":          sub.selftext or None,
        "score":         sub.score,
        "upvote_ratio":  sub.upvote_ratio,
        "comment_count": sub.num_comments,
        "is_nsfw":       sub.over_18,
        "flair":         sub.link_flair_text,
        "link_url":      sub.url if not sub.is_self else None,
        "author":        str(sub.author) if sub.author else "[deleted]",
        "published_at":  datetime.utcfromtimestamp(sub.created_utc),
        "source_url":    f"https://www.reddit.com{sub.permalink}",
        "image_urls":    _extract_image_urls(sub),
    }


def _extract_image_urls(sub: Submission) -> list[str]:
    urls = []
    if hasattr(sub, "preview") and sub.preview:
        for img in sub.preview.get("images", []):
            src = img.get("source", {}).get("url")
            if src:
                urls.append(src.replace("&amp;", "&"))
    if sub.url and any(sub.url.lower().endswith(ext) for ext in [".jpg", ".jpeg", ".png", ".webp", ".gif"]):
        urls.append(sub.url)
    return urls[:5]


async def _save_post(
    post: dict,
    scrape_run_id: int,
    source_id: int,
    country_id: int | None,
    subreddit_name: str,
) -> bool:
    """
    Save a Reddit post to raw_documents + raw_posts.
    Returns True if saved, False if duplicate.
    """
    # Deduplicate by content hash
    content = f"{post['title']} {post['body'] or ''}"
    content_hash = hashlib.sha256(content.encode()).hexdigest()

    existing = await fetchrow(
        "SELECT id FROM raw_documents WHERE content_hash = $1",
        content_hash,
    )
    if existing:
        return False

    # Insert raw_document
    doc_id = await fetchrow(
        """
        INSERT INTO raw_documents
            (scrape_run_id, source_id, country_id, source_url, document_type,
             title, body, content_hash, language, processing_status, scraped_at,
             published_at, author, metadata, created_at, updated_at)
        VALUES ($1,$2,$3,$4,'post',$5,$6,$7,'en','pending',NOW(),$8,$9,'{}',NOW(),NOW())
        RETURNING id
        """,
        scrape_run_id,
        source_id,
        country_id,
        post["source_url"],
        post["title"],
        post["body"],
        content_hash,
        post["published_at"],
        post["author"],
    )

    if not doc_id:
        return False

    doc_id = doc_id["id"]

    # Insert raw_post
    await execute(
        """
        INSERT INTO raw_posts
            (raw_document_id, platform, platform_id, community, author,
             title, body, score, upvote_ratio, comment_count, is_nsfw, flair,
             link_url, image_urls, published_at, scraped_at, metadata, created_at)
        VALUES ($1,'reddit',$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13::jsonb,$14,NOW(),'{}',NOW())
        ON CONFLICT DO NOTHING
        """,
        doc_id,
        post["platform_id"],
        subreddit_name,
        post["author"],
        post["title"],
        post["body"],
        post["score"],
        post["upvote_ratio"],
        post["comment_count"],
        post["is_nsfw"],
        post["flair"],
        post["link_url"],
        json.dumps(post["image_urls"]),
        post["published_at"],
    )

    # Update scrape_run stats
    await execute(
        "UPDATE scrape_runs SET records_collected = records_collected + 1 WHERE id = $1",
        scrape_run_id,
    )

    return True


def is_configured() -> bool:
    """Returns True if Reddit credentials are set."""
    return bool(REDDIT_CLIENT_ID and REDDIT_CLIENT_SECRET)
