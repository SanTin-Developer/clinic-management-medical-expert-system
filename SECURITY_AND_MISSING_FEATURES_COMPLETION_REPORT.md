# Security & Missing Features — Completion Report

Project: `clinic-management-medical-expert-system`
Report date: 2026-10-11
Scope: Laravel backend security hardening, missing application features, and API documentation.
Associate report: `PYTHON_EXPERT_SYSTEM_COMPLETION_REPORT.md` (inference engine & integration).

---

## 1. Executive summary

The previously missing security and application features were implemented,
tested, and verified against the running stack:

* **Device-trusted login OTP (2FA):** email one-time passcode required for
  first-time devices; trust stored in an `HttpOnly`, `SameSite=Lax`, `Secure`
  (prod) cookie with a hashed server-side token.
* **Forgot / reset password:** familiar Laravel token broker with a generic
  response, a hardened password policy, privileged-session revocation on reset,
  and a "password changed" notification.
* **Email verification:** signed-link verification, resend, and an opt-in
  `verified` middleware; bulletproof frontend URLs for the verification and
  reset links.
* **Secure document uploads:** strict allow/deny extension lists, double
  extension and archive rejection, magic-byte MIME verification against the
  declared extension, fail-closed optional ClamAV scanning, randomized storage
  names, and a storage abstraction (local disk, S3-compatible, Cloudinary).
* **Session policy:** Sanctum bearer tokens expire after a configurable window
  (default 8 hours); cookies/session short-lived.
* **PDF report export** for appointments, revenue, and inventory reports.
* **OpenAPI documentation** generated from routes (`/docs/api`, `/docs/api.json`),
  restricted to the local environment by default.

Backend test suite: **124 passed / 0 failed (396 assertions)**.
`composer audit`: no advisories.

Out of scope / deliberately skipped in this pass:

* **Frontend UI** for the new auth flows was skipped at the user's request.
* **Clinical validity** of the knowledge base is NOT established.
* **Email delivery (Brevo), Cloudinary uploads, and ClamAV scanning** are
  implemented but require credentials/infrastructure that are not yet wired in
  (see sections 6, 7, 8).

---

## 2. Feature flags (opt-in, safe defaults)

Everything that could lock out existing accounts is **off by default** so the
seeded/demo users (marked `email_verified_at = now()`) keep working:

| Env | Default | Effect |
| --- | --- | --- |
| `AUTH_OTP_ENABLED` | `false` | Require an email OTP when logging in from a new device |
| `AUTH_REQUIRE_VERIFIED_EMAIL` | `false` | Add the `verified` middleware to protected routes |
| `AUTH_ENFORCE_TRUSTED_DEVICE` | `false` | Require the trusted-device cookie on every API call |
| `SANCTUM_EXPIRATION` | `480` (min) | Personal access token lifetime |
| `SESSION_LIFETIME` | `480` (min) | Legacy session/cookie lifetime |

Other tunables: `AUTH_OTP_LENGTH` (6), `AUTH_OTP_EXPIRE_MINUTES` (10),
`AUTH_OTP_MAX_ATTEMPTS` (5), `AUTH_OTP_RESEND_COOLDOWN` (60 s),
`AUTH_TRUSTED_DEVICE_DAYS` (30), `AUTH_DEVICE_COOKIE_NAME`,
`AUTH_PASSWORD_MIN` (8), `DOCUMENTS_DISK`, `DOCUMENTS_MAX_SIZE_KB`,
`FILE_SCANNER_DRIVER` (`null`|`clamav`), `CLAMAV_*`.
All are documented as placeholders in `backend/.env.example`.

---

## 3. OTP / 2FA login

* **Database** (`migrations/2026_10_13_0000{1,2}_*.php`, applied to the live
  PostgreSQL database):
  * `trusted_devices` — user_id, unique `device_id`, `token_hash`, name,
    `ip_address`, `user_agent`, `last_used_at`, `expires_at`.
  * `login_challenges` — unique `challenge_token`, `code_hash`, `attempts`,
    `max_attempts`, `ip_address`, `user_agent`, `expires_at`, `consumed_at`,
    `last_sent_at`.
