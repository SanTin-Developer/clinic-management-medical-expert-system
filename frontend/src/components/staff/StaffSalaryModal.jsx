import { useState, useEffect } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { staffApi } from '../../api/staff.js'
import { PAY_FREQUENCY_OPTIONS } from '../../utils/status.js'

export default function StaffSalaryModal({ open, onClose, staff, onSaved }) {
  const [form, setForm] = useState({
    salary_amount: '',
    pay_frequency: 'monthly',
    allowances: '0',
    effective_from: '',
    note: '',
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open || !staff) return

    setForm({
      salary_amount: staff.salary_amount != null ? String(staff.salary_amount) : '',
      pay_frequency: staff.pay_frequency || 'monthly',
      allowances: staff.allowances != null ? String(staff.allowances) : '0',
      effective_from: new Date().toISOString().slice(0, 10),
      note: '',
    })
    setErrors({})
  }, [open, staff])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.salary_amount || Number(form.salary_amount) < 0) {
      next.salary_amount = 'A valid positive salary amount is required.'
    }
    if (!form.pay_frequency) next.pay_frequency = 'Select pay frequency.'
    if (!form.effective_from) next.effective_from = 'Effective date is required.'

    setErrors(next)
    if (Object.keys(next).length > 0) return

    setSaving(true)
    try {
      await staffApi.updateSalary(staff.id, {
        salary_amount: Number(form.salary_amount),
        pay_frequency: form.pay_frequency,
        allowances: form.allowances !== '' ? Number(form.allowances) : 0,
        effective_from: form.effective_from,
        note: form.note.trim() || null,
      })

      toast.success('Compensation updated and logged to audit history.')
      onSaved?.()
      onClose()
    } catch (err) {
      if (err.payload?.errors) {
        const nextErrors = {}
        for (const [key, val] of Object.entries(err.payload.errors)) {
          nextErrors[key] = Array.isArray(val) ? val[0] : String(val)
        }
        setErrors(nextErrors)
      }
      toast.error(err.message || 'Failed to update compensation.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Update Compensation"
      description={`Adjust salary and allowances for ${staff?.full_name || staff?.staff_number}. Each change writes an append-only audit record.`}
      size="md"
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <Field label="New salary amount ($)" required error={errors.salary_amount}>
          <Input
            type="number"
            step="0.01"
            min="0"
            value={form.salary_amount}
            onChange={(e) => set('salary_amount')(e.target.value)}
            placeholder="5500.00"
          />
        </Field>

        <Field label="Pay frequency" required error={errors.pay_frequency}>
          <Select
            value={form.pay_frequency}
            onChange={(event) => set('pay_frequency')(event.target.value)}
          >
            {PAY_FREQUENCY_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>{option.label}</option>
            ))}
          </Select>
        </Field>

        <Field label="Allowances ($)">
          <Input
            type="number"
            step="0.01"
            min="0"
            value={form.allowances}
            onChange={(e) => set('allowances')(e.target.value)}
            placeholder="300.00"
          />
        </Field>

        <Field label="Effective date" required error={errors.effective_from}>
          <Input
            type="date"
            value={form.effective_from}
            onChange={(e) => set('effective_from')(e.target.value)}
          />
        </Field>

        <Field label="Adjustment note / Reason (logged in audit history)">
          <Textarea
            value={form.note}
            onChange={(e) => set('note')(e.target.value)}
            placeholder="Annual performance review adjustment / promotion..."
            rows={2}
          />
        </Field>

        <div className="flex justify-end gap-3 pt-3 border-t border-line">
          <Button type="button" variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" disabled={saving}>
            {saving ? 'Updating…' : 'Record salary change'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
