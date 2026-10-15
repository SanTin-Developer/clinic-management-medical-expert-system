import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { medicationAdministrationApi } from '../../api/nursing.js'
import { MEDICATION_ROUTES, MEDICATION_ROUTE_OPTIONS, MEDICATION_STATUS, statusBadgeFor } from '../../utils/status.js'
import { formatDateTime, patientFullName } from '../../utils/format.js'

const ADMINISTRATION_OUTCOMES = [
  { value: 'administered', label: MEDICATION_STATUS.administered.label },
  { value: 'held', label: MEDICATION_STATUS.held.label },
  { value: 'refused', label: MEDICATION_STATUS.refused.label },
  { value: 'missed', label: MEDICATION_STATUS.missed.label },
]

const EMPTY = { outcome: 'administered', dose: '', route: 'oral', route_detail: '', notes: '' }

export default function AdministerMedicationModal({ open, record, initialOutcome = 'administered', onClose, onSaved }) {
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open || !record) return
    setForm({
      outcome: initialOutcome,
      dose: record.dose ?? '',
      route: record.route ?? 'oral',
      route_detail: record.route_detail ?? '',
      notes: '',
    })
    setErrors({})
  }, [open, record, initialOutcome])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const isGiven = form.outcome === 'administered'

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (isGiven) {
      if (!form.dose.trim()) next.dose = 'Actual dose given is required.'
      if (!form.route) next.route = 'Administration route is required.'
      if (form.route === 'other' && !form.route_detail.trim()) next.route_detail = 'Describe the route you used.'
    } else if (!form.notes.trim()) {
      next.notes = 'A reason is required when the dose is not administered.'
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await medicationAdministrationApi.administer(record.id, {
        outcome: form.outcome,
        dose: isGiven ? form.dose.trim() : null,
        route: isGiven ? form.route : null,
        route_detail: isGiven && form.route === 'other' ? form.route_detail.trim() : null,
        notes: form.notes.trim() || null,
      })
      toast.success(isGiven ? 'Medication administration recorded.' : `Dose marked as ${form.outcome}.`)
      onSaved?.()
      onClose()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  const statusMeta = record ? statusBadgeFor(MEDICATION_STATUS, form.outcome) : null

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Medication administration"
      description={record ? `${patientFullName(record.patient)} · ${record.medicine?.name ?? 'Medication'}` : ''}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="administer-form" loading={saving}>
            {isGiven ? 'Record administration' : `Save as ${form.outcome}`}
          </Button>
        </>
      }
    >
      <form id="administer-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}

        {record ? (
          <dl className="grid grid-cols-1 gap-2 rounded-lg border border-line bg-canvas px-3 py-2 text-sm sm:grid-cols-3">
            <div>
              <dt className="text-xs text-ink-muted">Scheduled dose</dt>
              <dd className="text-ink">{record.dose ?? '—'}{record.route ? ` · ${statusBadgeFor(MEDICATION_ROUTES, record.route).label}` : ''}</dd>
            </div>
            <div>
              <dt className="text-xs text-ink-muted">Scheduled at</dt>
              <dd className="text-ink">{formatDateTime(record.scheduled_at)}</dd>
            </div>
            <div>
              <dt className="text-xs text-ink-muted">Outcome</dt>
              <dd><span className="text-ink">{statusMeta?.label ?? '—'}</span></dd>
            </div>
          </dl>
        ) : null}

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Outcome" htmlFor="adm-outcome" required>
            <Select id="adm-outcome" value={form.outcome} onChange={(e) => set('outcome')(e.target.value)}>
              {ADMINISTRATION_OUTCOMES.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>

          {isGiven ? (
            <>
              <Field label="Dose actually given" htmlFor="adm-dose" required error={errors.dose} hint="Enter what was actually given.">
                <Input id="adm-dose" value={form.dose} onChange={(e) => set('dose')(e.target.value)} invalid={Boolean(errors.dose)} />
              </Field>
              <Field label="Route" htmlFor="adm-route" required error={errors.route}>
                <Select id="adm-route" value={form.route} onChange={(e) => set('route')(e.target.value)} invalid={Boolean(errors.route)}>
                  {MEDICATION_ROUTE_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </Select>
              </Field>
              {form.route === 'other' ? (
                <Field label="Route description" htmlFor="adm-route-detail" required error={errors.route_detail}>
                  <Input id="adm-route-detail" value={form.route_detail} onChange={(e) => set('route_detail')(e.target.value)} invalid={Boolean(errors.route_detail)} />
                </Field>
              ) : null}
            </>
          ) : null}

          <Field
            label={isGiven ? 'Notes' : 'Reason'}
            htmlFor="adm-notes"
            required={!isGiven}
            error={errors.notes}
            className={isGiven ? '' : 'sm:col-span-2'}
          >
            <Textarea id="adm-notes" rows={3} value={form.notes} onChange={(e) => set('notes')(e.target.value)} invalid={Boolean(errors.notes)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
