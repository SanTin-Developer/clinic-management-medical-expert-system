# 02 · Requirements

## 2.1 Stakeholders

| Stakeholder | Interest |
| --- | --- |
| **Clinic owner / administrator** | Operational control, staff accounts, security, reporting, website content |
| **Receptionist** | Fast patient registration, appointments, queue, billing |
| **Doctor** | Complete clinical history, consultations, prescriptions, expert-system support |
| **Nurse / Head Nurse** | Vitals, nursing tasks, medication administration, ward and bed control |
| **Pharmacist** | Accurate stock, expiry control, dispensing, POS sales |
| **Patient** | Correct personal record, appointments, prescriptions; limited read access |
| **Public visitor** | Clinic information, services, team, news, contact |
| **Developer / operator** | Deployable stack, migrations, tests, logs, backups, documentation |

## 2.2 Assumptions and Constraints

- The clinic runs a single tenant; there is no multi-clinic isolation.
- PostgreSQL 18 is the system of record. Redis is disposable cache/session/queue
  storage.
- The expert system is reachable only inside the private Docker network.
- Email delivery requires a working SMTP or API mail provider. Local development
  can use log/array mail, in which case OTP and invitation emails are not
  delivered.
- Payments are recorded manually; no gateway is integrated.
- File uploads require a writable private disk. Cloudinary/S3 is optional.
- The base Compose file is a development stack and is not production-safe.
- Production deployment, TLS, and backups must be configured per environment.

## 2.3 Functional Requirements

### FR-A — Authentication and account security

| ID | Requirement |
| --- | --- |
| FR-A1 | A user can sign in with email or username plus password and receives a Sanctum bearer token. |
| FR-A2 | Failed sign-ins are rate-limited; optional progressive lockout can temporarily block an account. |
| FR-A3 | When OTP login is enabled, an untrusted device must verify an emailed one-time code before a token is issued. |
| FR-A4 | A trusted-device cookie can remember a device for a configured number of days. |
| FR-A5 | When 2FA is mandatory, the user must confirm a TOTP code or a recovery code. |
| FR-A6 | A user can enroll, confirm, disable, and regenerate recovery codes for TOTP 2FA. |
| FR-A7 | A user can request a password reset code by email, verify the code, and set a new password. |
| FR-A8 | A user can change their password, request a two-stage email change, and review security events. |
| FR-A9 | A user can list and revoke trusted devices and other sessions. |
| FR-A10 | Administrators can invite staff, resend or revoke invitations, and approve or reject pending accounts. |
| FR-A11 | Sensitive account actions (suspend, disable, approve) require a short-lived step-up grant. |
| FR-A12 | The last active administrator cannot be suspended or disabled. |
| FR-A13 | Users can export their own data and request account deletion through account settings. |

### FR-B — Patients and documents

| ID | Requirement |
| --- | --- |
| FR-B1 | Authorized staff can list, search, create, view, update, and delete patient records. |
| FR-B2 | Patient documents can be uploaded, listed, downloaded, and deleted. |
| FR-B3 | Document downloads use signed or authenticated endpoints; files are not world-readable. |
| FR-B4 | Upload size and type are validated according to configuration. |

### FR-C — Appointments and outpatient queue

| ID | Requirement |
| --- | --- |
| FR-C1 | Staff can create, view, update, cancel, and delete appointments. |
| FR-C2 | Patients with the `Patient` role can create and cancel their own appointments. |
| FR-C3 | Queue entries can be created, called, started, completed, skipped, cancelled, and removed. |
| FR-C4 | Queue entries are linked to appointments where applicable and are permission-guarded. |

### FR-D — Clinical records

| ID | Requirement |
| --- | --- |
| FR-D1 | Vitals can be recorded, viewed, updated, and deleted by authorized clinical staff. |
| FR-D2 | Consultations and diagnoses can be created and updated by doctors. |
| FR-D3 | Medical records provide a consolidated clinical view and are auditable. |
| FR-D4 | Clinical reads and writes are restricted by role and policy. |

