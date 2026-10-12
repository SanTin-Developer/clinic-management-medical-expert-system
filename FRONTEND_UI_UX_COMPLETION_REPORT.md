# Frontend UI/UX — Completion Report

Project: `clinic-management-medical-expert-system`
Report date: 2026-10-11
Scope: React (Vite) single-page application for the Clinic Management System and
Medical Expert System — design system, all feature screens, RBAC, live API
integration, verification.
Companion reports: `SECURITY_AND_MISSING_FEATURES_COMPLETION_REPORT.md`
(backend security/features) and `PYTHON_EXPERT_SYSTEM_COMPLETION_REPORT.md`
(rule engine & integration).

---

## 1. Executive summary

The frontend was rebuilt from the earlier "foundation only" placeholder shell
into a feature-complete SPA wired to the live Laravel API. All 12 API modules,
22 route-level pages, 20 design-system primitives, 17 feature modals and the
RBAC/permission layer are implemented and verified against the running stack.

Highlights:

* **Design system** — rose/healthcare token theme in `src/styles/globals.css`,
  20 reusable primitives (Button, Card, Modal, ConfirmDialog, Tabs, Table,
  Pagination, Field, Input, Select, Textarea, Toggle, Badge, Dropdown,
  PageHeader, StatCard, Skeleton/Spinner, State, SearchInput, Breadcrumb) with
  consistent focus rings, disabled/loading states, and accessible roles.
* **App shell** — collapsible permission-aware sidebar, header with user menu
  and logout, mobile drawer, print-friendly invoice output (`print:hidden`
  chrome + `.print-area`).
* **Auth flows** — login with device-OTP branch, OTP verify/resend, forgot &
  reset password, and a new email-verification landing page matching the
  backend deep link (`/verify-email?verify_url=…`).
* **Clinical modules** — patients (+ secure document upload/download/delete),
  appointments (+ reschedule/cancel), medical records (consultations,
  diagnoses, vitals in one tabbed workspace), prescriptions (line items,
  dispense/cancel), pharmacy (medicines, inventory, stock movements), billing
  (invoices, balance-capped payments, printable invoice).
* **Insight & admin** — dashboard, reports with CSV/PDF export, users & roles,
  settings (profile + trusted devices), and the expert system (symptom
  analysis, knowledge-base CRUD, evaluation audit history).
* **RBAC** — every protected route is wrapped in a permission `Guard`; action
  buttons respect `can(...)`; navigation is filtered by permission. Server
  policies remain the source of truth (hiding is never authorisation).

Verification: `oxlint` 0 errors, `vite build` succeeds (2,105 modules), and a
live API smoke test returned `200` for login + all authenticated endpoints,
report CSV/PDF exports, and the expert-system evaluation, including correct
CORS headers.

Honest completion: **~97% of the frontend scope.** The residual is **blocked by
missing backend endpoints**, not unfinished UI (see §8).

---

## 2. Phase-by-phase status

| # | Phase | Status |
| --- | --- | --- |
| 1 | Foundation, tooling, design tokens | **100%** |
| 2 | App shell, routing, RBAC guards | **100%** |
| 3 | Authentication & account flows | **95%** |
| 4 | Patients & secure documents | **100%** |
| 5 | Appointments | **100%** |
| 6 | Medical records (consultations, diagnoses, vitals) | **100%** |
| 7 | Prescriptions | **100%** |
| 8 | Pharmacy (medicines, inventory, stock movements) | **100%** |
| 9 | Billing (invoices, payments, print) | **100%** |
| 10 | Reports & export (CSV/PDF) | **100%** |
| 11 | Users & roles | **100%** |
| 12 | Settings (profile, trusted devices) | **90%** |
| 13 | Expert system (analysis, knowledge base, audit) | **100%** |
| 14 | Verification (lint/build/API smoke) | **100%** |

---

## 3. Design system & layout

* **Tokens** (`src/styles/globals.css`): `--color-brand` (rose) family
  (`brand`, `brand-dark`, `brand-soft`, `brand-faint`), `ink`/`ink-muted`,
  `line`, `canvas`, `surface`, shadow tokens; Geist (+ Kantumruy Pro for Khmer)
  variable fonts.
* **Primitives** (`src/components/ui/*`): 20 components. `Modal` and
  `ConfirmDialog` trap focus and close on `Esc`/backdrop; `Tabs` use
  `role="tablist"`/`aria-selected`; `Toggle` is an accessible
  `role="switch"`; list rows render skeleton loaders while fetching.
* **Responsiveness**: sidebar collapses to a drawer under `lg`; grids use
  `sm`/`lg`/`xl` breakpoints; tables scroll horizontally with sensible
  `min-width`.
* **Print**: invoice detail uses a `.print-area` with sidebar/header/action
  chrome marked `print:hidden`, so `Print` produces a clean invoice.

## 4. Routing & RBAC

* `createBrowserRouter` with lazy-loaded pages and a `Suspense` boundary.
* `ProtectedRoute` gates the authenticated shell; `Guard` accepts
  `permission` or `any={[...]}` and renders a `Forbidden` state when the
  `AuthProvider` permission set does not satisfy it.
