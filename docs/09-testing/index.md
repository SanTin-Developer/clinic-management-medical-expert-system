# 09 · Testing

## 9.1 Test Strategy

The project uses three complementary checks:

| Layer | Tool | Location | Purpose |
| --- | --- | --- | --- |
| **Backend** | PHPUnit `^12.5.12` (Laravel test runner) | `backend/tests/` | Feature and unit coverage of API behavior, authorization, and domain rules |
| **Expert system** | pytest 8.4.2 + httpx 0.28.1 | `expert-system/tests/` | Engine matching, API contracts, configuration, audit behavior |
| **Frontend** | Oxlint + Vite build | `frontend/`, `website/` | Static checks and production build integrity |

There is no browser end-to-end suite (Playwright/Cypress) in the repository.
Frontend verification is lint plus build; API behavior is covered by PHPUnit.

## 9.2 Backend Test Suite

### 9.2.1 Environment

`backend/phpunit.xml` forces a deterministic, isolated environment:

| Setting | Value |
| --- | --- |
| `APP_ENV` | `testing` |
| `DB_CONNECTION` | `sqlite` (forced) |
| `DB_DATABASE` | `:memory:` (forced) |
| `CACHE_STORE` | `array` |
| `QUEUE_CONNECTION` | `sync` |
| `SESSION_DRIVER` | `array` |
| `MAIL_MAILER` | `array` |
| `BCRYPT_ROUNDS` | `4` |
| `BROADCAST_CONNECTION` | `null` |
| `TELEGRAM_ENABLED` | `false` |
| `ANNOUNCEMENT_POSTER_DISK` | `public` (forced) |
| `PULSE_ENABLED`, `TELESCOPE_ENABLED`, `NIGHTWATCH_ENABLED` | `false` |

Consequences:

- Tests never touch PostgreSQL, Redis, SMTP, Telegram, or a real filesystem
  queue.
- Each test runs against a fresh in-memory schema created by migrations.
- SQLite is a compatibility target for schema and query logic; PostgreSQL-specific
  behavior is not exercised by the suite.

The shared helper `backend/tests/Concerns/CreatesUsers.php` provisions users with
roles and permissions for authorization tests.

### 9.2.2 Commands

```powershell
cd backend

# Whole suite (clears config first)
composer test

# Or directly
php artisan test

# A single file / filter
php artisan test tests/Feature/PharmacyTest.php
php artisan test --filter=PharmacyPos

# Formatting check
./vendor/bin/pint --test
```

`composer test` runs `php artisan config:clear` before `php artisan test`.

### 9.2.3 Test inventory

The suite contains **69 PHP files** under `backend/tests/` (64 feature tests,
3 unit tests, 1 trait, and `TestCase.php`). Feature coverage is grouped below by
domain.

