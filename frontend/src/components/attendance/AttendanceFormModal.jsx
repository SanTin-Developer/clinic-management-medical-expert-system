import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { userApi } from '../../api/users.js'
import { attendanceApi } from '../../api/attendance.js'
import { useAll } from '../../hooks/usePaginated.js'
import { toIsoDate } from '../../utils/format.js'
import { ATTENDANCE_STATUS_OPTIONS } from '../../utils/status.js'

const today = toIsoDate(new Date())

function toLocalInput(value) {
  if (!value) return ''
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export default function AttendanceFormModal({ open, onClose, record, onSaved }) {
  const editing = Boolean(record?.id)
  const staff = useAll(['users', 'all'], (params) => userApi.list(params))

  const [form, setForm] = useState(
    record
      ? {
          user_id: record.user_id ?? '',
          work_date: toIsoDate(record.work_date),
          clock_in: toLocalInput(record.clock_in),
          clock_out: toLocalInput(record.clock_out),
          status: record.status ?? 'present',
          notes: record.notes ?? '',
        }
      : {
          user_id: '',
          work_date: today,
          clock_in: '',
          clock_out: '',
          status: 'present',
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
    if (!form.work_date) next.work_date = 'Required.'
    if (form.clock_out && !form.clock_in) next.clock_out = 'Set a clock-in time first.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        user_id: form.user_id,
        work_date: form.work_date,
        clock_in: form.clock_in ? form.clock_in.replace('T', ' ') : null,
        clock_out: form.clock_out ? form.clock_out.replace('T', ' ') : null,
        status: form.status,
        notes: form.notes || null,
      }
      if (editing) {
        const { user_id: _userId, work_date: _workDate, ...rest } = payload
        await attendanceApi.update(record.id, rest)
        toast.success('Attendance record updated.')
      } else {
        await attendanceApi.create(payload)
        toast.success('Attendance record created.')
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
      title={editing ? 'Edit attendance record' : 'New attendance record'}
      description="Log a staff member's clock-in and clock-out times."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="attendance-form" loading={saving}>
            {editing ? 'Save changes' : 'Create record'}
          </Button>
        </>
      }
    >
      <form id="attendance-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Staff member" htmlFor="att-staff" required error={errors.user_id}>
            <Select
              id="att-staff"
              value={form.user_id}
              onChange={(e) => set('user_id')(e.target.value)}
              invalid={Boolean(errors.user_id)}
              disabled={editing || staff.isLoading}
            >
              <option value="">{staff.isLoading ? 'Loading staff…' : 'Select…'}</option>
              {(staff.data ?? []).map((u) => (
                <option key={u.id} value={u.id}>{u.name}</option>
              ))}
            </Select>
          </Field>
          <Field label="Work date" htmlFor="att-date" required error={errors.work_date}>
            <Input
              id="att-date"
              type="date"
              value={form.work_date}
              onChange={(e) => set('work_date')(e.target.value)}
              invalid={Boolean(errors.work_date)}
            />
          </Field>
          <Field label="Clock in" htmlFor="att-in">
            <Input
              id="att-in"
              type="datetime-local"
              value={form.clock_in}
              onChange={(e) => set('clock_in')(e.target.value)}
            />
          </Field>
          <Field label="Clock out" htmlFor="att-out" error={errors.clock_out}>
            <Input
              id="att-out"
              type="datetime-local"
              value={form.clock_out}
              onChange={(e) => set('clock_out')(e.target.value)}
              invalid={Boolean(errors.clock_out)}
            />
          </Field>
          <Field label="Status" htmlFor="att-status">
            <Select id="att-status" value={form.status} onChange={(e) => set('status')(e.target.value)}>
              {ATTENDANCE_STATUS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Notes" htmlFor="att-notes" className="sm:col-span-2">
            <Textarea id="att-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
