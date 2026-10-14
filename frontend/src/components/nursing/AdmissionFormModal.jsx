import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { admissionApi, bedApi, wardApi } from '../../api/nursing.js'
import { useAll } from '../../hooks/usePaginated.js'
import { patientFullName } from '../../utils/format.js'

const EMPTY = {
  patient_id: '',
  ward_id: '',
  bed_id: '',
  reason: '',
  diagnosis_summary: '',
  notes: '',
  admitted_at: '',
}

export default function AdmissionFormModal({ open, onClose, onSaved }) {
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params), { enabled: open })
  const wards = useAll(['wards', 'all'], (params) => wardApi.list(params), { enabled: open })
  const beds = useAll(['beds', 'available'], (params) => bedApi.list(params), {
    params: { status: 'available' },
    enabled: open,
  })

  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const availableBeds = useMemo(() => {
    const all = beds.data ?? []
    if (!form.ward_id) return all
    return all.filter((b) => String(b.room?.ward_id) === String(form.ward_id))
  }, [beds.data, form.ward_id])

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    if (!form.bed_id) next.bed_id = 'Select a bed.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await admissionApi.create({
        patient_id: Number(form.patient_id),
        bed_id: Number(form.bed_id),
        reason: form.reason || null,
        diagnosis_summary: form.diagnosis_summary || null,
        notes: form.notes || null,
        admitted_at: form.admitted_at || undefined,
      })
      toast.success('Patient admitted.')
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
      title="Admit patient"
      description="Assign an available bed to an admitted patient."
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="admission-form" loading={saving}>Admit patient</Button>
        </>
      }
    >
      <form id="admission-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Patient" htmlFor="adm-patient" required error={errors.patient_id} className="sm:col-span-2">
            <Select id="adm-patient" value={form.patient_id} onChange={(e) => set('patient_id')(e.target.value)} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Ward" htmlFor="adm-ward" hint="Filters available beds.">
            <Select id="adm-ward" value={form.ward_id} onChange={(e) => { set('ward_id')(e.target.value); set('bed_id')('') }}>
              <option value="">All wards</option>
              {(wards.data ?? []).map((w) => (
                <option key={w.id} value={w.id}>{w.name}</option>
              ))}
            </Select>
          </Field>
          <Field label="Bed" htmlFor="adm-bed" required error={errors.bed_id}>
            <Select id="adm-bed" value={form.bed_id} onChange={(e) => set('bed_id')(e.target.value)} disabled={beds.isLoading} invalid={Boolean(errors.bed_id)}>
              <option value="">{beds.isLoading ? 'Loading…' : 'Select…'}</option>
              {availableBeds.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.room?.ward?.name ? `${b.room.ward.name} · ` : ''}{b.room?.room_number ? `Room ${b.room.room_number} · ` : ''}Bed {b.label}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Admitted at" htmlFor="adm-at" hint="Defaults to now.">
            <Input id="adm-at" type="datetime-local" value={form.admitted_at} onChange={(e) => set('admitted_at')(e.target.value)} />
          </Field>
          <Field label="Reason" htmlFor="adm-reason">
            <Input id="adm-reason" value={form.reason} onChange={(e) => set('reason')(e.target.value)} placeholder="e.g. Observation" />
          </Field>
        </div>
        <Field label="Diagnosis summary" htmlFor="adm-diagnosis">
          <Input id="adm-diagnosis" value={form.diagnosis_summary} onChange={(e) => set('diagnosis_summary')(e.target.value)} />
        </Field>
        <Field label="Notes" htmlFor="adm-notes">
          <Textarea id="adm-notes" rows={2} value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}