| Domain | Representative test files |
| --- | --- |
| **Authentication** | `AuthTest`, `AuthOtpTest`, `TwoFactorAuthenticationTest`, `PasswordResetTest`, `EmailVerificationTest`, `AccountSecurityTest`, `SessionPolicyTest`, `SecurityHardeningTest`, `SecurityHeadersTest` |
| **Account lifecycle** | `AccountLifecycleTest`, `StaffProvisioningTest`, `StaffApprovalTest`, `UserTest` |
| **Patients and documents** | `PatientTest`, `DocumentTest`, `FileSecurityTest`, `SoftDeleteTest` |
| **Appointments and queue** | `AppointmentTest`, `QueueEntryTest` |
| **Clinical records** | `ConsultationTest`, `DiagnosisTest`, `MedicalRecordTest`, `VitalSignTest` |
| **Prescriptions and pharmacy** | `PrescriptionTest`, `PharmacyTest`, `PharmacyHardeningTest`, `BillingPriceAuthorityTest` |
| **Billing and reports** | `BillingTest`, `ReportTest`, `ReportExportTest`, `DashboardAnalyticsTest` |
| **Inpatient and nursing** | `AdmissionWorkflowTest`, `InpatientBillingTest`, `NursingTaskTest`, `NursingTaskCompletionTest`, `MedicationAdministrationTest`, `MedicationAdministrationWorkflowTest`, `NurseAssignmentManagementTest`, `NursePatientIsolationTest` |
| **Staff and HR** | `StaffDirectoryTest`, `AttendanceRecordTest`, `AttendanceSessionTest`, `AttendanceFinalizationTest`, `ShiftTemplateTest`, `ShiftTest`, `ShiftSchedulingTest`, `LeaveRequestTest` |
| **Communication** | `ConversationTest`, `AnnouncementTest`, `NewsTest`, `NotificationApiTest`, `TelegramTest`, `SystemAlertTest` |
| **Website CMS and public site** | `WebsiteManagementTest`, `WebsitePublicTest`, `WebsiteContactMessageTest` |
| **Expert system** | `ExpertSystemTest`, `KnowledgeBaseTest` |
| **Audit and search** | `ActivityLogTest`, `AuditLogTest`, `SearchTest` |
| **Platform** | `ApiDocsTest`, `PerformanceLimitsTest`, `ExampleTest` |
| **Unit** | `TotpServiceTest`, `TelegramMessagesTest`, `ExampleTest` |

Notable behavioral areas asserted by the suite:

- Role/permission enforcement, including inpatient-billing isolation from legacy
  `billing.*` permissions.
- Nurse–patient isolation and assignment rules.
- POS server-side pricing authority, idempotency, stock allocation, and
  prescription-only blocking.
- File access hardening and security headers.
- Session policy and account lifecycle transitions.
- Dashboard analytics views and report exports.
- Website publish/unpublish and public-content boundaries.
- Expert-system proxy validation, error mapping, and audit toggling.

### 9.2.4 Recorded result

Last recorded in this working copy on 2026-10-29:

| Check | Result |
| --- | --- |
| Backend test suite | **608 tests passed** |

The suite was **not** re-run while this documentation set was written. Re-run it
after any code change; treat the number above as a snapshot, not a guarantee.

### 9.2.5 Known backend test gaps

- SQLite does not exercise PostgreSQL-specific types, indexes, or locking
  semantics. Transaction and `lockForUpdate()` paths are covered functionally but
  not under real PostgreSQL concurrency.
- No load, soak, or concurrency benchmark suite is present.
- No automated accessibility or visual regression tests.
- The Laravel backend has no CI workflow; tests are run locally or by the
  developer/operator.

## 9.3 Expert-System Test Suite

### 9.3.1 Environment

`expert-system/tests/conftest.py` sets:

- `DATABASE_URL=sqlite://` before importing the app.
- `LOG_LEVEL=WARNING`.
- `EXPERT_SYSTEM_API_KEY` removed, so most tests run without a key.
- A fresh in-memory SQLite schema per test, with seeded symptoms, diseases,
  rules, conditions, and audits.
- A FastAPI `TestClient` with the DB dependency overridden.
- Settings and rule caches cleared around every test.

The fixture knowledge base deliberately includes active and inactive symptoms,
diseases, rules, AND/OR groups, `absent` and `expected_value` conditions,
ties, unknown operators, and condition-less rules.

### 9.3.2 Commands

```powershell
cd expert-system

# Create a virtual environment once
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements-dev.txt

# Run tests
python -m pytest -q

# CI-equivalent checks
python -m bandit -q -r app
python -m pip_audit -r requirements.txt
```

### 9.3.3 Inventory

| File | Focus |
| --- | --- |
| `tests/test_api.py` | `/health`, `/ready`, `/evaluate`, API-key enforcement, validation, error codes, empty matches |
| `tests/test_engine.py` | AND/OR groups, `present`/`absent`/`expected_value`, inactive rule/disease/symptom exclusion, priority ordering, tie-breaking, rule cache |

### 9.3.4 Recorded result

Last recorded in this working copy on 2026-10-29:

| Check | Result |
| --- | --- |
| Expert-system suite | **45 tests passed** |

