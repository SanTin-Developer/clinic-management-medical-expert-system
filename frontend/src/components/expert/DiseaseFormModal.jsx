import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import Toggle from '../ui/Toggle.jsx'
import { expertApi } from '../../api/expert.js'
import { DISEASE_SEVERITY } from '../../utils/status.js'
import SymptomMultiSelect from './MultiSelector.jsx'

const empties = { name: '', name_km: '', category: '', description: '', severity_level: '', symptom_ids: [] }

export default function DiseaseFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const [form, setForm] = useState(
    initial
      ? {
          name: initial.name ?? '',
          name_km: initial.name_km ?? '',
          category: initial.category ?? '',
          description: initial.description ?? '',
          severity_level: initial.severity_level ?? '',
          symptom_ids: (initial.symptoms ?? []).map((s) => s.id),
          is_active: Boolean(initial.is_active) || true,
        }
      : { ...empties, severity_level: '', is_active: true },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        name_km: form.name_km.trim() || null,
        category: form.category.trim() || null,
        description: form.description.trim() || null,
        severity_level: form.severity_level || null,
        is_active: form.is_active,
      }
      payload.symptom_ids = form.symptom_ids
      if (editing) {
        await expertApi.updateDisease(initial.id, payload)
        toast.success('Disease updated.')
      } else {
        await expertApi.createDisease(payload)
        toast.success('Disease created.')
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
      title={editing ? `Edit disease: ${initial.code}` : 'New disease'}
      description="A disease registered in the clinical knowledge base."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="disease-form" loading={saving}>
            {editing ? 'Save changes' : 'Create disease'}
          </Button>
        </>
      }
    >
      <form id="disease-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Name" htmlFor="d-name" required error={errors.name}>
            <Input id="d-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
          </Field>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Name (Khmer)" htmlFor="d-name-km">
            <Input id="d-name-km" value={form.name_km} onChange={(e) => set('name_km')(e.target.value)} />
          </Field>
          <Field label="Category" htmlFor="d-category">
            <Input id="d-category" value={form.category} onChange={(e) => set('category')(e.target.value)} placeholder="e.g. Respiratory" />
          </Field>
        </div>
        <Field label="Severity level" htmlFor="d-severity">
          <Select id="d-severity" value={form.severity_level} onChange={(e) => set('severity_level')(e.target.value)}>
            <option value="">Not set</option>
            {Object.entries(DISEASE_SEVERITY).map(([key, meta]) => (
              <option key={key} value={key}>
                {meta.label}
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Description" htmlFor="d-description">
          <Textarea id="d-description" rows={3} value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>
        <Toggle label="Active in knowledge base" checked={form.is_active} onChange={(v) => set('is_active')(v)} />
        <SymptomMultiSelect value={form.symptom_ids} onChange={set('symptom_ids')} />
      </form>
    </Modal>
  )
}