import { NavLink } from 'react-router-dom'
import { Home, Images, Mail, Newspaper, Stethoscope } from 'lucide-react'
import { useLanguage } from '../../hooks/useLanguage.js'
import { cn } from '../../utils/cn.js'

/**
 * App-style bottom tab bar for phones. Mirrors the five most-used public
 * destinations; the hamburger menu keeps the full navigation on small screens.
 */
const TABS = [
  { to: '/home', label: 'Home', Icon: Home },
  { to: '/services', label: 'Services', Icon: Stethoscope },
  { to: '/health-news', label: 'News', Icon: Newspaper },
  { to: '/gallery', label: 'Gallery', Icon: Images },
  { to: '/contact', label: 'Contact', Icon: Mail },
]

export default function MobileTabBar() {
  const { t } = useLanguage()

  return (
    <nav
      aria-label={t('Quick navigation')}
      className="fixed inset-x-0 bottom-0 z-40 border-t border-line/80 bg-white/85 pb-[env(safe-area-inset-bottom)] shadow-[0_-10px_30px_rgba(11,74,143,0.10)] backdrop-blur-xl md:hidden"
    >
      <ul className="mx-auto flex h-16 w-full max-w-lg items-stretch justify-between px-2">
        {TABS.map(({ to, label, Icon }) => (
          <li key={to} className="min-w-0 flex-1">
            <NavLink
              to={to}
              className={({ isActive }) =>
                cn(
                  'flex h-full flex-col items-center justify-center gap-0.5 rounded-2xl px-1 text-[11px] font-semibold transition active:scale-95',
                  isActive ? 'text-accent-dark' : 'text-ink-muted',
                )
              }
            >
              {({ isActive }) => (
                <>
                  <span
                    className={cn(
                      'grid size-8 place-items-center rounded-full transition-colors',
                      isActive ? 'bg-accent/15' : '',
                    )}
                  >
                    <Icon className="size-5" aria-hidden />
                  </span>
                  <span className="truncate">{t(label)}</span>
                </>
              )}
            </NavLink>
          </li>
        ))}
      </ul>
    </nav>
  )
}
