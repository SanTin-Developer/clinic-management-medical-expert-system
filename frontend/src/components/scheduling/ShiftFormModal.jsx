import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { userApi } from '../../api/users.js'
import { shiftApi } from '../../api/shifts.js'
import { useAll } from '../../hooks/usePaginated.js'
import { toIsoDate } from '../../utils/format.js'
import { SHIFT_STATUS_OPTIONS, SHIFT_TYPE_OPTIONS } from '../../utils/status.js'

const today = toIsoDate(new Date())

export default function ShiftFormModal({ open, onClose, shift, onSaved }) {
  const editing = Boolean(shift?.id)
  const staff = useAll(['users', 'all'], (params) => userApi.list(params))

  const [form, setForm] = useState(
    shift
      ? {
          user_id: shift.user_id ?? '',
          shift_date: toIsoDate(shift.shift_date),
          start_time: (shift.start_time ?? '').slice(0, 5),
          end_time: (shift.end_time ?? '').slice(0, 5),
          type: shift.type ?? 'day',
          status: shift.status ?? 'scheduled',
          notes: shift.notes ?? '',
        }
      : {
          user_id: '',
          shift_date: today,
          start_time: '08:00',
          end_time: '16:00',
          type: 'day',
          status: 'scheduled',
          notes: '',
        },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.user_id) next.user_id = 'Select a staff member.'
    if (!form.shift_date) next.shift_date = 'Required.'
    if (!/^\d{2}:\d{2}$/.test(form.start_time)) next.start_time = 'Use HH:MM.'
    if (!/^\d{2}:\d{2}$/.test(form.end_time)) next.end_time = 'Use HH:MM.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        user_id: form.user_id,
        shift_date: form.shift_date,
        start_time: form.start_time,
        end_time: form.end_time,
        type: form.type,
        status: form.status,
        notes: form.notes || null,
      }
      if (editing) {
        await shiftApi.update(shift.id, payload)
        toast.success('Shift updated.')
      } else {
        await shiftApi.create(payload)
        toast.success('Shift scheduled.')
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
      title={editing ? 'Edit shift' : 'Schedule a shift'}
      description="Assign a staff member to a shift on the roster."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="shift-form" loading={saving}>
            {editing ? 'Save changes' : 'Schedule shift'}
          </Button>
        </>
      }
    >
      <form id="shift-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Staff member" htmlFor="shift-staff" required error={errors.user_id}>
            <Select
              id="shift-staff"
              value={form.user_id}
              onChange={(e) => set('user_id')(e.target.value)}
              invalid={Boolean(errors.user_id)}
              disabled={staff.isLoading}
            >
              <option value="">{staff.isLoading ? 'Loading staff…' : 'Select…'}</option>
              {(staff.data ?? []).map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </Select>
          </Field>
          <Field label="Date" htmlFor="shift-date" required error={errors.shift_date}>
            <Input
              id="shift-date"
              type="date"
              value={form.shift_date}
              onChange={(e) => set('shift_date')(e.target.value)}
              invalid={Boolean(errors.shift_date)}
            />
          </Field>
          <Field label="Start time" htmlFor="shift-start" required error={errors.start_time} hint="24-hour format.">
            <Input
              id="shift-start"
              type="time"
              value={form.start_time}
              onChange={(e) => set('start_time')(e.target.value)}
              invalid={Boolean(errors.start_time)}
            />
          </Field>
          <Field label="End time" htmlFor="shift-end" required error={errors.end_time} hint="24-hour format.">
            <Input
              id="shift-end"
              type="time"
              value={form.end_time}
              onChange={(e) => set('end_time')(e.target.value)}
              invalid={Boolean(errors.end_time)}
            />
          </Field>
          <Field label="Shift type" htmlFor="shift-type">
            <Select id="shift-type" value={form.type} onChange={(e) => set('type')(e.target.value)}>
              {SHIFT_TYPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Status" htmlFor="shift-status">
            <Select id="shift-status" value={form.status} onChange={(e) => set('status')(e.target.value)}>
              {SHIFT_STATUS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Notes" htmlFor="shift-notes" className="sm:col-span-2">
            <Textarea id="shift-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
