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
    database_url = raw_url.strip() if raw_url and raw_url.strip() else None

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
    )
