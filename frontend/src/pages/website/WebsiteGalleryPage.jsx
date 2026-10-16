import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { Images, Pencil, Plus, Trash2 } from 'lucide-react'
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
import Pagination from '../../components/ui/Pagination.jsx'
import { Skeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import ImageField from '../../components/website/ImageField.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = { title: '', caption: '', category: '', is_published: false, sort_order: 0 }

function GalleryFormModal({ open, onClose, onSaved, item }) {
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

export default function WebsiteGalleryPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [category, setCategory] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'gallery', { category, status }],
    (params) => websiteAdminApi.listGallery({ ...params, category: category || undefined, is_published: status || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []

  const togglePublish = async (row) => {
    try {
      await websiteAdminApi.updateGallery(row.id, { is_published: !row.is_published })
      toast.success(row.is_published ? 'Image unpublished.' : 'Image published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteGallery(deleting.id)
      toast.success('Gallery item deleted.')
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
        title="Gallery"
        description="Photos of your clinic, facilities and team shown publicly."
        actions={
          can('website.gallery.manage') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden />
              Add image
            </Button>
          ) : null
        }
      />

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput onDebounced={(value) => { setCategory(value); setPage(1) }} placeholder="Filter by category…" className="sm:max-w-xs" />
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="sm:w-40" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="1">Published</option>
            <option value="0">Draft</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} className="h-40 w-full" />
            ))}
          </div>
        ) : query.isError ? (
          <div className="p-5"><ErrorState onRetry={query.refetch} /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="No gallery images yet" message="Upload photos to showcase your clinic." icon={Images} />
        ) : (
          <div className="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-4">
            {rows.map((row) => (
              <div key={row.id} className="group overflow-hidden rounded-2xl border border-line bg-surface">
                <div className="relative">
                  {row.image?.url ? (
                    <img src={row.image.url} alt={row.title ?? ''} className="h-40 w-full object-cover" />
                  ) : (
                    <div className="grid h-40 w-full place-items-center bg-brand-soft text-brand">
                      <Images className="size-6" aria-hidden />
                    </div>
                  )}
                  <div className="absolute left-2 top-2">
                    <Badge tone={row.is_published ? 'green' : 'gray'}>{row.is_published ? 'published' : 'draft'}</Badge>
                  </div>
                </div>
                <div className="p-3">
                  <p className="truncate text-sm font-medium text-ink">{row.title || 'Untitled'}</p>
                  <p className="truncate text-xs text-ink-muted">{row.category || 'Uncategorised'}</p>
                  {can('website.gallery.manage') ? (
                    <div className="mt-2 flex items-center justify-between gap-1">
                      <Button size="sm" variant="ghost" onClick={() => togglePublish(row)}>
                        {row.is_published ? 'Unpublish' : 'Publish'}
                      </Button>
                      <div className="flex gap-1">
                        <Button size="sm" variant="ghost" onClick={() => { setEditing(row); setFormOpen(true) }} aria-label="Edit">
                          <Pencil className="size-4" aria-hidden />
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setDeleting(row)} aria-label="Delete">
                          <Trash2 className="size-4" aria-hidden />
                        </Button>
                      </div>
                    </div>
                  ) : null}
                </div>
              </div>
            ))}
          </div>
        )}

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <GalleryFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={query.refetch} item={editing} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete gallery item"
        message="This image will be removed from the gallery. Continue?"
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
