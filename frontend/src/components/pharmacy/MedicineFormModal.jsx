import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import { medicineApi } from '../../api/pharmacy.js'

const FORM_FIELDS = [
  { key: 'name', label: 'Name', required: true, type: 'text' },
  { key: 'generic_name', label: 'Generic name', type: 'text' },
  { key: 'category', label: 'Category', type: 'text' },
  { key: 'form', label: 'Form', type: 'text' },
  { key: 'strength', label: 'Strength', type: 'text' },
  { key: 'unit', label: 'Unit', type: 'text' },
  { key: 'manufacturer', label: 'Manufacturer', type: 'text' },
  { key: 'price', label: 'Price', type: 'number', step: '0.01', min: 0 },
  { key: 'reorder_level', label: 'Reorder level', type: 'number', step: '1', min: 0 },
  { key: 'description', label: 'Description', type: 'textarea' },
]

export default function MedicineFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const [form, setForm] = useState(
    initial
      ? { ...initial, is_active: initial.is_active == null ? true : initial.is_active }
      : {
          name: '',
          generic_name: '',
          category: '',
          form: '',
          strength: '',
          unit: '',
          manufacturer: '',
          description: '',
          price: '',
          reorder_level: '',
          is_active: true,
        },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Required.'
    const price = Number(form.price)
    if (form.price !== '' && (!Number.isFinite(price) || price < 0)) next.price = 'Must be ≥ 0.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = { ...form }
      delete payload.code
      payload.price = payload.price === '' ? null : Number(payload.price)
      payload.reorder_level =
        payload.reorder_level === '' ? null : Number(payload.reorder_level)
      for (const key of ['generic_name', 'category', 'form', 'strength', 'unit', 'manufacturer', 'description']) {
        if (!payload[key]) payload[key] = null
      }
      if (editing) {
        await medicineApi.update(initial.id, payload)
        toast.success('Medicine updated.')
      } else {
        await medicineApi.create(payload)
        toast.success('Medicine created.')
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
      title={editing ? 'Edit medicine' : 'New medicine'}
      description="Add or update a medicine in the formulary."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="medicine-form" loading={saving}>
            {editing ? 'Save changes' : 'Create medicine'}
          </Button>
        </>
      }
    >
      <form id="medicine-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {FORM_FIELDS.map((field) =>
            field.type === 'textarea' ? (
              <Field key={field.key} label={field.label} htmlFor={`med-${field.key}`} className="sm:col-span-2">
                <Textarea rows={2} id={`med-${field.key}`} value={form[field.key]} onChange={(e) => set(field.key)(e.target.value)} />
              </Field>
            ) : (
              <Field
                key={field.key}
                label={field.label}
                htmlFor={`med-${field.key}`}
                required={field.required}
                hint={field.hint}
                error={errors[field.key]}
              >
                <Input
                  id={`med-${field.key}`}
                  type={field.type}
                  step={field.step}
                  min={field.min}
                  value={form[field.key]}
                  onChange={(e) => set(field.key)(e.target.value)}
                  invalid={Boolean(errors[field.key])}
                />
              </Field>
            ),
          )}
          <label className="flex items-end gap-2 pb-2.5 text-sm text-ink">
            <input
              type="checkbox"
              checked={form.is_active}
              onChange={(e) => set('is_active')(e.target.checked)}
              className="size-4 accent-brand"
            />
            Active (available for prescriptions)
          </label>
        </div>
      </form>
    </Modal>
  )
}