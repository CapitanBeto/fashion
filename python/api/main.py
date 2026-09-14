"""
Fashion Intelligence — Python Data Engine
FastAPI application entry point.
"""

import logging
import os
import time

from fastapi import FastAPI, HTTPException, Request
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse

from db.client import close_pool, get_pool, ping as db_ping
from llm.client import ping as llm_ping
from api.routes import experiments, products, trends

# ── Logging ───────────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)

ENGINE_SECRET = os.getenv("ENGINE_SECRET", "")

# ── App ───────────────────────────────────────────────────────────────────────
app = FastAPI(
    title="Fashion Intelligence — Data Engine",
    description="Scraping, NLP, scoring, and LLM inference for Fashion Intelligence.",
    version="1.0.0",
    docs_url="/docs",   # Swagger UI at http://localhost:8001/docs
    redoc_url="/redoc",
)

# CORS — only allow localhost in development
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://127.0.0.1:8000"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


# ── Security middleware ───────────────────────────────────────────────────────
@app.middleware("http")
async def verify_engine_secret(request: Request, call_next):
    """
    Verify the shared secret sent by Laravel.
    Skip verification for /health and /docs endpoints.
    """
    open_paths = {"/health", "/docs", "/redoc", "/openapi.json"}

    if request.url.path not in open_paths:
        secret = request.headers.get("X-Engine-Secret", "")
        if ENGINE_SECRET and secret != ENGINE_SECRET:
            return JSONResponse(
                status_code=401,
                content={"detail": "Invalid engine secret"},
            )

    start = time.time()
    response = await call_next(request)
    duration = time.time() - start

    logger.info(f"{request.method} {request.url.path} → {response.status_code} ({duration:.2f}s)")
    return response


# ── Startup / Shutdown ────────────────────────────────────────────────────────
@app.on_event("startup")
async def startup():
    logger.info("Starting Fashion Intelligence Data Engine...")
    pool = await get_pool()
    logger.info(f"Database pool created (size: {pool.get_size()})")

    llm_ok = await llm_ping()
    logger.info(f"LLM ({os.getenv('LLM_PROVIDER', 'ollama')}/{os.getenv('LLM_MODEL', 'qwen3:8b')}): {'OK' if llm_ok else 'NOT AVAILABLE'}")

    if not llm_ok:
        logger.warning(
            "LLM not available. Entity extraction will use spaCy fallback. "
            "Start Ollama with: ollama serve"
        )


@app.on_event("shutdown")
async def shutdown():
    await close_pool()
    logger.info("Database pool closed.")


# ── Routes ────────────────────────────────────────────────────────────────────
app.include_router(experiments.router, tags=["Experiments"])
app.include_router(trends.router, tags=["Trends"])
app.include_router(products.router, tags=["Products"])


_llm_health_cache: dict = {"ok": False, "checked_at": 0.0}
_LLM_HEALTH_TTL_SECONDS = 30


async def _cached_llm_ping() -> bool:
    """
    llm_ping() sends a real chat completion through the LLM (multi-second
    round trip on CPU inference), but /health is polled every 30s by the
    dashboard and hit as a pre-flight check before every artisan command.
    Without caching, that pre-flight check (5-10s timeout) could lose the
    race against a health check that's still waiting on the LLM — this was
    reproduced directly (ping() timing out while the engine was healthy,
    just slow). Cache the result for a short TTL instead of re-querying the
    LLM on every single call.
    """
    now = time.time()
    if now - _llm_health_cache["checked_at"] < _LLM_HEALTH_TTL_SECONDS:
        return _llm_health_cache["ok"]

    ok = await llm_ping()
    _llm_health_cache["ok"] = ok
    _llm_health_cache["checked_at"] = now
    return ok


@app.get("/health", tags=["System"])
async def health():
    """Health check — called by Laravel every 30 seconds."""
    db_ok  = await db_ping()
    llm_ok = await _cached_llm_ping()

    status = "ok" if db_ok else "degraded"

    return {
        "status":   status,
        "database": "connected" if db_ok else "error",
        "llm":      f"{os.getenv('LLM_PROVIDER', 'ollama')}/{os.getenv('LLM_MODEL', 'qwen3:8b')}",
        "llm_ok":   llm_ok,
        "scrape_mode": os.getenv("SCRAPE_MODE", "FREE"),
    }
