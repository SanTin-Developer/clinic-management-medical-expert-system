# 06 · Features Specification

Status labels: **Implemented**, **Partially implemented**, **Proposed**,
**Unverified**.

## 6.1 Authentication and Account Management

### Feature 1: Password login with email or username

- **Status:** Implemented
- **Endpoint:** `POST /api/v1/auth/login`
- **Inputs:** `email` (email or username), `password`
- **Workflow:** Resolve the identifier as email or username → reject a locked
  account with `429` → verify the password → optionally start OTP/2FA →
  issue a Sanctum token.
- **Rules:** Login is limited to 5 attempts per minute per email + IP. Usernames
  are normalized to lowercase. The SPA stores the token and sends
  `Authorization: Bearer <token>`.

### Feature 2: Email OTP for untrusted devices

- **Status:** Implemented, disabled by default (`AUTH_OTP_ENABLED=false`)
- **Endpoints:** `POST /auth/otp/verify`, `POST /auth/otp/resend`
- **Rules:** 6-digit code, 10-minute expiry, 5 attempts, 60-second resend
  cooldown. A successful verification stores an HttpOnly trusted-device cookie.

### Feature 3: TOTP two-factor authentication

- **Status:** Implemented, enforcement disabled by default
  (`AUTH_2FA_MANDATORY=false`)
- **Endpoints:** `/auth/2fa/status`, `/auth/2fa/enroll`, `/auth/2fa/confirm`,
  `DELETE /auth/2fa`, `/auth/2fa/recovery-codes`,
  `POST /auth/2fa/verify`
- **Rules:** 6 digits, 30-second period, ±1 window, 8 recovery codes. When
  enforcement is on, `2fa.enrolled` blocks unenrolled accounts.

### Feature 4: Staff invitations and approval

- **Status:** Implemented
- **Endpoints:** `GET|POST /staff/invitations`,
  `POST /staff/invitations/{invitation}/resend`,
  `DELETE /staff/invitations/{invitation}`,
  `GET /staff/approvals`, `POST /users/{user}/approve`,
  `POST /users/{user}/reject`
- **Rules:** Only a hash of the invitation token is stored; the plaintext token is
  emailed once. Approval and rejection require a step-up grant.

### Feature 5: Password reset and email change

- **Status:** Implemented
- **Endpoints:** `POST /auth/forgot-password`,
  `POST /auth/reset-password/verify-code`, `POST /auth/reset-password`,
  `PUT /auth/password`, `POST /auth/email/change`,
  `POST /auth/email/change/confirm`
- **Rules:** Reset uses an emailed 6-digit code plus a short-lived reset token.
  Email changes require confirmation of a code sent to the new address.

### Feature 6: Sessions, devices, and step-up

- **Status:** Implemented
- **Endpoints:** `/auth/devices`, `/auth/devices/{device}`,
  `/auth/sessions`, `/auth/sessions/others`, `/auth/sessions/{tokenId}`,
  `/auth/step-up`, `/auth/security-events`
- **Rules:** Users can revoke a device or other sessions. Step-up grants are
  short-lived, single-use, and bound to the requested action.

### Feature 7: Account settings and data export

- **Status:** Implemented
- **Endpoints:** `/user/preferences`, `/user/avatar`, `/user/data-export`,
  `DELETE /user/account`
- **Rules:** Avatar upload/removal uses the configured storage disk. Account
  deletion is the user's own request and must be handled according to clinic
  retention policy.

## 6.2 Patients and Documents

### Feature 8: Patient records

- **Status:** Implemented
- **Endpoints:** `GET|POST /patients`, `GET|PUT|PATCH|DELETE /patients/{patient}`
- **Inputs:** Demographic and contact data defined in the patient form request.
- **Rules:** `patients.view` is held by all staff roles and `Patient`;
  create/update by Admin and Receptionist; delete by Admin. Patient-role access
  to other patients' records is constrained by policy and must be treated as a
  privacy-sensitive area.

### Feature 9: Patient documents