* **Flow** (`AuthController`, `LoginChallengeService`, `TrustedDeviceService`):
  1. `POST /api/v1/auth/login`+ valid credentials from a device with no valid
     trusted-device cookie returns `202` with `otp_required: true` and a
     `challenge_token`. No token is issued.
  2. The 6-digit code is emailed (hashed in the DB, single-use, 10 min expiry,
     5 max attempts, 60 s resend cooldown).
  3. `POST /api/v1/auth/otp/verify` accepts `challenge_token`, `code`,
     `remember_device`; on success issues the Sanctum token and, unless opted
     out, rotates in a trusted-device token.
  4. `POST /api/v1/auth/otp/resend` regenerates the code (cooldown enforced).
* **Trusted devices:** `GET /api/v1/auth/devices` (flags the current device),
  `DELETE /api/v1/auth/devices/{device}`, `DELETE /api/v1/auth/devices`.
  Logout clears the cookie; password reset and `logout` revoke everything.
* **Middleware:** `EnsureTrustedDevice` (alias `device.trust`) can require the
  trusted-device cookie on every call, mitigating bearer-token replay from an
  unknown device.
* **Protection:** `/auth/login` (`throttle:login` 5/min per email+IP),
  `/auth/otp/*` (`throttle:otp` 5/min per challenge+IP),
  `/auth/forgot-password` and `/auth/reset-password` (`throttle:password-reset`
  5/min per email+IP), plus the general 60/min `throttle:api` for everything
  else.

## 4. Password reset & email verification

* `POST /api/v1/auth/forgot-password` — generic response (no enumeration); uses
  the standard broker so token expiry/throttle are enforced.
* `POST /api/v1/auth/reset-password` — validates `token`/`email`/`password`
  (min length + letters + numbers), then: updates the password, deletes all
  Sanctum tokens **and** trusted devices (same privilege revocation as logout),
  and notifies via `PasswordChangedNotification`.
* `GET /api/v1/auth/email/verify/{id}/{hash}` — `signed` + `throttle:api`,
  `timingSafeCompare` hash check, idempotent.
* `POST /api/v1/auth/email/verification-notification` — resend, auth required.
* Notification **URLs point at the SPA** (`FRONTEND_URL`): `VerifyEmail` and
  `ResetPassword ::createUrlUsing` build `{frontend}/verify-email?verify_url=…`
  and `{frontend}/reset-password?token=…&email=…`.

## 5. Secure document uploads

Layer stack in `app/Services/Files/*`:

* `PatientDocumentService` — orchestrates validation, scanning, storage.
* `FileScanner` / `NullFileScanner` / `ClamAvFileScanner` — scan abstraction;
  `ClamAvFileScanner` streams via the INSTREAM protocol over TCP (no PHP
  extension required). **Fail closed**: if a scan errors and
  `documents.scanner.allow_on_error` is not true, the upload is rejected.
* `DocumentStorage` / `DiskDocumentStorage` / `CloudinaryStorage` — storage
  abstraction selected by `DOCUMENTS_DISK` (`local`, any S3 disk, or
  `cloudinary`); stored names are UUIDs.
* **Validation** (`DocumentController` + service):
  * size limit (`DOCUMENTS_MAX_SIZE_KB`, default 10 MB),
  * allow-list of extensions and MIME types,
  * deny-list covering executables/scripts/HTML/SVG and **all archives**
    include `.zip`, `.gz`, `.tgz`, `.rar`, `.7z`, `.tar`, `.bz2`, `.xz`,
  * double-extension detection (`invoice.pdf.exe` rejected),
  * path-traversal/null-byte sanitisation,
  * **magic-byte MIME sniffing** (`finfo`) must match the declared extension for
    pdf/jpg/png/webp/doc/docx; known-dangerous signatures are rejected.
