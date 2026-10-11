"""Deterministic, rule-based inference engine.

Rule semantics (authoritative, matching the existing knowledge-base schema):

* A *rule* is eligible when the rule is active, its disease is active, it has
  at least one condition, every condition references an existing active
  symptom, and every condition uses a supported operator.
* Conditions sharing the same ``condition_group`` are combined with **AND**.
* Different ``condition_groups`` within the same rule are combined with
  **OR** (any group matching makes the rule match).
* ``expected_value`` is the boolean value the evaluated condition must equal:

  - operator ``present`` evaluates to ``True`` when the symptom is in the
    submitted set, otherwise ``False``.
  - operator ``absent`` evaluates to ``True`` when the symptom is **not** in
    the submitted set, otherwise ``False``.
  - the condition is satisfied when the evaluated value equals
    ``expected_value``.

* Results are ordered by ``priority`` descending, then ``rule_code`` ascending,
  which is deterministic and does not depend on an unvalidated confidence
  value.

The engine never fabricates knowledge. It does not claim that priority or the
stored ``confidence_score`` represents a clinically validated probability.
"""

from __future__ import annotations

import logging
import threading
import time
from dataclasses import dataclass, field
from typing import Dict, Iterable, List, Optional, Sequence, Tuple

from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError
from sqlalchemy.orm import Session

from app.config import SERVICE_VERSION, get_settings
from app.schemas import EvaluateResult, MatchedRule

logger = logging.getLogger(__name__)

ENGINE_NAME = "rule-based-expert-system"
SUPPORTED_OPERATORS = frozenset({"present", "absent"})

# (expiry_monotonic, rules). Guarded by ``_rules_cache_lock`` because FastAPI
# runs synchronous endpoints in a threadpool.
_rules_cache: Optional[Tuple[float, List["Rule"]]] = None
_rules_cache_lock = threading.Lock()

_RULE_QUERY = text(
    """
    SELECT
        er.id                AS rule_id,
        er.code              AS rule_code,
        er.name              AS rule_name,
        er.explanation       AS explanation,
        er.priority          AS priority,
        er.confidence_score  AS confidence_score,
        d.code               AS disease_code,
        d.name               AS disease_name,
        d.severity_level     AS severity_level,
        erc.id               AS condition_id,
        erc.symptom_id       AS symptom_id,
        erc.operator         AS operator,
        erc.expected_value   AS expected_value,
        erc.condition_group  AS condition_group,
        s.name               AS symptom_name,
        s.is_active          AS symptom_is_active
    FROM expert_rules er
    JOIN diseases d
        ON d.id = er.disease_id
       AND d.is_active = TRUE
    LEFT JOIN expert_rule_conditions erc
        ON erc.expert_rule_id = er.id
    LEFT JOIN symptoms s
        ON s.id = erc.symptom_id
    WHERE er.is_active = TRUE
    ORDER BY er.priority DESC, er.code ASC, erc.condition_group ASC, erc.id ASC
    """
)


class RuleEngineError(Exception):
    """Base class for engine failures."""


class RuleLoadError(RuleEngineError):
    """Raised when active rules cannot be read from the database."""


@dataclass(frozen=True)
class RuleCondition:
    """A single ``expert_rule_conditions`` row."""

    condition_id: int
    symptom_id: int
    symptom_name: str | None
    operator: str
    expected_value: bool
    condition_group: int

    @property
    def is_supported(self) -> bool:
        return self.operator in SUPPORTED_OPERATORS


@dataclass
class Rule:
    """An ``expert_rules`` row together with its conditions."""

    rule_id: int
    rule_code: str
    rule_name: str
    explanation: str | None
    priority: int
    confidence_score: float
    disease_code: str
    disease_name: str
    severity_level: str | None
    conditions: List[RuleCondition] = field(default_factory=list)
    is_eligible: bool = True
    ineligibility_reason: str | None = None


def _coerce_bool(value: object) -> bool:
    """Normalise driver-specific boolean representations (``True``/``1``)."""

    if isinstance(value, bool):
        return value
    if isinstance(value, (int, float)):
        return value != 0
    if isinstance(value, str):
        return value.strip().lower() in {"1", "true", "t", "yes", "y"}
    return bool(value)


