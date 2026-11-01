import { useState } from 'react'
import { toast } from 'sonner'
import {
  AlertTriangle,
  Archive,
  ArchiveRestore,
  CalendarClock,
  FilePen,
  History,
  Megaphone,
  Pencil,
  Plus,
  Send,
  Trash2,
  Undo2,
  Users,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import Modal from '../components/ui/Modal.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
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
  ANNOUNCEMENT_CATEGORY,
  ANNOUNCEMENT_CATEGORY_OPTIONS,
  ANNOUNCEMENT_PRIORITY,
  ANNOUNCEMENT_PRIORITY_OPTIONS,
  ANNOUNCEMENT_STATUS,
  ANNOUNCEMENT_STATUS_OPTIONS,
  statusBadgeFor,
} from '../utils/status.js'
import AnnouncementFormModal from '../components/announcements/AnnouncementFormModal.jsx'

function announcementStatus(announcement) {
  if (announcement.archived_at) return ANNOUNCEMENT_STATUS.archived
  if (announcement.expires_at && new Date(announcement.expires_at) < new Date()) {
    return ANNOUNCEMENT_STATUS.expired
  }
  if (!announcement.is_published) {
    if (announcement.published_at && new Date(announcement.published_at) > new Date()) {
      return ANNOUNCEMENT_STATUS.scheduled
    }
    return ANNOUNCEMENT_STATUS.draft
  }
  return ANNOUNCEMENT_STATUS.published
}

function localDateTimeInput(value) {
  const date = value ? new Date(value) : new Date(Date.now() + 60 * 60 * 1000)
  const offset = date.getTimezoneOffset() * 60_000
  return new Date(date.getTime() - offset).toISOString().slice(0, 16)
}

