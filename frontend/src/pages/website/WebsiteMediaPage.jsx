import { useState } from 'react'
import { toast } from 'sonner'
import { FolderOpen, Trash2, UploadCloud } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Modal from '../../components/ui/Modal.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import FileDropzone from '../../components/ui/FileDropzone.jsx'
import { Skeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatBytes, formatDate } from '../../utils/format.js'

export default function WebsiteMediaPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [uploadOpen, setUploadOpen] = useState(false)
  const [file, setFile] = useState(null)
  const [name, setName] = useState('')
  const [uploading, setUploading] = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'media', { search }],
    (params) => websiteAdminApi.listMedia({ ...params, search: search || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []

  const openUpload = () => {
    setFile(null)
    setName('')
    setUploadOpen(true)
  }

  const submitUpload = async (event) => {
    event.preventDefault()
    if (!file) {
      toast.error('Please choose an image to upload.')
      return
    }
    setUploading(true)
    try {
      await websiteAdminApi.uploadMedia({ file, name: name || undefined })
      toast.success('Image uploaded.')
      setUploadOpen(false)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setUploading(false)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteMedia(deleting.id)
      toast.success('File deleted.')
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
        title="Media Library"
        description="Images uploaded for use across the website."
        actions={
          can('website.media.manage') ? (
            <Button onClick={openUpload}>
              <UploadCloud className="size-4" aria-hidden />
              Upload image
            </Button>
          ) : null
        }
      />

      <Card bodyClassName="p-0">
        <div className="flex items-center justify-between gap-3 border-b border-line p-4">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search files…" className="sm:max-w-xs" />
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
          <EmptyState title="No media yet" message="Upload images to build your media library." icon={FolderOpen} />
        ) : (
          <div className="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 lg:grid-cols-4">
            {rows.map((item) => (
              <div key={item.id} className="group overflow-hidden rounded-2xl border border-line bg-surface">
                <div className="relative">
                  {item.url ? (
                    <img src={item.url} alt={item.name ?? ''} className="h-40 w-full object-cover" />
                  ) : (
                    <div className="grid h-40 w-full place-items-center bg-brand-soft text-brand">
                      <FolderOpen className="size-6" aria-hidden />
                    </div>
                  )}
                  {can('website.media.manage') ? (
                    <button
                      type="button"
                      onClick={() => setDeleting(item)}
                      aria-label="Delete file"
                      className="absolute right-2 top-2 grid size-8 place-items-center rounded-lg bg-black/60 text-white opacity-0 transition-opacity hover:bg-red-600 group-hover:opacity-100"
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </button>
                  ) : null}
                </div>
                <div className="p-3">
                  <p className="truncate text-sm font-medium text-ink">{item.name || 'Untitled'}</p>
                  <p className="text-xs text-ink-muted">{formatBytes(item.size)} · {formatDate(item.created_at)}</p>
                </div>
              </div>
            ))}
          </div>
        )}

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <Modal
        open={uploadOpen}
        onClose={() => setUploadOpen(false)}
        title="Upload image"
        footer={
          <>
            <Button variant="outline" onClick={() => setUploadOpen(false)} disabled={uploading}>Cancel</Button>
            <Button type="submit" form="media-form" loading={uploading}>Upload</Button>
          </>
        }
      >
        <form id="media-form" onSubmit={submitUpload} className="space-y-4">
          <Field label="Image" htmlFor="media-file" required>
            <FileDropzone
              id="media-file"
              value={file}
              accept=".jpg,.jpeg,.png,.webp,.gif"
              hint="JPG, PNG, WEBP or GIF · up to 10MB"
              onSelect={setFile}
            />
          </Field>
          <Field label="Name" htmlFor="media-name" hint="Optional label to help you find this file later.">
            <Input id="media-name" value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete file"
        message={`Delete “${deleting?.name}”? This cannot be undone.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
