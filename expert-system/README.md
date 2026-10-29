# Medical Expert System (FastAPI)

Deterministic, rule-based clinical decision-support service for the Clinic
Management & Medical Expert System.

> **Clinical disclaimer**
> This service performs deterministic rule matching against a configurable
> knowledge base. It is **not a medical device**, its output is **not a
> diagnosis**, and its results are **not a clinically validated probability**.
> It must never replace assessment by a qualified clinician. The stored
> `confidence_score` is an unvalidated configuration value, not a probability.

> **Proprietary software.** This repository is not open source. See
> [../PROPRIETARY-NOTICE.md](../PROPRIETARY-NOTICE.md).

---

## 1. Architecture

```
React (frontend)
   │  HTTPS + Sanctum bearer token
   ▼
Laravel API  (/api/v1/expert-system/evaluate)
   │  auth:sanctum + expert-system.use permission
   │  validates symptom IDs, forwards only symptom_ids
   ▼  http://expert-system:8001 (private Docker network)
FastAPI service (this folder)
   │  parameterised SQL only
   ▼
PostgreSQL knowledge base
```

* The Python service is **internal**. In production it is reachable only by the
  Laravel backend over the private `clinic` Docker network and its port is not
  published. In development it is bound to `127.0.0.1:8001` for direct testing.
* The API validates and authorises requests and is the only component exposed to
  the browser.

## 2. Folder structure

```
expert-system/
├── app/
│   ├── __init__.py
│   ├── audit.py             # opt-in evaluation audit trail
│   ├── config.py            # environment-driven settings
│   ├── database.py          # lazy SQLAlchemy engine/session factory
│   ├── engine.py            # deterministic rule engine (load + match + cache)
│   ├── logging_config.py    # structured JSON logging
│   ├── main.py              # FastAPI app, middleware, and endpoints
│   └── schemas.py           # Pydantic request/response models
├── tests/
│   ├── conftest.py          # isolated in-memory SQLite fixtures
│   ├── test_api.py          # API tests
│   └── test_engine.py       # engine unit tests
├── Dockerfile
├── .dockerignore
├── pytest.ini
├── requirements.txt         # pinned runtime dependencies
├── requirements-dev.txt     # test dependencies
└── README.md
```

## 3. Python dependencies

Runtime (pinned in `requirements.txt`):

| Package | Version | Purpose |
|---|---|---|
| fastapi | 0.143.0 | HTTP API and OpenAPI docs |
| uvicorn[standard] | 0.38.0 | ASGI server |
| SQLAlchemy | 2.1.4 | Database access (parameterised SQL) |
| psycopg[binary] | 3.3.6 | PostgreSQL driver |

Test-only (`requirements-dev.txt`): `pytest==8.4.2`, `httpx==0.28.1`,
`bandit==1.9.4`, `pip-audit==2.10.1`. These are not installed in the runtime
image.

## 4. Environment variables

| Variable | Default | Description |
|---|---|---|
| `DATABASE_URL` | *(required)* | SQLAlchemy URL, e.g. `postgresql+psycopg://user:pass@postgres:5432/db`. Never commit real values. |
| `EXPERT_SYSTEM_API_KEY` | *(empty)* | Optional shared secret. When set, `/evaluate` requires header `X-Internal-Api-Key`. **Set this in production.** |
| `LOG_LEVEL` | `INFO` | Root log level (`DEBUG`, `INFO`, ...). |
| `DB_POOL_SIZE` | `5` | Connection pool size. |
| `DB_MAX_OVERFLOW` | `5` | Extra connections above pool size. |
| `DB_POOL_RECYCLE_SECONDS` | `1800` | Recycle idle connections after N seconds. |
| `DB_CONNECT_TIMEOUT_SECONDS` | `5` | libpq connect timeout. |
| `DB_STATEMENT_TIMEOUT_MS` | `5000` | PostgreSQL `statement_timeout`. |
| `EXPERT_RULES_CACHE_TTL_SECONDS` | `60` | In-process rule cache lifetime; `0` disables caching. |
| `EXPERT_SYSTEM_REQUEST_TIMEOUT_SECONDS` | `15` | Hard application-level timeout per request. Returns `504` on expiry. |
| `EXPERT_AUDIT_ENABLED` | `false` | Persist each evaluation to `expert_evaluation_audits`. Stores selected symptom IDs, so enable only with privacy approval. |
| `APP_NAME` | `expert-system` | Reported service name. |
| `APP_VERSION` | `1.0.0` | Reported service version. |

A ready-to-copy sample is provided at `expert-system/.env.example`.

## 5. Docker

Start the whole stack from the project root:

```sh
docker compose up -d --build
```

Start only the expert system (PostgreSQL must be healthy):

