import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import Modal from '../ui/Modal.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Textarea from '../ui/Textarea.jsx'
import Select from '../ui/Select.jsx'
import Button from '../ui/Button.jsx'
import ImageField from './ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = { title: '', slug: '', excerpt: '', body: '', status: 'draft', show_in_footer: false, seo_title: '', seo_description: '' }

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
            slug: page.slug ?? '',
            excerpt: page.excerpt ?? '',
            body: page.body ?? '',
            status: page.status ?? 'draft',
            show_in_footer: Boolean(page.show_in_footer),
            seo_title: page.seo_title ?? '',
            seo_description: page.seo_description ?? '',
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
      const payload = { ...form, title: form.title.trim() }
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
        <Field label="Slug" htmlFor="page-slug" hint="Leave blank to generate from the title.">
          <Input id="page-slug" value={form.slug} onChange={(e) => set('slug')(e.target.value)} placeholder="about-us" />
        </Field>
        <Field label="Excerpt" htmlFor="page-excerpt">
          <Textarea id="page-excerpt" rows={2} value={form.excerpt} onChange={(e) => set('excerpt')(e.target.value)} />
        </Field>
        <Field label="Body" htmlFor="page-body">
          <Textarea id="page-body" rows={8} value={form.body} onChange={(e) => set('body')(e.target.value)} />
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
          <Field label="SEO description" htmlFor="page-seo-description">
            <Input id="page-seo-description" value={form.seo_description} onChange={(e) => set('seo_description')(e.target.value)} />
          </Field>
        </div>
      </form>
    </Modal>
  )
}
