import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { toast } from 'sonner'
import Button from '../components/ui/Button.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import PasswordChecklist from '../components/ui/PasswordChecklist.jsx'
import { Spinner } from '../components/ui/Skeleton.jsx'
import { ErrorState } from '../components/ui/State.jsx'
import { lookupInvitation, acceptInvitation } from '../api/auth.js'
import { passwordError } from '../utils/password.js'
import { usePageTitle } from '../hooks/usePageTitle.js'
import AuthShell from './auth/AuthShell.jsx'

export default function AcceptInvitationPage() {
  usePageTitle('Accept Invitation')
  const navigate = useNavigate()
  const [searchParams] = useSearchParams()
  const token = searchParams.get('token') ?? ''

  const [invite, setInvite] = useState(null)
  const [loadingInvite, setLoadingInvite] = useState(true)
  const [loadError, setLoadError] = useState(null)
  const [done, setDone] = useState(null)

  const [form, setForm] = useState({ name: '', username: '', password: '', confirm: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!token) {
      setLoadingInvite(false)
      setLoadError('This invitation link is missing its token.')
      return
    }
    let cancelled = false
    lookupInvitation(token)
      .then((result) => {
        if (cancelled) return
        setInvite(result)
        setForm((f) => ({ ...f, name: result.name ?? '' }))
      })
      .catch((err) => {
        if (!cancelled) setLoadError(err.message)
      })
      .finally(() => {
        if (!cancelled) setLoadingInvite(false)
      })
    return () => {
      cancelled = true
    }
  }, [token])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Required.'
    const username = form.username.trim()
    if (username && !/^[A-Za-z0-9._-]+$/.test(username)) {
      next.username = 'Use letters, numbers, dots, dashes or underscores.'
    }
    const pwdError = passwordError(form.password)
    if (pwdError) next.password = pwdError
    if (form.password !== form.confirm) next.confirm = 'Passwords do not match.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const result = await acceptInvitation({
        token,
        name: form.name.trim(),
        ...(username ? { username: username.toLowerCase() } : {}),
        password: form.password,
        password_confirmation: form.confirm,
      })
      setDone(result)
      toast.success('Your account has been activated.')
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  if (loadingInvite) {
    return (
      <AuthShell title="Your invitation" description="Checking your invitation…">
        <div className="grid place-items-center py-8">
          <Spinner label="Validating invitation…" />
        </div>
      </AuthShell>
    )
  }

  if (loadError) {
    return (
      <AuthShell title="Invitation unavailable" description="We could not use this invitation.">
        <div className="space-y-4">
          <ErrorState compact message={loadError} />
          <Button className="w-full" size="lg" onClick={() => navigate('/login', { replace: true })}>
            Back to sign in
          </Button>
        </div>
      </AuthShell>
    )
  }

  if (done) {
    return (
      <AuthShell title="Account activated" description="You can now sign in.">
        <div className="space-y-4">
          <div role="status" className="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
            {done.message}
          </div>
          <dl className="space-y-1 text-sm">
            <div className="flex justify-between">
              <dt className="text-ink-muted">Email</dt>
              <dd className="font-medium text-ink">{done.email}</dd>
            </div>
            {done.username ? (
              <div className="flex justify-between">
                <dt className="text-ink-muted">Username</dt>
                <dd className="font-medium text-ink">{done.username}</dd>
              </div>
            ) : null}
          </dl>
          <Button className="w-full" size="lg" onClick={() => navigate('/login', { replace: true })}>
            Go to sign in
          </Button>
        </div>
      </AuthShell>
    )
  }

  return (
    <AuthShell
      title="Accept your invitation"
      description={`Set up your account${invite?.role ? ` as ${invite.role}` : ''}.`}
    >
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="rounded-lg bg-canvas px-3 py-2 text-sm text-ink-muted">
          Invitation for <span className="font-medium text-ink">{invite?.email}</span>
          {invite?.role ? <> · role: <span className="font-medium text-ink">{invite.role}</span></> : null}
        </div>
        <Field label="Full name" htmlFor="inv-name" required error={errors.name}>
          <Input id="inv-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
        </Field>
        <Field label="Username" htmlFor="inv-username" error={errors.username} hint="Optional — we'll generate one from your email if left blank.">
          <Input id="inv-username" value={form.username} onChange={(e) => set('username')(e.target.value)} invalid={Boolean(errors.username)} placeholder="jdoe" />
        </Field>
        <Field label="Password" htmlFor="inv-password" required error={errors.password}>
          <Input id="inv-password" type="password" autoComplete="new-password" value={form.password} onChange={(e) => set('password')(e.target.value)} invalid={Boolean(errors.password)} aria-describedby="inv-password-requirements" />
        </Field>
        <PasswordChecklist id="inv-password-requirements" value={form.password} />
        <Field label="Confirm password" htmlFor="inv-confirm" required error={errors.confirm}>
          <Input id="inv-confirm" type="password" autoComplete="new-password" value={form.confirm} onChange={(e) => set('confirm')(e.target.value)} invalid={Boolean(errors.confirm)} />
        </Field>
        <Button type="submit" className="w-full" size="lg" loading={saving}>
          Activate my account
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
