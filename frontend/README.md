# Clinic Management System — Frontend

React (Vite) SPA for the Clinic Management System and Medical Expert System.

The public informational website was extracted into the standalone `../website`
application. This app now contains the internal admin dashboard **and** the
Admin-only Website Management CMS (`src/pages/website/`, `src/api/websiteAdmin.js`),
which manages the content served by the public website.

## Stack

- React 19 + Vite (JavaScript, JSX)
- React Router v7 + Zustand (state) + TanStack Query (server state)
- Tailwind CSS v4 (`@tailwindcss/vite`) with tokens in `src/styles/globals.css`
- shadcn / Base UI primitives, class-variance-authority, tailwind-merge, clsx
- Lucide + React Icons, Framer Motion, Sonner (toasts), Zod (validation)
- axios API client, date-fns, react-day-picker, react-easy-crop, swiper
- HTML-to-image / PDF export: html2canvas-pro, jspdf, qrcode.react
- Geist + Kantumruy Pro (Khmer support) variable fonts
- Oxlint for linting

## Audit note

`npm audit` reports 7 high-severity advisories, all from the `shadcn` CLI's
dev toolchain (`braces` → `micromatch` → `fast-glob`). There is no non-breaking
fix and it never ships in the production bundle.

## Quick start

```sh
npm install
npm run dev          # http://localhost:5173
npm run build        # typecheck + production build
npm run preview
npm run lint
```

## Environment

Copy `.env.example` to `.env` and set `VITE_API_URL` to the backend route
prefix (default `http://localhost:8000/api/v1`).

The API takes Bearer tokens for authorisation and also sets the trusted-device
cookie, so the backend CORS config must allow this origin with credentials.

## Structure

```
src/
  api/         axios client + endpoint modules (auth, patients, appointments,
               clinical, prescriptions, pharmacy, billing, documents, reports,
               users, expert)
  components/
    ui/        Design-system primitives (Button, Card, Modal, Tabs, Table, …)
    layout/    App shell (sidebar, header, layout)
    auth/      Shared auth shell
    patients/  appointments/  medical/  prescriptions/  pharmacy/  billing/
    users/  expert/           Feature modals and selectors
  constants/   App name, routes, role names
  context/     AuthProvider + AuthContext
  hooks/       useAuth, usePaginated / useAll
  pages/       Route-level screens
  router/      Router config, ProtectedRoute, permission Guard
  styles/      Global styles + design tokens (rose healthcare theme)
  utils/        cn, status maps, formatters, storage helpers
```

## Status

Feature-complete SPA: authentication (login, device OTP, forgot/reset password,
email verification), dashboard, patients + documents, appointments, medical
records (consultations, diagnoses, vitals), prescriptions, pharmacy (medicines,
inventory, stock movements), billing (invoices, payments, print), reports +
CSV/PDF export, users & roles, settings (profile, trusted devices), and the
medical expert system (symptom analysis, knowledge base, audit history).
All screens are RBAC-aware and wired to the live API.