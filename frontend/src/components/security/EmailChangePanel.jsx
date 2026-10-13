import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Mail } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import { requestEmailChange, confirmEmailChange } from '../../api/auth.js'
import { useAuth } from '../../hooks/useAuth.js'

const EMAIL_RE = /^\S+@\S+\.\S+$/

export default function EmailChangePanel() {
  const navigate = useNavigate()
  const { user, endSession } = useAuth()

  const [step, setStep] = useState('request')
  const [newEmail, setNewEmail] = useState('')
  const [password, setPassword] = useState('')
  const [pendingEmail, setPendingEmail] = useState('')
  const [code, setCode] = useState('')
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const reset = () => {
    setStep('request')
    setNewEmail('')
    setPassword('')
    setPendingEmail('')
    setCode('')
    setErrors({})
  }

  const handleRequest = async (event) => {
    event.preventDefault()
    const next = {}
    if (!EMAIL_RE.test(newEmail.trim())) next.newEmail = 'Enter a valid email address.'
    else if (newEmail.trim().toLowerCase() === (user?.email ?? '').toLowerCase()) {
      next.newEmail = 'That is already your current email address.'
    }
    if (!password) next.password = 'Enter your account password.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const result = await requestEmailChange({ new_email: newEmail.trim(), password })
      setPendingEmail(result.new_email ?? newEmail.trim())
      setPassword('')
      setStep('confirm')
      toast.success(result?.message ?? 'Confirmation code sent.')
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  const handleConfirm = async (event) => {
    event.preventDefault()
    const next = {}
    if (!/^\d{6}$/.test(code)) next.code = 'Enter the 6-digit code sent to the new address.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const result = await confirmEmailChange(code)
      toast.success(result?.message ?? 'Your email address has been updated.')
      endSession()
      navigate('/login', { replace: true })
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Card
      title="Email address"
      subtitle="Changing your email requires confirming a code sent to the new address."
    >
      <div className="mb-4 flex items-center gap-3 rounded-lg bg-canvas px-3 py-2 text-sm">
        <Mail className="size-4 shrink-0 text-ink-muted" aria-hidden />
        <span className="text-ink-muted">
          Current: <span className="font-medium text-ink">{user?.email}</span>
        </span>
      </div>

      {errors.form ? (
        <div role="alert" className="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
          {errors.form}
        </div>
      ) : null}

      {step === 'request' ? (
        <form onSubmit={handleRequest} className="space-y-4" noValidate>
          <Field label="New email address" htmlFor="ec-email" required error={errors.newEmail}>
            <Input
              id="ec-email"
              type="email"
              autoComplete="email"
              value={newEmail}
              onChange={(e) => setNewEmail(e.target.value)}
              invalid={Boolean(errors.newEmail)}
            />
          </Field>
          <Field label="Account password" htmlFor="ec-password" required error={errors.password} hint="We verify your password before sending a code to the new address.">
            <Input
              id="ec-password"
              type="password"
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              invalid={Boolean(errors.password)}
            />
          </Field>
          <Button type="submit" loading={saving}>
            <Mail className="size-4" aria-hidden /> Send confirmation code
          </Button>
        </form>
      ) : (
        <form onSubmit={handleConfirm} className="space-y-4" noValidate>
          <p className="text-sm text-ink-muted">
            We sent a 6-digit code to <span className="font-medium text-ink">{pendingEmail}</span>.
          </p>
          <Field label="Confirmation code" htmlFor="ec-code" required error={errors.code}>
            <Input
              id="ec-code"
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
          <div className="flex flex-wrap gap-2">
            <Button type="submit" loading={saving}>
              Confirm email change
            </Button>
            <Button type="button" variant="outline" onClick={reset} disabled={saving}>
              Start over
            </Button>
          </div>
        </form>
      )}
    </Card>
  )
}
