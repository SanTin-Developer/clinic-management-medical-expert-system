import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { userApi } from '../../api/users.js'
import { appointmentApi } from '../../api/appointments.js'
import { useAll } from '../../hooks/usePaginated.js'
import { patientFullName, toIsoDate } from '../../utils/format.js'

const BASE_FORM = {
  patient_id: '',
  doctor_id: '',
  appointment_date: '',
  appointment_time: '',
  reason: '',
  note: '',
}

function formFromRecord(record) {
  if (!record) return BASE_FORM
  return {
    patient_id: record.patient_id ?? '',
    doctor_id: record.doctor_id ?? '',
    appointment_date: record.appointment_date ? String(record.appointment_date).slice(0, 10) : '',
    appointment_time: record.appointment_time ? String(record.appointment_time).slice(0, 5) : '',
    reason: record.reason ?? '',
    note: record.note ?? '',
  }
}

export default function AppointmentFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)

  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params))
  const doctorsQuery = useAll(['users', 'all'], (params) => userApi.list(params))

  const doctorOptions = (doctorsQuery.data ?? []).filter((u) =>
    (u.roles ?? []).some((r) => String(r.name ?? r).includes('Doctor')),
  )

  const [form, setForm] = useState(BASE_FORM)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  // The modal stays mounted between openings, so initialise the form whenever
  // it opens or a different record is selected.
  useEffect(() => {
    if (!open) return
    setForm(formFromRecord(initial))
    setErrors({})
  }, [open, initial?.id])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    if (!form.doctor_id) next.doctor_id = 'Select a doctor.'
    if (!form.appointment_date) next.appointment_date = 'Required.'
    if (!/^\d{2}:\d{2}$/.test(form.appointment_time)) next.appointment_time = 'Use HH:MM (24-hour).'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        ...form,
        reason: form.reason || null,
        note: form.note || null,
      }
      if (editing) {
        await appointmentApi.update(initial.id, payload)
        toast.success('Appointment updated.')
      } else {
        await appointmentApi.create(payload)
        toast.success('Appointment created.')
      }
      onSaved?.()
      onClose()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  const today = toIsoDate(new Date())

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? 'Edit appointment' : 'New appointment'}
      description="Book or reschedule an appointment with a doctor."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="appointment-form" loading={saving}>
            {editing ? 'Save changes' : 'Create appointment'}
          </Button>
        </>
      }
    >
      <form id="appointment-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Patient" htmlFor="apt-patient" required error={errors.patient_id}>
            <Select
              id="apt-patient"
              value={form.patient_id}
              onChange={(e) => set('patient_id')(e.target.value)}
              invalid={Boolean(errors.patient_id)}
              disabled={patients.isLoading}
            >
              <option value="">{patients.isLoading ? 'Loading patients…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>
                  {patientFullName(p)} ({p.patient_number})
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Doctor" htmlFor="apt-doctor" required error={errors.doctor_id}>
            <Select
              id="apt-doctor"
              value={form.doctor_id}
              onChange={(e) => set('doctor_id')(e.target.value)}
              invalid={Boolean(errors.doctor_id)}
              disabled={doctorsQuery.isLoading}
            >
              <option value="">{doctorsQuery.isLoading ? 'Loading doctors…' : 'Select…'}</option>
              {doctorOptions.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Date" htmlFor="apt-date" required error={errors.appointment_date}>
            <Input
              id="apt-date"
              type="date"
              min={today}
              value={form.appointment_date}
              onChange={(e) => set('appointment_date')(e.target.value)}
              invalid={Boolean(errors.appointment_date)}
            />
          </Field>
          <Field label="Time" htmlFor="apt-time" required error={errors.appointment_time} hint="24-hour format, e.g. 14:30.">
            <Input
              id="apt-time"
              type="time"
              value={form.appointment_time}
              onChange={(e) => set('appointment_time')(e.target.value)}
              invalid={Boolean(errors.appointment_time)}
            />
          </Field>
          <Field label="Reason" htmlFor="apt-reason" className="sm:col-span-2">
            <Textarea rows={2} id="apt-reason" value={form.reason} onChange={(e) => set('reason')(e.target.value)} placeholder="Why is the patient visiting?" />
          </Field>
          <Field label="Note" htmlFor="apt-note" className="sm:col-span-2">
            <Textarea rows={2} id="apt-note" value={form.note} onChange={(e) => set('note')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}