# Backend — Laravel API

The Laravel REST API and the shared PostgreSQL database for the RoyalCare Clinic
Management System. It is the single source of truth for clinic data and the only
backend the admin dashboard and public website call.

> **Proprietary software.** This repository is not open source. See
> [../PROPRIETARY-NOTICE.md](../PROPRIETARY-NOTICE.md). The `license` field in
> `composer.json` is inherited Laravel skeleton metadata and does not license
> this project.

## Stack

| Component | Version/source |
|---|---|
| PHP | Docker image uses PHP 8.4; `composer.json` requires `^8.3` |
| Laravel | `^13.17` |
| Authentication | Laravel Sanctum `^4.0` |
| Roles/permissions | `spatie/laravel-permission` `^8.3` |
| WebSockets | Laravel Reverb `^1.12` |
| API docs | Scramble `^0.13.47` |
| PDF | `barryvdh/laravel-dompdf` `^3.1` |
| Database | PostgreSQL 18 in Compose |
| Cache/session/queue | Redis 8 in Compose |
| Tests | PHPUnit `^12.5`, Laravel test runner |

## Quick start (Docker, from the repository root)

```powershell
Copy-Item .env.example .env
Copy-Item backend\.env.example backend\.env
docker compose up -d --build backend postgres redis expert-system
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
```

Expected: the API responds at `http://localhost:8000`, and `/up` returns HTTP
`200`.

Full setup, ports, and troubleshooting:

- [Deployment & Infrastructure](../docs/10-deployment/index.md)
- [Database Design](../docs/05-database-design/index.md)
- [Repository structure](../docs/01-project-overview/index.md)

## Configuration

`backend/.env` is created from `.env.example`. Never commit it.

| Group | Key variables |
|---|---|
| Application | `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL` |
| Database | `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Redis | `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT` |
| Cache/queue/session | `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` |
| Auth | `SANCTUM_EXPIRATION`, `AUTH_*`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS` |
| Files | `DOCUMENTS_DISK`, `DOCUMENTS_MAX_SIZE_KB`, `CLOUDINARY_*`, `FILE_SCANNER_DRIVER` |
| Expert system | `EXPERT_SYSTEM_URL`, `EXPERT_SYSTEM_TIMEOUT`, `EXPERT_SYSTEM_API_KEY` |
| Realtime | `REVERB_*` |
| Telegram | `TELEGRAM_*` |

Inside Compose, the database, Redis, and expert-system hosts are the service
names `postgres`, `redis`, and `expert-system`. Do not use `localhost` inside a
container.

The Compose stack injects `APP_ENV: local`, `DB_CONNECTION: pgsql`, and Redis
cache/session/queue settings as environment variables. Explicit container
environment values take precedence over `.env`.

See [Deployment §10.3](../docs/10-deployment/index.md) for the full variable
list and production rules.

## Layout

| Path | Responsibility |
|---|---|
| `routes/api.php` | All routes under `/api/v1` |
| `routes/channels.php` | Broadcast channel authorization |
| `app/Http/Controllers/Api/` | API controllers |
| `app/Http/Requests/` | Form request validation |
| `app/Http/Middleware/` | Account, password, trusted-device, 2FA, step-up, security headers |
| `app/Models/` | Eloquent models |
| `app/Policies/` | Model-level authorization |
| `app/Services/` | Billing, inventory, pharmacy, auth, files, Telegram, website |
| `app/Jobs/`, `app/Events/`, `app/Listeners/` | Queued work and events |
| `app/Console/Commands/` | Artisan commands such as `telegram:poll` |
| `config/` | Application configuration |
| `database/migrations/` | Schema changes |
| `database/seeders/` | Roles, baseline content, and local demo data |
| `database/data/knowledge_base_100/` | Knowledge-base starter data |
| `tests/` | PHPUnit unit and feature tests |
| `docker/` | Nginx, PHP, Supervisor, and warmup configuration |

## Commands

```powershell
docker compose exec backend php artisan about
docker compose exec backend php artisan route:list
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan test
docker compose exec -T backend ./vendor/bin/pint --test
```

Run a single test:

```powershell
docker compose exec backend php artisan test --filter=AppointmentTest
```

Last verified in this working copy on 2026-10-29: **608 tests passed**.

Do not run `migrate:fresh`, `db:wipe`, `migrate:rollback`, or `db:seed` against
real clinic data. See [Database Design](../docs/05-database-design/index.md).

## Authentication and authorization

- Sanctum bearer tokens are issued by `POST /api/v1/auth/login`.
- Protected routes use `auth:sanctum`, `throttle:api`, `account.active`, and
  `password.current`, with optional `verified`, `device.trust`, and
  `2fa.enrolled`.
- Roles: `Admin`, `Receptionist`, `Nurse`, `Head Nurse`, `Doctor`,
  `Pharmacist`, `Patient`.
- Permissions are defined in `database/seeders/RolePermissionSeeder.php`.

Full details: [API Documentation](../docs/07-api-documentation/index.md) and
[System Design §4.6–4.7](../docs/04-system-design/index.md).

## Runtime processes

`docker/supervisord.conf` starts:

- PHP-FPM
- Nginx on container port `8000`
- Queue worker
- Laravel Reverb on container port `8080`
- Telegram poller
- Laravel scheduler

A storage-permission fix runs once at container start. If queued work, realtime
updates, or scheduled tasks fail, check `docker compose logs backend`.

## API documentation

Scramble serves:

- UI: `http://localhost:8000/docs/api`
- OpenAPI JSON: `http://localhost:8000/docs/api.json`

In `local`, docs are open. In other environments they are restricted through the
`viewApiDocs` gate to authenticated administrators.

## Tests and quality tools

| Tool | Command |
|---|---|
| PHPUnit via Artisan | `php artisan test` |
| Pint style check | `./vendor/bin/pint --test` |
| Pint auto-fix | `./vendor/bin/pint` |

The test configuration (`phpunit.xml`) uses in-memory SQLite, array cache, sync
queue, and the array mail transport. Tests do not require PostgreSQL.

## Deployment notes

- The image is built from `backend/Dockerfile` and includes Nginx, PHP-FPM, and
  Supervisor.
- Migrations are not run automatically.
- The base `docker-compose.yml` bind-mounts the source and sets
  `APP_ENV: local`; create a production override.
- Use `APP_ENV=production`, `APP_DEBUG=false`, a strong `APP_KEY`, HTTPS
  cookies, and exact CORS origins.

See [Deployment & Infrastructure](../docs/10-deployment/index.md).

## Ownership

Copyright © 2026 SanTin. All Rights Reserved. See
[../PROPRIETARY-NOTICE.md](../PROPRIETARY-NOTICE.md).
