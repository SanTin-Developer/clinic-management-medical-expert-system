# Security Implementation & Audit Report

**Project:** RoyalCare Clinic Management (clinic-management-medical-expert-system)
**Scope:** Secure staff account provisioning, TOTP two-factor authentication, trusted devices, server-side step-up, secure password change, two-stage email change, risk-based security events, patient-data protection.
**Backend:** Laravel 13.34 / PHP 8.4.24 / PostgreSQL, Sanctum bearer tokens, spatie/laravel-permission.
**Date:** 2026-10-13
**Status:** Backend security core implemented, migrated, seeded and tested. Frontend integration and final UI polish remain (see §11).

---

## 1. Executive summary

The audit found that the platform already had a solid authentication base (Sanctum bearer tokens, email-OTP second step, trusted-device cookie, password reset by code, role/permission RBAC), but lacked the *staff account lifecycle*, *app-based two-factor authentication*, *step-up authorization*, *self-service password/email change*, and *security event logging* required by the master security prompt.

This work delivers the **backend security core** for all of those areas without introducing new Composer dependencies (TOTP is a pure-PHP RFC 6238 implementation), without destructive migrations, and without changing existing API contracts (all changes are additive). Existing deployments keep working because every new enforcement path is behind a config flag that defaults to **off**.

**Verification:** full backend test suite **270 passed / 892 assertions** (up from the 227 / 761 baseline, i.e. +43 new tests, zero regressions). Additive migrations applied to the live PostgreSQL database, permissions re-seeded, and a live smoke test of login → 2FA status → security events → staff invitations → step-up passed.

---

## 2. Baseline and final test results

| Stage | Tests | Assertions |
|-------|-------|------------|
| Baseline (before this work) | 227 | 761 |
| After backend security core | **270** | **892** |

New test files:

| File | Covers |
|------|--------|
| `backend/tests/Unit/TotpServiceTest.php` | RFC 6238 vectors, Base32 round-trip, replay rejection, provisioning URI |
| `backend/tests/Feature/TwoFactorAuthenticationTest.php` | enroll/confirm/disable, mandatory login gating, replay, recovery codes |
| `backend/tests/Feature/StaffProvisioningTest.php` | invite, lookup, accept, revoke, suspend + step-up, last-admin safeguard |
| `backend/tests/Feature/AccountSecurityTest.php` | password change + session revoke, email change (two-stage), step-up, security events |
| `backend/tests/Feature/AccountLifecycleTest.php` | pending/suspended/disabled account login blocking, restricted password-change state |

Measured with `docker exec clinic_backend php artisan test`.

---

## 3. Phase 1 — Audit checklist (status + evidence)

Legend: **PASS** = implemented and enforced server-side; **PARTIAL** = present but incomplete/hardened further here; **MISSING** = absent before this work; **BLOCKED** = cannot be completed/verified in this environment.

