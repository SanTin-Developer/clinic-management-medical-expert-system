import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Toggle from '../ui/Toggle.jsx'
import { expertApi } from '../../api/expert.js'
import { useAll } from '../../hooks/usePaginated.js'
import { MultiSelectOptions } from './MultiSelector.jsx'

export default function SymptomFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const [form, setForm] = useState(
    initial
      ? {
          name: initial.name ?? '',
          name_km: initial.name_km ?? '',
          description: initial.description ?? '',
          disease_ids: (initial.diseases ?? []).map((d) => d.id),
          is_active: Boolean(initial.is_active) || true,
        }
      : { name: '', name_km: '', description: '', disease_ids: [], is_active: true },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const diseases = useAll(['diseases', 'all'], (params) => expertApi.diseases(params))
  const diseaseOptions = useMemo(
    () => (diseases.data ?? []).filter((d) => d.is_active),
    [diseases.data],
  )

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
        description: form.description.trim() || null,
        is_active: form.is_active,
      }
      payload.disease_ids = form.disease_ids
      if (editing) {
        await expertApi.updateSymptom(initial.id, payload)
        toast.success('Symptom updated.')
      } else {
        await expertApi.createSymptom(payload)
        toast.success('Symptom created.')
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
      title={editing ? `Edit symptom: ${initial.code}` : 'New symptom'}
      description="A symptom used by the rule engine and recording workflow."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="symptom-form" loading={saving}>
            {editing ? 'Save changes' : 'Create symptom'}
          </Button>
        </>
      }
    >
      <form id="symptom-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Name" htmlFor="s-name" required error={errors.name}>
            <Input id="s-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
          </Field>
        </div>
        <Field label="Name (Khmer)" htmlFor="s-name-km">
          <Input id="s-name-km" value={form.name_km} onChange={(e) => set('name_km')(e.target.value)} />
        </Field>
        <Field label="Description" htmlFor="s-description">
          <Textarea id="s-description" rows={3} value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>
        <Toggle label="Active in knowledge base" checked={form.is_active} onChange={(v) => set('is_active')(v)} />
        {diseases.isLoading ? (
          <p className="text-sm text-ink-muted">Loading diseases…</p>
        ) : (
          <MultiSelectOptions
            label="Link to diseases"
            hint="Only active diseases can be linked."
            options={diseaseOptions}
            value={form.disease_ids}
            onChange={set('disease_ids')}
          />
        )}
      </form>
    </Modal>
  )
}