* Endpoints: `GET|POST /patients/{patient}/documents`,
  `GET /documents/{document}/download`, `DELETE /documents/{document}` — all
  behind the existing `documents.view`/`documents.manage`/policy layer.

## 6. Storage: Cloudinary (implemented, needs secret)

* `config/services.php` has a `cloudinary.*` block; `CloudinaryStorage` +
  `CloudinaryClient` upload, destroy, and return secure delivery URLs with a
  configurable folder (`CLOUDINARY_FOLDER`).
* **Not yet live:** uploads signed with the API secret could not be tested or
  enabled because `CLOUDINARY_API_SECRET` is not configured. Until it is set,
  keep `DOCUMENTS_DISK=local`.

## 7. Email: Brevo SMTP (implemented, needs account login)

* `MAIL_MAILER=smtp`, `MAIL_HOST=smtp-relay.brevo.com`, `MAIL_PORT=587`,
  `MAIL_PASSWORD` are set in `backend/.env` (gitignored).
* **Not yet live:** the Brevo account **login email** (`MAIL_USERNAME`) is
  still unknown, so real delivery is unverified. Notifications are covered by
  the test suite with `MAIL_MAILER=array`.

## 8. ClamAV (implemented, needs a clamd service)

* `ClamAvFileScanner` is dependency-free and ready; switching
  `FILE_SCANNER_DRIVER=clamav` and adding a `clamd` service to
  `docker-compose.yml` (infra change, requires approval) enables it.
  Until then `FILE_SCANNER_DRIVER=null` is the safe default for dev/test but
  does **no** antivirus scanning in production.

## 9. Reporting & export

* `ReportController::export` → CSV (existing) and new `ReportController::pdf`
  (`GET /api/v1/reports/{report}/pdf` for `appointments`, `revenue`,
  `inventory`), rendered from `resources/views/reports/pdf.blade.php` via
  `barryvdh/laravel-dompdf`, A4, `application/pdf` download. Both share the same
  report-row builders so JSON, CSV, and PDF stay consistent.
* PDF generation is memory-heavy: `phpunit.xml` raises the test limit to 512M;
  production PHP should allow at least 256M (reports here are small).

## 10. OpenAPI documentation

* `dedoc/scramble` generates the spec from the actual routes.
  * UI: `/docs/api`, JSON: `/docs/api.json` (`openapi 3.1`).
  * `config/scramble.php`: title/version/description, `API_VERSION`,
    bearer `security_strategy` so protected routes are marked authenticated.
  * **Access control:** `RestrictedDocsAccess` allows only the `local`
    environment by default; outside local, a `viewApiDocs` Gate requires an
    authenticated **Admin** (all other users and guests get `403`). Covered by
    `tests/Feature/ApiDocsTest.php`.

## 11. Verification

* **Live DB:** the two new migrations were applied to the running PostgreSQL
  (`trusted_devices`, `login_challenges`). Existing data untouched
  (symptoms=123, diseases=120, users=5 before/after).
* **Tests** — full suite `124 passed (396 assertions)`:
  * `AuthOtpTest` — login challenge, verify issue, trusted-device round-trip,
    wrong/expired/consumed codes, resend cooldown, untrusted no-OTP-on-login.
  * `PasswordResetTest` — generic feedback, reset flows, token/device
    revocation, password policy, throttling.
  * `EmailVerificationTest` — signed verify, tamper rejection, resend.
  * `FileSecurityTest` — allowed/rejected files, double extensions, magic-byte
    mismatch, size limit, fail-closed scanner, storage isolation.
  * `SessionPolicyTest` — 8-hour token expiry (401 after 9h).
  * `ReportExportTest` (+9) — CSV **and** PDF for each report, date ranges,
    404s, auth denials.
  * `ApiDocsTest` — spec present locally; forbidden default outside local;
    admin-only via gate.
  * `SecurityHeadersTest` — baseline headers on API/HTML responses.
  * `PerformanceLimitsTest` — query budgets for list + report endpoints, and
    warm report calls served from cache (no N+1 / dropped-index regressions).
