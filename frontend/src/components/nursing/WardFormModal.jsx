import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import Toggle from '../ui/Toggle.jsx'
import { wardApi } from '../../api/nursing.js'
import { WARD_TYPE_OPTIONS } from '../../utils/status.js'

const EMPTY = {
  name: '',
  code: '',
  floor: '',
  ward_type: 'general',
  capacity: '',
  description: '',
  is_active: true,
}

export default function WardFormModal({ open, onClose, onSaved, ward }) {
  const editing = Boolean(ward)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setForm(
      ward
        ? {
            name: ward.name ?? '',
            code: ward.code ?? '',
            floor: ward.floor ?? '',
            ward_type: ward.ward_type ?? 'general',
            capacity: ward.capacity ?? '',
            description: ward.description ?? '',
            is_active: ward.is_active ?? true,
          }
        : EMPTY,
    )
  }, [open, ward])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Name is required.'
    if (!form.code.trim()) next.code = 'Code is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        code: form.code.trim(),
        floor: form.floor || null,
        ward_type: form.ward_type,
        capacity: form.capacity === '' ? null : Number(form.capacity),
        description: form.description || null,
        is_active: form.is_active,
      }
      if (editing) {
        await wardApi.update(ward.id, payload)
        toast.success('Ward updated.')
      } else {
        await wardApi.create(payload)
        toast.success('Ward created.')
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
      title={editing ? 'Edit ward' : 'New ward'}
      description="Wards group rooms and their beds."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="ward-form" loading={saving}>
            {editing ? 'Save changes' : 'Create ward'}
          </Button>
        </>
      }
    >
      <form id="ward-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Name" htmlFor="ward-name" required error={errors.name}>
            <Input id="ward-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
          </Field>
          <Field label="Code" htmlFor="ward-code" required error={errors.code}>
            <Input id="ward-code" value={form.code} onChange={(e) => set('code')(e.target.value)} invalid={Boolean(errors.code)} placeholder="e.g. GW-01" />
          </Field>
          <Field label="Floor" htmlFor="ward-floor">
            <Input id="ward-floor" value={form.floor} onChange={(e) => set('floor')(e.target.value)} />
          </Field>
          <Field label="Type" htmlFor="ward-type">
            <Select id="ward-type" value={form.ward_type} onChange={(e) => set('ward_type')(e.target.value)}>
              {WARD_TYPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Capacity" htmlFor="ward-capacity" hint="Optional planned bed capacity.">
            <Input id="ward-capacity" type="number" min="0" value={form.capacity} onChange={(e) => set('capacity')(e.target.value)} />
          </Field>
          <div className="flex items-end">
            <Toggle label="Active" checked={form.is_active} onChange={(v) => set('is_active')(v)} className="w-full pb-1" />
          </div>
        </div>
        <Field label="Description" htmlFor="ward-description">
          <Textarea id="ward-description" rows={2} value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
