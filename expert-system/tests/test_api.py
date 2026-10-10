"""API tests for the FastAPI service.

The database dependency is overridden with an isolated in-memory session, so
no external PostgreSQL instance is required and no real data is touched.
"""

from __future__ import annotations

import pytest
from fastapi.testclient import TestClient
from sqlalchemy.exc import OperationalError

from app.config import get_settings
from app.database import get_db
from app.main import app as fastapi_app
from app.schemas import MAX_SYMPTOMS_PER_REQUEST


class BrokenSession:
    """A session stub whose queries always fail at the database layer."""

    def execute(self, *args, **kwargs):  # noqa: D401 - test stub
        raise OperationalError("SELECT 1", {}, Exception("connection refused"))

    def close(self) -> None:
        pass


def with_broken_db():
    """Return a context manager installing a failing DB dependency."""

    def _override():
        return BrokenSession()

    return _override


# --- Meta / health ---------------------------------------------------------


def test_health_is_lightweight_and_ok(client: TestClient):
    response = client.get("/health")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ok"
    assert body["service"] == "expert-system"
    assert "version" in body


def test_root_returns_service_metadata(client: TestClient):
    response = client.get("/")
    assert response.status_code == 200
    body = response.json()
    assert body["service"] == "expert-system"
    assert body["endpoints"]["evaluate"] == "/evaluate"


# --- Readiness -------------------------------------------------------------


def test_ready_reports_database_ok(client: TestClient):
    response = client.get("/ready")
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "ready"
    assert body["database"] == "ok"


def test_ready_returns_503_when_database_unavailable(db_session):
    fastapi_app.dependency_overrides[get_db] = with_broken_db()
    try:
        with TestClient(fastapi_app, raise_server_exceptions=False) as client:
            response = client.get("/ready")
    finally:
        fastapi_app.dependency_overrides.clear()

    assert response.status_code == 503
    assert response.json() == {"detail": "Database is not ready."}


# --- Evaluate: success paths ----------------------------------------------


def test_evaluate_valid_request(client: TestClient):
    response = client.post("/evaluate", json={"symptom_ids": [1, 2, 3, 4]})
    assert response.status_code == 200
    body = response.json()
    assert body["engine"] == "rule-based-expert-system"
    assert body["is_clinically_validated"] is False
    assert body["matched_rule_count"] == len(body["matched_rules"])
    assert body["matched_rule_count"] >= 1
    assert "notice" in body
    first = body["matched_rules"][0]
    assert set(first) >= {
        "rule_code",
        "rule_name",
        "disease_code",
        "disease_name",
        "priority",
        "confidence_score",
        "matched_condition_groups",
    }


def test_evaluate_no_matching_rules_is_success_with_empty_list(empty_result_client: TestClient):
    # symptom 2 exists and is active, but no rule references it.
    response = empty_result_client.post("/evaluate", json={"symptom_ids": [2]})
    assert response.status_code == 200
    body = response.json()
    assert body["matched_rule_count"] == 0
    assert body["matched_rules"] == []


def test_evaluate_duplicate_ids_are_deduplicated(client: TestClient):
    response = client.post("/evaluate", json={"symptom_ids": [1, 1, 1, 2]})
    assert response.status_code == 200
    assert "RULE-AND" in [m["rule_code"] for m in response.json()["matched_rules"]]


# --- Evaluate: validation failures ----------------------------------------


def test_evaluate_missing_body_is_unprocessable(client: TestClient):
    response = client.post("/evaluate", json={})
    assert response.status_code == 422


def test_evaluate_empty_list_is_unprocessable(client: TestClient):
    response = client.post("/evaluate", json={"symptom_ids": []})
    assert response.status_code == 422


def test_evaluate_too_many_symptoms_is_unprocessable(client: TestClient):
    response = client.post(
        "/evaluate",
        json={"symptom_ids": list(range(1, MAX_SYMPTOMS_PER_REQUEST + 2))},
    )
    assert response.status_code == 422


def test_evaluate_non_positive_id_is_unprocessable(client: TestClient):
    response = client.post("/evaluate", json={"symptom_ids": [0]})
    assert response.status_code == 422


def test_evaluate_nonexistent_id_returns_422(client: TestClient):
    response = client.post("/evaluate", json={"symptom_ids": [999]})
    assert response.status_code == 422
    assert response.json()["detail"]["invalid_symptom_ids"] == [999]


def test_evaluate_inactive_symptom_returns_422(client: TestClient):
    response = client.post("/evaluate", json={"symptom_ids": [6]})
    assert response.status_code == 422
    assert response.json()["detail"]["invalid_symptom_ids"] == [6]


# --- Evaluate: dependency failures ----------------------------------------


def test_evaluate_returns_503_when_database_unavailable(db_session):
    fastapi_app.dependency_overrides[get_db] = with_broken_db()
    try:
        with TestClient(fastapi_app, raise_server_exceptions=False) as client:
            response = client.post("/evaluate", json={"symptom_ids": [1]})
    finally:
        fastapi_app.dependency_overrides.clear()

    assert response.status_code == 503
    text = response.text
    assert "OperationalError" not in text
    assert "Traceback" not in text
    assert response.json() == {"detail": "Database temporarily unavailable."}


# --- Internal API key ------------------------------------------------------


def test_evaluate_requires_api_key_when_configured(client: TestClient, monkeypatch: pytest.MonkeyPatch):
    monkeypatch.setenv("EXPERT_SYSTEM_API_KEY", "s3cret")
    get_settings.cache_clear()

    unauthorized = client.post("/evaluate", json={"symptom_ids": [1]})
    assert unauthorized.status_code == 401

    authorized = client.post(
        "/evaluate",
        json={"symptom_ids": [1]},
        headers={"X-Internal-Api-Key": "s3cret"},
    )
    assert authorized.status_code == 200


def test_evaluate_rejects_wrong_api_key(client: TestClient, monkeypatch: pytest.MonkeyPatch):
    monkeypatch.setenv("EXPERT_SYSTEM_API_KEY", "s3cret")
    get_settings.cache_clear()

    response = client.post(
        "/evaluate",
        json={"symptom_ids": [1]},
        headers={"X-Internal-Api-Key": "wrong"},
    )
    assert response.status_code == 401
