"""FastAPI application for the medical expert system.

Endpoints
---------
* ``GET /``         - service metadata (useful for humans and smoke tests).
* ``GET /health``   - lightweight liveness check, never touches the database.
* ``GET /ready``    - readiness check that verifies database connectivity.
* ``POST /evaluate``- deterministic rule evaluation (internal, authenticated).

Security posture
----------------
The evaluation endpoint is intended to be reached only by the Laravel backend
over a private Docker network. It is bound to localhost in development and is
never published publicly in production. When ``EXPERT_SYSTEM_API_KEY`` is set,
callers must present a matching ``X-Internal-Api-Key`` header (defence in
depth). Permissive CORS is intentionally not enabled.
"""

from __future__ import annotations

import logging
import time
from typing import Annotated, Iterator

from fastapi import Depends, FastAPI, Header, HTTPException, Request, status
from fastapi.responses import JSONResponse
from sqlalchemy import bindparam, text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.config import SERVICE_NAME, SERVICE_VERSION, get_settings
from app.database import get_db
from app.engine import RuleEngineError, evaluate_rules
from app.logging_config import configure_logging
from app.schemas import (
    EvaluateRequest,
    EvaluateResult,
    HealthResponse,
    ReadinessResponse,
)

configure_logging()
logger = logging.getLogger("app.main")

app = FastAPI(
    title="Clinic Medical Expert System",
    version=SERVICE_VERSION,
    description=(
        "Deterministic rule-matching service for clinical decision support "
        "prototyping. Not a medical device and not clinically validated."
    ),
)


@app.middleware("http")
async def log_requests(request: Request, call_next):
    """Log request metadata without recording request bodies or symptom IDs."""

    started = time.perf_counter()
    try:
        response = await call_next(request)
    except Exception:  # pragma: no cover - safety net around logging
        duration_ms = (time.perf_counter() - started) * 1000
        logger.exception(
            "Unhandled error processing %s %s after %.1fms",
            request.method,
            request.url.path,
            duration_ms,
        )
        raise
    duration_ms = (time.perf_counter() - started) * 1000
    logger.info(
        "%s %s -> %s (%.1fms)",
        request.method,
        request.url.path,
        response.status_code,
        duration_ms,
    )
    return response


def require_api_key(
    x_internal_api_key: Annotated[str | None, Header()] = None,
) -> None:
    """Enforce the optional internal shared secret when configured."""

    settings = get_settings()
    if settings.api_key_required and x_internal_api_key != settings.api_key:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing or invalid internal API key.",
        )


@app.exception_handler(SQLAlchemyError)
async def handle_database_error(request: Request, exc: SQLAlchemyError) -> JSONResponse:
    """Never leak SQL, credentials, or stack traces on database failures."""

    logger.error("Database error on %s: %s", request.url.path, type(exc).__name__)
    return JSONResponse(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        content={"detail": "Database temporarily unavailable."},
    )


@app.exception_handler(RuleEngineError)
async def handle_engine_error(request: Request, exc: RuleEngineError) -> JSONResponse:
    logger.error("Rule engine error on %s: %s", request.url.path, type(exc).__name__)
    return JSONResponse(
        status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
        content={"detail": "Expert system temporarily unavailable."},
    )


@app.get("/", tags=["meta"])
def root() -> dict:
    """Service metadata. No dependencies, safe to call at any time."""

    return {
        "service": SERVICE_NAME,
        "version": SERVICE_VERSION,
        "documentation": "/docs",
        "endpoints": {
            "health": "/health",
            "ready": "/ready",
            "evaluate": "/evaluate",
        },
    }


@app.get("/health", response_model=HealthResponse, tags=["meta"])
def health_check() -> HealthResponse:
    """Lightweight liveness probe. Does not touch the database."""

    return HealthResponse(status="ok", service=SERVICE_NAME, version=SERVICE_VERSION)


@app.get(
    "/ready",
    response_model=ReadinessResponse,
    tags=["meta"],
    responses={503: {"description": "Database is not ready"}},
)
def readiness_check(db: Annotated[Session, Depends(get_db)]) -> ReadinessResponse:
    """Readiness probe verifying PostgreSQL connectivity."""

    try:
        db.execute(text("SELECT 1"))
    except SQLAlchemyError as exc:
        logger.error("Readiness database check failed: %s", type(exc).__name__)
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Database is not ready.",
        ) from exc

    return ReadinessResponse(
        status="ready",
        service=SERVICE_NAME,
        version=SERVICE_VERSION,
        database="ok",
    )


@app.post(
    "/evaluate",
    response_model=EvaluateResult,
    tags=["inference"],
    dependencies=[Depends(require_api_key)],
    responses={
        401: {"description": "Missing or invalid internal API key"},
        422: {"description": "Invalid or inactive symptom identifiers"},
        503: {"description": "Database or engine unavailable"},
    },
)
def evaluate(
    payload: EvaluateRequest,
    db: Annotated[Session, Depends(get_db)],
) -> EvaluateResult:
    """Evaluate active rules against the submitted symptom identifiers.

    A valid request that matches no rules returns HTTP 200 with an empty
    ``matched_rules`` list. Invalid or inactive symptom identifiers return
    HTTP 422.
    """

    unique_ids = set(payload.symptom_ids)

    statement = text(
        """
        SELECT id
        FROM symptoms
        WHERE is_active = TRUE
          AND id IN :ids
        """
    ).bindparams(bindparam("ids", expanding=True))

    active_ids = set(db.execute(statement, {"ids": sorted(unique_ids)}).scalars().all())

    invalid_ids = sorted(unique_ids - active_ids)
    if invalid_ids:
        raise HTTPException(
            status_code=422,
            detail={
                "message": "One or more symptoms are invalid or inactive.",
                "invalid_symptom_ids": invalid_ids,
            },
        )

    return evaluate_rules(db, unique_ids)
