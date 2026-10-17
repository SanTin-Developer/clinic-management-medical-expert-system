import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  Calendar,
  Clock,
  Mail,
  MapPin,
  Menu,
  Phone,
  X,
} from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { APP_NAME, FOOTER_LINKS, PRIMARY_NAV, SECONDARY_NAV } from '../../constants/index.js'
import { cn } from '../../utils/cn.js'

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

function RoyalCareLogo({ className }) {
  return (
    <svg viewBox="0 0 48 48" role="img" aria-label="RoyalCare" className={cn('shrink-0', className)}>
      <rect x="17.5" y="6" width="13" height="36" rx="6.5" fill="#1E88C8" />
      <rect x="6" y="17.5" width="36" height="13" rx="6.5" fill="#1E88C8" />
      <path
        d="M30.5 8c6.6-.8 11.2 2.8 11.2 2.8s-1.3 6-6.8 8c-4.1 1.5-7.9.3-9.7-.8 1.8-5.2 5.3-10 5.3-10Z"
        fill="#4CAF50"
        transform="translate(0, 22)"
      />
    </svg>
  )
}

function BrandMark({ settings, className }) {
  if (settings?.logo?.url) {
    return <img src={settings.logo.url} alt={settings.site_name ?? APP_NAME} className={cn('object-contain', className)} />
  }
  return <RoyalCareLogo className={cn('h-10 w-10', className)} />
}

function navLinkClass({ isActive }) {
  return cn(
    'relative inline-flex items-center px-3.5 py-2 text-sm font-medium transition-colors',
    isActive
      ? "text-[#1E88C8] after:absolute after:-bottom-1 after:left-3 after:right-3 after:h-0.5 after:rounded-full after:bg-[#1E88C8]"
      : 'text-[#4A5568] hover:text-[#0B4A8F]',
  )
}