| # | Requirement | Before | After | Evidence |
|---|-------------|--------|-------|----------|
| 1 | Server-side authentication with stateless tokens | PASS | PASS | Sanctum bearer tokens, `AuthController::login` (`backend/app/Http/Controllers/Api/AuthController.php:35`) |
| 2 | Admin-created staff accounts (no public self-registration for staff) | MISSING | PASS | `StaffInvitationController`, `StaffProvisioningService`; `users.staff_id`, `users.account_status` |
| 3 | Prohibited open staff self-registration | PARTIAL | PASS | Staff come only through an invitation accepted by token; accounts start `pending` until activated |
| 4 | Hashed invitation tokens, single-use, expiring | MISSING | PASS | `staff_invitations.token_hash` unique; `config('auth.invitation.expire_hours')`; `StaffProvisioningService::accept` |
| 5 | Mandatory strong password policy | PARTIAL | PASS | `Rules\Password` (mixed case/numbers/symbols); `AUTH_PASSWORD_MIN` |
| 6 | Force password change on first login | MISSING | PASS | `users.must_change_password` + `EnsurePasswordIsCurrent` middleware |
| 7 | App-based TOTP 2FA (RFC 6238) | MISSING | PASS | `TotpService`, `Support\Base32`, `TwoFactorService` |
| 8 | Encrypt 2FA secret at rest, never expose it | MISSING | PASS | `User::$casts['two_factor_secret'] = 'encrypted'`, `User::$hidden` includes it |
| 9 | Replay protection for TOTP codes | MISSING | PASS | `users.two_factor_last_step`; verified by `a totp code cannot be replayed` |
| 10 | Single-use recovery codes | MISSING | PASS | `two_factor_recovery_codes.code_hash` (hashed), `used_at` |
| 11 | Mandatory 2FA enforcement, configurable | MISSING | PASS (flag, default off) | `AUTH_2FA_MANDATORY`; `EnsureTwoFactorEnrolled` registered only when mandatory |
| 12 | Trusted devices skip step-up (but not TOTP) | PARTIAL | PASS | `TrustedDeviceService`; trusted device skips email-OTP only, TOTP still required |
| 13 | Server-side step-up for sensitive actions | MISSING | PASS | `StepUpService` + `EnsureStepUp` middleware on `users.suspend` / `users.disable` |
| 14 | Step-up grants short-lived and single-use | MISSING | PASS | `step_up_authorizations` (hashed token, `action`, `expires_at`, `consumed_at`) |
| 15 | Secure self-service password change (revokes sessions) | MISSING | PASS | `AccountSecurityService::changePassword`, `PUT /auth/password` |
| 16 | Two-stage email change (confirm new address) | MISSING | PASS | `EmailChangeService`, `email_change_requests`, `POST /auth/email/change[/confirm]` |
| 17 | Notify old address on email change | MISSING | PASS | `EmailChangedNotice` notification |
| 18 | Account lifecycle: pending/active/suspended/disabled | MISSING | PASS | `users.account_status` + `User` helpers; `AccountSecurityService` |
| 19 | Suspended/disabled accounts cannot authenticate or use existing tokens | MISSING | PASS | `login` status gate + `EnsureAccountIsActive` middleware on every protected route |
| 20 | Protect against locking out the last active admin | MISSING | PASS | `AccountSecurityService` last-active-admin guard; test `the last active admin cannot be suspended` |
| 21 | Risk-based security event log, secret-free | MISSING | PASS | `SecurityEventService` (+`riskScore`), `security_events` table with sanitizer |
| 22 | Progressive account lockout | MISSING | PASS (flag, default off) | `AUTH_LOCKOUT_ENABLED`; `users.failed_login_attempts`, `locked_until` |
| 23 | Rate limiting on auth endpoints | PASS | PASS | `login`, `otp`, `password-reset`, plus new `two-factor` and `invitation` limiters (`AppServiceProvider`) |
| 24 | RBAC for invitation/suspension, least privilege | PARTIAL | PASS | `users.invite`, `users.suspend`, `security.view`; `UserPolicy::invite/suspend` |
| 25 | Patient-data protection from staff-only controls | PASS | PASS | All new endpoints are staff-scoped; `EnsureTwoFactorEnrolled` exempts the Patient role |
| 26 | Email/external delivery verified end-to-end | BLOCKED | BLOCKED | Mail is queued (sync in tests). Delivery to real inboxes not verifiable here — see §10 |

---

## 4. Implemented controls

### 4.1 Staff provisioning (admin-driven)
- `StaffInvitationController` (`GET/POST /staff/invitations`, `DELETE …/{invitation}`, `POST …/{invitation}/resend`) and public `InvitationController` (`POST /auth/invitation/lookup`, `POST /auth/invitation/accept`).
- Only a **hash** of the invitation token is persisted (`staff_invitations.token_hash`, unique). The plaintext token is delivered once by `StaffInvitationNotification`, built from `config('auth.invitation.frontend_path')`.
- Accepting an invitation creates the `User` with status `active`, assigns the invited role, marks email verified, and records `account.invited` / `account.activated` events.
- Invitations can be revoked and expire (`AUTH_INVITATION_EXPIRE_HOURS`, default 72).

### 4.2 TOTP two-factor authentication (pure PHP)
- `App\Services\Auth\TotpService` + `App\Services\Auth\Support\Base32` implement RFC 6238 (HMAC-SHA1, configurable digits/period/window) with no external dependency. Validated against the official RFC 6238 test vectors.
- Enrollment: `POST /auth/2fa/enroll` returns a provisioning URI (`otpauth://`) and secret to the authenticated user (after re-supplying the account password via `TwoFactorEnrollRequest`). `POST /auth/2fa/confirm` verifies a code, flips `two_factor_enabled`, sets `two_factor_confirmed_at`, and issues one-time recovery codes.
- The secret is stored with Laravel's `encrypted` cast and is in the model's `$hidden` array, so it is never serialized.
- Login gating: when `AUTH_2FA_MANDATORY=true` **and** the user has confirmed TOTP, `login`/`otp/verify` return `202 { two_factor_required, challenge_token, expires_in }` instead of a token. `POST /auth/2fa/verify` completes the challenge (TOTP or recovery code) and issues the token.
- Replay protection via `users.two_factor_last_step`; a consumed step cannot be reused.
- `POST /auth/2fa/recovery-codes` regenerates codes; `DELETE /auth/2fa` disables 2FA (requires the account password).
- A trusted device only removes the **email-OTP** step; the TOTP step is always required. This is stricter than the email-OTP flow and is intentional.

