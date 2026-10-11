"""Shared pytest fixtures.

Environment variables are configured *before* importing the application so
that configuration is deterministic and no external PostgreSQL instance is
required. All database-backed tests use an isolated in-memory SQLite schema
that mirrors the production knowledge-base tables. No production or
development database is ever touched.
"""

from __future__ import annotations

import os

os.environ.setdefault("DATABASE_URL", "sqlite://")
os.environ.setdefault("LOG_LEVEL", "WARNING")
os.environ.pop("EXPERT_SYSTEM_API_KEY", None)

import pytest  # noqa: E402
from fastapi.testclient import TestClient  # noqa: E402
from sqlalchemy import create_engine, text  # noqa: E402
from sqlalchemy.orm import Session, sessionmaker  # noqa: E402
from sqlalchemy.pool import StaticPool  # noqa: E402

from app.config import get_settings  # noqa: E402
from app.database import get_db  # noqa: E402
from app.engine import invalidate_rules_cache  # noqa: E402
from app.main import app as fastapi_app  # noqa: E402

DDL_STATEMENTS = [
    """
    CREATE TABLE symptoms (
        id INTEGER PRIMARY KEY,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        is_active BOOLEAN NOT NULL DEFAULT 1,
        created_at TIMESTAMP,
        updated_at TIMESTAMP
    )
    """,
    """
    CREATE TABLE diseases (
        id INTEGER PRIMARY KEY,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        severity_level TEXT,
        is_active BOOLEAN NOT NULL DEFAULT 1,
        created_at TIMESTAMP,
        updated_at TIMESTAMP
    )
    """,
    """
    CREATE TABLE expert_rules (
        id INTEGER PRIMARY KEY,
        code TEXT UNIQUE NOT NULL,
        name TEXT NOT NULL,
        disease_id INTEGER NOT NULL REFERENCES diseases(id),
        explanation TEXT,
        priority INTEGER NOT NULL DEFAULT 1,
        confidence_score DECIMAL(5, 2) NOT NULL DEFAULT 0,
        is_active BOOLEAN NOT NULL DEFAULT 1,
        created_at TIMESTAMP,
        updated_at TIMESTAMP
    )
    """,
    """
    CREATE TABLE expert_rule_conditions (
        id INTEGER PRIMARY KEY,
        expert_rule_id INTEGER NOT NULL REFERENCES expert_rules(id),
        symptom_id INTEGER NOT NULL REFERENCES symptoms(id),
        operator VARCHAR(20) NOT NULL DEFAULT 'present',
        expected_value BOOLEAN NOT NULL DEFAULT 1,
        condition_group INTEGER NOT NULL DEFAULT 1,
        created_at TIMESTAMP,
        updated_at TIMESTAMP
    )
    """,
    """
    CREATE TABLE expert_evaluation_audits (
        id INTEGER PRIMARY KEY,
        symptom_ids TEXT,
        matched_rule_count INTEGER NOT NULL DEFAULT 0,
        matched_rule_codes TEXT,
        engine_version TEXT,
        is_clinically_validated BOOLEAN NOT NULL DEFAULT 0,
        created_at TIMESTAMP,
        updated_at TIMESTAMP
    )
    """,
]

# --- Seed data -------------------------------------------------------------
# Symptoms 1-5 active, 6 inactive.
SYMPTOMS = [
    (1, "SYM-FEVER", "Fever", 1),
    (2, "SYM-COUGH", "Cough", 1),
    (3, "SYM-HEADACHE", "Headache", 1),
    (4, "SYM-RUNNY-NOSE", "Runny nose", 1),
    (5, "SYM-SNEEZING", "Sneezing", 1),
    (6, "SYM-RETIRED", "Retired symptom", 0),
]

# Diseases 1-2 active, 3 inactive.
DISEASES = [
    (1, "DIS-A", "Illness A", "mild", 1),
    (2, "DIS-B", "Illness B", "moderate", 1),
    (3, "DIS-RETIRED", "Retired disease", "severe", 0),
]

