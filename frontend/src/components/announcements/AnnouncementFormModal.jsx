import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Select from '../ui/Select.jsx'
import Textarea from '../ui/Textarea.jsx'
import FileDropzone from '../ui/FileDropzone.jsx'
import { announcementApi } from '../../api/announcements.js'
import {
  ANNOUNCEMENT_AUDIENCE_OPTIONS,
  ANNOUNCEMENT_CATEGORY_OPTIONS,
  ANNOUNCEMENT_PRIORITY_OPTIONS,
} from '../../utils/status.js'

const EMPTY = {
  title: '',
  body: '',
  audience: 'all',
  category: 'general',
  priority: 'normal',
  mode: 'draft',
  scheduled_at: '',
  expires_at: '',
}

function initialMode(announcement) {
  if (!announcement) return 'draft'
  if (announcement.is_published) return 'now'
  if (announcement.published_at && new Date(announcement.published_at) > new Date()) return 'scheduled'
  return 'draft'
}

function localDateTimeInput(value) {
  const date = value ? new Date(value) : new Date(Date.now() + 60 * 60 * 1000)
  const offset = date.getTimezoneOffset() * 60_000
  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

export default function AnnouncementFormModal({ open, onClose, onSaved, announcement }) {
  const editing = Boolean(announcement)
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [posterFile, setPosterFile] = useState(null)
  const [posterRemoved, setPosterRemoved] = useState(false)
  const [posterPreview, setPosterPreview] = useState(null)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setPosterFile(null)
    setPosterRemoved(false)
    setForm(
      announcement
        ? {
            title: announcement.title ?? '',
            body: announcement.body ?? '',
            audience: announcement.audience ?? 'all',
            category: announcement.category ?? 'general',
            priority: announcement.priority ?? 'normal',
            mode: initialMode(announcement),
            scheduled_at: announcement.published_at ? localDateTimeInput(announcement.published_at) : localDateTimeInput(),
            expires_at: announcement.expires_at ? announcement.expires_at.slice(0, 10) : '',
          }
        : EMPTY,
    )
  }, [open, announcement])

  useEffect(() => {
    if (!posterFile) {
      setPosterPreview(null)
      return undefined
    }
    const url = URL.createObjectURL(posterFile)
    setPosterPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [posterFile])

  const existingPoster = editing && !posterRemoved ? announcement?.poster_url : null
  const shownPoster = posterPreview ?? existingPoster

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const handleSubmit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.title.trim()) next.title = 'Title is required.'
    if (!form.body.trim()) next.body = 'Message is required.'
    if (form.mode === 'scheduled' && !form.scheduled_at) next.scheduled_at = 'Choose a date and time.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        title: form.title.trim(),
        body: form.body.trim(),
        audience: form.audience,
        category: form.category,
        priority: form.priority,
        is_published: form.mode === 'now',
        published_at: form.mode === 'scheduled' ? new Date(form.scheduled_at).toISOString() : null,
        expires_at: form.expires_at || null,
      }
      if (posterFile) {
        payload.poster = posterFile
      } else if (posterRemoved) {
        payload.remove_poster = true
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

        <Field
          label="Poster image"
          htmlFor="announcement-poster"
          hint="Optional. Shown in the news feed and sent with Telegram notices."
        >
          {shownPoster ? (
            <div className="relative overflow-hidden rounded-2xl border border-line">
              <img src={shownPoster} alt="Announcement poster preview" className="h-44 w-full object-cover" />
              <button
                type="button"
                onClick={() => {
                  setPosterFile(null)
                  setPosterRemoved(true)
                }}
                className="absolute right-2 top-2 rounded-lg bg-black/60 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-black/80"
              >
                Remove
              </button>
            </div>
          ) : (
            <FileDropzone
              id="announcement-poster"
              value={posterFile}
              accept=".jpg,.jpeg,.png,.webp"
              hint="JPG, PNG or WEBP · up to 10MB"
              onSelect={(file) => {
                setPosterFile(file)
                if (file) setPosterRemoved(false)
              }}
            />
          )}
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

          <Field label="Category" htmlFor="announcement-category">
            <Select
              id="announcement-category"
              value={form.category}
              onChange={(e) => set('category')(e.target.value)}
            >
              {ANNOUNCEMENT_CATEGORY_OPTIONS.map((o) => (
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

          <Field label="Publishing" htmlFor="announcement-mode">
            <Select
              id="announcement-mode"
              value={form.mode}
              onChange={(e) => set('mode')(e.target.value)}
            >
              <option value="draft">Save as draft</option>
              <option value="now">Publish now</option>
              <option value="scheduled">Schedule for later</option>
            </Select>
          </Field>

          {form.mode === 'scheduled' ? (
            <Field label="Publish at" htmlFor="announcement-scheduled" required error={errors.scheduled_at}>
              <Input
                id="announcement-scheduled"
                type="datetime-local"
                value={form.scheduled_at}
                onChange={(e) => set('scheduled_at')(e.target.value)}
                invalid={Boolean(errors.scheduled_at)}
              />
            </Field>
          ) : null}

          <Field label="Expires on" htmlFor="announcement-expires" hint="Optional. Hide after this date.">
            <Input
              id="announcement-expires"
              type="date"
              value={form.expires_at}
              onChange={(e) => set('expires_at')(e.target.value)}
            />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
