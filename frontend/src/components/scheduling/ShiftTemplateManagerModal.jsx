import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Badge from '../ui/Badge.jsx'
import Toggle from '../ui/Toggle.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../ui/Table.jsx'
import ConfirmDialog from '../ui/ConfirmDialog.jsx'
import { RowSkeleton } from '../ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../ui/State.jsx'
import { shiftTemplateApi } from '../../api/shiftTemplates.js'
import { SHIFT_TYPES, statusBadgeFor } from '../../utils/status.js'
import { formatTime } from '../../utils/format.js'

const EMPTY = {
  name: '',
  type: 'day',
  start_time: '08:00',
  clock_in_cutoff: '08:00',
  end_time: '16:00',
  clock_out_opens_at: '',
  clock_out_closes_at: '',
  crosses_midnight: false,
  is_active: true,
  sort_order: 0,
}

export default function ShiftTemplateManagerModal({ open, onClose }) {
  const query = useQuery({
    queryKey: ['shift-templates'],
    queryFn: () => shiftTemplateApi.list(),
  })
  const templates = query.data ?? []

  const [showForm, setShowForm] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(null)

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const startCreate = () => {
    setEditingId(null)
    setForm({ ...EMPTY, sort_order: templates.length })
    setErrors({})
    setShowForm(true)
  }

  const startEdit = (template) => {
    setEditingId(template.id)
    setForm({
      name: template.name ?? '',
      type: template.type ?? 'day',
      start_time: (template.start_time ?? '').slice(0, 5),
      clock_in_cutoff: (template.clock_in_cutoff ?? '').slice(0, 5),
      end_time: (template.end_time ?? '').slice(0, 5),
      clock_out_opens_at: (template.clock_out_opens_at ?? '').slice(0, 5),
      clock_out_closes_at: (template.clock_out_closes_at ?? '').slice(0, 5),
      crosses_midnight: Boolean(template.crosses_midnight),
      is_active: Boolean(template.is_active),
      sort_order: template.sort_order ?? 0,
    })
    setErrors({})
    setShowForm(true)
  }

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Required.'
    for (const key of ['start_time', 'clock_in_cutoff', 'end_time']) {
      if (!/^\d{2}:\d{2}$/.test(form[key])) next[key] = 'Use HH:MM.'
    }
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        name: form.name.trim(),
        type: form.type,
        start_time: form.start_time,
        clock_in_cutoff: form.clock_in_cutoff,
        end_time: form.end_time,
        clock_out_opens_at: form.clock_out_opens_at || null,
        clock_out_closes_at: form.clock_out_closes_at || null,
        crosses_midnight: form.crosses_midnight,
        is_active: form.is_active,
        sort_order: Number(form.sort_order) || 0,
      }
      if (editingId) {
        await shiftTemplateApi.update(editingId, payload)
        toast.success('Shift template updated.')
      } else {
        await shiftTemplateApi.create(payload)
        toast.success('Shift template created.')
      }
      setShowForm(false)
      query.refetch()
    } catch (err) {
      setErrors({ form: err.message })
    } finally {
      setSaving(false)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await shiftTemplateApi.remove(deleting.id)
      toast.success('Shift template deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Shift templates"
      description="Reusable shift definitions with start, cutoff, and clock-out windows."
      size="xl"
      footer={<Button variant="outline" onClick={onClose}>Close</Button>}
    >
      <div className="space-y-4">
        {showForm ? (
          <form onSubmit={handleSubmit} className="space-y-4 rounded-xl border border-line p-4" noValidate>
            <p className="text-sm font-semibold text-ink">
              {editingId ? 'Edit template' : 'New template'}
            </p>
            {errors.form ? (
              <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                {errors.form}
              </div>
            ) : null}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <Field label="Name" htmlFor="tpl-name" required error={errors.name} className="sm:col-span-2 lg:col-span-1">
                <Input id="tpl-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
              </Field>
              <Field label="Type" htmlFor="tpl-type">
                <Select id="tpl-type" value={form.type} onChange={(e) => set('type')(e.target.value)}>
                  {Object.entries(SHIFT_TYPES).map(([value, m]) => (
                    <option key={value} value={value}>{m.label}</option>
                  ))}
                </Select>
              </Field>
              <Field label="Sort order" htmlFor="tpl-sort">
                <Input id="tpl-sort" type="number" min="0" max="255" value={form.sort_order} onChange={(e) => set('sort_order')(e.target.value)} />
              </Field>
              <Field label="Start time" htmlFor="tpl-start" required error={errors.start_time}>
                <Input id="tpl-start" type="time" value={form.start_time} onChange={(e) => set('start_time')(e.target.value)} invalid={Boolean(errors.start_time)} />
              </Field>
              <Field label="Clock-in cutoff" htmlFor="tpl-cutoff" required error={errors.clock_in_cutoff} hint="Scans after this time are rejected.">
                <Input id="tpl-cutoff" type="time" value={form.clock_in_cutoff} onChange={(e) => set('clock_in_cutoff')(e.target.value)} invalid={Boolean(errors.clock_in_cutoff)} />
              </Field>
              <Field label="End time" htmlFor="tpl-end" required error={errors.end_time}>
                <Input id="tpl-end" type="time" value={form.end_time} onChange={(e) => set('end_time')(e.target.value)} invalid={Boolean(errors.end_time)} />
              </Field>
              <Field label="Clock-out opens" htmlFor="tpl-out-open" hint="Optional.">
                <Input id="tpl-out-open" type="time" value={form.clock_out_opens_at} onChange={(e) => set('clock_out_opens_at')(e.target.value)} />
              </Field>
              <Field label="Clock-out closes" htmlFor="tpl-out-close" hint="Optional.">
                <Input id="tpl-out-close" type="time" value={form.clock_out_closes_at} onChange={(e) => set('clock_out_closes_at')(e.target.value)} />
              </Field>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <Toggle label="Crosses midnight" checked={form.crosses_midnight} onChange={set('crosses_midnight')} />
              <Toggle label="Active" checked={form.is_active} onChange={set('is_active')} />
            </div>
            <div className="flex gap-2">
              <Button type="submit" loading={saving}>{editingId ? 'Save changes' : 'Create template'}</Button>
              <Button type="button" variant="outline" onClick={() => setShowForm(false)} disabled={saving}>Cancel</Button>
            </div>
          </form>
        ) : (
          <Button onClick={startCreate}>
            <Plus className="size-4" aria-hidden /> Add template
          </Button>
        )}

        {query.isLoading ? (
          <RowSkeleton rows={4} cols={6} />
        ) : query.isError ? (
          <ErrorState title="Failed to load templates" message={query.error?.message} onRetry={() => query.refetch()} />
        ) : templates.length === 0 ? (
          <EmptyState title="No shift templates" message="Create a template to speed up scheduling." />
        ) : (
          <Table minWidth="min-w-[760px]">
            <THead>
              <Tr>
                <Th>Name</Th>
                <Th>Type</Th>
                <Th>Start</Th>
                <Th>Cutoff</Th>
                <Th>End</Th>
                <Th>Windows</Th>
                <Th>Active</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {templates.map((template) => {
                const typeMeta = statusBadgeFor(SHIFT_TYPES, template.type)
                return (
                  <Tr key={template.id}>
                    <Td primary className="font-medium text-ink">{template.name}</Td>
                    <Td><Badge tone={typeMeta.tone}>{typeMeta.label}</Badge></Td>
                    <Td leading className="whitespace-nowrap text-ink-muted">{formatTime(template.start_time)}</Td>
                    <Td className="whitespace-nowrap text-ink-muted">{formatTime(template.clock_in_cutoff)}</Td>
                    <Td className="whitespace-nowrap text-ink-muted">
                      {formatTime(template.end_time)}
                      {template.crosses_midnight ? <span className="ml-1 text-xs">(+1d)</span> : null}
                    </Td>
                    <Td className="whitespace-nowrap text-xs text-ink-muted">
                      {template.clock_out_opens_at || template.clock_out_closes_at
                        ? `${formatTime(template.clock_out_opens_at)} – ${formatTime(template.clock_out_closes_at)}`
                        : '—'}
                    </Td>
                    <Td trailing><Badge tone={template.is_active ? 'green' : 'gray'}>{template.is_active ? 'Active' : 'Inactive'}</Badge></Td>
                    <Td actions>
                      <div className="flex justify-end gap-1">
                        <button
                          type="button"
                          aria-label="Edit template"
                          title="Edit"
                          className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                          onClick={() => startEdit(template)}
                        >
                          <Pencil className="size-4" aria-hidden />
                        </button>
                        <button
                          type="button"
                          aria-label="Delete template"
                          title="Delete"
                          className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                          onClick={() => setDeleting(template)}
                        >
                          <Trash2 className="size-4" aria-hidden />
                        </button>
                      </div>
                    </Td>
                  </Tr>
                )
              })}
            </TBody>
          </Table>
        )}
      </div>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete shift template?"
        message="Shifts already created from this template are kept."
        confirmLabel="Delete"
      />
    </Modal>
  )
}
