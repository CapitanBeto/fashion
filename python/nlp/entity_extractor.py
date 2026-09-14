"""
Fashion entity extractor.
Uses spaCy for fast NER + LLM for fashion-specific entities
(brands, products, colors, silhouettes, trends).
"""

import json
import logging
import re
from functools import lru_cache

import spacy

from db.client import fetch, fetchrow
from llm.client import complete

logger = logging.getLogger(__name__)

# Load spaCy model once
@lru_cache(maxsize=1)
def _get_nlp():
    try:
        return spacy.load("en_core_web_sm")
    except OSError:
        logger.warning("spaCy model 'en_core_web_sm' not found. Run: python -m spacy download en_core_web_sm")
        return None


SYSTEM_PROMPT = """You are a fashion intelligence analyst with deep, practitioner-level knowledge of
the fashion cycle — how a look moves from a subculture or niche design community through early
adopters, creator/influencer amplification, brand adoption, mainstream diffusion, saturation, and
eventual decline or revival (trickle-up/bubble-up vs. trickle-down diffusion, seasonal cycles, the
MAYA principle of what reads as "advanced yet acceptable" at a given moment). Use that expertise to
recognize which extracted concepts are genuinely trend-relevant signals versus incidental mentions,
but extract only entities and concepts that are actually present in the text — never invent a brand,
product, or trend that isn't there. Return only valid JSON, no markdown, no explanation."""

USER_TEMPLATE = """Extract fashion entities from this text. Return JSON with these exact keys:
- brands: array of brand/label names mentioned
- products: array of specific product descriptions
- colors: array of colors mentioned
- silhouettes: array of garment shapes, cuts, or styles
- trends: array of trend names or fashion concepts — prefer the specific diffusion-stage-aware
  framing where the text supports it (e.g. "underground revival of X" vs. "mainstream saturation of X")
  over a generic label, but only when the text itself supports that framing
- creators: array of influencer/creator/designer names
- sentiment: float from -1.0 (very negative) to 1.0 (very positive)

Text: {text}"""


async def extract_entities(text: str) -> dict:
    """
    Extract fashion entities from text.
    Returns dict with brands, products, colors, silhouettes, trends, creators, sentiment.
    """
    if not text or len(text.strip()) < 20:
        return _empty_result()

    # Truncate very long texts to fit context window
    text_truncated = text[:3000]

    try:
        result = await complete(
            system=SYSTEM_PROMPT,
            user=USER_TEMPLATE.format(text=text_truncated),
            json_mode=True,
        )

        if isinstance(result, dict):
            return _normalize_result(result)

    except Exception as e:
        logger.warning(f"LLM entity extraction failed: {e}. Falling back to spaCy.")

    # Fallback: spaCy NER for organizations/persons only
    return _spacy_fallback(text_truncated)


def _normalize_result(raw: dict) -> dict:
    """Ensure all expected keys exist and are the right types."""
    def to_str_list(v):
        if isinstance(v, list):
            return [str(x).strip() for x in v if x and str(x).strip()]
        return []

    return {
        "brands":      to_str_list(raw.get("brands", [])),
        "products":    to_str_list(raw.get("products", [])),
        "colors":      to_str_list(raw.get("colors", [])),
        "silhouettes": to_str_list(raw.get("silhouettes", [])),
        "trends":      to_str_list(raw.get("trends", [])),
        "creators":    to_str_list(raw.get("creators", [])),
        "sentiment":   float(raw.get("sentiment", 0.0)),
    }


def _spacy_fallback(text: str) -> dict:
    """Use spaCy NER as fallback when LLM is unavailable."""
    nlp = _get_nlp()
    if nlp is None:
        return _empty_result()

    doc = nlp(text)
    brands   = [e.text for e in doc.ents if e.label_ == "ORG"]
    creators = [e.text for e in doc.ents if e.label_ == "PERSON"]

    return {
        "brands":      brands[:10],
        "products":    [],
        "colors":      _extract_colors_regex(text),
        "silhouettes": [],
        "trends":      [],
        "creators":    creators[:10],
        "sentiment":   0.0,
    }


def _extract_colors_regex(text: str) -> list[str]:
    """Simple regex-based color extraction as last resort."""
    color_words = [
        "black", "white", "gray", "grey", "navy", "blue", "red", "green",
        "yellow", "orange", "purple", "pink", "brown", "beige", "cream",
        "olive", "khaki", "burgundy", "camel", "tan", "rust", "sage",
        "washed", "faded", "bleached", "acid wash", "tie-dye",
    ]
    found = []
    text_lower = text.lower()
    for color in color_words:
        if re.search(r"\b" + color + r"\b", text_lower):
            found.append(color)
    return found


def _empty_result() -> dict:
    return {
        "brands": [], "products": [], "colors": [],
        "silhouettes": [], "trends": [], "creators": [],
        "sentiment": 0.0,
    }


async def match_and_save_trend_mentions(
    document_id: int,
    entities: dict,
    trend_keywords: list[tuple[int, list[str]]],  # [(trend_id, [keywords])]
    source_id: int | None,
    country_id: int | None,
    published_at,
) -> int:
    """
    Match extracted entities against trend keywords.
    Save matches to trend_mentions table.
    Returns number of mentions created.
    """
    text_signals = (
        entities["brands"] +
        entities["products"] +
        entities["trends"] +
        entities["silhouettes"]
    )
    text_lower = " ".join(text_signals).lower()

    mentions_created = 0

    for trend_id, keywords in trend_keywords:
        for kw in keywords:
            if kw.lower() in text_lower:
                await _save_mention(
                    trend_id=trend_id,
                    document_id=document_id,
                    source_id=source_id,
                    country_id=country_id,
                    signal_type="community",
                    sentiment=entities.get("sentiment", 0.0),
                    published_at=published_at,
                )
                mentions_created += 1
                break  # one mention per trend per document

    return mentions_created


async def _save_mention(
    trend_id: int,
    document_id: int,
    source_id: int | None,
    country_id: int | None,
    signal_type: str,
    sentiment: float,
    published_at,
) -> None:
    from db.client import execute
    await execute(
        """
        INSERT INTO trend_mentions
            (trend_id, raw_document_id, source_id, country_id,
             signal_type, sentiment, confidence, mentioned_at, created_at)
        VALUES ($1, $2, $3, $4, $5, $6, 0.7, $7, NOW())
        ON CONFLICT DO NOTHING
        """,
        trend_id,
        document_id,
        source_id,
        country_id,
        signal_type,
        sentiment,
        published_at,
    )
