import { useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { medicineApi, inventoryApi } from '../../api/pharmacy.js'
import { useAll } from '../../hooks/usePaginated.js'

function dateInputValue(value) {
  return value ? String(value).slice(0, 10) : ''
}

export default function InventoryFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const medicines = useAll(['medicines', 'all'], (params) => medicineApi.list(params))

  const [form, setForm] = useState(
    initial
      ? {
          medicine_id: String(initial.medicine_id ?? ''),
          batch_number: initial.batch_number ?? '',
          quantity: String(initial.quantity ?? ''),
          expiration_date: dateInputValue(initial.expiration_date),
          received_date: dateInputValue(initial.received_date),
          location: initial.location ?? '',
        }
      : {
          medicine_id: '',
          batch_number: '',
          quantity: '',
          expiration_date: '',
          received_date: '',
          location: '',
        },
  )
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.medicine_id) next.medicine_id = 'Select a medicine.'
    if (!form.batch_number.trim()) next.batch_number = 'Batch number is required.'
    const qty = Number(form.quantity)
    if (form.quantity === '' || !Number.isInteger(qty) || qty < 1) next.quantity = 'Must be a whole number ≥ 1.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        medicine_id: form.medicine_id,
        batch_number: form.batch_number.trim(),
        quantity: qty,
        expiration_date: form.expiration_date || null,
        received_date: form.received_date || null,
        location: form.location || null,
      }
      if (editing) {
        await inventoryApi.update(initial.id, payload)
        toast.success('Inventory item updated.')
      } else {
        await inventoryApi.create(payload)
        toast.success('Stock added.')
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
      title={editing ? 'Edit inventory item' : 'Add stock'}
      description={editing ? 'Batch details only. Use a stock movement to change quantity.' : 'Receive a new batch; its opening balance is recorded as a stock receipt.'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="inventory-form" loading={saving}>
            {editing ? 'Save changes' : 'Add stock'}
          </Button>
        </>
      }
    >
      <form id="inventory-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Medicine" htmlFor="inv-med" required error={errors.medicine_id} className="sm:col-span-2">
            <Select id="inv-med" value={form.medicine_id} onChange={(e) => set('medicine_id')(e.target.value)} disabled={medicines.isLoading} invalid={Boolean(errors.medicine_id)}>
              <option value="">{medicines.isLoading ? 'Loading…' : 'Select…'}</option>
              {(medicines.data ?? []).map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name} ({m.code})
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Quantity" htmlFor="inv-qty" required error={errors.quantity}>
            <Input id="inv-qty" type="number" inputMode="numeric" min="1" value={form.quantity} onChange={(e) => set('quantity')(e.target.value)} disabled={editing} invalid={Boolean(errors.quantity)} />
          </Field>
          <Field label="Batch number" htmlFor="inv-batch" required error={errors.batch_number}>
            <Input id="inv-batch" value={form.batch_number} onChange={(e) => set('batch_number')(e.target.value)} disabled={editing} invalid={Boolean(errors.batch_number)} placeholder="e.g. LOT-2026-001" />
          </Field>
          <Field label="Expiration date" htmlFor="inv-exp">
            <Input id="inv-exp" type="date" value={form.expiration_date} onChange={(e) => set('expiration_date')(e.target.value)} />
          </Field>
          <Field label="Received date" htmlFor="inv-rec">
            <Input id="inv-rec" type="date" value={form.received_date} onChange={(e) => set('received_date')(e.target.value)} />
          </Field>
          <Field label="Location" htmlFor="inv-loc" className="sm:col-span-2">
            <Input id="inv-loc" value={form.location} onChange={(e) => set('location')(e.target.value)} placeholder="e.g. Pharmacy shelf B-2" />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
