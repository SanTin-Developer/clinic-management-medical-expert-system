import { useState } from 'react'
import { toast } from 'sonner'
import { AlertTriangle, FilePen, Megaphone, Pencil, Plus, Send, Trash2, Undo2 } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import Modal from '../components/ui/Modal.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { announcementApi } from '../api/announcements.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDate, formatDateTime } from '../utils/format.js'
import {
  ANNOUNCEMENT_AUDIENCE,
  ANNOUNCEMENT_AUDIENCE_OPTIONS,
  ANNOUNCEMENT_PRIORITY,
  ANNOUNCEMENT_PRIORITY_OPTIONS,
  statusBadgeFor,
} from '../utils/status.js'
import AnnouncementFormModal from '../components/announcements/AnnouncementFormModal.jsx'

function announcementStatus(announcement) {
  if (announcement.expires_at && new Date(announcement.expires_at) < new Date()) {
    return { label: 'Expired', tone: 'gray' }
  }
  if (!announcement.is_published) return { label: 'Draft', tone: 'gray' }
  if (announcement.published_at && new Date(announcement.published_at) > new Date()) {
    return { label: 'Scheduled', tone: 'amber' }
  }
  return { label: 'Published', tone: 'green' }
}

export default function AnnouncementListPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [audience, setAudience] = useState('')
  const [priority, setPriority] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [viewing, setViewing] = useState(null)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(
    ['announcements', { search, audience, priority, status }],
    (params) =>
      announcementApi.list({
        ...params,
        search: search || undefined,
        audience: audience || undefined,
        priority: priority || undefined,
        status: status || undefined,
      }),
    { page },
  )

  const announcements = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const togglePublish = async (announcement) => {
    try {
      if (announcement.is_published) {
        await announcementApi.unpublish(announcement.id)
        toast.success('Announcement unpublished.')
      } else {
        await announcementApi.publish(announcement.id)
        toast.success('Announcement published.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await announcementApi.remove(deleting.id)
      toast.success('Announcement deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Announcements"
        description="Share notices with staff and patients across the clinic."
        breadcrumb={[{ label: 'Announcements' }]}
        actions={
          can('announcements.create') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden /> New announcement
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total" value={stats.total ?? 0} icon={Megaphone} tone="brand" loading={query.isLoading} />
        <StatCard label="Published" value={stats.published ?? 0} icon={Send} tone="green" loading={query.isLoading} />
        <StatCard label="Drafts" value={stats.draft ?? 0} icon={FilePen} tone="navy" loading={query.isLoading} />
        <StatCard label="Urgent" value={stats.urgent ?? 0} icon={AlertTriangle} tone="amber" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="grid grid-cols-1 gap-3 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-4">
          <div className="lg:col-span-1">
            <SearchInput
              placeholder="Search announcements…"
              onDebounced={(value) => { setSearch(value); setPage(1) }}
            />
          </div>
          <Select
            aria-label="Filter by audience"
            value={audience}
            onChange={(e) => { setAudience(e.target.value); setPage(1) }}
          >
            <option value="">All audiences</option>
            {ANNOUNCEMENT_AUDIENCE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Select
            aria-label="Filter by priority"
            value={priority}
            onChange={(e) => { setPriority(e.target.value); setPage(1) }}
          >
            <option value="">All priorities</option>
            {ANNOUNCEMENT_PRIORITY_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Select
            aria-label="Filter by status"
            value={status}
            onChange={(e) => { setStatus(e.target.value); setPage(1) }}
          >
            <option value="">All statuses</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="scheduled">Scheduled</option>
            <option value="expired">Expired</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={6} /></div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState
              title="Failed to load announcements"
              message={query.error?.message}
              onRetry={() => query.refetch()}
            />
          </div>
        ) : announcements.length === 0 ? (
          <EmptyState
            title="No announcements yet"
            message="Create an announcement to broadcast a notice to staff or patients."
            icon={Megaphone}
            action={
              can('announcements.create') ? (
                <Button size="sm" onClick={() => { setEditing(null); setFormOpen(true) }}>
                  New announcement
                </Button>
              ) : null
            }
          />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[880px]">
              <THead>
                <Tr>
                  <Th>Title</Th>
                  <Th>Audience</Th>
                  <Th>Priority</Th>
                  <Th>Status</Th>
                  <Th>Published</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {announcements.map((announcement) => {
                  const audienceMeta = statusBadgeFor(ANNOUNCEMENT_AUDIENCE, announcement.audience)
                  const priorityMeta = statusBadgeFor(ANNOUNCEMENT_PRIORITY, announcement.priority)
                  const statusMeta = announcementStatus(announcement)
                  return (
                    <Tr key={announcement.id} onClick={() => setViewing(announcement)}>
                      <Td>
                        <span className="line-clamp-1 max-w-xs font-medium text-ink">
                          {announcement.title}
                        </span>
                      </Td>
                      <Td><Badge tone={audienceMeta.tone}>{audienceMeta.label}</Badge></Td>
                      <Td><Badge tone={priorityMeta.tone}>{priorityMeta.label}</Badge></Td>
                      <Td><Badge tone={statusMeta.tone}>{statusMeta.label}</Badge></Td>
                      <Td className="whitespace-nowrap text-ink-muted">
                        {announcement.published_at ? formatDate(announcement.published_at) : '—'}
                      </Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-1">
                          {can('announcements.publish') ? (
                            <button
                              type="button"
                              aria-label={announcement.is_published ? 'Unpublish' : 'Publish'}
                              title={announcement.is_published ? 'Unpublish' : 'Publish'}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => togglePublish(announcement)}
                            >
                              {announcement.is_published
                                ? <Undo2 className="size-4" aria-hidden />
                                : <Send className="size-4" aria-hidden />}
                            </button>
                          ) : null}
                          {can('announcements.update') ? (
                            <button
                              type="button"
                              aria-label={`Edit ${announcement.title}`}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => { setEditing(announcement); setFormOpen(true) }}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {can('announcements.delete') ? (
                            <button
                              type="button"
                              aria-label={`Delete ${announcement.title}`}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setDeleting(announcement)}
                            >
                              <Trash2 className="size-4" aria-hidden />
                            </button>
                          ) : null}
                        </div>
                      </Td>
                    </Tr>
                  )
                })}
              </TBody>
            </Table>
          </div>
        )}

        <Pagination
          page={query.data?.current_page ?? 1}
          lastPage={query.data?.last_page ?? 1}
          total={query.data?.total}
          onChange={setPage}
        />
      </Card>

      <AnnouncementFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        announcement={editing}
        onSaved={() => query.refetch()}
      />

      <Modal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.title ?? 'Announcement'}
        description={viewing ? statusBadgeFor(ANNOUNCEMENT_AUDIENCE, viewing.audience).label : undefined}
        size="md"
      >
        {viewing ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={statusBadgeFor(ANNOUNCEMENT_PRIORITY, viewing.priority).tone}>
                {statusBadgeFor(ANNOUNCEMENT_PRIORITY, viewing.priority).label} priority
              </Badge>
              <Badge tone={announcementStatus(viewing).tone}>{announcementStatus(viewing).label}</Badge>
              {viewing.expires_at ? (
                <span className="text-xs text-ink-muted">Expires {formatDate(viewing.expires_at)}</span>
              ) : null}
            </div>
            <p className="whitespace-pre-wrap text-sm text-ink">{viewing.body}</p>
            <div className="border-t border-line pt-3 text-xs text-ink-muted">
              {viewing.published_at ? `Published ${formatDateTime(viewing.published_at)}` : 'Not published yet'}
              {viewing.author?.name ? ` · by ${viewing.author.name}` : ''}
            </div>
          </div>
        ) : null}
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete announcement?"
        message="This permanently removes the announcement. This action cannot be undone."
        confirmLabel="Delete"
      />
    </div>
  )
}
