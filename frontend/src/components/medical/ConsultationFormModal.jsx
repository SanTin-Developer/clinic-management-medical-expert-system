import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { appointmentApi } from '../../api/appointments.js'
import { consultationApi } from '../../api/clinical.js'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { patientFullName } from '../../utils/format.js'

export default function ConsultationFormModal({ open, onClose, onSaved }) {
  const { user } = useAuth()
  const appointments = usePaginated(
    ['appointments', 'page1'],
    (params) => appointmentApi.list(params),
    { page: 1 },
  )

  const [form, setForm] = useState({
    appointment_id: '',
    patient_id: '',
    doctor_id: String(user?.id ?? ''),
    chief_complaint: '',
    symptoms: '',
    clinical_notes: '',
    examination: '',
    assessment: '',
    plan: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const appts = appointments.data?.data ?? []
  const selectedAppt = appts.find((a) => String(a.id) === String(form.appointment_id))

  const setAppointment = (value) => {
    const appt = appts.find((a) => String(a.id) === String(value))
    setForm((f) => ({
      ...f,
      appointment_id: value,
      patient_id: appt ? String(appt.patient_id) : '',
      doctor_id: appt ? String(appt.doctor_id) : String(user?.id ?? ''),
    }))
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.appointment_id) next.appointment_id = 'Select the appointment.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = { ...form }
      for (const key of ['chief_complaint', 'symptoms', 'clinical_notes', 'examination', 'assessment', 'plan']) {
        if (!payload[key]) payload[key] = null
      }
      await consultationApi.create(payload)
      toast.success('Consultation recorded.')
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
      title="New consultation"
      description="Record the clinical encounter for an appointment."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="consultation-form" loading={saving}>
            Save consultation
          </Button>
        </>
      }
    >
      <form id="consultation-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Appointment" htmlFor="cs-appt" required error={errors.appointment_id} hint="Patient and doctor are taken from the appointment.">
          <Select id="cs-appt" value={form.appointment_id} onChange={(e) => setAppointment(e.target.value)} disabled={appointments.isLoading} invalid={Boolean(errors.appointment_id)}>
            <option value="">{appointments.isLoading ? 'Loading…' : 'Select…'}</option>
            {appts
              .filter((a) => !['cancelled', 'completed', 'no_show'].includes(a.status))
              .map((a) => (
                <option key={a.id} value={a.id}>
                  {a.appointment_number} · {patientFullName(a.patient)}
                </option>
              ))}
          </Select>
        </Field>

        {selectedAppt ? (
          <div className="rounded-xl border border-line bg-canvas px-4 py-3 text-sm text-ink-muted">
            Patient: <span className="font-medium text-ink">{patientFullName(selectedAppt.patient)}</span>
            {' · '}Doctor: <span className="font-medium text-ink">{selectedAppt.doctor?.name ?? '—'}</span>
          </div>
        ) : null}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Chief complaint" htmlFor="cs-cc" className="sm:col-span-2">
            <Textarea rows={2} id="cs-cc" value={form.chief_complaint} onChange={(e) => setForm((f) => ({ ...f, chief_complaint: e.target.value }))} />
          </Field>
          <Field label="Symptoms" htmlFor="cs-symptoms" className="sm:col-span-2">
            <Textarea rows={2} id="cs-symptoms" value={form.symptoms} onChange={(e) => setForm((f) => ({ ...f, symptoms: e.target.value }))} />
          </Field>
          <Field label="Examination" htmlFor="cs-exam">
            <Textarea rows={2} id="cs-exam" value={form.examination} onChange={(e) => setForm((f) => ({ ...f, examination: e.target.value }))} />
          </Field>
          <Field label="Clinical notes" htmlFor="cs-notes">
            <Textarea rows={2} id="cs-notes" value={form.clinical_notes} onChange={(e) => setForm((f) => ({ ...f, clinical_notes: e.target.value }))} />
          </Field>
          <Field label="Assessment" htmlFor="cs-assessment" className="sm:col-span-2">
            <Textarea rows={2} id="cs-assessment" value={form.assessment} onChange={(e) => setForm((f) => ({ ...f, assessment: e.target.value }))} />
          </Field>
          <Field label="Plan" htmlFor="cs-plan" className="sm:col-span-2">
            <Textarea rows={2} id="cs-plan" value={form.plan} onChange={(e) => setForm((f) => ({ ...f, plan: e.target.value }))} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}