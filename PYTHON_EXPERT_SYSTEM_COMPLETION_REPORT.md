# Python Medical Expert System — Completion Report

Project: `clinic-management-medical-expert-system`
Report date: 2026-10-10
Scope: Python FastAPI expert system, PostgreSQL knowledge base, and Laravel ↔ Python integration.

---

## 1. Executive summary

The Python Medical Expert System was inspected, completed, hardened, tested,
containerised, integrated with the Laravel API, and verified end-to-end against
the running PostgreSQL knowledge base and the running Docker stack.

Key outcomes:

* A deterministic, typed, parameterised rule engine with clearly documented
  `present`/`absent`/`expected_value` semantics, AND-within-group / OR-across-
  group logic, eligibility rules, and deterministic ordering.
* A hardened FastAPI service with `/`, `/health`, `/ready`, `/evaluate`,
  Pydantic schemas, structured JSON logging, optional internal API key,
  database/engine error handling that never leaks internals, and no CORS.
* A secured Laravel proxy endpoint `POST /api/v1/expert-system/evaluate` that
  enforces Sanctum authentication and the existing `expert-system.use`
  permission, validates symptom IDs locally, forwards only `symptom_ids`,
  applies timeouts, and validates the upstream response.
* Pinned dependencies, non-root container, Docker healthcheck, and private
  networking.
* **Python tests: 37 passed / 0 failed.** **Laravel tests: 11 passed / 0 failed
  (26 assertions)** including 9 new expert-system integration tests.
* Security tooling run: `pip-audit` (no known vulnerabilities), `bandit`
  (0 issues), `composer audit` (no advisories), `docker scout cves` on the built
  image (0 critical, 1 high with no upstream fix).
* Live authorization smoke test for all roles (Admin/Doctor 200; Nurse/
  Receptionist/Patient 403) confirmed the running stack enforces the RBAC.
* Built, started, healthy in Docker; readiness verifies PostgreSQL; end-to-end
  Laravel → Python request returned HTTP 200 with a real rule match.

Clinical validity is **out of scope and NOT established**. The system is a
deterministic pattern matcher, not a validated diagnostic model.

---

## 2. Initial project status

* Git: branch `master`, **no commits yet**; all project files untracked. No user
  commits existed to preserve; no resets or destructive operations were run.
* Stack already present and running via Docker Compose:
  * `clinic_postgres` (postgres:18, healthy)
  * `clinic_backend` (Laravel/PHP 8.4 CLI server, up)
  * `clinic_expert_system` (FastAPI, port bound to `127.0.0.1:8001`)
  * `clinic_frontend` (Vite, exited at the time of inspection)
* Database schema (source of truth) — 26 tables. Knowledge base populated:
  **63 symptoms, 20 diseases, 20 expert rules, 117 rule conditions**. No
  inactive rows.
* Pre-existing Python files were functional but incomplete: no `/ready`, no
  response schemas, no structured logging, no error hardening, unpinned deps,
  and an engine that silently dropped conditions for inactive symptoms (risk of
  false matches).
* Pre-existing migration defects blocked any fresh migration (and therefore the
  feature tests): `create_appointments_table.php` called the non-existent
  `casecadeOnDelete()`, and the follow-up rename migration renamed a column that
  the base migration already creates.
* Tooling available: PHP 8.5.9, Composer 2.10.2, Python 3.13.15 (`py`; the
  `python` alias is absent on this host), Docker 29.8.2, Docker Compose v5.5.1.

---

## 3. Files created or modified

### Python service (`expert-system/`)

| File | Status | Notes |
|---|---|---|
| `app/config.py` | created | Env-driven, cached settings; no hardcoded secrets. |
| `app/database.py` | replaced | Lazy engine; PG pooling + timeouts; SQLite support for tests. |
| `app/engine.py` | replaced | Typed engine; eligibility rules; deterministic ordering. |
| `app/schemas.py` | created | Pydantic request/response models with clinical disclaimer. |
| `app/logging_config.py` | created | Structured JSON logging, no PII. |
| `app/main.py` | replaced | `/`, `/health`, `/ready`, `/evaluate`; API key; error handlers; middleware. |
| `app/__init__.py` | kept | Unchanged (empty). |
| `requirements.txt` | modified | Pinned to the versions already used by the container. |
| `requirements-dev.txt` | created | `pytest`, `httpx`, `bandit`, `pip-audit`. |
| `pytest.ini` | created | Test discovery. |
| `.env.example` | created | Documented sample configuration (placeholders only). |
| `Dockerfile` | replaced | Non-root, healthcheck, pinned deps, SIGTERM. |
| `.dockerignore` | created | Excludes venv/tests/caches from build context. |
| `.gitignore` | created | Excludes venv/caches. |
| `tests/conftest.py` | created | In-memory SQLite fixtures mirroring production tables. |
| `tests/test_engine.py` | created | Engine unit + DB-load tests. |
| `tests/test_api.py` | created | API tests. |
| `README.md` | created | Full documentation. |

