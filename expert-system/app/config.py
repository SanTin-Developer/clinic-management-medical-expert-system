"""Runtime configuration for the medical expert system service.

All configuration is read from environment variables so that the same image
can be promoted across environments without code changes. No production
credentials are ever hardcoded here.
"""

from __future__ import annotations

import os
from dataclasses import dataclass
from functools import lru_cache
from typing import Optional

SERVICE_NAME = "expert-system"
SERVICE_VERSION = "1.0.0"

DEFAULT_DB_POOL_SIZE = 5
DEFAULT_DB_MAX_OVERFLOW = 5
DEFAULT_DB_POOL_RECYCLE_SECONDS = 1800
DEFAULT_DB_CONNECT_TIMEOUT_SECONDS = 5
DEFAULT_DB_STATEMENT_TIMEOUT_MS = 5000
DEFAULT_LOG_LEVEL = "INFO"
DEFAULT_RULES_CACHE_TTL_SECONDS = 60
DEFAULT_REQUEST_TIMEOUT_SECONDS = 15.0
DEFAULT_AUDIT_ENABLED = False


def _int_from_env(name: str, default: int) -> int:
    """Read a positive integer from the environment, ignoring invalid values."""
    raw = os.getenv(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        value = int(raw)
    except ValueError:
        return default
    return value if value > 0 else default


def _non_negative_int_from_env(name: str, default: int) -> int:
    """Read a non-negative integer (0 allowed, used to disable a feature)."""
    raw = os.getenv(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        value = int(raw)
    except ValueError:
        return default
    return value if value >= 0 else default


def _float_from_env(name: str, default: float) -> float:
    """Read a positive float from the environment, ignoring invalid values."""
    raw = os.getenv(name)
    if raw is None or raw.strip() == "":
        return default
    try:
        value = float(raw)
    except ValueError:
        return default
    return value if value > 0 else default


def _bool_from_env(name: str, default: bool) -> bool:
    """Read a boolean from common truthy/falsey string forms."""
    raw = os.getenv(name)
    if raw is None or raw.strip() == "":
        return default
    return raw.strip().lower() in {"1", "true", "yes", "y", "on"}


def _normalise_database_url(url: str) -> str:
    """Point a libpq-style PostgreSQL URL at the installed psycopg 3 driver.

    Managed platforms (Render, Heroku, ...) expose ``postgresql://...`` (or the
    legacy ``postgres://...``). SQLAlchemy maps a bare ``postgresql`` scheme to
    psycopg2, which this image does not ship, so rewrite it explicitly.

    When ``DB_SSLMODE`` is set (Supabase requires ``require``), append it to the
    URL if the caller did not already specify a value. This keeps the expert
    service encrypted even when the shared ``DATABASE_URL`` omits the query
    parameter.
    """
    if url.startswith("postgresql+psycopg://"):
        normalised = url
    elif url.startswith("postgresql://"):
        normalised = "postgresql+psycopg://" + url[len("postgresql://"):]
    elif url.startswith("postgres://"):
        normalised = "postgresql+psycopg://" + url[len("postgres://"):]
    else:
        normalised = url

    sslmode = (os.getenv("DB_SSLMODE") or "").strip()
    if sslmode and "sslmode=" not in normalised:
        separator = "&" if "?" in normalised else "?"
        normalised = f"{normalised}{separator}sslmode={sslmode}"

    return normalised


@dataclass(frozen=True)
class Settings:
    """Immutable, validated view of the process environment."""

    database_url: Optional[str]
    db_pool_size: int
    db_max_overflow: int
    db_pool_recycle_seconds: int
    db_connect_timeout_seconds: int
    db_statement_timeout_ms: int
    api_key: Optional[str]
    log_level: str
    app_name: str
    app_version: str
    rules_cache_ttl_seconds: int
    request_timeout_seconds: float
    audit_enabled: bool

    @property
    def api_key_required(self) -> bool:
        """True when an internal shared secret is configured."""
        return bool(self.api_key)


@lru_cache
def get_settings() -> Settings:
    """Return the cached process settings.

    ``DATABASE_URL`` is intentionally not required at import time; it is only
    enforced when the database engine is actually built (see ``database.py``).
    """

    raw_url = os.getenv("DATABASE_URL")
    database_url = (
        _normalise_database_url(raw_url.strip())
        if raw_url and raw_url.strip()
        else None
    )

    raw_key = os.getenv("EXPERT_SYSTEM_API_KEY")
    api_key = raw_key.strip() if raw_key and raw_key.strip() else None

    return Settings(
        database_url=database_url,
        db_pool_size=_int_from_env("DB_POOL_SIZE", DEFAULT_DB_POOL_SIZE),
        db_max_overflow=_int_from_env("DB_MAX_OVERFLOW", DEFAULT_DB_MAX_OVERFLOW),
        db_pool_recycle_seconds=_int_from_env(
            "DB_POOL_RECYCLE_SECONDS", DEFAULT_DB_POOL_RECYCLE_SECONDS
        ),
        db_connect_timeout_seconds=_int_from_env(
            "DB_CONNECT_TIMEOUT_SECONDS", DEFAULT_DB_CONNECT_TIMEOUT_SECONDS
        ),
        db_statement_timeout_ms=_int_from_env(
            "DB_STATEMENT_TIMEOUT_MS", DEFAULT_DB_STATEMENT_TIMEOUT_MS
        ),
        api_key=api_key,
        log_level=(os.getenv("LOG_LEVEL") or DEFAULT_LOG_LEVEL).upper(),
        app_name=os.getenv("APP_NAME") or SERVICE_NAME,
        app_version=os.getenv("APP_VERSION") or SERVICE_VERSION,
        rules_cache_ttl_seconds=_non_negative_int_from_env(
            "EXPERT_RULES_CACHE_TTL_SECONDS", DEFAULT_RULES_CACHE_TTL_SECONDS
        ),
        request_timeout_seconds=_float_from_env(
            "EXPERT_SYSTEM_REQUEST_TIMEOUT_SECONDS", DEFAULT_REQUEST_TIMEOUT_SECONDS
        ),
        audit_enabled=_bool_from_env("EXPERT_AUDIT_ENABLED", DEFAULT_AUDIT_ENABLED),
    )
