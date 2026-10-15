import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import { nursingTaskApi } from '../../api/nursing.js'
import { MEDICATION_ROUTE_OPTIONS, NURSING_TASK_OUTCOME_OPTIONS } from '../../utils/status.js'
import { patientFullName, formatDateTime } from '../../utils/format.js'

const CARE_OUTCOMES = new Set(['partially_completed', 'held', 'refused', 'not_completed'])

const VITAL_FIELDS = [
  { key: 'temperature', label: 'Temperature (°C)', step: '0.1' },
  { key: 'blood_pressure_systolic', label: 'BP systolic (mmHg)', step: '1' },
  { key: 'blood_pressure_diastolic', label: 'BP diastolic (mmHg)', step: '1' },
  { key: 'heart_rate', label: 'Heart rate (bpm)', step: '1' },
  { key: 'respiratory_rate', label: 'Respiratory rate', step: '1' },
  { key: 'oxygen_saturation', label: 'SpO₂ (%)', step: '0.1' },
  { key: 'weight', label: 'Weight (kg)', step: '0.1' },
  { key: 'height', label: 'Height (cm)', step: '0.1' },
]

const EMPTY_VITALS = {
  temperature: '',
  blood_pressure_systolic: '',
  blood_pressure_diastolic: '',
  heart_rate: '',
  respiratory_rate: '',
  oxygen_saturation: '',
  weight: '',
  height: '',
  symptoms: '',
  notes: '',
}

const EMPTY_MEDICATION = { outcome: 'administered', dose: '', route: 'oral', route_detail: '', notes: '' }

const EMPTY_CARE = { outcome: 'completed', reason: '', care_provided: '' }

const numberOrNull = (value) => (value === '' || value === null || value === undefined ? null : Number(value))
const textOrNull = (value) => (value && value.trim() ? value.trim() : null)