### Laravel backend (`backend/`)

| File | Status | Notes |
|---|---|---|
| `app/Http/Controllers/Api/ExpertSystemController.php` | created | Secured proxy with validation + timeouts + response validation. |
| `routes/api.php` | modified | Added `POST /api/v1/expert-system/evaluate`. |
| `config/services.php` | modified | Added `services.expert_system` block (URL/timeouts/api_key). |
| `tests/Feature/ExpertSystemTest.php` | created | 9 integration/authorization/error-handling tests. |
| `.env.example` | modified | Added `EXPERT_SYSTEM_*` keys (empty secret). |
| `.env` | modified | Added `EXPERT_SYSTEM_*` keys pointing at `expert-system:8001`. |
| `database/migrations/2026_10_06_122856_create_appointments_table.php` | modified | Fixed `casecadeOnDelete()` → `cascadeOnDelete()`. |
| `database/migrations/2026_10_07_043623_rename_appointment_type_to_appointment_number.php` | modified | Guarded rename with `hasColumn` so fresh migrations work. |

### Root / infrastructure

| File | Status | Notes |
|---|---|---|
| `docker-compose.yml` | modified | Env for backend + expert-system, healthcheck, `depends_on`. |
| `.env.example` | modified | Documented `EXPERT_SYSTEM_*` variables (placeholders only). |
| `.gitignore` | created | Ignores real `.env*` files; keeps `.env.example` tracked. |
| `.github/workflows/expert-system-ci.yml` | created | CI: pytest + bandit + pip-audit on `expert-system/**` changes. |
| `PYTHON_EXPERT_SYSTEM_COMPLETION_REPORT.md` | created | This report. |

**Not changed:** no Knowledge Base tables/columns were renamed or deleted; no
new migrations were added; no frontend files were modified; no production data
was altered.

---

## 4. Architecture and data flow

```
React ──► Laravel API  POST /api/v1/expert-system/evaluate
              │  auth:sanctum
              │  can('expert-system.use')
              │  validate symptom_ids (exist + active, 1..50, distinct)
              │  forward only {"symptom_ids": [...]}  (timeout 5s / connect 2s)
              ▼
        FastAPI (private network http://expert-system:8001)
              │  optional X-Internal-Api-Key
              │  validate IDs again (defence in depth)
              │  parameterised SQL
              ▼
        PostgreSQL (diseases / expert_rules / expert_rule_conditions / symptoms)
```

Laravel validates the Python response shape before returning it to React. The
Python port is bound to `127.0.0.1:8001` in development and is intended to be
unpublished in production.

---

## 5. Implemented inference rules and exact semantics

Source: `expert-system/app/engine.py`.

**Eligibility (a rule is skipped unless all hold):**
1. `expert_rules.is_active = TRUE`
2. `diseases.is_active = TRUE`
3. rule has ≥ 1 condition
4. every condition references an existing **active** symptom
5. every operator is supported (`present`, `absent`)

**Evaluation:**
* Within a `condition_group`: **AND** (all conditions must be satisfied).
* Across groups: **OR** (any satisfied group makes the rule match).
* `present` → evaluated `True` iff the symptom is in the submitted set.
* `absent` → evaluated `True` iff the symptom is **not** in the submitted set.
* A condition is satisfied iff `evaluated == expected_value`.
* Duplicate input IDs are collapsed; each rule appears at most once.
* Ordering: `priority` DESC, then `rule_code` ASC (deterministic).
  `confidence_score` is **not** used for ordering and is explicitly documented
  as an unvalidated configuration value, **not** a probability.
* Empty selection / no match → HTTP 200 with `matched_rule_count: 0`.

Response includes `is_clinically_validated: false` and a clinical disclaimer.

---

