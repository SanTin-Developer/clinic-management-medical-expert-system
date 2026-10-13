import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { staffInvitationApi } from '../../api/staffInvitations.js'
import { ROLES } from '../../utils/status.js'

export default function InviteStaffModal({ open, onClose, onSent }) {
  const [form, setForm] = useState({ name: '', email: '', role: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const close = () => {
    setForm({ name: '', email: '', role: '' })
    setErrors({})
    onClose()
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Required.'
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) next.email = 'Enter a valid email.'
    if (!form.role) next.role = 'Select a role.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await staffInvitationApi.create({
        name: form.name.trim(),
        email: form.email.trim().toLowerCase(),
        role: form.role,
      })
      toast.success('Invitation sent.')
      onSent?.()
      close()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={close}
      title="Invite a staff member"
      description="We'll email them a secure link to set a password and activate their account."
      footer={
        <>
          <Button variant="outline" onClick={close} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="invite-form" loading={saving}>
            Send invitation
          </Button>
        </>
      }
    >
      <form id="invite-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Full name" htmlFor="invite-name" required error={errors.name}>
          <Input id="invite-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
        </Field>
        <Field label="Email" htmlFor="invite-email" required error={errors.email}>
          <Input id="invite-email" type="email" autoComplete="off" value={form.email} onChange={(e) => set('email')(e.target.value)} invalid={Boolean(errors.email)} />
        </Field>
        <Field label="Role" htmlFor="invite-role" required error={errors.role}>
          <Select id="invite-role" value={form.role} onChange={(e) => set('role')(e.target.value)} invalid={Boolean(errors.role)}>
            <option value="">Select…</option>
            {ROLES.map((r) => (
              <option key={r.value} value={r.value}>{r.label}</option>
            ))}
          </Select>
        </Field>
      </form>
    </Modal>
  )
}