export default function PublicLayout() {
  const [menuOpen, setMenuOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const location = useLocation()

  const { data } = useQuery({
    queryKey: ['website', 'bootstrap'],
    queryFn: publicWebsiteApi.bootstrap,
    staleTime: 5 * 60_000,
  })

  const settings = data?.settings ?? {}
  const footerPages = data?.footer_pages ?? []
  const services = data?.services ?? []

  // Only the home page opens with a hero image, so that is where the header can
  // start fully transparent. Other pages have dark banners that need the glass.
  const isHome = location.pathname === '/home' || location.pathname === '/'

  // The header sits flush over the hero when at the top of the page (no
  // background), then fades in its glass background once the page is scrolled.
  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20)
    const frame = requestAnimationFrame(onScroll)
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      cancelAnimationFrame(frame)
      window.removeEventListener('scroll', onScroll)
    }
  }, [])

  useEffect(() => {
    setMenuOpen(false)
  }, [location.pathname])

  useEffect(() => {
    document.title = settings.seo_title || settings.site_name || APP_NAME

    if (settings.seo_description) {
      let meta = document.querySelector("meta[name='description']")
      if (!meta) {
        meta = document.createElement('meta')
        meta.name = 'description'
        document.head.appendChild(meta)
      }
      meta.content = settings.seo_description
    }

    if (settings.favicon?.url) {
      let link = document.querySelector("link[rel='icon']")
      if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
      }
      link.href = settings.favicon.url
    }
  }, [settings.seo_title, settings.site_name, settings.seo_description, settings.favicon?.url])

  const phone = settings.primary_phone ?? settings.secondary_phone
  const year = new Date().getFullYear()

  return (
    <div className="flex min-h-screen flex-col bg-canvas text-ink">
      <header
        className={cn(
          'fixed inset-x-0 top-0 z-50 transition-all duration-300',
          scrolled || !isHome
            ? 'bg-white/60 shadow-[0_10px_30px_rgba(11,74,143,0.06)] backdrop-blur-[12px]'
            : 'bg-transparent',
        )}
      >
        <div className="relative mx-auto flex h-16 w-full max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
          <Link to="/home" className="flex items-center gap-3">
            <BrandMark settings={settings} className="size-11" />
            <div className="flex flex-col leading-tight">
              <span className="text-[24px] font-extrabold tracking-tight text-[#0B4A8F]" style={{ fontFamily: 'var(--font-display, "Plus Jakarta Sans", system-ui, sans-serif)' }}>
                RoyalCare
              </span>
              <span className="text-[15px] font-medium text-[#1E88C8]" style={{ fontFamily: 'var(--font-display, "Plus Jakarta Sans", system-ui, sans-serif)' }}>
                Clinic
              </span>
            </div>
          </Link>

          <nav
            className="absolute left-1/2 top-1/2 hidden -translate-x-1/2 -translate-y-1/2 items-center gap-1 lg:flex"
            aria-label="Primary"
          >
            {PRIMARY_NAV.map((item) => (
              <NavLink key={item.to} to={item.to} className={navLinkClass}>
                {item.label}
              </NavLink>
            ))}
          </nav>

          <div className="flex items-center gap-2">
            <Link
              to="/contact"
              className="hidden items-center gap-2 rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-accent-ink shadow-[var(--shadow-card)] transition-transform hover:-translate-y-0.5 hover:bg-accent-dark lg:inline-flex"
            >
              <Calendar className="size-4" aria-hidden />
              Book an Appointment
            </Link>

            <button
              type="button"
              onClick={() => setMenuOpen((v) => !v)}
              aria-label={menuOpen ? 'Close menu' : 'Open menu'}
              aria-expanded={menuOpen}
              className="grid size-10 place-items-center rounded-xl border border-line bg-surface/70 text-ink lg:hidden"
            >
              {menuOpen ? <X className="size-5" aria-hidden /> : <Menu className="size-5" aria-hidden />}
            </button>
          </div>
        </div>

        {menuOpen ? (
          <nav className="border-t border-line bg-surface px-4 py-3 lg:hidden" aria-label="Mobile">
            <div className="flex flex-col">
              {[...PRIMARY_NAV, ...SECONDARY_NAV].map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    cn(
                      'rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                      isActive ? 'bg-azure-100 text-azure-700' : 'text-ink-muted hover:bg-canvas',
                    )
                  }
                >
                  {item.label}
                </NavLink>
              ))}
              <Link
                to="/contact"
                className="mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2.5 text-sm font-semibold text-accent-ink shadow-[var(--shadow-card)]"
              >
                <Calendar className="size-4" aria-hidden />
                Book an Appointment
              </Link>
            </div>
          </nav>
        ) : null}
      </header>

      <main className="flex-1">
        <Outlet context={{ settings }} />
      </main>

      <footer className="mt-16 border-t border-line bg-surface">
        <div className="mx-auto grid w-full max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-4">
          <div className="lg:col-span-1">
            <Link to="/home" className="flex items-center gap-2.5">
              <BrandMark settings={settings} className="size-9" />
              <span className="text-base font-bold text-ink">{settings.site_name ?? APP_NAME}</span>
            </Link>
            {settings.tagline ? (
              <p className="mt-3 text-sm font-medium text-azure-700">{settings.tagline}</p>
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
                  className="grid size-9 place-items-center rounded-lg border border-line text-ink-muted transition-colors hover:border-azure-400 hover:text-azure-600"
                >
                  <SocialGlyph path={s.path} label={s.label} />
                </a>
              ))}
            </div>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-ink">Quick Links</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {FOOTER_LINKS.map((item) => (
                <li key={item.to}>
                  <Link to={item.to} className="text-ink-muted transition-colors hover:text-azure-600">
                    {item.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-ink">Our Services</h2>
            <ul className="mt-3 space-y-2 text-sm">
              {services.slice(0, 6).map((service) => (
                <li key={service.id}>
                  <Link to="/services" className="text-ink-muted transition-colors hover:text-azure-600">
                    {service.title}
                  </Link>
                </li>
              ))}
              {services.length === 0 ? (
                <li>
                  <Link to="/services" className="text-ink-muted transition-colors hover:text-azure-600">
                    View our services
                  </Link>
                </li>
              ) : null}
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold text-ink">Get in Touch</h2>
            <ul className="mt-3 space-y-3 text-sm text-ink-muted">
              {settings.address ? (
                <li className="flex gap-2">
                  <MapPin className="mt-0.5 size-4 shrink-0 text-azure-500" aria-hidden />
                  <span>{settings.address}</span>
                </li>
              ) : null}
              {phone ? (
                <li className="flex gap-2">
                  <Phone className="mt-0.5 size-4 shrink-0 text-azure-500" aria-hidden />
                  <a href={`tel:${phone}`} className="transition-colors hover:text-azure-600">
                    {phone}
                    {settings.secondary_phone ? ` · ${settings.secondary_phone}` : ''}
                  </a>
                </li>
              ) : null}
              {settings.email ? (
                <li className="flex gap-2">
                  <Mail className="mt-0.5 size-4 shrink-0 text-azure-500" aria-hidden />
                  <a href={`mailto:${settings.email}`} className="transition-colors hover:text-azure-600">
                    {settings.email}
                  </a>
                </li>
              ) : null}
              {settings.business_hours ? (
                <li className="flex gap-2">
                  <Clock className="mt-0.5 size-4 shrink-0 text-azure-500" aria-hidden />
                  <span className="whitespace-pre-line">{settings.business_hours}</span>
                </li>
              ) : null}
            </ul>
          </div>
        </div>

        <div className="border-t border-line">
          <div className="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-4 text-xs text-ink-muted sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <p>
              © {year} {settings.site_name ?? APP_NAME}. All rights reserved.
            </p>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1">
              {footerPages.map((page) => (
                <Link key={page.id} to={`/pages/${page.slug}`} className="transition-colors hover:text-azure-600">
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
