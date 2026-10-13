import { useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import { resendOtp } from '../api/auth.js'
import { useAuth } from '../hooks/useAuth.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import AuthShell from './auth/AuthShell.jsx'

export default function OtpVerifyPage() {
  usePageTitle('Two-Factor Verification')
  const { verifyOtp } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const stateToken = location.state?.challengeToken ?? ''
  const stateEmail = location.state?.email ?? ''

  const [challengeToken, setChallengeToken] = useState(stateToken)
  const [code, setCode] = useState('')
  const [rememberDevice, setRememberDevice] = useState(true)
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [resending, setResending] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!challengeToken.trim()) next.form = 'This verification session is missing. Please sign in again.'
    if (!/^\d{6}$/.test(code)) next.code = 'Enter the 6-digit code from your email.'
    setErrors(next)
    if (Object.keys(next).length) return

    setLoading(true)
    try {
      const result = await verifyOtp({
        challenge_token: challengeToken,
        code,
        remember_device: rememberDevice,
      })
      if (result?.two_factor_required) {
        navigate('/login/2fa', {
          replace: true,
          state: { challengeToken: result.challenge_token, from: location.state?.from ?? '/' },
        })
        return
      }
      const from = location.state?.from ?? '/'
      navigate(from, { replace: true })
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setLoading(false)
    }
  }

  const handleResend = async () => {
    if (!challengeToken.trim()) return
    setResending(true)
    try {
      const result = await resendOtp(challengeToken)
      setChallengeToken(result.challenge_token)
      setErrors({})
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setResending(false)
    }
  }

  return (
    <AuthShell title="Verify your email" description="Enter the verification code sent to your email address.">
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        {stateEmail ? (
          <p className="text-sm text-ink-muted">
            A code was sent to <span className="font-medium text-ink">{stateEmail}</span>.
          </p>
        ) : null}
        <Field label="Verification code" htmlFor="otp-code" required error={errors.code} hint="The code is 6 digits and expires after a short time.">
          <Input
            id="otp-code"
            name="code"
            inputMode="numeric"
            autoComplete="one-time-code"
            placeholder="123456"
            maxLength={6}
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
            invalid={Boolean(errors.code)}
          />
        </Field>
        <label className="flex items-center gap-2 text-sm text-ink">
          <input
            type="checkbox"
            checked={rememberDevice}
            onChange={(e) => setRememberDevice(e.target.checked)}
            className="size-4 accent-brand"
          />
          Remember this device for 30 days
        </label>
        <Button type="submit" className="w-full" size="lg" loading={loading}>
          Verify code
        </Button>
        <p className="text-center text-sm text-ink-muted">
          Didn't receive it?{' '}
          <button type="button" className="font-medium text-brand hover:text-brand-dark" onClick={handleResend} disabled={resending}>
            {resending ? 'Resending…' : 'Resend code'}
          </button>
        </p>
      </form>
    </AuthShell>
  )
}