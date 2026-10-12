import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { patientApi } from '../../api/patients.js'
import { medicineApi } from '../../api/pharmacy.js'
import { prescriptionApi } from '../../api/prescriptions.js'
import { useAll } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { patientFullName } from '../../utils/format.js'

const EMPTY_ITEM = {
  medicine_id: '',
  quantity: '1',
  dosage: '',
  frequency: '',
  duration: '',
  instructions: '',
}

export default function PrescriptionFormModal({ open, onClose, onSaved }) {
  const { user } = useAuth()
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params))
  const medicines = useAll(['medicines', 'all'], (params) => medicineApi.list(params))

  const [form, setForm] = useState({
    patient_id: '',
    doctor_id: String(user?.id ?? ''),
    consultation_id: '',
    notes: '',
    items: [{ ...EMPTY_ITEM }],
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const setItem = (index, key) => (value) => {
    setForm((f) => {
      const items = f.items.map((item, i) => (i === index ? { ...item, [key]: value } : item))
      return { ...f, items }
    })
  }

  const addItem = () => setForm((f) => ({ ...f, items: [...f.items, { ...EMPTY_ITEM }] }))
  const removeItem = (index) =>
    setForm((f) => ({ ...f, items: f.items.filter((_, i) => i !== index) }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    if (form.items.length === 0) next.items = 'Add at least one medicine.'
    form.items.forEach((item, i) => {
      if (!item.medicine_id) next[`item-${i}`] = 'Select a medicine.'
      const qty = Number(item.quantity)
      if (!Number.isInteger(qty) || qty < 1) next[`item-${i}`] = 'Quantity must be at least 1.'
    })
    setErrors(next)
    if (Object.keys(next).length) {
      setErrors({ ...next, form: 'Please fix the highlighted fields.' })
      return
    }

    setSaving(true)
    try {
      await prescriptionApi.create({
        patient_id: form.patient_id,
        doctor_id: form.doctor_id || user?.id,
        consultation_id: form.consultation_id || null,
        notes: form.notes || null,
        items: form.items.map((item) => ({
          medicine_id: item.medicine_id,
          quantity: Number(item.quantity),
          dosage: item.dosage || null,
          frequency: item.frequency || null,
          duration: item.duration || null,
          instructions: item.instructions || null,
        })),
      })
      toast.success('Prescription created.')
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
      title="New prescription"
      description="Create a prescription with one or more medicines."
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="prescription-form" loading={saving}>
            Create prescription
          </Button>
        </>
      }
    >
      <form id="prescription-form" onSubmit={handleSubmit} className="space-y-5" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Patient" htmlFor="rx-patient" required error={errors.patient_id}>
            <Select id="rx-patient" value={form.patient_id} onChange={(e) => set('patient_id')(e.target.value)} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Doctor" htmlFor="rx-doctor">
            <Input id="rx-doctor" value={user?.name ?? ''} readOnly className="bg-canvas" />
          </Field>
          <Field label="Consultation ID" htmlFor="rx-consultation" hint="Optional.">
            <Input id="rx-consultation" type="number" inputMode="numeric" min="1" value={form.consultation_id} onChange={(e) => set('consultation_id')(e.target.value)} />
          </Field>
        </div>

        <div>
          <div className="mb-2 flex items-center justify-between">
            <p className="text-sm font-medium text-ink">Medicines</p>
            <Button type="button" variant="outline" size="sm" onClick={addItem}>
              <Plus className="size-4" aria-hidden /> Add medicine
            </Button>
          </div>
          <div className="space-y-3">
            {form.items.map((item, index) => (
              <div
                key={index}
                className="rounded-xl border border-line bg-canvas/50 p-3"
              >
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
                  <Field
                    label="Medicine"
                    htmlFor={`rx-item-${index}-med`}
                    required
                    error={errors[`item-${index}`]}
                    className="col-span-2"
                  >
                    <Select
                      id={`rx-item-${index}-med`}
                      value={item.medicine_id}
                      onChange={(e) => setItem(index, 'medicine_id')(e.target.value)}
                      disabled={medicines.isLoading}
                    >
                      <option value="">{medicines.isLoading ? 'Loading…' : 'Select…'}</option>
                      {(medicines.data ?? []).map((m) => (
                        <option key={m.id} value={m.id}>
                          {m.name} ({m.code})
                        </option>
                      ))}
                    </Select>
                  </Field>
                  <Field label="Quantity" htmlFor={`rx-item-${index}-qty`} required>
                    <Input
                      id={`rx-item-${index}-qty`}
                      type="number"
                      inputMode="numeric"
                      min="1"
                      value={item.quantity}
                      onChange={(e) => setItem(index, 'quantity')(e.target.value)}
                    />
                  </Field>
                  <Field label="Dosage" htmlFor={`rx-item-${index}-dose`}>
                    <Input id={`rx-item-${index}-dose`} value={item.dosage} onChange={(e) => setItem(index, 'dosage')(e.target.value)} placeholder="500 mg" />
                  </Field>
                  <Field label="Frequency" htmlFor={`rx-item-${index}-freq`}>
                    <Input id={`rx-item-${index}-freq`} value={item.frequency} onChange={(e) => setItem(index, 'frequency')(e.target.value)} placeholder="3x daily" />
                  </Field>
                </div>
                <div className="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_1fr_auto]">
                  <Field label="Duration" htmlFor={`rx-item-${index}-dur`}>
                    <Input id={`rx-item-${index}-dur`} value={item.duration} onChange={(e) => setItem(index, 'duration')(e.target.value)} placeholder="7 days" />
                  </Field>
                  <Field label="Instructions" htmlFor={`rx-item-${index}-instr`}>
                    <Input id={`rx-item-${index}-instr`} value={item.instructions} onChange={(e) => setItem(index, 'instructions')(e.target.value)} placeholder="Take after meals" />
                  </Field>
                  <div className="flex items-end pb-2">
                    <button
                      type="button"
                      aria-label={`Remove medicine ${index + 1}`}
                      className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-40"
                      onClick={() => removeItem(index)}
                      disabled={form.items.length === 1}
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <Field label="Notes" htmlFor="rx-notes">
          <Textarea rows={2} id="rx-notes" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
        </Field>
      </form>
    </Modal>
  )
}