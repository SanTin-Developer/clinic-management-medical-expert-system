import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  Clock,
  Mail,
  MapPin,
  Menu,
  Phone,
  Stethoscope,
  X,
} from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { APP_NAME } from '../../constants/index.js'
import { cn } from '../../utils/cn.js'

const NAV_ITEMS = [
  { to: '/home', label: 'Home' },
  { to: '/about', label: 'About Us' },
  { to: '/services', label: 'Services' },
  { to: '/team', label: 'Our Team' },
  { to: '/gallery', label: 'Gallery' },
  { to: '/health-news', label: 'News & Health' },
  { to: '/contact', label: 'Contact' },
]

const SOCIALS = [
  {
    key: 'facebook_url',
    label: 'Facebook',
    path: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
  },
  {
    key: 'twitter_url',
    label: 'Twitter / X',
    path: 'M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z',
  },
  {
    key: 'instagram_url',
    label: 'Instagram',
    path: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z',
  },
  {
    key: 'linkedin_url',
    label: 'LinkedIn',
    path: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
  },
  {
    key: 'youtube_url',
    label: 'YouTube',
    path: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
  },
]

function SocialGlyph({ path, label }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" role="img" aria-label={label} className="size-4">
      <path d={path} />
    </svg>
  )
}

function BrandMark({ settings, className }) {
  if (settings?.logo?.url) {
    return <img src={settings.logo.url} alt={settings.site_name ?? APP_NAME} className={cn('object-contain', className)} />
  }
  return (
    <span className={cn('grid place-items-center rounded-xl bg-brand text-white', className)}>
      <Stethoscope className="size-5" aria-hidden />
    </span>
  )
}

function navLinkClass({ isActive }) {
  return cn(
    'rounded-lg px-3 py-2 text-sm font-medium transition-colors',
    isActive ? 'text-brand' : 'text-ink-muted hover:text-ink',
  )
}

