import { useState } from 'react'
import { toast } from 'sonner'
import {
  CalendarClock,
  CalendarDays,
  CheckCircle2,
  LayoutGrid,
  Pencil,
  Plus,
  Send,
  Settings2,
  Trash2,
  UploadCloud,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import Input from '../components/ui/Input.jsx'
import Field from '../components/ui/Field.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { shiftApi } from '../api/shifts.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDate, formatTime, toIsoDate } from '../utils/format.js'
import { SHIFT_STATUS, SHIFT_STATUS_OPTIONS, SHIFT_TYPES, SHIFT_TYPE_OPTIONS, statusBadgeFor } from '../utils/status.js'
import ShiftFormModal from '../components/scheduling/ShiftFormModal.jsx'
import ShiftTemplateManagerModal from '../components/scheduling/ShiftTemplateManagerModal.jsx'
import ShiftBoardModal from '../components/scheduling/ShiftBoardModal.jsx'

function currentWeekRange() {
  const d = new Date()
  const offset = (d.getDay() + 6) % 7
  const start = new Date(d)
  start.setDate(d.getDate() - offset)
  const end = new Date(start)
  end.setDate(start.getDate() + 6)
  return { from: toIsoDate(start), to: toIsoDate(end) }
}

export default function SchedulingPage() {
  const { can } = useAuth()
  const canManage = can('scheduling.manage')

  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [templatesOpen, setTemplatesOpen] = useState(false)
  const [boardOpen, setBoardOpen] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [viewing, setViewing] = useState(null)

  const query = usePaginated(
    ['shifts', { search, type, status, from, to }],
    (params) =>
      shiftApi.list({
        ...params,
        search: search || undefined,
        type: type || undefined,
        status: status || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
    { page },
  )

  const shifts = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await shiftApi.remove(deleting.id)
      toast.success('Shift removed.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const publishShift = async (shift) => {
    try {
      await shiftApi.publish(shift.id)
      toast.success('Shift published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const publishWeek = async () => {
    const range = currentWeekRange()
    setPublishing(true)
    try {
      const result = await shiftApi.publishBulk({
        from: from || range.from,
        to: to || range.to,
      })
      toast.success(`${result.published} shift(s) published.`)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setPublishing(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Staff Scheduling"
        description={canManage
          ? 'Build the roster and assign shifts across the team.'
          : 'View your upcoming shift roster.'}
        breadcrumb={[{ label: 'Staff Scheduling' }]}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => setBoardOpen(true)}>
              <LayoutGrid className="size-4" aria-hidden /> Board
            </Button>
            {canManage ? (
              <Button variant="outline" onClick={() => setTemplatesOpen(true)}>
                <Settings2 className="size-4" aria-hidden /> Templates
              </Button>
            ) : null}
            {canManage ? (
              <Button variant="outline" onClick={publishWeek} loading={publishing}>
                <UploadCloud className="size-4" aria-hidden /> Publish week
              </Button>
            ) : null}
            {canManage ? (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="size-4" aria-hidden /> Schedule shift
              </Button>
            ) : null}
          </div>
        }
      />

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-5">
        <StatCard label="Total shifts" value={stats.total ?? 0} icon={CalendarDays} tone="brand" loading={query.isLoading} />
        <StatCard label="Today" value={stats.today ?? 0} icon={CalendarClock} tone="navy" loading={query.isLoading} />
        <StatCard label="Scheduled" value={stats.scheduled ?? 0} icon={CalendarDays} tone="blue" loading={query.isLoading} />
        <StatCard label="Completed" value={stats.completed ?? 0} icon={CheckCircle2} tone="green" loading={query.isLoading} />
        <StatCard label="Unpublished" value={stats.unpublished ?? 0} icon={UploadCloud} tone="amber" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="grid grid-cols-1 items-end gap-3 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-5">
          {canManage ? (
            <div className="lg:col-span-1">
              <SearchInput
                placeholder="Search staff…"
                onDebounced={(value) => { setSearch(value); setPage(1) }}
              />
            </div>
          ) : null}
          <Select aria-label="Filter by type" value={type} onChange={(e) => { setType(e.target.value); setPage(1) }}>
            <option value="">All types</option>
            {SHIFT_TYPE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Select aria-label="Filter by status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All statuses</option>
            {SHIFT_STATUS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Field label="From" htmlFor="shift-from" className="space-y-1">
            <Input id="shift-from" type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
          </Field>
          <Field label="To" htmlFor="shift-to" className="space-y-1">
            <Input id="shift-to" type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
          </Field>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={5} /></div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState title="Failed to load shifts" message={query.error?.message} onRetry={() => query.refetch()} />
          </div>
        ) : shifts.length === 0 ? (
          <EmptyState
            title="No shifts scheduled"
            message={canManage ? 'No shifts match your filters.' : 'You have no shifts on the roster yet.'}
            icon={CalendarClock}
            action={
              canManage ? (
                <Button size="sm" onClick={() => { setEditing(null); setFormOpen(true) }}>Schedule shift</Button>
              ) : null
            }
          />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[820px]">
              <THead>
                <Tr>
                  <Th>Staff</Th>
                  <Th>Date</Th>
                  <Th>Time</Th>
                  <Th>Type</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {shifts.map((shift) => {
                  const typeMeta = statusBadgeFor(SHIFT_TYPES, shift.type)
                  const statusMeta = statusBadgeFor(SHIFT_STATUS, shift.status)
                  return (
                    <Tr key={shift.id} onClick={() => setViewing(shift)}>
                      <Td primary className="font-medium text-ink">{shift.user?.name ?? '—'}</Td>
                      <Td leading className="whitespace-nowrap text-ink-muted">{formatDate(shift.shift_date)}</Td>
                      <Td className="whitespace-nowrap text-ink-muted">
                        {formatTime(shift.start_time)} – {formatTime(shift.end_time)}
                      </Td>
                      <Td><Badge tone={typeMeta.tone}>{typeMeta.label}</Badge></Td>
                      <Td trailing>
                        <div className="flex items-center gap-2">
                          <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge>
                          {!shift.published_at ? <Badge tone="amber">Draft</Badge> : null}
                        </div>
                      </Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        {canManage ? (
                          <div className="flex justify-end gap-1">
                            {!shift.published_at ? (
                              <button
                                type="button"
                                aria-label="Publish shift"
                                title="Publish"
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-brand-soft hover:text-brand-dark"
                                onClick={() => publishShift(shift)}
                              >
                                <Send className="size-4" aria-hidden />
                              </button>
                            ) : null}
                            <button
                              type="button"
                              aria-label="Edit shift"
                              title="Edit"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => { setEditing(shift); setFormOpen(true) }}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                            <button
                              type="button"
                              aria-label="Remove shift"
                              title="Remove"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setDeleting(shift)}
                            >
                              <Trash2 className="size-4" aria-hidden />
                            </button>
                          </div>
                        ) : null}
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

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.user?.name ?? 'Shift'}
        subtitle={viewing ? `${formatDate(viewing.shift_date)} · ${formatTime(viewing.start_time)} – ${formatTime(viewing.end_time)}` : undefined}
        badges={
          viewing
            ? [
                statusBadgeFor(SHIFT_TYPES, viewing.type),
                statusBadgeFor(SHIFT_STATUS, viewing.status),
                viewing.published_at ? null : { label: 'Draft', tone: 'amber' },
              ]
            : []
        }
        fields={
          viewing
            ? [
                { label: 'Staff', value: viewing.user?.name },
                { label: 'Date', value: formatDate(viewing.shift_date) },
                { label: 'Time', value: `${formatTime(viewing.start_time)} – ${formatTime(viewing.end_time)}` },
                { label: 'Type', value: statusBadgeFor(SHIFT_TYPES, viewing.type).label },
                { label: 'Published', value: viewing.published_at ? formatDate(viewing.published_at) : '—' },
                { label: 'Notes', value: viewing.notes },
              ]
            : []
        }
      />

      <ShiftFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        shift={editing}
        onSaved={() => query.refetch()}
      />

      {canManage ? (
        <ShiftTemplateManagerModal open={templatesOpen} onClose={() => setTemplatesOpen(false)} />
      ) : null}

      <ShiftBoardModal open={boardOpen} onClose={() => setBoardOpen(false)} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Remove shift?"
        message="This removes the shift from the roster."
        confirmLabel="Remove"
      />
    </div>
  )
}
