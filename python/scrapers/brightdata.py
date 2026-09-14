"""
Bright Data Instagram Scraper API client.

Uses Bright Data's Web Scraper APIs directly.

Instagram Profiles:
    gd_l1vikfch901nx3by4

Instagram Posts:
    gd_lk5ns7kz21pck8jpis

This module does NOT use the Bright Data Datasets download workflow.
It uses:

    POST /datasets/v3/scrape
"""

import logging
import os
from typing import Any

import httpx
from tenacity import retry, stop_after_attempt, wait_exponential

logger = logging.getLogger(__name__)

BASE_URL = "https://api.brightdata.com/datasets/v3"

API_TOKEN = os.getenv("BRIGHTDATA_API_TOKEN", "")

PROFILES_DATASET_ID = os.getenv(
    "BRIGHTDATA_DATASET_ID_PROFILES",
    "gd_l1vikfch901nx3by4",
)

POSTS_DATASET_ID = os.getenv(
    "BRIGHTDATA_DATASET_ID_POSTS",
    "gd_lk5ns7kz21pck8jpis",
)


def is_configured() -> bool:
    return bool(API_TOKEN)


def _headers() -> dict[str, str]:
    if not API_TOKEN:
        raise RuntimeError(
            "BRIGHTDATA_API_TOKEN is not configured."
        )

    return {
        "Authorization": f"Bearer {API_TOKEN}",
        "Content-Type": "application/json",
    }


@retry(
    stop=stop_after_attempt(3),
    wait=wait_exponential(
        multiplier=2,
        min=2,
        max=15,
    ),
    reraise=True,
)
async def scrape(
    dataset_id: str,
    payload: dict[str, Any],
    *,
    scrape_type: str = "discover_new",
    discover_by: str | None = None,
) -> list[dict[str, Any]]:
    """
    Execute a Bright Data Scraper API request.

    This uses:
        POST /datasets/v3/scrape

    Unlike the asynchronous trigger/snapshot workflow, this endpoint
    returns the scraper results directly.
    """

    params: dict[str, str] = {
        "dataset_id": dataset_id,
        "notify": "false",
        "include_errors": "true",
        "type": scrape_type,
    }

    if discover_by:
        params["discover_by"] = discover_by

    logger.info(
        "Bright Data scrape: dataset=%s type=%s discover_by=%s",
        dataset_id,
        scrape_type,
        discover_by,
    )

    async with httpx.AsyncClient(timeout=180) as client:
        response = await client.post(
            f"{BASE_URL}/scrape",
            params=params,
            headers=_headers(),
            json=payload,
        )

        if response.status_code >= 400:
            logger.error(
                "Bright Data API error %s: %s",
                response.status_code,
                response.text,
            )

        response.raise_for_status()

        result = response.json()

    # Bright Data can return a list directly.
    if isinstance(result, list):
        return result

    # Defensive handling for wrapped responses.
    if isinstance(result, dict):

        if isinstance(result.get("data"), list):
            return result["data"]

        if isinstance(result.get("results"), list):
            return result["results"]

        # If Bright Data returns an error payload while HTTP status
        # happens to be successful, expose it clearly.
        if result.get("error"):
            raise RuntimeError(
                f"Bright Data error: {result['error']}"
            )

    raise RuntimeError(
        "Unexpected Bright Data response: "
        f"{result}"
    )


async def scrape_profiles_by_username(
    usernames: list[str],
) -> list[dict[str, Any]]:
    """
    Instagram Profiles scraper.

    Example input:

        [{"user_name": "zoobarcelona"}]
    """

    inputs = []

    for username in usernames:
        username = (
            username
            .strip()
            .lstrip("@")
        )

        if username:
            inputs.append(
                {
                    "user_name": username,
                }
            )

    if not inputs:
        return []

    return await scrape(
        dataset_id=PROFILES_DATASET_ID,
        payload={
            "input": inputs,
            "limit_per_input": None,
        },
        scrape_type="discover_new",
        discover_by="user_name",
    )


async def scrape_posts_by_profiles(
    profiles: list[dict[str, Any]],
) -> list[dict[str, Any]]:
    """
    Instagram Posts scraper.

    profiles example:

        [
            {
                "url": "https://www.instagram.com/meta/",
                "num_of_posts": 10,
                "post_type": "Post",
            }
        ]
    """

    inputs = []

    for profile in profiles:
        url = profile.get("url")

        if not url:
            continue

        item = {
            "url": url,
        }

        if profile.get("num_of_posts") is not None:
            item["num_of_posts"] = profile["num_of_posts"]

        if profile.get("start_date"):
            item["start_date"] = profile["start_date"]

        if profile.get("end_date"):
            item["end_date"] = profile["end_date"]

        if profile.get("post_type"):
            item["post_type"] = profile["post_type"]

        if profile.get("posts_to_not_include"):
            item["posts_to_not_include"] = (
                profile["posts_to_not_include"]
            )

        inputs.append(item)

    if not inputs:
        return []

    return await scrape(
        dataset_id=POSTS_DATASET_ID,
        payload={
            "input": inputs,
            "limit_per_input": None,
        },
        scrape_type="discover_new",
        discover_by="url",
    )