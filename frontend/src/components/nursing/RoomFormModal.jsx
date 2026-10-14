import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import Toggle from '../ui/Toggle.jsx'
import { roomApi } from '../../api/nursing.js'
import { ROOM_TYPE_OPTIONS, ROOM_TYPE_PRICES } from '../../utils/status.js'

const EMPTY = {
  room_number: '',
  room_type: 'general',
  capacity: '',
  price_per_day: '',
  notes: '',
  is_active: true,
}

export default function RoomFormModal({ open, onClose, onSaved, wardId, room }) {
  const editing = Boolean(room)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setForm(
      room
        ? {
            room_number: room.room_number ?? '',
            room_type: room.room_type ?? 'general',
            capacity: room.capacity ?? '',
            price_per_day: room.price_per_day ?? '',
            notes: room.notes ?? '',
            is_active: room.is_active ?? true,
          }
        : EMPTY,
    )
  }, [open, room])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.room_number.trim()) next.room_number = 'Room number is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        ward_id: wardId ?? room?.ward_id,
        room_number: form.room_number.trim(),
        room_type: form.room_type,
        capacity: form.capacity === '' ? null : Number(form.capacity),
        price_per_day: form.price_per_day === '' ? null : Number(form.price_per_day),
        notes: form.notes || null,
        is_active: form.is_active,
      }
      if (editing) {
        await roomApi.update(room.id, payload)
        toast.success('Room updated.')
      } else {
        await roomApi.create(payload)
        toast.success('Room created.')
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
      title={editing ? 'Edit room' : 'New room'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="room-form" loading={saving}>
            {editing ? 'Save' : 'Create room'}
          </Button>
        </>
      }
    >
      <form id="room-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Room number" htmlFor="room-number" required error={errors.room_number}>
            <Input id="room-number" value={form.room_number} onChange={(e) => set('room_number')(e.target.value)} invalid={Boolean(errors.room_number)} />
          </Field>
          <Field label="Type" htmlFor="room-type">
            <Select
              id="room-type"
              value={form.room_type}
              onChange={(e) => {
                const nextType = e.target.value
                setForm((f) => ({
                  ...f,
                  room_type: nextType,
                  price_per_day: f.price_per_day === '' ? (ROOM_TYPE_PRICES[nextType] ?? '') : f.price_per_day,
                }))
              }}
            >
              {ROOM_TYPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Capacity" htmlFor="room-capacity" hint="Optional.">
            <Input id="room-capacity" type="number" min="0" value={form.capacity} onChange={(e) => set('capacity')(e.target.value)} />
          </Field>
          <Field label="Price / day" htmlFor="room-price" hint="Optional. Defaults by type.">
            <Input id="room-price" type="number" min="0" step="0.01" value={form.price_per_day} onChange={(e) => set('price_per_day')(e.target.value)} />
          </Field>
          <div className="flex items-end">
            <Toggle label="Active" checked={form.is_active} onChange={(v) => set('is_active')(v)} className="w-full pb-1" />
          </div>
        </div>
        <Field label="Notes" htmlFor="room-notes">
          <Textarea id="room-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
