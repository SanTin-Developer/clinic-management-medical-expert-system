# Documentation Index

The RoyalCare Clinic Management System & Medical Expert System is documented in
eleven numbered sections plus an operational appendix. Start with the root
[README](../README.md) if you are new to the repository, then read section 01.

> **Documentation scope:** These guides describe the code in this repository.
> They do not replace clinical, security, privacy, or legal review. Items marked
> **Unverified** have not been confirmed against a running production system.

## Numbered sections

| # | Section | Use it for |
| --- | --- | --- |
| 01 | [Project Overview](01-project-overview/index.md) | Problem statement, objectives, scope, roles, architecture, stack, feature map, honest status summary, repository structure |
| 02 | [Requirements](02-requirements/index.md) | Stakeholders, assumptions, FR-A–FR-M, role–permission matrix, non-functional requirements, acceptance criteria |
| 03 | [System Analysis](03-system-analysis/index.md) | Existing-system problems, proposed system, actors, use cases, activity diagrams, business rules |
| 04 | [System Design](04-system-design/index.md) | Architecture, backend layers, SPA design, API standards, authentication, authorization, sequence diagrams, security |
| 05 | [Database Design](05-database-design/index.md) | ERD, table inventory by domain, views, indexes, seeders, backup rules |
| 06 | [Features](06-features/index.md) | Feature-by-feature specification with status labels and limits |
| 07 | [API Documentation](07-api-documentation/index.md) | Base URL, headers, status codes, full route inventory, internal expert API, error examples |
| 08 | [Frontend Documentation](08-frontend-documentation/index.md) | Admin SPA and public website routes, state, API modules, design system, build notes |
| 09 | [Testing](09-testing/index.md) | Test strategy, backend and expert-system suites, frontend checks, CI, quality gates |
| 10 | [Deployment](10-deployment/index.md) | Compose topology, environment variables, TLS, release sequence, backups, rollback, checklist |
| 11 | [User Guide](11-user-guide/index.md) | Step-by-step workflows for each role, plus the public visitor path |

## Appendix

| Document | Status | Notes |
| --- | --- | --- |
| [Administrator Security Runbook](ADMIN_SECURITY_RUNBOOK.md) | Implemented | Invitations, admin approval, TOTP 2FA, step-up authorization, lockout, and security alerts |
| [Production TLS proxy notes](../docker/nginx/README.md) | Reference | Nginx TLS termination example and deployment checklist |
| [Proprietary Notice](../PROPRIETARY-NOTICE.md) | Required reading | Ownership and usage restrictions |
| [Screenshots](screenshots/) | Placeholder | Drop deployment screenshots here; the folder is tracked with `.gitkeep` |

## Application guides

| Application | Guide |
| --- | --- |
| Laravel API | [backend/README.md](../backend/README.md) |
| Admin dashboard | [frontend/README.md](../frontend/README.md) |
| Public website | [website/README.md](../website/README.md) |
| Expert system | [expert-system/README.md](../expert-system/README.md) |
| Expert-system Postman collection | [expert-system/postman/README.md](../expert-system/postman/README.md) |
| Knowledge-base starter data | [backend/database/data/knowledge_base_100/README.md](../backend/database/data/knowledge_base_100/README.md) |

## Status labels used in these guides

| Label | Meaning |
| --- | --- |
| **Implemented** | Code, routes, migrations, or UI exist in this repository. |
| **Partially implemented** | Present, with the stated limitation. |
| **Proposed** | Design notes only; no implementation in this repository. |
| **Unverified** | Depends on deployment, credentials, or external systems and was not checked. |

## Documentation conventions

- Commands are written for Windows PowerShell unless a shell is named.
- File paths are relative to the repository root unless stated otherwise.
- Placeholders such as `<database-password>` are never real secrets.
- Do not copy `.env` values into issues, chat, screenshots, or documentation.
- Seeded passwords are intentionally not reproduced; read them from the seeder
  files in a local checkout.
- Do not run migrations, seeders, database resets, or destructive Docker volume
  commands against an environment whose data you need to keep.

## Sources of truth

- API routes: `backend/routes/api.php`
- Roles and permissions: `backend/database/seeders/RolePermissionSeeder.php`
- Schema: `backend/database/migrations/`
- Compose services and ports: `docker-compose.yml`
- Development-only override: `docker-compose.override.yml`
- Admin SPA routes: `frontend/src/router/index.jsx`
- Public site routes: `website/src/router/index.jsx`
