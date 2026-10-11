"""Opt-in evaluation audit trail.

When ``EXPERT_AUDIT_ENABLED`` is true, every evaluation is persisted to the
``expert_evaluation_audits`` table so that rule usage can be reviewed later.

Privacy note: an audit row stores the submitted symptom identifiers, which are
clinical selections. Auditing is therefore **disabled by default** and must be
enabled deliberately in line with the applicable privacy policy. Audit writes
are best-effort: a failure to record never fails the evaluation request.
"""

from __future__ import annotations

import json
import logging
from datetime import datetime, timezone
from typing import Iterable, List

from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.config import get_settings
from app.schemas import EvaluateResult

logger = logging.getLogger(__name__)

_INSERT_AUDIT = text(
    """
    INSERT INTO expert_evaluation_audits (
        symptom_ids,
        matched_rule_count,
        matched_rule_codes,
        engine_version,
        is_clinically_validated,
        created_at,
        updated_at
    )
    VALUES (
        :symptom_ids,
        :matched_rule_count,
        :matched_rule_codes,
        :engine_version,
        :is_clinically_validated,
        :created_at,
        :updated_at
    )
    """
)


def record_evaluation(
    db: Session,
    result: EvaluateResult,
    symptom_ids: Iterable[int],
) -> None:
    """Persist an evaluation when auditing is enabled (best-effort)."""

    if not get_settings().audit_enabled:
        return

    now = datetime.now(timezone.utc).replace(tzinfo=None)
    codes: List[str] = [match.rule_code for match in result.matched_rules]
    ordered_ids = sorted({int(symptom_id) for symptom_id in symptom_ids})

    try:
        db.execute(
            _INSERT_AUDIT,
            {
                "symptom_ids": json.dumps(ordered_ids),
                "matched_rule_count": result.matched_rule_count,
                "matched_rule_codes": json.dumps(codes),
                "engine_version": result.engine_version,
                "is_clinically_validated": result.is_clinically_validated,
                "created_at": now,
                "updated_at": now,
            },
        )
        db.commit()
    except SQLAlchemyError as exc:  # pragma: no cover - defensive
        db.rollback()
        logger.error("Failed to record evaluation audit: %s", type(exc).__name__)