```sh
docker compose up -d --build expert-system
```

Stop:

```sh
docker compose stop expert-system
docker compose down          # keep the postgres_data volume
```

The container runs as the non-root user `appuser` (uid 10001) and exposes a
`HEALTHCHECK` that polls `/health`. PostgreSQL is reached by DNS name
`postgres` on the `clinic` network.

The base `docker-compose.yml` does **not** publish the expert-system port. The
development-only `docker-compose.override.yml` maps it to
`127.0.0.1:8001:8001`. Do not deploy the override file to production.

## 6. Local development

```powershell
cd expert-system
py -3.12 -m venv .venv
.venv\Scripts\python -m pip install -r requirements-dev.txt
$env:DATABASE_URL = "postgresql+psycopg://<user>:<password>@localhost:5432/clinic_db"
.venv\Scripts\python -m uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

Use your local credentials, not the placeholders. Python 3.12 matches the Docker
image and CI.

Interactive API docs: `http://127.0.0.1:8001/docs`.

## 7. API endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/` | none | Service metadata. |
| GET | `/health` | none | Liveness. Does **not** touch the database. |
| GET | `/ready` | none | Readiness. Verifies PostgreSQL with `SELECT 1`; `503` if unavailable. |
| POST | `/evaluate` | optional `X-Internal-Api-Key` | Rule evaluation. |

### Examples

`GET /health`

```json
{"status": "ok", "service": "expert-system", "version": "1.0.0"}
```

`GET /ready`

```json
{"status": "ready", "service": "expert-system", "version": "1.0.0", "database": "ok"}
```

`POST /evaluate`

Request:

```json
{"symptom_ids": [9, 13, 31]}
```

Response `200`:

```json
{
  "engine": "rule-based-expert-system",
  "engine_version": "1.0.0",
  "is_clinically_validated": false,
  "matched_rule_count": 1,
  "matched_rules": [
    {
      "rule_code": "RULE-FLU-001",
      "rule_name": "Influenza Pattern",
      "disease_code": "DIS-INFLUENZA",
      "disease_name": "Influenza",
      "priority": 7,
      "confidence_score": 0.85,
      "severity_level": "moderate",
      "matched_condition_groups": [1],
      "explanation": "High fever with body aches and a dry cough or chills suggests seasonal influenza."
    }
  ],
  "notice": "This is a deterministic rule-matching engine ... must never replace assessment by a qualified clinician."
}
```

A valid request that matches nothing returns `200` with `matched_rule_count: 0`
and an empty `matched_rules` list.

Errors:

| Status | Meaning |
|---|---|
| `401` | Missing/invalid internal API key (when configured). |
| `422` | Body invalid, empty, > 50 symptoms, or unknown/inactive symptom IDs. |
| `503` | Database or engine unavailable. |
| `504` | Application-level request timeout exceeded (`EXPERT_SYSTEM_REQUEST_TIMEOUT_SECONDS`). |

## 8. Rule evaluation semantics (authoritative)

Knowledge-base tables used: `symptoms`, `diseases`, `expert_rules`,
`expert_rule_conditions` (see `backend/database/migrations`).

* **Eligibility.** A rule participates only if the rule is active, its disease
  is active, it has at least one condition, every condition references an
  existing **active** symptom, and every operator is supported (`present`,
  `absent`). A rule that fails any check is skipped (it can never match).
* **AND within a group.** All conditions sharing a `condition_group` must be
  satisfied.
* **OR across groups.** A rule matches if **any** of its condition groups is
  satisfied. `matched_condition_groups` lists the satisfied groups.
* **`present`** evaluates to `True` when the symptom is in the submitted set.
* **`absent`** evaluates to `True` when the symptom is **not** submitted.
* **`expected_value`** is the boolean the evaluated condition must equal. The
  condition is satisfied when `evaluated == expected_value`. For example
  `operator=absent, expected_value=true` means "symptom must be absent".
* **Ordering.** Results are sorted by `priority` descending, then `rule_code`
  ascending. `confidence_score` is **not** used for ordering. `priority` is a
  configuration ordering value, not a severity or probability.
* **Determinism / no duplicates.** Each rule is evaluated once; a rule appears
  at most once in the output. Duplicate input symptom IDs are collapsed.

## 9. Database requirements

The service expects these columns (names verified against migrations):

* `symptoms(id, name, is_active)`
* `diseases(id, name, severity_level, is_active)`
* `expert_rules(id, code, name, disease_id, explanation, priority, confidence_score, is_active)`
* `expert_rule_conditions(expert_rule_id, symptom_id, operator, expected_value, condition_group)`

All queries use bound parameters; no user-supplied value is interpolated into
SQL.

## 10. Tests