* Route → permission map: patients/documents, appointments, medical-records,
  prescriptions, pharmacy (`medicines.view` OR `inventory.view`), billing,
  reports, users, expert-system (`expert-system.view` OR `expert-system.use`);
  settings is available to all authenticated users.
* Action-level checks (`can('patients.create')`, `can('billing.update')`, …)
  hide/disable mutating controls; the API policy layer enforces the same rules.
* The **Doctor** role carries `knowledge-base.view`, so doctors get the
  read-only Knowledge Base tab; create/edit controls stay hidden without
  `knowledge-base.manage`.

## 5. Auth & account flows

* **Login** → `POST /auth/login` with a single **"Email or username"** field
  (posted as `email`; the API resolves it to the matching column); if
  `otp_required`, redirects to `/login/verify` carrying the `challenge_token`;
  otherwise stores the token and returns to the requested route.
* **OTP** → `POST /auth/otp/verify` (with `remember_device`) and
  `POST /auth/otp/resend` (cooldown-aware).
* **Password reset (OTP)** → `forgot-password` emails a 6-digit code and moves
  on to `reset-password` (email carried in route state); the reset page takes
  the code plus the new password and offers an inline "send a new code" resend.
* **Email verification** → `/verify-email?verify_url=…` calls the signed URL
  and reports success / expired / already-verified / missing-token states;
  Settings offers a "Resend link" action when unverified.
* **Trusted devices** → Settings lists devices (flags the current one) with
  per-device and revoke-all actions via `GET/DELETE /auth/devices`.
* **Session** → bearer token persisted (no password/OTP in storage), attached
  by the axios client; `401` clears the session and routes back to `/login`.

## 6. Feature modules

* **Patients** — searchable list, create/edit modal with validation mirroring
  the API rules (gender, DOB `< today`, blood type, emergency contacts),
  detail page with appointment shortcuts, and a documents panel (upload with
  category/title, authenticated blob download, delete).
* **Appointments** — status-tabbed list, create/edit with doctor/patient
  selects and slot-uniqueness handled server-side, detail page with
  reschedule and cancel.
* **Medical records** — four tabs (records, consultations, diagnoses, vitals)
  with their own create/edit modals; consultations auto-fill patient/doctor
  from the appointment; diagnosis severity uses the shared option map.
* **Prescriptions** — dynamic line-item editor (medicine, dosage, frequency,
  duration), status tabs, detail with dispense/cancel/delete; `dispensed_by`
  renders whether serialised as an object or id.
* **Pharmacy** — three tabs: medicines (search + CRUD), inventory batches
  (low-stock highlighting), stock movements (in/out/adjustment).
* **Billing** — invoice builder (description/qty/unit price, discount, tax),
  balance-capped payment modal, status tabs, and a printable detail page with
  payment history, cancel (only when unpaid) and delete.
* **Reports** — overview cards from `/reports/summary` plus appointment,
  revenue (by-day bars / by-method) and inventory (low stock, expiring)
  reports with date filters and CSV/PDF export buttons.
* **Users & roles** — list with role badges, create/edit (name, email, role,
  optional password reset) and delete.
* **Settings** — profile card (roles, verification, permission count),
  security card, and trusted-device management.