def _build_rules(rows: Iterable[dict]) -> List[Rule]:
    """Group flat SQL rows into :class:`Rule` objects, flagging ineligible ones.

    Inactive or unknown symptoms make a rule ineligible instead of silently
    dropping the condition, which prevents false matches on a reduced set of
    conditions.
    """

    rules: "Dict[int, Rule]" = {}

    for row in rows:
        rule_id = int(row["rule_id"])

        rule = rules.get(rule_id)
        if rule is None:
            rule = Rule(
                rule_id=rule_id,
                rule_code=row["rule_code"],
                rule_name=row["rule_name"],
                explanation=row["explanation"],
                priority=int(row["priority"] or 0),
                confidence_score=float(row["confidence_score"] or 0),
                disease_code=row["disease_code"],
                disease_name=row["disease_name"],
                severity_level=row["severity_level"],
            )
            rules[rule_id] = rule

        if row["condition_id"] is None:
            continue

        symptom_id = row["symptom_id"]
        symptom_is_active = _coerce_bool(row["symptom_is_active"])

        if symptom_id is None:
            rule.is_eligible = False
            rule.ineligibility_reason = "condition references a missing symptom"
            continue

        if not symptom_is_active:
            rule.is_eligible = False
            rule.ineligibility_reason = "condition references an inactive symptom"
            continue

        condition = RuleCondition(
            condition_id=int(row["condition_id"]),
            symptom_id=int(symptom_id),
            symptom_name=row["symptom_name"],
            operator=str(row["operator"]).strip().lower(),
            expected_value=_coerce_bool(row["expected_value"]),
            condition_group=int(row["condition_group"] or 1),
        )

        if not condition.is_supported:
            rule.is_eligible = False
            rule.ineligibility_reason = f"unsupported operator '{row['operator']}'"
            continue

        rule.conditions.append(condition)

    for rule in rules.values():
        if not rule.conditions:
            rule.is_eligible = False
            rule.ineligibility_reason = rule.ineligibility_reason or "rule has no conditions"

    return list(rules.values())


def load_active_rules(db: Session, *, use_cache: bool = True) -> List[Rule]:
    """Load active rules (and their diseases/conditions) from PostgreSQL.

    Results are cached in-process for ``EXPERT_RULES_CACHE_TTL_SECONDS`` seconds
    (0 disables caching) so steady traffic does not re-read the knowledge base
    on every request. The cache is time-based; call :func:`invalidate_rules_cache`
    after a knowledge-base change if immediate visibility is required.
    """

    ttl = get_settings().rules_cache_ttl_seconds

    global _rules_cache

    if use_cache and ttl > 0:
        cached = _rules_cache
        if cached is not None and time.monotonic() < cached[0]:
            return cached[1]

    try:
        rows = db.execute(_RULE_QUERY).mappings().all()
    except SQLAlchemyError as exc:  # pragma: no cover - exercised via API tests
        logger.error("Failed to load expert rules: %s", type(exc).__name__)
        raise RuleLoadError("Unable to load expert rules from the knowledge base.") from exc

    rules = _build_rules(rows)

    if use_cache and ttl > 0:
        with _rules_cache_lock:
            _rules_cache = (time.monotonic() + ttl, rules)

    return rules


def invalidate_rules_cache() -> None:
    """Drop the cached rules so the next load reads the database."""

    global _rules_cache
    with _rules_cache_lock:
        _rules_cache = None


def _condition_satisfied(condition: RuleCondition, selected: frozenset[int]) -> bool:
    is_present = condition.symptom_id in selected

    if condition.operator == "present":
        evaluated = is_present
    else:  # "absent" (validated when the rule was loaded)
        evaluated = not is_present

    return evaluated == condition.expected_value


def match_rules(
    rules: Sequence[Rule],
    selected_symptom_ids: Iterable[int],
) -> List[MatchedRule]:
    """Return rules whose condition groups are satisfied by the input.

    Duplicate identifiers are collapsed via a set. Ineligible rules are
    skipped. The returned list is deterministically ordered.
    """

    selected = frozenset(int(symptom_id) for symptom_id in selected_symptom_ids)

    matches: List[MatchedRule] = []

    for rule in rules:
        if not rule.is_eligible or not rule.conditions:
            continue

        groups: "Dict[int, List[RuleCondition]]" = {}
        for condition in rule.conditions:
            groups.setdefault(condition.condition_group, []).append(condition)

        matched_groups = [
            group_id
            for group_id, conditions in sorted(groups.items())
            if all(_condition_satisfied(condition, selected) for condition in conditions)
        ]

        if not matched_groups:
            continue

        matches.append(
            MatchedRule(
                rule_code=rule.rule_code,
                rule_name=rule.rule_name,
                disease_code=rule.disease_code,
                disease_name=rule.disease_name,
                priority=rule.priority,
                confidence_score=rule.confidence_score,
                severity_level=rule.severity_level,
                matched_condition_groups=matched_groups,
                explanation=rule.explanation,
            )
        )

    matches.sort(key=lambda item: (-item.priority, item.rule_code))
    return matches


def evaluate_rules(
    db: Session,
    selected_symptom_ids: Iterable[int],
) -> EvaluateResult:
    """Load active rules and evaluate them against the selected symptoms.

    Returns a structured result. A valid request that matches no rules yields
    an empty ``matched_rules`` list rather than an error.
    """

    selected = {int(symptom_id) for symptom_id in selected_symptom_ids}
    rules = load_active_rules(db)
    matches = match_rules(rules, selected)

    return EvaluateResult(
        engine=ENGINE_NAME,
        engine_version=SERVICE_VERSION,
        is_clinically_validated=False,
        matched_rule_count=len(matches),
        matched_rules=matches,
    )
