import { useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import PasswordChecklist from '../components/ui/PasswordChecklist.jsx'
import { resetPassword } from '../api/auth.js'
import { passwordError } from '../utils/password.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import AuthShell from './auth/AuthShell.jsx'

const EMAIL_RE = /^\S+@\S+\.\S+$/

export default function NewPasswordPage() {
  usePageTitle('Reset Password')
  const navigate = useNavigate()
  const location = useLocation()
  const emailParam = location.state?.email ?? ''
  const resetToken = location.state?.reset_token ?? ''

  const [email, setEmail] = useState(emailParam)
  const [password, setPassword] = useState('')
  const [confirm, setConfirm] = useState('')
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [done, setDone] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!resetToken) next.form = 'This reset session is missing. Verify your code again first.'
    if (!EMAIL_RE.test(email.trim())) next.email = 'Enter a valid email address.'
    const pwdError = passwordError(password)
    if (pwdError) next.password = pwdError
    if (password !== confirm) next.confirm = 'Passwords do not match.'
    setErrors(next)
    if (Object.keys(next).length) return

    setLoading(true)
    try {
      await resetPassword({ email: email.trim(), reset_token: resetToken, password, password_confirmation: confirm })
      setDone(true)
      toast.success('Your password has been reset. Please sign in.')
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setLoading(false)
    }
  }

  if (done) {
    return (
      <AuthShell title="Password updated" description="Your new password is ready to use.">
        <div className="space-y-4">
          <div role="status" className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            Your password was reset successfully. You can now sign in with your new password.
          </div>
          <Button className="w-full" size="lg" onClick={() => navigate('/login', { replace: true })}>
            Back to sign in
          </Button>
        </div>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Choose a new password" description="Set a new password for your account. Keep it private and unique to you.">
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Email" htmlFor="new-email" required error={errors.email}>
          <Input
            id="new-email"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            invalid={Boolean(errors.email)}
          />
        </Field>
        <Field label="New password" htmlFor="new-password" required error={errors.password}>
          <Input id="new-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} invalid={Boolean(errors.password)} aria-describedby="new-password-requirements" />
        </Field>
        <PasswordChecklist id="new-password-requirements" value={password} />
        <Field label="Confirm password" htmlFor="new-confirm" required error={errors.confirm}>
          <Input id="new-confirm" type="password" autoComplete="new-password" value={confirm} onChange={(e) => setConfirm(e.target.value)} invalid={Boolean(errors.confirm)} />
        </Field>
        <Button type="submit" className="w-full" size="lg" loading={loading}>
          Update password
        </Button>
        <p className="text-center text-sm text-ink-muted">
          <Link className="font-medium text-brand hover:text-brand-dark" to="/login">
            Back to sign in
          </Link>
        </p>
      </form>
    </AuthShell>
  )
}