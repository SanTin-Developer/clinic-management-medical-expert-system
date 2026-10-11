"""SQLAlchemy engine and session management.

The engine is created lazily so that importing the application does not
require a reachable database or a configured ``DATABASE_URL``. This keeps the
service importable for tooling and tests while still failing fast with a clear
message the first time a query is attempted without configuration.
"""

from __future__ import annotations

import logging
from functools import lru_cache
from typing import Iterator

from sqlalchemy import create_engine
from sqlalchemy.engine import Engine
from sqlalchemy.orm import Session, sessionmaker

from app.config import get_settings

logger = logging.getLogger(__name__)


class DatabaseConfigurationError(RuntimeError):
    """Raised when a query is attempted without a configured ``DATABASE_URL``.

    Subclasses :class:`RuntimeError` for backwards compatibility while letting
    the application translate the condition into a clean ``503`` response
    instead of an unhandled ``500``.
    """


def build_engine(database_url: str) -> Engine:
    """Create an :class:`Engine` for ``database_url``.

    PostgreSQL receives production-grade pooling and libpq-level timeouts.
    SQLite is supported so tests can run against an in-memory database.
    """

    if database_url.startswith("sqlite"):
        connect_args: dict[str, object] = {"check_same_thread": False}
        kwargs: dict[str, object] = {"connect_args": connect_args}
        if database_url in {"sqlite://", "sqlite:///:memory:"} or ":memory:" in database_url:
            from sqlalchemy.pool import StaticPool

            kwargs["poolclass"] = StaticPool
        return create_engine(database_url, **kwargs)

    settings = get_settings()

    logger.info("Configuring PostgreSQL engine with statement timeout of %sms", settings.db_statement_timeout_ms)

    return create_engine(
        database_url,
        pool_pre_ping=True,
        pool_size=settings.db_pool_size,
        max_overflow=settings.db_max_overflow,
        pool_recycle=settings.db_pool_recycle_seconds,
        connect_args={
            "connect_timeout": settings.db_connect_timeout_seconds,
            "application_name": settings.app_name,
            "options": f"-c statement_timeout={settings.db_statement_timeout_ms}",
        },
    )


@lru_cache
def get_engine() -> Engine:
    """Return the process-wide engine, building it on first use."""

    settings = get_settings()
    if not settings.database_url:
        raise DatabaseConfigurationError(
            "DATABASE_URL is not configured; the expert system cannot reach its database."
        )
    return build_engine(settings.database_url)


@lru_cache
def _session_factory() -> sessionmaker[Session]:
    return sessionmaker(
        bind=get_engine(),
        autoflush=False,
        autocommit=False,
        expire_on_commit=False,
    )


def get_db() -> Iterator[Session]:
    """FastAPI dependency yielding a database session per request."""

    session = _session_factory()()
    try:
        yield session
    finally:
        session.close()
