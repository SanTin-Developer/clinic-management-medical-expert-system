import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { ChevronDown, LogOut, Menu, Settings } from 'lucide-react'
import Dropdown, { DropdownItem } from '../ui/Dropdown.jsx'
import NotificationBell from '../communication/NotificationBell.jsx'
import { Spinner } from '../ui/Skeleton.jsx'
import { useAuth } from '../../hooks/useAuth.js'

function initials(name = '') {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

export default function Header({ onMenu }) {
  const { user, logout } = useAuth()
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
          className="fixed inset-0 z-[100] grid place-items-center bg-white/80 backdrop-blur-sm"
        >
          <Spinner label="Signing you out…" />
        </div>
      ) : null}
      <header className="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line bg-surface px-4 print:hidden sm:px-6">
        <button
          type="button"
          onClick={onMenu}
          aria-label="Open navigation"
          className="grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas md:hidden"
        >
          <Menu className="size-5" aria-hidden />
        </button>

        <div />
        <div className="flex items-center gap-3">
          <NotificationBell />
          <Dropdown
            trigger={
              <button
                type="button"
                className="flex items-center gap-2 rounded-xl border border-line bg-surface px-2 py-1.5 transition-colors hover:bg-canvas sm:px-3"
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
              <Settings className="size-4" aria-hidden /> Settings
            </DropdownItem>
            <div className="mx-2 my-1 border-t border-line" />
            <DropdownItem danger onClick={handleSignOut} disabled={signingOut}>
              <LogOut className="size-4" aria-hidden /> Sign out
            </DropdownItem>
          </Dropdown>
        </div>
      </header>
    </>
  )
}