import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { staffInvitationApi } from '../../api/staffInvitations.js'
import { staffApi } from '../../api/staff.js'
import { ROLES } from '../../utils/status.js'

export default function InviteStaffModal({ open, onClose, onSent }) {
  const [form, setForm] = useState({ staff_id: '', name: '', email: '', role: '' })
  const [unlinkedStaff, setUnlinkedStaff] = useState([])
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return

    staffApi.list({ has_account: 'false', per_page: 100 })
      .then((res) => {
        const list = res.data ?? []
        setUnlinkedStaff(
          list.map((s) => ({
            value: String(s.id),
            label: `${s.full_name || `${s.first_name} ${s.last_name}`} (${s.staff_number} - ${s.job_title || s.department || 'Staff'})`,
            raw: s,
          }))
        )
      })
      .catch(() => {})
  }, [open])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleStaffSelect = (staffIdStr) => {
    if (!staffIdStr) {
      setForm((f) => ({ ...f, staff_id: '' }))
      return
    }

    const found = unlinkedStaff.find((s) => s.value === staffIdStr)?.raw
    setForm((f) => ({
      ...f,
      staff_id: staffIdStr,
      name: found ? (found.full_name || `${found.first_name} ${found.last_name}`) : f.name,
      email: found?.email || f.email,
    }))
  }

  const close = () => {
    setForm({ staff_id: '', name: '', email: '', role: '' })
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
        staff_id: form.staff_id ? Number(form.staff_id) : undefined,
        name: form.name.trim(),
        email: form.email.trim().toLowerCase(),
        role: form.role,
      })
      toast.success('Invitation sent.')
      onSent?.()
      close()
    } catch (err) {
      if (err.payload?.errors) {
        const nextErrors = {}
        for (const [key, val] of Object.entries(err.payload.errors)) {
          nextErrors[key] = Array.isArray(val) ? val[0] : String(val)
        }
        setErrors(nextErrors)
      } else {
        setErrors({ form: err.message })
      }
      toast.error(err.message || 'Failed to send invitation.')
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

        {unlinkedStaff.length > 0 ? (
          <Field
            label="Link to existing staff profile (optional)"
            htmlFor="invite-staff-id"
            hint="Selecting an employee links the invited user account directly to their staff profile."
          >
            <Select
              id="invite-staff-id"
              value={form.staff_id}
              onChange={(event) => handleStaffSelect(event.target.value)}
            >
              <option value="">None (Unlinked / General)</option>
              {unlinkedStaff.map((s) => (
                <option key={s.value} value={s.value}>{s.label}</option>
              ))}
            </Select>
          </Field>
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
