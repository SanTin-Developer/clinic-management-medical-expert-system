import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { ChevronDown, LogOut, Menu, PanelLeft, UserRound } from 'lucide-react'
import Dropdown, { DropdownItem } from '../ui/Dropdown.jsx'
import NotificationBell from '../communication/NotificationBell.jsx'
import GlobalSearch from '../search/GlobalSearch.jsx'
import MessagesButton from './MessagesButton.jsx'
import ThemeToggle from './ThemeToggle.jsx'
import { Spinner } from '../ui/Skeleton.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { pageTitleFor } from '../../hooks/usePageTitle.js'

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
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const [signingOut, setSigningOut] = useState(false)

  const role = user?.roles?.[0] ?? ''
  const title = pageTitleFor(pathname)

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
          <Spinner label="Signing you out…" />
        </div>
      ) : null}
      <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line bg-surface px-4 print:hidden sm:px-6">
        <div className="flex min-w-0 items-center gap-1.5">
          <button
            type="button"
            onClick={onMenu}
            aria-label="Open navigation"
            className="grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas md:hidden"
          >
            <Menu className="size-5" aria-hidden />
          </button>
          <button
            type="button"
            onClick={onToggleSidebar}
            aria-label="Toggle navigation"
            className="hidden size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink md:grid"
          >
            <PanelLeft className="size-5" aria-hidden />
          </button>
          <h1 className="hidden truncate text-base font-semibold text-ink sm:block">{title}</h1>
        </div>

        <div className="flex flex-1 justify-center px-1">
          <GlobalSearch />
        </div>

        <div className="flex items-center gap-0.5 sm:gap-1.5">
          <NotificationBell />
          <MessagesButton />
          <ThemeToggle />
          <Dropdown
            trigger={
              <button
                type="button"
                className="flex items-center gap-2 rounded-xl border border-line bg-surface px-1.5 py-1.5 transition-colors hover:bg-canvas sm:px-2.5"
              >
                <span className="grid size-8 shrink-0 place-items-center rounded-full bg-brand text-sm font-bold text-white">
                  {initials(user?.name)}
                </span>
                <span className="hidden text-left sm:block">
                  <span className="block max-w-40 truncate text-sm font-semibold text-ink">
                    {user?.name}
                  </span>
                  <span className="block max-w-40 truncate text-xs text-ink-muted">{role}</span>
                </span>
                <ChevronDown className="hidden size-4 text-ink-muted sm:block" aria-hidden />
              </button>
            }
          >
            <DropdownItem onClick={() => navigate('/settings')}>
              <UserRound className="size-4" aria-hidden /> Profile
            </DropdownItem>
            <div className="mx-2 my-1 border-t border-line" />
            <DropdownItem danger onClick={handleSignOut} disabled={signingOut}>
              <LogOut className="size-4" aria-hidden /> Log out
            </DropdownItem>
          </Dropdown>
        </div>
      </header>
    </>
  )
}
