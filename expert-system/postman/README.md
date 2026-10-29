# Postman Collection — Clinic Medical Expert System

Import `Clinic_Expert_System.postman_collection.json` in Postman
(File → Import, or drag-and-drop).

This collection is a development aid. It is not part of the application runtime.
Do not use production credentials in Postman, and do not commit a modified
collection that contains secrets.

## Quick start

1. Start the Docker stack from the repository root:

   ```powershell
   docker compose up -d
   ```

2. Open the **Laravel API (authorized)** folder and run **Login (Admin)**.
   The request uses the seeded local administrator email. Set the password field
   to the local password from `backend/database/seeders/AdminUserSeeder.php`, or
   change the request to use your own local account.
3. The login request writes the Sanctum token into the collection variable
   `token`.
4. Run **Evaluate (Laravel → Python)** with `{"symptom_ids":[9,13,31]}`.
5. Expect HTTP `200` and `matched_rule_count: 1` (`RULE-FLU-001`) on a freshly
   seeded demo database.

Passwords are intentionally not reproduced in this guide. Read them from the
local seeder or use your own account.

## Folder overview

### Laravel API (authorized) — `http://127.0.0.1:8000`

| Request | Behaviour |
|---|---|
| Login (Admin) | `POST /api/v1/auth/login`; stores `token` |
| Evaluate (Laravel → Python) | `POST /api/v1/expert-system/evaluate`; requires `expert-system.use` |
| Evaluate empty selection | Valid; returns `200` with 0 matches |
| Evaluate invalid symptom | ID `999999` returns `422` |
| Evaluate unauthenticated | Returns `401` |

`Admin` and `Doctor` have `expert-system.use`. `Nurse`, `Head Nurse`,
`Receptionist`, `Pharmacist`, and `Patient` do not, so they receive `403`.
`Nurse` may still have `expert-system.view` for read-only screens.

### Python Expert System (direct, development only) — `http://127.0.0.1:8001`

This port is published only by the development override
(`docker-compose.override.yml`) and only on loopback. In production the service is
private and calls go through Laravel.

Direct `/evaluate` requires the `X-Internal-Api-Key` header only when
`EXPERT_SYSTEM_API_KEY` is set. Keep the `apiKey` collection variable empty when
the key is not configured.

## Example symptom sets

| `symptom_ids` | Expected |
|---|---|
| `[9, 13, 31]` | `RULE-FLU-001` (high fever + body ache + dry cough) |
| `[35, 37]` | `RULE-COLD-001` (runny nose + sneezing) |
| `[]` | `200`, 0 matches |
| `[999999]` | `422` invalid |

IDs are live `symptoms.id` values in PostgreSQL. They are assigned by seeders and
**can change when the database is re-seeded**. Treat the values above as examples
and confirm them against your own instance.

## Demo users

Demo users are seeded in `local` and `testing` only by
`backend/database/seeders/DemoUsersSeeder.php`. The seeder is the source of truth
for emails, roles, and local-only passwords.

Typical local accounts:

| Email | Role |
|---|---|
| `admin@clinic.local` | Admin |
| `doctor@clinic.local` | Doctor |
| `nurse@clinic.local` | Nurse |
| `receptionist@clinic.local` | Receptionist |
| `patient@clinic.local` | Patient |

Use these accounts only in a local or test environment. Do not seed demo data
into production.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `401` on Evaluate | Run Login first; check the `token` collection variable |
| `403` on Evaluate | Use an account with `expert-system.use` (Admin or Doctor) |
| `422` on Evaluate | Confirm the symptom IDs exist and are active |
| `503` | Backend cannot reach the Python service or database; check `docker compose logs clinic_backend clinic_expert_system` |
| `504` | The expert-system request timeout expired; check database performance and `EXPERT_SYSTEM_REQUEST_TIMEOUT_SECONDS` |
| Wrong host or port | Edit the collection variables `baseUrl` and `pythonUrl` |

## Related documentation

- [Expert system README](../README.md)
- [API Documentation](../../docs/07-api-documentation/index.md)
- [Testing](../../docs/09-testing/index.md)
- [Proprietary Notice](../../PROPRIETARY-NOTICE.md)
