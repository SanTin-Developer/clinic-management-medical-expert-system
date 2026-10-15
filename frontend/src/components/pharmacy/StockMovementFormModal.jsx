import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { medicineApi, inventoryApi, stockMovementApi } from '../../api/pharmacy.js'
import { useAll } from '../../hooks/usePaginated.js'
import { STOCK_MOVEMENT_TYPE } from '../../utils/status.js'

const TYPE_OPTIONS = Object.entries(STOCK_MOVEMENT_TYPE).map(([value, m]) => ({
  value,
  label: m.label,
}))

export default function StockMovementFormModal({ open, onClose, onSaved }) {
  const medicines = useAll(['medicines', 'all'], (params) => medicineApi.list(params))
  const inventory = useAll(['inventory', 'all'], (params) => inventoryApi.list(params))

  const [form, setForm] = useState({
    medicine_id: '',
    inventory_item_id: '',
    type: 'in',
    quantity: '',
    reason: '',
    reference: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const inventoryRows = (inventory.data ?? []).filter(
    (item) => !form.medicine_id || String(item.medicine_id) === String(form.medicine_id),
  )

  useEffect(() => {
    if (!open) return
    setErrors({})
  }, [open])

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.medicine_id) next.medicine_id = 'Select a medicine.'
    if (!form.inventory_item_id) next.inventory_item_id = 'Select the batch.'
    const qty = Number(form.quantity)
    const minimum = form.type === 'adjustment' ? 0 : 1
    if (!Number.isInteger(qty) || qty < minimum) next.quantity = `Must be a whole number ≥ ${minimum}.`
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await stockMovementApi.create({
        medicine_id: form.medicine_id,
        inventory_item_id: form.inventory_item_id,
        type: form.type,
        quantity: qty,
        reason: form.reason || null,
        reference: form.reference || null,
      })
      toast.success('Stock movement recorded.')
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
      title="Record stock movement"
      description="Increase, decrease or adjust stock on a specific batch."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="movement-form" loading={saving}>
            Record movement
          </Button>
        </>
      }
    >
      <form id="movement-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Medicine" htmlFor="mv-med" required error={errors.medicine_id}>
            <Select
              id="mv-med"
              value={form.medicine_id}
              onChange={(e) => {
                set('medicine_id')(e.target.value)
                set('inventory_item_id')('')
              }}
              disabled={medicines.isLoading}
              invalid={Boolean(errors.medicine_id)}
            >
              <option value="">{medicines.isLoading ? 'Loading…' : 'Select…'}</option>
              {(medicines.data ?? []).map((m) => (
                <option key={m.id} value={m.id}>
                  {m.name} ({m.code})
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Type" htmlFor="mv-type" required>
            <Select id="mv-type" value={form.type} onChange={(e) => set('type')(e.target.value)}>
              {TYPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Batch" htmlFor="mv-batch" required error={errors.inventory_item_id} className="sm:col-span-2">
            <Select
              id="mv-batch"
              value={form.inventory_item_id}
              onChange={(e) => set('inventory_item_id')(e.target.value)}
              disabled={inventory.isLoading || !form.medicine_id}
              invalid={Boolean(errors.inventory_item_id)}
            >
              <option value="">
                {!form.medicine_id
                  ? 'Select a medicine first'
                  : inventory.isLoading
                    ? 'Loading…'
                    : 'Select a batch…'}
              </option>
              {inventoryRows.map((item) => (
                <option key={item.id} value={item.id}>
                  {item.medicine?.name ?? `#${item.medicine_id}`} · Batch {item.batch_number || '—'} · {item.quantity} in stock
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Quantity" htmlFor="mv-qty" required error={errors.quantity}>
            <Input id="mv-qty" type="number" inputMode="numeric" min={form.type === 'adjustment' ? '0' : '1'} value={form.quantity} onChange={(e) => set('quantity')(e.target.value)} invalid={Boolean(errors.quantity)} />
          </Field>
          <Field label="Reference" htmlFor="mv-ref">
            <Input id="mv-ref" value={form.reference} onChange={(e) => set('reference')(e.target.value)} placeholder="e.g. PO-1001" />
          </Field>
          <Field label="Reason" htmlFor="mv-reason" className="sm:col-span-2">
            <Input id="mv-reason" value={form.reason} onChange={(e) => set('reason')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