### FR-E — Prescriptions and dispensing

| ID | Requirement |
| --- | --- |
| FR-E1 | Doctors create and update prescriptions with medicine items. |
| FR-E2 | Pharmacists can review and dispense prescriptions. |
| FR-E3 | Dispensing allocates stock from inventory batches and records stock movements. |
| FR-E4 | Prescriptions can be cancelled; cancellation rules are enforced by the controller and policy. |
| FR-E5 | Patients can view their own prescriptions. |

### FR-F — Pharmacy, inventory, and POS

| ID | Requirement |
| --- | --- |
| FR-F1 | The medicine catalog supports create, update, delete, and search. |
| FR-F2 | Inventory batches track quantity, expiry, location, and stock movements. |
| FR-F3 | The pharmacy dashboard summarizes stock health, expiry risk, and dispensing activity. |
| FR-F4 | POS lists sellable medicines with available stock. |
| FR-F5 | POS checkout requires a UUID idempotency key and at least one item. |
| FR-F6 | POS calculates subtotal, discount, and total on the server; clients cannot send prices. |
| FR-F7 | POS rejects inactive, recalled, or prescription-required medicines and insufficient stock. |
| FR-F8 | POS creates the sale, invoice, invoice items, sale items, allocations, and stock movements in one transaction. |
| FR-F9 | A repeated idempotency key returns the original sale with `duplicate: true`. |
| FR-F10 | POS optional payment must equal the server-calculated total. |

### FR-G — Billing and payments

| ID | Requirement |
| --- | --- |
| FR-G1 | Invoices can be created, viewed, updated, cancelled, and deleted by authorized billing staff. |
| FR-G2 | Invoice items and medicine lines are validated. |
| FR-G3 | Payments are recorded against invoices, with cash, card, bank transfer, insurance, and other methods. |
| FR-G4 | Invoice totals and balances are derived from recorded items and payments. |
| FR-G5 | Invoice PDFs can be generated for authorized users. |
| FR-G6 | Reports cover summary, appointments, revenue, and inventory, with CSV/PDF export. |

### FR-H — Inpatient care

| ID | Requirement |
| --- | --- |
| FR-H1 | Wards, rooms, and beds can be managed; bed status can be updated. |
| FR-H2 | Admissions can be created, updated, transferred, discharged, and cancelled. |
| FR-H3 | Nurses can be assigned to admitted patients and assignments can be ended. |
| FR-H4 | Nursing tasks can be created, updated, completed, and flagged overdue. |
| FR-H5 | Medication administration records support administration and status updates. |
| FR-H6 | Inpatient billing supports room charges, ad-hoc charges, daily invoices, payments, voiding charges, and discharge reconciliation. |
| FR-H7 | Inpatient financial data is protected by dedicated inpatient permissions, not legacy billing permissions. |

### FR-I — Staff, attendance, scheduling, and leave

| ID | Requirement |
| --- | --- |
| FR-I1 | A staff directory tracks departments, job titles, contact data, salary, and documents. |
| FR-I2 | Staff records can be linked to or unlinked from user accounts. |
| FR-I3 | Staff can clock in and out, and administrators can correct or approve records. |
| FR-I4 | Rotating QR attendance sessions can be created, displayed, scanned, and closed. |
| FR-I5 | Shift templates and shifts can be created, published, and inspected for coverage. |
| FR-I6 | Staff can request leave; Head Nurse and Admin can approve or reject it. |

### FR-J — Communication and content

| ID | Requirement |
| --- | --- |
| FR-J1 | Staff can start direct or group conversations and exchange messages. |
| FR-J2 | Message attachments use signed URLs. |
| FR-J3 | Announcements support draft, publish, schedule, archive, restore, readership, and audit. |
| FR-J4 | News is audience-filtered and supports read state. |
| FR-J5 | Users receive in-app notifications and can manage notification preferences. |
| FR-J6 | System alerts are generated on a schedule and can be acknowledged or resolved. |
| FR-J7 | Telegram accounts can be linked and group notification settings managed. |

