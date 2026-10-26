import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import { useAuth } from '../hooks/useAuth.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import { useLanguage } from '../hooks/useLanguage.js'
import { APP_NAME } from '../constants/index.js'
import AuthShell from './auth/AuthShell.jsx'

export default function LoginPage() {
  const { t } = useLanguage()
  usePageTitle(t('Sign In'))
  const { login } = useAuth()
  const navigate = useNavigate()

  const [identifier, setIdentifier] = useState('')
  const [password, setPassword] = useState('')
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!identifier.trim()) next.identifier = t('Email or username is required.')
    if (!password) next.password = t('Password is required.')
    setErrors(next)
    if (Object.keys(next).length) return

    setLoading(true)
    try {
      const result = await login({ email: identifier.trim(), password })
      if (result?.otp_required) {
        navigate('/login/verify', {
          replace: true,
          state: { challengeToken: result.challenge_token, email: identifier.trim() },
        })
        return
      }
      if (result?.two_factor_required) {
        navigate('/login/2fa', {
          replace: true,
          state: { challengeToken: result.challenge_token },
        })
        return
      }
      const userName = result?.user?.name || identifier.trim()
      toast.success(t('Welcome back, {name}!', { name: userName }))
      navigate('/', { replace: true })
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setLoading(false)
    }
  }

  return (
    <AuthShell title={APP_NAME} description={t('Sign in to your account to continue.')}>
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div
            role="alert"
            className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700"
          >
            {errors.form}
          </div>
        ) : null}
        <Field label={t('Email or username')} htmlFor="login-identifier" required error={errors.identifier}>
          <Input
            id="login-identifier"
            name="email"
            type="text"
            autoComplete="username"
            placeholder={t('you@clinic.local or username')}
            value={identifier}
            onChange={(e) => setIdentifier(e.target.value)}
            invalid={Boolean(errors.identifier)}
          />
        </Field>
        <Field label={t('Password')} htmlFor="login-password" required error={errors.password}>
          <Input
            id="login-password"
            name="password"
            type="password"
            autoComplete="current-password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            invalid={Boolean(errors.password)}
          />
        </Field>
        <Button
          type="submit"
          className="w-full bg-gradient-to-r from-brand to-brand-dark shadow-[0_10px_24px_-12px_rgb(37_99_235_/_0.8)] hover:from-brand-dark hover:to-brand-darker dark:from-brand dark:to-brand dark:hover:brightness-110"
          size="lg"
          loading={loading}
        >
          {t('Sign in')}
        </Button>
        <p className="pt-1 text-center text-sm text-ink-muted">
          {t('Forgotten your password?')}{' '}
          <Link className="font-medium text-brand hover:text-brand-dark" to="/forgot-password">
            {t('Reset it')}
          </Link>
        </p>
      </form>
    </AuthShell>
  )
}