# Rules: (id, code, name, disease_id, priority, confidence, is_active)
RULES = [
    (1, "RULE-AND", "AND rule", 1, 5, "0.50", 1),
    (2, "RULE-OR", "OR rule", 2, 9, "0.90", 1),
    (3, "RULE-ABSENT", "Absent rule", 1, 3, "0.40", 1),
    (4, "RULE-EXPECTED-FALSE", "Expected-false rule", 1, 2, "0.30", 1),
    (5, "RULE-INACTIVE", "Inactive rule", 1, 10, "0.99", 0),
    (6, "RULE-INACTIVE-DISEASE", "Inactive disease rule", 3, 10, "0.99", 1),
    (7, "RULE-INACTIVE-SYMPTOM", "Inactive symptom rule", 1, 10, "0.99", 1),
    (8, "RULE-NO-CONDITIONS", "No condition rule", 1, 10, "0.99", 1),
    (9, "RULE-UNKNOWN-OPERATOR", "Unknown operator rule", 1, 10, "0.99", 1),
    (10, "RULE-TIE-A", "Tie rule A", 1, 8, "0.10", 1),
    (11, "RULE-TIE-B", "Tie rule B", 1, 8, "0.20", 1),
]

# Conditions: (id, rule_id, symptom_id, operator, expected_value, group)
CONDITIONS = [
    # RULE-AND: group 1 = fever AND cough
    (1, 1, 1, "present", 1, 1),
    (2, 1, 2, "present", 1, 1),
    # RULE-OR: group 1 = headache OR group 2 = runny nose
    (3, 2, 3, "present", 1, 1),
    (4, 2, 4, "present", 1, 2),
    # RULE-ABSENT: cough absent
    (5, 3, 2, "absent", 1, 1),
    # RULE-EXPECTED-FALSE: fever present must equal false (i.e. fever absent)
    (6, 4, 1, "present", 0, 1),
    # RULE-INACTIVE (active=false) - should be ignored
    (7, 5, 1, "present", 1, 1),
    # RULE-INACTIVE-DISEASE (disease inactive) - should be ignored
    (8, 6, 1, "present", 1, 1),
    # RULE-INACTIVE-SYMPTOM references symptom 6 (inactive)
    (9, 7, 6, "present", 1, 1),
    # RULE-NO-CONDITIONS has no rows
    # RULE-UNKNOWN-OPERATOR uses an unsupported operator
    (10, 9, 1, "maybe", 1, 1),
    # Tie rules both require fever present
    (11, 10, 1, "present", 1, 1),
    (12, 11, 1, "present", 1, 1),
]


def _seed(db: Session) -> None:
    for symptom_id, code, name, is_active in SYMPTOMS:
        db.execute(
            text(
                "INSERT INTO symptoms (id, code, name, is_active) "
                "VALUES (:id, :code, :name, :is_active)"
            ),
            {"id": symptom_id, "code": code, "name": name, "is_active": is_active},
        )

    for disease_id, code, name, severity, is_active in DISEASES:
        db.execute(
            text(
                "INSERT INTO diseases (id, code, name, severity_level, is_active) "
                "VALUES (:id, :code, :name, :severity, :is_active)"
            ),
            {
                "id": disease_id,
                "code": code,
                "name": name,
                "severity": severity,
                "is_active": is_active,
            },
        )

    for rule_id, code, name, disease_id, priority, confidence, is_active in RULES:
        db.execute(
            text(
                "INSERT INTO expert_rules "
                "(id, code, name, disease_id, priority, confidence_score, is_active) "
                "VALUES (:id, :code, :name, :disease_id, :priority, :confidence, :is_active)"
            ),
            {
                "id": rule_id,
                "code": code,
                "name": name,
                "disease_id": disease_id,
                "priority": priority,
                "confidence": confidence,
                "is_active": is_active,
            },
        )

    for (
        condition_id,
        rule_id,
        symptom_id,
        operator,
        expected_value,
        group,
    ) in CONDITIONS:
        db.execute(
            text(
                "INSERT INTO expert_rule_conditions "
                "(id, expert_rule_id, symptom_id, operator, expected_value, condition_group) "
                "VALUES (:id, :rule_id, :symptom_id, :operator, :expected_value, :group)"
            ),
            {
                "id": condition_id,
                "rule_id": rule_id,
                "symptom_id": symptom_id,
                "operator": operator,
                "expected_value": expected_value,
                "group": group,
            },
        )

    db.commit()


@pytest.fixture(autouse=True)
def _clear_settings_cache():
    """Ensure environment changes are reflected in each test."""

    get_settings.cache_clear()
    invalidate_rules_cache()
    yield
    get_settings.cache_clear()
    invalidate_rules_cache()