export default function NursingTaskCompleteModal({ open, task, onClose, onSaved }) {
  const [vitals, setVitals] = useState(EMPTY_VITALS)
  const [medication, setMedication] = useState(EMPTY_MEDICATION)
  const [care, setCare] = useState(EMPTY_CARE)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const detail = useQuery({
    queryKey: ['nursing-tasks', 'detail', task?.id],
    queryFn: () => nursingTaskApi.get(task.id),
    enabled: open && Boolean(task?.id),
  })

  useEffect(() => {
    if (!open) return
    setVitals(EMPTY_VITALS)
    setMedication(EMPTY_MEDICATION)
    setCare(EMPTY_CARE)
    setErrors({})
  }, [open, task?.id])

  useEffect(() => {
    const source = detail.data?.scheduled_dose ?? detail.data?.medication_administration
    if (!source) return
    setMedication((f) => ({
      ...f,
      dose: source.dose ?? '',
      route: source.route ?? 'oral',
      route_detail: source.route_detail ?? '',
    }))
  }, [detail.data])

  const setVital = (key) => (value) => setVitals((f) => ({ ...f, [key]: value }))
  const setMedicationField = (key) => (value) => setMedication((f) => ({ ...f, [key]: value }))
  const setCareField = (key) => (value) => setCare((f) => ({ ...f, [key]: value }))

  if (!task) return null

  const taskType = task.task_type
  const isVitalSigns = taskType === 'vital_signs'
  const isMedication = taskType === 'medication'
  const scheduledDose = detail.data?.scheduled_dose ?? null
  const doseStatus = scheduledDose?.status ?? null
  const hasActionableDose = Boolean(scheduledDose) && doseStatus === 'scheduled'
  const linkedDose = scheduledDose?.medicine_name ?? detail.data?.medication_administration?.medicine?.name ?? null
  const isGiven = medication.outcome === 'administered'

  const buildPayload = () => {
    if (isVitalSigns) {
      return {
        temperature: numberOrNull(vitals.temperature),
        blood_pressure_systolic: numberOrNull(vitals.blood_pressure_systolic),
        blood_pressure_diastolic: numberOrNull(vitals.blood_pressure_diastolic),
        heart_rate: numberOrNull(vitals.heart_rate),
        respiratory_rate: numberOrNull(vitals.respiratory_rate),
        oxygen_saturation: numberOrNull(vitals.oxygen_saturation),
        weight: numberOrNull(vitals.weight),
        height: numberOrNull(vitals.height),
        symptoms: textOrNull(vitals.symptoms),
        notes: textOrNull(vitals.notes),
      }
    }

    if (isMedication) {
      return {
        outcome: medication.outcome,
        dose: isGiven ? textOrNull(medication.dose) : null,
        route: isGiven ? medication.route : null,
        route_detail: isGiven && medication.route === 'other' ? textOrNull(medication.route_detail) : null,
        notes: textOrNull(medication.notes),
      }
    }

    return {
      outcome: care.outcome,
      reason: textOrNull(care.reason),
      care_provided: textOrNull(care.care_provided),
    }
  }

  const validate = () => {
    const next = {}
    if (isVitalSigns) {
      const hasMeasurement = VITAL_FIELDS.some((f) => vitals[f.key] !== '')
      if (!hasMeasurement) next.temperature = 'Enter at least one measurement you took.'
    } else if (isMedication) {
      if (!hasActionableDose) return next
      if (isGiven) {
        if (!medication.dose.trim()) next.dose = 'Actual dose given is required.'
        if (!medication.route) next.route = 'Administration route is required.'
        if (medication.route === 'other' && !medication.route_detail.trim()) next.route_detail = 'Describe the route you used.'
      } else if (!medication.notes.trim()) {
        next.notes = 'A reason is required when the dose is not administered.'
      }
    } else if (CARE_OUTCOMES.has(care.outcome) && !care.reason.trim()) {
      next.reason = 'A reason is required when care was not fully completed.'
    }
    return next
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = validate()
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      await nursingTaskApi.complete(task.id, buildPayload())
      toast.success('Task completed.')
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
      title={`Complete “${task.title}”`}
      description={patientFullName(task.patient)}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="task-complete-form" loading={saving} disabled={isMedication && !hasActionableDose}>Mark complete</Button>
        </>
      }
    >
      <form id="task-complete-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}

        {isVitalSigns ? (
          <>
            <p className="text-sm text-ink-muted">Record only the measurements you actually took. Leave blank anything you did not measure.</p>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              {VITAL_FIELDS.map((field) => (
                <Field key={field.key} label={field.label} htmlFor={`vital-${field.key}`} error={errors[field.key]}>
                  <Input
                    id={`vital-${field.key}`}
                    type="number"
                    step={field.step}
                    inputMode="decimal"
                    value={vitals[field.key]}
                    onChange={(e) => setVital(field.key)(e.target.value)}
                    invalid={Boolean(errors[field.key])}
                  />
                </Field>
              ))}
              <Field label="Symptoms" htmlFor="vital-symptoms" className="sm:col-span-2">
                <Textarea id="vital-symptoms" rows={2} value={vitals.symptoms} onChange={(e) => setVital('symptoms')(e.target.value)} />
              </Field>
              <Field label="Notes" htmlFor="vital-notes">
                <Textarea id="vital-notes" rows={2} value={vitals.notes} onChange={(e) => setVital('notes')(e.target.value)} />
              </Field>
            </div>
          </>
        ) : null}

        {isMedication ? (
          <>
            {hasActionableDose ? (
              <div className="rounded-lg border border-line bg-canvas px-3 py-2 text-sm">
                <p className="font-medium text-ink">
                  {linkedDose ?? 'Scheduled dose'}{scheduledDose?.dose ? ` · ${scheduledDose.dose}` : ''}
                </p>
                <p className="text-ink-muted">
                  Scheduled {formatDateTime(scheduledDose.scheduled_at)} · Route {scheduledDose.route ?? '—'}
                </p>
                <p className="mt-1 text-xs text-ink-muted">Recording this dose never changes pharmacy stock.</p>
              </div>
            ) : (
              <div role="alert" className="rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                {scheduledDose
                  ? `The linked dose is already recorded as ${doseStatus}. No scheduled dose is available to administer.`
                  : 'No scheduled dose is linked to this task. Schedule the dose on the MAR page first, then return here to record administration.'}
              </div>
            )}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field label="Outcome" htmlFor="med-outcome" required>
                <Select id="med-outcome" value={medication.outcome} onChange={(e) => setMedicationField('outcome')(e.target.value)} disabled={!hasActionableDose}>
                  <option value="administered">Administered</option>
                  <option value="held">Held</option>
                  <option value="refused">Refused</option>
                  <option value="missed">Missed</option>
                </Select>
              </Field>
              {isGiven ? (
                <>
                  <Field label="Dose actually given" htmlFor="med-dose" required error={errors.dose}>
                    <Input id="med-dose" value={medication.dose} onChange={(e) => setMedicationField('dose')(e.target.value)} invalid={Boolean(errors.dose)} disabled={!hasActionableDose} />
                  </Field>
                  <Field label="Route" htmlFor="med-route" required error={errors.route}>
                    <Select id="med-route" value={medication.route} onChange={(e) => setMedicationField('route')(e.target.value)} invalid={Boolean(errors.route)} disabled={!hasActionableDose}>
                      {MEDICATION_ROUTE_OPTIONS.map((o) => (
                        <option key={o.value} value={o.value}>{o.label}</option>
                      ))}
                    </Select>
                  </Field>
                  {medication.route === 'other' ? (
                    <Field label="Route description" htmlFor="med-route-detail" required error={errors.route_detail}>
                      <Input id="med-route-detail" value={medication.route_detail} onChange={(e) => setMedicationField('route_detail')(e.target.value)} invalid={Boolean(errors.route_detail)} disabled={!hasActionableDose} />
                    </Field>
                  ) : null}
                </>
              ) : null}
              <Field label={isGiven ? 'Notes' : 'Reason'} htmlFor="med-notes" required={!isGiven} error={errors.notes} className={isGiven ? '' : 'sm:col-span-2'}>
                <Textarea id="med-notes" rows={3} value={medication.notes} onChange={(e) => setMedicationField('notes')(e.target.value)} invalid={Boolean(errors.notes)} disabled={!hasActionableDose} />
              </Field>
            </div>
          </>
        ) : null}

        {!isVitalSigns && !isMedication ? (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Outcome" htmlFor="care-outcome" required>
              <Select id="care-outcome" value={care.outcome} onChange={(e) => setCareField('outcome')(e.target.value)}>
                {NURSING_TASK_OUTCOME_OPTIONS.map((o) => (
                  <option key={o.value} value={o.value}>{o.label}</option>
                ))}
              </Select>
            </Field>
            {CARE_OUTCOMES.has(care.outcome) ? (
              <Field label="Reason" htmlFor="care-reason" required error={errors.reason}>
                <Textarea id="care-reason" rows={3} value={care.reason} onChange={(e) => setCareField('reason')(e.target.value)} invalid={Boolean(errors.reason)} />
              </Field>
            ) : (
              <Field label="Care provided" htmlFor="care-provided" hint="Optional summary of what was done.">
                <Textarea id="care-provided" rows={3} value={care.care_provided} onChange={(e) => setCareField('care_provided')(e.target.value)} />
              </Field>
            )}
          </div>
        ) : null}
      </form>
    </Modal>
  )
}
