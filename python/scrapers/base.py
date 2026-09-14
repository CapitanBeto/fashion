"""
Scraping provider abstraction.
ScrapingProviderInterface defines the contract.
LocalScraper is the default FREE implementation using httpx + BeautifulSoup.
"""

import asyncio
import json
import os
import re
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from datetime import datetime
from urllib.parse import urljoin, urlparse
from urllib.robotparser import RobotFileParser

import httpx
from bs4 import BeautifulSoup
from fake_useragent import UserAgent

DELAY_MS    = int(os.getenv("SCRAPE_DEFAULT_DELAY_MS", "2000"))
TIMEOUT_S   = int(os.getenv("SCRAPE_TIMEOUT_SECONDS", "30"))
RESPECT_ROBOTS = os.getenv("SCRAPE_RESPECT_ROBOTS", "true").lower() == "true"

ua = UserAgent()


@dataclass
class ScrapeTarget:
    url: str
    country: str | None = None
    requires_js: bool = False
    complexity: str = "simple"      # simple | medium | complex
    is_high_volume: bool = False
    metadata: dict = field(default_factory=dict)


@dataclass
class ScrapeResult:
    url: str
    canonical_url: str | None
    title: str | None
    body: str | None
    html_raw: str | None
    published_at: datetime | None
    author: str | None
    images: list[str]
    links: list[str]
    metadata: dict
    success: bool
    error: str | None = None
    http_status: int | None = None


class ScrapingProviderInterface(ABC):
    @abstractmethod
    async def scrape(self, target: ScrapeTarget) -> ScrapeResult:
        ...

    @abstractmethod
    async def ping(self) -> bool:
        ...


