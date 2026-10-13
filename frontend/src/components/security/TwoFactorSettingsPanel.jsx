import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { QRCodeSVG } from 'qrcode.react'
import { CheckCircle2, Copy, KeyRound, RefreshCw, ShieldCheck, ShieldOff } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import Modal from '../ui/Modal.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import { CardSkeleton } from '../ui/Skeleton.jsx'
import {
  twoFactorStatus,
  enrollTwoFactor,
  confirmTwoFactor,
  disableTwoFactor,
  regenerateRecoveryCodes,
} from '../../api/auth.js'
import { formatDateTime } from '../../utils/format.js'

function CodesList({ codes, onDone, doneLabel = 'Done' }) {
  const copyAll = async () => {
    try {
      await navigator.clipboard.writeText(codes.join('\n'))
      toast.success('Recovery codes copied.')
    } catch {
      toast.error('Could not copy to the clipboard.')
    }
  }

  return (
    <div className="space-y-4">
      <div role="status" className="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
        Store these recovery codes somewhere safe. Each works once and they will not be shown again.
      </div>
      <ul className="grid grid-cols-2 gap-2">
        {codes.map((code) => (
          <li key={code} className="rounded-lg border border-line bg-canvas px-3 py-2 text-center font-mono text-sm text-ink">
            {code}
          </li>
        ))}
      </ul>
      <div className="flex justify-between gap-2">
        <Button variant="outline" onClick={copyAll}>
          <Copy className="size-4" aria-hidden /> Copy all
        </Button>
        <Button onClick={onDone}>{doneLabel}</Button>
      </div>
    </div>
  )
}

