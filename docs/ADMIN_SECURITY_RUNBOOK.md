# Administrator Security Runbook

**Project:** RoyalCare Clinic Management (clinic-management-medical-expert-system)
**Audience:** Clinic administrators and DevOps operators
**Scope:** Enabling and operating the staff security features — staff invitations, mandatory staff-admin approval, TOTP two-factor authentication, step-up authorization, account lifecycle, lockout, and security alerts.

> Every enforcement feature is behind an environment flag that **defaults to off**. Deploy with the flags off first, let staff enroll, then turn features on per environment. No code change is required to toggle any of them.

---

## 1. Feature flags (backend `.env`)

| Variable | Default | Effect when enabled |
|----------|---------|---------------------|
| `AUTH_2FA_MANDATORY` | `false` | Staff with TOTP enrolled must enter a code at sign-in; staff without it are prompted/blocked until they enroll. |
| `AUTH_STEP_UP_REQUIRE_TWO_FACTOR` | `false` | A step-up (e.g. suspending a user) additionally requires a TOTP/recovery code **for accounts that have TOTP enabled**. Non-enrolled accounts are unaffected. |
| `AUTH_LOCKOUT_ENABLED` | `false` | Progressive per-account lockout after repeated failed passwords. The login route is always rate-limited regardless of this flag. |
| `AUTH_SECURITY_ALERTS_ENABLED` | `true` | Emails the account owner when a **critical** security event occurs (e.g. account lockout). Best-effort; never contains secrets. |
| `AUTH_REQUIRE_VERIFIED_EMAIL` | `false` | Requires a verified email before protected routes are reachable. |
| `AUTH_ENFORCE_TRUSTED_DEVICE` | `false` | Requires a trusted-device cookie in addition to the bearer token. |

Supporting values:

```
AUTH_2FA_ISSUER, AUTH_2FA_DIGITS, AUTH_2FA_PERIOD, AUTH_2FA_WINDOW
AUTH_2FA_CHALLENGE_EXPIRE_MINUTES, AUTH_2FA_CHALLENGE_MAX_ATTEMPTS, AUTH_2FA_RECOVERY_CODES
AUTH_STEP_UP_TTL_MINUTES
AUTH_LOCKOUT_MAX_ATTEMPTS, AUTH_LOCKOUT_DECAY_MINUTES
AUTH_INVITATION_EXPIRE_HOURS, AUTH_INVITATION_PATH
AUTH_EMAIL_CHANGE_EXPIRE_MINUTES, AUTH_EMAIL_CHANGE_MAX_ATTEMPTS
```

After changing any flag, clear the config cache and restart long-running workers:

```bash
docker exec clinic_backend php artisan config:clear
docker exec clinic_backend php artisan route:clear     # needed for AUTH_2FA_MANDATORY (route-registered middleware)
docker restart clinic_backend                            # queue/broadcast workers
```

> `2fa.enrolled` middleware is only registered when `AUTH_2FA_MANDATORY=true` at route-registration time, so toggling that specific flag **requires** a `route:clear` (or a fresh deploy), not just a config clear.

---

## 2. Staff invitations (no public self-registration)

Staff accounts are created **only** by an administrator invitation. There is no public sign-up for staff.

1. Sign in as an **Admin** and open **Users → Invitations** (`/invitations`).
2. Choose **Invite staff**, enter the name, work email, and role, then send.
3. The invitee receives an email with a one-time link to `/accept-invitation`.
4. The account is `pending` until the invitee sets a password and accepts; accepting moves it to `pending_approval` (it **cannot sign in yet**).
5. An administrator reviews and approves the registration from **Users → Pending Approvals** (`/approvals`). Only after approval does the account become `active` and able to sign in.

Operator notes:

- Only a hash of the invitation token is stored; the plaintext is emailed once.
- Invitations expire after `AUTH_INVITATION_EXPIRE_HOURS` (default 72). Expired/used tokens are rejected on lookup and acceptance.
- You can **resend** (regenerates the link) or **revoke** an invitation at any time from the Invitations screen.

---

## 2a. Approving new staff registrations

Accepting an invitation does **not** grant access. Every invited staff account must be reviewed by an authorized administrator before it can sign in.

1. The invitee accepts the invitation and sets a password — the account enters `pending_approval`.
2. Open **Users → Pending Approvals** (`/approvals`). This page lists every account awaiting review.
3. For each account choose **Approve** or **Reject**:
   - **Approve** makes the account `active`; the staff member can now sign in (subject to any 2FA/OTP requirements).
   - **Reject** requires a reason and moves the account to `rejected`, which blocks sign-in. A rejected account can be approved later if the decision is reversed.