class LocalScraper(ScrapingProviderInterface):
    """
    Free HTTP scraper using httpx + BeautifulSoup.
    Respects robots.txt, adds delays, rotates user agents.
    """

    def __init__(self):
        self._robots_cache: dict[str, RobotFileParser] = {}

    async def ping(self) -> bool:
        return True

    async def scrape(self, target: ScrapeTarget) -> ScrapeResult:
        # Robots.txt check
        if RESPECT_ROBOTS and not await self._is_allowed(target.url):
            return ScrapeResult(
                url=target.url, canonical_url=None, title=None, body=None,
                html_raw=None, published_at=None, author=None, images=[], links=[],
                metadata={}, success=False, error="Blocked by robots.txt",
            )

        # Polite delay
        await asyncio.sleep(DELAY_MS / 1000)

        headers = {
            "User-Agent": ua.random,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "en-US,en;q=0.5",
            "Accept-Encoding": "gzip, deflate",
            "Connection": "keep-alive",
        }

        try:
            async with httpx.AsyncClient(
                timeout=TIMEOUT_S,
                follow_redirects=True,
                headers=headers,
            ) as client:
                response = await client.get(target.url)
                response.raise_for_status()

            html = response.text
            canonical = str(response.url)
            result = self._parse_html(html, canonical)
            result.http_status = response.status_code
            return result

        except httpx.HTTPStatusError as e:
            return ScrapeResult(
                url=target.url, canonical_url=None, title=None, body=None,
                html_raw=None, published_at=None, author=None, images=[], links=[],
                metadata={}, success=False,
                error=f"HTTP {e.response.status_code}: {str(e)}",
                http_status=e.response.status_code,
            )
        except Exception as e:
            return ScrapeResult(
                url=target.url, canonical_url=None, title=None, body=None,
                html_raw=None, published_at=None, author=None, images=[], links=[],
                metadata={}, success=False, error=str(e),
            )

    def _parse_html(self, html: str, canonical_url: str) -> ScrapeResult:
        soup = BeautifulSoup(html, "lxml")
        base = urlparse(canonical_url)

        # Title
        title = None
        if soup.title:
            title = soup.title.string.strip() if soup.title.string else None
        if not title:
            og = soup.find("meta", property="og:title")
            title = og.get("content", "").strip() if og else None

        # og:image — the standard, reliable "hero image" signal any e-commerce
        # site sets for link previews. Far more reliable than guessing which
        # <img> tag is the product photo vs. nav/logo/icon noise.
        og_image = None
        og_img_tag = soup.find("meta", property="og:image")
        if og_img_tag and og_img_tag.get("content"):
            og_image = urljoin(canonical_url, og_img_tag["content"])

        # Shopify (and most modern storefronts) also embed a JSON-LD Product
        # schema with a full image list — pull it when present for more than
        # just the one hero shot.
        jsonld_images: list[str] = []
        for script in soup.find_all("script", type="application/ld+json"):
            try:
                data = json.loads(script.string or "{}")
                candidates = data if isinstance(data, list) else [data]
                for entry in candidates:
                    if isinstance(entry, dict) and entry.get("@type") == "Product":
                        raw_images = entry.get("image")
                        if isinstance(raw_images, str):
                            jsonld_images.append(raw_images)
                        elif isinstance(raw_images, list):
                            jsonld_images.extend(i for i in raw_images if isinstance(i, str))
            except (json.JSONDecodeError, TypeError, AttributeError):
                continue

        # Published date
        published_at = None
        for selector in [
            {"name": "meta", "property": "article:published_time"},
            {"name": "meta", "itemprop": "datePublished"},
            {"name": "time"},
        ]:
            el = soup.find(**selector)
            if el:
                date_str = el.get("content") or el.get("datetime") or el.string
                if date_str:
                    try:
                        from dateutil import parser as dateparser
                        published_at = dateparser.parse(date_str)
                        break
                    except Exception:
                        pass

        # Author
        author = None
        for sel in [{"name": "meta", "name": "author"}, {"name": "meta", "property": "article:author"}]:
            el = soup.find(**sel)
            if el and el.get("content"):
                author = el["content"].strip()
                break

        # Body text — strip nav, footer, scripts
        for tag in soup(["script", "style", "nav", "footer", "header", "aside", "form"]):
            tag.decompose()
        body = soup.get_text(separator=" ", strip=True)
        body = re.sub(r"\s{2,}", " ", body).strip()

        # Images — reliable sources (JSON-LD product schema, og:image) first,
        # generic <img> tag scan (mostly nav/logo/icon noise on e-commerce
        # sites) last, so callers taking images[:N] get the real photos.
        images = []
        for src in jsonld_images:
            resolved = urljoin(canonical_url, src)
            if resolved not in images:
                images.append(resolved)
        if og_image and og_image not in images:
            images.append(og_image)

        for img in soup.find_all("img", src=True):
            src = img["src"]
            if src.startswith("data:"):
                continue
            resolved = urljoin(canonical_url, src)
            if resolved not in images:
                images.append(resolved)

        # Internal links
        links = []
        for a in soup.find_all("a", href=True):
            href = urljoin(canonical_url, a["href"])
            parsed = urlparse(href)
            if parsed.netloc == base.netloc and parsed.scheme in ("http", "https"):
                links.append(href)

        return ScrapeResult(
            url=canonical_url,
            canonical_url=canonical_url,
            title=title,
            body=body[:50_000],  # cap at 50k chars
            html_raw=html[:200_000],
            published_at=published_at,
            author=author,
            images=images[:50],
            links=list(set(links))[:100],
            metadata={"word_count": len(body.split())},
            success=True,
        )

    async def _is_allowed(self, url: str) -> bool:
        """Check robots.txt for the given URL."""
        parsed = urlparse(url)
        robots_url = f"{parsed.scheme}://{parsed.netloc}/robots.txt"

        if robots_url not in self._robots_cache:
            rp = RobotFileParser()
            rp.set_url(robots_url)
            try:
                async with httpx.AsyncClient(timeout=10) as client:
                    r = await client.get(robots_url)
                    rp.parse(r.text.splitlines())
            except Exception:
                # If robots.txt is unreachable, assume allowed
                rp.parse([])
            self._robots_cache[robots_url] = rp

        return self._robots_cache[robots_url].can_fetch("*", url)


def get_scraper(budget_remaining: float = 0.0, target: ScrapeTarget | None = None) -> ScrapingProviderInterface:
    """
    Provider router — returns the best scraper given budget and target complexity.
    In FREE mode, always returns LocalScraper.
    """
    mode = os.getenv("SCRAPE_MODE", "FREE")

    if mode == "FREE" or budget_remaining <= 0:
        return LocalScraper()

    if target and target.requires_js:
        # Could return ZyteProvider() or PlaywrightScraper() here
        return LocalScraper()

    return LocalScraper()