The suite was not re-run during documentation edits. A Starlette/httpx
deprecation warning is emitted and is non-blocking.

## 9.4 Frontend Checks

```powershell
cd frontend
npm run lint
npm run build

cd ..\website
npm run lint
npm run build
```

Last recorded in this working copy on 2026-10-29:

| Check | Result |
| --- | --- |
| Admin lint | Exit `0`; React Compiler warnings only |
| Admin build | Passed |
| Website lint | Exit `0`; warnings only |
| Website build | Passed |

Warnings are advisory. `npm run build` does not run a type checker because both
apps are JavaScript/JSX.

## 9.5 Continuous Integration

Only one workflow exists: `.github/workflows/expert-system-ci.yml`.

| Property | Value |
| --- | --- |
| Trigger | Push to `master`/`main` and pull requests, limited to `expert-system/**` and the workflow file |
| Runner | `ubuntu-latest` |
| Python | `3.12` |
| Steps | Install `requirements-dev.txt` → `pytest -q` (SQLite) → Bandit → pip-audit |
| Permissions | `contents: read` |

There is **no** CI job for the Laravel backend, the admin SPA, or the public
website. Add them before relying on CI as a merge gate.

## 9.6 Quality Gates and Conventions

| Gate | Command | Blocking? |
| --- | --- | --- |
| Backend tests | `composer test` | Yes, by team convention |
| Backend formatting | `./vendor/bin/pint --test` | Yes, by team convention |
| Expert tests | `python -m pytest -q` | Yes |
| Expert static analysis | `python -m bandit -q -r app` | Yes, in CI |
| Expert dependency audit | `python -m pip_audit -r requirements.txt` | Yes, in CI |
| SPA lint | `npm run lint` | Advisory; warnings do not fail |
| SPA build | `npm run build` | Yes |

Additional conventions:

- Bug fixes should add or extend a failing test before the fix.
- Permission-sensitive endpoints should have a negative test for an
  unauthorized role.
- Financial and stock mutations should assert both the resulting state and the
  rejection path.
- Tests must not depend on external services or the developer's `.env`.

## 9.7 Manual Verification Checklist

Before a release, verify in a non-production environment:

- [ ] `docker compose config --quiet` succeeds.
- [ ] All containers report healthy.
- [ ] `GET /up` returns `200`.
- [ ] `GET /api/v1/website/bootstrap` returns `200`.
- [ ] Login succeeds for an active account; a wrong password is rate-limited.
- [ ] An unauthorized role receives `403` on a protected route.
- [ ] POS checkout creates one sale; repeating the idempotency key does not
      create a second.
- [ ] Expert-system `GET /health` returns `200`, and evaluation returns matches.
- [ ] Invoice PDF downloads.
- [ ] A CMS page can be published and appears on the public site.
- [ ] A backup can be restored into a scratch database.

## 9.8 Troubleshooting Quick Reference

### 9.8.1 Logs

```powershell
docker compose logs --tail=100 backend
docker compose logs --tail=100 expert-system
docker compose logs --tail=100 postgres
docker compose logs -f backend
Get-Content backend\storage\logs\laravel.log -Tail 100
```

Never paste logs containing tokens, passwords, patient data, or full request
bodies into public issues.

### 9.8.2 Docker and Compose

| Symptom | Cause | Fix |
| --- | --- | --- |
| `docker: command not found` | Docker not installed or not running | Start Docker Desktop, reopen the shell |
| `port is already allocated` | Host port in use | Change `BACKEND_PORT`, `FRONTEND_PORT`, `WEBSITE_PORT`, `REVERB_PORT`, or `POSTGRES_PORT` |
| Container exits immediately | Build or startup error | `docker compose logs <service>` |
| `expert-system` is unhealthy | Database unreachable or app failed to start | Check logs, `DATABASE_URL`, and `postgres` health |
| Compose warns about the override file | Development override is present | Expected in development; do not deploy it |

### 9.8.3 Backend

