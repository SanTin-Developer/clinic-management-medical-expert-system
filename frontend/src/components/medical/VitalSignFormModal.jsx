import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { appointmentApi } from '../../api/appointments.js'
import { vitalSignApi } from '../../api/clinical.js'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { patientFullName } from '../../utils/format.js'

const NUMERIC_FIELDS = [
  { key: 'temperature', label: 'Temperature (°C)', min: 30, max: 45, step: '0.1' },
  { key: 'blood_pressure_systolic', label: 'Systolic BP (mmHg)', min: 50, max: 250, step: '1' },
  { key: 'blood_pressure_diastolic', label: 'Diastolic BP (mmHg)', min: 30, max: 150, step: '1' },
  { key: 'heart_rate', label: 'Heart rate (bpm)', min: 30, max: 250, step: '1' },
  { key: 'respiratory_rate', label: 'Respiratory rate', min: 5, max: 60, step: '1' },
  { key: 'oxygen_saturation', label: 'Oxygen saturation (%)', min: 50, max: 100, step: '1' },
  { key: 'weight', label: 'Weight (kg)', min: 1, max: 500, step: '0.1' },
  { key: 'height', label: 'Height (cm)', min: 30, max: 250, step: '1' },
]

export default function VitalSignFormModal({ open, onClose, onSaved }) {
  const { user } = useAuth()
  const isNurse = (user?.roles ?? []).some((r) => String(r.name ?? r).includes('Nurse'))
  const appointments = usePaginated(
    ['appointments', 'page1'],
    (params) => appointmentApi.list(params),
    { page: 1 },
  )

  const [form, setForm] = useState({
    appointment_id: '',
    patient_id: '',
    nurse_id: String(user?.id ?? ''),
    temperature: '',
    blood_pressure_systolic: '',
    blood_pressure_diastolic: '',
    heart_rate: '',
    respiratory_rate: '',
    oxygen_saturation: '',
    weight: '',
    height: '',
    symptoms: '',
    notes: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const appts = appointments.data?.data ?? []

  const setValue = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const setAppointment = (value) => {
    const appt = appts.find((a) => String(a.id) === String(value))
    setForm((f) => ({
      ...f,
      appointment_id: value,
      patient_id: appt ? String(appt.patient_id) : '',
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.appointment_id) next.appointment_id = 'Select the appointment.'
    if (!isNurse) next.form = 'Vital signs must be recorded by a user with the Nurse role.'
    for (const meta of NUMERIC_FIELDS) {
      const raw = form[meta.key]
      if (raw === '' || raw === null) continue
      const n = Number(raw)
      if (!Number.isFinite(n)) next[meta.key] = 'Must be a number.'
      else if (n < meta.min || n > meta.max) next[meta.key] = `Between ${meta.min} and ${meta.max}.`
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = { ...form }
      for (const meta of NUMERIC_FIELDS) {
        payload[meta.key] = payload[meta.key] === '' ? null : Number(payload[meta.key])
      }
      for (const key of ['symptoms', 'notes']) {
        payload[key] = payload[key] || null
      }
      await vitalSignApi.create(payload)
      toast.success('Vital signs recorded.')
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
      title="Record vital signs"
      description="Requires the Nurse role. Leave fields blank if not measured."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="vitals-form" loading={saving}>
            Save vital signs
          </Button>
        </>
      }
    >
      <form id="vitals-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Appointment" htmlFor="vs-appt" required error={errors.appointment_id}>
          <Select id="vs-appt" value={form.appointment_id} onChange={(e) => setAppointment(e.target.value)} disabled={appointments.isLoading} invalid={Boolean(errors.appointment_id)}>
            <option value="">{appointments.isLoading ? 'Loading…' : 'Select…'}</option>
            {appts
              .filter((a) => !['cancelled', 'no_show'].includes(a.status))
              .map((a) => (
                <option key={a.id} value={a.id}>
                  {a.appointment_number} · {patientFullName(a.patient)}
                </option>
              ))}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
          {NUMERIC_FIELDS.map((meta) => (
            <Field key={meta.key} label={meta.label} htmlFor={`vs-${meta.key}`} error={errors[meta.key]}>
              <Input
                id={`vs-${meta.key}`}
                type="number"
                inputMode="decimal"
                step={meta.step}
                placeholder="—"
                value={form[meta.key]}
                onChange={(e) => setValue(meta.key)(e.target.value)}
                invalid={Boolean(errors[meta.key])}
              />
            </Field>
          ))}
        </div>
        <Field label="Symptoms" htmlFor="vs-symptoms" className="sm:col-span-2">
          <Textarea rows={2} id="vs-symptoms" value={form.symptoms} onChange={(e) => setValue('symptoms')(e.target.value)} />
        </Field>
        <Field label="Notes" htmlFor="vs-notes" className="sm:col-span-2">
          <Textarea rows={2} id="vs-notes" value={form.notes} onChange={(e) => setValue('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}