import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Activity, Pencil, Plus, Sparkles, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import Modal from '../../components/ui/Modal.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import Textarea from '../../components/ui/Textarea.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import Select from '../../components/ui/Select.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import Table, { TBody, Td, Th, THead, Tr } from '../../components/ui/Table.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import ImageField from '../../components/website/ImageField.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = { title: '', slug: '', icon: '', short_description: '', description: '', is_featured: false, is_published: false, sort_order: 0 }

function ServiceFormModal({ open, onClose, onSaved, service }) {
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
            slug: service.slug ?? '',
            icon: service.icon ?? '',
            short_description: service.short_description ?? '',
            description: service.description ?? '',
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
      const payload = { ...form, title: form.title.trim() }
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
        <Field label="Description" htmlFor="service-desc">
          <Textarea id="service-desc" rows={6} value={form.description} onChange={(e) => set('description')(e.target.value)} />
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

export default function WebsiteServicesPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'services', { search, status }],
    (params) => websiteAdminApi.listServices({ ...params, search: search || undefined, is_published: status || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []

  const togglePublish = async (row) => {
    try {
      await websiteAdminApi.updateService(row.id, { is_published: !row.is_published })
      toast.success(row.is_published ? 'Service unpublished.' : 'Service published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteService(deleting.id)
      toast.success('Service deleted.')
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
        title="Services"
        description="Medical services displayed on the public website."
        actions={
          can('website.services.manage') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden />
              New service
            </Button>
          ) : null
        }
      />

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search services…" className="sm:max-w-xs" />
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="sm:w-40" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="1">Published</option>
            <option value="0">Draft</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={4} /></div>
        ) : query.isError ? (
          <div className="p-5"><ErrorState onRetry={query.refetch} /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="No services yet" message="Add the services your clinic offers." icon={Activity} />
        ) : (
          <div className="p-4">
            <Table>
              <THead>
                <Tr>
                  <Th>Service</Th>
                  <Th>Featured</Th>
                  <Th>Status</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </THead>
              <TBody>
                {rows.map((row) => (
                  <Tr key={row.id}>
                    <Td>
                      <div className="flex items-center gap-3">
                        {row.image?.url ? (
                          <img src={row.image.url} alt="" className="size-10 rounded-lg object-cover" />
                        ) : (
                          <span className="grid size-10 place-items-center rounded-lg bg-brand-soft text-brand">
                            <Activity className="size-4" aria-hidden />
                          </span>
                        )}
                        <div>
                          <div className="font-medium text-ink">{row.title}</div>
                          <div className="max-w-xs truncate text-xs text-ink-muted">{row.short_description}</div>
                        </div>
                      </div>
                    </Td>
                    <Td>{row.is_featured ? <Badge tone="brand"><Sparkles className="size-3" aria-hidden />Featured</Badge> : '—'}</Td>
                    <Td><Badge tone={row.is_published ? 'green' : 'gray'}>{row.is_published ? 'published' : 'draft'}</Badge></Td>
                    <Td>
                      <div className="flex items-center justify-end gap-1">
                        {can('website.services.manage') ? (
                          <>
                            <Button size="sm" variant={row.is_published ? 'outline' : 'secondary'} onClick={() => togglePublish(row)}>
                              {row.is_published ? 'Unpublish' : 'Publish'}
                            </Button>
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

      <ServiceFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={query.refetch} service={editing} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete service"
        message={`Delete “${deleting?.title}”? This cannot be undone.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
