import { useCallback, useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import { Camera, CameraOff, KeyRound } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { attendanceApi } from '../../api/attendance.js'

const READER_ID = 'attendance-qr-reader'

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

export default function AttendanceScannerModal({ open, onClose, defaultAction = 'clock_in', onScanned }) {
  const [scanning, setScanning] = useState(false)
  const [status, setStatus] = useState('')
  const [manualToken, setManualToken] = useState('')
  const [action, setAction] = useState(defaultAction)
  const [busy, setBusy] = useState(false)

  const scannerRef = useRef(null)
  const busyRef = useRef(false)
  const actionRef = useRef(action)
  const submitRef = useRef(null)

  const submit = useCallback(
    async (token, resolvedAction) => {
      if (busyRef.current || !token) return
      busyRef.current = true
      setBusy(true)
      try {
        const result = await attendanceApi.scan({ token, action: resolvedAction || actionRef.current })
        toast.success(resolvedAction === 'clock_out' ? 'Clocked out successfully.' : 'Clocked in successfully.')
        onScanned?.(result)
        setStatus('')
        onClose?.()
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
    [onClose, onScanned],
  )

  useEffect(() => {
    actionRef.current = action
  }, [action])

  useEffect(() => {
    submitRef.current = submit
  }, [submit])

  useEffect(() => {
    if (!open) return undefined

    let cancelled = false
    let instance = null

    // html5-qrcode throws synchronously from stop() when the scanner is not
    // running (e.g. the modal was closed before the camera finished starting),
    // so every call must be guarded to avoid crashing the React tree.
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
          { fps: 10, qrbox: { width: 240, height: 240 } },
          (decodedText) => {
            const payload = parsePayload(decodedText, actionRef.current)
            if (payload) submitRef.current?.(payload.token, payload.action)
          },
          () => {},
        )
        if (cancelled) {
          // Closed while the camera was still starting: tear it down.
          teardown(instance)
          return
        }
        setScanning(true)
      } catch {
        if (!cancelled) {
          setScanning(false)
          setStatus('Camera unavailable. Enter the code manually below.')
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
  }, [open])

  const handleManual = (event) => {
    event.preventDefault()
    submit(manualToken.trim(), action)
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Scan attendance QR"
      description="Point the camera at the rotating code shown by the administrator."
      size="md"
      footer={
        <Button variant="outline" onClick={onClose}>
          Done
        </Button>
      }
    >
      <div className="space-y-4">
        <div className="relative overflow-hidden rounded-xl border border-line bg-slate-950">
          <div id={READER_ID} className="mx-auto min-h-[240px] w-full [&_video]:w-full" />
          {!scanning ? (
            <div className="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center text-slate-300">
              {status ? <CameraOff className="size-6" aria-hidden /> : <Camera className="size-6" aria-hidden />}
              <p className="px-4 text-xs">{status || 'Requesting camera…'}</p>
            </div>
          ) : null}
        </div>

        {status && scanning ? (
          <p role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {status}
          </p>
        ) : null}

        <form onSubmit={handleManual} className="space-y-3">
          <Field label="Action" htmlFor="scan-action">
            <Select id="scan-action" value={action} onChange={(e) => setAction(e.target.value)}>
              <option value="clock_in">Clock in</option>
              <option value="clock_out">Clock out</option>
            </Select>
          </Field>
          <Field label="Or enter the code manually" htmlFor="scan-manual" hint="Useful when a camera is not available.">
            <Input
              id="scan-manual"
              value={manualToken}
              onChange={(e) => setManualToken(e.target.value)}
              placeholder="Paste the code…"
            />
          </Field>
          <Button type="submit" loading={busy} disabled={!manualToken.trim()} className="w-full sm:w-auto">
            <KeyRound className="size-4" aria-hidden /> Submit code
          </Button>
        </form>
      </div>
    </Modal>
  )
}