@pytest.fixture
def db_session() -> Session:
    """A seeded in-memory session isolated to a single test."""

    engine = create_engine(
        "sqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    for statement in DDL_STATEMENTS:
        with engine.begin() as connection:
            connection.execute(text(statement))

    session = sessionmaker(bind=engine, expire_on_commit=False)()
    _seed(session)
    try:
        yield session
    finally:
        session.close()
        engine.dispose()


@pytest.fixture
def client(db_session: Session) -> TestClient:
    """TestClient with the database dependency overridden."""

    def _override_get_db():
        yield db_session

    fastapi_app.dependency_overrides[get_db] = _override_get_db
    with TestClient(fastapi_app, raise_server_exceptions=False) as test_client:
        yield test_client
    fastapi_app.dependency_overrides.clear()


def _new_engine():
    engine = create_engine(
        "sqlite://",
        connect_args={"check_same_thread": False},
        poolclass=StaticPool,
    )
    for statement in DDL_STATEMENTS:
        with engine.begin() as connection:
            connection.execute(text(statement))
    return engine


# A minimal knowledge base with only present-based rules, used to prove that a
# valid request matching nothing yields an empty (not failing) result.
MINIMAL_SYMPTOMS = [(1, "SYM-FEVER", "Fever", 1), (2, "SYM-COUGH", "Cough", 1)]
MINIMAL_DISEASES = [(1, "DIS-A", "Illness A", "mild", 1)]
MINIMAL_RULES = [(1, "RULE-ONLY-FEVER", "Only fever", 1, 5, "0.50", 1)]
MINIMAL_CONDITIONS = [(1, 1, 1, "present", 1, 1)]


def _seed_minimal(db: Session) -> None:
    for symptom_id, code, name, is_active in MINIMAL_SYMPTOMS:
        db.execute(
            text(
                "INSERT INTO symptoms (id, code, name, is_active) "
                "VALUES (:id, :code, :name, :is_active)"
            ),
            {"id": symptom_id, "code": code, "name": name, "is_active": is_active},
        )
    for disease_id, code, name, severity, is_active in MINIMAL_DISEASES:
        db.execute(
            text(
                "INSERT INTO diseases (id, code, name, severity_level, is_active) "
                "VALUES (:id, :code, :name, :severity, :is_active)"
            ),
            {
                "id": disease_id,
                "code": code,
                "name": name,
                "severity": severity,
                "is_active": is_active,
            },
        )
    for rule_id, code, name, disease_id, priority, confidence, is_active in MINIMAL_RULES:
        db.execute(
            text(
                "INSERT INTO expert_rules "
                "(id, code, name, disease_id, priority, confidence_score, is_active) "
                "VALUES (:id, :code, :name, :disease_id, :priority, :confidence, :is_active)"
            ),
            {
                "id": rule_id,
                "code": code,
                "name": name,
                "disease_id": disease_id,
                "priority": priority,
                "confidence": confidence,
                "is_active": is_active,
            },
        )
    for (
        condition_id,
        rule_id,
        symptom_id,
        operator,
        expected_value,
        group,
    ) in MINIMAL_CONDITIONS:
        db.execute(
            text(
                "INSERT INTO expert_rule_conditions "
                "(id, expert_rule_id, symptom_id, operator, expected_value, condition_group) "
                "VALUES (:id, :rule_id, :symptom_id, :operator, :expected_value, :group)"
            ),
            {
                "id": condition_id,
                "rule_id": rule_id,
                "symptom_id": symptom_id,
                "operator": operator,
                "expected_value": expected_value,
                "group": group,
            },
        )
    db.commit()


@pytest.fixture
def minimal_session() -> Session:
    """An in-memory session with only present-based rules."""

    engine = _new_engine()
    session = sessionmaker(bind=engine, expire_on_commit=False)()
    _seed_minimal(session)
    try:
        yield session
    finally:
        session.close()
        engine.dispose()


@pytest.fixture
def empty_result_client(minimal_session: Session) -> TestClient:
    """TestClient backed by the minimal (present-only) knowledge base."""

    def _override_get_db():
        yield minimal_session

    fastapi_app.dependency_overrides[get_db] = _override_get_db
    with TestClient(fastapi_app, raise_server_exceptions=False) as test_client:
        yield test_client
    fastapi_app.dependency_overrides.clear()
