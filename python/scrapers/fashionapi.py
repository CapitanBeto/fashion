import os
from typing import Any

import httpx
from dotenv import load_dotenv

load_dotenv()

FASHION_DATA_API_URL = os.getenv(
    "FASHION_DATA_API_URL",
    "http://127.0.0.1:8000",
).rstrip("/")


def is_configured() -> bool:
    return bool(FASHION_DATA_API_URL)


async def discover_posts_by_profiles(
    profiles: list[str],
) -> list[dict[str, Any]]:

    usernames = []

    for profile in profiles:
        profile = profile.strip()

        if not profile:
            continue

        profile = profile.rstrip("/")

        if "instagram.com/" in profile:
            username = profile.split("instagram.com/")[-1]
        else:
            username = profile

        username = username.split("?")[0]
        username = username.lstrip("@").strip("/")

        if username:
            usernames.append(username)

    if not usernames:
        return []

    async with httpx.AsyncClient(timeout=180) as client:

        response = await client.post(
            f"{FASHION_DATA_API_URL}/instagram/profiles",
            json={
                "usernames": usernames
            },
        )

        response.raise_for_status()

        result = response.json()

    if not result.get("success"):
        raise RuntimeError(
            f"fashion-data-api devolvió una respuesta inválida: {result}"
        )

    profiles_data = result.get("data", [])

    if not isinstance(profiles_data, list):
        raise RuntimeError(
            "fashion-data-api no devolvió una lista de perfiles"
        )

    posts = []

    for profile in profiles_data:

        if not isinstance(profile, dict):
            continue

        username = (
            profile.get("user_name")
            or profile.get("username")
            or profile.get("name")
            or ""
        )

        profile_posts = profile.get("posts", [])

        if not isinstance(profile_posts, list):
            continue

        for post in profile_posts:

            if not isinstance(post, dict):
                continue

            normalized = {
                "post_id": post.get("id"),
                "url": post.get("url"),
                "user_posted": username,
                "description": post.get("caption"),
                "hashtags": post.get("post_hashtags"),
                "date_posted": post.get("datetime"),
                "likes": post.get("likes"),
                "num_comments": post.get("comments"),
                "photos": (
                    [post["image_url"]]
                    if post.get("image_url")
                    else []
                ),
                "raw": post,
            }

            posts.append(normalized)

    return posts


async def scrape_profiles_by_username(
    usernames: list[str],
) -> list[dict[str, Any]]:

    async with httpx.AsyncClient(timeout=180) as client:

        response = await client.post(
            f"{FASHION_DATA_API_URL}/instagram/profiles",
            json={
                "usernames": usernames
            },
        )

        response.raise_for_status()

        result = response.json()

    if not result.get("success"):
        raise RuntimeError(
            f"fashion-data-api devolvió una respuesta inválida: {result}"
        )

    return result.get("data", [])


async def scrape_posts_by_profiles(
    profiles: list[dict[str, Any]],
) -> list[dict[str, Any]]:

    # El endpoint de perfiles ya devuelve los posts
    # asociados a cada perfil.
    usernames = []

    for profile in profiles:

        url = profile.get("url", "")

        if "instagram.com/" in url:
            username = url.split("instagram.com/")[-1]
            username = username.rstrip("/").split("?")[0]

            if username:
                usernames.append(username)

    return await discover_posts_by_profiles(usernames)