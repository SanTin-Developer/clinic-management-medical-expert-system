import { useEffect, useRef, useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  CircleCheck,
  Clock,
  Hourglass,
  Maximize,
  Minimize,
  Radio,
  UserCheck,
  Users,
  WifiOff,
} from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
import Badge from '../components/ui/Badge.jsx'
import Button from '../components/ui/Button.jsx'
import { attendanceApi } from '../api/attendance.js'
import { useFullscreen } from '../hooks/useFullscreen.js'
import { usePageTitle } from '../hooks/usePageTitle.js'

const TOKEN_REFRESH_MS = 5000
const PAYLOAD_POLL_MS = 3000
const TICK_MS = 500

const INITIAL_NOW = Date.now()

const CLOCK_FMT = new Intl.DateTimeFormat('en-US', {
  hour: 'numeric',
  minute: '2-digit',
  second: '2-digit',
})
const DATE_FMT = new Intl.DateTimeFormat('en-US', {
  weekday: 'long',
  year: 'numeric',
  month: 'long',
  day: 'numeric',
})
const SCAN_TIME_FMT = new Intl.DateTimeFormat('en-US', {
  hour: 'numeric',
  minute: '2-digit',
  second: '2-digit',
})

function formatClock(ms) {
  return CLOCK_FMT.format(new Date(ms))
}

function formatFullDate(ms) {
  return DATE_FMT.format(new Date(ms))
}

function formatScanTime(iso) {
  const d = new Date(iso)
  return Number.isNaN(d.getTime()) ? '' : SCAN_TIME_FMT.format(d)
}

const ACTION_LABELS = {
  clock_in: 'Clock In',
  clock_out: 'Clock Out',
}

const CONNECTION = {
  live: { label: 'Live', tone: 'green', Icon: Radio },
  connecting: { label: 'Connecting…', tone: 'amber', Icon: Hourglass },
  disconnected: { label: 'Disconnected', tone: 'red', Icon: WifiOff },
}

