import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { KeyRound } from 'lucide-react'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import { useAuth } from '../hooks/useAuth.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import AuthShell from './auth/AuthShell.jsx'

export default function TwoFactorVerifyPage() {
  usePageTitle('Two-Factor Authentication')
  const { verifyTwoFactor } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const challengeToken = location.state?.challengeToken ?? ''
  const from = location.state?.from ?? '/'

  const [code, setCode] = useState('')
  const [useRecovery, setUseRecovery] = useState(false)
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!challengeToken) next.form = 'This verification session is missing. Please sign in again.'
    if (useRecovery) {
      if (!code.trim()) next.code = 'Enter one of your recovery codes.'
    } else if (!/^\d{6}$/.test(code)) {
      next.code = 'Enter the 6-digit code from your authenticator app.'
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setLoading(true)
    try {
      await verifyTwoFactor({ challenge_token: challengeToken, code: code.trim() })
      navigate(from, { replace: true })
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthShell
      title="Two-factor verification"
      description="Enter the code from your authenticator app to continue."
    >
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="flex items-center gap-3 rounded-lg bg-brand-soft/60 px-3 py-2 text-sm text-brand-dark">
          <KeyRound className="size-4 shrink-0" aria-hidden />
          <span>
            {useRecovery
              ? 'Enter one of the single-use recovery codes you saved when you enabled two-factor authentication.'
              : 'Open your authenticator app and enter the current 6-digit code.'}
          </span>
        </div>
        <Field
          label={useRecovery ? 'Recovery code' : 'Authenticator code'}
          htmlFor="totp-code"
          required
          error={errors.code}
          hint={useRecovery ? undefined : 'The code rotates every 30 seconds.'}
        >
          <Input
            id="totp-code"
            name="code"
            inputMode={useRecovery ? 'text' : 'numeric'}
            autoComplete="one-time-code"
            autoFocus
            placeholder={useRecovery ? 'xxxx-xxxx' : '123456'}
            maxLength={useRecovery ? 20 : 6}
            value={code}
            onChange={(e) =>
              setCode(useRecovery ? e.target.value : e.target.value.replace(/\D/g, ''))
            }
            invalid={Boolean(errors.code)}
            className={useRecovery ? undefined : 'text-center text-lg tracking-[0.5em]'}
          />
        </Field>
        <Button type="submit" className="w-full" size="lg" loading={loading}>
          Verify and continue
        </Button>
        <div className="flex flex-col items-center gap-1 pt-1 text-center text-sm text-ink-muted">
          <button
            type="button"
            className="font-medium text-brand hover:text-brand-dark"
            onClick={() => {
              setUseRecovery((value) => !value)
              setCode('')
              setErrors({})
            }}
          >
            {useRecovery ? 'Use authenticator code instead' : 'Use a recovery code'}
          </button>
          <Link className="text-ink-muted hover:text-ink" to="/login">
            Back to sign in
          </Link>
        </div>
      </form>
    </AuthShell>
  )
}
