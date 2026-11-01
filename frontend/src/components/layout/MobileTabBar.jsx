import { NavLink, useLocation } from 'react-router-dom'
import { CalendarDays, LayoutDashboard, Menu, Users } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useLanguage } from '../../hooks/useLanguage.js'

/**
 * Compact bottom navigation for phones. It only links to existing routes and
 * hides any destination the signed-in user cannot open. "More" opens the same
 * navigation drawer as the header menu button.
 */
export default function MobileTabBar({ onMore }) {
  const { can } = useAuth()
  const { t } = useLanguage()
  const { pathname } = useLocation()

  const tabs = [
    { to: '/', label: t('Home'), icon: LayoutDashboard, end: true, allowed: true },
    {
      to: '/patients',
      label: t('Patients'),
      icon: Users,
      allowed: can('patients.view'),
    },
    {
      to: '/appointments',
      label: t('Appointments'),
      icon: CalendarDays,
      allowed: can('appointments.view'),
    },
  ].filter((tab) => tab.allowed)

  // "More" is the active destination whenever the current page is not one of
  // the tabs above.
  const onTab = tabs.some((tab) =>
    tab.end ? pathname === tab.to : pathname.startsWith(tab.to),
  )

  return (
    <nav
      aria-label={t('Quick navigation')}
      className="shrink-0 border-t border-line bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden print:hidden"
    >
      <ul className="flex items-stretch justify-around">
        {tabs.map((tab) => (
          <li key={tab.to} className="min-w-0 flex-1">
            <NavLink
              to={tab.to}
              end={tab.end}
              className={({ isActive }) =>
                cn(
                  'flex min-h-14 flex-col items-center justify-center gap-1 px-1 py-1.5 text-[11px] font-medium transition-colors',
                  isActive
                    ? 'text-brand'
                    : 'text-ink-muted hover:text-ink',
                )
              }
            >
              {({ isActive }) => (
                <>
                  <span
                    className={cn(
                      'grid h-7 w-12 place-items-center rounded-full transition-colors',
                      isActive && 'bg-brand-soft',
                    )}
                  >
                    <tab.icon className="size-5" aria-hidden />
                  </span>
                  <span className="max-w-full truncate">{tab.label}</span>
                </>
              )}
            </NavLink>
          </li>
        ))}
        <li className="min-w-0 flex-1">
          <button
            type="button"
            onClick={onMore}
            aria-label={t('Open all navigation')}
            className={cn(
              'flex min-h-14 w-full flex-col items-center justify-center gap-1 px-1 py-1.5 text-[11px] font-medium transition-colors',
              !onTab ? 'text-brand' : 'text-ink-muted hover:text-ink',
            )}
          >
            <span
              className={cn(
                'grid h-7 w-12 place-items-center rounded-full transition-colors',
                !onTab && 'bg-brand-soft',
              )}
            >
              <Menu className="size-5" aria-hidden />
            </span>
            <span className="max-w-full truncate">{t('More')}</span>
          </button>
        </li>
      </ul>
    </nav>
  )
}