### 4.3 Step-up authorization
- `StepUpService::authorize` verifies the current password and issues a short-lived, action-bound grant (`X-Step-Up-Token`). `EnsureStepUp` (`step.up:users.suspend`) consumes it single-use.
- Applied to `POST /users/{user}/suspend` and `POST /users/{user}/disable`.

### 4.4 Password and email change
- `PUT /auth/password` (`AccountSecurityService::changePassword`) enforces the current password, applies the password policy, clears `must_change_password`, updates `password_changed_at`, records `password.changed`, and revokes all other sessions.
- `POST /auth/email/change` issues a code to the **new** address (code never returned to the client); `POST /auth/email/change/confirm` verifies the code, updates the email, records `email.changed`, notifies the **old** address, and revokes tokens/devices.

### 4.5 Account lifecycle & risk events
- `users.account_status` (`pending` / `active` / `suspended` / `disabled`) with `activated_at`, `suspended_at`, `suspended_reason`, `last_login_at`, `password_changed_at`, `must_change_password`, `failed_login_attempts`, `locked_until`.
- `EnsureAccountIsActive` is applied to the entire protected route group, so a suspended/disabled account cannot use an existing token.
- `SecurityEventService` writes append-only events with a severity map and a `sanitize()` guard that strips `password`, `token`, `secret`, `code`, etc. `riskScore()` provides an advisory signal only; it never drives authorization.

---

## 5. Schema additions (all additive)

| Migration | Table | Purpose |
|-----------|-------|---------|
| `2026_10_20_000013_add_account_lifecycle_to_users_table` | `users` | staff_id, phone, account_status, lifecycle timestamps, lockout counters, created_by |
| `2026_10_20_000014_add_two_factor_columns_to_users_table` | `users` | encrypted secret, enabled/confirmed flags, last step |
| `2026_10_20_000015_create_staff_invitations_table` | `staff_invitations` | hashed invite tokens |
| `2026_10_20_000016_create_two_factor_recovery_codes_table` | `two_factor_recovery_codes` | hashed one-time codes |
| `2026_10_20_000017_create_two_factor_challenges_table` | `two_factor_challenges` | login TOTP challenges |
| `2026_10_20_000018_create_step_up_authorizations_table` | `step_up_authorizations` | action-bound grants |
| `2026_10_20_000019_create_security_events_table` | `security_events` | append-only audit log |
| `2026_10_20_000020_create_email_change_requests_table` | `email_change_requests` | two-stage email change codes |

No existing table was dropped, renamed, or had data rewritten. Existing users inherit `account_status = 'active'` via the column default.

---

## 6. API endpoints added

| Method | Path | Notes |
|--------|------|-------|
| POST | `/auth/2fa/verify` | complete login TOTP/recovery challenge (public, throttled `two-factor`) |
| GET | `/auth/2fa/status` | enrollment state for the current user |
| POST | `/auth/2fa/enroll` | begin enrollment (password-gated) |
| POST | `/auth/2fa/confirm` | confirm code; returns recovery codes once |
| POST | `/auth/2fa/recovery-codes` | regenerate recovery codes |
| DELETE | `/auth/2fa` | disable 2FA (password-gated) |
| PUT | `/auth/password` | change own password; revokes sessions |
| POST | `/auth/email/change` | request two-stage email change |
| POST | `/auth/email/change/confirm` | confirm code; notifies old address |
| POST | `/auth/step-up` | obtain a step-up grant (password) |
| GET | `/auth/security-events` | self-scoped security event feed |
| POST | `/auth/invitation/lookup` | validate an invitation token (public) |
| POST | `/auth/invitation/accept` | accept invitation, create account (public) |
| GET/POST | `/staff/invitations` | list / create invitations (staff) |
| DELETE | `/staff/invitations/{invitation}` | revoke |
| POST | `/staff/invitations/{invitation}/resend` | resend |
| POST | `/users/{user}/suspend` | requires `step.up:users.suspend` |
| POST | `/users/{user}/activate` | reactivate |
| POST | `/users/{user}/disable` | requires `step.up:users.suspend` |

All protected routes carry `account.active` and `password.current` (and `2fa.enrolled` when mandatory). Existing `login`/`otp`/`me`/`logout`/`devices` responses gained **additive** fields only.

---

## 7. RBAC

`RolePermissionSeeder` adds `users.invite`, `users.suspend`, `security.view`. `UserPolicy` gains `invite` and `suspend`. Newly seeded permissions were applied to the live database via `php artisan db:seed --class=RolePermissionSeeder` (idempotent; no data loss).

---

## 8. Configuration & rollout

New keys (documented in `backend/.env.example`), **all default to off / non-breaking**:

