"""Structured logging configuration.

Logs are emitted as single-line JSON to stdout so a container log collector
can parse them. Request bodies and symptom selections are never logged:
only high-level request metadata (method, path, status, duration) is recorded,
which avoids writing patient-identifiable information to logs.
"""

from __future__ import annotations

import json
import logging
import sys
from typing import Any

from app.config import get_settings


class JsonFormatter(logging.Formatter):
    """Minimal JSON formatter for structured, parseable log lines."""

    def format(self, record: logging.LogRecord) -> str:
        payload: dict[str, Any] = {
            "timestamp": self.formatTime(record, "%Y-%m-%dT%H:%M:%S%z"),
            "level": record.levelname,
            "logger": record.name,
            "message": record.getMessage(),
        }
        if record.exc_info:
            payload["exception"] = self.formatException(record.exc_info)
        return json.dumps(payload, default=str)


def configure_logging() -> None:
    """Configure the root logger once, replacing default handlers."""

    settings = get_settings()
    handler = logging.StreamHandler(sys.stdout)
    handler.setFormatter(JsonFormatter())

    root = logging.getLogger()
    root.handlers.clear()
    root.addHandler(handler)
    root.setLevel(settings.log_level)

    # Uvicorn installs its own handlers; route them through the same formatter.
    for name in ("uvicorn", "uvicorn.error", "uvicorn.access"):
        uvicorn_logger = logging.getLogger(name)
        uvicorn_logger.handlers.clear()
        uvicorn_logger.propagate = True
