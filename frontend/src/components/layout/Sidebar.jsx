import { useMemo, useState } from 'react'
import { NavLink } from 'react-router-dom'
import {
  BrainCircuit,
  CalendarDays,
  ChevronsLeft,
  ChevronsRight,
  FolderHeart,
  LayoutDashboard,
  MessageSquare,
  PackageSearch,
  Pill,
  Receipt,
  Settings,
  ShieldCheck,
  Users,
  BarChart3,
} from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { APP_NAME } from '../../constants/index.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useChatUnread } from '../../hooks/useChatUnread.js'

const NAV_ITEMS = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true, permissions: [] },
  { to: '/patients', label: 'Patients', icon: Users, permissions: ['patients.view'] },
  { to: '/appointments', label: 'Appointments', icon: CalendarDays, permissions: ['appointments.view'] },
  { to: '/chat', label: 'Team Chat', icon: MessageSquare, permissions: ['chat.view'] },
  { to: '/medical-records', label: 'Medical Records', icon: FolderHeart, permissions: ['medical-records.view'] },
  { to: '/prescriptions', label: 'Prescriptions', icon: Pill, permissions: ['prescriptions.view'] },
  { to: '/pharmacy', label: 'Pharmacy', icon: PackageSearch, permissions: ['medicines.view', 'inventory.view'] },
  { to: '/billing', label: 'Billing & Payments', icon: Receipt, permissions: ['billing.view'] },
  { to: '/reports', label: 'Reports', icon: BarChart3, permissions: ['reports.view'] },
  { to: '/users', label: 'Users & Roles', icon: ShieldCheck, permissions: ['users.view'] },
  { to: '/expert-system', label: 'Expert System', icon: BrainCircuit, permissions: ['expert-system.view', 'expert-system.use'] },
  { to: '/settings', label: 'Settings', icon: Settings, permissions: [] },
]

export default function Sidebar({ open, onClose, collapsed, onToggleCollapsed }) {
  const { user } = useAuth()
  const chatUnread = useChatUnread()
  const [localCollapsed, setLocalCollapsed] = useState(false)
  const isCollapsed = collapsed ?? localCollapsed

  const items = useMemo(() => {
    const perms = user?.permissions ?? []
    return NAV_ITEMS.filter(
      (item) => item.permissions.length === 0 || item.permissions.some((p) => perms.includes(p)),
    )
  }, [user])

  const toggleCollapse = () => {
    if (onToggleCollapsed) onToggleCollapsed()
    else setLocalCollapsed((c) => !c)
  }

  return (
    <>
      {open ? (
        <div
          className="fixed inset-0 z-40 bg-ink/40 md:hidden"
          onClick={onClose}
          aria-hidden
        />
      ) : null}
      <aside
        className={cn(
          'fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-brand-darker text-white transition-[width,transform] duration-200 md:static md:translate-x-0 print:hidden',
          open ? 'translate-x-0' : '-translate-x-full md:static',
          isCollapsed ? 'md:w-[80px]' : 'md:w-64',
        )}
        aria-label="Primary navigation"
      >
        <div className={cn('flex h-16 items-center gap-2.5 px-4', !open && isCollapsed && 'md:justify-center md:px-0')}>
          <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white shadow-md ring-1 ring-white/30">
            <img
              src="/royalcare-logo.png"
              alt={APP_NAME}
              className="size-7 object-contain"
            />
          </span>
          <div className={cn('min-w-0 leading-tight', isCollapsed && 'md:hidden')}>
            <p className="truncate text-base font-bold tracking-tight text-white">{APP_NAME}</p>
            <p className="truncate text-[10px] font-semibold uppercase tracking-wider text-blue-200/90">
              Medical &amp; Expert System
            </p>
          </div>
        </div>

        <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
          {items.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              onClick={onClose}
              title={item.label}
              className={({ isActive }) =>
                cn(
                  'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                  isActive ? 'bg-white text-brand-dark shadow-sm' : 'text-blue-100/80 hover:bg-white/10 hover:text-white',
                  isCollapsed && !open ? 'md:justify-center md:px-0' : '',
                )
              }
            >
              <item.icon className="size-5 shrink-0" aria-hidden />
              <span className={cn('flex-1 truncate', isCollapsed && !open && 'md:hidden')}>{item.label}</span>
              {item.to === '/chat' && chatUnread > 0 ? (
                <span
                  className={cn(
                    'grid min-w-5 place-items-center rounded-full bg-brand-soft px-1.5 text-[10px] font-bold leading-5 text-brand-dark',
                    isCollapsed && !open && 'md:hidden',
                  )}
                >
                  {chatUnread > 9 ? '9+' : chatUnread}
                </span>
              ) : null}
            </NavLink>
          ))}
        </nav>

        <div className="border-t border-white/10 p-3">
          <button
            type="button"
            onClick={toggleCollapse}
            aria-label={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            className="hidden w-full items-center justify-center gap-2 rounded-xl px-3 py-2 text-sm text-blue-100/80 transition-colors hover:bg-white/10 hover:text-white md:flex"
          >
            {isCollapsed ? <ChevronsRight className="size-4" aria-hidden /> : <ChevronsLeft className="size-4" aria-hidden />}
            {isCollapsed ? null : <span>Collapse</span>}
          </button>
        </div>
      </aside>
    </>
  )
}