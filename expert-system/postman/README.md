# Postman collection — Clinic Medical Expert System

Import `Clinic_Expert_System.postman_collection.json` in Postman
(File > Import, or drag-and-drop).

## Quick start (recommended)

1. Make sure the Docker stack is running:
   `docker compose up -d`
2. In Postman, open the **Laravel API (authorized)** folder and run
   **Login (Admin)**. It posts `admin@clinic.local` / `Admin@12345` and writes
   the Sanctum token into the collection variable `token` (no manual copy/paste).
3. Run **Evaluate (Laravel -> Python)** with `{"symptom_ids":[9,13,31]}`.
   Expect HTTP 200 and `matched_rule_count: 1` (`RULE-FLU-001`).

## Folder overview

### Laravel API (authorized) — `http://127.0.0.1:8000`
- Login (Admin) — `POST /api/v1/auth/login`, stores `token`.
- Evaluate (Laravel -> Python) — `POST /api/v1/expert-system/evaluate`. Requires
  the `expert-system.use` permission (Doctor or Admin). Nurse / Receptionist /
  Patient get 403; missing token gets 401.
- Evaluate empty selection — valid, returns 200 with 0 matches.
- Evaluate invalid symptom — ID 999999 returns 422.
- Evaluate unauthenticated — 401.

### Python Expert System (direct, dev only) — `http://127.0.0.1:8001`
Only reachable locally (loopback); in production this port is not published and
calls go through Laravel. Direct `/evaluate` requires the internal API key only
when `EXPERT_SYSTEM_API_KEY` is set — keep `apiKey` empty in dev.

## Example symptom sets
| symptom_ids | Expected |
|---|---|
| `[9, 13, 31]` | RULE-FLU-001 (high-fever + body-ache + dry-cough) |
| `[35, 37]` | RULE-COLD-001 (runny-nose + sneezing) |
| `[]` | 200, 0 matches |
| `[999999]` | 422 invalid |

IDs are the live `symptoms.id` values in PostgreSQL (`clinic_db`). They are
assigned by the seeders and **can change when the database is re-seeded** — treat
the values above as examples and confirm them against your own instance.

## Demo users
Seeded in `local`/`testing` only (`DemoUsersSeeder`):

| Email | Password | Role |
|---|---|---|
| `admin@clinic.local` | `Admin@12345` | Admin |
| `doctor@clinic.local` | `Password@12345` | Doctor |
| `nurse@clinic.local` | `Password@12345` | Nurse |
| `receptionist@clinic.local` | `Password@12345` | Receptionist |
| `patient@clinic.local` | `Password@12345` | Patient |

Only Admin and Doctor have `expert-system.use`; Nurse has `expert-system.view`
and gets `403` on Evaluate.

## Troubleshooting (Postman)
- `401` on Evaluate: run Login first; check the `token` collection variable.
- `503`: backend can't reach the Python service or DB. Check
  `docker compose logs clinic_backend clinic_expert_system`.
- DNS/port values: edit the collection variables `baseUrl` / `pythonUrl`.