import { useMemo, useState } from 'react'
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
import { formatCurrency, patientFullName } from '../../utils/format.js'

const EMPTY_ITEM = { medicine_id: '', quantity: '1' }

export default function InvoiceFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const patients = useAll(['patients', 'all'], (params) => patientApi.list(params))
  const medicines = useAll(
    ['invoice-medicines'],
    (params) => invoiceApi.medicines(params),
    { enabled: !editing },
  )

  const [form, setForm] = useState(
    initial
      ? {
          patient_id: String(initial.patient_id ?? ''),
          consultation_id: initial.consultation_id ?? '',
          discount: String(initial.discount ?? ''),
          due_date: initial.due_date ?? '',
          notes: initial.notes ?? '',
          items: (initial.items ?? []).map((item) => ({
            medicine_id: item.medicine_id ? String(item.medicine_id) : '',
            quantity: String(item.quantity ?? '1'),
            description: item.description ?? '',
            unit_price: String(item.unit_price ?? '0.00'),
          })),
        }
      : {
          patient_id: '',
          consultation_id: '',
          discount: '',
          due_date: '',
          notes: '',
          items: [{ ...EMPTY_ITEM }],
        },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const medicineRows = useMemo(() => medicines.data ?? [], [medicines.data])
  const medicineById = useMemo(
    () => new Map(medicineRows.map((medicine) => [String(medicine.id), medicine])),
    [medicineRows],
  )

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

  const linePrice = (item) => {
    if (editing) {
      return Number(item.unit_price || 0)
    }

    const medicine = medicineById.get(String(item.medicine_id))

    return medicine ? Number(medicine.price) : 0
  }

  const subtotal = form.items.reduce(
    (sum, item) => sum + linePrice(item) * Number(item.quantity || 0),
    0,
  )
  const discount = Number(form.discount || 0)
  const tax = editing ? Number(initial?.tax ?? 0) : 0
  const total = subtotal - discount + tax

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.patient_id) next.patient_id = 'Select a patient.'

    if (!editing) {
      form.items.forEach((item, i) => {
        if (!item.medicine_id) {
          next[`item-${i}`] = 'Select a medicine from the catalog.'
        }
        const qty = Number(item.quantity)
        if (!Number.isInteger(qty) || qty < 1) {
          next[`item-${i}`] = 'Quantity must be ≥ 1.'
        }
      })
    }

    if (form.items.length === 0) {
      next.items = 'Add at least one line item.'
    }

    if (form.discount !== '' && (!Number.isFinite(discount) || discount < 0)) {
      next.discount = 'Must be ≥ 0.'
    } else if (discount > subtotal) {
      next.discount = 'Discount cannot exceed the subtotal.'
    } else if (total <= 0) {
      next.form = 'Invoice total must be greater than 0.'
    }

    setErrors(next)
    if (Object.keys(next).length > 0) {
      return
    }

    setSaving(true)
    try {
      if (editing) {
        await invoiceApi.update(initial.id, {
          discount: form.discount === '' ? 0 : discount,
          due_date: form.due_date || null,
          notes: form.notes || null,
        })
        toast.success('Invoice updated.')
      } else {
        await invoiceApi.create({
          patient_id: form.patient_id,
          consultation_id: form.consultation_id || null,
          discount: form.discount === '' ? 0 : discount,
          due_date: form.due_date || null,
          notes: form.notes || null,
          items: form.items.map((item) => ({
            medicine_id: Number(item.medicine_id),
            quantity: Number(item.quantity),
          })),
        })
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
      description={
        editing
          ? 'Line items are read-only. Only discount, due date, and notes can be changed.'
          : 'Bill a patient using catalog medicines with server-resolved prices.'
      }
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
            <Select
              id="inv-patient"
              value={form.patient_id}
              onChange={(e) => set('patient_id')(e.target.value)}
              disabled={editing || patients.isLoading}
              invalid={Boolean(errors.patient_id)}
            >
              <option value="">{patients.isLoading ? 'Loading…' : 'Select…'}</option>
              {(patients.data ?? []).map((p) => (
                <option key={p.id} value={p.id}>{patientFullName(p)} ({p.patient_number})</option>
              ))}
            </Select>
          </Field>
          <Field label="Consultation ID" htmlFor="inv-cons" hint={editing ? 'Read-only.' : 'Optional.'}>
            <Input
              id="inv-cons"
              type="number"
              min="1"
              value={form.consultation_id}
              onChange={(e) => set('consultation_id')(e.target.value)}
              disabled={editing}
            />
          </Field>
          <Field label="Due date" htmlFor="inv-due">
            <Input id="inv-due" type="date" value={form.due_date} onChange={(e) => set('due_date')(e.target.value)} />
          </Field>
        </div>

        <div>
          <div className="mb-2 flex items-center justify-between">
            <p className="text-sm font-medium text-ink">
              Line items
              {errors.items ? <span className="ml-1 text-sm text-red-600">required</span> : null}
            </p>
            {editing ? null : (
              <Button type="button" variant="outline" size="sm" onClick={addItem}>
                <Plus className="size-4" aria-hidden /> Add line
              </Button>
            )}
          </div>
          <div className="space-y-3">
            {form.items.map((item, index) => {
              const medicine = medicineById.get(String(item.medicine_id))
              const price = linePrice(item)

              return (
                <div key={index} className="rounded-xl border border-line bg-canvas/50 p-3">
                  {errors[`item-${index}`] ? (
                    <p className="mb-2 text-xs font-medium text-red-600">{errors[`item-${index}`]}</p>
                  ) : null}
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_120px_140px_auto]">
                    <Field label="Medicine" htmlFor={`inv-item-${index}-medicine`}>
                      {editing ? (
                        <Input
                          id={`inv-item-${index}-medicine`}
                          value={item.description}
                          disabled
                        />
                      ) : (
                        <Select
                          id={`inv-item-${index}-medicine`}
                          value={item.medicine_id}
                          onChange={(e) => setItem(index, 'medicine_id')(e.target.value)}
                          disabled={medicines.isLoading}
                        >
                          <option value="">
                            {medicines.isLoading ? 'Loading catalog…' : 'Select a medicine…'}
                          </option>
                          {medicineRows.map((row) => (
                            <option key={row.id} value={row.id}>
                              {row.name}{row.strength ? ` · ${row.strength}` : ''} — {formatCurrency(row.price)}
                            </option>
                          ))}
                        </Select>
                      )}
                    </Field>
                    <Field label="Quantity" htmlFor={`inv-item-${index}-qty`}>
                      <Input
                        id={`inv-item-${index}-qty`}
                        type="number"
                        min="1"
                        value={item.quantity}
                        onChange={(e) => setItem(index, 'quantity')(e.target.value)}
                        disabled={editing}
                      />
                    </Field>
                    <Field label="Unit price" htmlFor={`inv-item-${index}-price`} hint="Server-resolved.">
                      <Input
                        id={`inv-item-${index}-price`}
                        value={formatCurrency(price)}
                        disabled
                      />
                    </Field>
                    <div className="flex items-end justify-end pb-2">
                      <button
                        type="button"
                        aria-label={`Remove line ${index + 1}`}
                        className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600 disabled:opacity-40"
                        onClick={() => removeItem(index)}
                        disabled={editing || form.items.length === 1}
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </button>
                    </div>
                  </div>
                  {medicine ? (
                    <p className="mt-2 text-xs text-ink-muted">
                      {[medicine.code, medicine.form, medicine.unit].filter(Boolean).join(' · ')}
                    </p>
                  ) : null}
                </div>
              )
            })}
          </div>
        </div>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Discount" htmlFor="inv-discount" error={errors.discount}>
            <Input
              id="inv-discount"
              type="number"
              min="0"
              step="0.01"
              value={form.discount}
              onChange={(e) => set('discount')(e.target.value)}
              invalid={Boolean(errors.discount)}
            />
          </Field>
          <Field label="Tax" htmlFor="inv-tax" hint={editing && tax > 0 ? 'Legacy tax is preserved.' : 'Tax is not configured.'}>
            <Input id="inv-tax" value={formatCurrency(tax)} disabled />
          </Field>
          <Field label="Notes" htmlFor="inv-notes">
            <Input id="inv-notes" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
        </div>

        <div className="flex flex-wrap items-center justify-end gap-x-6 gap-y-1 rounded-lg border border-line bg-canvas/50 px-4 py-3 text-sm">
          <span className="text-ink-muted">Subtotal: <span className="font-medium text-ink">{formatCurrency(subtotal)}</span></span>
          <span className="text-ink-muted">Discount: <span className="font-medium text-ink">{formatCurrency(discount || 0)}</span></span>
          <span className="text-ink">Total: <span className="font-semibold">{formatCurrency(total || 0)}</span></span>
        </div>
      </form>
    </Modal>
  )
}
