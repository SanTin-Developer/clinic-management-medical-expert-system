import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { bedApi } from '../../api/nursing.js'
import { BED_STATUS_OPTIONS } from '../../utils/status.js'

const EMPTY = { room_id: '', label: '', status: 'available', notes: '' }

export default function BedFormModal({ open, onClose, onSaved, rooms = [], roomId, bed }) {
  const editing = Boolean(bed)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setForm(
      bed
        ? {
            room_id: String(bed.room_id ?? ''),
            label: bed.label ?? '',
            status: bed.status ?? 'available',
            notes: bed.notes ?? '',
          }
        : { ...EMPTY, room_id: roomId ? String(roomId) : '' },
    )
  }, [open, bed, roomId])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.room_id) next.room_id = 'Select a room.'
    if (!form.label.trim()) next.label = 'Label is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      if (editing) {
        const payload = { label: form.label.trim(), notes: form.notes || null }
        // `occupied` is admission-driven and is intentionally not editable.
        if (bed.status !== 'occupied') payload.status = form.status
        await bedApi.update(bed.id, payload)
        toast.success('Bed updated.')
      } else {
        await bedApi.create({
          room_id: Number(form.room_id),
          label: form.label.trim(),
          status: form.status,
          notes: form.notes || null,
        })
        toast.success('Bed created.')
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
      title={editing ? 'Edit bed' : 'New bed'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="bed-form" loading={saving}>
            {editing ? 'Save' : 'Create bed'}
          </Button>
        </>
      }
    >
      <form id="bed-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Room" htmlFor="bed-room" required error={errors.room_id}>
            <Select id="bed-room" value={form.room_id} onChange={(e) => set('room_id')(e.target.value)} invalid={Boolean(errors.room_id)} disabled={editing}>
              <option value="">Select…</option>
              {rooms.map((r) => (
                <option key={r.id} value={r.id}>{r.room_number}</option>
              ))}
            </Select>
          </Field>
          <Field label="Label" htmlFor="bed-label" required error={errors.label}>
            <Input id="bed-label" value={form.label} onChange={(e) => set('label')(e.target.value)} invalid={Boolean(errors.label)} placeholder="e.g. A" />
          </Field>
          {!editing || bed.status !== 'occupied' ? (
            <Field label="Status" htmlFor="bed-status">
              <Select id="bed-status" value={form.status} onChange={(e) => set('status')(e.target.value)}>
                {BED_STATUS_OPTIONS.filter((o) => o.value !== 'occupied').map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </Field>
          ) : null}
        </div>
        <Field label="Notes" htmlFor="bed-notes">
          <Textarea id="bed-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
