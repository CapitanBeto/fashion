"""
LLM client — unified adapter for Ollama, OpenAI, and Anthropic.
Reads LLM_PROVIDER from environment to select the backend.
Always returns structured JSON when json_mode=True.
"""

import json
import os
import re
from typing import Any

import httpx
from dotenv import load_dotenv
from tenacity import retry, stop_after_attempt, wait_exponential

load_dotenv()

PROVIDER    = os.getenv("LLM_PROVIDER", "ollama")
MODEL       = os.getenv("LLM_MODEL", "qwen3:8b")
BASE_URL    = os.getenv("LLM_BASE_URL", "http://localhost:11434")
MAX_TOKENS  = int(os.getenv("LLM_MAX_TOKENS", "2048"))
TEMPERATURE = float(os.getenv("LLM_TEMPERATURE", "0.1"))


def _extract_json(text: str) -> dict:
    """Extract the first valid JSON object from a text that may have markdown."""
    # Remove ```json ... ``` blocks
    text = re.sub(r"```(?:json)?\s*", "", text).strip().rstrip("`").strip()
    # Find the first { ... }
    match = re.search(r"\{.*\}", text, re.DOTALL)
    if match:
        return json.loads(match.group())
    raise ValueError(f"No JSON found in LLM response: {text[:200]}")


@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=2, max=10))
async def complete(
    system: str,
    user: str,
    json_mode: bool = False,
) -> str | dict:
    """
    Send a completion request to the configured LLM.
    If json_mode=True, parses and returns a dict.
    """
    if PROVIDER == "ollama":
        return await _ollama(system, user, json_mode)
    elif PROVIDER == "openai":
        return await _openai(system, user, json_mode)
    elif PROVIDER == "anthropic":
        return await _anthropic(system, user, json_mode)
    else:
        raise ValueError(f"Unknown LLM provider: {PROVIDER}")


async def _ollama(system: str, user: str, json_mode: bool) -> str | dict:
    payload: dict[str, Any] = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system},
            {"role": "user",   "content": user},
        ],
        "stream": False,
        "options": {
            "temperature": TEMPERATURE,
            "num_predict": MAX_TOKENS,
        },
    }
    if json_mode:
        payload["format"] = "json"

    async with httpx.AsyncClient(timeout=120) as client:
        r = await client.post(f"{BASE_URL}/api/chat", json=payload)
        r.raise_for_status()
        content = r.json()["message"]["content"]

    return _extract_json(content) if json_mode else content


async def _openai(system: str, user: str, json_mode: bool) -> str | dict:
    api_key = os.getenv("OPENAI_API_KEY", "")
    payload: dict[str, Any] = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system},
            {"role": "user",   "content": user},
        ],
        "max_tokens": MAX_TOKENS,
        "temperature": TEMPERATURE,
    }
    if json_mode:
        payload["response_format"] = {"type": "json_object"}

    headers = {"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"}
    async with httpx.AsyncClient(timeout=120) as client:
        r = await client.post(f"{BASE_URL}/chat/completions", json=payload, headers=headers)
        r.raise_for_status()
        content = r.json()["choices"][0]["message"]["content"]

    return _extract_json(content) if json_mode else content


async def _anthropic(system: str, user: str, json_mode: bool) -> str | dict:
    api_key = os.getenv("ANTHROPIC_API_KEY", "")
    if json_mode:
        user = user + "\n\nRespond with valid JSON only. No markdown, no explanation."

    payload = {
        "model": MODEL,
        "max_tokens": MAX_TOKENS,
        "system": system,
        "messages": [{"role": "user", "content": user}],
    }
    headers = {
        "x-api-key": api_key,
        "anthropic-version": "2023-06-01",
        "Content-Type": "application/json",
    }
    async with httpx.AsyncClient(timeout=120) as client:
        r = await client.post("https://api.anthropic.com/v1/messages", json=payload, headers=headers)
        r.raise_for_status()
        content = r.json()["content"][0]["text"]

    return _extract_json(content) if json_mode else content


async def ping() -> bool:
    """Check if the LLM is reachable."""
    try:
        result = await complete("You are a test.", "Reply with the single word: ok", json_mode=False)
        return "ok" in str(result).lower()
    except Exception:
        return False
