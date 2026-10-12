import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import { forgotPassword } from '../api/auth.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import AuthShell from './auth/AuthShell.jsx'

const EMAIL_RE = /^\S+@\S+\.\S+$/

export default function ForgotPasswordPage() {
  usePageTitle('Forgot Password')
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [sentEmail, setSentEmail] = useState(null)
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!email.trim()) next.email = 'Email is required.'
    else if (!EMAIL_RE.test(email.trim())) next.email = 'Enter a valid email address.'
    setErrors(next)
    if (Object.keys(next).length) return

    setLoading(true)
    try {
      await forgotPassword(email.trim())
      toast.success('Reset code sent.')
      setSentEmail(email.trim())
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setLoading(false)
    }
  }

  if (sentEmail) {
    return (
      <AuthShell
        title="Check your email"
        description={`We sent a 6-digit code to ${sentEmail}. Open your inbox (and the spam folder) to find it — it expires in 15 minutes.`}
      >
        <div className="space-y-4">
          <div role="status" className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            If that email address is registered, a reset code is on its way. You can close this step while you grab the code.
          </div>
          <Button
            className="w-full"
            size="lg"
            onClick={() => navigate(`/reset-password?email=${encodeURIComponent(sentEmail)}`, { replace: true })}
          >
            I have the code — continue
          </Button>
          <p className="text-center text-sm text-ink-muted">
            <Link className="font-medium text-brand hover:text-brand-dark" to="/login">
              Back to sign in
            </Link>
          </p>
        </div>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Reset your password" description="Enter the email for your account and we'll send a one-time code.">
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Email" htmlFor="forgot-email" required error={errors.email}>
          <Input
            id="forgot-email"
            type="email"
            autoComplete="username"
            placeholder="you@clinic.local"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            invalid={Boolean(errors.email)}
          />
        </Field>
        <Button type="submit" className="w-full" size="lg" loading={loading}>
          Send reset code
        </Button>
        <p className="text-center text-sm text-ink-muted">
          Remembered it?{' '}
          <Link className="font-medium text-brand hover:text-brand-dark" to="/login">
            Back to sign in
          </Link>
        </p>
      </form>
    </AuthShell>
  )
}