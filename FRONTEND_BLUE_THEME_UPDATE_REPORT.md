# Frontend Blue Theme Update Report

**Date:** 2026-10-12
**Scope:** Full replacen of the pink brand theme with a professional **Modern Medical Blue** theme, plus RoyalCare Clinic branding (name, logo, dynamic tab titles, time-of-day greeting).

---

## 1. Clinic name: RoyalCare Clinic

| Location | Before | After |
|---|---|---|
| `frontend/src/constants/index.js` (`APP_NAME`) | `Clinic Management System` | `RoyalCare Clinic` |
| `frontend/index.html` (`<title>`, `<meta name="description">`) | `Clinic Management System` | `RoyalCare Clinic` / "Frontend for the RoyalCare Clinic Management System and Medical Expert System." |
| `frontend/src/components/layout/Sidebar.jsx` (brand text) | hardcoded `Clinic` | `APP_NAME` constant → `RoyalCare Clinic` |
| `frontend/src/pages/LoginPage.jsx` (auth shell title) | hardcoded string | `APP_NAME` constant |
| `backend/.env` + `backend/.env.example` (`APP_NAME`) | `Clinic Management & Medical Expert System` / `Laravel` | `RoyalCare Clinic` (keeps mail/notification branding consistent) |

No Laravel API, database, permission, or business logic was modified.

---

## 2. Color palette (design tokens)

All colors are defined centrally in `frontend/src/styles/globals.css` (Tailwind CSS v4 `@theme`). Changing these tokens updates every `brand-*`, `canvas`, `surface`, `line`, `ink*` utility used across the app.

| Token | Before (pink) | After (blue) |
|---|---|---|
| `--color-brand` | `#db2777` | `#2563EB` (primary) |
| `--color-brand-dark` | `#9d174d` | `#1D4ED8` (primary hover / deep blue) |
| `--color-brand-darker` | `#831843` | `#1E40AF` (sidebar deep blue) |
| `--color-brand-soft` | `#fce7f3` | `#DBEAFE` (soft blue) |
| `--color-brand-faint` | `#fff0f7` | `#EFF6FF` (light blue) |
| `--color-canvas` (page bg) | `#fff7fb` | `#F8FAFC` |
| `--color-surface` (cards/modals) | `#ffffff` | `#FFFFFF` (unchanged) |
| `--color-line` | `#f3d9e6` | `#E2E8F0` (borders) |
| `--color-ink` (main text) | `#172554` | `#172554` (unchanged) |
| `--color-ink-muted` (secondary text) | `#64748b` | `#64748B` (unchanged) |
| `--color-success` | `#10b981` | `#10B981` (semantic, unchanged) |
| `--color-warning` | `#f59e0b` | `#F59E0B` (semantic, unchanged) |
| `--color-danger` | `#ef4444` | `#EF4444` (semantic, unchanged) |
| `--shadow-card` | pink glow `rgb(219 39 119 / 0.06)` | blue glow `rgb(37 99 235 / 0.08)` |
| scrollbar thumb | `#f0c8db` | `#bfdbfe` |
| `:focus-visible` outline | `var(--color-brand)` (auto) | `var(--color-brand)` → now blue |

### Sidebar
- Background changed `bg-brand-dark` → `bg-brand-darker` (`#1E40AF`) per the requested deep-blue sidebar.
- Active item: white item with `text-brand-dark` (`#1D4ED8)`) text.
- Inactive/hover items: `text-blue-100/80`, hover `bg-white/10 hover:text-white`.

---

## 3. Hardcoded pink removed

Global search for `pink`/`rose-`/`fuchsia-` tailwind classes and the old pink hexes (`#db2777`, `#9d174d`, `#831843`, `#fce7f3`, `#fff0f7`, `#fff7fb`, `#f3d9e6`, `#f0c8db`) now returns **zero** matches.

