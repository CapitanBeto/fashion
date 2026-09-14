import asyncio
import json

from scrapers.fashionapi import discover_posts_by_profiles


async def main():

    result = await discover_posts_by_profiles([
        "https://www.instagram.com/francis_coffin/",
        "https://www.instagram.com/zoobarcelona/",
    ])

    print(
        json.dumps(
            result,
            indent=2,
            ensure_ascii=False
        )
    )


if __name__ == "__main__":
    asyncio.run(main())