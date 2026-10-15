import { useState } from 'react'
import { toast } from 'sonner'
import {
  Ban,
  BellRing,
  CheckCircle2,
  Clock,
  ListOrdered,
  Play,
  Plus,
  SkipForward,
  Stethoscope,
  Trash2,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import Input from '../components/ui/Input.jsx'
import Field from '../components/ui/Field.jsx'
import Modal from '../components/ui/Modal.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { queueApi } from '../api/queue.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime, patientFullName } from '../utils/format.js'
import { QUEUE_PRIORITY, QUEUE_PRIORITY_OPTIONS, QUEUE_STATUS, QUEUE_STATUS_OPTIONS, statusBadgeFor } from '../utils/status.js'
import QueueEntryFormModal from '../components/queue/QueueEntryFormModal.jsx'
import AppointmentPickerModal from '../components/queue/AppointmentPickerModal.jsx'

const today = new Date().toISOString().slice(0, 10)

export default function QueueListPage() {
  const { can } = useAuth()
  const canManage = can('queue.manage')

  const [page, setPage] = useState(1)
  const [date, setDate] = useState(today)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [priority, setPriority] = useState('')
  const [walkInOpen, setWalkInOpen] = useState(false)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [viewing, setViewing] = useState(null)
  const [confirming, setConfirming] = useState(null)

  const query = usePaginated(
    ['queue-entries', { date, search, status, priority }],
    (params) =>
      queueApi.list({
        ...params,
        date,
        search: search || undefined,
        status: status || undefined,
        priority: priority || undefined,
      }),
    { page },
  )

  const entries = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const runAction = async (entry, action) => {
    try {
      await queueApi[action](entry.id)
      const labels = {
        call: 'Patient called.',
        start: 'Service started.',
        complete: 'Visit completed.',
        skip: 'Entry skipped.',
        cancel: 'Entry cancelled.',
        remove: 'Entry removed.',
      }
      toast.success(labels[action] ?? 'Updated.')
      setConfirming(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Queue Management"
        description="Live outpatient waiting list with a call-next workflow."
        breadcrumb={[{ label: 'Queue Management' }]}
        actions={
          canManage ? (
            <>
              <Button variant="outline" onClick={() => setPickerOpen(true)}>
                <Plus className="size-4" aria-hidden /> From appointments
              </Button>
              <Button onClick={() => setWalkInOpen(true)}>
                <Plus className="size-4" aria-hidden /> New walk-in
              </Button>
            </>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Waiting" value={stats.waiting ?? 0} icon={Clock} tone="amber" loading={query.isLoading} />
        <StatCard label="Called" value={stats.called ?? 0} icon={BellRing} tone="brand" loading={query.isLoading} />
        <StatCard label="Serving" value={stats.serving ?? 0} icon={Stethoscope} tone="navy" loading={query.isLoading} />
        <StatCard label="Done" value={stats.done ?? 0} icon={CheckCircle2} tone="green" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="grid grid-cols-1 gap-3 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-5">
          <div className="lg:col-span-1">
            <SearchInput
              placeholder="Search ticket or patient…"
              onDebounced={(value) => { setSearch(value); setPage(1) }}
            />
          </div>
          <Field label="Date" htmlFor="queue-date" className="space-y-1">
            <Input id="queue-date" type="date" value={date} onChange={(e) => { setDate(e.target.value); setPage(1) }} />
          </Field>
          <Select aria-label="Filter by status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All statuses</option>
            {QUEUE_STATUS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Select aria-label="Filter by priority" value={priority} onChange={(e) => { setPriority(e.target.value); setPage(1) }}>
            <option value="">All priorities</option>
            {QUEUE_PRIORITY_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={6} /></div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState title="Failed to load the queue" message={query.error?.message} onRetry={() => query.refetch()} />
          </div>
        ) : entries.length === 0 ? (
          <EmptyState
            title="Queue is empty"
            message="No patients are waiting for this date."
            icon={ListOrdered}
            action={
              canManage ? (
                <Button size="sm" onClick={() => setWalkInOpen(true)}>Add walk-in</Button>
              ) : null
            }
          />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[900px]">
              <THead>
                <Tr>
                  <Th>Ticket</Th>
                  <Th>Patient</Th>
                  <Th>Doctor</Th>
                  <Th>Priority</Th>
                  <Th>Status</Th>
                  <Th>Added</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {entries.map((entry) => {
                  const statusMeta = statusBadgeFor(QUEUE_STATUS, entry.status)
                  const priorityMeta = statusBadgeFor(QUEUE_PRIORITY, entry.priority)
                  return (
                    <Tr key={entry.id} onClick={() => setViewing(entry)}>
                      <Td className="font-mono font-medium text-ink">{entry.ticket_number}</Td>
                      <Td className="font-medium text-ink">{patientFullName(entry.patient)}</Td>
                      <Td className="text-ink-muted">{entry.doctor?.name ?? '—'}</Td>
                      <Td><Badge tone={priorityMeta.tone}>{priorityMeta.label}</Badge></Td>
                      <Td><Badge tone={statusMeta.tone}>{statusMeta.label}</Badge></Td>
                      <Td className="whitespace-nowrap text-ink-muted">{formatDateTime(entry.created_at)}</Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-1">
                          {canManage && entry.status === 'waiting' ? (
                            <button
                              type="button"
                              aria-label="Call patient"
                              title="Call"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-brand-50 hover:text-brand-700"
                              onClick={() => runAction(entry, 'call')}
                            >
                              <BellRing className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {canManage && entry.status === 'called' ? (
                            <button
                              type="button"
                              aria-label="Start service"
                              title="Start"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-blue-50 hover:text-blue-700"
                              onClick={() => runAction(entry, 'start')}
                            >
                              <Play className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {canManage && entry.status === 'serving' ? (
                            <button
                              type="button"
                              aria-label="Complete visit"
                              title="Complete"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-green-50 hover:text-green-700"
                              onClick={() => runAction(entry, 'complete')}
                            >
                              <CheckCircle2 className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {canManage && ['waiting', 'called', 'serving'].includes(entry.status) ? (
                            <button
                              type="button"
                              aria-label="Skip entry"
                              title="Skip"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => setConfirming({ entry, action: 'skip' })}
                            >
                              <SkipForward className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {canManage && !['done', 'cancelled'].includes(entry.status) ? (
                            <button
                              type="button"
                              aria-label="Cancel entry"
                              title="Cancel"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-amber-50 hover:text-amber-700"
                              onClick={() => setConfirming({ entry, action: 'cancel' })}
                            >
                              <Ban className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {canManage ? (
                            <button
                              type="button"
                              aria-label="Remove entry"
                              title="Remove"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setConfirming({ entry, action: 'remove' })}
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

      <QueueEntryFormModal
        open={walkInOpen}
        onClose={() => setWalkInOpen(false)}
        onSaved={() => query.refetch()}
      />

      <AppointmentPickerModal
        open={pickerOpen}
        date={date}
        onClose={() => setPickerOpen(false)}
        onChanged={() => query.refetch()}
      />

      <Modal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing ? `Queue ${viewing.ticket_number}` : 'Queue entry'}
        description={viewing ? patientFullName(viewing.patient) : undefined}
        size="md"
      >
        {viewing ? (
          <dl className="divide-y divide-line text-sm">
            <DetailRow label="Patient" value={patientFullName(viewing.patient)} />
            <DetailRow label="Doctor" value={viewing.doctor?.name ?? 'Unassigned'} />
            <DetailRow label="Priority" value={statusBadgeFor(QUEUE_PRIORITY, viewing.priority).label} />
            <DetailRow label="Status" value={statusBadgeFor(QUEUE_STATUS, viewing.status).label} />
            <DetailRow label="Notes" value={viewing.notes || '—'} />
            {viewing.called_at ? <DetailRow label="Called at" value={formatDateTime(viewing.called_at)} /> : null}
            {viewing.started_at ? <DetailRow label="Started at" value={formatDateTime(viewing.started_at)} /> : null}
            {viewing.completed_at ? <DetailRow label="Completed at" value={formatDateTime(viewing.completed_at)} /> : null}
          </dl>
        ) : null}
      </Modal>

      <ConfirmDialog
        open={Boolean(confirming)}
        onClose={() => setConfirming(null)}
        onConfirm={() => confirming && runAction(confirming.entry, confirming.action)}
        title={
          confirming?.action === 'remove'
            ? 'Remove queue entry?'
            : confirming?.action === 'cancel'
              ? 'Cancel queue entry?'
              : 'Skip this entry?'
        }
        message={
          confirming?.action === 'remove'
            ? 'This permanently removes the entry from the queue.'
            : confirming?.action === 'cancel'
              ? 'The patient will be removed from the active queue.'
              : 'The patient will be moved out of the active waiting list.'
        }
        confirmLabel={
          confirming?.action === 'remove'
            ? 'Remove'
            : confirming?.action === 'cancel'
              ? 'Cancel entry'
              : 'Skip'
        }
      />
    </div>
  )
}

function DetailRow({ label, value }) {
  return (
    <div className="grid grid-cols-3 gap-3 py-2.5">
      <dt className="font-medium text-ink-muted">{label}</dt>
      <dd className="col-span-2 break-words text-ink">{value}</dd>
    </div>
  )
}
