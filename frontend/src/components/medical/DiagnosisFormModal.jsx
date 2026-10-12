import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { consultationApi, diagnosisApi } from '../../api/clinical.js'
import { usePaginated } from '../../hooks/usePaginated.js'
import { patientFullName } from '../../utils/format.js'
import { DISEASE_SEVERITY } from '../../utils/status.js'

const SEVERITY_OPTIONS = Object.entries(DISEASE_SEVERITY).map(([value, m]) => ({
  value,
  label: m.label,
}))

export default function DiagnosisFormModal({ open, onClose, onSaved }) {
  const consultations = usePaginated(
    ['consultations', 'page1'],
    (params) => consultationApi.list(params),
    { page: 1 },
  )

  const [form, setForm] = useState({
    consultation_id: '',
    diagnosis_name: '',
    description: '',
    severity: '',
    is_confirmed: false,
    notes: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const list = consultations.data?.data ?? []
  const selected = list.find((c) => String(c.id) === String(form.consultation_id))

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.consultation_id) next.consultation_id = 'Select the consultation.'
    if (!form.diagnosis_name.trim()) next.diagnosis_name = 'Required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        consultation_id: form.consultation_id,
        diagnosis_name: form.diagnosis_name.trim(),
        description: form.description || null,
        severity: form.severity || null,
        notes: form.notes || null,
        is_confirmed: Boolean(form.is_confirmed),
      }
      await diagnosisApi.create(payload)
      toast.success('Diagnosis recorded.')
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
      title="Record diagnosis"
      description="Only the doctor assigned to the consultation can record a diagnosis."
      size="md"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="diagnosis-form" loading={saving}>
            Save diagnosis
          </Button>
        </>
      }
    >
      <form id="diagnosis-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Consultation" htmlFor="dx-cs" required error={errors.consultation_id}>
          <Select id="dx-cs" value={form.consultation_id} onChange={(e) => set('consultation_id')(e.target.value)} disabled={consultations.isLoading} invalid={Boolean(errors.consultation_id)}>
            <option value="">{consultations.isLoading ? 'Loading…' : 'Select…'}</option>
            {list.map((c) => (
              <option key={c.id} value={c.id}>
                #{c.id} · {patientFullName(c.patient)}
              </option>
            ))}
          </Select>
        </Field>
        {selected ? (
          <p className="text-xs text-ink-muted">
            Doctor for this consultation: <span className="font-medium text-ink">{selected.doctor?.name ?? '—'}</span>
          </p>
        ) : null}
        <Field label="Diagnosis name" htmlFor="dx-name" required error={errors.diagnosis_name}>
          <Input id="dx-name" value={form.diagnosis_name} onChange={(e) => set('diagnosis_name')(e.target.value)} placeholder="e.g. Acute sinusitis" invalid={Boolean(errors.diagnosis_name)} />
        </Field>
        <Field label="Description" htmlFor="dx-desc">
          <Textarea rows={2} id="dx-desc" value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Severity" htmlFor="dx-severity">
            <Select id="dx-severity" value={form.severity} onChange={(e) => set('severity')(e.target.value)}>
              <option value="">Not specified</option>
              {SEVERITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <label className="flex items-end gap-2 pb-2.5 text-sm text-ink">
            <input
              type="checkbox"
              checked={form.is_confirmed}
              onChange={(e) => set('is_confirmed')(e.target.checked)}
              className="size-4 accent-brand"
            />
            Confirmed diagnosis
          </label>
        </div>
        <Field label="Notes" htmlFor="dx-notes">
          <Textarea rows={2} id="dx-notes" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}