## 6. FastAPI endpoints and example responses

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/` | none | Metadata |
| GET | `/health` | none | Liveness (no DB) |
| GET | `/ready` | none | Readiness (`SELECT 1`) |
| POST | `/evaluate` | optional internal key | Rule evaluation |

Verified live outputs:

```json
GET /health
{"status":"ok","service":"expert-system","version":"1.0.0"}

GET /ready
{"status":"ready","service":"expert-system","version":"1.0.0","database":"ok"}

POST /evaluate {"symptom_ids":[9,13,31]}   -> 200
{
  "engine":"rule-based-expert-system","engine_version":"1.0.0",
  "is_clinically_validated":false,"matched_rule_count":1,
  "matched_rules":[{"rule_code":"RULE-FLU-001","disease_name":"Influenza",
    "priority":7,"confidence_score":0.85,"matched_condition_groups":[1], ...}],
  "notice":"This is a deterministic rule-matching engine ..."
}

POST /evaluate {"symptom_ids":[999999]}  -> 422
{"detail":{"message":"One or more symptoms are invalid or inactive.",
           "invalid_symptom_ids":[999999]}}
```

---

## 7. Laravel integration status

* `POST /api/v1/expert-system/evaluate`, inside the `auth:sanctum` group.
* Permission enforced with `$request->user()->can('expert-system.use')` (the
  permission defined in `RolePermissionSeeder`). **Doctor and Admin** hold it;
  **Nurse has only `expert-system.view`**; Receptionist/Patient/Pharmacist do
  not — all denied (`403`). Unauthenticated → `401`.
* Symptom IDs validated with `array|min:1|max:50` and
  `Rule::exists('symptoms','id')->where('is_active', true)` + `distinct`.
* Only `symptom_ids` are forwarded.
* URL/timeouts/secret from `config('services.expert_system')` (env-driven).
* Error mapping: connection/timeout → `503`; upstream non-2xx → `502`; invalid
  upstream JSON/shape → `502`; no upstream internals leaked.
* Response format: `{"message": ..., "data": <python payload>}`.

Verified live (admin token): HTTP 200 with `data.matched_rule_count=1`
(`RULE-FLU-001`), forwarded over the private Docker network to
`expert-system:8001`. Unauthenticated request with `Accept: application/json`
→ `401`.

Live role smoke test against the running stack (temporary Sanctum tokens minted
in the container; no passwords changed; temporary objects deleted afterwards)
with symptom set `[9,13,31]`:

| Role | Result |
|---|---|
| Admin | HTTP 200, `matched_rule_count=1` |
| Doctor | HTTP 200, `matched_rule_count=1` |
| Nurse | HTTP 403 |
| Receptionist | HTTP 403 |
| Patient | HTTP 403 |

This confirms the live container enforces exactly the `expert-system.use`
permission matrix covered by the automated tests.

---

## 8. Database and Docker changes

**Database:** No schema changes were added. The Python queries match the actual
columns of `symptoms`, `diseases`, `expert_rules`, and `expert_rule_conditions`.
Two pre-existing migration files were fixed so that fresh migrations succeed:

* `create_appointments_table.php`: corrected the invalid `casecadeOnDelete()`
  method name to `cascadeOnDelete()`.
* `..._rename_appointment_type_to_appointment_number.php`: guarded the rename
  with `Schema::hasColumn(...)` because the base table migration already creates
  `appointment_number`.

Neither change affects the already-migrated development database (their
migration records already exist). No destructive command (`migrate:fresh`,
`db:wipe`, `down -v`) was run.

**Docker:**
* `expert-system` service: non-root user, `HEALTHCHECK`, `restart:
  unless-stopped`, `EXPERT_SYSTEM_API_KEY`, `LOG_LEVEL`, DB timeout envs,
  `depends_on: postgres (service_healthy)`.
* `backend` service: `EXPERT_SYSTEM_URL/TIMEOUT/CONNECT_TIMEOUT/API_KEY`, and
  `depends_on: expert-system`.
* Port still bound to `127.0.0.1:8001` for development; documented to remove in
  production.

---

## 9. Test results

### Python (pytest)

Command: `expert-system\.venv\Scripts\python -m pytest -q`

```
37 passed, 1 warning in 0.33s
```

(The single warning is a third-party Starlette deprecation notice about
`httpx`; not caused by this change.)

Coverage of the required cases: single-condition, AND, OR, present, absent,
`expected_value`, priority ordering, inactive rule, inactive disease, inactive
symptom, rule without valid conditions, unknown operator, no match, duplicate
IDs, invalid IDs, empty body, empty list, too-many symptoms, readiness DB
failure, evaluate DB failure (no internal leakage), an unconfigured
`DATABASE_URL` (clean `503`), and API-key enforcement.

### Laravel (PHPUnit)

Commands:
* `php artisan test --filter=ExpertSystemTest` → `{"result":"passed","tests":9,"passed":9,"assertions":24}`
* `php artisan test` → `{"result":"passed","tests":11,"passed":11,"assertions":26}`

The 9 new tests cover: Doctor allowed, unauthorized roles (`Nurse`,
`Receptionist`, `Patient`, `Pharmacist`) forbidden, unauthenticated → 401,
empty/nonexistent/inactive symptom rejection before forwarding, connection
failure → 503 (no `cURL`/`timed out` leakage), invalid upstream response → 502,
upstream error → 502 without leaking details.

The 2 pre-existing tests still pass (described as `tests`:11).

---

## 10. Exact commands executed

```powershell
# Inspection
git status ; git branch --show-current ; git log --oneline -10
docker ps -a ; docker volume ls
docker exec clinic_postgres psql -U clinic_user -d clinic_db -c "\dt"
docker exec clinic_postgres psql -U clinic_user -d clinic_db -c "SELECT count(*) FROM symptoms; ..."

