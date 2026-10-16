# Clinic Management & Medical Expert System

A clinic management platform for RoyalCare Clinic, split into four applications:

| Path              | Purpose                                                                                       |
| ----------------- | --------------------------------------------------------------------------------------------- |
| `backend/`        | Laravel REST API and the shared PostgreSQL database. The single source of truth for all data.  |
| `frontend/`       | Internal admin dashboard: patients, appointments, clinical records, pharmacy, billing, reports, staff/attendance/chat, users & roles, **and the Admin-only Website Management CMS**. |
| `website/`        | Standalone public-facing informational website (Home, About, Services, Team, Gallery, News & Health, Contact, Privacy/Terms). Public visitors do not authenticate. |
| `expert-system/`  | Python (FastAPI) medical expert system, reached by the backend over the private network.       |

The public website and the admin dashboard are independent React/Vite SPAs that
both talk to `backend/`. The website only reads **published** content through
public (unauthenticated) endpoints; all private clinic features stay in
`frontend/` behind authentication and RBAC.

## Prerequisites

- Node.js 22+ and npm
- Docker + Docker Compose (for the backend, database, Redis, and expert system)
- PHP 8.5 / Composer (only if running the backend outside Docker)

## Backend (API + database)

```sh
cp .env.example .env            # set secrets; never commit real values
docker compose up -d            # backend :8000, postgres, redis, expert-system (internal)
```

The API is served at `http://localhost:8000/api/v1`. Public website endpoints
live under `/api/v1/website/*`; admin CMS endpoints under
`/api/v1/website/admin/*` (authenticated + permission-gated).

## Admin Dashboard (`frontend/`)

```sh
cd frontend
npm install
cp .env.example .env            # set VITE_API_URL (default http://localhost:8000/api/v1)
npm run dev                     # http://localhost:5173
npm run build                   # production build → dist/
```

Included modules: authentication, dashboard, patients, appointments, medical
records, prescriptions, wards/admissions/nursing/MAR, pharmacy & inventory,
billing, reports, staff directory, attendance, scheduling, leave, announcements,
team chat, users & roles, invitations/approvals, audit logs, settings, and the
**Website Management CMS**.

## Public Website (`website/`)

```sh
cd website
npm install
cp .env.example .env            # set VITE_API_URL
npm run dev                     # http://localhost:5174
npm run build                   # production build → dist/
```

See `website/README.md` for routes and deployment notes (SPA fallback).

### API base URL

Both SPAs read `VITE_API_URL` (no trailing slash, matching the backend prefix
`api/v1`). Set it in each app's `.env`; the default is
`http://localhost:8000/api/v1`. For the website origin to call the API, add it
to the backend `CORS_ALLOWED_ORIGINS` (see `backend/.env.example`).

## How the CMS publishes content to the website

Administrators use **Website Management** in the admin dashboard
(`/website/*`) to manage branding, homepage content, pages, services, public team
profiles, articles (News & Health), the gallery, contact details, and SEO. Only
content marked **published** is returned by the public `/api/v1/website/*`
endpoints, so drafts never appear on the public site. The `website/` app fetches
that published content at runtime.

## Build both frontends

```sh
cd frontend && npm run build
cd ../website && npm run build
```

## Docker Compose (optional full stack)

`docker compose up -d` builds the backend, database, Redis, and expert system,
plus the `frontend` (`FRONTEND_PORT`, default 5173) and `website`
(`WEBSITE_PORT`, default 5174) dev servers. Copy `.env.example` to `.env` first
and adjust ports/secrets.

## Independent deployment

`frontend/` and `website/` produce static `dist/` bundles and can be deployed
separately (any static host / CDN / Nginx with SPA fallback). They only need the
backend API URL at build time and must be listed in the backend CORS origins.

See each app's `README.md` for details.
