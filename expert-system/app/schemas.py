"""Pydantic request and response schemas for the expert system API.

The schemas deliberately avoid any wording that could imply a clinically
validated diagnosis or probability. ``confidence_score`` is carried through
because it exists in the knowledge base, but it is documented as an
unvalidated configuration value rather than a probability.
"""

from __future__ import annotations

from typing import Annotated, List, Optional

from pydantic import BaseModel, ConfigDict, Field

MIN_SYMPTOMS_PER_REQUEST = 1
MAX_SYMPTOMS_PER_REQUEST = 50

CLINICAL_DISCLAIMER = (
    "This is a deterministic rule-matching engine for development and "
    "decision-support prototyping only. Results are not a medical diagnosis, "
    "are not a clinically validated probability, and must never replace "
    "assessment by a qualified clinician."
)

CONFIDENCE_SCORE_DESCRIPTION = (
    "Unvalidated configuration value stored in the knowledge base. It is NOT "
    "a clinically validated probability and must not be interpreted as one."
)


class EvaluateRequest(BaseModel):
    """Validated payload accepted by ``POST /evaluate``."""

    model_config = ConfigDict(extra="forbid")

    symptom_ids: List[Annotated[int, Field(gt=0, le=2_147_483_647)]] = Field(
        min_length=MIN_SYMPTOMS_PER_REQUEST,
        max_length=MAX_SYMPTOMS_PER_REQUEST,
        description=(
            "Positive integer identifiers of observed symptoms. Duplicates are "
            "deduplicated by the engine."
        ),
    )


class MatchedRule(BaseModel):
    """A single rule whose conditions are satisfied by the request."""

    rule_code: str
    rule_name: str
    rule_name_km: Optional[str] = None
    disease_code: str
    disease_name: str
    disease_name_km: Optional[str] = None
    priority: int = Field(
        description="Configuration ordering only. Higher values are returned "
        "first. This is not a severity or probability score."
    )
    confidence_score: float = Field(description=CONFIDENCE_SCORE_DESCRIPTION)
    severity_level: Optional[str] = None
    matched_condition_groups: List[int] = Field(
        description="Identifiers of the condition groups (OR clauses) that matched."
    )
    explanation: Optional[str] = None
    explanation_km: Optional[str] = None


class EvaluateResult(BaseModel):
    """Structured response returned by ``POST /evaluate``."""

    engine: str
    engine_version: str
    is_clinically_validated: bool = False
    matched_rule_count: int
    matched_rules: List[MatchedRule]
    notice: str = CLINICAL_DISCLAIMER


class HealthResponse(BaseModel):
    """Lightweight liveness response (no dependency checks)."""

    status: str
    service: str
    version: str


class ReadinessResponse(BaseModel):
    """Readiness response including a database connectivity check."""

    status: str
    service: str
    version: str
    database: str
