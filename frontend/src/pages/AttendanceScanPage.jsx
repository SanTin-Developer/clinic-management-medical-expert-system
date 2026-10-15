import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  ArrowLeft,
  Camera,
  CameraOff,
  CheckCircle2,
  KeyRound,
  RotateCcw,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import Select from '../components/ui/Select.jsx'
import { attendanceApi } from '../api/attendance.js'

const READER_ID = 'attendance-scan-reader'
const ACTION_KEY = 'clinic.attendance.scanAction'

function parsePayload(text, fallbackAction) {
  const trimmed = String(text ?? '').trim()
  if (!trimmed) return null

  try {
    const parsed = JSON.parse(trimmed)
    if (parsed && typeof parsed === 'object' && parsed.t) {
      return { token: String(parsed.t), action: parsed.a || fallbackAction }
    }
  } catch {
    // Not JSON: treat the scanned text as the raw token.
  }

  return { token: trimmed, action: fallbackAction }
}

export default function AttendanceScanPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [action, setAction] = useState(() => localStorage.getItem(ACTION_KEY) || 'clock_in')
  const [active, setActive] = useState(true)
  const [scanning, setScanning] = useState(false)
  const [status, setStatus] = useState('')
  const [manualToken, setManualToken] = useState('')
  const [busy, setBusy] = useState(false)
  const [success, setSuccess] = useState(null)

  const scannerRef = useRef(null)
  const busyRef = useRef(false)
  const actionRef = useRef(action)
  const submitRef = useRef(null)

  useEffect(() => {
    actionRef.current = action
    localStorage.setItem(ACTION_KEY, action)
  }, [action])

  const submit = useCallback(
    async (token, resolvedAction) => {
      const act = resolvedAction || actionRef.current
      if (busyRef.current || !token) return
      busyRef.current = true
      setBusy(true)
      try {
        await attendanceApi.scan({ token, action: act })
        setActive(false)
        setSuccess({ action: act })
        queryClient.invalidateQueries({ queryKey: ['attendance'] })
        toast.success(act === 'clock_out' ? 'Clocked out successfully.' : 'Clocked in successfully.')
      } catch (err) {
        setStatus(err.message || 'This code could not be accepted.')
        // Allow the staff member to try the next code after a short pause.
        setTimeout(() => {
          busyRef.current = false
          setBusy(false)
        }, 1500)
        return
      }
      busyRef.current = false
      setBusy(false)
    },
    [queryClient],
  )

  useEffect(() => {
    submitRef.current = submit
  }, [submit])

  useEffect(() => {
    if (!active) return undefined

    let cancelled = false
    let instance = null

    // html5-qrcode throws synchronously from stop() when the scanner is not
    // running (e.g. the page closed before the camera finished starting),
    // so every call is guarded to avoid crashing the React tree.
    const teardown = async (target) => {
      if (!target) return
      try {
        await target.stop()
      } catch {
        // Scanner was not running or paused.
      }
      try {
        await target.clear()
      } catch {
        // Nothing to clear.
      }
    }

    const start = async () => {
      try {
        const { Html5Qrcode } = await import('html5-qrcode')
        if (cancelled) return
        instance = new Html5Qrcode(READER_ID, { verbose: false })
        scannerRef.current = instance

        await instance.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 260, height: 260 } },
          (decodedText) => {
            const payload = parsePayload(decodedText, actionRef.current)
            if (payload) submitRef.current?.(payload.token, payload.action)
          },
          () => {},
        )
        if (cancelled) {
          teardown(instance)
          return
        }
        setScanning(true)
      } catch {
        if (!cancelled) {
          setScanning(false)
          setStatus('Camera unavailable. Allow camera access, then try again or enter the code manually.')
        }
      }
    }

    start()

    return () => {
      cancelled = true
      const current = scannerRef.current
      scannerRef.current = null
      setScanning(false)
      teardown(current)
    }
  }, [active])

  const rescan = () => {
    busyRef.current = false
    setBusy(false)
    setStatus('')
    setSuccess(null)
    setActive(true)
  }

  const handleManual = (event) => {
    event.preventDefault()
    submit(manualToken.trim(), action)
  }

  return (
    <div className="mx-auto w-full max-w-lg">
      <PageHeader
        title="Scan attendance"
        description="Point your camera at the QR code shown on the kiosk screen."
        actions={
          <Button variant="outline" size="sm" onClick={() => navigate('/attendance')}>
            <ArrowLeft className="size-4" aria-hidden /> Attendance
          </Button>
        }
      />

      {success ? (
        <div className="flex flex-col items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-10 text-center">
          <CheckCircle2 className="size-14 text-emerald-600" aria-hidden />
          <p className="text-2xl font-bold text-ink">
            {success.action === 'clock_out' ? 'Clocked out' : 'Clocked in'}
          </p>
          <p className="text-ink-muted">Your attendance has been recorded.</p>
          <div className="mt-2 flex flex-wrap justify-center gap-2">
            <Button onClick={rescan}>
              <RotateCcw className="size-4" aria-hidden /> Scan again
            </Button>
            <Button variant="outline" onClick={() => navigate('/attendance')}>
              Done
            </Button>
          </div>
        </div>
      ) : (
        <div className="space-y-4">
          <div className="relative overflow-hidden rounded-2xl border border-line bg-slate-950">
            <div id={READER_ID} className="mx-auto min-h-[280px] w-full [&_video]:w-full" />
            {!scanning ? (
              <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center text-slate-300">
                {status ? <CameraOff className="size-7" aria-hidden /> : <Camera className="size-7" aria-hidden />}
                <p className="px-6 text-sm">{status || 'Opening camera…'}</p>
              </div>
            ) : null}
          </div>

          {status && scanning ? (
            <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
              {status}
            </p>
          ) : null}

          <div className="rounded-2xl border border-line bg-surface p-4">
            <form onSubmit={handleManual} className="space-y-3">
              <Field label="Action" htmlFor="scan-action">
                <Select id="scan-action" value={action} onChange={(e) => setAction(e.target.value)}>
                  <option value="clock_in">Clock in</option>
                  <option value="clock_out">Clock out</option>
                </Select>
              </Field>
              <Field
                label="Or enter the code manually"
                htmlFor="scan-manual"
                hint="Useful when a camera is not available."
              >
                <Input
                  id="scan-manual"
                  value={manualToken}
                  onChange={(e) => setManualToken(e.target.value)}
                  placeholder="Paste the code…"
                />
              </Field>
              <Button type="submit" loading={busy} disabled={!manualToken.trim()} className="w-full">
                <KeyRound className="size-4" aria-hidden /> Submit code
              </Button>
            </form>
          </div>
        </div>
      )}
    </div>
  )
}