- **Status:** Implemented
- **Endpoints:** `GET|POST /patients/{patient}/documents`,
  `GET /documents/{document}/download`, `DELETE /documents/{document}`
- **Rules:** Private storage; authorized download; size and MIME validation;
  optional ClamAV scanning.

## 6.3 Appointments and Outpatient Queue

### Feature 10: Appointment lifecycle

- **Status:** Implemented
- **Endpoints:** `GET|POST /appointments`, `GET|PUT|PATCH|DELETE
  /appointments/{appointment}`, `POST /appointments/{appointment}/cancel`
- **Rules:** Staff with appointment permissions can create/update/cancel.
  `Patient` can create and cancel but not update or delete.

### Feature 11: Outpatient queue

- **Status:** Implemented
- **Endpoints:** `GET|POST /queue-entries`, `GET|PUT|PATCH|DELETE
  /queue-entries/{queueEntry}`, plus `/call`, `/start`, `/complete`, `/skip`,
  `/cancel`, and `GET /queue-entries/appointments`
- **Rules:** Queue actions are explicit transitions; each action is audited by
  the record timestamps/actor fields. UI route: `/queue`.

## 6.4 Clinical Records

### Feature 12: Vital signs

- **Status:** Implemented
- **Endpoints:** `/vital-signs` CRUD
- **Rules:** Nurses and Admin record and update; Admin can delete. Doctors can
  read through medical records but do not receive `vitals.*` in the seeder.

### Feature 13: Consultations and diagnoses

- **Status:** Implemented
- **Endpoints:** `/consultations` and `/diagnoses` CRUD
- **Rules:** Admin and Doctor manage; other roles do not receive write
  permissions.

### Feature 14: Medical records

- **Status:** Implemented
- **Endpoints:** `/medical-records` CRUD
- **Rules:** Admin, Nurse, and Doctor write; Head Nurse and Patient read.

## 6.5 Prescriptions and Pharmacy

### Feature 15: Prescription management

- **Status:** Implemented
- **Endpoints:** `GET|POST /prescriptions`, `GET|PUT|PATCH|DELETE
  /prescriptions/{prescription}`, `POST /prescriptions/{prescription}/cancel`,
  `POST /prescriptions/{prescription}/dispense`
- **Rules:** Admin and Doctor create/update; Admin and Pharmacist update;
  patients read. Dispensing allocates stock through `InventoryService`.

### Feature 16: Medicine catalog

- **Status:** Implemented
- **Endpoints:** `/medicines` CRUD
- **Rules:** Admin and Pharmacist manage. Medicines carry active/recall state and
  a `requires_prescription` flag used by POS.

### Feature 17: Inventory and stock movements

- **Status:** Implemented
- **Endpoints:** `/inventory-items` CRUD, `GET|POST /stock-movements`
- **Rules:** Batch quantity is mutated through `InventoryService` under row
  locks; every change should produce a stock movement.

### Feature 18: Pharmacy dashboard

- **Status:** Implemented
- **Endpoint:** `GET /pharmacy/dashboard`
- **Content:** Stock health, expiry risk, and dispensing activity, backed by the
  inventory tables and dashboard views.

## 6.6 Pharmacy POS

### Feature 19: Product lookup

- **Status:** Implemented
- **Endpoint:** `GET /pharmacy/pos/products`
- **Rules:** Returns sellable medicines with available stock.

### Feature 20: POS checkout

- **Status:** Implemented
- **Endpoint:** `POST /pharmacy/pos/checkout`
- **Inputs:** `idempotency_key` (required UUID), optional `patient_id`,
  optional `discount`, optional `payment` (`amount`, `amount_received`,
  `method`), and `items[]` (`medicine_id`, `quantity`).
