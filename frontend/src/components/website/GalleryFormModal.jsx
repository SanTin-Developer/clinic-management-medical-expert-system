import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Button from '../ui/Button.jsx'
import ImageField from './ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = { title: '', caption: '', category: '', is_published: false, sort_order: 0 }

export default function GalleryFormModal({ open, onClose, onSaved, item }) {
  const editing = Boolean(item)
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
      item
        ? {
            title: item.title ?? '',
            caption: item.caption ?? '',
            category: item.category ?? '',
            is_published: Boolean(item.is_published),
            sort_order: item.sort_order ?? 0,
          }
        : EMPTY,
    )
  }, [open, item])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    if (!editing && !image) {
      setErrors({ image: 'Please choose an image.' })
      return
    }
    setSaving(true)
    try {
      const payload = { ...form }
      if (image) payload.image = image
      else if (removed) payload.remove_image = true

      if (editing) {
        await websiteAdminApi.updateGallery(item.id, payload)
        toast.success('Gallery item updated.')
      } else {
        await websiteAdminApi.createGallery(payload)
        toast.success('Gallery item added.')
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
      title={editing ? 'Edit gallery item' : 'Add gallery item'}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="gallery-form" loading={saving}>{editing ? 'Save changes' : 'Add image'}</Button>
        </>
      }
    >
      <form id="gallery-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{errors.form}</div>
        ) : null}
        <Field label="Image" htmlFor="gallery-image" required={!editing} error={errors.image}>
          <ImageField
            id="gallery-image"
            file={image}
            existingUrl={removed ? null : item?.image?.url}
            onFile={(file) => { setImage(file); setRemoved(false); setErrors({}) }}
            onRemove={() => { setImage(null); setRemoved(true) }}
          />
        </Field>
        <Field label="Title" htmlFor="gallery-title">
          <Input id="gallery-title" value={form.title} onChange={(e) => set('title')(e.target.value)} />
        </Field>
        <Field label="Caption" htmlFor="gallery-caption">
          <Textarea id="gallery-caption" rows={2} value={form.caption} onChange={(e) => set('caption')(e.target.value)} />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Field label="Category" htmlFor="gallery-category" hint="e.g. Facilities">
            <Input id="gallery-category" value={form.category} onChange={(e) => set('category')(e.target.value)} />
          </Field>
          <Field label="Sort order" htmlFor="gallery-order">
            <Input id="gallery-order" type="number" min="0" value={form.sort_order} onChange={(e) => set('sort_order')(Number(e.target.value))} />
          </Field>
          <Field label="Status" htmlFor="gallery-published">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input id="gallery-published" type="checkbox" checked={form.is_published} onChange={(e) => set('is_published')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Published
            </label>
          </Field>
        </div>
      </form>
    </Modal>
  )
}
