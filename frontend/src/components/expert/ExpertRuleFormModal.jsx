import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Info, Plus, Trash2 } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import Toggle from '../ui/Toggle.jsx'
import { expertApi } from '../../api/expert.js'
import { useAll } from '../../hooks/usePaginated.js'

const defaultCondition = { symptom_id: '', operator: 'present', expected_value: true, condition_group: 1 }

export default function ExpertRuleFormModal({ open, onClose, initial, onSaved }) {
  const editing = Boolean(initial?.id)
  const [form, setForm] = useState(() => {
    if (!initial) {
      return { code: '', name: '', disease_id: '', explanation: '', priority: '', confidence_score: '', is_active: true, conditions: [{ ...defaultCondition }] }
    }
    return {
      code: initial.code ?? '',
      name: initial.name ?? '',
      disease_id: initial.disease_id ?? '',
      explanation: initial.explanation ?? '',
      priority: initial.priority ?? '',
      confidence_score: initial.confidence_score ?? '',
      is_active: Boolean(initial.is_active),
      conditions: (initial.conditions ?? []).map((c) => ({
        symptom_id: c.symptom_id ?? '',
        operator: c.operator ?? 'present',
        expected_value: c.expected_value ?? true,
        condition_group: c.condition_group ?? 1,
      })),
    }
  })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  const diseases = useAll(['diseases', 'all'], (params) => expertApi.diseases(params))
  const symptoms = useAll(['symptoms', 'all'], (params) => expertApi.symptoms(params))
  const diseaseOptions = useMemo(() => (diseases.data ?? []).filter((d) => d.is_active), [diseases.data])
  const symptomOptions = useMemo(() => (symptoms.data ?? []).filter((s) => s.is_active), [symptoms.data])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const setCondition = (index, key, value) =>
    setForm((f) => ({
      ...f,
      conditions: f.conditions.map((c, i) => (i === index ? { ...c, [key]: value } : c)),
    }))

  const runRuleSubmit = async () => {
    const next = {}
    if (!form.name.trim()) next.name = 'Required.'
    if (!form.disease_id) next.disease_id = 'Select a disease.'
    if (editing && !form.code) next.code = 'Required.'
    const cleanConditions = form.conditions
      .filter((c) => c.symptom_id)
      .map((c) => ({
        symptom_id: c.symptom_id,
        operator: c.operator,
        expected_value: Boolean(c.expected_value),
        condition_group: Number(c.condition_group) || 1,
      }))
    if (cleanConditions.length === 0) next.conditions = 'Add at least one condition.'
    if (Number(form.confidence_score) > 1 || Number(form.confidence_score) < 0) next.confidence_score = 'Between 0 and 1.'
    if (Number(form.priority) < 0) next.priority = '0 or greater.'
    setErrors(next)
    if (Object.keys(next).length) return next

    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        disease_id: Number(form.disease_id),
        explanation: form.explanation.trim() || null,
        priority: form.priority === '' ? 0 : Number(form.priority),
        confidence_score: form.confidence_score === '' ? 0.5 : Number(form.confidence_score),
        is_active: form.is_active,
        conditions: cleanConditions,
      }
      if (!editing) payload.code = form.code.trim()
      if (editing) {
        await expertApi.updateExpertRule(initial.id, payload)
        toast.success('Expert rule updated.')
      } else {
        await expertApi.createExpertRule(payload)
        toast.success('Expert rule created.')
      }
      onSaved?.()
      onClose()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
    return null
  }

  const submit = (event) => {
    event.preventDefault()
    runRuleSubmit()
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? `Edit rule: ${initial.code}` : 'New expert rule'}
      description="A rule the engine uses to suggest diseases from symptoms."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="rule-form" loading={saving}>
            {editing ? 'Save changes' : 'Create rule'}
          </Button>
        </>
      }
    >
      <form id="rule-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Code" htmlFor="r-code" required={!editing} error={errors.code} hint={editing ? `Current: ${initial.code}` : 'Auto-generated if blank.'}>
            <Input id="r-code" value={form.code} onChange={(e) => set('code')(e.target.value)} invalid={Boolean(errors.code)} placeholder="e.g. RULE-FLU-001" />
          </Field>
          <Field label="Name" htmlFor="r-name" required error={errors.name}>
            <Input id="r-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
          </Field>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Disease" htmlFor="r-disease" required error={errors.disease_id} className="sm:col-span-2">
            <Select id="r-disease" value={form.disease_id} onChange={(e) => set('disease_id')(e.target.value)} invalid={Boolean(errors.disease_id)}>
              <option value="">Select…</option>
              {diseaseOptions.map((d) => (
                <option key={d.id} value={d.id}>
                  {d.code} — {d.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Priority" htmlFor="r-priority" error={errors.priority} hint="Lower = checked later.">
            <Input id="r-priority" type="number" min="0" value={form.priority} onChange={(e) => set('priority')(e.target.value)} />
          </Field>
        </div>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Confidence score" htmlFor="r-confidence" error={errors.confidence_score} hint="Decimal between 0 and 1.">
            <Input id="r-confidence" type="number" step="0.01" min="0" max="1" value={form.confidence_score} onChange={(e) => set('confidence_score')(e.target.value)} />
          </Field>
          <div className="flex items-end pb-1">
            <Toggle label="Active rule" checked={form.is_active} onChange={(v) => set('is_active')(v)} />
          </div>
        </div>
        <Field label="Explanation" htmlFor="r-explanation">
          <Textarea id="r-explanation" rows={2} value={form.explanation} onChange={(e) => set('explanation')(e.target.value)} />
        </Field>

        <div>
          <div className="mb-2 flex items-center justify-between">
            <span className="text-sm font-semibold text-ink">Conditions</span>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => setForm((f) => ({ ...f, conditions: [...f.conditions, { ...defaultCondition }] }))}
              disabled={saving}
            >
              <Plus className="size-4" aria-hidden /> Add condition
            </Button>
          </div>
          <p className="mb-2 flex items-start gap-1.5 text-xs text-ink-muted">
            <Info className="mt-0.5 size-3.5 shrink-0" aria-hidden />
            Conditions in the same group are ANDed; groups are ORed. A rule matches when any group is satisfied.
          </p>
          {errors.conditions ? (
            <p role="alert" className="mb-2 text-sm font-medium text-red-600">
              {errors.conditions}
            </p>
          ) : null}
          <div className="space-y-3">
            {form.conditions.map((cond, idx) => (
              <div key={idx} className="grid grid-cols-2 gap-3 rounded-xl border border-line p-3 sm:grid-cols-[1fr_150px_100px_auto]">
                <Field label="Symptom" htmlFor={`r-cond-${idx}-symptom`} className="col-span-2 sm:col-span-1">
                  <Select id={`r-cond-${idx}-symptom`} value={cond.symptom_id} onChange={(e) => setCondition(idx, 'symptom_id', e.target.value)}>
                    <option value="">Select…</option>
                    {symptomOptions.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name}
                      </option>
                    ))}
                  </Select>
                </Field>
                <Field label="Operator" htmlFor={`r-cond-${idx}-op`}>
                  <Select id={`r-cond-${idx}-op`} value={cond.operator} onChange={(e) => setCondition(idx, 'operator', e.target.value)}>
                    <option value="present">present</option>
                    <option value="absent">absent</option>
                  </Select>
                </Field>
                <Field label="Expected" htmlFor={`r-cond-${idx}-exp`}>
                  <Select id={`r-cond-${idx}-exp`} value={cond.expected_value ? 'true' : 'false'} onChange={(e) => setCondition(idx, 'expected_value', e.target.value === 'true')}>
                    <option value="true">true</option>
                    <option value="false">false</option>
                  </Select>
                </Field>
                <Field label="Group" htmlFor={`r-cond-${idx}-grp`}>
                  <Input id={`r-cond-${idx}-grp`} type="number" min="1" value={cond.condition_group} onChange={(e) => setCondition(idx, 'condition_group', e.target.value)} className="min-w-0" />
                </Field>
                <div className="col-span-2 flex justify-end sm:col-span-4">
                  <button
                    type="button"
                    aria-label="Remove condition"
                    className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                    onClick={() => setForm((f) => ({ ...f, conditions: f.conditions.filter((_, i) => i !== idx) }))}
                    disabled={saving || form.conditions.length === 1}
                  >
                    <Trash2 className="size-4" aria-hidden />
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </form>
    </Modal>
  )
}