* **Expert system** — Symptom Analysis (active-symptom multi-select →
  `/expert-system/evaluate`, matched-rule cards with priority/confidence/
  severity/explanation and the engine's non-diagnostic notice), Knowledge Base
  (CRUD for diseases, symptoms and expert rules, including the condition
  editor with present/absent operators and AND/OR groups), and Audit History
  (`/audit-logs` with date filters).

## 7. Verification

* **Lint** — `npm run lint` (`oxlint`): **0 errors**; 28 warnings, all benign
  and intentional:
  * `react(only-export-components)` on the router (required by the
    `React.lazy` re-export pattern);
  * `react(set-state-in-effect)` where effects synchronise modal state on
    open / trigger a fetch on mount;
  * `react(purity)` for `new Date()` used only to seed default form values.
* **Build** — `npm run build` (vite v8.3.2): **success**, 2,105 modules
  transformed. `npm run preview` serves the built app (HTTP 200, `#root`
  present).
* **Live API smoke** (against the running Docker stack, `admin@clinic.local`):
  * `POST /auth/login` → `200` with token, roles `["Admin"]`, full permission
    list;
  * `GET` → `200`: `/patients`, `/appointments`,
    `/knowledge-base/{diseases,symptoms,expert-rules}`, `/audit-logs`,
    `/reports/summary`, `/auth/me`, `/auth/devices`;
  * `GET /reports/{appointments,revenue,inventory}/export` → `200 text/csv`,
    `/reports/appointments/pdf` → `200 application/pdf`;
  * `POST /expert-system/evaluate` → `200` with the exact
    `{ engine, engine_version, is_clinically_validated, matched_rule_count,
    matched_rules[], notice }` shape the UI consumes;
  * **CORS** headers present on the API response:
    `Access-Control-Allow-Origin: http://localhost:5173` and
    `Access-Control-Allow-Credentials: true` (bearer + trusted-device cookie).
* **Latency (resolved).** The live API initially responded slowly (~10–30 s per
  request; a DB-less `/up` took ~3 s and a framework boot ~8–14 s). Root cause
  was the dev backend: `php artisan serve` is single-threaded and, being CLI,
  runs with OPcache **disabled**, so it re-read all of `vendor/` through the
  slow Windows bind mount on every request. The backend now mirrors the fast
  reference stack — **`php:8.4-fpm` + nginx + supervisor**, OPcache/realpath
  tuning, a one-shot OPcache warmup, and **Redis** for cache/session/queue (see
  §10.4). After the fix, warm requests are ~0.2–0.7 s and the first request
  after `docker compose up` is ~0.3 s (previously ~8.5 s); 9 parallel requests
  complete in ~1.2 s (previously ~45 s serialized). CORS and all API contracts
  are unchanged.

## 8. Known gaps & backend dependencies

These are **not** frontend defects; each is blocked by an API that does not
exist yet or is intentionally out of scope.

1. **Self-service profile / email / password change** — no API endpoint
   updates the authenticated user's own profile, so the Settings profile card
   is read-only (password changes go through the forgot/reset flow, profile
   changes through an admin). *Recommendation:* add `PATCH /auth/profile` and
   `PUT /auth/password`.
2. **Server-side list query params** — list endpoints expose only `?page`
   (fixed 15/page); there is no `search`, `sort`, `status` or `per_page`.
   Search/filtering is therefore client-side within the current page. Fine at
   current volumes; revisit for large datasets.
3. **Real-time updates** — no WebSocket/broadcast layer; data refreshes via
   query refetch after mutations.
4. **Appointment calendar** — implemented as a status-tabbed list + detail
   (not a month/week calendar grid). The data model supports a calendar if a
   dedicated view is later requested.
5. **Internationalisation** — the Khmer font (Kantumruy Pro) is bundled and
   the API returns `name_km`, but the UI chrome is English-only; there is no
   language switcher yet.
6. **Client-side PDF/report rendering** — reports export through the backend
   (CSV + server-rendered PDF); `jspdf`/`html2canvas-pro` are installed but
   the invoice "print" path uses the browser print dialog rather than a
   generated PDF.
7. **Dependency audit** — `npm audit` reports **7 high** advisories, all in
   the `shadcn` CLI dev toolchain (`braces` → `micromatch` → `fast-glob`).
   No non-breaking fix exists and it never enters the production bundle.

## 9. Files of record

* `frontend/src/api/*` — 12 modules (auth, patients, appointments, clinical,
  prescriptions, pharmacy, billing, documents, reports, users, expert, client).
* `frontend/src/components/ui/*` — 20 primitives.
* `frontend/src/components/{layout,auth,patients,appointments,medical,
  prescriptions,pharmacy,billing,users,expert}/*` — 17 feature components
  (incl. 17 modals).
* `frontend/src/pages/*` — 22 screens.
* `frontend/src/router/{index,ProtectedRoute,Guard}.jsx` — routing + RBAC.
* `frontend/src/styles/globals.css` — design tokens.
* `frontend/src/{hooks,context,utils}/*` — `useAuth`, `usePaginated`/`useAll`,
  `AuthProvider`, status/format helpers.

## 10. Follow-ups

1. Add profile/password self-service once the backend exposes the endpoints
   (§8.1), then turn the Settings profile card into an editable form.
2. Add server-side search/sort/pagination params to list endpoints (§8.2) and
   switch the client-side filters to query params.
3. Optional: month/week appointment calendar, i18n toggle (EN/KM), and
   client-side PDF for invoices if a printable-PDF (not print-dialog) export
   is required.
4. **Resolved — dev API latency.** The backend Docker image was switched from
   `php:8.4-cli` (`artisan serve`) to **`php:8.4-fpm` + nginx + supervisor**,
   with OPcache + realpath-cache tuning, a one-shot OPcache warmup on start, and
   **Redis** for cache/session/queue. Files: `backend/Dockerfile`,
   `backend/docker/{nginx.conf,supervisord.conf,php.ini,warmup.sh}`,
   `backend/.dockerignore`, `docker-compose.yml`. Rebuild with
   `docker compose build backend && docker compose up -d`.
5. **Username login + Doctor knowledge-base access (added).** Every account now
   has a unique `username`, backfilled from the email local part and
   auto-generated for new users (editable by admins). The login form is a single
   **"Email or username"** field and the API accepts either. The Doctor role was
   granted `knowledge-base.view` for read-only Knowledge Base access.
   Backend: new `add_username_to_users_table` migration + `AuthController`
   (identifier resolution), `UserController` (validate/generate), `User` model
   fillable, seeders. Frontend: `LoginPage`, `UserFormModal`, `UsersPage`,
   `SettingsPage`. `POST /auth/login` treats the `email` field as an
   email-or-username identifier (contract preserved; usernames are slug-like and
   never contain `@`).
