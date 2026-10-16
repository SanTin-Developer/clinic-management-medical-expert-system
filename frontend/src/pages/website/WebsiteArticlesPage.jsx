import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { useQuery } from '@tanstack/react-query'
import { EyeOff, Globe, Newspaper, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import StatCard from '../../components/ui/StatCard.jsx'
import Modal from '../../components/ui/Modal.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import Textarea from '../../components/ui/Textarea.jsx'
import Select from '../../components/ui/Select.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import Table, { TBody, Td, Th, THead, Tr } from '../../components/ui/Table.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import ImageField from '../../components/website/ImageField.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { publicWebsiteApi } from '../../api/website.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatDate } from '../../utils/format.js'

const EMPTY = { title: '', slug: '', excerpt: '', body: '', category: '', tags: '', status: 'draft', is_featured: false }

function ArticleFormModal({ open, onClose, onSaved, article, categories }) {
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
            slug: article.slug ?? '',
            excerpt: article.excerpt ?? '',
            body: article.body ?? '',
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
        slug: form.slug,
        excerpt: form.excerpt,
        body: form.body,
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
        <Field label="Slug" htmlFor="article-slug" hint="Leave blank to generate from the title.">
          <Input id="article-slug" value={form.slug} onChange={(e) => set('slug')(e.target.value)} />
        </Field>
        <Field label="Excerpt" htmlFor="article-excerpt" hint="Short summary shown in listings.">
          <Textarea id="article-excerpt" rows={2} value={form.excerpt} onChange={(e) => set('excerpt')(e.target.value)} />
        </Field>
        <Field label="Body" htmlFor="article-body" required error={errors.body}>
          <Textarea id="article-body" rows={8} value={form.body} onChange={(e) => set('body')(e.target.value)} invalid={Boolean(errors.body)} />
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

export default function WebsiteArticlesPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [category, setCategory] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'articles', { search, status, category }],
    (params) =>
      websiteAdminApi.listArticles({
        ...params,
        search: search || undefined,
        status: status || undefined,
        category: category || undefined,
      }),
    { page },
  )

  const rows = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const feedQuery = useQuery({
    queryKey: ['website', 'article-categories'],
    queryFn: () => publicWebsiteApi.listArticles({ page: 1 }),
  })
  const categories = feedQuery.data?.categories ?? []

  const togglePublish = async (row) => {
    try {
      if (row.status === 'published') {
        await websiteAdminApi.unpublishArticle(row.id)
        toast.success('Article unpublished.')
      } else {
        await websiteAdminApi.publishArticle(row.id)
        toast.success('Article published.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteArticle(deleting.id)
      toast.success('Article deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <PageHeader
        title="News & Health"
        description="Articles published to the public website."
        actions={
          can('website.articles.manage') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden />
              New article
            </Button>
          ) : null
        }
      />

      <div className="mb-5 grid gap-4 sm:grid-cols-3">
        <StatCard label="Total articles" value={stats.total ?? 0} icon={Newspaper} tone="brand" loading={query.isLoading} />
        <StatCard label="Published" value={stats.published ?? 0} icon={Globe} tone="green" loading={query.isLoading} />
        <StatCard label="Drafts" value={stats.draft ?? 0} icon={EyeOff} tone="amber" loading={query.isLoading} />
      </div>

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search articles…" className="sm:max-w-xs" />
          <div className="flex gap-2">
            <Input
              value={category}
              onChange={(e) => { setCategory(e.target.value); setPage(1) }}
              placeholder="Category"
              className="sm:w-40"
              aria-label="Filter by category"
            />
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="sm:w-40" aria-label="Filter by status">
              <option value="">All statuses</option>
              <option value="published">Published</option>
              <option value="draft">Draft</option>
            </Select>
          </div>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={4} /></div>
        ) : query.isError ? (
          <div className="p-5"><ErrorState onRetry={query.refetch} /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="No articles yet" message="Write your first article for the public website." icon={Newspaper} />
        ) : (
          <div className="p-4">
            <Table>
              <THead>
                <Tr>
                  <Th>Article</Th>
                  <Th>Category</Th>
                  <Th>Status</Th>
                  <Th>Views</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </THead>
              <TBody>
                {rows.map((row) => (
                  <Tr key={row.id}>
                    <Td>
                      <div className="flex items-center gap-3">
                        {row.cover?.url ? (
                          <img src={row.cover.url} alt="" className="size-10 rounded-lg object-cover" />
                        ) : (
                          <span className="grid size-10 place-items-center rounded-lg bg-brand-soft text-brand">
                            <Newspaper className="size-4" aria-hidden />
                          </span>
                        )}
                        <div>
                          <div className="flex items-center gap-2 font-medium text-ink">
                            {row.title}
                            {row.is_featured ? <Sparkles className="size-3.5 text-brand" aria-hidden /> : null}
                          </div>
                          <div className="text-xs text-ink-muted">{row.published_at ? formatDate(row.published_at) : 'Not published'}</div>
                        </div>
                      </div>
                    </Td>
                    <Td className="text-ink-muted">{row.category || '—'}</Td>
                    <Td><Badge tone={row.status === 'published' ? 'green' : 'gray'}>{row.status}</Badge></Td>
                    <Td className="text-ink-muted">{row.views ?? 0}</Td>
                    <Td>
                      <div className="flex items-center justify-end gap-1">
                        {can('website.articles.publish') ? (
                          <Button size="sm" variant={row.status === 'published' ? 'outline' : 'secondary'} onClick={() => togglePublish(row)}>
                            {row.status === 'published' ? 'Unpublish' : 'Publish'}
                          </Button>
                        ) : null}
                        {can('website.articles.manage') ? (
                          <>
                            <Button size="sm" variant="ghost" onClick={() => { setEditing(row); setFormOpen(true) }} aria-label="Edit">
                              <Pencil className="size-4" aria-hidden />
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setDeleting(row)} aria-label="Delete">
                              <Trash2 className="size-4" aria-hidden />
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          </div>
        )}

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <ArticleFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={query.refetch} article={editing} categories={categories} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete article"
        message={`Delete “${deleting?.title}”? This cannot be undone.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
