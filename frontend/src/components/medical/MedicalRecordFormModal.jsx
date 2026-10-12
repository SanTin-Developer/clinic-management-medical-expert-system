import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { appointmentApi } from '../../api/appointments.js'
import { medicalRecordApi } from '../../api/clinical.js'
import { usePaginated, useAll } from '../../hooks/usePaginated.js'
import { patientFullName } from '../../utils/format.js'

export default function MedicalRecordFormModal({ open, onClose, onSaved }) {
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params))
  const appointments = usePaginated(
    ['appointments', 'page1'],
    (params) => appointmentApi.list(params),
    { page: 1 },
  )

  const [form, setForm] = useState({
    patient_id: '',
    appointment_id: '',
    consultation_id: '',
    medical_history: '',
    allergies: '',
    treatment_plan: '',
    notes: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = { ...form }
      for (const key of ['appointment_id', 'consultation_id']) {
        if (!payload[key]) payload[key] = null
      }
      for (const key of ['medical_history', 'allergies', 'treatment_plan', 'notes']) {
        if (!payload[key]) payload[key] = null
      }
      await medicalRecordApi.create(payload)
      toast.success('Medical record created.')
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
      title="New medical record"
      description="Capture history, allergies and the treatment plan."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="record-form" loading={saving}>
            Save record
          </Button>
        </>
      }
    >
      <form id="record-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Patient" htmlFor="rec-patient" required error={errors.patient_id}>
            <Select id="rec-patient" value={form.patient_id} onChange={(e) => set('patient_id')(e.target.value)} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Appointment" htmlFor="rec-appt" hint="Optional; links the record to a visit.">
            <Select id="rec-appt" value={form.appointment_id} onChange={(e) => set('appointment_id')(e.target.value)}>
              <option value="">None</option>
              {(appointments.data?.data ?? []).map((a) => (
                <option key={a.id} value={a.id}>
                  {a.appointment_number} · {patientFullName(a.patient)}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Medical history" htmlFor="rec-hist" className="sm:col-span-2">
            <Textarea rows={3} id="rec-hist" value={form.medical_history} onChange={(e) => set('medical_history')(e.target.value)} placeholder="Prior conditions, surgeries, medications…" />
          </Field>
          <Field label="Allergies" htmlFor="rec-allergies" className="sm:col-span-2">
            <Textarea rows={2} id="rec-allergies" value={form.allergies} onChange={(e) => set('allergies')(e.target.value)} />
          </Field>
          <Field label="Treatment plan" htmlFor="rec-plan" className="sm:col-span-2">
            <Textarea rows={3} id="rec-plan" value={form.treatment_plan} onChange={(e) => set('treatment_plan')(e.target.value)} />
          </Field>
          <Field label="Notes" htmlFor="rec-notes" className="sm:col-span-2">
            <Textarea rows={2} id="rec-notes" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}