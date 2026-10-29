import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Button from '../ui/Button.jsx'
import ImageField from '../ui/ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = {
  title: '',
  title_km: '',
  slug: '',
  icon: '',
  short_description: '',
  short_description_km: '',
  description: '',
  description_km: '',
  is_featured: false,
  is_published: false,
  sort_order: 0,
}

const KM_HINT = 'Shown on the Khmer version of the website. Leave empty to reuse the English text.'

/** Blank Khmer fields are sent as null so the public site falls back to English. */
const kmValue = (value) => value.trim() || null

export default function ServiceFormModal({ open, onClose, onSaved, service }) {
  const editing = Boolean(service)
  const [form, setForm] = useState(EMPTY)
  const [image, setImage] = useState(null)
  const [removed, setRemoved] = useState(false)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setImage(null)
    setRemoved(false)
    setForm(
      service
        ? {
            title: service.title ?? '',
            title_km: service.title_km ?? '',
            slug: service.slug ?? '',
            icon: service.icon ?? '',
            short_description: service.short_description ?? '',
            short_description_km: service.short_description_km ?? '',
            description: service.description ?? '',
            description_km: service.description_km ?? '',
            is_featured: Boolean(service.is_featured),
            is_published: Boolean(service.is_published),
            sort_order: service.sort_order ?? 0,
          }
        : EMPTY,
    )
  }, [open, service])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    if (!form.title.trim()) {
      setErrors({ title: 'Title is required.' })
      return
    }
    setSaving(true)
    try {
      const payload = {
        ...form,
        title: form.title.trim(),
        title_km: kmValue(form.title_km),
        short_description_km: kmValue(form.short_description_km),
        description_km: kmValue(form.description_km),
      }
      if (image) payload.image = image
      else if (removed) payload.remove_image = true

      if (editing) {
        await websiteAdminApi.updateService(service.id, payload)
        toast.success('Service updated.')
      } else {
        await websiteAdminApi.createService(payload)
        toast.success('Service created.')
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
      title={editing ? 'Edit service' : 'New service'}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="service-form" loading={saving}>{editing ? 'Save changes' : 'Create service'}</Button>
        </>
      }
    >
      <form id="service-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{errors.form}</div>
        ) : null}
        <Field label="Title" htmlFor="service-title" required error={errors.title}>
          <Input id="service-title" value={form.title} onChange={(e) => set('title')(e.target.value)} invalid={Boolean(errors.title)} />
        </Field>
        <Field label="Title (Khmer)" htmlFor="service-title-km" hint={KM_HINT}>
          <Input id="service-title-km" lang="km" value={form.title_km} onChange={(e) => set('title_km')(e.target.value)} />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Slug" htmlFor="service-slug" hint="Optional.">
            <Input id="service-slug" value={form.slug} onChange={(e) => set('slug')(e.target.value)} />
          </Field>
          <Field label="Icon key" htmlFor="service-icon" hint="heart, stethoscope, brain, pill, lab, scan, syringe, baby, eye, activity, ambulance, cross">
            <Input id="service-icon" value={form.icon} onChange={(e) => set('icon')(e.target.value)} placeholder="heart" />
          </Field>
        </div>
        <Field label="Short description" htmlFor="service-short">
          <Textarea id="service-short" rows={2} value={form.short_description} onChange={(e) => set('short_description')(e.target.value)} />
        </Field>
        <Field label="Short description (Khmer)" htmlFor="service-short-km" hint={KM_HINT}>
          <Textarea id="service-short-km" lang="km" rows={2} value={form.short_description_km} onChange={(e) => set('short_description_km')(e.target.value)} />
        </Field>
        <Field label="Description" htmlFor="service-desc">
          <Textarea id="service-desc" rows={6} value={form.description} onChange={(e) => set('description')(e.target.value)} />
        </Field>
        <Field label="Description (Khmer)" htmlFor="service-desc-km" hint={KM_HINT}>
          <Textarea id="service-desc-km" lang="km" rows={6} value={form.description_km} onChange={(e) => set('description_km')(e.target.value)} />
        </Field>
        <Field label="Image" htmlFor="service-image">
          <ImageField
            id="service-image"
            file={image}
            existingUrl={removed ? null : service?.image?.url}
            onFile={(file) => { setImage(file); setRemoved(false) }}
            onRemove={() => { setImage(null); setRemoved(true) }}
          />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Sort order" htmlFor="service-order">
            <Input id="service-order" type="number" min="0" value={form.sort_order} onChange={(e) => set('sort_order')(Number(e.target.value))} />
          </Field>
          <Field label="Featured" htmlFor="service-featured">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input id="service-featured" type="checkbox" checked={form.is_featured} onChange={(e) => set('is_featured')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Show on homepage
            </label>
          </Field>
          <Field label="Status" htmlFor="service-published">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input id="service-published" type="checkbox" checked={form.is_published} onChange={(e) => set('is_published')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Published
            </label>
          </Field>
        </div>
      </form>
    </Modal>
  )
}
