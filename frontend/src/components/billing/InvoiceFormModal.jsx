import { useState } from 'react'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { patientApi } from '../../api/patients.js'
import { invoiceApi } from '../../api/billing.js'
import { useAll } from '../../hooks/usePaginated.js'
import { patientFullName } from '../../utils/format.js'

const EMPTY_ITEM = { description: '', quantity: '1', unit_price: '' }

export default function InvoiceFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params))

  const [form, setForm] = useState(
    initial
      ? {
          patient_id: String(initial.patient_id ?? ''),
          consultation_id: initial.consultation_id ?? '',
          discount: String(initial.discount ?? ''),
          tax: String(initial.tax ?? ''),
          due_date: initial.due_date ?? '',
          notes: initial.notes ?? '',
          items: (initial.items ?? []).map((item) => ({
            description: item.description ?? '',
            quantity: String(item.quantity ?? '1'),
            unit_price: String(item.unit_price ?? ''),
          })),
        }
      : {
          patient_id: '',
          consultation_id: '',
          discount: '',
          tax: '',
          due_date: '',
          notes: '',
          items: [{ ...EMPTY_ITEM }],
        },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const setItem = (index, key) => (value) => {
    setForm((f) => ({
      ...f,
      items: f.items.map((item, i) => (i === index ? { ...item, [key]: value } : item)),
    }))
  }

  const addItem = () => setForm((f) => ({ ...f, items: [...f.items, { ...EMPTY_ITEM }] }))
  const removeItem = (index) =>
    setForm((f) => ({ ...f, items: f.items.filter((_, i) => i !== index) }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'
    let anyInvalid = false
    form.items.forEach((item, i) => {
      if (!item.description.trim()) {
        next[`item-${i}`] = 'Enter a description.'
        anyInvalid = true
      }
      const qty = Number(item.quantity)
      if (!Number.isInteger(qty) || qty < 1) {
        next[`item-${i}`] = 'Quantity must be ≥ 1.'
        anyInvalid = true
      }
      const price = Number(item.unit_price)
      if (item.unit_price === '' || !Number.isFinite(price) || price < 0) {
        next[`item-${i}`] = 'Unit price must be ≥ 0.'
        anyInvalid = true
      }
    })
    if (form.items.length === 0) {
      next.items = 'Add at least one line item.'
      anyInvalid = true
    }
    const discount = Number(form.discount)
    if (form.discount !== '' && (!Number.isFinite(discount) || discount < 0)) {
      next.discount = 'Must be ≥ 0.'
      anyInvalid = true
    }
    setErrors(next)
    if (anyInvalid) {
      setErrors({ ...next, form: 'Please fix the highlighted fields.' })
      return
    }

    setSaving(true)
    try {
      const payload = {
        patient_id: form.patient_id,
        consultation_id: form.consultation_id || null,
        discount: form.discount === '' ? null : Number(form.discount),
        tax: form.tax === '' ? null : Number(form.tax),
        due_date: form.due_date || null,
        notes: form.notes || null,
        items: form.items.map((item) => ({
          description: item.description.trim(),
          quantity: Number(item.quantity),
          unit_price: Number(item.unit_price),
        })),
      }
      if (editing) {
        await invoiceApi.update(initial.id, payload)
        toast.success('Invoice updated.')
      } else {
        await invoiceApi.create(payload)
        toast.success('Invoice created.')
      }
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
      title={editing ? 'Edit invoice' : 'New invoice'}
      description="Bill a patient for services or products."
      size="xl"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="invoice-form" loading={saving}>
            {editing ? 'Save changes' : 'Create invoice'}
          </Button>
        </>
      }
    >
      <form id="invoice-form" onSubmit={handleSubmit} className="space-y-5" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Patient" htmlFor="inv-patient" required error={errors.patient_id}>
            <Select id="inv-patient" value={form.patient_id} onChange={(e) => set('patient_id')(e.target.value)} disabled={patients.isLoading} invalid={Boolean(errors.patient_id)}>
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Consultation ID" htmlFor="inv-cons" hint="Optional.">
            <Input id="inv-cons" type="number" min="1" value={form.consultation_id} onChange={(e) => set('consultation_id')(e.target.value)} />
          </Field>
          <Field label="Due date" htmlFor="inv-due">
            <Input id="inv-due" type="date" value={form.due_date} onChange={(e) => set('due_date')(e.target.value)} />
          </Field>
        </div>

        <div>
          <div className="mb-2 flex items-center justify-between">
            <p className="text-sm font-medium text-ink">Line items{errors.items ? <span className="ml-1 text-sm text-red-600">required</span> : null}</p>
            <Button type="button" variant="outline" size="sm" onClick={addItem}>
              <Plus className="size-4" aria-hidden /> Add line
            </Button>
          </div>
          <div className="space-y-3">
            {form.items.map((item, index) => (
              <div key={index} className="rounded-xl border border-line bg-canvas/50 p-3">
                {errors[`item-${index}`] ? (
                  <p className="mb-2 text-xs font-medium text-red-600">{errors[`item-${index}`]}</p>
                ) : null}
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_120px_140px_auto]">
                  <Field label="Description" htmlFor={`inv-item-${index}-desc`}>
                    <Input id={`inv-item-${index}-desc`} value={item.description} onChange={(e) => setItem(index, 'description')(e.target.value)} placeholder="e.g. Consultation fee" />
                  </Field>
                  <Field label="Quantity" htmlFor={`inv-item-${index}-qty`}>
                    <Input id={`inv-item-${index}-qty`} type="number" min="1" value={item.quantity} onChange={(e) => setItem(index, 'quantity')(e.target.value)} />
                  </Field>
                  <Field label="Unit price" htmlFor={`inv-item-${index}-price`}>
                    <Input id={`inv-item-${index}-price`} type="number" min="0" step="0.01" value={item.unit_price} onChange={(e) => setItem(index, 'unit_price')(e.target.value)} />
                  </Field>
                  <div className="flex items-end justify-end pb-2">
                    <button
                      type="button"
                      aria-label={`Remove line ${index + 1}`}
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

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Discount" htmlFor="inv-discount" error={errors.discount}>
            <Input id="inv-discount" type="number" min="0" step="0.01" value={form.discount} onChange={(e) => set('discount')(e.target.value)} invalid={Boolean(errors.discount)} />
          </Field>
          <Field label="Tax" htmlFor="inv-tax">
            <Input id="inv-tax" type="number" min="0" step="0.01" value={form.tax} onChange={(e) => set('tax')(e.target.value)} />
          </Field>
          <Field label="Notes" htmlFor="inv-notes">
            <Input id="inv-notes" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}