# Python environment
py -3.13 -m venv expert-system\.venv
expert-system\.venv\Scripts\python -m pip install "fastapi==0.143.0" "uvicorn[standard]==0.38.0" "SQLAlchemy==2.1.4" "psycopg[binary]==3.3.6" pytest httpx

# Python tests
# (workdir: expert-system)
.venv\Scripts\python -m pytest -q

# Laravel baseline + new tests
# (workdir: backend)
php artisan test
php artisan test --filter=ExpertSystemTest

# Docker
docker compose config --quiet
docker compose build expert-system
docker compose up -d expert-system
docker inspect --format "{{json .State.Health}}" clinic_expert_system

# Live checks
Invoke-WebRequest http://127.0.0.1:8001/health
Invoke-WebRequest http://127.0.0.1:8001/ready
Invoke-WebRequest http://127.0.0.1:8001/evaluate -Method POST ... -Body '{"symptom_ids":[9,13,31]}'
Invoke-RestMethod  http://127.0.0.1:8000/api/v1/auth/login ...
Invoke-WebRequest  http://127.0.0.1:8000/api/v1/expert-system/evaluate -Headers @{Authorization="Bearer ..."}

# Security
composer audit --no-interaction
expert-system\.venv\Scripts\python -m pip_audit -r expert-system\requirements.txt
expert-system\.venv\Scripts\python -m bandit -r expert-system\app
```

---

## 11. Security checks performed and results

| Check | Tool | Result |
|---|---|---|
| Python dependency vulnerabilities | `pip-audit` 2.10.1 | **No known vulnerabilities found** |
| Python static analysis | `bandit` 1.9.4 | **0 issues** (all severities/confidences) |
| PHP dependency advisories | `composer audit` | **No security vulnerability advisories found** |
| Container base-image CVE scan | `docker scout cves` on the built image | **0 critical, 1 high** (`zlib` `CVE-2026-85091`, **no upstream fix yet**) |

Repository secret hygiene: the root `.env` (which contains
`POSTGRES_PASSWORD`) was **not** ignored by git and could have been committed.
A root `.gitignore` was added so `.env` / `.env.*` are ignored while
`.env.example` files stay tracked; `backend/.env` was already ignored. The
`.env.example` files contain placeholders only (`change_me`, empty API key) and
no real secrets.

Manual review (documented in `expert-system/README.md` §12):

* Parameterised SQL only (`expanded bindparams`); no string interpolation.
* No permissive CORS.
* `/evaluate` internal; optional shared-secret header; port loopback-bound in
  dev and unpublished in production.
* Error handlers/logging avoid SQL, credentials, stack traces, and patient data.
* Non-root container; libpq connect/statement timeouts; no hardcoded secrets.

**Not run / not available:** no external penetration test, no separate PHP SAST
beyond Composer audit. These are reported as NOT RUN rather than claimed clean.

---

## 12. Build, startup, health, readiness results

* `docker compose build expert-system` → **built successfully**.
* `docker compose up -d expert-system` → container **started**; `docker compose
  ps` shows `clinic_expert_system ... Up ... (healthy)`.
* `docker inspect ... .State.Health` → `"Status":"healthy"`, `FailingStreak:0`.
* `docker exec clinic_expert_system whoami` → `appuser` (non-root).
* `/health` → 200; `/ready` → 200 `database: ok`; `/evaluate` → 200 with a real
  match.
* Structured JSON logs observed on stdout (request metadata only).
* Docker DNS `postgres` resolves (readiness succeeds) and `expert-system`
  resolves from the backend (evaluate succeeds).
* Deployed artefact parity: SHA-256 of `/app/app/main.py` and `/app/app/engine.py`
  inside the running container are **identical** to the verified local sources,
  confirming the running service matches the tested code.

---

## 13. Known limitations and outstanding issues

1. **Clinical validity not established** — rule matching and `confidence_score`
   are not clinically validated and are not a probability.
2. Knowledge-base indexes on `expert_rule_conditions(expert_rule_id)` are not
   added; acceptable at the current KB size but a scalability consideration.
3. No application-level per-request timeout beyond DB statement timeouts.
4. No evaluation audit trail / persistence of results.
5. Container image inherits `zlib` `CVE-2026-85091` (high) from the Debian base;
   upstream has **no fix** at report time. Monitor the base image; when a fixed
   base tag is available, rebuild (or consider an alternative base image).
6. Production hardening not performed in this environment: removing the
   published port, TLS termination, real secret provisioning, and an external
   penetration test.
7. `python` command alias is absent on this host; `py -3.13` was used locally
   (the container uses Python 3.12).

---

## 14. Production deployment checklist

- [ ] Set a strong `EXPERT_SYSTEM_API_KEY` for both backend and expert system.
- [ ] Remove the `expert-system` `ports:` mapping (private network only).
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, real `DATABASE_URL`.
- [ ] Terminate TLS at a reverse proxy in front of the Laravel API.
- [ ] Run `composer audit`, `pip-audit`, and a container image scanner in CI.
- [ ] Add resource limits and a request timeout at the proxy.
- [ ] Confirm log aggregation receives the structured JSON logs.
- [ ] Run DB migrations normally (never `migrate:fresh`/`db:wipe`/`down -v`).
- [ ] Complete clinical review of the knowledge base (see §15).

---

## 15. Clinical validation requirements

The following remain outstanding and require qualified professionals; they are
**not** satisfied by software correctness or passing tests:

* Clinical review and sign-off of every symptom, disease, rule, operator, and
  `expected_value`.
* Evidence-based justification for rule priorities and removal of the
  `confidence_score` field or its rigorous validation as a probability.
* Prospective/retrospective validation against real clinical data with
  sensitivity/specificity/accuracy reporting, governance, and versioning.
* Regulatory assessment (e.g. medical-device classification) under the
  applicable jurisdiction.
* Safety review for false negatives, especially for severe conditions.

---

## 16. Final status classification

| Area | Status | Evidence |
|---|---|---|
| Python service implementation | **PASS** | Code implemented; builds and runs. |
| Inference semantics | **PASS** | 36 pytest cases; live real-DB match. |
| FastAPI endpoints (`/`, `/health`, `/ready`, `/evaluate`) | **PASS** | Live 200/422 responses recorded. |
| Laravel integration & authorization | **PASS** | 9 feature tests; live e2e 200 and 401. |
| Automated tests (Python + Laravel) | **PASS** | 37 + 11 passed, 0 failed. |
| Database schema compatibility | **PASS** | Queries match migration columns; no schema change. |
| Docker build/run/health | **PASS** | Built, healthy, non-root, readiness ok. |
| Dependency security scans | **PASS** | pip-audit, bandit, composer audit clean. |
| External penetration test | **NOT RUN** | Tools/authorisation not available in scope. |
| Container image CVE scan | **PASS** | `docker scout cves`: 0 critical, 1 high (no fix). |
| Clinical validation | **NOT DONE** | Requires qualified clinicians/regulatory review. |

### Completion scoring (transparent)

Software-implementation acceptance criteria were defined as: (a) implementation
completeness, (b) automated test completion for the agreed scope, (c)
integration completeness, (d) security review completion, (e) operational
readiness for the development/agreed scope. Clinical validation is scored
separately and is **0%**.

| Category | Weight | Earned | Rationale |
|---|---:|---:|---|
| Implementation completeness | 25 | 25 | All required modules/endpoints/integration delivered. |
| Automated test completion | 20 | 20 | All listed unit/API/integration cases automated and passing. |
| Integration completeness | 20 | 20 | Covered by tests + live e2e for all five roles. |
| Security review completion | 20 | 18 | Scans clean + manual review + image CVE scan; no external pentest. |
| Operational readiness | 15 | 10 | Dev verified; production port/TLS/secret hardening pending. |
| **Software scope total** | **100** | **93** | ~93% for the agreed software scope. |
| Clinical knowledge validation | — | **0%** | Not performed; requires clinicians. |

**Overall:** software implementation for the agreed development scope is
**~93% complete and verified**; **clinical validation and regulatory approval
remain 0% and outstanding**, and are required before any real-world clinical
use.

---

## 17. Addendum — Knowledge Base Starter (100 diseases)

Requested next: complete the bilingual (English/Khmer) starter catalog to the
requested **100 diseases**.

### Delivered (software scope)
* `backend/database/data/knowledge_base_100/` — reproducible starter package:
  `diseases_100.csv` (KB-001..KB-100), `symptoms_dictionary.csv`
  (SYM-001..SYM-060), mapping/rule **templates** (empty by design), `sources.json`,
  `manifest.json`, `README.md`, `knowledge_base_starter.json`, and
  `generate_knowledge_base.py` (port of the supplied builder into the repo).
* Migrations: `2026_10_10_100000_add_khmer_catalog_fields_to_diseases_and_symptoms.php`
  — additive nullable `name_km` / `category` columns (guarded); engine columns
  untouched.
* Seeder: `KnowledgeBase100CatalogSeeder` — idempotent `updateOrCreate()` keyed
  on `code`, inserts catalog records with **`is_active=false`**, never modifies
  existing active `DIS-*` / `SYM-*` records, and **creates no mappings or
  rules**.
* Live state: `diseases` = 20 active + 100 inactive = **120**;
  `symptoms` = 63 active + 60 inactive = **123**. Spot-checked `KB-001`
  (respiratory) and `KB-100` (additional_common_conditions) carry Khmer + category.

### Verification
* `php artisan test` → **11 passed / 26 assertions** (migration runs in
  RefreshDatabase tests). `pytest` → **37 passed**.
* Live `/evaluate [9,13,31]` → still HTTP 200, `RULE-FLU-001` only (inactive
  catalog rows are correctly excluded).

### Clinical status (NOT DONE — unchanged)
The 100 catalog entries are structure-only. ICD-11 codes are blank, mappings
and rules are intentionally empty, and `clinical_use_enabled=false` /
`catalog_needs_review` applies to every record. **No diagnosis may be derived
from this catalog.** Known rough spots to reconcile during clinical review:
duplicate symptom concepts between the active vocab and the catalog
(e.g. Fever/Cough/Rash), the `Tonsillitis` Khmer term, and the
`Tension headache` / `Tension-type headache` pair.

---

## 18. Addendum — Python-only completion pass

A focused, Python-only completion/verification pass was performed (no frontend,
no Laravel, no schema changes). All changes are under `expert-system/`.

### Verified (no change needed)
* All eight service modules present and free of `TODO`/`FIXME` markers.
* `pytest` → **37 passed**; `bandit -q -r app` → **0 issues**; `pip-audit -r
  requirements.txt` → **no known vulnerabilities**.
* Live Docker stack healthy; service runs as non-root `appuser`.
* Live knowledge base confirmed: **120** diseases (20 active + 100 inactive),
  **123** symptoms (63 active + 60 inactive), **20** active rules, **117**
  conditions; `/evaluate [9,13,31]` still returns `RULE-FLU-001`.

### Gaps closed
1. **`expert-system/.env.example`** added — documented sample of every runtime
   variable, placeholders only.
2. **Missing `DATABASE_URL` now returns a clean `503`** instead of an unhandled
   `500`. `database.get_engine()` raises the new typed
   `DatabaseConfigurationError`, handled by an exception handler in `main.py`
   (`{"detail":"Database is not configured."}`, no stack trace). A regression
   test (`test_missing_database_url_returns_clean_503`) covers both `/ready` and
   `/evaluate`. README §11 corrected (the condition is first-request, not
   startup).
3. **CI workflow** `.github/workflows/expert-system-ci.yml` runs the test suite,
   `bandit`, and `pip-audit` for changes under `expert-system/`. `bandit` and
   `pip-audit` were added to `requirements-dev.txt` for reproducibility.

### Status
Software scope remains verified and passing. Clinical validation remains
**NOT DONE / 0%** and is unchanged.

