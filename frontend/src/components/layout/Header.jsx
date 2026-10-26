import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ChevronDown, LogOut, Menu, PanelLeft, UserRound } from 'lucide-react'
import Dropdown, { DropdownItem } from '../ui/Dropdown.jsx'
import NotificationBell from '../communication/NotificationBell.jsx'
import GlobalSearch from '../search/GlobalSearch.jsx'
import MessagesButton from './MessagesButton.jsx'
import NavbarCalendar from './NavbarCalendar.jsx'
import NavbarClock from './NavbarClock.jsx'
import NavbarMenu from './NavbarMenu.jsx'
import ThemeToggle from './ThemeToggle.jsx'
import { Spinner } from '../ui/Skeleton.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { useLanguage } from '../../hooks/useLanguage.js'

function initials(name = '') {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

export default function Header({ onMenu, onToggleSidebar }) {
  const { user, logout } = useAuth()
  const { t } = useLanguage()
  const navigate = useNavigate()
  const [signingOut, setSigningOut] = useState(false)

  const role = user?.roles?.[0] ?? ''

  const handleSignOut = async () => {
    setSigningOut(true)
    try {
      await logout()
    } finally {
      navigate('/login', { replace: true })
    }
  }

  return (
    <>
      {signingOut ? (
        <div
          role="status"
          aria-live="polite"
          className="fixed inset-0 z-[100] grid place-items-center bg-surface/80 backdrop-blur-sm"
        >
          <Spinner label={t('Signing you out…')} />
        </div>
      ) : null}
      <header className="flex h-16 shrink-0 items-center gap-3 border-b border-line bg-surface px-4 print:hidden sm:px-6">
        <div className="flex min-w-0 flex-1 items-center gap-2">
          <button
            type="button"
            onClick={onMenu}
            aria-label={t('Open navigation')}
            className="grid size-10 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas md:hidden"
          >
            <Menu className="size-5" aria-hidden />
          </button>
          <button
            type="button"
            onClick={onToggleSidebar}
            aria-label={t('Toggle navigation')}
            className="hidden size-10 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink md:grid"
          >
            <PanelLeft className="size-5" aria-hidden />
          </button>
          <div className="hidden shrink-0 lg:block">
            <NavbarMenu />
          </div>
          <GlobalSearch />
        </div>

        <div className="flex shrink-0 items-center gap-0.5 sm:gap-1.5 [&>button]:shrink-0 [&>div]:shrink-0">
          <NotificationBell />
          <MessagesButton />
          <ThemeToggle />
          <NavbarClock />
          <div className="hidden shrink-0 sm:block">
            <NavbarCalendar />
          </div>
          <Dropdown
            trigger={
              <button
                type="button"
                aria-label={t('Account menu for {name}', { name: user?.name ?? t('current user') })}
                className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-line bg-surface p-0 transition-colors hover:bg-canvas 2xl:h-auto 2xl:w-auto 2xl:gap-2 2xl:px-2.5 2xl:py-1.5"
              >
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-brand text-sm font-bold text-white">
                  {initials(user?.name)}
                </span>
                <span className="hidden text-left 2xl:block">
                  <span className="block max-w-40 truncate text-sm font-semibold text-ink">
                    {user?.name}
                  </span>
                  <span className="block max-w-40 truncate text-xs text-ink-muted">{role ? t(role) : role}</span>
                </span>
                <ChevronDown className="hidden size-4 text-ink-muted 2xl:block" aria-hidden />
              </button>
            }
          >
            <DropdownItem onClick={() => navigate('/settings')}>
              <UserRound className="size-4" aria-hidden /> {t('Profile')}
            </DropdownItem>
            <div className="mx-2 my-1 border-t border-line" />
            <DropdownItem danger onClick={handleSignOut} disabled={signingOut}>
              <LogOut className="size-4" aria-hidden /> {t('Log out')}
            </DropdownItem>
          </Dropdown>
        </div>
      </header>
    </>
  )
}