4. Both actions require you to confirm your identity (your admin password, plus an authenticator code if you have TOTP enabled) — the same step-up flow used for suspend/disable.

Operator notes:

- Approval requires the `users.approve` permission (held by the **Admin** role). An administrator cannot approve or reject their own account.
- The reviewing administrator and the decision (approve/reject, timestamp, and reason) are recorded on the account and written to the `security_events` audit log (`account.approved` / `account.rejected`).
- The legacy **Activate** action on the Users screen will refuse a `pending_approval` or `rejected` account — use the Pending Approvals workflow instead so the decision is always audited.
- `pending` (invited, not yet accepted) accounts are **not** shown on the Pending Approvals page; only accepted registrations awaiting review appear there.

---

## 3. Two-factor authentication (TOTP)

1. Each staff member enables 2FA from **Settings → Security → Two-factor**.
2. They scan the QR code (or enter the secret) in Google Authenticator / Authy / 1Password / Microsoft Authenticator, then confirm with a code.
3. **Recovery codes** are shown once — store them safely. Each works once.

Rollout:

1. Deploy with `AUTH_2FA_MANDATORY=false`.
2. Ask staff to enroll from Settings.
3. Once enrollment is broad, set `AUTH_2FA_MANDATORY=true` and run `route:clear` + worker restart.
4. Accounts that still have not enrolled will be prompted to set it up before they can use the app.

---

## 4. Step-up authorization (sensitive actions)

Sensitive actions — **suspend** and **disable** a user — require the operator to re-prove their identity. The SPA prompts for the account password (and an authenticator code when required).

- A short-lived, single-use grant is issued and consumed by the `X-Step-Up-Token` header.
- With `AUTH_STEP_UP_REQUIRE_TWO_FACTOR=true`, an account that has TOTP enabled must also supply a valid authenticator (or recovery) code.
- The **last active administrator cannot be suspended/disabled**, protecting you from locking everyone out.

---

## 5. Account lifecycle

| Status | Sign-in | Existing tokens |
|--------|---------|-----------------|
| `pending` | Blocked (must accept invitation) | Blocked |
| `pending_approval` | Blocked (must be approved by an admin) | Blocked |
| `active` | Allowed | Allowed |
| `suspended` | Blocked | Blocked |
| `disabled` | Blocked | Blocked |
| `rejected` | Blocked (registration rejected) | Blocked |

`EnsureAccountIsActive` is applied to every protected route, so suspending/disabling an account immediately invalidates its existing API tokens. The same middleware blocks `pending_approval` and `rejected` accounts, so a staff member cannot sign in — or use any token issued before the workflow — until an administrator approves the registration. Accounts created with `must_change_password` are restricted to Settings until they rotate the password.

---

## 6. Security events & alerts

- Every authentication and account-security event is written to an append-only `security_events` log (never stores secrets).
- Staff can review their own recent activity in **Settings → Security → Recent security activity**, including an advisory **risk score** (higher = more unusual). The risk score is informational only and never blocks access.
- When `AUTH_SECURITY_ALERTS_ENABLED=true`, the account owner is emailed on **critical** events (currently: account lockout).
- Admins can inspect the full log via the database or the audit tooling; there is no public admin UI for cross-account events yet.

---

## 7. Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| 2FA prompt never appears after enabling mandatory mode | Run `php artisan route:clear` and restart workers; the middleware is route-registered. |
| Step-up asks for a code unexpectedly | `AUTH_STEP_UP_REQUIRE_TWO_FACTOR=true` and the acting admin has TOTP enabled. Enter an authenticator/recovery code. |
| Invite email not received | Check `MAIL_*` settings and the queue worker; invitations are queued. Resend from the Invitations screen. |
| Account locked out | Wait `AUTH_LOCKOUT_DECAY_MINUTES`, or set `locked_until = NULL` for the user. A successful sign-in clears the lock. |
| Risk badge shows on an event | Advisory only; it reflects proxy headers, unknown IP, or recent failures. No action required. |
| New staff member accepted the invitation but cannot sign in | Expected — the account is `pending_approval`. Approve it from **Pending Approvals** (`/approvals`). |
| "Activate" on the Users screen is refused for a new account | By design: `pending_approval`/`rejected` accounts must go through the approval workflow so the decision is audited. |
| Pending Approvals page is empty but someone just accepted | The page lists only accepted registrations awaiting review; a `pending` (not-yet-accepted) invitation will not appear. |

---

## 8. Reference

- Configuration: `backend/config/auth.php` and `backend/.env.example`.
- API docs (OpenAPI, auto-generated by Scramble): `/docs/api` and `/docs/api.json` (local env, or authenticated Admin elsewhere).
- Related report: `SECURITY_IMPLEMENTATION_AUDIT_REPORT.md`.