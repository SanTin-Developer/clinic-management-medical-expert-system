import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import Toggle from '../ui/Toggle.jsx'
import { announcementApi } from '../../api/announcements.js'
import { ANNOUNCEMENT_AUDIENCE_OPTIONS, ANNOUNCEMENT_PRIORITY_OPTIONS } from '../../utils/status.js'

const EMPTY = {
  title: '',
  body: '',
  audience: 'all',
  priority: 'normal',
  is_published: false,
  expires_at: '',
}

export default function AnnouncementFormModal({ open, onClose, onSaved, announcement }) {
  const editing = Boolean(announcement)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setForm(
      announcement
        ? {
            title: announcement.title ?? '',
            body: announcement.body ?? '',
            audience: announcement.audience ?? 'all',
            priority: announcement.priority ?? 'normal',
            is_published: announcement.is_published ?? false,
            expires_at: announcement.expires_at ? announcement.expires_at.slice(0, 10) : '',
          }
        : EMPTY,
    )
  }, [open, announcement])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.title.trim()) next.title = 'Title is required.'
    if (!form.body.trim()) next.body = 'Message is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        title: form.title.trim(),
        body: form.body.trim(),
        audience: form.audience,
        priority: form.priority,
        is_published: form.is_published,
        expires_at: form.expires_at || null,
      }
      if (editing) {
        await announcementApi.update(announcement.id, payload)
        toast.success('Announcement updated.')
      } else {
        await announcementApi.create(payload)
        toast.success('Announcement created.')
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
      title={editing ? 'Edit announcement' : 'New announcement'}
      description="Publish a notice to a chosen audience."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="announcement-form" loading={saving}>
            {editing ? 'Save changes' : 'Create announcement'}
          </Button>
        </>
      }
    >
      <form id="announcement-form" onSubmit={handleSubmit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}

        <Field label="Title" htmlFor="announcement-title" required error={errors.title}>
          <Input
            id="announcement-title"
            value={form.title}
            onChange={(e) => set('title')(e.target.value)}
            invalid={Boolean(errors.title)}
            placeholder="e.g. Clinic closed on Friday"
          />
        </Field>

        <Field label="Message" htmlFor="announcement-body" required error={errors.body}>
          <Textarea
            id="announcement-body"
            rows={4}
            value={form.body}
            onChange={(e) => set('body')(e.target.value)}
            invalid={Boolean(errors.body)}
          />
        </Field>

        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Audience" htmlFor="announcement-audience">
            <Select
              id="announcement-audience"
              value={form.audience}
              onChange={(e) => set('audience')(e.target.value)}
            >
              {ANNOUNCEMENT_AUDIENCE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Priority" htmlFor="announcement-priority">
            <Select
              id="announcement-priority"
              value={form.priority}
              onChange={(e) => set('priority')(e.target.value)}
            >
              {ANNOUNCEMENT_PRIORITY_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>
                  {o.label}
                </option>
              ))}
            </Select>
          </Field>

          <Field label="Expires on" htmlFor="announcement-expires" hint="Optional. Hide after this date.">
            <Input
              id="announcement-expires"
              type="date"
              value={form.expires_at}
              onChange={(e) => set('expires_at')(e.target.value)}
            />
          </Field>

          <div className="flex items-end">
            <Toggle
              label="Publish now"
              checked={form.is_published}
              onChange={(v) => set('is_published')(v)}
              className="w-full pb-1"
            />
          </div>
        </div>
      </form>
    </Modal>
  )
}
