import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Button from '../ui/Button.jsx'
import ImageField from './ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = { name: '', position: '', specialization: '', bio: '', email: '', phone: '', is_featured: false, is_published: false, sort_order: 0 }

export default function TeamFormModal({ open, onClose, onSaved, profile }) {
  const editing = Boolean(profile)
  const [form, setForm] = useState(EMPTY)
  const [photo, setPhoto] = useState(null)
  const [removed, setRemoved] = useState(false)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setPhoto(null)
    setRemoved(false)
    setForm(
      profile
        ? {
            name: profile.name ?? '',
            position: profile.position ?? '',
            specialization: profile.specialization ?? '',
            bio: profile.bio ?? '',
            email: profile.email ?? '',
            phone: profile.phone ?? '',
            is_featured: Boolean(profile.is_featured),
            is_published: Boolean(profile.is_published),
            sort_order: profile.sort_order ?? 0,
          }
        : EMPTY,
    )
  }, [open, profile])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.name.trim()) next.name = 'Name is required.'
    if (!form.position.trim()) next.position = 'Position is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = { ...form, name: form.name.trim(), position: form.position.trim() }
      if (photo) payload.photo = photo
      else if (removed) payload.remove_photo = true

      if (editing) {
        await websiteAdminApi.updateTeam(profile.id, payload)
        toast.success('Team profile updated.')
      } else {
        await websiteAdminApi.createTeam(payload)
        toast.success('Team profile created.')
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
      title={editing ? 'Edit team profile' : 'New team profile'}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="team-form" loading={saving}>{editing ? 'Save changes' : 'Create profile'}</Button>
        </>
      }
    >
      <form id="team-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{errors.form}</div>
        ) : null}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Full name" htmlFor="team-name" required error={errors.name}>
            <Input id="team-name" value={form.name} onChange={(e) => set('name')(e.target.value)} invalid={Boolean(errors.name)} />
          </Field>
          <Field label="Position" htmlFor="team-position" required error={errors.position}>
            <Input id="team-position" value={form.position} onChange={(e) => set('position')(e.target.value)} invalid={Boolean(errors.position)} placeholder="e.g. Consultant Cardiologist" />
          </Field>
          <Field label="Specialization" htmlFor="team-spec" className="sm:col-span-2">
            <Input id="team-spec" value={form.specialization} onChange={(e) => set('specialization')(e.target.value)} />
          </Field>
          <Field label="Email" htmlFor="team-email">
            <Input id="team-email" type="email" value={form.email} onChange={(e) => set('email')(e.target.value)} />
          </Field>
          <Field label="Phone" htmlFor="team-phone">
            <Input id="team-phone" value={form.phone} onChange={(e) => set('phone')(e.target.value)} />
          </Field>
        </div>
        <Field label="Bio" htmlFor="team-bio">
          <Textarea id="team-bio" rows={4} value={form.bio} onChange={(e) => set('bio')(e.target.value)} />
        </Field>
        <Field label="Photo" htmlFor="team-photo">
          <ImageField
            id="team-photo"
            file={photo}
            existingUrl={removed ? null : profile?.photo?.url}
            onFile={(file) => { setPhoto(file); setRemoved(false) }}
            onRemove={() => { setPhoto(null); setRemoved(true) }}
          />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Sort order" htmlFor="team-order">
            <Input id="team-order" type="number" min="0" value={form.sort_order} onChange={(e) => set('sort_order')(Number(e.target.value))} />
          </Field>
          <Field label="Featured" htmlFor="team-featured">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input id="team-featured" type="checkbox" checked={form.is_featured} onChange={(e) => set('is_featured')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Show on homepage
            </label>
          </Field>
          <Field label="Status" htmlFor="team-published">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input id="team-published" type="checkbox" checked={form.is_published} onChange={(e) => set('is_published')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Published
            </label>
          </Field>
        </div>
      </form>
    </Modal>
  )
}