* **`composer audit`:** no advisories.

## 12. Hardening & performance pass (2026-10-11)

A follow-up review pass addressed the weakest scoring dimensions:

* **Security headers** — global `SecurityHeaders` middleware emits
  `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Permissions-Policy`, `Cache-Control: no-store` and production-only
  `Strict-Transport-Security`; a scoped `Content-Security-Policy` is added to
  HTML responses only (so JSON APIs are unaffected). Registered in
  `bootstrap/app.php`; covered by `SecurityHeadersTest`.
* **Encrypted cookies** — `EncryptCookies` is now prepended to the `api` group,
  so the trusted-device token is encrypted at rest (previously plaintext).
  `AuthOtpTest` round-trips the cookie exactly like a real client.
* **Auth Form Requests** — validation extracted from the controllers into
  `app/Http/Requests/Auth/*`: `LoginRequest`, `OtpVerifyRequest`,
  `OtpResendRequest`, `ForgotPasswordRequest`, `ResetPasswordRequest`
  (centralised rules, including the password policy in one place).
* **Report performance** — aggregate JSON reports are served through
  `ReportController::cachedReport()` using `Cache::flexible()` with a
  stale-within grace window (config `REPORTS_CACHE_ENABLED` /
  `REPORTS_CACHE_TTL`, default 300 s) so a single request refreshes an
  expired entry while every other request keeps serving the stale copy — no
  thundering herd on expiry. Date-filtered keys keep caches per-range, and
  CSV/PDF exports are intentionally never cached. Revenue totals /
  by-day / by-method are now aggregated in the database (`GROUP BY`) rather
  than pulling every payment row into PHP.
* **Indexes** — additive, non-destructive migrations applied to the live
  database: `2026_10_13_000003` (`trusted_devices(user_id, token_hash)`,
  `appointments(status)`, `payments(paid_at, method)`) and
  `2026_10_15_000001` (`patients(created_at)`,
  `appointments(appointment_date, status)`, `invoices(status, issued_at)`)
  so report/date-range and list-ordering queries avoid sequential scans.
* **N+1 sweep** — all 12 older controllers audited against their model
  relations: **no lazy-loading N+1 found** (every touched relation is eager
  loaded). The one loop-of-queries (`PrescriptionController::allocateStock`)
  is inherent to per-batch stock allocation and was left intentionally. The
  Python expert system already caches the knowledge base in-process
  (`EXPERT_RULES_CACHE_TTL_SECONDS` + an explicit invalidation hook).
* **Query-budget regression test** — `PerformanceLimitsTest` seeds 60
  patients / 25 medicines plus invoices and payments, then asserts the list
  and report endpoints stay within fixed query budgets and that warm report
  calls are served from cache; an accidental N+1 or a dropped index now
  fails CI instead of silently degrading.
* Suite grew from 120 → 124 tests; Pint clean.

## 13. Known limitations & follow-ups

1. **Frontend UI (React)** for OTP / reset / verify / device management is not
   built (explicitly skipped by the user). API endpoints and deep links are
   ready.
2. **Brevo** `MAIL_USERNAME` missing → set it, then confirm delivery in a live
   account.
3. **Cloudinary** `CLOUDINARY_API_SECRET` missing → set it to enable signed
   uploads (`DOCUMENTS_DISK=cloudinary`).
4. **ClamAV** → add a `clamd` service and set `FILE_SCANNER_DRIVER=clamav`
   (infra change).
5. Clinical validation of the KB and the expert system remains out of scope.
6. **PDF export** is CPU/memory heavy (`barryvdh/laravel-dompdf`, needs
   `memory_limit >= 256M` in production). It is deliberately not cached (each
   download is timestamped); move PDF generation to a queued job if it becomes
   a hot path.
7. **Laravel Boost** (the dev tooling requested by `backend/AGENTS.md`) could
   not be installed — `composer require laravel/boost --dev` timed out reaching
   `api.github.com` from the container. Retry on a machine with GitHub access;
   it is dev-only and does not affect runtime.