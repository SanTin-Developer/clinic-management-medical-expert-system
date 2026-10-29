# RoyalCare Clinic — Public Website

Standalone public-facing informational website for RoyalCare Clinic. It is a
React (Vite) single-page application that consumes the **public, read-only**
endpoints of the shared Laravel API (`../backend`). All content is managed from
the Admin Dashboard's Website Management CMS (`../frontend`).

This is a separate application from the admin dashboard. It contains **no**
authentication, patient data, or private clinic features.

> **Proprietary software.** This repository is not open source. See
> [../PROPRIETARY-NOTICE.md](../PROPRIETARY-NOTICE.md).

> **Staff / administration:** the internal Admin Dashboard lives in
> `../frontend` and runs at `http://localhost:5173` in development. It is
> intentionally not linked from the public website; staff reach it directly at
> its own URL.

## Stack

| Component | Technology |
|---|---|
| UI | React 19, JavaScript/JSX |
| Build | Vite |
| Routing | React Router v7 (`createBrowserRouter`) |
| Server state | TanStack Query |
| API | axios |
| Styling | Tailwind CSS v4 (`@tailwindcss/vite`), tokens in `src/styles/globals.css` |
| Icons and utilities | lucide-react, clsx, tailwind-merge, class-variance-authority |
| Fonts | Geist and Kantumruy Pro variable fonts (Khmer support) |
| Lint | Oxlint |

## Quick start

With Docker, from the repository root:

```powershell
docker compose up -d website
```

On the host:

```powershell
cd website
Copy-Item .env.example .env
npm install
npm run dev
```

Expected: `http://localhost:5174`.

The backend must be running and reachable at `http://localhost:8000`.

## Commands

```powershell
npm run dev        # Vite development server
npm run build      # production build in dist/
npm run preview    # preview the production build
npm run lint       # Oxlint; warnings do not fail the command
```

Last verified in this working copy on 2026-10-29:

- `npm run lint` exits `0` with warnings.
- `npm run build` succeeds.

## Environment

Copy `.env.example` to `.env`:

| Variable | Purpose | Default |
|---|---|---|
| `VITE_API_URL` | Laravel API base URL, no trailing slash | `http://localhost:8000/api/v1` |

Vite reads this at build time; rebuild after changing it.

For the browser to call the API from `http://localhost:5174`, the backend
`CORS_ALLOWED_ORIGINS` must include this exact origin.

## Routes

| Path | Page |
|---|---|
| `/` and `/home` | Home |
| `/about` | About Us (CMS page `about`) |
| `/services` | Services |
| `/team` | Our Team |
| `/gallery` | Facilities and Gallery |
| `/health-news` and `/news` | News and Health index |
| `/health-news/:slug` and `/news/:slug` | Article detail |
| `/contact` | Contact Us |
| `/privacy-policy` and `/pages/privacy-policy` | Privacy Policy |
| `/terms-of-use`, `/terms`, and `/pages/terms-of-use` | Terms of Use |
| `/pages/about` | Redirects to `/about` |
| `/pages/:slug` | Any other published CMS page |
| `*` | Not-found page |

## API integration

All requests target the Laravel backend prefix `/api/v1` and only fetch
**published** content:

- `GET /website/bootstrap` — site settings, footer pages, services
- `GET /website/home` — homepage sections
- `GET /website/pages`, `GET /website/pages/{slug}`
- `GET /website/services`, `/website/team`, `/website/gallery`
- `GET /website/articles`, `/website/articles/{slug}`
- `GET /website/media/{id}` — served media
- `POST /website/contact` — contact form submission

No authentication token is used. Loading, empty, and error states are handled per
page. The contact endpoint is public but throttled.

## Content not yet verified

Some homepage copy reproduces the design reference and is **not** verified clinic
data. Confirm or replace it before publishing:

- **Hero highlights** — "10,000+ Happy Patients", "50+ Specialists", and
  "24 Hours" (`HERO_STATS` in `src/constants/index.js`). These are reference
  figures, not confirmed statistics.
- **Partner strip** — `PARTNER_PLACEHOLDERS` renders five styled marks
  (PharmaHealth, MediCare, LifePlus, HealthFirst, WellnessCo) as icon plus
  wordmark. These are design-reference names, not confirmed partnerships.
  Replace them with real partners, ideally CMS-backed, before publishing.

Hero copy (eyebrow, heading, description), the about summary, and the hero image
are editable from the Admin CMS (Website Management → Settings). The stat cards
and partner strip remain code-level placeholders until CMS fields are added.

## Media assets

| File | Source | License |
|---|---|---|
| `public/media/hero-background.png` | Supplied by project owner | Project asset |
| `public/media/about-clinic.jpg` | Pexels (photo 4989168) | Pexels License |
| `public/royalcare-logo.png` | Project asset | Project asset |

`about-clinic.jpg` is a Pexels stock photo. Replace it with a real RoyalCare
facility photo when available. The hero background can be overridden without a
code change by uploading a `hero_image` in the CMS.

Third-party assets and libraries remain subject to their own licenses. The
project's proprietary notice does not claim ownership of them.

## Build and deployment

The app is a SPA. Build with `npm run build` and serve `dist/` behind a web server
configured to fall back to `index.html` for unknown paths, so direct navigation to
nested routes such as `/health-news/foo` works on refresh.

Nginx example:

```nginx
location / {
  try_files $uri $uri/ /index.html;
}
```

Set `VITE_API_URL` at build time to the deployed API URL and add the deployed
origin to the backend `CORS_ALLOWED_ORIGINS`.

The Docker image in this folder runs the Vite development server. Do not deploy
it as a production web server. See
[Deployment & Infrastructure](../docs/10-deployment/index.md).

## Related documentation

- [Root README](../README.md)
- [Frontend Documentation](../docs/08-frontend-documentation/index.md)
- [Deployment & Infrastructure](../docs/10-deployment/index.md)
- [API Documentation](../docs/07-api-documentation/index.md)
- [Admin dashboard README](../frontend/README.md)