export default function AnnouncementListPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [audience, setAudience] = useState('')
  const [priority, setPriority] = useState('')
  const [category, setCategory] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [viewing, setViewing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)
  const [scheduling, setScheduling] = useState(null)
  const [scheduleAt, setScheduleAt] = useState('')
  const [savingSchedule, setSavingSchedule] = useState(false)
  const [readership, setReadership] = useState(null)
  const [audit, setAudit] = useState(null)
  const [loadingDetail, setLoadingDetail] = useState(false)

  const query = usePaginated(
    ['announcements', { search, audience, priority, category, status }],
    (params) =>
      announcementApi.list({
        ...params,
        search: search || undefined,
        audience: audience || undefined,
        priority: priority || undefined,
        category: category || undefined,
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

  const toggleArchive = async (announcement) => {
    try {
      if (announcement.archived_at) {
        await announcementApi.restore(announcement.id)
        toast.success('Announcement restored.')
      } else {
        await announcementApi.archive(announcement.id)
        toast.success('Announcement archived.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const openSchedule = (announcement) => {
    setScheduling(announcement)
    setScheduleAt(localDateTimeInput(announcement.published_at))
  }

  const submitSchedule = async (event) => {
    event.preventDefault()
    if (!scheduleAt) return
    setSavingSchedule(true)
    try {
      await announcementApi.schedule(scheduling.id, {
        published_at: new Date(scheduleAt).toISOString(),
      })
      toast.success('Announcement scheduled.')
      setScheduling(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingSchedule(false)
    }
  }

  const openReadership = async (announcement) => {
    setReadership({ announcement, data: null })
    setLoadingDetail(true)
    try {
      const data = await announcementApi.readership(announcement.id)
      setReadership({ announcement, data })
    } catch (err) {
      toast.error(err.message)
      setReadership(null)
    } finally {
      setLoadingDetail(false)
    }
  }

  const openAudit = async (announcement) => {
    setAudit({ announcement, entries: null })
    setLoadingDetail(true)
    try {
      const res = await announcementApi.audit(announcement.id)
      setAudit({ announcement, entries: res.data ?? [] })
    } catch (err) {
      toast.error(err.message)
      setAudit(null)
    } finally {
      setLoadingDetail(false)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    setIsDeleting(true)
    try {
      await announcementApi.remove(deleting.id)
      toast.success('Announcement deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setIsDeleting(false)
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

      <div className="grid grid-cols-2 gap-4 xl:grid-cols-6">
        <StatCard label="Total" value={stats.total ?? 0} icon={Megaphone} tone="brand" loading={query.isLoading} />
        <StatCard label="Published" value={stats.published ?? 0} icon={Send} tone="green" loading={query.isLoading} />
        <StatCard label="Drafts" value={stats.draft ?? 0} icon={FilePen} tone="navy" loading={query.isLoading} />
        <StatCard label="Scheduled" value={stats.scheduled ?? 0} icon={CalendarClock} tone="amber" loading={query.isLoading} />
        <StatCard label="Urgent" value={stats.urgent ?? 0} icon={AlertTriangle} tone="red" loading={query.isLoading} />
        <StatCard label="Archived" value={stats.archived ?? 0} icon={Archive} tone="gray" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="grid grid-cols-1 gap-3 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-5">
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
            aria-label="Filter by category"
            value={category}
            onChange={(e) => { setCategory(e.target.value); setPage(1) }}
          >
            <option value="">All categories</option>
            {ANNOUNCEMENT_CATEGORY_OPTIONS.map((o) => (
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
            {ANNOUNCEMENT_STATUS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={7} /></div>
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
            <Table minWidth="min-w-[1080px]">
              <THead>
                <Tr>
                  <Th>Title</Th>
                  <Th>Audience</Th>
                  <Th>Category</Th>
                  <Th>Priority</Th>
                  <Th>Status</Th>
                  <Th>Read</Th>
                  <Th>Published</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {announcements.map((announcement) => {
                  const audienceMeta = statusBadgeFor(ANNOUNCEMENT_AUDIENCE, announcement.audience)
                  const priorityMeta = statusBadgeFor(ANNOUNCEMENT_PRIORITY, announcement.priority)
                  const categoryMeta = statusBadgeFor(ANNOUNCEMENT_CATEGORY, announcement.category)
                  const statusMeta = announcementStatus(announcement)
                  return (
                    <Tr key={announcement.id} onClick={() => setViewing(announcement)}>
                      <Td primary>
                        <div className="flex items-center gap-3">
                          {announcement.poster_url ? (
                            <img
                              src={announcement.poster_url}
                              alt=""
                              className="size-9 shrink-0 rounded-lg border border-line object-contain"
                              loading="lazy"
                            />
                          ) : null}
                          <span className="line-clamp-1 max-w-xs font-medium text-ink">
                            {announcement.title}
                          </span>
                        </div>
                      </Td>
                      <Td><Badge tone={audienceMeta.tone}>{audienceMeta.label}</Badge></Td>
                      <Td>
                        {announcement.category
                          ? <Badge tone={categoryMeta.tone}>{categoryMeta.label}</Badge>
                          : <span className="text-ink-muted">—</span>}
                      </Td>
                      <Td><Badge tone={priorityMeta.tone}>{priorityMeta.label}</Badge></Td>
                      <Td trailing><Badge tone={statusMeta.tone}>{statusMeta.label}</Badge></Td>
                      <Td mobile={false} className="whitespace-nowrap text-ink-muted">
                        {announcement.reads_count ?? 0}
                      </Td>
                      <Td leading className="whitespace-nowrap text-ink-muted">
                        {announcement.published_at ? formatDate(announcement.published_at) : '—'}
                      </Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-1">
                          {can('announcements.publish') && !announcement.archived_at ? (
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
                          {can('announcements.publish') && !announcement.is_published && !announcement.archived_at ? (
                            <button
                              type="button"
                              aria-label={`Schedule ${announcement.title}`}
                              title="Schedule"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => openSchedule(announcement)}
                            >
                              <CalendarClock className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {can('announcements.update') ? (
                            <button
                              type="button"
                              aria-label={`Readership for ${announcement.title}`}
                              title="Readership"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => openReadership(announcement)}
                            >
                              <Users className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {can('announcements.update') ? (
                            <button
                              type="button"
                              aria-label={`History for ${announcement.title}`}
                              title="History"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => openAudit(announcement)}
                            >
                              <History className="size-4" aria-hidden />
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
                              aria-label={announcement.archived_at ? `Restore ${announcement.title}` : `Archive ${announcement.title}`}
                              title={announcement.archived_at ? 'Restore' : 'Archive'}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => toggleArchive(announcement)}
                            >
                              {announcement.archived_at
                                ? <ArchiveRestore className="size-4" aria-hidden />
                                : <Archive className="size-4" aria-hidden />}
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
              {viewing.category ? (
                <Badge tone={statusBadgeFor(ANNOUNCEMENT_CATEGORY, viewing.category).tone}>
                  {statusBadgeFor(ANNOUNCEMENT_CATEGORY, viewing.category).label}
                </Badge>
              ) : null}
              <Badge tone={statusBadgeFor(ANNOUNCEMENT_PRIORITY, viewing.priority).tone}>
                {statusBadgeFor(ANNOUNCEMENT_PRIORITY, viewing.priority).label} priority
              </Badge>
              <Badge tone={announcementStatus(viewing).tone}>{announcementStatus(viewing).label}</Badge>
              {viewing.expires_at ? (
                <span className="text-xs text-ink-muted">Expires {formatDate(viewing.expires_at)}</span>
              ) : null}
            </div>
            {viewing.poster_url ? (
              <img
                src={viewing.poster_url}
                alt=""
                className="max-h-80 w-full rounded-xl border border-line object-contain"
              />
            ) : null}
            <p className="whitespace-pre-wrap text-sm text-ink">{viewing.body}</p>
            <div className="border-t border-line pt-3 text-xs text-ink-muted">
              {viewing.published_at ? `Published ${formatDateTime(viewing.published_at)}` : 'Not published yet'}
              {viewing.author?.name ? ` · by ${viewing.author.name}` : ''}
            </div>
          </div>
        ) : null}
      </Modal>

      <Modal
        open={Boolean(scheduling)}
        onClose={() => setScheduling(null)}
        title="Schedule announcement"
        description={scheduling?.title}
        size="sm"
        footer={
          <>
            <Button variant="outline" onClick={() => setScheduling(null)} disabled={savingSchedule}>
              Cancel
            </Button>
            <Button type="submit" form="schedule-form" loading={savingSchedule}>
              Schedule
            </Button>
          </>
        }
      >
        <form id="schedule-form" onSubmit={submitSchedule} className="space-y-4">
          <Field label="Publish at" htmlFor="schedule-at" required hint="The announcement goes live automatically at this time.">
            <Input
              id="schedule-at"
              type="datetime-local"
              value={scheduleAt}
              onChange={(e) => setScheduleAt(e.target.value)}
            />
          </Field>
        </form>
      </Modal>

      <Modal
        open={Boolean(readership)}
        onClose={() => setReadership(null)}
        title="Readership"
        description={readership?.announcement?.title}
        size="md"
      >
        {loadingDetail && !readership?.data ? (
          <RowSkeleton rows={3} cols={2} />
        ) : readership?.data ? (
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-3 text-center">
              <div className="rounded-lg bg-canvas px-3 py-2">
                <p className="text-lg font-semibold text-ink">{readership.data.recipients}</p>
                <p className="text-xs text-ink-muted">Recipients</p>
              </div>
              <div className="rounded-lg bg-canvas px-3 py-2">
                <p className="text-lg font-semibold text-emerald-600">{readership.data.read}</p>
                <p className="text-xs text-ink-muted">Read</p>
              </div>
              <div className="rounded-lg bg-canvas px-3 py-2">
                <p className="text-lg font-semibold text-amber-600">{readership.data.unread}</p>
                <p className="text-xs text-ink-muted">Unread</p>
              </div>
            </div>
            <div>
              <p className="mb-2 text-sm font-semibold text-ink">Read by</p>
              {readership.data.read_recipients?.length ? (
                <ul className="space-y-1 text-sm text-ink-muted">
                  {readership.data.read_recipients.map((r) => (
                    <li key={r.id} className="flex items-center justify-between">
                      <span>{r.name}</span>
                      <span className="text-xs">{r.read_at ? formatDateTime(r.read_at) : ''}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-ink-muted">No one has read this yet.</p>
              )}
            </div>
          </div>
        ) : null}
      </Modal>

      <Modal
        open={Boolean(audit)}
        onClose={() => setAudit(null)}
        title="History"
        description={audit?.announcement?.title}
        size="md"
      >
        {loadingDetail && !audit?.entries ? (
          <RowSkeleton rows={3} cols={2} />
        ) : audit?.entries?.length ? (
          <ul className="space-y-3">
            {audit.entries.map((entry) => (
              <li key={entry.id} className="border-b border-line pb-3 text-sm last:border-b-0 last:pb-0">
                <div className="flex items-center justify-between">
                  <span className="font-medium capitalize text-ink">{entry.event}</span>
                  <span className="text-xs text-ink-muted">{formatDateTime(entry.created_at)}</span>
                </div>
                <p className="text-xs text-ink-muted">
                  {entry.description}
                  {entry.actor ? ` · ${entry.actor}` : ''}
                </p>
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-ink-muted">No history recorded.</p>
        )}
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete announcement?"
        message="This permanently removes the announcement. This action cannot be undone."
        confirmLabel={isDeleting ? 'Deleting…' : 'Delete'}
        loading={isDeleting}
      />
    </div>
  )
}
