"""Unit tests for rule evaluation semantics.

These tests exercise the pure matching logic directly (no database) and the
database loading path against an isolated in-memory schema.
"""

from __future__ import annotations

from typing import List, Optional

from sqlalchemy.orm import Session

from app.engine import (
    Rule,
    RuleCondition,
    evaluate_rules,
    load_active_rules,
    match_rules,
)


def make_condition(
    condition_id: int,
    symptom_id: int,
    operator: str = "present",
    expected_value: bool = True,
    condition_group: int = 1,
) -> RuleCondition:
    return RuleCondition(
        condition_id=condition_id,
        symptom_id=symptom_id,
        symptom_name=f"Symptom {symptom_id}",
        operator=operator,
        expected_value=expected_value,
        condition_group=condition_group,
    )


def make_rule(
    rule_id: int,
    code: str,
    conditions: List[RuleCondition],
    priority: int = 5,
    confidence: float = 0.5,
    is_eligible: bool = True,
    disease_code: str = "DIS-A",
) -> Rule:
    return Rule(
        rule_id=rule_id,
        rule_code=code,
        rule_name=code,
        explanation=None,
        priority=priority,
        confidence_score=confidence,
        disease_code=disease_code,
        disease_name="Illness A",
        severity_level="mild",
        conditions=list(conditions),
        is_eligible=is_eligible,
    )


def codes(matches) -> List[str]:
    return [match.rule_code for match in matches]


# --- Pure matching logic ---------------------------------------------------


def test_single_present_condition_matches():
    rule = make_rule(1, "RULE-ONE", [make_condition(1, 10)])
    assert codes(match_rules([rule], {10})) == ["RULE-ONE"]


def test_single_present_condition_does_not_match_when_absent():
    rule = make_rule(1, "RULE-ONE", [make_condition(1, 10)])
    assert match_rules([rule], {99}) == []


def test_and_logic_requires_all_conditions():
    rule = make_rule(
        1,
        "RULE-AND",
        [make_condition(1, 10), make_condition(2, 11, condition_group=1)],
    )
    assert match_rules([rule], {10, 11}) != []
    assert match_rules([rule], {10}) == []
    assert match_rules([rule], {11}) == []


def test_or_logic_across_groups():
    rule = make_rule(
        1,
        "RULE-OR",
        [make_condition(1, 10, condition_group=1), make_condition(2, 11, condition_group=2)],
    )
    assert codes(match_rules([rule], {10})) == ["RULE-OR"]
    assert codes(match_rules([rule], {11})) == ["RULE-OR"]
    assert codes(match_rules([rule], {10, 11})) == ["RULE-OR"]
    assert match_rules([rule], {99}) == []


def test_absent_operator_matches_only_when_symptom_missing():
    rule = make_rule(1, "RULE-ABSENT", [make_condition(1, 10, operator="absent")])
    assert codes(match_rules([rule], set())) == ["RULE-ABSENT"]
    assert match_rules([rule], {10}) == []


def test_expected_value_false_on_present_behaves_like_absent():
    rule = make_rule(
        1,
        "RULE-EXPECTED-FALSE",
        [make_condition(1, 10, operator="present", expected_value=False)],
    )
    assert codes(match_rules([rule], set())) == ["RULE-EXPECTED-FALSE"]
    assert match_rules([rule], {10}) == []


def test_matched_condition_groups_reported():
    rule = make_rule(
        1,
        "RULE-OR",
        [make_condition(1, 10, condition_group=1), make_condition(2, 11, condition_group=2)],
    )
    matches = match_rules([rule], {10, 11})
    assert matches[0].matched_condition_groups == [1, 2]


def test_priority_ordering_and_code_tiebreak():
    low = make_rule(1, "RULE-LOW", [make_condition(1, 10)], priority=1)
    high_b = make_rule(2, "RULE-HIGH-B", [make_condition(2, 10)], priority=9)
    high_a = make_rule(3, "RULE-HIGH-A", [make_condition(3, 10)], priority=9)
    result = match_rules([low, high_b, high_a], {10})
    assert codes(result) == ["RULE-HIGH-A", "RULE-HIGH-B", "RULE-LOW"]


def test_duplicate_symptom_ids_are_deduplicated():
    rule = make_rule(1, "RULE-ONE", [make_condition(1, 10)])
    assert codes(match_rules([rule], [10, 10, 10])) == ["RULE-ONE"]


def test_empty_selection_matches_nothing():
    rule = make_rule(1, "RULE-ONE", [make_condition(1, 10)])
    assert match_rules([rule], set()) == []


def test_ineligible_rule_is_skipped():
    rule = make_rule(1, "RULE-BAD", [make_condition(1, 10)], is_eligible=False)
    assert match_rules([rule], {10}) == []


def test_rule_without_conditions_is_skipped():
    rule = make_rule(1, "RULE-EMPTY", [])
    assert match_rules([rule], {10}) == []


# --- Database loading path -------------------------------------------------


def load_by_code(db_session: Session) -> dict:
    return {rule.rule_code: rule for rule in load_active_rules(db_session)}


def test_load_excludes_inactive_rule(db_session: Session):
    rules = load_by_code(db_session)
    assert "RULE-INACTIVE" not in rules


def test_load_excludes_rule_for_inactive_disease(db_session: Session):
    rules = load_by_code(db_session)
    assert "RULE-INACTIVE-DISEASE" not in rules


def test_rule_with_inactive_symptom_is_ineligible(db_session: Session):
    rules = load_by_code(db_session)
    rule = rules["RULE-INACTIVE-SYMPTOM"]
    assert rule.is_eligible is False
    assert "inactive symptom" in (rule.ineligibility_reason or "")


def test_rule_without_conditions_is_ineligible(db_session: Session):
    rules = load_by_code(db_session)
    rule = rules["RULE-NO-CONDITIONS"]
    assert rule.is_eligible is False


def test_rule_with_unknown_operator_is_ineligible(db_session: Session):
    rules = load_by_code(db_session)
    rule = rules["RULE-UNKNOWN-OPERATOR"]
    assert rule.is_eligible is False
    assert "unsupported operator" in (rule.ineligibility_reason or "")


def test_evaluate_end_to_end_matches_and_orders(db_session: Session):
    result = evaluate_rules(db_session, {1, 2, 3, 4})
    # RULE-AND requires fever+cough, RULE-OR requires headache or runny nose,
    # RULE-TIE-A/B require fever.
    assert result.matched_rule_count == len(result.matched_rules)
    assert result.matched_rule_count >= 4
    assert result.engine == "rule-based-expert-system"
    assert result.is_clinically_validated is False
    priorities = [match.priority for match in result.matched_rules]
    assert priorities == sorted(priorities, reverse=True)
    assert "RULE-OR" in codes(result.matched_rules)
    assert "RULE-AND" in codes(result.matched_rules)


def test_evaluate_with_no_matches_returns_empty_success(minimal_session: Session):
    # symptom 2 (cough) exists but no rule references it.
    result = evaluate_rules(minimal_session, {2})
    assert result.matched_rule_count == 0
    assert result.matched_rules == []
    assert result.is_clinically_validated is False


def test_evaluate_absent_rule_matches_when_cough_not_selected(db_session: Session):
    result = evaluate_rules(db_session, {1})  # fever only, no cough
    assert "RULE-ABSENT" in codes(result.matched_rules)
