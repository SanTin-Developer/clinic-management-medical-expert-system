import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import {
  Copy,
  ExternalLink,
  Eye,
  FolderOpen,
  MoreVertical,
  Trash2,
  UploadCloud,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Modal from '../../components/ui/Modal.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import Checkbox, { SelectableCard } from '../../components/ui/Checkbox.jsx'
import Lightbox from '../../components/ui/Lightbox.jsx'
import Dropdown, { DropdownItem } from '../../components/ui/Dropdown.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import ImageField from '../../components/ui/ImageField.jsx'
import { Skeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatBytes, formatDate } from '../../utils/format.js'
import { cn } from '../../utils/cn.js'

export default function WebsiteMediaPage() {
  const { can } = useAuth()
  const manage = can('website.media.manage')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [uploadOpen, setUploadOpen] = useState(false)
  const [file, setFile] = useState(null)
  const [name, setName] = useState('')
  const [uploading, setUploading] = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [previewIndex, setPreviewIndex] = useState(null)
  const [selectMode, setSelectMode] = useState(false)
  const [selectedIds, setSelectedIds] = useState([])
  const [bulkOpen, setBulkOpen] = useState(false)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'media', { search }],
    (params) => websiteAdminApi.listMedia({ ...params, search: search || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []
  const rowsById = new Map(rows.map((item) => [item.id, item]))
  const selectedRows = selectedIds.map((id) => rowsById.get(id)).filter(Boolean)
  const selectedUnused = selectedRows.filter((item) => !item.usage_count)
  const selectedInUse = selectedRows.filter((item) => item.usage_count)
  const allSelected = rows.length > 0 && rows.every((item) => selectedIds.includes(item.id))
  const someSelected = selectedIds.length > 0 && !allSelected

  const openUpload = () => {
    setFile(null)
    setName('')
    setUploadOpen(true)
  }

  const toggleSelected = (id) => {
    setSelectedIds((current) =>
      current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
    )
  }

  const toggleAll = () => {
    setSelectedIds(allSelected ? [] : rows.map((item) => item.id))
  }

  const toggleSelectMode = () => {
    setSelectMode((current) => {
      if (current) {
        setSelectedIds([])
      }
      return !current
    })
  }

  const previewItem = previewIndex === null ? null : rows[previewIndex] ?? null
  const hasPreviewPrev = previewIndex !== null && previewIndex > 0
  const hasPreviewNext = previewIndex !== null && previewIndex < rows.length - 1

  const openPreview = (id) => {
    const index = rows.findIndex((item) => item.id === id)
    if (index !== -1) {
      setPreviewIndex(index)
    }
  }

  const copyUrl = async (item) => {
    if (!item?.url) {
      toast.error('This file has no public URL yet.')
      return
    }

    try {
      await navigator.clipboard.writeText(item.url)
      toast.success('Image link copied.')
    } catch {
      toast.error('Could not copy the link. Copy it from the preview instead.')
    }
  }

  // Drop selections that are no longer visible (page change, search, deletion).
  const visibleIds = rows.map((item) => item.id).join(',')
  useEffect(() => {
    const visible = new Set(rows.map((item) => item.id))
    setSelectedIds((current) => {
      const next = current.filter((id) => visible.has(id))
      return next.length === current.length ? current : next
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visibleIds])

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
      toast.success('Image permanently deleted.')
      setDeleting(null)
      setSelectedIds((current) => current.filter((id) => id !== deleting.id))
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  const confirmBulkDelete = async () => {
    if (selectedUnused.length === 0) {
      return
    }
    setBusy(true)
    const deleted = []
    const failed = []
    try {
      for (const item of selectedUnused) {
        try {
          await websiteAdminApi.deleteMedia(item.id)
          deleted.push(item.id)
        } catch (err) {
          failed.push({ item, message: err.message })
        }
      }

      if (deleted.length > 0) {
        toast.success(
          `${deleted.length} image${deleted.length === 1 ? '' : 's'} permanently deleted.`,
        )
      }
      if (failed.length > 0) {
        toast.error(`${failed.length} image(s) could not be deleted. ${failed[0].message}`)
      }

      setSelectedIds((current) => current.filter((id) => !deleted.includes(id)))
      setBulkOpen(false)
      query.refetch()
    } finally {
      setBusy(false)
    }
  }

  const deletingInUse = (deleting?.usage_count ?? 0) > 0
  const deletingReferences = deleting?.usage_references ?? []
  const referenceSummary = deletingReferences
    .slice(0, 4)
    .map((reference) => reference.label)
    .join(', ')

  return (
    <>
      <PageHeader
        title="Media Library"
        description="Images uploaded for use across the website."
        actions={
          manage ? (
            <Button onClick={openUpload}>
              <UploadCloud className="size-4" aria-hidden />
              Upload image
            </Button>
          ) : null
        }
      />

      <Card bodyClassName="p-0">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line p-4">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search files…" className="sm:max-w-xs" />
          {manage && rows.length > 0 ? (
            <div className="flex items-center gap-3">
              {selectMode ? (
                <label className="flex cursor-pointer items-center gap-2 text-sm font-medium text-ink-muted transition-colors hover:text-ink">
                  <Checkbox
                    checked={allSelected}
                    indeterminate={someSelected}
                    onChange={toggleAll}
                    aria-label="Select all files on this page"
                  />
                  Select all
                </label>
              ) : null}
              <Button
                variant={selectMode ? 'secondary' : 'outline'}
                size="sm"
                onClick={toggleSelectMode}
              >
                <Eye className="size-4" aria-hidden />
                {selectMode ? 'Done' : 'Select'}
              </Button>
            </div>
          ) : null}
        </div>

        {selectMode && selectedRows.length > 0 ? (
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-slate-50 px-4 py-2.5 dark:bg-white/5">
            <p className="text-sm font-medium text-ink">
              {selectedRows.length} selected
              {selectedInUse.length > 0 ? (
                <span className="ml-1 font-normal text-amber-700">
                  · {selectedInUse.length} in use (will be skipped)
                </span>
              ) : null}
            </p>
            <div className="flex items-center gap-2">
              <Button variant="ghost" size="sm" onClick={() => setSelectedIds([])}>
                Clear
              </Button>
              <Button
                variant="danger"
                size="sm"
                disabled={selectedUnused.length === 0}
                onClick={() => setBulkOpen(true)}
              >
                <Trash2 className="size-4" aria-hidden />
                Delete selected
              </Button>
            </div>
          </div>
        ) : null}

        {query.isLoading ? (
          <div className="grid grid-cols-2 gap-5 bg-slate-50/80 p-5 sm:grid-cols-3 lg:grid-cols-4 dark:bg-transparent">
            {Array.from({ length: 8 }).map((_, i) => (
              <Skeleton key={i} className="h-40 w-full" />
            ))}
          </div>
        ) : query.isError ? (
          <div className="p-5"><ErrorState onRetry={query.refetch} /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="No media yet" message="Upload images to build your media library." icon={FolderOpen} />
        ) : (
          <div className="grid grid-cols-2 gap-5 bg-slate-50/80 p-5 sm:grid-cols-3 lg:grid-cols-4 dark:bg-transparent">
            {rows.map((item) => {
              const selected = selectedIds.includes(item.id)
              return (
                <SelectableCard
                  key={item.id}
                  selected={selected}
                  onToggle={() => toggleSelected(item.id)}
                  onOpen={() => (selectMode ? toggleSelected(item.id) : openPreview(item.id))}
                  label={
                    selectMode
                      ? `${selected ? 'Deselect' : 'Select'} ${item.name || 'file'}`
                      : `View ${item.name || 'file'}`
                  }
                >
                  <div className="relative">
                    {item.url ? (
                      <img
                        src={item.url}
                        alt={item.name ?? ''}
                        className="h-40 w-full bg-slate-50 object-contain"
                      />
                    ) : (
                      <div className="grid h-40 w-full place-items-center bg-slate-50 text-slate-400">
                        <FolderOpen className="size-6" aria-hidden />
                      </div>
                    )}

                    {/* Hover/focus affordance telling the user the image is clickable. */}
                    {!selectMode ? (
                      <span
                        className={cn(
                          'pointer-events-none absolute inset-0 grid place-items-center bg-slate-950/0 transition-colors duration-200',
                          'group-hover:bg-slate-950/15',
                        )}
                        aria-hidden
                      >
                        <span className="grid size-10 scale-90 place-items-center rounded-full bg-slate-950/55 text-white opacity-0 backdrop-blur-sm transition-all duration-200 group-hover:scale-100 group-hover:opacity-100">
                          <Eye className="size-5" aria-hidden />
                        </span>
                      </span>
                    ) : null}

                    {manage && selectMode ? (
                      <span
                        className={cn(
                          'absolute left-2.5 top-2.5 grid place-items-center rounded-full bg-white/85 p-1 shadow-[0_1px_4px_rgba(15,23,42,0.12)] ring-1 ring-slate-900/5 backdrop-blur-sm transition-opacity duration-200',
                          'dark:bg-slate-900/70 dark:ring-white/10',
                          selected ? 'opacity-100' : 'opacity-90 group-hover:opacity-100',
                        )}
                        onClick={(event) => event.stopPropagation()}
                        onKeyDown={(event) => event.stopPropagation()}
                        role="presentation"
                      >
                        <Checkbox
                          checked={selected}
                          onChange={() => toggleSelected(item.id)}
                          tabIndex={-1}
                          aria-label={`Select ${item.name || 'file'}`}
                        />
                      </span>
                    ) : null}

                    {manage ? (
                      <span
                        className="absolute right-2 top-2 z-20"
                        onClick={(event) => event.stopPropagation()}
                        onKeyDown={(event) => event.stopPropagation()}
                        role="presentation"
                      >
                          <Dropdown
                            width="min-w-52"
                            align="right"
                            trigger={
                              <button
                                type="button"
                                aria-label={`More options for ${item.name || 'file'}`}
                                className="grid size-8 place-items-center rounded-full bg-slate-950/55 text-white opacity-0 backdrop-blur-sm transition-all hover:bg-slate-950/75 focus-visible:opacity-100 group-hover:opacity-100 data-[open=true]:opacity-100"
                              >
                                <MoreVertical className="size-4" aria-hidden />
                              </button>
                            }
                          >
                            <DropdownItem onClick={() => openPreview(item.id)}>
                              <Eye className="size-4" aria-hidden /> View image
                            </DropdownItem>
                            <DropdownItem onClick={() => copyUrl(item)}>
                              <Copy className="size-4" aria-hidden /> Copy image link
                            </DropdownItem>
                            {item.url ? (
                              <DropdownItem
                                onClick={() => window.open(item.url, '_blank', 'noopener,noreferrer')}
                              >
                                <ExternalLink className="size-4" aria-hidden /> Open in new tab
                              </DropdownItem>
                            ) : null}
                            <div className="mx-2 my-1 border-t border-line" />
                            <DropdownItem danger onClick={() => setDeleting(item)}>
                              <Trash2 className="size-4" aria-hidden /> Delete permanently
                            </DropdownItem>
                          </Dropdown>
                        </span>
                    ) : null}

                    {selected ? (
                      <span className="pointer-events-none absolute inset-0 bg-[#6d28d9]/8" aria-hidden />
                    ) : null}
                  </div>

                  <div className="space-y-0.5 p-3.5">
                    <p className="truncate text-sm font-medium text-ink">{item.name || 'Untitled'}</p>
                    <p className="text-xs text-ink-muted">
                      {formatBytes(item.size)} · {formatDate(item.created_at)}
                    </p>
                    <p
                      className={cn(
                        'pt-0.5 text-xs font-medium',
                        item.usage_count ? 'text-amber-700' : 'text-ink-muted',
                      )}
                    >
                      {item.usage_count
                        ? `Used by ${item.usage_count} record${item.usage_count === 1 ? '' : 's'}`
                        : 'Not used by any record'}
                    </p>
                  </div>
                </SelectableCard>
              )
            })}
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
          <Field label="Image" htmlFor="media-file" required hint="JPG, PNG, WEBP or GIF · up to 10MB">
            <ImageField
              id="media-file"
              file={file}
              onFile={setFile}
              onRemove={() => setFile(null)}
            />
          </Field>
          <Field label="Name" htmlFor="media-name" hint="Optional label to help you find this file later.">
            <Input id="media-name" value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
        </form>
      </Modal>

      <Lightbox
        open={Boolean(previewItem)}
        onClose={() => setPreviewIndex(null)}
        src={previewItem?.url}
        alt={previewItem?.name ?? ''}
        title={previewItem?.name || 'Untitled'}
        description={
          previewItem
            ? `${formatBytes(previewItem.size)} · ${formatDate(previewItem.created_at)}`
            : undefined
        }
        hasPrev={hasPreviewPrev}
        hasNext={hasPreviewNext}
        onPrev={() => setPreviewIndex((index) => Math.max(0, (index ?? 0) - 1))}
        onNext={() =>
          setPreviewIndex((index) => Math.min(rows.length - 1, (index ?? 0) + 1))
        }
        actions={
          manage && previewItem ? (
            <>
              <button
                type="button"
                onClick={() => {
                  const target = previewItem
                  setPreviewIndex(null)
                  setDeleting(target)
                }}
                aria-label="Delete image permanently"
                className="grid size-9 place-items-center rounded-lg bg-white/10 text-white transition-colors hover:bg-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
              >
                <Trash2 className="size-4" aria-hidden />
              </button>
            </>
          ) : null
        }
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title={deletingInUse ? 'Image is still in use' : 'Permanently delete image'}
        message={
          deletingInUse
            ? `This image is used by ${deleting?.usage_count} record(s): ${referenceSummary}${deletingReferences.length > 4 ? '…' : ''}. Remove those references before permanently deleting it from Cloudinary.`
            : `Permanently delete “${deleting?.name}” from Cloudinary? This cannot be undone.`
        }
        confirmLabel={deletingInUse ? 'Cannot delete yet' : 'Delete permanently'}
        confirmDisabled={deletingInUse}
        loading={busy}
      />

      <ConfirmDialog
        open={bulkOpen}
        onClose={() => setBulkOpen(false)}
        onConfirm={confirmBulkDelete}
        title={`Permanently delete ${selectedUnused.length} image${selectedUnused.length === 1 ? '' : 's'}?`}
        message={
          selectedInUse.length > 0
            ? `${selectedUnused.length} unused image(s) will be permanently deleted from Cloudinary. ${selectedInUse.length} selected image(s) are still in use and will be skipped. This cannot be undone.`
            : `This permanently deletes ${selectedUnused.length} image(s) from Cloudinary. This cannot be undone.`
        }
        confirmLabel="Delete selected"
        loading={busy}
      />
    </>
  )
}
