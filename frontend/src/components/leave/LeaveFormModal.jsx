import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { leaveApi } from '../../api/leave.js'
import { LEAVE_TYPE_OPTIONS } from '../../utils/status.js'
import { toIsoDate } from '../../utils/format.js'

const today = toIsoDate(new Date())

const EMPTY = {
  type: 'annual',
  start_date: today,
  end_date: today,
  reason: '',
}

export default function LeaveFormModal({ open, onClose, onSaved, request }) {
  const editing = Boolean(request)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setForm(
      request
        ? {
            type: request.type ?? 'annual',
            start_date: request.start_date ? request.start_date.slice(0, 10) : today,
            end_date: request.end_date ? request.end_date.slice(0, 10) : today,
            reason: request.reason ?? '',
          }
        : EMPTY,
    )
  }, [open, request])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.start_date) next.start_date = 'Start date is required.'
    if (!form.end_date) next.end_date = 'End date is required.'
    if (form.start_date && form.end_date && form.end_date < form.start_date) {
      next.end_date = 'End date cannot be before the start date.'
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        type: form.type,
        start_date: form.start_date,
        end_date: form.end_date,
        reason: form.reason || null,
      }
      if (editing) {
        await leaveApi.update(request.id, payload)
        toast.success('Leave request updated.')
      } else {
        await leaveApi.create(payload)
        toast.success('Leave request submitted.')
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
      title={editing ? 'Edit leave request' : 'New leave request'}
      description="Submit a leave request for manager approval."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="leave-form" loading={saving}>
            {editing ? 'Save changes' : 'Submit request'}
          </Button>
        </>
      }
    >
      <form id="leave-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}

        <Field label="Leave type" htmlFor="leave-type">
          <Select id="leave-type" value={form.type} onChange={(e) => set('type')(e.target.value)}>
            {LEAVE_TYPE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
        </Field>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Start date" htmlFor="leave-start" required error={errors.start_date}>
            <Input
              id="leave-start"
              type="date"
              value={form.start_date}
              onChange={(e) => set('start_date')(e.target.value)}
              invalid={Boolean(errors.start_date)}
            />
          </Field>
          <Field label="End date" htmlFor="leave-end" required error={errors.end_date}>
            <Input
              id="leave-end"
              type="date"
              value={form.end_date}
              onChange={(e) => set('end_date')(e.target.value)}
              invalid={Boolean(errors.end_date)}
            />
          </Field>
        </div>

        <Field label="Reason" htmlFor="leave-reason" hint="Optional context for the reviewer.">
          <Textarea
            id="leave-reason"
            rows={3}
            value={form.reason}
            onChange={(e) => set('reason')(e.target.value)}
          />
        </Field>
      </form>
    </Modal>
  )
}
