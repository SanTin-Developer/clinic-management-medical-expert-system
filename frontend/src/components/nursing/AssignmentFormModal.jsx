import { useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { admissionApi, assignmentApi, wardApi } from '../../api/nursing.js'
import { userApi } from '../../api/users.js'
import { useAll } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { patientFullName } from '../../utils/format.js'

const EMPTY = {
  scope: 'patient',
  nurse_id: '',
  patient_id: '',
  ward_id: '',
  admission_id: '',
  shift: '',
  starts_at: '',
  ends_at: '',
  notes: '',
  reason: '',
}

const toLocalInput = (iso) => (iso ? iso.slice(0, 16) : '')

export default function AssignmentFormModal({ open, assignment, onClose, onSaved }) {
  const { user, can } = useAuth()
  const canSeeUsers = can('users.view')
  const isEdit = Boolean(assignment)

  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params), { enabled: open })
  const wards = useAll(['wards', 'all'], (params) => wardApi.list(params), { enabled: open })
  const nurses = useAll(['users', 'nurses'], (params) => userApi.list(params), { enabled: open && canSeeUsers })
  const activeAdmissions = useAll(
    ['admissions', 'active'],
    (params) => admissionApi.list(params),
    { params: { status: 'admitted' }, enabled: open },
  )

  const [form, setForm] = useState({ ...EMPTY, nurse_id: String(user?.id ?? '') })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    if (!assignment) {
      setForm({ ...EMPTY, nurse_id: String(user?.id ?? '') })
      return
    }
    setForm({
      scope: assignment.patient_id ? 'patient' : 'ward',
      nurse_id: String(assignment.nurse_id ?? ''),
      patient_id: assignment.patient_id ? String(assignment.patient_id) : '',
      ward_id: assignment.ward_id ? String(assignment.ward_id) : '',
      admission_id: assignment.admission_id ? String(assignment.admission_id) : '',
      shift: assignment.shift ?? '',
      starts_at: toLocalInput(assignment.starts_at),
      ends_at: toLocalInput(assignment.ends_at),
      notes: assignment.notes ?? '',
      reason: '',
    })
  }, [open, assignment, user?.id])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const nurseOptions = useMemo(
    () => (nurses.data ?? []).filter((u) => (u.roles ?? []).some((r) => r.name === 'Nurse')),
    [nurses.data],
  )

  const admissionOptions = useMemo(
    () => (activeAdmissions.data ?? []).filter((a) => String(a.patient_id) === String(form.patient_id)),
    [activeAdmissions.data, form.patient_id],
  )

  const handleSubmit = async (event) => {
    event.preventDefault()
    const nurseId = canSeeUsers ? form.nurse_id : String(user?.id ?? '')
    const next = {}
    if (!nurseId) next.nurse_id = 'Select a nurse.'
    if (form.scope === 'patient' && !form.patient_id) next.patient_id = 'Select a patient.'
    if (form.scope === 'ward' && !form.ward_id) next.ward_id = 'Select a ward.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        nurse_id: Number(nurseId),
        patient_id: form.scope === 'patient' ? Number(form.patient_id) : null,
        ward_id: form.scope === 'ward' ? Number(form.ward_id) : null,
        admission_id: form.scope === 'patient' && form.admission_id ? Number(form.admission_id) : null,
        shift: form.shift || null,
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        notes: form.notes || null,
        reason: form.reason || null,
      }

      if (isEdit) {
        await assignmentApi.update(assignment.id, payload)
        toast.success('Assignment updated.')
      } else {
        await assignmentApi.create(payload)
        toast.success('Assignment created.')
      }
      setForm({ ...EMPTY, nurse_id: String(user?.id ?? '') })
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
      title={isEdit ? 'Edit assignment' : 'New assignment'}
      description={
        isEdit
          ? 'Changing the nurse or scope reassigns the patient and records an audit trail.'
          : 'Assign a nurse to a patient or an entire ward.'
      }
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="assignment-form" loading={saving}>
            {isEdit ? 'Save changes' : 'Create assignment'}
          </Button>
        </>
      }
    >
      <form id="assignment-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          {canSeeUsers ? (
            <Field label="Nurse" htmlFor="asg-nurse" required error={errors.nurse_id}>
              <Select id="asg-nurse" value={form.nurse_id} onChange={(e) => set('nurse_id')(e.target.value)} disabled={nurses.isLoading} invalid={Boolean(errors.nurse_id)}>
                <option value="">{nurses.isLoading ? 'Loading…' : 'Select…'}</option>
                {nurseOptions.map((n) => (
                  <option key={n.id} value={n.id}>{n.name}</option>
                ))}
              </Select>
            </Field>
          ) : null}
          <Field label="Scope" htmlFor="asg-scope">
            <Select id="asg-scope" value={form.scope} onChange={(e) => { set('scope')(e.target.value); set('patient_id')(''); set('ward_id')(''); set('admission_id')('') }}>
              <option value="patient">Specific patient</option>
              <option value="ward">Whole ward</option>
            </Select>
          </Field>
          {form.scope === 'patient' ? (
            <>
              <Field label="Patient" htmlFor="asg-patient" required error={errors.patient_id}>
                <Select id="asg-patient" value={form.patient_id} onChange={(e) => { set('patient_id')(e.target.value); set('admission_id')('') }} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
                  <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
                  {(patients.data ?? []).map((p) => (
                    <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
                  ))}
                </Select>
              </Field>
              <Field label="Admission" htmlFor="asg-admission" hint="Optional.">
                <Select id="asg-admission" value={form.admission_id} onChange={(e) => set('admission_id')(e.target.value)} disabled={!form.patient_id}>
                  <option value="">None</option>
                  {admissionOptions.map((a) => (
                    <option key={a.id} value={a.id}>#{a.id} {a.ward?.name ? `· ${a.ward.name}` : ''}</option>
                  ))}
                </Select>
              </Field>
            </>
          ) : (
            <Field label="Ward" htmlFor="asg-ward" required error={errors.ward_id}>
              <Select id="asg-ward" value={form.ward_id} onChange={(e) => set('ward_id')(e.target.value)} disabled={wards.isLoading} invalid={Boolean(errors.ward_id)}>
                <option value="">{wards.isLoading ? 'Loading…' : 'Select…'}</option>
                {(wards.data ?? []).map((w) => (
                  <option key={w.id} value={w.id}>{w.name}</option>
                ))}
              </Select>
            </Field>
          )}
          <Field label="Shift" htmlFor="asg-shift" hint="Optional.">
            <Select id="asg-shift" value={form.shift} onChange={(e) => set('shift')(e.target.value)}>
              <option value="">Full time (7:30 - 5:00pm)</option>
              <option value="morning">Morning</option>
              <option value="afternoon">Afternoon</option>
              <option value="evening">Evening</option>
              <option value="night">Night</option>
            </Select>
          </Field>
          <Field label="Starts at" htmlFor="asg-starts" hint="Defaults to now.">
            <Input id="asg-starts" type="datetime-local" value={form.starts_at} onChange={(e) => set('starts_at')(e.target.value)} />
          </Field>
          <Field label="Ends at" htmlFor="asg-ends" hint="Optional; blank = open-ended.">
            <Input id="asg-ends" type="datetime-local" value={form.ends_at} onChange={(e) => set('ends_at')(e.target.value)} />
          </Field>
        </div>
        {isEdit ? (
          <Field label="Reason" htmlFor="asg-reason" hint="Stored in the assignment audit trail.">
            <Input id="asg-reason" value={form.reason} onChange={(e) => set('reason')(e.target.value)} />
          </Field>
        ) : null}
        <Field label="Notes" htmlFor="asg-notes">
          <Textarea id="asg-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
