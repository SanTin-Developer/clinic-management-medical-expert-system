import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Plus, Pencil, Trash2, Globe, EyeOff, FileText } from 'lucide-react'
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
import { websiteAdminApi } from '../../api/websiteAdmin.js'

function PageFormModal({ open, onClose, onSaved, page }) {
  const editing = Boolean(page)
  const [form, setForm] = useState({ title: '', slug: '', excerpt: '', body: '', status: 'draft', show_in_footer: false, seo_title: '', seo_description: '' })
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
        : { title: '', slug: '', excerpt: '', body: '', status: 'draft', show_in_footer: false, seo_title: '', seo_description: '' },
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

export default function WebsitePagesPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'pages', { search, status }],
    (params) => websiteAdminApi.listPages({ ...params, search: search || undefined, status: status || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const togglePublish = async (row) => {
    try {
      if (row.status === 'published') {
        await websiteAdminApi.unpublishPage(row.id)
        toast.success('Page unpublished.')
      } else {
        await websiteAdminApi.publishPage(row.id)
        toast.success('Page published.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deletePage(deleting.id)
      toast.success('Page deleted.')
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
        title="Pages"
        description="Manage informational pages such as About, Privacy Policy and Terms of Use."
        actions={
          can('website.pages.manage') ? (
            <Button
              onClick={() => {
                setEditing(null)
                setFormOpen(true)
              }}
            >
              <Plus className="size-4" aria-hidden />
              New page
            </Button>
          ) : null
        }
      />

      <div className="mb-5 grid gap-4 sm:grid-cols-3">
        <StatCard label="Total pages" value={stats.total ?? 0} icon={FileText} tone="brand" loading={query.isLoading} />
        <StatCard label="Published" value={stats.published ?? 0} icon={Globe} tone="green" loading={query.isLoading} />
        <StatCard label="Drafts" value={stats.draft ?? 0} icon={EyeOff} tone="amber" loading={query.isLoading} />
      </div>

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search pages…" className="sm:max-w-xs" />
          <Select
            value={status}
            onChange={(e) => { setStatus(e.target.value); setPage(1) }}
            className="sm:w-40"
            aria-label="Filter by status"
          >
            <option value="">All statuses</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={5} cols={4} />
          </div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState onRetry={query.refetch} />
          </div>
        ) : rows.length === 0 ? (
          <EmptyState title="No pages yet" message="Create your first informational page." icon={FileText} />
        ) : (
          <div className="p-4">
            <Table>
              <THead>
                <Tr>
                  <Th>Title</Th>
                  <Th>Status</Th>
                  <Th>Footer</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </THead>
              <TBody>
                {rows.map((row) => (
                  <Tr key={row.id}>
                    <Td>
                      <div className="font-medium text-ink">{row.title}</div>
                      <div className="text-xs text-ink-muted">/{row.slug}{row.is_system ? ' · system' : ''}</div>
                    </Td>
                    <Td>
                      <Badge tone={row.status === 'published' ? 'green' : 'gray'}>{row.status}</Badge>
                    </Td>
                    <Td className="text-ink-muted">{row.show_in_footer ? 'Yes' : '—'}</Td>
                    <Td>
                      <div className="flex items-center justify-end gap-1">
                        {can('website.pages.publish') ? (
                          <Button size="sm" variant={row.status === 'published' ? 'outline' : 'secondary'} onClick={() => togglePublish(row)}>
                            {row.status === 'published' ? 'Unpublish' : 'Publish'}
                          </Button>
                        ) : null}
                        {can('website.pages.manage') ? (
                          <>
                            <Button size="sm" variant="ghost" onClick={() => { setEditing(row); setFormOpen(true) }} aria-label="Edit">
                              <Pencil className="size-4" aria-hidden />
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setDeleting(row)} aria-label="Delete" disabled={row.is_system}>
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

      <PageFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={query.refetch} page={editing} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete page"
        message={`Delete “${deleting?.title}”? This cannot be undone.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
