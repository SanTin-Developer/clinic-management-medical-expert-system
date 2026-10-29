# Admin Dashboard — Frontend

React (Vite) single-page application for clinic staff. It contains the internal
admin dashboard and the Admin-only Website Management CMS that publishes content
for the public website in `../website`.

> **Proprietary software.** This repository is not open source. See
> [../PROPRIETARY-NOTICE.md](../PROPRIETARY-NOTICE.md).

The public informational website is a separate application. This app is
intentionally not linked from the public site; staff open it directly.

## Stack

| Component | Technology |
|---|---|
| UI | React 19, JavaScript/JSX |
| Build | Vite |
| Routing | React Router v7 |
| Server state | TanStack Query |
| Local state | Zustand |
| Styling | Tailwind CSS v4 (`@tailwindcss/vite`), tokens in `src/styles/globals.css` |
| Components | shadcn / Base UI primitives, class-variance-authority, tailwind-merge, clsx |
| Icons and motion | Lucide, React Icons, Framer Motion |
| Forms and feedback | Zod, Sonner |
| API | axios |
| Dates and files | date-fns, react-day-picker, react-easy-crop, html2canvas-pro, jspdf, qrcode.react |
| Fonts | Geist and Kantumruy Pro variable fonts (Khmer support) |
| Lint | Oxlint |

## Quick start

With Docker, from the repository root:

```powershell
docker compose up -d frontend
```

On the host:

```powershell
cd frontend
Copy-Item .env.example .env
npm install
npm run dev
```

Expected: `http://localhost:5173`.

The API must be running at `http://localhost:8000`.

## Commands

```powershell
npm run dev        # Vite development server
npm run build      # Vite production build in dist/
npm run preview    # Preview the production build
npm run lint       # Oxlint; warnings do not fail the command
```

`npm run build` runs `vite build`. It does **not** run a TypeScript type-check;
this app uses JavaScript and JSX.

Last verified in this working copy on 2026-10-29:

- `npm run lint` exits `0` with React Compiler warnings.
- `npm run build` succeeds.

## Environment

Copy `.env.example` to `.env`.

| Variable | Default | Purpose |
|---|---|---|
| `VITE_API_URL` | `http://localhost:8000/api/v1` | Laravel API base URL, no trailing slash |
| `VITE_PUBLIC_SITE_URL` | `http://localhost:5174` | Public website URL used by "View website" |
| `VITE_REVERB_APP_KEY` | empty | Must match backend `REVERB_APP_KEY` |
| `VITE_REVERB_HOST` | `localhost` | Public Reverb host |
| `VITE_REVERB_PORT` | `8080` | Public Reverb port |
| `VITE_REVERB_SCHEME` | `http` | Use `https` behind TLS |

Vite reads these at build time. Rebuild after changing them. Never put secrets in
`VITE_*` variables; they are included in the browser bundle.

The backend uses Sanctum bearer tokens and also sets a trusted-device cookie, so
the backend CORS configuration must allow this origin with credentials.

## Structure

```text
src/
  api/         Axios client and endpoint modules by feature
  components/
    ui/        Design-system primitives
    layout/    App shell (sidebar, header, layout)
    auth/      Shared authentication shell
    patients/ appointments/ medical/ prescriptions/ pharmacy/ billing/
    users/ expert/ nursing/ inpatient/ staff/ website/
  constants/   App name, navigation, role names
  context/     AuthProvider and AuthContext
  hooks/       Shared hooks
  pages/       Route-level screens, including pages/website/ (CMS)
  router/      Router, ProtectedRoute, permission Guard
  styles/      Global styles and design tokens
  utils/       Formatting, status maps, storage helpers
```

## Modules

| Area | Examples |
|---|---|
| Dashboard | `/` |
| Patients and documents | `/patients`, `/patients/:id` |
| Appointments and queue | `/appointments`, `/queue` |
| Clinical records | `/medical-records`, consultations, diagnoses, vitals |
| Prescriptions | `/prescriptions`, `/prescriptions/:id` |
| Pharmacy and inventory | `/pharmacy`, `/pharmacy/pos`, `/inventory` |
| Billing and reports | `/billing`, `/reports` |
| Inpatient | `/admissions`, `/wards`, `/nursing-tasks`, `/mar`, `/assignments` |
| Staff and HR | `/staff`, `/attendance`, `/scheduling`, `/leave` |
| Communication | `/chat`, `/news`, `/announcements`, `/notifications` |
| Administration | `/users`, `/invitations`, `/approvals`, `/audit-logs`, `/settings` |
| Expert system | `/expert-system` |
| Website CMS | `/website/*` |

The router in `src/router/index.jsx` is authoritative. Some navigation items are
hidden unless the signed-in user has the required permission.

## Authentication and permissions

- Login, device OTP, TOTP, forgot/reset password, invitation acceptance, and
  email verification screens are implemented.
- The access token is stored by the storage helper and sent as
  `Authorization: Bearer <token>`.
- A `401` response clears the token and emits an authentication event.
- Navigation (`src/constants/navigation.js`) and route guards hide or block
  screens, but the backend enforces authorization. Frontend checks are not a
  security boundary.

See [API Documentation](../docs/07-api-documentation/index.md) and
[System Design §4.7](../docs/04-system-design/index.md).

## Audit note

`npm audit` reports 7 high-severity advisories, all from the `shadcn` CLI's
development toolchain (`braces` → `micromatch` → `fast-glob`). There is no
non-breaking fix at the time of writing, and the toolchain does not ship in the
production bundle. Re-check before upgrading dependencies.

## Build and deployment

Build with `npm run build` and serve `dist/` from a static host. Configure SPA
fallback so deep links such as `/patients/42` work on refresh:

```nginx
location / {
  try_files $uri $uri/ /index.html;
}
```

Set `VITE_API_URL` before building. The Docker image in this folder is a
development server (`npm run dev`); do not deploy it as a production web server.
See [Deployment & Infrastructure](../docs/10-deployment/index.md).

## Related documentation

- [Root README](../README.md)
- [Frontend Documentation](../docs/08-frontend-documentation/index.md)
- [Deployment & Infrastructure](../docs/10-deployment/index.md)
- [Testing](../docs/09-testing/index.md)
- [Administrator Security Runbook](../docs/ADMIN_SECURITY_RUNBOOK.md)
- [Public website README](../website/README.md)
