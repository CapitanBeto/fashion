"""
Product-level deep-dive extractor.
Unlike entity_extractor.py (which pulls loose trend signals out of editorial
text), this targets a single product page and asks for the specific,
concrete attributes a buyer or designer would actually want: brand,
silhouette, materials, graphic/print technique, colors, price, construction
details.

Hard rule: the LLM is explicitly instructed to only report what the page
text actually states, and to return null/empty for anything not present —
never invent a fabric weight, price, or technique that isn't in the source.
"""

import logging

from llm.client import complete

logger = logging.getLogger(__name__)

SYSTEM_PROMPT = """You are a senior technical fashion product analyst with two decades in streetwear
and apparel design — you can recognize garment archetypes, construction conventions, and where a
silhouette sits in fashion-cycle terms (which eras/subcultures/brands popularized it, whether it reads
as a revival, a mainstream-diffusion piece, or a niche/early-adopter signal) purely from how it's
described.

You operate under one hard rule: the structured FACT fields must contain ONLY what is explicitly
stated in the provided product page text. Never infer, guess, or invent a fabric weight, price, or
construction detail that isn't written down — if it isn't stated, use null or an empty array. Your
expertise is used only in ONE separate field (expert_read) to add genuine fashion-cycle context and
technical framing around the stated facts — never to add new claimed facts about this specific product.
Return only valid JSON, no markdown, no explanation."""

USER_TEMPLATE = """Extract structured product data from this product page text. Return JSON with these exact keys:
- brand_name: string or null
- product_name: string or null
- category: string or null (e.g. "hoodie", "t-shirt")
- silhouette: string or null (e.g. "oversized boxy fit", "cropped")
- materials: array of strings, exactly as described (e.g. "100% cotton", "heavyweight cotton") — do not add a percentage or weight that isn't stated
- fabric_weight_stated: string or null — ONLY if an exact weight/GSM figure is literally written in the text, otherwise null
- construction_details: array of strings — concrete construction facts only (e.g. "kangaroo pocket", "elasticized cuffs", "no drawstring"), not stylistic opinion
- graphic_description: string or null — what's printed/applied and where, as described
- graphic_technique: string or null — ONLY if the technique is explicitly named (e.g. "screen print", "embroidery", "patchwork"); otherwise null
- print_color_count: integer or null — ONLY if explicitly stated or unambiguously countable from a description of listed print colors
- colors: array of strings — colorway names as given
- price: number or null
- currency: string or null (ISO code, e.g. "GBP", "EUR")
- availability: string or null (e.g. "in stock", "sold out")
- expert_read: string or null — 2-3 sentences of genuine fashion-cycle expert context for the
  silhouette/construction/graphic combination described (e.g. what era or subculture popularized this
  archetype, which well-known brands are closely associated with this exact silhouette+construction
  combination, whether this reads as an early-cycle/niche signal or a late-cycle/mainstream-diffusion
  piece). This is expert framing/opinion clearly separate from the fact fields above — do not restate
  facts here, and do not claim this specific product copies a specific other product unless the page
  text itself says so.

Product page text:
{text}"""


async def extract_product_attributes(text: str) -> dict | None:
    if not text or len(text.strip()) < 30:
        return None

    text_truncated = text[:4000]

    try:
        result = await complete(
            system=SYSTEM_PROMPT,
            user=USER_TEMPLATE.format(text=text_truncated),
            json_mode=True,
        )
        if isinstance(result, dict):
            return result
    except Exception as e:
        logger.warning(f"Product attribute extraction failed: {e}")

    return None