```
AUTH_2FA_MANDATORY=false          # enable mandatory TOTP per environment
AUTH_2FA_ISSUER, AUTH_2FA_DIGITS, AUTH_2FA_PERIOD, AUTH_2FA_WINDOW
AUTH_2FA_CHALLENGE_EXPIRE_MINUTES, AUTH_2FA_CHALLENGE_MAX_ATTEMPTS, AUTH_2FA_RECOVERY_CODES
AUTH_LOCKOUT_ENABLED=false        # progressive per-account lockout
AUTH_LOCKOUT_MAX_ATTEMPTS, AUTH_LOCKOUT_DECAY_MINUTES
AUTH_INVITATION_EXPIRE_HOURS, AUTH_INVITATION_PATH
AUTH_STEP_UP_TTL_MINUTES
AUTH_EMAIL_CHANGE_EXPIRE_MINUTES, AUTH_EMAIL_CHANGE_MAX_ATTEMPTS
```

**Recommended rollout:** (1) deploy with flags off, (2) let staff enroll in TOTP from settings, (3) enable `AUTH_2FA_MANDATORY=true` per environment once enrollment is broad, (4) enable `AUTH_LOCKOUT_ENABLED=true` after tuning thresholds.

---

## 9. Verification performed

- Full suite: `docker exec clinic_backend php artisan test` → **270 passed (892 assertions)**.
- `php -l` syntax check across all new/changed PHP files → clean; `route:list` resolves all new routes with the intended middleware stacks.
- Live PostgreSQL migrations applied (`php artisan migrate --force`) — 8 migrations, all additive, no errors.
- Live permission re-seed completed.
- Live smoke test against `http://127.0.0.1:8000/api/v1`: admin login → token; `GET /auth/2fa/status` (`enabled:false, mandatory:false`); `GET /auth/security-events` (queryable); `GET /staff/invitations` (authorized); `POST /auth/step-up` (grant issued).
- Code style: `pint` → 6 style issues fixed across touched files.

---

## 10. Known limitations & blockers (honest)

1. **Email delivery not verified end-to-end.** Notifications are queued; tests use the `array` mailer. Actual inbox delivery (invitations, email-change codes, status notifications) depends on SMTP credentials and was **not** verified in this environment.
2. **Step-up uses password only.** Accounts with TOTP enabled can complete step-up with the password alone. Requiring a TOTP code as an additional step-up factor is a planned hardening.
3. **Frontend not yet integrated.** The new backend endpoints have no React UI/wrappers yet (no TOTP enrollment/QR screen, no password/email-change forms, no invitation-accept page). The API is fully usable but not yet surfaced in the SPA.
4. **`2fa.enrolled` not active by default.** It is only registered when `AUTH_2FA_MANDATORY=true` at route-registration time, so changing the flag requires a config/route cache clear and a worker restart.
5. **Risk score is advisory.** It is logged but does not block or gate anything by itself.
6. **Operational:** long-running workers (queue/broadcast) require `docker restart clinic_backend` after class changes; no `supervisorctl` socket is available.

---

## 11. Remaining work

- **Frontend:** TOTP enrollment with QR rendering from the `otpauth://` URI, recovery-code display, login TOTP step, password-change and two-stage email-change forms, invitation-accept page, staff invitation management screen, security-events view, and gating UI for suspend/step-up.
- **Docs:** OpenAPI annotations for the new endpoints, and a short administrator runbook for enabling mandatory 2FA and invitations.
- **Hardening (optional):** require TOTP as a step-up factor for enrolled users; expose `riskScore` in the security-events response and alert on `critical` severity.

---

## 12. Files of interest

**Services:** `backend/app/Services/Auth/{TotpService,TwoFactorService,TwoFactorChallengeService,StepUpService,StaffProvisioningService,EmailChangeService,AccountSecurityService,SecurityEventService}.php`, `Services/Auth/Support/Base32.php`
**Controllers:** `backend/app/Http/Controllers/Api/{AuthController,TwoFactorController,AccountSecurityController,StaffInvitationController,InvitationController,UserController}.php`
**Middleware:** `backend/app/Http/Middleware/{EnsureAccountIsActive,EnsurePasswordIsCurrent,EnsureTwoFactorEnrolled,EnsureStepUp}.php`
**Models:** `backend/app/Models/{User,StaffInvitation,TwoFactorRecoveryCode,TwoFactorChallenge,StepUpAuthorization,SecurityEvent,EmailChangeRequest}.php`
**Config/wiring:** `backend/config/auth.php`, `backend/.env.example`, `backend/bootstrap/app.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/app/Policies/UserPolicy.php`, `backend/database/seeders/RolePermissionSeeder.php`, `backend/routes/api.php`
**Tests:** `backend/tests/Unit/TotpServiceTest.php`, `backend/tests/Feature/{TwoFactorAuthenticationTest,StaffProvisioningTest,AccountSecurityTest,AccountLifecycleTest}.php`
