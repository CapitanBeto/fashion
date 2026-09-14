import asyncio
import json

from scrapers.brightdata import (
    scrape_profiles_by_username,
    scrape_posts_by_profiles,
)


async def main():

    print("=" * 70)
    print("BRIGHT DATA INSTAGRAM API TEST")
    print("=" * 70)

    # ---------------------------------------------------------------
    # 1. PROFILE
    # ---------------------------------------------------------------

    print("\n[1] Instagram Profiles")

    profiles = await scrape_profiles_by_username(
        ["zoobarcelona"]
    )

    print(
        json.dumps(
            profiles,
            indent=2,
            ensure_ascii=False,
        )
    )

    # ---------------------------------------------------------------
    # 2. POSTS
    # ---------------------------------------------------------------

    print("\n[2] Instagram Posts")

    posts = await scrape_posts_by_profiles(
        [
            {
                "url": "https://www.instagram.com/zoobarcelona/",
                "num_of_posts": 5,
                "post_type": "Post",
            }
        ]
    )

    print(
        json.dumps(
            posts,
            indent=2,
            ensure_ascii=False,
        )
    )

    print("\nDONE")


if __name__ == "__main__":
    asyncio.run(main())