export default function PublicLayout() {
  const [menuOpen, setMenuOpen] = useState(false)
  const location = useLocation()

  const { data } = useQuery({
    queryKey: ['website', 'bootstrap'],
    queryFn: publicWebsiteApi.bootstrap,
    staleTime: 5 * 60_000,
  })

  const settings = data?.settings ?? {}
  const footerPages = data?.footer_pages ?? []
  const services = data?.services ?? []

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname])

  useEffect(() => {
    document.title = settings.seo_title || settings.site_name || APP_NAME

    if (settings.favicon?.url) {
      let link = document.querySelector("link[rel='icon']")
      if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
      }
      link.href = settings.favicon.url
    }
  }, [settings.seo_title, settings.site_name, settings.favicon?.url])

  const phone = settings.primary_phone ?? settings.secondary_phone
  const year = new Date().getFullYear()

  return (
    <div className="flex min-h-screen flex-col bg-canvas text-ink">
      <header className="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur">
        <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
          <Link to="/home" className="flex items-center gap-2.5">
            <BrandMark settings={settings} className="size-9" />
            <span className="text-base font-bold tracking-tight text-ink">
              {settings.site_name ?? APP_NAME}
            </span>
          </Link>

          <nav className="hidden items-center gap-1 lg:flex" aria-label="Primary">
            {NAV_ITEMS.map((item) => (
              <NavLink key={item.to} to={item.to} className={navLinkClass}>
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="hidden items-center gap-2 lg:flex">
            <Link
              to="/login"
              className="rounded-lg border border-line px-3 py-2 text-sm font-semibold text-ink transition-colors hover:bg-canvas"
            >
              Staff Login
            </Link>
            {phone ? (
              <a
                href={`tel:${phone}`}
                className="inline-flex items-center gap-2 rounded-lg bg-brand px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-brand-dark"
              >
                <Phone className="size-4" aria-hidden />
                Call Us
              </a>
            ) : null}
          </div>

          <button
            type="button"
            onClick={() => setMenuOpen((v) => !v)}
            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={menuOpen}
            className="grid size-10 place-items-center rounded-lg border border-line text-ink lg:hidden"
          >
            {menuOpen ? <X className="size-5" aria-hidden /> : <Menu className="size-5" aria-hidden />}
          </button>
        </div>

        {menuOpen ? (
          <nav className="border-t border-line bg-surface px-4 py-3 lg:hidden" aria-label="Mobile">
            <div className="flex flex-col">
              {NAV_ITEMS.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    cn(
                      'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                      isActive ? 'bg-brand-soft text-brand-dark' : 'text-ink-muted hover:bg-canvas',
                    )
                  }
                >
                  {item.label}
                </NavLink>
              ))}
              <Link
                to="/login"
                className="mt-2 rounded-lg bg-brand px-3 py-2.5 text-center text-sm font-semibold text-white"
              >
                Staff Login
              </Link>
            </div>
          </nav>
        ) : null}
      </header>

      <main className="flex-1">
        <Outlet context={{ settings }} />
      </main>

      <footer className="mt-16 border-t border-line bg-surface">
        <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-4">
          <div className="lg:col-span-1">
            <Link to="/home" className="flex items-center gap-2.5">
              <BrandMark settings={settings} className="size-9" />
              <span className="text-base font-bold text-ink">{settings.site_name ?? APP_NAME}</span>
            </Link>
            {settings.tagline ? (
              <p className="mt-3 text-sm font-medium text-brand">{settings.tagline}</p>
            ) : null}
            {settings.about_summary ? (
              <p className="mt-2 line-clamp-4 text-sm text-ink-muted">{settings.about_summary}</p>
            ) : null}
            <div className="mt-4 flex items-center gap-2">
              {SOCIALS.filter((s) => settings[s.key]).map((s) => (
                <a
                  key={s.key}
                  href={settings[s.key]}
                  target="_blank"
                  rel="noreferrer"
                  aria-label={s.label}
                  className="grid size-9 place-items-center rounded-lg border border-line text-ink-muted transition-colors hover:border-brand hover:text-brand"
                >
                  <SocialGlyph path={s.path} label={s.label} />
                </a>
              ))}
            </div>
          </div>

          <div>
            <h3 className="text-sm font-semibold text-ink">Quick Links</h3>
            <ul className="mt-3 space-y-2 text-sm">
              {NAV_ITEMS.slice(1).map((item) => (
                <li key={item.to}>
                  <Link to={item.to} className="text-ink-muted transition-colors hover:text-brand">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-semibold text-ink">Our Services</h3>
            <ul className="mt-3 space-y-2 text-sm">
              {services.slice(0, 6).map((service) => (
                <li key={service.id}>
                  <Link to="/services" className="text-ink-muted transition-colors hover:text-brand">
                    {service.title}
                  </Link>
                </li>
              ))}
              {services.length === 0 ? (
                <li className="text-ink-muted">Coming soon.</li>
              ) : null}
            </ul>
          </div>

          <div>
            <h3 className="text-sm font-semibold text-ink">Get in Touch</h3>
            <ul className="mt-3 space-y-3 text-sm text-ink-muted">
              {settings.address ? (
                <li className="flex gap-2">
                  <MapPin className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden />
                  <span>{settings.address}</span>
                </li>
              ) : null}
              {phone ? (
                <li className="flex gap-2">
                  <Phone className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden />
                  <a href={`tel:${phone}`} className="transition-colors hover:text-brand">
                    {phone}
                    {settings.secondary_phone ? ` · ${settings.secondary_phone}` : ''}
                  </a>
                </li>
              ) : null}
              {settings.email ? (
                <li className="flex gap-2">
                  <Mail className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden />
                  <a href={`mailto:${settings.email}`} className="transition-colors hover:text-brand">
                    {settings.email}
                  </a>
                </li>
              ) : null}
              {settings.business_hours ? (
                <li className="flex gap-2">
                  <Clock className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden />
                  <span className="whitespace-pre-line">{settings.business_hours}</span>
                </li>
              ) : null}
            </ul>
          </div>
        </div>

        <div className="border-t border-line">
          <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-4 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p>
              © {year} {settings.site_name ?? APP_NAME}. All rights reserved.
            </p>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
              {footerPages.map((page) => (
                <Link key={page.id} to={`/pages/${page.slug}`} className="transition-colors hover:text-brand">
                  {page.title}
                </Link>
              ))}
            </div>
          </div>
        </div>
      </footer>
    </div>
  )
}