### FR-K — Website CMS and public site

| ID | Requirement |
| --- | --- |
| FR-K1 | Administrators manage site settings, pages, services, team profiles, gallery, articles, media, and content blocks. |
| FR-K2 | Pages and articles support publish and unpublish actions. |
| FR-K3 | Contact messages from the public site can be read and marked read/unread or deleted. |
| FR-K4 | The public site reads only published content through unauthenticated endpoints. |
| FR-K5 | The public contact form is throttled. |

### FR-L — Expert system

| ID | Requirement |
| --- | --- |
| FR-L1 | Authorized users submit selected symptom IDs to `POST /expert-system/evaluate`. |
| FR-L2 | Laravel validates the request, forwards only symptom IDs, and enforces `expert-system.use`. |
| FR-L3 | The Python service matches active rules with AND/OR groups and `present`/`absent`/`expected_value` conditions. |
| FR-L4 | Results are deterministic and ordered by rule priority; confidence is configuration, not probability. |
| FR-L5 | The service returns `422` for invalid or unknown symptoms and `503`/`504` for availability or timeout failures. |
| FR-L6 | Laravel can store and list evaluation audits when the audit feature is enabled. |

### FR-M — Audit and administration

| ID | Requirement |
| --- | --- |
| FR-M1 | User activity is recorded and visible to users with `audit-logs.view`. |
| FR-M2 | Expert evaluation audits are visible from the Expert page. |
| FR-M3 | The dashboard exposes analytics from a single endpoint. |
| FR-M4 | System health alerts are restricted to administrators. |

## 2.4 Role–Permission Matrix

Derived from `backend/database/seeders/RolePermissionSeeder.php`. `✓` means the
role receives at least one permission in that area; the Notes column states
whether access is read-only, manage, or a subset. Admin receives every
permission.

| Area | Admin | Receptionist | Nurse | Head Nurse | Doctor | Pharmacist | Patient |
| --- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Users | ✓ manage | — | — | view only | — | — | — |
| Staff directory | ✓ manage | — | — | — | — | — | — |
| Security events | ✓ view | — | — | — | — | — | — |
| Patients | ✓ manage | ✓ create/update | view | view | view | view | view |
| Patient documents | ✓ manage | view | ✓ manage | ✓ manage | ✓ manage | — | — |
| Appointments | ✓ manage | ✓ create/update/cancel | view | view | view | — | ✓ create/cancel |
| Outpatient queue | ✓ manage | ✓ manage | ✓ manage | ✓ manage | view | — | — |
| Vitals | ✓ manage | — | ✓ create/update | view | — | — | — |
| Consultations | ✓ manage | — | — | — | ✓ create/update | — | — |
| Diagnoses | ✓ manage | — | — | — | ✓ create/update | — | — |
| Medical records | ✓ manage | — | ✓ create/update | view | ✓ create/update | — | view |
| Prescriptions | ✓ manage | — | view | view | ✓ create/update | ✓ view/update | view |
| Medicines | ✓ manage | — | view | view | view | ✓ manage | — |
| Inventory / POS | ✓ manage | — | — | — | — | ✓ manage | — |
| Billing | ✓ manage | ✓ view/create/update | — | — | — | — | — |
| Inpatient view | ✓ | ✓ | ✓ | ✓ | ✓ | — | — |
| Inpatient billing | ✓ manage | ✓ view/manage | — | view | — | — | — |
| Wards and beds | ✓ manage | view | view | ✓ manage | view | — | — |
| Admissions | ✓ manage | ✓ create | view | ✓ update/discharge | ✓ create/update | — | — |
| Nursing tasks / MAR | ✓ manage | — | ✓ | ✓ | view | — | — |
| Assignments | ✓ manage | — | view | ✓ manage | — | — | — |
| Expert system | ✓ use | — | view | view | ✓ use | — | — |
| Knowledge base | ✓ manage | — | — | — | view | — | — |
| Reports | ✓ | — | — | — | — | — | — |
| Audit logs | ✓ | — | — | — | — | — | — |
| Announcements | ✓ manage | view | view | ✓ manage | view | view | view |
| Website CMS | ✓ manage | — | — | — | — | — | — |
| Leave | ✓ manage/approve | ✓ own | ✓ own | ✓ approve | ✓ own | ✓ own | — |
| Attendance | ✓ manage | ✓ clock | ✓ clock | ✓ manage | ✓ clock | ✓ clock | — |
| Scheduling | ✓ manage | view | view | ✓ manage | view | view | — |
| Chat | ✓ manage | ✓ chat | ✓ chat | ✓ chat | ✓ chat | ✓ chat | — |
| Notifications | ✓ manage | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| System health | ✓ | — | — | — | — | — | — |
| Telegram | ✓ manage | ✓ connect | ✓ connect | ✓ connect | ✓ connect | ✓ connect | — |

