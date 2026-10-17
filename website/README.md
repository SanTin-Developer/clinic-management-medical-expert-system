# RoyalCare Clinic — Public Website

Standalone public-facing informational website for RoyalCare Clinic. It is a
React (Vite) single-page app that consumes the **public, read-only** endpoints of
the shared Laravel API (`../backend`). All content is managed from the Admin
Dashboard's Website Management CMS (`../frontend`).

This is a separate application from the admin dashboard. It contains **no**
authentication, patient data, or other private clinic features.

> **Staff / administration:** the internal Admin Dashboard (clinic management +
> Website Management CMS) lives in `../frontend` and runs at `http://localhost:5173`
> in development. It is intentionally **not** linked from the public website; staff
> reach it directly at its own URL.

## Stack

- React 19 + Vite (JavaScript, JSX)
- React Router v7 (`createBrowserRouter`)
- TanStack Query (server state) + axios (API client)
- Tailwind CSS v4 (`@tailwindcss/vite`) with tokens in `src/styles/globals.css`
- lucide-react icons, clsx + tailwind-merge (`cn`), class-variance-authority
- Geist + Kantumruy Pro variable fonts
- Oxlint for linting

## Quick start

```sh
npm install
cp .env.example .env     # then set VITE_API_URL if needed
npm run dev              # http://localhost:5174
npm run build            # production build → dist/
npm run preview          # preview the production build
npm run lint
```

The backend must be running and reachable (default `http://localhost:8000`).

## Environment

Copy `.env.example` to `.env`:

| Variable       | Purpose                                            | Default                         |
| -------------- | -------------------------------------------------- | ------------------------------- |
| `VITE_API_URL` | Base URL of the Laravel API (no trailing slash)    | `http://localhost:8000/api/v1`  |

For the browser to call the API from `http://localhost:5174`, the backend
`CORS_ALLOWED_ORIGINS` must include this origin (see `../backend/.env.example`).

## Routes

| Path                    | Page                                  |
| ----------------------- | ------------------------------------- |
| `/` and `/home`         | Home                                  |
| `/about`                | About Us (CMS page `about`)           |
| `/services`             | Services                              |
| `/team`                 | Our Team                              |
| `/gallery`              | Facilities & Gallery                  |
| `/health-news` (`/news`) | News & Health index                  |
| `/health-news/:slug` (`/news/:slug`) | Article detail             |
| `/contact`              | Contact Us                            |
| `/privacy-policy`       | Privacy Policy (CMS page)             |
| `/terms-of-use` (`/terms`) | Terms of Use (CMS page)            |
| `/pages/:slug`          | Any other published CMS page          |

## API integration

All requests target the Laravel backend prefix `/api/v1` and only fetch
**published** content:

- `GET /website/bootstrap` — site settings, footer pages, services
- `GET /website/home` — homepage sections
- `GET /website/pages`, `/website/pages/{slug}`
- `GET /website/services`, `/website/team`, `/website/gallery`
- `GET /website/articles`, `/website/articles/{slug}`
- `GET /website/media/{id}` — served media

No authentication token is used. Loading, empty, and error states are handled
per page.

## Content not yet verified

Some homepage copy reproduces the design reference and is **not** verified
clinic data. Confirm or replace it before publishing:

- **Hero highlights** — "10,000+ Happy Patients", "50+ Specialists" and
  "8AM - 8PM" (`HERO_STATS` in `src/constants/index.js`). These are reference
  figures, not confirmed statistics.
- **Partner strip** — `PARTNER_PLACEHOLDERS` renders five styled marks
  (PharmaHealth, MediCare, LifePlus, HealthFirst, WellnessCo) as icon + wordmark.
  These are design-reference names, not confirmed partnerships; replace them with
  real partners (ideally CMS-backed) before publishing.

Hero copy (eyebrow, heading, description), the about summary and the hero image
are editable from the Admin CMS (Website Management → Settings). The stat cards
and partner strip remain code-level placeholders until CMS fields are added.

## Media assets

| File                               | Source                    | License        |
| ---------------------------------- | ------------------------- | -------------- |
| `public/media/hero-background.png` | Supplied by project owner | Project asset  |
| `public/media/about-clinic.jpg`    | Pexels (photo 4989168)    | Pexels License |
| `public/royalcare-logo.png`        | Project asset             | Project asset  |

`about-clinic.jpg` is a Pexels stock photo (free to use, no attribution
required); replace it with a real RoyalCare facility photo when available. The
hero background can be overridden without a code change by uploading a
`hero_image` in the CMS.

## Deployment

The app is a SPA — build with `npm run build` and serve `dist/` behind a web
server configured to fall back to `index.html` for unknown paths (so direct
navigation to nested routes such as `/health-news/foo` works on refresh).

Nginx example:

```nginx
location / {
  try_files $uri $uri/ /index.html;
}
```

Set `VITE_API_URL` at build time to the deployed API URL and add the deployed
origin to the backend `CORS_ALLOWED_ORIGINS`.

The dev server also supports deep links out of the box (Vite SPA fallback).
