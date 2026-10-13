import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import PasswordChecklist from '../ui/PasswordChecklist.jsx'
import { userApi } from '../../api/users.js'
import { passwordError } from '../../utils/password.js'
import { ROLES } from '../../utils/status.js'

export default function UserFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const [form, setForm] = useState(
    initial
      ? {
          name: initial.name ?? '',
          username: initial.username ?? '',
          email: initial.email ?? '',
          role: (initial.roles?.[0]?.name ?? initial.roles?.[0] ?? ''),
          password: '',
        }
      : { name: '', username: '', email: '', role: '', password: '' },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    const username = form.username.trim()
    if (!form.name.trim()) next.name = 'Required.'
    if (username && !/^[A-Za-z0-9._-]+$/.test(username)) next.username = 'Use letters, numbers, dots, dashes or underscores.'
    else if (username && username.length < 3) next.username = 'At least 3 characters.'
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) next.email = 'Enter a valid email.'
    if (!form.role) next.role = 'Select a role.'
    if (!editing) {
      if (!form.password) next.password = 'Required.'
      else {
        const pwdError = passwordError(form.password)
        if (pwdError) next.password = pwdError
      }
    } else if (form.password) {
      const pwdError = passwordError(form.password)
      if (pwdError) next.password = pwdError
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        email: form.email.trim(),
        role: form.role,
      }
      if (username) payload.username = username.toLowerCase()
      if (form.password) payload.password = form.password
      if (editing) {
        await userApi.update(initial.id, payload)
        toast.success('User updated.')
      } else {
        await userApi.create(payload)
        toast.success('User created.')
      }
      onSaved?.()
      onClose()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? 'Edit user' : 'New user'}
      description="Create or update a staff account and assign their role."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="user-form" loading={saving}>
            {editing ? 'Save changes' : 'Create user'}
          </Button>
        </>
      }
    >
      <form id="user-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Full name" htmlFor="u-name" required error={errors.name}>
          <Input id="u-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
        </Field>
        <Field label="Username" htmlFor="u-username" error={errors.username} hint="Optional for new users — generated from the email if left blank.">
          <Input id="u-username" value={form.username} onChange={(e) => set('username')(e.target.value)} invalid={Boolean(errors.username)} placeholder="jdoe" />
        </Field>
        <Field label="Email" htmlFor="u-email" required error={errors.email}>
          <Input id="u-email" type="email" autoComplete="username" value={form.email} onChange={(e) => set('email')(e.target.value)} invalid={Boolean(errors.email)} />
        </Field>
        <Field label="Role" htmlFor="u-role" required error={errors.role}>
          <Select id="u-role" value={form.role} onChange={(e) => set('role')(e.target.value)} invalid={Boolean(errors.role)}>
            <option value="">Select…</option>
            {ROLES.map((r) => (
              <option key={r.value} value={r.value}>{r.label}</option>
            ))}
          </Select>
        </Field>
        <Field
          label={editing ? 'New password' : 'Password'}
          htmlFor="u-password"
          required={!editing}
          error={errors.password}
          hint={editing ? 'Leave blank to keep the current password. Otherwise it must meet all requirements below.' : 'Must meet all requirements below.'}
        >
          <Input id="u-password" type="password" autoComplete="new-password" value={form.password} onChange={(e) => set('password')(e.target.value)} invalid={Boolean(errors.password)} aria-describedby="u-password-requirements" />
        </Field>
        <PasswordChecklist id="u-password-requirements" value={form.password} />
      </form>
    </Modal>
  )
}