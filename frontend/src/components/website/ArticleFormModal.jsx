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
  category: '',
  tags: '',
  status: 'draft',
  is_featured: false,
}

const KM_HINT = 'Shown on the Khmer version of the website. Leave empty to reuse the English text.'

/** Blank Khmer fields are sent as null so the public site falls back to English. */
const kmValue = (value) => value.trim() || null

export default function ArticleFormModal({ open, onClose, onSaved, article, categories = [] }) {
  const editing = Boolean(article)
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
      article
        ? {
            title: article.title ?? '',
            title_km: article.title_km ?? '',
            slug: article.slug ?? '',
            excerpt: article.excerpt ?? '',
            excerpt_km: article.excerpt_km ?? '',
            body: article.body ?? '',
            body_km: article.body_km ?? '',
            category: article.category ?? '',
            tags: Array.isArray(article.tags) ? article.tags.join(', ') : '',
            status: article.status ?? 'draft',
            is_featured: Boolean(article.is_featured),
          }
        : EMPTY,
    )
  }, [open, article])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    const next = {}
    if (!form.title.trim()) next.title = 'Title is required.'
    if (!form.body.trim()) next.body = 'Body is required.'
    setErrors(next)
    if (Object.keys(next).length) return

    setSaving(true)
    try {
      const payload = {
        title: form.title.trim(),
        title_km: kmValue(form.title_km),
        slug: form.slug,
        excerpt: form.excerpt,
        excerpt_km: kmValue(form.excerpt_km),
        body: form.body,
        body_km: kmValue(form.body_km),
        category: form.category,
        status: form.status,
        is_featured: form.is_featured,
        tags: form.tags.split(',').map((t) => t.trim()).filter(Boolean),
      }
      if (cover) payload.cover = cover
      else if (removed) payload.remove_cover = true

      if (editing) {
        await websiteAdminApi.updateArticle(article.id, payload)
        toast.success('Article updated.')
      } else {
        await websiteAdminApi.createArticle(payload)
        toast.success('Article created.')
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
      title={editing ? 'Edit article' : 'New article'}
      size="lg"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" form="article-form" loading={saving}>{editing ? 'Save changes' : 'Create article'}</Button>
        </>
      }
    >
      <form id="article-form" onSubmit={submit} className="space-y-4" noValidate>
        {errors.form ? (
          <div role="alert" className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{errors.form}</div>
        ) : null}
        <Field label="Title" htmlFor="article-title" required error={errors.title}>
          <Input id="article-title" value={form.title} onChange={(e) => set('title')(e.target.value)} invalid={Boolean(errors.title)} />
        </Field>
        <Field label="Title (Khmer)" htmlFor="article-title-km" hint={KM_HINT}>
          <Input id="article-title-km" lang="km" value={form.title_km} onChange={(e) => set('title_km')(e.target.value)} />
        </Field>
        <Field label="Slug" htmlFor="article-slug" hint="Leave blank to generate from the title.">
          <Input id="article-slug" value={form.slug} onChange={(e) => set('slug')(e.target.value)} />
        </Field>
        <Field label="Excerpt" htmlFor="article-excerpt" hint="Short summary shown in listings.">
          <Textarea id="article-excerpt" rows={2} value={form.excerpt} onChange={(e) => set('excerpt')(e.target.value)} />
        </Field>
        <Field label="Excerpt (Khmer)" htmlFor="article-excerpt-km" hint={KM_HINT}>
          <Textarea id="article-excerpt-km" lang="km" rows={2} value={form.excerpt_km} onChange={(e) => set('excerpt_km')(e.target.value)} />
        </Field>
        <Field label="Body" htmlFor="article-body" required error={errors.body}>
          <Textarea id="article-body" rows={8} value={form.body} onChange={(e) => set('body')(e.target.value)} invalid={Boolean(errors.body)} />
        </Field>
        <Field label="Body (Khmer)" htmlFor="article-body-km" hint={KM_HINT}>
          <Textarea id="article-body-km" lang="km" rows={8} value={form.body_km} onChange={(e) => set('body_km')(e.target.value)} />
        </Field>
        <Field label="Cover image" htmlFor="article-cover">
          <ImageField
            id="article-cover"
            file={cover}
            existingUrl={removed ? null : article?.cover?.url}
            onFile={(file) => { setCover(file); setRemoved(false) }}
            onRemove={() => { setCover(null); setRemoved(true) }}
          />
        </Field>
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <Field label="Category" htmlFor="article-category">
            <Input
              id="article-category"
              value={form.category}
              onChange={(e) => set('category')(e.target.value)}
              list="article-categories"
            />
            <datalist id="article-categories">
              {categories.map((cat) => (
                <option key={cat} value={cat} />
              ))}
            </datalist>
          </Field>
          <Field label="Tags" htmlFor="article-tags" hint="Comma separated.">
            <Input id="article-tags" value={form.tags} onChange={(e) => set('tags')(e.target.value)} placeholder="wellness, prevention" />
          </Field>
          <Field label="Status" htmlFor="article-status">
            <Select id="article-status" value={form.status} onChange={(e) => set('status')(e.target.value)}>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
            </Select>
          </Field>
          <Field label="Featured" htmlFor="article-featured">
            <label className="flex h-10 items-center gap-2 rounded-lg border border-line bg-surface px-3 text-sm text-ink">
              <input id="article-featured" type="checkbox" checked={form.is_featured} onChange={(e) => set('is_featured')(e.target.checked)} className="size-4 accent-[var(--color-brand)]" />
              Feature on homepage
            </label>
          </Field>
        </div>
      </form>
    </Modal>
  )
}