```powershell
cd expert-system
.venv\Scripts\python -m pytest -q
```

Last verified in this working copy on 2026-10-29: **45 passed**, with one
Starlette/httpx deprecation warning.

Tests use an isolated in-memory SQLite schema; they never touch the development
or production PostgreSQL database. Coverage includes single/AND/OR matching,
`present`/`absent`, `expected_value`, priority ordering, inactive
rules/diseases/symptoms, rules without valid conditions, duplicates, no-match,
invalid IDs, request validation, readiness failures, database failures, an
unconfigured `DATABASE_URL` (returns `503`, never `500`), API key enforcement,
and the opt-in audit path.

Security scans (`requirements-dev.txt`):

```powershell
.venv\Scripts\python -m bandit -q -r app
.venv\Scripts\python -m pip_audit -r requirements.txt
```

CI (`.github/workflows/expert-system-ci.yml`) runs the test suite, `bandit`, and
`pip-audit` on every change under `expert-system/`.

## 11. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `/ready` or `/evaluate` returns `503` `{"detail":"Database is not configured."}` | `DATABASE_URL` is unset | Copy `.env.example` and set `DATABASE_URL`, then restart. |
| `/ready` returns 503 | PostgreSQL unreachable | Check `postgres` service health and credentials. |
| `/evaluate` returns 503 | DB error or engine load failure | Inspect structured logs; check DB. |
| `/evaluate` returns 422 | Unknown/inactive symptom IDs | Send only active symptom IDs. |
| Laravel returns 503 | Python service down/timeout | `docker compose ps`, check `EXPERT_SYSTEM_URL`. |
| Laravel returns 502 | Python returned non-2xx or invalid JSON | Check Python logs. |

Logs are single-line JSON on stdout: `docker logs clinic_expert_system --tail 50`.

## 12. Security considerations

* No permissive CORS is enabled.
* `/evaluate` is internal; the port is not published in production and is bound
  to loopback in development.
* When `EXPERT_SYSTEM_API_KEY` is set, `/evaluate` requires `X-Internal-Api-Key`.
* Error handlers never return SQL, credentials, or stack traces.
* All SQL is parameterised.
* Logs contain request metadata only, never symptom selections or patient data.
* The container runs as a non-root user.
* Statements and connections have libpq-level timeouts.
* Secrets are supplied via environment variables only and never committed.

## 13. Current limitations

* Rule matching is a deterministic pattern matcher, not a probabilistic or
  clinically validated model. `confidence_score` is not a probability.
* Knowledge-base indexes on `expert_rule_conditions(expert_rule_id)` are not
  added; fine for the current small KB, worth revisiting at scale.
* There is no automated clinical validation. The optional audit trail records
  evaluations but is not a substitute for clinical review.
* Rule changes take effect after the in-process cache TTL expires
  (`EXPERT_RULES_CACHE_TTL_SECONDS`, default 60 seconds).

## 14. Deployment configuration

1. Set a strong `EXPERT_SYSTEM_API_KEY` shared by backend and expert system.
2. Do **not** deploy `docker-compose.override.yml`; the base Compose file already
   keeps the service off the host network.
3. Set `APP_ENV=production`, `APP_DEBUG=false` on the backend, and supply a real
   `DATABASE_URL` through the deployment environment.
4. Run behind a reverse proxy that terminates TLS for the Laravel API.
5. Keep `restart: unless-stopped` and the Docker healthcheck enabled.
6. Decide whether `EXPERT_AUDIT_ENABLED` is permitted by the privacy policy.
7. Do not run `migrate:fresh`/`db:wipe`/`down -v` against production data.

See [Deployment & Infrastructure](../docs/10-deployment/index.md).

## 15. Adding clinically reviewed knowledge safely

1. Author knowledge as data in `KnowledgeBaseSeeder` (or a dedicated,
   review-gated seeder). **Never** inject fabricated diseases or treatment rules
   into production.
2. Have a qualified clinician review and sign off on codes, names, rule
   conditions, operators, and `expected_value` values.
3. Keep development fixtures separate from clinical content.
4. Re-run the seeder only after review; it is idempotent (`updateOrCreate`) and
   prunes placeholder fixtures.
5. Deactivate (do not delete) superseded symptoms/diseases/rules via `is_active`.
6. Re-run the test suite and a manual `/evaluate` smoke test after any change.

## 16. Related documentation

- [Root README](../README.md)
- [API Documentation](../docs/07-api-documentation/index.md)
- [Database Design](../docs/05-database-design/index.md)
- [System Design](../docs/04-system-design/index.md)
- [Deployment & Infrastructure](../docs/10-deployment/index.md)
- [Postman collection guide](postman/README.md)
- [Proprietary Notice](../PROPRIETARY-NOTICE.md)