export default function AttendanceKioskPage() {
  const { sessionId } = useParams()
  usePageTitle('Attendance Kiosk')
  const rootRef = useRef(null)
  const { isFullscreen, toggle, error: fullscreenError } = useFullscreen()

  const [nowMs, setNowMs] = useState(INITIAL_NOW)

  const payloadQuery = useQuery({
    queryKey: ['attendance', 'session', sessionId],
    queryFn: () => attendanceApi.sessions.get(sessionId),
    enabled: Boolean(sessionId),
    refetchInterval: (query) => {
      const status = query.state.data?.session?.status
      return status && status !== 'open' ? false : PAYLOAD_POLL_MS
    },
    refetchIntervalInBackground: true,
    retry: 1,
  })

  const payload = payloadQuery.data
  const session = payload?.session
  const isOpen = !session || session.status === 'open'
  const stats = payload?.stats ?? {}

  const tokenQuery = useQuery({
    queryKey: ['attendance', 'session', sessionId, 'token'],
    queryFn: () => attendanceApi.sessions.token(sessionId),
    enabled: Boolean(sessionId) && isOpen,
    refetchInterval: isOpen ? TOKEN_REFRESH_MS : false,
    refetchIntervalInBackground: true,
    retry: false,
  })

  const token = tokenQuery.data

  useEffect(() => {
    const id = setInterval(() => setNowMs(Date.now()), TICK_MS)
    return () => clearInterval(id)
  }, [])

  const ttl = token?.ttl ?? TOKEN_REFRESH_MS / 1000
  const dataUpdatedAt = tokenQuery.dataUpdatedAt
  const remaining =
    token && dataUpdatedAt ? Math.max(0, Math.ceil(ttl - (nowMs - dataUpdatedAt) / 1000)) : 0
  const progress = ttl > 0 ? Math.min(100, Math.round((remaining / ttl) * 100)) : 0

  const qrValue =
    token?.token && session ? JSON.stringify({ t: token.token, a: session.action }) : ''

  const connection = payloadQuery.isError
    ? 'disconnected'
    : payloadQuery.isLoading && !payload
      ? 'connecting'
      : 'live'
  const connectionMeta = CONNECTION[connection]
  const ConnectionIcon = connectionMeta.Icon

  const scanned = payload?.scanned ?? []
  const actionLabel = ACTION_LABELS[session?.action] ?? 'Attendance'

  return (
    <div
      ref={rootRef}
      className="flex min-h-screen flex-col bg-canvas px-6 py-5 text-ink sm:px-10 sm:py-8"
    >
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <span
            className="grid size-14 shrink-0 select-none place-items-center rounded-2xl bg-white shadow-md ring-1 ring-line"
            onContextMenu={(event) => event.preventDefault()}
            onCopy={(event) => event.preventDefault()}
            onCut={(event) => event.preventDefault()}
          >
            <img
              src="/royalcare-logo.png"
              alt="RoyalCare logo"
              draggable={false}
              style={{ WebkitUserDrag: 'none', userSelect: 'none' }}
              className="pointer-events-none size-10 object-contain"
            />
          </span>
          <div>
            <p className="text-2xl font-bold tracking-tight text-ink">RoyalCare</p>
            <p className="text-sm text-ink-muted">Staff Attendance</p>
          </div>
        </div>

        <div className="flex items-center gap-3">
          <Badge tone={connectionMeta.tone}>
            <ConnectionIcon className="size-3.5" aria-hidden />
            {connectionMeta.label}
          </Badge>
          <Button variant="outline" size="sm" onClick={() => toggle(rootRef.current)}>
            {isFullscreen ? (
              <Minimize className="size-4" aria-hidden />
            ) : (
              <Maximize className="size-4" aria-hidden />
            )}
            {isFullscreen ? 'Exit full screen' : 'Full screen'}
          </Button>
        </div>
      </header>

      {fullscreenError ? (
        <p role="alert" className="mt-4 rounded-xl bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800">
          {fullscreenError}
        </p>
      ) : null}

      {payloadQuery.isError && payloadQuery.error?.status === 404 ? (
        <div className="grid flex-1 place-items-center">
          <div className="max-w-md text-center">
            <WifiOff className="mx-auto size-10 text-red-500" aria-hidden />
            <p className="mt-3 text-2xl font-semibold text-ink">Session not found</p>
            <p className="mt-1 text-ink-muted">
              This attendance session no longer exists. Ask an administrator to open a new one.
            </p>
          </div>
        </div>
      ) : (
        <main className="mt-6 grid flex-1 grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
          <section className="flex flex-col items-center justify-center rounded-3xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]">
            <div className="text-center">
              <p className="text-3xl font-bold text-ink">{session?.shift_name ?? '—'}</p>
              <div className="mt-2 flex items-center justify-center gap-2">
                <Badge tone="brand">{actionLabel}</Badge>
                <Badge tone={isOpen ? 'green' : 'gray'}>
                  {isOpen ? 'Session open' : 'Session closed'}
                </Badge>
              </div>
            </div>

            <div className="relative mt-6 flex items-center justify-center">
              {isOpen && qrValue ? (
                <div className="rounded-3xl bg-white p-6 shadow-lg">
                  <QRCodeSVG value={qrValue} size={400} level="M" includeMargin className="h-auto w-full max-w-[400px]" />
                </div>
              ) : (
                <div className="grid size-[360px] max-w-full place-items-center rounded-3xl border-2 border-dashed border-line bg-canvas text-center">
                  {isOpen ? (
                    <p className="text-lg text-ink-muted">Preparing secure code…</p>
                  ) : (
                    <div>
                      <CircleCheck className="mx-auto size-14 text-ink-muted" aria-hidden />
                      <p className="mt-3 text-2xl font-semibold text-ink">Session closed</p>
                      <p className="mt-1 text-sm text-ink-muted">Scanning is no longer available.</p>
                    </div>
                  )}
                </div>
              )}
            </div>

            {isOpen && token ? (
              <div className="mt-6 w-full max-w-md">
                <div className="flex items-center justify-between text-sm font-medium text-ink">
                  <span className="flex items-center gap-1.5 text-ink-muted">
                    <Clock className="size-4" aria-hidden /> Code refreshes in
                  </span>
                  <span className="tabular-nums">{remaining}s</span>
                </div>
                <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-line">
                  <div
                    className="h-full rounded-full bg-brand transition-[width] duration-500 ease-linear"
                    style={{ width: `${progress}%` }}
                  />
                </div>
                <p className="mt-2 text-center text-xs text-ink-muted">
                  The secure code rotates every {ttl} seconds. Staff scan using their own account.
                </p>
              </div>
            ) : null}
          </section>

          <section className="flex flex-col gap-5">
            <div className="rounded-3xl border border-line bg-surface p-6 text-center shadow-[var(--shadow-card)]">
              <p className="text-5xl font-bold tabular-nums text-ink">{formatClock(nowMs)}</p>
              <p className="mt-1 text-lg text-ink-muted">{formatFullDate(nowMs)}</p>
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div className="rounded-3xl border border-line bg-surface p-5 text-center shadow-[var(--shadow-card)]">
                <Users className="mx-auto size-6 text-ink-muted" aria-hidden />
                <p className="mt-2 text-4xl font-bold text-ink">{stats.assigned ?? 0}</p>
                <p className="text-sm text-ink-muted">Assigned</p>
              </div>
              <div className="rounded-3xl border border-line bg-surface p-5 text-center shadow-[var(--shadow-card)]">
                <UserCheck className="mx-auto size-6 text-green-600" aria-hidden />
                <p className="mt-2 text-4xl font-bold text-ink">{stats.scanned ?? 0}</p>
                <p className="text-sm text-ink-muted">Scanned</p>
              </div>
              <div className="rounded-3xl border border-line bg-surface p-5 text-center shadow-[var(--shadow-card)]">
                <Hourglass className="mx-auto size-6 text-amber-600" aria-hidden />
                <p className="mt-2 text-4xl font-bold text-ink">{stats.unscanned ?? 0}</p>
                <p className="text-sm text-ink-muted">Pending</p>
              </div>
            </div>

            <div className="flex-1 rounded-3xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]">
              <p className="mb-3 text-lg font-semibold text-ink">Recent scans</p>
              {scanned.length === 0 ? (
                <p className="text-ink-muted">No scans yet. Staff will appear here as they check in.</p>
              ) : (
                <ul className="space-y-2">
                  {scanned.slice(0, 8).map((row) => (
                    <li
                      key={row.user_id}
                      className="flex items-center justify-between rounded-2xl bg-canvas px-4 py-3"
                    >
                      <span className="flex min-w-0 items-center gap-3 text-lg text-ink">
                        <CircleCheck className="size-5 shrink-0 text-green-600" aria-hidden />
                        <span className="truncate">{row.name ?? 'Staff'}</span>
                      </span>
                      <span className="flex shrink-0 items-center gap-3">
                        <span className="text-sm tabular-nums text-ink-muted">
                          {row.at ? formatScanTime(row.at) : ''}
                        </span>
                        {row.late_minutes > 0 ? (
                          <Badge tone="amber">{row.late_minutes} min late</Badge>
                        ) : (
                          <Badge tone="green">On time</Badge>
                        )}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            {connection === 'disconnected' ? (
              <div className="rounded-2xl bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                <p>Connection lost. Retrying automatically…</p>
                <Button variant="outline" size="sm" className="mt-2" onClick={() => payloadQuery.refetch()}>
                  Reconnect now
                </Button>
              </div>
            ) : null}
          </section>
        </main>
      )}
    </div>
  )
}
