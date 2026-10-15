import { useMemo, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { QRCodeSVG } from 'qrcode.react'
import {
  CircleCheck,
  CircleDot,
  ExternalLink,
  Loader2,
  Maximize,
  Minimize,
  QrCode,
  UserCheck,
  Users,
} from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Badge from '../ui/Badge.jsx'
import { attendanceApi } from '../../api/attendance.js'
import { shiftTemplateApi } from '../../api/shiftTemplates.js'
import { useFullscreen } from '../../hooks/useFullscreen.js'
import { toIsoDate } from '../../utils/format.js'

const today = toIsoDate(new Date())
const TOKEN_REFRESH_MS = 5000
const PAYLOAD_POLL_MS = 3000

export default function AttendanceSessionModal({ open, onClose }) {
  const [templateId, setTemplateId] = useState('')
  const [date, setDate] = useState(today)
  const [action, setAction] = useState('clock_in')
  const [sessionId, setSessionId] = useState(null)
  const [opening, setOpening] = useState(false)
  const [popupBlocked, setPopupBlocked] = useState(false)
  const displayRef = useRef(null)
  const { isFullscreen, toggle: toggleFullscreen, error: fullscreenError } = useFullscreen()

  const templatesQuery = useQuery({
    queryKey: ['shift-templates'],
    queryFn: () => shiftTemplateApi.list(),
    enabled: open,
  })

  const tokenQuery = useQuery({
    queryKey: ['attendance', 'session', sessionId, 'token'],
    queryFn: () => attendanceApi.sessions.token(sessionId),
    enabled: open && Boolean(sessionId),
    refetchInterval: TOKEN_REFRESH_MS,
    refetchIntervalInBackground: true,
    retry: false,
  })

  const payloadQuery = useQuery({
    queryKey: ['attendance', 'session', sessionId],
    queryFn: () => attendanceApi.sessions.get(sessionId),
    enabled: open && Boolean(sessionId),
    refetchInterval: PAYLOAD_POLL_MS,
    refetchIntervalInBackground: true,
    retry: false,
  })

  const payload = payloadQuery.data
  const stats = payload?.stats ?? {}
  const token = tokenQuery.data

  const qrValue = useMemo(() => {
    if (!token?.token) return ''
    return JSON.stringify({ t: token.token, a: action })
  }, [token, action])

  const openSession = async () => {
    if (!templateId) {
      toast.error('Select a shift template.')
      return
    }
    setOpening(true)
    try {
      const payload = await attendanceApi.sessions.open({
        shift_template_id: templateId,
        session_date: date,
        action,
      })
      setSessionId(payload.session.id)
      toast.success('Attendance session opened.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setOpening(false)
    }
  }

  const closeSession = async () => {
    if (!sessionId) return
    try {
      await attendanceApi.sessions.close(sessionId)
      toast.success('Attendance session closed.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSessionId(null)
    }
  }

  const openKiosk = () => {
    if (!sessionId) return
    const url = `${window.location.origin}/attendance/kiosk/${sessionId}`
    const win = window.open(url, '_blank')
    if (win) {
      win.opener = null
      setPopupBlocked(false)
      return
    }
    setPopupBlocked(true)
    toast.error('Pop-up blocked. Allow pop-ups for this site, then try again.')
  }

  const handleClose = () => {
    onClose?.()
  }

  return (
    <Modal
      open={open}
      onClose={handleClose}
      title="QR attendance session"
      description="Open a session, project the rotating code, and watch staff scan in live."
      size="xl"
    >
      <div className="space-y-5">
        {!sessionId ? (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <Field label="Shift template" htmlFor="session-template" required>
              <Select
                id="session-template"
                value={templateId}
                onChange={(e) => setTemplateId(e.target.value)}
                disabled={templatesQuery.isLoading}
              >
                <option value="">{templatesQuery.isLoading ? 'Loading…' : 'Select…'}</option>
                {(templatesQuery.data ?? []).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="Date" htmlFor="session-date" required>
              <Input id="session-date" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
            </Field>
            <Field label="Action" htmlFor="session-action" required>
              <Select id="session-action" value={action} onChange={(e) => setAction(e.target.value)}>
                <option value="clock_in">Clock in</option>
                <option value="clock_out">Clock out</option>
              </Select>
            </Field>
            <div className="sm:col-span-3">
              <Button onClick={openSession} loading={opening}>
                <QrCode className="size-4" aria-hidden /> Open session
              </Button>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div className="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" onClick={openKiosk}>
                  <ExternalLink className="size-4" aria-hidden /> Open in new tab
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => toggleFullscreen(displayRef.current)}
                >
                  {isFullscreen ? (
                    <Minimize className="size-4" aria-hidden />
                  ) : (
                    <Maximize className="size-4" aria-hidden />
                  )}
                  {isFullscreen ? 'Exit full screen' : 'Full screen'}
                </Button>
              </div>
              <p className="text-xs text-ink-muted">
                Display only — closing the display does not end the session.
              </p>
            </div>

            {popupBlocked ? (
              <p
                role="alert"
                className="rounded-xl bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800"
              >
                Your browser blocked the new tab. Allow pop-ups for this site and try again.
              </p>
            ) : null}

            {fullscreenError ? (
              <p
                role="alert"
                className="rounded-xl bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800"
              >
                {fullscreenError}
              </p>
            ) : null}

            <div
              ref={displayRef}
              className="grid grid-cols-1 gap-6 rounded-2xl bg-surface p-4 sm:p-6 lg:grid-cols-2"
            >
            <div className="flex flex-col items-center gap-3">
              <div className="rounded-2xl border border-line bg-white p-4">
                {qrValue ? (
                  <QRCodeSVG value={qrValue} size={232} level="M" includeMargin />
                ) : (
                  <div className="flex size-[232px] items-center justify-center text-ink-muted">
                    <Loader2 className="size-6 animate-spin" aria-hidden />
                  </div>
                )}
              </div>
              <p className="text-center text-xs text-ink-muted">
                Refreshing every {TOKEN_REFRESH_MS / 1000}s. Staff scan with their own account.
              </p>
              <Badge tone={payload?.session?.status === 'open' ? 'green' : 'gray'}>
                {payload?.session?.status ?? 'open'}
              </Badge>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={() => tokenQuery.refetch()}>
                  Refresh code
                </Button>
                <Button variant="danger" size="sm" onClick={closeSession}>
                  Close session
                </Button>
              </div>
            </div>

            <div className="space-y-4">
              <div className="grid grid-cols-3 gap-3 text-center">
                <div className="rounded-xl border border-line p-3">
                  <Users className="mx-auto size-4 text-ink-muted" aria-hidden />
                  <p className="mt-1 text-lg font-semibold text-ink">{stats.assigned ?? 0}</p>
                  <p className="text-xs text-ink-muted">Assigned</p>
                </div>
                <div className="rounded-xl border border-line p-3">
                  <UserCheck className="mx-auto size-4 text-green-600" aria-hidden />
                  <p className="mt-1 text-lg font-semibold text-ink">{stats.scanned ?? 0}</p>
                  <p className="text-xs text-ink-muted">Scanned</p>
                </div>
                <div className="rounded-xl border border-line p-3">
                  <CircleDot className="mx-auto size-4 text-amber-600" aria-hidden />
                  <p className="mt-1 text-lg font-semibold text-ink">{stats.unscanned ?? 0}</p>
                  <p className="text-xs text-ink-muted">Pending</p>
                </div>
              </div>

              <div>
                <p className="mb-1 text-sm font-medium text-ink">Waiting to scan</p>
                <ul className="max-h-40 space-y-1 overflow-y-auto">
                  {(payload?.unscanned ?? []).length === 0 ? (
                    <li className="text-sm text-ink-muted">Everyone has scanned.</li>
                  ) : (
                    payload.unscanned.map((row) => (
                      <li key={row.user_id} className="flex items-center justify-between rounded-lg bg-canvas px-3 py-1.5 text-sm">
                        <span className="text-ink">{row.name}</span>
                        <span className="text-ink-muted">{row.department ?? ''}</span>
                      </li>
                    ))
                  )}
                </ul>
              </div>

              <div>
                <p className="mb-1 text-sm font-medium text-ink">Scanned</p>
                <ul className="max-h-40 space-y-1 overflow-y-auto">
                  {(payload?.scanned ?? []).length === 0 ? (
                    <li className="text-sm text-ink-muted">No scans yet.</li>
                  ) : (
                    payload.scanned.map((row) => (
                      <li key={row.user_id} className="flex items-center justify-between rounded-lg bg-green-50 px-3 py-1.5 text-sm">
                        <span className="flex items-center gap-1.5 text-ink">
                          <CircleCheck className="size-4 text-green-600" aria-hidden />
                          {row.name}
                        </span>
                        {row.late_minutes > 0 ? (
                          <Badge tone="amber">{row.late_minutes} min late</Badge>
                        ) : (
                          <Badge tone="green">On time</Badge>
                        )}
                      </li>
                    ))
                  )}
                </ul>
              </div>
            </div>
            </div>
          </div>
        )}
      </div>
    </Modal>
  )
}
