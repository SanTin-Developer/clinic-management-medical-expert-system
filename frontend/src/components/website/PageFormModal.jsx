import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Select from '../ui/Select.jsx'
import Button from '../ui/Button.jsx'
import ImageField from '../ui/ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = {
  title: '',
  title_km: '',
  slug: '',
  excerpt: '',
  excerpt_km: '',
  body: '',
  body_km: '',
  status: 'draft',
  show_in_footer: false,
  seo_title: '',
  seo_title_km: '',
  seo_description: '',
  seo_description_km: '',
}

const KM_HINT = 'Shown on the Khmer version of the website. Leave empty to reuse the English text.'

/** Blank Khmer fields are sent as null so the public site falls back to English. */
const kmValue = (value) => value.trim() || null

export default function PageFormModal({ open, onClose, onSaved, page }) {
  const editing = Boolean(page)
  const [form, setForm] = useState(EMPTY)
  const [cover, setCover] = useState(null)
  const [removed, setRemoved] = useState(false)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!open) return
    setErrors({})
    setCover(null)
    setRemoved(false)
    setForm(
      page
        ? {
            title: page.title ?? '',
            title_km: page.title_km ?? '',
            slug: page.slug ?? '',
            excerpt: page.excerpt ?? '',
            excerpt_km: page.excerpt_km ?? '',
            body: page.body ?? '',
            body_km: page.body_km ?? '',
            status: page.status ?? 'draft',
            show_in_footer: Boolean(page.show_in_footer),
            seo_title: page.seo_title ?? '',
            seo_title_km: page.seo_title_km ?? '',
            seo_description: page.seo_description ?? '',
            seo_description_km: page.seo_description_km ?? '',
          }
        : EMPTY,
    )
  }, [open, page])

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
        excerpt_km: kmValue(form.excerpt_km),
        body_km: kmValue(form.body_km),
        seo_title_km: kmValue(form.seo_title_km),
        seo_description_km: kmValue(form.seo_description_km),
      }
      if (cover) payload.cover = cover
      else if (removed) payload.remove_cover = true

      if (editing) {
        await websiteAdminApi.updatePage(page.id, payload)
        toast.success('Page updated.')
      } else {
        await websiteAdminApi.createPage(payload)
        toast.success('Page created.')
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
      title={editing ? 'Edit page' : 'New page'}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button type="submit" form="page-form" loading={saving}>
            {editing ? 'Save changes' : 'Create page'}
          </Button>
        </>
      }
    >
      <form id="page-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
            {errors.form}
          </div>
        ) : null}
        <Field label="Title" htmlFor="page-title" required error={errors.title}>
          <Input id="page-title" value={form.title} onChange={(e) => set('title')(e.target.value)} invalid={Boolean(errors.title)} />
        </Field>
        <Field label="Title (Khmer)" htmlFor="page-title-km" hint={KM_HINT}>
          <Input id="page-title-km" lang="km" value={form.title_km} onChange={(e) => set('title_km')(e.target.value)} />
        </Field>
        <Field label="Slug" htmlFor="page-slug" hint="Leave blank to generate from the title.">
          <Input id="page-slug" value={form.slug} onChange={(e) => set('slug')(e.target.value)} placeholder="about-us" />
        </Field>
        <Field label="Excerpt" htmlFor="page-excerpt">
          <Textarea id="page-excerpt" rows={2} value={form.excerpt} onChange={(e) => set('excerpt')(e.target.value)} />
        </Field>
        <Field label="Excerpt (Khmer)" htmlFor="page-excerpt-km" hint={KM_HINT}>
          <Textarea id="page-excerpt-km" lang="km" rows={2} value={form.excerpt_km} onChange={(e) => set('excerpt_km')(e.target.value)} />
        </Field>
        <Field label="Body" htmlFor="page-body">
          <Textarea id="page-body" rows={8} value={form.body} onChange={(e) => set('body')(e.target.value)} />
        </Field>
        <Field label="Body (Khmer)" htmlFor="page-body-km" hint={KM_HINT}>
          <Textarea id="page-body-km" lang="km" rows={8} value={form.body_km} onChange={(e) => set('body_km')(e.target.value)} />
        </Field>
        <Field label="Cover image" htmlFor="page-cover">
          <ImageField
            id="page-cover"
            file={cover}
            existingUrl={removed ? null : page?.cover?.url}
            onFile={(file) => {
              setCover(file)
              setRemoved(false)
            }}
            onRemove={() => {
              setCover(null)
              setRemoved(true)
            }}
          />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Status" htmlFor="page-status">
            <Select id="page-status" value={form.status} onChange={(e) => set('status')(e.target.value)}>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
            </Select>
          </Field>
          <Field label="Footer link" htmlFor="page-footer">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input type="checkbox" checked={form.show_in_footer} onChange={(e) => set('show_in_footer')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Show in footer
            </label>
          </Field>
          <Field label="SEO title" htmlFor="page-seo-title">
            <Input id="page-seo-title" value={form.seo_title} onChange={(e) => set('seo_title')(e.target.value)} />
          </Field>
          <Field label="SEO title (Khmer)" htmlFor="page-seo-title-km" hint={KM_HINT}>
            <Input id="page-seo-title-km" lang="km" value={form.seo_title_km} onChange={(e) => set('seo_title_km')(e.target.value)} />
          </Field>
          <Field label="SEO description" htmlFor="page-seo-description">
            <Input id="page-seo-description" value={form.seo_description} onChange={(e) => set('seo_description')(e.target.value)} />
          </Field>
          <Field label="SEO description (Khmer)" htmlFor="page-seo-description-km" hint={KM_HINT}>
            <Input id="page-seo-description-km" lang="km" value={form.seo_description_km} onChange={(e) => set('seo_description_km')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
