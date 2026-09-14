"""
Ties together: scrape a product page -> extract structured attributes ->
resolve/create real Brand/Silhouette/Material/Graphic/Color catalog rows ->
persist a real Product row with real pivots.

This is the first real "entity resolution" step in the codebase — brands and
attribute catalog entries were previously only ever seeded by hand, never
created from scraped data.
"""

import json
import logging
import re

from db.client import execute, fetch, fetchrow
from nlp.product_extractor import extract_product_attributes
from scrapers.base import ScrapeTarget, get_scraper

logger = logging.getLogger(__name__)


def _slugify(name: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", name.lower()).strip("-")
    return slug[:250]


_IMAGE_JUNK_PATTERNS = re.compile(
    r"(logo|icon|favicon|sprite|badge|payment|avatar|placeholder|spinner|loading|pixel\.gif)",
    re.IGNORECASE,
)


def _likely_product_images(image_urls: list[str], limit: int = 6) -> list[str]:
    """
    Real image URLs found on the page, minus obvious nav/logo/icon noise.
    No claim is made about which one is "the" hero shot — the UI shows all
    of them and lets the viewer judge, since the LLM here is text-only and
    can't actually look at the images.
    """
    candidates = [u for u in image_urls if not _IMAGE_JUNK_PATTERNS.search(u)]
    return candidates[:limit] if candidates else image_urls[:limit]


async def _resolve_brand(name: str, country_id: int | None, niche_id: int | None) -> int:
    existing = await fetchrow("SELECT id FROM brands WHERE name ILIKE $1", name)
    if existing:
        return existing["id"]

    row = await fetchrow(
        """
        INSERT INTO brands (name, slug, country_id, niche_id, confidence, is_demo, created_at, updated_at)
        VALUES ($1, $2, $3, $4, 60, false, NOW(), NOW())
        ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name
        RETURNING id
        """,
        name, _slugify(name), country_id, niche_id,
    )
    logger.info(f"Created brand: {name} (id={row['id']})")
    return row["id"]


async def _resolve_ref(table: str, name: str, extra_cols: dict | None = None) -> int:
    """Generic lookup-or-create against a reference catalog table that has a
    unique (name, slug) pair — silhouettes, materials, graphics."""
    existing = await fetchrow(f"SELECT id FROM {table} WHERE name ILIKE $1", name)
    if existing:
        return existing["id"]

    cols = ["name", "slug"] + list((extra_cols or {}).keys())
    vals = [name, _slugify(name)] + list((extra_cols or {}).values())
    placeholders = ", ".join(f"${i+1}" for i in range(len(vals)))
    row = await fetchrow(
        f"INSERT INTO {table} ({', '.join(cols)}) VALUES ({placeholders}) "
        f"ON CONFLICT (slug) DO UPDATE SET name = EXCLUDED.name RETURNING id",
        *vals,
    )
    return row["id"]


async def _resolve_color(name: str) -> int:
    """colors has no slug column and no unique constraint on name — plain
    check-then-insert (fine at this pipeline's single-process, low-volume
    scale; a rare race would just create a harmless duplicate color row)."""
    existing = await fetchrow("SELECT id FROM colors WHERE name ILIKE $1", name)
    if existing:
        return existing["id"]

    row = await fetchrow(
        "INSERT INTO colors (name, created_at, updated_at) VALUES ($1, NOW(), NOW()) RETURNING id",
        name,
    )
    return row["id"]


async def analyze_product_url(url: str, country_id: int | None, niche_id: int | None) -> dict | None:
    """
    Scrape one product page, extract real attributes, persist a Product row
    plus resolved Brand/Silhouette/Material/Graphic/Color links.
    Returns the saved product's full breakdown, or None if scraping/
    extraction failed (never returns a fabricated result).
    """
    scraper = get_scraper()
    result = await scraper.scrape(ScrapeTarget(url=url))

    if not result.success:
        logger.warning(f"Product scrape failed for {url}: {result.error}")
        return None

    text = f"{result.title or ''}\n{result.body or ''}"
    attrs = await extract_product_attributes(text)

    if not attrs or not attrs.get("product_name"):
        logger.warning(f"No usable product data extracted from {url}")
        return None

    image_urls = _likely_product_images(result.images or [])

    brand_id = None
    if attrs.get("brand_name"):
        brand_id = await _resolve_brand(attrs["brand_name"], country_id, niche_id)

    product_row = await fetchrow(
        """
        INSERT INTO products
            (brand_id, country_id, name, slug, description, category, sub_category,
             source_url, image_urls, price, currency, availability, metadata, created_at, updated_at)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9::jsonb,$10,$11,$12,$13::jsonb,NOW(),NOW())
        RETURNING id
        """,
        brand_id, country_id, attrs["product_name"], _slugify(attrs["product_name"] + "-" + url[-8:]),
        attrs.get("graphic_description"), attrs.get("category"), None,
        url, json.dumps(image_urls), attrs.get("price"), attrs.get("currency"), attrs.get("availability"),
        json.dumps({
            "silhouette_raw": attrs.get("silhouette"),
            "materials_raw": attrs.get("materials") or [],
            "fabric_weight_stated": attrs.get("fabric_weight_stated"),
            "construction_details": attrs.get("construction_details") or [],
            "graphic_technique": attrs.get("graphic_technique"),
            "print_color_count": attrs.get("print_color_count"),
            "expert_read": attrs.get("expert_read"),
        }),
    )
    product_id = product_row["id"]

    if attrs.get("silhouette"):
        sid = await _resolve_ref("silhouettes", attrs["silhouette"])
        await execute(
            "INSERT INTO product_silhouettes (product_id, silhouette_id, confidence) VALUES ($1,$2,80)",
            product_id, sid,
        )

    for material_name in attrs.get("materials") or []:
        mid = await _resolve_ref("materials", material_name)
        await execute(
            "INSERT INTO product_materials (product_id, material_id, is_primary) VALUES ($1,$2,$3)",
            product_id, mid, material_name == (attrs.get("materials") or [None])[0],
        )

    if attrs.get("graphic_description") or attrs.get("graphic_technique"):
        graphic_name = attrs.get("graphic_technique") or attrs["graphic_description"][:100]
        gid = await _resolve_ref("graphics", graphic_name, {"description": attrs.get("graphic_description")})
        await execute(
            "INSERT INTO product_graphics (product_id, graphic_id, confidence) VALUES ($1,$2,70)",
            product_id, gid,
        )

    for i, color_name in enumerate(attrs.get("colors") or []):
        cid = await _resolve_color(color_name)
        await execute(
            "INSERT INTO product_colors (product_id, color_id, is_dominant) VALUES ($1,$2,$3)",
            product_id, cid, i == 0,
        )

    if attrs.get("price"):
        await execute(
            """
            INSERT INTO price_observations (product_id, source_url, price, currency, country_id, observed_at)
            VALUES ($1,$2,$3,$4,$5,NOW())
            """,
            product_id, url, attrs["price"], attrs.get("currency"), country_id,
        )

    logger.info(f"Analyzed product: {attrs.get('brand_name')} — {attrs['product_name']} (id={product_id})")

    return {
        "product_id": product_id,
        "url": url,
        "image_urls": image_urls,
        **attrs,
    }