| File | Change |
|---|---|
| `components/ui/Badge.jsx` | tone key `pink: bg-pink-100 text-pink-800` → `brand: bg-brand-soft text-brand-dark` |
| `components/ui/StatCard.jsx` | tone key `pink` → `brand`; default `tone="pink"` → `"brand"`; skeleton `bg-pink-100/70` → `bg-brand-soft/70` |
| `components/ui/Skeleton.jsx` | `bg-pink-100/70` → `bg-brand-soft/70` |
| `components/layout/Sidebar.jsx` | `text-pink-200` → logo swap; `text-pink-100/80` → `text-blue-100/80` (2 spots) |
| `pages/DashboardPage.jsx` | `tone="pink"` → `tone="brand"` |
| `pages/PatientListPage.jsx` | `<Badge tone="pink">` → `<Badge tone="brand">` |
| `pages/PrescriptionDetailPage.jsx` | `<Badge tone="pink">` → `<Badge tone="brand">` |
| `pages/ReportsPage.jsx` | `tone="pink"` → `tone="brand"` (StatCard) |
| `pages/SettingsPage.jsx` | `<Badge tone="pink">` → `<Badge tone="brand">` |
| `pages/UsersPage.jsx` | role tone map `Nurse: 'pink'` → `'brand'` |
| `utils/status.js` | `confirmed`, `bank_transfer`, `imaging` tone `pink` → `brand` |
| `pages/OtpVerifyPage.jsx` | `accent-[#db2777]` → `accent-brand` |
| `components/medical/DiagnosisFormModal.jsx` | `accent-[#db2777]` → `accent-brand` |
| `components/pharmacy/MedicineFormModal.jsx` | `accent-[#db2777]` → `accent-brand` |

All other brand usage across pages (buttons, links, active tabs, selected states, dashboard stat icons, expert-system result chips, aria-checked rows, etc.) uses token classes that now resolve to blue automatically — no per-component edits needed.

---

## 4. RoyalCare logo

- Asset saved locally: `frontend/public/royalcare-logo.png` (downloaded from the provided Cloudinary URL, ~73 KB, transparent background). Served at `/royalcare-logo.png`.
- `frontend/index.html`: favicon switched `href="/favicon.svg"` → `href="/royalcare-logo.png"`.
- `components/layout/Sidebar.jsx`: the `HeartPulse` icon tile replaced with the logo image.
- `pages/auth/AuthShell.jsx`: the `HeartPulse` badge replaced with the logo image (login/OTP/reset/verify screens).
- Chrome tab now shows the blue squared mark.

---

## 5. Page-specific tab titles

New central helper: `frontend/src/hooks/usePageTitle.js`.

- `TAB_TITLE(label)` → `RoyalCare - <label>`.
- `DocumentTitle` component maps the current pathname to a label and sets `document.title`:
  - `/` → **RoyalCare - Dashboard**
  - `/patients` → Patients; `/patients/:id` → Patient
  - `/appointments` → Appointments; `/appointments/:id` → Appointment
  - `/medical-records` → Medical Records
  - `/prescriptions` → Prescriptions; `/prescriptions/:id` → Prescription
  - `/pharmacy` → Pharmacy
  - `/billing` → Billing & Payments; `/billing/:id` → Invoice
  - `/reports` → Reports
  - `/users` → Users & Roles
  - `/settings` → Settings
  - `/expert-system` → Expert System
  - `/forbidden` → Access Denied
- `DocumentTitle` is mounted once in `AppLayout`. Standalone pages (outside the layout) call `usePageTitle(...)` directly: Login (`Sign In`), OTP verify (`Two-Factor Verification`), Forgot/Reset/New password (`Forgot Password` / `Reset Password`), `Verify Email`, `Not Found`.

---

## 6. Time-of-day dashboard greeting

`pages/DashboardPage.jsx` `greeting()` now follows the requested schedule (local time):

| Time | Greeting |
|---|---|
| 5:00 AM – 11:59 AM | Good morning |
| 12:00 PM – 4:59 PM | Good afternoon |
| 5:00 PM – 8:59 PM | Good evening |
| 9:00 PM – 4:59 AM | Good night |

---

## 7. Verification

- `npm run lint` (oxlint): **0 errors.** The remaining warnings (set-state-in-effect, `Date` purity during render, fast-refresh export-only) are pre-existing and unrelated to this change.
- `npm run build` (Vite): **succeeded** — 2,109 modules, all chunks emitted; `dist/royalcare-logo.png` copied from `public/`.
- Post-change search for pink color codes / pink utility classes across `frontend/`: **no matches**.
- No new dependencies, no framework change, no layout/responsive/typography changes beyond the theme swap.
- Semantic status colors unchanged (green = success, amber = warning, red = danger, slate = neutral); charts, badges, and the Expert System result cards render with the blue palette and remain readable.
- Laravel APIs, Python inference, database schema, authentication, permissions, and business logic untouched.