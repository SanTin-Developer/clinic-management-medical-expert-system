import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { medicineApi } from '../../api/pharmacy.js'
import { admissionApi, medicationAdministrationApi } from '../../api/nursing.js'
import { useAll } from '../../hooks/usePaginated.js'
import { patientFullName } from '../../utils/format.js'
import { MEDICATION_ROUTE_OPTIONS } from '../../utils/status.js'

const EMPTY = {
  patient_id: '',
  admission_id: '',
  medicine_id: '',
  dose: '',
  route: 'oral',
  scheduled_at: '',
  notes: '',
}

export default function MarFormModal({ open, onClose, onSaved }) {
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params), { enabled: open })
  const medicines = useAll(['medicines', 'all'], (params) => medicineApi.list(params), { enabled: open })
  const activeAdmissions = useAll(
    ['admissions', 'active'],
    (params) => admissionApi.list(params),
    { params: { status: 'admitted' }, enabled: open },
  )

  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const admissionOptions = useMemo(
    () => (activeAdmissions.data ?? []).filter((a) => String(a.patient_id) === String(form.patient_id)),
    [activeAdmissions.data, form.patient_id],
  )

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    if (!form.scheduled_at) next.scheduled_at = 'Scheduled time is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await medicationAdministrationApi.create({
        patient_id: Number(form.patient_id),
        admission_id: form.admission_id ? Number(form.admission_id) : null,
        medicine_id: form.medicine_id ? Number(form.medicine_id) : null,
        dose: form.dose || null,
        route: form.route,
        scheduled_at: form.scheduled_at,
        notes: form.notes || null,
      })
      toast.success('Medication scheduled.')
      setForm(EMPTY)
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
      title="Schedule medication"
      description="Add a dose to the medication administration record."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="mar-form" loading={saving}>Schedule</Button>
        </>
      }
    >
      <form id="mar-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Patient" htmlFor="mar-patient" required error={errors.patient_id}>
            <Select id="mar-patient" value={form.patient_id} onChange={(e) => { set('patient_id')(e.target.value); set('admission_id')('') }} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Admission" htmlFor="mar-admission" hint="Optional.">
            <Select id="mar-admission" value={form.admission_id} onChange={(e) => set('admission_id')(e.target.value)} disabled={!form.patient_id}>
              <option value="">None</option>
              {admissionOptions.map((a) => (
                <option key={a.id} value={a.id}>#{a.id} {a.ward?.name ? `· ${a.ward.name}` : ''}</option>
              ))}
            </Select>
          </Field>
          <Field label="Medicine" htmlFor="mar-medicine">
            <Select id="mar-medicine" value={form.medicine_id} onChange={(e) => set('medicine_id')(e.target.value)} disabled={medicines.isLoading}>
              <option value="">{medicines.isLoading ? 'Loading…' : 'Select…'}</option>
              {(medicines.data ?? []).map((m) => (
                <option key={m.id} value={m.id}>{m.name} ({m.code})</option>
              ))}
            </Select>
          </Field>
          <Field label="Dose" htmlFor="mar-dose">
            <Input id="mar-dose" value={form.dose} onChange={(e) => set('dose')(e.target.value)} placeholder="e.g. 500 mg / 1 tab" />
          </Field>
          <Field label="Route" htmlFor="mar-route">
            <Select id="mar-route" value={form.route} onChange={(e) => set('route')(e.target.value)}>
              {MEDICATION_ROUTE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Scheduled at" htmlFor="mar-scheduled" required error={errors.scheduled_at}>
            <Input id="mar-scheduled" type="datetime-local" value={form.scheduled_at} onChange={(e) => set('scheduled_at')(e.target.value)} invalid={Boolean(errors.scheduled_at)} />
          </Field>
        </div>
        <Field label="Notes" htmlFor="mar-notes">
          <Textarea id="mar-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