Notes:

- A permission check is the security boundary. Hiding a navigation item in the SPA
  is not authorization.
- Some controller and policy checks add record-level rules that are stricter than
  the role table, for example the last-admin protection and step-up middleware.
- `Patient` is a real role, but there is no separate patient portal UI.

## 2.5 Non-Functional Requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-1 | Security | Passwords are hashed; bearer tokens are stored as Sanctum personal access tokens; secrets are never committed. |
| NFR-2 | Security | Sensitive endpoints are rate-limited per user/IP and protected by middleware such as `account.active`, `password.current`, `device.trust`, `2fa.enrolled`, and `step.up`. |
| NFR-3 | Security | File downloads are authorized or signed; storage is private by default. |
| NFR-4 | Privacy | Clinical and patient data is not exposed on public endpoints. Public website responses are limited to published content. |
| NFR-5 | Integrity | POS and dispensing operations run in database transactions with row locks. |
| NFR-6 | Reliability | Scheduled commands are idempotent and use `withoutOverlapping` where appropriate. |
| NFR-7 | Performance | Dashboard analytics, reports, and list endpoints are paginated or aggregated; Redis caches sessions, queues, and rate limits. |
| NFR-8 | Usability | The admin SPA provides permission-aware navigation, loading states, toasts, and responsive layouts. |
| NFR-9 | Compatibility | Modern evergreen browsers; Node 20+ for SPA builds; PHP 8.3+; Python 3.12 for the expert system. |
| NFR-10 | Maintainability | Pint formatting, PHPUnit tests, pytest, Oxlint, Bandit, and pip-audit are available. |
| NFR-11 | Observability | Laravel logs, queue failure records, security events, audit logs, and system alerts are available. |
| NFR-12 | Portability | Docker Compose provides a reproducible local stack; production uses the same images with a hardened override. |
| NFR-13 | Internationalization | Khmer text (Kantumruy Pro) and a bilingual knowledge-base starter are supported. Full admin UI translation is **Unverified**. |

## 2.6 Acceptance Criteria Summary

- A new staff member can be invited, accept the invitation, be approved, and sign
  in with the permissions of their assigned role.
- An unauthorized role receives `403` on protected API routes, even if it guesses
  a URL.
- A doctor can record a consultation, diagnosis, and prescription; a pharmacist
  can dispense it and stock decreases accordingly.
- A POS sale with a duplicate idempotency key does not create a second sale.
- An inpatient admission can generate room charges, accept payments, and be
  reconciled at discharge.
- A website editor can publish a page or article that appears on the public site
  without a code deployment.
- An expert-system evaluation returns matching rules and is rejected when the
  caller lacks `expert-system.use`.
- `php artisan test` and `pytest` pass; both SPAs build successfully.
