/**
 * Public website preview helpers.
 *
 * The admin app and the public website are separate SPAs served from different
 * origins (see docker-compose `frontend` on 5173 and `website` on 5174), so the
 * preview embeds the public site in an iframe rather than rendering it inline.
 */

/** Base origin of the public website. Override per environment for deployments. */
export const PUBLIC_SITE_URL =
  import.meta.env.VITE_PUBLIC_SITE_URL ?? 'http://localhost:5174'

/**
 * Viewport presets for the preview frame, matching common device widths so an
 * admin can sanity-check responsive layouts without leaving the dashboard.
 */
export const PREVIEW_VIEWPORTS = [
  { id: 'desktop', label: 'Desktop', width: null, icon: 'Monitor' },
  { id: 'tablet', label: 'Tablet', width: 834, icon: 'Tablet' },
  { id: 'mobile', label: 'Mobile', width: 390, icon: 'Smartphone' },
]

/**
 * Build an absolute URL on the public site for a given path.
 *
 * Accepts either a bare path (`/services`) or a full URL (already-absolute
 * values are returned untouched so callers can pass through a stored link).
 */
export function publicSiteUrl(path = '/') {
  if (!path) {
    return PUBLIC_SITE_URL
  }

  if (/^https?:\/\//i.test(path)) {
    return path
  }

  const normalized = path.startsWith('/') ? path : `/${path}`
  return `${PUBLIC_SITE_URL.replace(/\/$/, '')}${normalized}`
}

/** Deep links used by the embedded preview's page selector. */
export const PREVIEW_PAGES = [
  { id: 'home', label: 'Home', path: '/' },
  { id: 'about', label: 'About', path: '/about' },
  { id: 'services', label: 'Services', path: '/services' },
  { id: 'team', label: 'Our Team', path: '/team' },
  { id: 'gallery', label: 'Gallery', path: '/gallery' },
  { id: 'news', label: 'News & Health', path: '/health-news' },
  { id: 'contact', label: 'Contact', path: '/contact' },
]
