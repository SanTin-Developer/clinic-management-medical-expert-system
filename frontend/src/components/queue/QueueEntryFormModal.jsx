import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { userApi } from '../../api/users.js'
import { queueApi } from '../../api/queue.js'
import { useAll } from '../../hooks/usePaginated.js'
import { patientFullName } from '../../utils/format.js'
import { QUEUE_PRIORITY_OPTIONS } from '../../utils/status.js'

export default function QueueEntryFormModal({ open, onClose, onSaved }) {
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params))
  const doctorsQuery = useAll(['users', 'all'], (params) => userApi.list(params))

  const doctorOptions = (doctorsQuery.data ?? []).filter((u) =>
    (u.roles ?? []).some((r) => String(r.name ?? r).includes('Doctor')),
  )

  const [form, setForm] = useState({ patient_id: '', doctor_id: '', priority: 'normal', notes: '' })
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
      await queueApi.create({
        patient_id: form.patient_id,
        doctor_id: form.doctor_id || null,
        priority: form.priority,
        notes: form.notes || null,
      })
      toast.success('Added to the queue.')
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
      title="Add walk-in to queue"
      description="Register a patient who arrived without an appointment."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="queue-entry-form" loading={saving}>
            Add to queue
          </Button>
        </>
      }
    >
      <form id="queue-entry-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Patient" htmlFor="queue-patient" required error={errors.patient_id}>
            <Select
              id="queue-patient"
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
          <Field label="Doctor" htmlFor="queue-doctor">
            <Select
              id="queue-doctor"
              value={form.doctor_id}
              onChange={(e) => set('doctor_id')(e.target.value)}
              disabled={doctorsQuery.isLoading}
            >
              <option value="">{doctorsQuery.isLoading ? 'Loading doctors…' : 'Unassigned'}</option>
              {doctorOptions.map((u) => (
                <option key={u.id} value={u.id}>
                  {u.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Priority" htmlFor="queue-priority">
            <Select
              id="queue-priority"
              value={form.priority}
              onChange={(e) => set('priority')(e.target.value)}
            >
              {QUEUE_PRIORITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Notes" htmlFor="queue-notes" className="sm:col-span-2">
            <Textarea
              id="queue-notes"
              rows={2}
              value={form.notes}
              onChange={(e) => set('notes')(e.target.value)}
              placeholder="Triage notes, presenting complaint…"
            />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