- **Workflow:**
  1. Authorize `PharmacySale` create.
  2. Confirm POS tables and the `requires_prescription` column exist; otherwise
     return `503`.
  3. Validate input.
  4. Return the existing sale with `duplicate: true` when the idempotency key is
     already used.
  5. Lock medicine rows; reject inactive, recalled, or prescription-required
     medicines.
  6. Compute subtotal, discount, and total server-side.
  7. Create the sale, invoice, invoice items, sale items, and inventory
     allocations.
  8. Record stock movements through `InventoryService`.
  9. If a payment is supplied, require it to equal the total and mark the invoice
     paid.
  10. Return `201` with the sale, invoice payments, items, and allocations.
- **Errors:** `422` for validation/stock, `503` for missing migrations, `403` for
  unauthorized users.
- **UI:** `/pharmacy/pos`.

### Feature 21: POS limitations

- No void or refund endpoint, despite schema columns for status/void.
- Prescription linking is not accepted by checkout yet.
- Prescription-required medicines are blocked from walk-in sale.
- Duplicate idempotency returns HTTP `200`, not `409`.

### Feature 22: Suppliers and stock receiving

- **Status:** **Proposed only** — no `suppliers`, `stock_receipts`, or
  `stock_receipt_lines` tables exist in `backend/database/migrations/`.
- Proposed migration set:
  1. `suppliers`: unique code, name, contact details, address, active flag,
     timestamps.
  2. `stock_receipts`: unique receipt number, nullable supplier, received date,
     receiving user, external reference, status, notes, timestamps.
  3. `stock_receipt_lines`: receipt, medicine, batch number, expiry date,
     quantity, unit cost, location, and the created inventory batch. A unique
     receipt/batch/medicine constraint prevents a retried receipt from creating
     stock twice.
  4. Optional reconciliation fields on `inventory_items` or stock movements to
     link a received batch to its receipt line.
- Expected behaviour if implemented:
  - Receiving stock creates inventory batches and stock movements atomically.
  - Retries are idempotent.
  - A receipt is immutable once it has created stock; corrections use reversing
    movements.
  - Supplier and purchase-cost reporting reads receipt lines, not free-text
    stock movements.
- Before approval, clinic policy must define:
  - Which medicines are prescription-only (`requires_prescription` exists and is
    enforced by POS).
  - Whether walk-in sales are allowed.
  - Who may receive stock and adjust inventory.
  - Whether purchase costs are recorded and who can view them.
  - The approval and audit requirements for stock corrections.

## 6.7 Billing, Payments, and Reports

### Feature 23: Invoices

- **Status:** Implemented
- **Endpoints:** `GET|POST /invoices`, `GET|PUT|PATCH|DELETE /invoices/{invoice}`,
  `GET /invoices/medicines`, `GET /invoices/{invoice}/pdf`,
  `POST /invoices/{invoice}/payments`, `POST /invoices/{invoice}/cancel`
- **Rules:** Admin and Receptionist manage billing. Totals are derived
  server-side from items, discounts, and payments.

### Feature 24: Payments

- **Status:** Implemented (recorded, not processed)
- **Methods:** `cash`, `card`, `bank_transfer`, `insurance`, `other`
- **Rules:** Payment amount and method are validated; a payment cannot silently
  exceed the balance. No external gateway is called.

### Feature 25: PDF invoices

- **Status:** Implemented
- **Endpoint:** `GET /invoices/{invoice}/pdf`
- **Engine:** `barryvdh/laravel-dompdf`.

### Feature 26: Dashboard analytics and reports

- **Status:** Implemented
- **Endpoints:** `GET /dashboard/analytics`, `/reports/summary`,
  `/reports/appointments`, `/reports/revenue`, `/reports/inventory`,
  `/reports/{report}/export`, `/reports/{report}/pdf`
- **Rules:** `reports.view` is Admin-only in the seeder. Dashboard charts read
  `DashboardMetricsService`, which uses the SQL views.

## 6.8 Inpatient Care

### Feature 27: Wards, rooms, and beds

- **Status:** Implemented
- **Endpoints:** `/wards`, `/rooms`, `/beds` CRUD,
  `POST /beds/{bed}/status`
- **Rules:** Admin and Head Nurse manage; other clinical roles read.

