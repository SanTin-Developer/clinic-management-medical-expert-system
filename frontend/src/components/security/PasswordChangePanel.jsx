import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { KeyRound } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import PasswordChecklist from '../ui/PasswordChecklist.jsx'
import { changePassword } from '../../api/auth.js'
import { passwordError } from '../../utils/password.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function PasswordChangePanel() {
  const navigate = useNavigate()
  const { user, endSession } = useAuth()
  const { t } = useLanguage()
  const mustChange = Boolean(user?.must_change_password)

  const [form, setForm] = useState({ current: '', password: '', confirm: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.current) next.current = t('Enter your current password.')
    const pwdError = passwordError(form.password)
    if (pwdError) next.password = t(pwdError)
    if (form.password && form.password === form.current) {
      next.password = t('Choose a password different from your current one.')
    }
    if (form.password !== form.confirm) next.confirm = t('Passwords do not match.')
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await changePassword({
        current_password: form.current,
        password: form.password,
        password_confirmation: form.confirm,
      })
      toast.success(t('Your password has been changed.'))
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
      title={t('Password')}
      subtitle={t('Changing your password signs you out of every device, including this one.')}
    >
      <form onSubmit={handleSubmit} className="space-y-4" noValidate>
        {mustChange ? (
          <div role="alert" className="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
            {t('You must set a new password before you can continue using the application.')}
          </div>
        ) : null}
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label={t('Current password')} htmlFor="pw-current" required error={errors.current}>
          <Input
            id="pw-current"
            type="password"
            autoComplete="current-password"
            value={form.current}
            onChange={(e) => set('current')(e.target.value)}
            invalid={Boolean(errors.current)}
          />
        </Field>
        <Field label={t('New password')} htmlFor="pw-new" required error={errors.password}>
          <Input
            id="pw-new"
            type="password"
            autoComplete="new-password"
            value={form.password}
            onChange={(e) => set('password')(e.target.value)}
            invalid={Boolean(errors.password)}
            aria-describedby="pw-new-requirements"
          />
        </Field>
        <PasswordChecklist id="pw-new-requirements" value={form.password} />
        <Field label={t('Confirm new password')} htmlFor="pw-confirm" required error={errors.confirm}>
          <Input
            id="pw-confirm"
            type="password"
            autoComplete="new-password"
            value={form.confirm}
            onChange={(e) => set('confirm')(e.target.value)}
            invalid={Boolean(errors.confirm)}
          />
        </Field>
        <Button type="submit" loading={saving}>
          <KeyRound className="size-4" aria-hidden /> {t('Update password')}
        </Button>
      </form>
    </Card>
  )
}