| Symptom | Cause | Fix |
| --- | --- | --- |
| `No application encryption key has been specified` | Empty `APP_KEY` | `docker compose exec backend php artisan key:generate` |
| Database connection refused | Wrong host, port, or credentials | Use `DB_HOST=postgres` inside Compose; check root `.env` |
| `could not find driver` | Missing `pdo_pgsql` | Rebuild the backend image |
| `500` with permission errors | `storage` or `bootstrap/cache` not writable | Restart backend; the startup script fixes permissions |
| `419` or CSRF errors | Mixing session and bearer-token flows | Use the bearer-token flow; check Sanctum/CORS |
| CORS error in the browser | Origin missing from `CORS_ALLOWED_ORIGINS` | Add the exact origin, clear config, restart |
| `401` on every request | Missing or expired token | Sign in again; check `SANCTUM_EXPIRATION` |
| `403` for an admin action | Missing permission or inactive account | Check role/permissions and account status |
| Upload returns `413` | File too large or proxy limit | Check `DOCUMENTS_MAX_SIZE_KB` and nginx `client_max_body_size` |
| Upload rejected | Extension or MIME not allowed | Use an allowed type |
| `SQLSTATE[42P01]` / missing relation | Migration not applied | Run `migrate:status`; apply migrations safely |
| Queue jobs do not run | Worker or Redis problem | Check backend logs and Redis health |
| Realtime chat does not update | Reverb or `VITE_REVERB_*` mismatch | Check the Reverb process, port `8080`, and matching keys |
| Telegram does nothing | Bot disabled or wrong delivery mode | Check `TELEGRAM_*`; use webhook or polling, not both |
| Expert call returns `503` | Python service unavailable | `docker compose ps`; check `EXPERT_SYSTEM_URL` and logs |
| Expert call returns `502` | Python returned non-2xx or invalid JSON | Check Python logs and the internal API key |

### 9.8.4 Frontend

| Symptom | Cause | Fix |
| --- | --- | --- |
| Blank page after deployment | SPA fallback missing | Serve `index.html` for unknown paths |
| API calls go to the wrong host | `VITE_API_URL` baked in at build time | Set it before `npm run build`, then rebuild |
| Login succeeds but API calls return `401` | Token not stored or sent | Check browser storage and the authorization header |
| Old content after changing `.env` | Vite variables are build-time | Rebuild |
| `npm install` fails | Wrong Node version or network issue | Use Node.js 22; retry with a clean install |
| Lint reports warnings but exits `0` | Warnings only | No action required |

### 9.8.5 Expert system

| Symptom | Cause | Fix |
| --- | --- | --- |
| `/ready` returns `503` | `DATABASE_URL` unset or PostgreSQL unreachable | Set the URL and check `postgres` |
| `/evaluate` returns `401` | Shared key configured but header missing/wrong | Send `X-Internal-Api-Key` |
| `/evaluate` returns `422` | Unknown/inactive symptom IDs or invalid body | Send active symptom IDs |
| `/evaluate` returns `503` | Database or engine failure | Check logs and database |
| `/evaluate` times out | Request timeout exceeded | Check database performance and `EXPERT_SYSTEM_REQUEST_TIMEOUT_SECONDS` |
| Stale rule results | In-process rule cache | Wait for the TTL or set `EXPERT_RULES_CACHE_TTL_SECONDS=0` |

### 9.8.6 Historical log entries

`backend/storage/logs/laravel.log` may contain:

```text
SQLSTATE[42703]: Undefined column: 7 ERROR: column "title_km" does not exist
```

These entries predate the Khmer-fields migration. Confirm the migration is
applied and the timestamp is old before treating it as current.

`testing.ERROR` entries can come from tests that intentionally simulate
failures; check the test name and timestamp first.

### 9.8.7 Reporting a problem

Include the failing command or action, the expected and actual result, the
relevant log excerpt with secrets and patient data removed, the application and
tool versions, and whether it happens locally, in CI, or in a deployment.
