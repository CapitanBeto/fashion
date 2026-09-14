"""
Database client.
Connects to the same PostgreSQL database as Laravel.
Uses asyncpg for async queries; psycopg2 available via SQLAlchemy for sync operations.
"""

import os
from contextlib import asynccontextmanager
from typing import AsyncGenerator

import asyncpg
from dotenv import load_dotenv

load_dotenv()

DATABASE_URL = os.getenv("DATABASE_URL", "postgresql://fi_user:fi_password_2024@localhost:5432/fashion_intelligence")

# Global connection pool — created once at startup
_pool: asyncpg.Pool | None = None


async def get_pool() -> asyncpg.Pool:
    global _pool
    if _pool is None:
        _pool = await asyncpg.create_pool(
            DATABASE_URL,
            min_size=2,
            max_size=10,
            command_timeout=60,
        )
    return _pool


async def close_pool() -> None:
    global _pool
    if _pool:
        await _pool.close()
        _pool = None


@asynccontextmanager
async def get_conn() -> AsyncGenerator[asyncpg.Connection, None]:
    """Context manager for a single database connection from the pool."""
    pool = await get_pool()
    async with pool.acquire() as conn:
        yield conn


async def fetchrow(query: str, *args) -> asyncpg.Record | None:
    async with get_conn() as conn:
        return await conn.fetchrow(query, *args)


async def fetch(query: str, *args) -> list[asyncpg.Record]:
    async with get_conn() as conn:
        return await conn.fetch(query, *args)


async def execute(query: str, *args) -> str:
    async with get_conn() as conn:
        return await conn.execute(query, *args)


async def executemany(query: str, args: list) -> None:
    async with get_conn() as conn:
        await conn.executemany(query, args)


async def ping() -> bool:
    """Check database connectivity."""
    try:
        result = await fetchrow("SELECT 1 AS ok")
        return result["ok"] == 1
    except Exception:
        return False