### Feature 28: Admissions and transfers

- **Status:** Implemented
- **Endpoints:** `/admissions` CRUD, `POST /admissions/{admission}/discharge`,
  `/transfer`, `/cancel`
- **Rules:** Receptionist and Doctor create; Head Nurse and Doctor update;
  discharge is restricted to Admin/Head Nurse permissions.

### Feature 29: Nurse assignments and nursing tasks

- **Status:** Implemented
- **Endpoints:** `/assignments`, `/assignments/{assignment}/end`,
  `/nursing/my-patients`, `/nursing-tasks`,
  `POST /nursing-tasks/{nursingTask}/complete`
- **Rules:** Head Nurse manages assignments; nurses see their own patients.
  Overdue tasks generate notifications via the scheduler.

### Feature 30: Medication administration (MAR)

- **Status:** Implemented
- **Endpoints:** `/medication-administrations` CRUD,
  `POST /medication-administrations/{id}/administer`,
  `POST /medication-administrations/{id}/status`
- **Rules:** `medications.record` is held by Nurse, Head Nurse, and Admin.

### Feature 31: Inpatient billing

- **Status:** Implemented
- **Endpoints:** `GET /admissions/{admission}/billing`,
  `POST .../billing/room-charges`, `.../charges`, `.../daily-invoice`,
  `.../payments`, `.../discharge-reconciliation`,
  `POST /admission-charges/{charge}/void`
- **Rules:** Dedicated `inpatient.billing.*` permissions; legacy `billing.*`
  must not grant access.

## 6.9 Staff, Attendance, Scheduling, and Leave

### Feature 32: Staff directory and documents

- **Status:** Implemented
- **Endpoints:** `/staff`, `/staff/departments`, `/staff/job-titles`,
  `/staff/available-users`, `/staff/{staff}/salary`, `/staff/{staff}/link-user`,
  `/staff/{staff}/unlink-user`, `/staff/{staff}/documents`,
  `/staff-documents/{document}/download`
- **Rules:** Admin-only directory management; salary access has separate
  permissions.

### Feature 33: Attendance

- **Status:** Implemented
- **Endpoints:** `/attendance`, `/attendance/today`, `/attendance/clock-in`,
  `/attendance/clock-out`, `/attendance/export`, `/attendance/{record}/correct`,
  `/approve`, `/attendance/sessions`, `/attendance/sessions/{id}/token`,
  `/attendance/sessions/{id}/close`, `POST /attendance/scan`
- **Rules:** All staff can view and clock; management, correction, sessions, and
  export are Admin/Head Nurse. The QR token rotates and scan attempts are
  throttled. The scheduler finalizes sessions every minute.

### Feature 34: Shift scheduling

- **Status:** Implemented
- **Endpoints:** `/shift-templates` CRUD, `/shifts` CRUD,
  `/shifts/board`, `/shifts/uncovered`, `/shifts/publish`,
  `/shifts/{shift}/publish`
- **Rules:** Admin and Head Nurse manage; all staff can view.

### Feature 35: Leave management

- **Status:** Implemented
- **Endpoints:** `/leave-requests` CRUD plus `/approve`, `/reject`, `/cancel`
- **Rules:** Staff request their own leave; Head Nurse and Admin approve.

## 6.10 Communication, Announcements, and Alerts

### Feature 36: Chat

- **Status:** Implemented
- **Endpoints:** `/conversations`, `/conversations/recipients`,
  `/conversations/{conversation}`, `/messages`, `/read`, `/participants`,
  `.../attachment` (signed URL)
- **Rules:** `chat.view`, `chat.create`, `chat.send`; `chat.manage` for
  administrative actions. Attachments cannot be fetched with a bearer token by
  `<img>`/`<video>`, so they use temporary signed URLs.

### Feature 37: Announcements and news

- **Status:** Implemented
- **Endpoints:** `/announcements` CRUD, `/publish`, `/unpublish`, `/schedule`,
  `/archive`, `/restore`, `/readership`, `/audit`; `/news`, `/news/unread-count`,
  `/news/read-all`, `/news/{announcement}/read`
