import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { admissionApi, nursingTaskApi } from '../../api/nursing.js'
import { userApi } from '../../api/users.js'
import { useAll } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { patientFullName } from '../../utils/format.js'
import { NURSING_TASK_TYPE_OPTIONS, TASK_PRIORITY_OPTIONS } from '../../utils/status.js'

const EMPTY = {
  patient_id: '',
  admission_id: '',
  assigned_nurse_id: '',
  task_type: 'observation',
  title: '',
  description: '',
  priority: 'normal',
  scheduled_at: '',
  due_at: '',
  notes: '',
}

export default function NursingTaskFormModal({ open, onClose, onSaved }) {
  const { user, can } = useAuth()
  const canSeeUsers = can('users.view')

  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params), { enabled: open })
  const nurses = useAll(['users', 'nurses'], (params) => userApi.list(params), { enabled: open && canSeeUsers })
  const patientAdmissions = useAll(
    ['admissions', 'active'],
    (params) => admissionApi.list(params),
    { params: { status: 'admitted' }, enabled: open },
  )

  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const nurseOptions = useMemo(
    () => (nurses.data ?? []).filter((u) => (u.roles ?? []).some((r) => r.name === 'Nurse')),
    [nurses.data],
  )

  const admissionOptions = useMemo(
    () => (patientAdmissions.data ?? []).filter((a) => String(a.patient_id) === String(form.patient_id)),
    [patientAdmissions.data, form.patient_id],
  )

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    if (!form.title.trim()) next.title = 'Title is required.'
    if (!form.scheduled_at) next.scheduled_at = 'Scheduled time is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await nursingTaskApi.create({
        patient_id: Number(form.patient_id),
        admission_id: form.admission_id ? Number(form.admission_id) : null,
        assigned_nurse_id: form.assigned_nurse_id ? Number(form.assigned_nurse_id) : user?.id ?? null,
        task_type: form.task_type,
        title: form.title.trim(),
        description: form.description || null,
        priority: form.priority,
        scheduled_at: form.scheduled_at,
        due_at: form.due_at || null,
        notes: form.notes || null,
      })
      toast.success('Nursing task created.')
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
      title="New nursing task"
      description="Schedule a care activity for an assigned patient."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="task-form" loading={saving}>Create task</Button>
        </>
      }
    >
      <form id="task-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Patient" htmlFor="task-patient" required error={errors.patient_id}>
            <Select id="task-patient" value={form.patient_id} onChange={(e) => { set('patient_id')(e.target.value); set('admission_id')('') }} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Admission" htmlFor="task-admission" hint="Optional active admission.">
            <Select id="task-admission" value={form.admission_id} onChange={(e) => set('admission_id')(e.target.value)} disabled={!form.patient_id}>
              <option value="">None</option>
              {admissionOptions.map((a) => (
                <option key={a.id} value={a.id}>
                  #{a.id} {a.ward?.name ? `· ${a.ward.name}` : ''}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Type" htmlFor="task-type">
            <Select id="task-type" value={form.task_type} onChange={(e) => set('task_type')(e.target.value)}>
              {NURSING_TASK_TYPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Priority" htmlFor="task-priority">
            <Select id="task-priority" value={form.priority} onChange={(e) => set('priority')(e.target.value)}>
              {TASK_PRIORITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Title" htmlFor="task-title" required error={errors.title} className="sm:col-span-2">
            <Input id="task-title" value={form.title} onChange={(e) => set('title')(e.target.value)} invalid={Boolean(errors.title)} placeholder="e.g. Check vital signs" />
          </Field>
          {canSeeUsers ? (
            <Field label="Assign to nurse" htmlFor="task-nurse" hint={`Defaults to you (${user?.name ?? 'current user'}).`}>
              <Select id="task-nurse" value={form.assigned_nurse_id} onChange={(e) => set('assigned_nurse_id')(e.target.value)} disabled={nurses.isLoading}>
                <option value="">Me ({user?.name ?? 'current user'})</option>
                {nurseOptions.map((n) => (
                  <option key={n.id} value={n.id}>{n.name}</option>
                ))}
              </Select>
            </Field>
          ) : null}
          <Field label="Scheduled at" htmlFor="task-scheduled" required error={errors.scheduled_at}>
            <Input id="task-scheduled" type="datetime-local" value={form.scheduled_at} onChange={(e) => set('scheduled_at')(e.target.value)} invalid={Boolean(errors.scheduled_at)} />
          </Field>
          <Field label="Due at" htmlFor="task-due" hint="Optional.">
            <Input id="task-due" type="datetime-local" value={form.due_at} onChange={(e) => set('due_at')(e.target.value)} />
          </Field>
        </div>
        <Field label="Description" htmlFor="task-description">
          <Textarea id="task-description" rows={2} value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>
        <Field label="Notes" htmlFor="task-notes">
          <Textarea id="task-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