export default function TwoFactorSettingsPanel() {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['twoFactorStatus'],
    queryFn: twoFactorStatus,
  })

  const [enrollOpen, setEnrollOpen] = useState(false)
  const [enrollStep, setEnrollStep] = useState('password')
  const [enrollment, setEnrollment] = useState(null)
  const [recoveryCodes, setRecoveryCodes] = useState([])

  const [password, setPassword] = useState('')
  const [code, setCode] = useState('')
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)

  const [disableOpen, setDisableOpen] = useState(false)
  const [regenerateOpen, setRegenerateOpen] = useState(false)

  const resetEnroll = () => {
    setEnrollStep('password')
    setEnrollment(null)
    setRecoveryCodes([])
    setPassword('')
    setCode('')
    setErrors({})
  }

  const closeEnroll = () => {
    setEnrollOpen(false)
    resetEnroll()
  }

  const handleStartEnroll = async () => {
    setErrors({})
    if (!password) {
      setErrors({ password: 'Enter your account password.' })
      return
    }
    setBusy(true)
    try {
      const result = await enrollTwoFactor(password)
      setEnrollment(result)
      setEnrollStep('scan')
      setPassword('')
    } catch (err) {
      setErrors({ password: err.message })
    } finally {
      setBusy(false)
    }
  }

  const handleConfirmEnroll = async () => {
    setErrors({})
    if (!/^\d{6}$/.test(code)) {
      setErrors({ code: 'Enter the 6-digit code from your authenticator app.' })
      return
    }
    setBusy(true)
    try {
      const result = await confirmTwoFactor(code)
      setRecoveryCodes(result.recovery_codes ?? [])
      setEnrollStep('codes')
      setCode('')
      refetch()
    } catch (err) {
      setErrors({ code: err.message })
    } finally {
      setBusy(false)
    }
  }

  const handleDisable = async () => {
    setErrors({})
    if (!password) {
      setErrors({ password: 'Enter your account password.' })
      return
    }
    setBusy(true)
    try {
      await disableTwoFactor(password)
      toast.success('Two-factor authentication disabled.')
      setDisableOpen(false)
      setPassword('')
      refetch()
    } catch (err) {
      setErrors({ password: err.message })
    } finally {
      setBusy(false)
    }
  }

  const handleRegenerate = async () => {
    setErrors({})
    if (!password) {
      setErrors({ password: 'Enter your account password.' })
      return
    }
    setBusy(true)
    try {
      const result = await regenerateRecoveryCodes(password)
      setRecoveryCodes(result.recovery_codes ?? [])
      setRegenerateOpen(false)
      setPassword('')
      setEnrollOpen(true)
      setEnrollStep('codes')
      refetch()
    } catch (err) {
      setErrors({ password: err.message })
    } finally {
      setBusy(false)
    }
  }

  if (isLoading) return <CardSkeleton rows={3} />
  if (isError) {
    return (
      <Card title="Two-factor authentication">
        <div className="flex items-center justify-between gap-3">
          <p className="text-sm text-red-600">{error.message}</p>
          <Button variant="outline" size="sm" onClick={() => refetch()}>
            Try again
          </Button>
        </div>
      </Card>
    )
  }

  const enabled = Boolean(data?.enabled)
  const remaining = data?.recovery_codes_remaining ?? 0

  return (
    <Card
      title="Two-factor authentication"
      subtitle="Add an authenticator app as a second sign-in factor."
      actions={
        enabled ? (
          <Badge tone="green">
            <CheckCircle2 className="size-3.5" aria-hidden /> Enabled
          </Badge>
        ) : (
          <Badge tone="amber">Not enabled</Badge>
        )
      }
    >
      <div className="space-y-4">
        <div className="flex items-start gap-3">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
            {enabled ? <ShieldCheck className="size-5" aria-hidden /> : <KeyRound className="size-5" aria-hidden />}
          </span>
          <div className="text-sm text-ink-muted">
            {enabled ? (
              <p>
                Two-factor authentication is active
                {data?.confirmed_at ? ` since ${formatDateTime(data.confirmed_at)}` : ''}.
                {data?.mandatory ? ' It is required for your account.' : ''}
                {' '}
                You have <span className="font-medium text-ink">{remaining}</span>{' '}
                unused recovery code{remaining === 1 ? '' : 's'}.
              </p>
            ) : (
              <p>
                Use an authenticator app (Google Authenticator, Authy, 1Password) to generate
                one-time codes. {data?.mandatory ? 'Your account requires two-factor authentication.' : ''}
              </p>
            )}
          </div>
        </div>

        {enabled ? (
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => { setErrors({}); setRegenerateOpen(true) }}>
              <RefreshCw className="size-4" aria-hidden /> Regenerate recovery codes
            </Button>
            <Button variant="outline" onClick={() => { setErrors({}); setDisableOpen(true) }}>
              <ShieldOff className="size-4" aria-hidden /> Disable
            </Button>
          </div>
        ) : (
          <Button onClick={() => { resetEnroll(); setEnrollOpen(true) }}>
            <ShieldCheck className="size-4" aria-hidden /> Enable two-factor
          </Button>
        )}
      </div>

      <Modal
        open={enrollOpen}
        onClose={closeEnroll}
        title={enrollStep === 'codes' ? 'Save your recovery codes' : 'Enable two-factor authentication'}
        description={
          enrollStep === 'password'
            ? 'Confirm your password to begin.'
            : enrollStep === 'scan'
              ? 'Scan the QR code, then enter the generated code.'
              : undefined
        }
        footer={
          enrollStep === 'password' ? (
            <>
              <Button variant="outline" onClick={closeEnroll} disabled={busy}>Cancel</Button>
              <Button onClick={handleStartEnroll} loading={busy}>Continue</Button>
            </>
          ) : enrollStep === 'scan' ? (
            <>
              <Button variant="outline" onClick={closeEnroll} disabled={busy}>Cancel</Button>
              <Button onClick={handleConfirmEnroll} loading={busy}>Confirm code</Button>
            </>
          ) : null
        }
      >
        {errors.form ? (
          <div role="alert" className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}

        {enrollStep === 'password' ? (
          <Field label="Account password" htmlFor="2fa-enroll-password" required error={errors.password}>
            <Input
              id="2fa-enroll-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              invalid={Boolean(errors.password)}
            />
          </Field>
        ) : null}

        {enrollStep === 'scan' && enrollment ? (
          <div className="space-y-4">
            <div className="flex justify-center">
              <div className="rounded-2xl bg-white p-3 ring-1 ring-line">
                <QRCodeSVG value={enrollment.otpauth_url} size={176} level="M" />
              </div>
            </div>
            <div className="text-center">
              <p className="text-xs text-ink-muted">Or enter this key manually:</p>
              <p className="mt-1 break-all font-mono text-sm font-medium text-ink">{enrollment.secret}</p>
            </div>
            <Field label="Verification code" htmlFor="2fa-enroll-code" required error={errors.code}>
              <Input
                id="2fa-enroll-code"
                inputMode="numeric"
                autoComplete="one-time-code"
                placeholder="123456"
                maxLength={6}
                value={code}
                onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                invalid={Boolean(errors.code)}
                className="text-center text-lg tracking-[0.5em]"
              />
            </Field>
          </div>
        ) : null}

        {enrollStep === 'codes' ? <CodesList codes={recoveryCodes} onDone={closeEnroll} /> : null}
      </Modal>

      <Modal
        open={disableOpen}
        onClose={() => { setDisableOpen(false); setPassword(''); setErrors({}) }}
        title="Disable two-factor authentication"
        description="This makes your account less secure. Confirm your password to continue."
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => { setDisableOpen(false); setPassword('') }} disabled={busy}>
              Cancel
            </Button>
            <Button variant="danger" onClick={handleDisable} loading={busy}>Disable</Button>
          </>
        }
      >
        <Field label="Account password" htmlFor="2fa-disable-password" required error={errors.password}>
          <Input
            id="2fa-disable-password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            invalid={Boolean(errors.password)}
          />
        </Field>
      </Modal>

      <Modal
        open={regenerateOpen}
        onClose={() => { setRegenerateOpen(false); setPassword(''); setErrors({}) }}
        title="Regenerate recovery codes"
        description="This invalidates your existing recovery codes. Confirm your password to continue."
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => { setRegenerateOpen(false); setPassword('') }} disabled={busy}>
              Cancel
            </Button>
            <Button onClick={handleRegenerate} loading={busy}>Generate new codes</Button>
          </>
        }
      >
        <Field label="Account password" htmlFor="2fa-regen-password" required error={errors.password}>
          <Input
            id="2fa-regen-password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            invalid={Boolean(errors.password)}
          />
        </Field>
      </Modal>
    </Card>
  )
}
