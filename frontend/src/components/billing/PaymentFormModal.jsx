import { useEffect, useRef, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import { invoiceApi } from '../../api/billing.js'
import { PAYMENT_METHOD_OPTIONS } from '../../utils/status.js'

export default function PaymentFormModal({ open, onClose, invoice, onSaved }) {
  const [form, setForm] = useState({
    amount: '',
    method: 'cash',
    reference: '',
    paid_at: '',
    notes: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const idempotencyKeyRef = useRef(null)

  const balance = Number(invoice?.balance ?? 0)

  useEffect(() => {
    if (!open) return
    idempotencyKeyRef.current = crypto.randomUUID()
  }, [open])

  useEffect(() => {
    if (!open) return
    setForm((f) => ({ ...f, amount: String(balance || '') }))
    setErrors({})
  }, [open, balance])

  const set = (key) => (value) => {
    setForm((f) => ({ ...f, [key]: value }))
    idempotencyKeyRef.current = null
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    const amount = Number(form.amount)
    if (!Number.isFinite(amount) || amount < 0.01) next.amount = 'Enter an amount ≥ 0.01.'
    else if (amount > balance) next.amount = `Cannot exceed the outstanding balance (${balance}).`
    setErrors(next)
    if (Object.keys(next).length) return

    const paymentKey = idempotencyKeyRef.current ?? crypto.randomUUID()
    if (!idempotencyKeyRef.current) idempotencyKeyRef.current = paymentKey

    setSaving(true)
    try {
      await invoiceApi.recordPayment(invoice.id, {
        amount,
        method: form.method,
        reference: form.reference || null,
        paid_at: form.paid_at || null,
        notes: form.notes || null,
        idempotency_key: paymentKey,
      })
      toast.success('Payment recorded.')
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
      title="Record payment"
      description={invoice ? `Outstanding balance: ${balance}` : 'Record a payment against this invoice.'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="payment-form" loading={saving}>
            Record payment
          </Button>
        </>
      }
    >
      <form id="payment-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Amount" htmlFor="pay-amount" required error={errors.amount}>
            <Input id="pay-amount" type="number" min="0.01" step="0.01" value={form.amount} onChange={(e) => set('amount')(e.target.value)} invalid={Boolean(errors.amount)} />
          </Field>
          <Field label="Method" htmlFor="pay-method">
            <Select id="pay-method" value={form.method} onChange={(e) => set('method')(e.target.value)}>
              {PAYMENT_METHOD_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Reference" htmlFor="pay-ref">
            <Input id="pay-ref" value={form.reference} onChange={(e) => set('reference')(e.target.value)} placeholder="e.g. card code or transfer ref" />
          </Field>
          <Field label="Paid at" htmlFor="pay-date">
            <Input id="pay-date" type="datetime-local" value={form.paid_at} onChange={(e) => set('paid_at')(e.target.value)} />
          </Field>
          <Field label="Notes" htmlFor="pay-notes" className="sm:col-span-2">
            <Input id="pay-notes" value={form.notes} onChange={(e) => set('notes')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}