- **Rules:** All roles read announcements; Admin and Head Nurse manage. Scheduled
  publication runs every minute.

### Feature 38: Notifications and system alerts

- **Status:** Implemented
- **Endpoints:** `/notifications`, `/notifications/unread-count`,
  `/notifications/read-all`, `/notifications/{id}/read`,
  `/notification-preferences`, `/system-alerts`, `/system-alerts/summary`,
  `/acknowledge`, `/resolve`
- **Rules:** System alerts are Admin-only because they expose infrastructure
  state. `alerts:generate` runs every five minutes.

### Feature 39: Telegram integration

- **Status:** Implemented when configured
- **Endpoints:** `POST /telegram/webhook` (public, secret header),
  `/telegram/status`, `/telegram/link`, `/telegram/webhook/register`,
  `/telegram/group-config`
- **Rules:** Requires a bot token and webhook secret. The backend runs a
  `telegram:poll` process; delivery attempts are logged.

## 6.11 Website CMS and Public Website

### Feature 40: Website settings and content

- **Status:** Implemented
- **Endpoints:** `/website/admin/settings`, `/pages`, `/services`, `/team`,
  `/gallery`, `/articles`, `/content-blocks`, `/media`,
  `/contact-messages`, `/dashboard`
- **Rules:** Admin-only. Pages and articles support publish/unpublish. Media
  usage is tracked so assets are not deleted while referenced.

### Feature 41: Public website

- **Status:** Implemented
- **Endpoints:** `GET /website/bootstrap`, `/home`, `/pages`, `/pages/{slug}`,
  `/services`, `/team`, `/gallery`, `/articles`, `/articles/{slug}`,
  `/media/{asset}`, and `POST /website/contact`
- **Rules:** Unauthenticated, read-only, published content only. The contact
  endpoint is throttled. No patient or staff data is exposed. Each submission
  notifies website administrators in the notification bell, and the bell entry
  stays in sync with the message's read/unread state.

## 6.12 Medical Expert System

### Feature 42: Rule evaluation

- **Status:** Implemented
- **Endpoints:** `POST /api/v1/expert-system/evaluate` (Laravel proxy);
  `POST /evaluate` on the internal FastAPI service
- **Rules:**
  - Requires `expert-system.use` in Laravel.
  - Forwards only symptom IDs, with `X-Internal-Api-Key` when configured.
  - Active rules only; AND/OR groups; `present`, `absent`, and `expected_value`.
  - Maximum 50 symptom IDs; empty selection returns zero matches.
  - Results ordered by priority; `confidence_score` is not a probability.
  - `422` invalid input, `503` unavailable, `504` timeout.
- **Disclaimer:** Not a medical device, not a diagnosis, no clinically validated
  probability.

### Feature 43: Knowledge-base management

- **Status:** Implemented
- **Endpoints:** `/knowledge-base/diseases`, `/symptoms`, `/expert-rules`
- **Rules:** Admin manages; Doctor reads. Starter data lives in
  `backend/database/data/knowledge_base_100/` and is explicitly **not**
  clinically validated.

### Feature 44: Evaluation audit

- **Status:** Implemented, opt-in
- **Endpoints:** `GET /expert-system/audit-logs`,
  `/expert-system/audit-logs/{auditLog}`
- **Rules:** `EXPERT_AUDIT_ENABLED=true` stores selected symptom IDs; enable only
  with privacy approval.

## 6.13 Audit and Administration

### Feature 45: Activity and security audit

- **Status:** Implemented
- **Endpoints:** `GET /audit-logs`, `/audit-logs/{activityLog}`,
  `/auth/security-events`
- **Rules:** `audit-logs.view` and `security.view` are Admin permissions.

### Feature 46: Global search

- **Status:** Implemented
- **Endpoint:** `GET /search`
- **Rules:** Results are filtered by the caller's permissions per resource.
