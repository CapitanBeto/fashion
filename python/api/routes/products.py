"""
Product deep-dive endpoint — scrape one real product page and extract real,
sourced attributes (never fabricated) into the products/brands/attribute
catalog tables.
"""

import logging

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from nlp.product_pipeline import analyze_product_url

router = APIRouter()
logger = logging.getLogger(__name__)


class ProductAnalyzeRequest(BaseModel):
    url: str
    country_id: int | None = None
    niche_id: int | None = None


@router.post("/products/analyze")
async def analyze_product(body: ProductAnalyzeRequest):
    result = await analyze_product_url(body.url, body.country_id, body.niche_id)
    if not result:
        raise HTTPException(status_code=422, detail="Could not scrape or extract usable product data from this URL")
    return result
