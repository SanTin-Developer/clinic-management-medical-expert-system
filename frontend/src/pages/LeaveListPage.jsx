import { useState } from 'react'
import { toast } from 'sonner'
import {
  Ban,
  CalendarDays,
  Check,
  CheckCircle2,
  Clock,
  Pencil,
  Plus,
  Trash2,
  X,
  XCircle,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import Input from '../components/ui/Input.jsx'
import Field from '../components/ui/Field.jsx'
import Textarea from '../components/ui/Textarea.jsx'
import Modal from '../components/ui/Modal.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { leaveApi } from '../api/leave.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDate, formatDateTime } from '../utils/format.js'
import { LEAVE_STATUS, LEAVE_STATUS_OPTIONS, LEAVE_TYPES, LEAVE_TYPE_OPTIONS, statusBadgeFor } from '../utils/status.js'
import LeaveFormModal from '../components/leave/LeaveFormModal.jsx'

export default function LeaveListPage() {
  const { user, can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [type, setType] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [viewing, setViewing] = useState(null)
  const [review, setReview] = useState(null)
  const [cancelling, setCancelling] = useState(null)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(
    ['leave-requests', { search, status, type, from, to }],
    (params) =>
      leaveApi.list({
        ...params,
        search: search || undefined,
        status: status || undefined,
        type: type || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
    { page },
  )

  const requests = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const canApprove = can('leave.approve')

  const handleCancel = async () => {
    if (!cancelling) return
    try {
      await leaveApi.cancel(cancelling.id)
      toast.success('Leave request cancelled.')
      setCancelling(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await leaveApi.remove(deleting.id)
      toast.success('Leave request deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Leave Management"
        description={canApprove
          ? 'Review and manage staff leave requests.'
          : 'Submit and track your leave requests.'}
        breadcrumb={[{ label: 'Leave Management' }]}
        actions={
          can('leave.create') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden /> New request
            </Button>
          ) : null
        }
      />

      <div className="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <StatCard label="Total" value={stats.total ?? 0} icon={CalendarDays} tone="brand" loading={query.isLoading} />
        <StatCard label="Pending" value={stats.pending ?? 0} icon={Clock} tone="amber" loading={query.isLoading} />
        <StatCard label="Approved" value={stats.approved ?? 0} icon={CheckCircle2} tone="green" loading={query.isLoading} />
        <StatCard label="Rejected" value={stats.rejected ?? 0} icon={XCircle} tone="navy" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="grid grid-cols-1 items-end gap-3 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-5">
          <div className="lg:col-span-1">
            <SearchInput
              placeholder="Search requests…"
              onDebounced={(value) => { setSearch(value); setPage(1) }}
            />
          </div>
          <Select aria-label="Filter by status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All statuses</option>
            {LEAVE_STATUS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Select aria-label="Filter by type" value={type} onChange={(e) => { setType(e.target.value); setPage(1) }}>
            <option value="">All types</option>
            {LEAVE_TYPE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Field label="From" htmlFor="leave-from" className="space-y-1">
            <Input id="leave-from" type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
          </Field>
          <Field label="To" htmlFor="leave-to" className="space-y-1">
            <Input id="leave-to" type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
          </Field>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={6} /></div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState title="Failed to load leave requests" message={query.error?.message} onRetry={() => query.refetch()} />
          </div>
        ) : requests.length === 0 ? (
          <EmptyState
            title="No leave requests"
            message={canApprove ? 'No leave requests match your filters.' : 'Submit a request to get started.'}
            icon={CalendarDays}
            action={
              can('leave.create') ? (
                <Button size="sm" onClick={() => { setEditing(null); setFormOpen(true) }}>New request</Button>
              ) : null
            }
          />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[900px]">
              <THead>
                <Tr>
                  <Th>Requester</Th>
                  <Th>Type</Th>
                  <Th>Dates</Th>
                  <Th>Days</Th>
                  <Th>Status</Th>
                  <Th>Reviewed by</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {requests.map((request) => {
                  const typeMeta = statusBadgeFor(LEAVE_TYPES, request.type)
                  const statusMeta = statusBadgeFor(LEAVE_STATUS, request.status)
                  const isOwn = request.user_id === user?.id
                  const isPending = request.status === 'pending'
                  return (
                    <Tr key={request.id} onClick={() => setViewing(request)}>
                      <Td primary className="font-medium text-ink">{request.user?.name ?? '—'}</Td>
                      <Td><Badge tone={typeMeta.tone}>{typeMeta.label}</Badge></Td>
                      <Td leading className="whitespace-nowrap text-ink-muted">
                        {formatDate(request.start_date)} → {formatDate(request.end_date)}
                      </Td>
                      <Td>{request.days}</Td>
                      <Td trailing><Badge tone={statusMeta.tone}>{statusMeta.label}</Badge></Td>
                      <Td className="text-ink-muted">{request.reviewer?.name ?? '—'}</Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-1">
                          {isPending && canApprove ? (
                            <>
                              <button
                                type="button"
                                aria-label="Approve request"
                                title="Approve"
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-green-50 hover:text-green-700"
                                onClick={() => setReview({ request, mode: 'approve' })}
                              >
                                <Check className="size-4" aria-hidden />
                              </button>
                              <button
                                type="button"
                                aria-label="Reject request"
                                title="Reject"
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                                onClick={() => setReview({ request, mode: 'reject' })}
                              >
                                <X className="size-4" aria-hidden />
                              </button>
                            </>
                          ) : null}
                          {isPending && (isOwn || canApprove) ? (
                            <button
                              type="button"
                              aria-label="Cancel request"
                              title="Cancel"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => setCancelling(request)}
                            >
                              <Ban className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {isPending && (isOwn || can('leave.update')) ? (
                            <button
                              type="button"
                              aria-label="Edit request"
                              title="Edit"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => { setEditing(request); setFormOpen(true) }}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {can('leave.delete') || isPending ? (
                            <button
                              type="button"
                              aria-label="Delete request"
                              title="Delete"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setDeleting(request)}
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

      <LeaveFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        request={editing}
        onSaved={() => query.refetch()}
      />

      <Modal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title="Leave request"
        description={viewing ? statusBadgeFor(LEAVE_TYPES, viewing.type).label : undefined}
        size="md"
      >
        {viewing ? (
          <dl className="divide-y divide-line text-sm">
            <DetailRow label="Requester" value={viewing.user?.name ?? '—'} />
            <DetailRow label="Dates" value={`${formatDate(viewing.start_date)} → ${formatDate(viewing.end_date)}`} />
            <DetailRow label="Days" value={viewing.days} />
            <DetailRow label="Status" value={statusBadgeFor(LEAVE_STATUS, viewing.status).label} />
            <DetailRow label="Reason" value={viewing.reason || '—'} />
            {viewing.reviewer?.name ? <DetailRow label="Reviewed by" value={viewing.reviewer.name} /> : null}
            {viewing.review_notes ? <DetailRow label="Review notes" value={viewing.review_notes} /> : null}
            {viewing.reviewed_at ? <DetailRow label="Reviewed at" value={formatDateTime(viewing.reviewed_at)} /> : null}
          </dl>
        ) : null}
      </Modal>

      {review ? (
        <ReviewModal
          request={review.request}
          mode={review.mode}
          onClose={() => setReview(null)}
          onDone={() => { setReview(null); query.refetch() }}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(cancelling)}
        onClose={() => setCancelling(null)}
        onConfirm={handleCancel}
        title="Cancel leave request?"
        message="The request will be marked as cancelled."
        confirmLabel="Cancel request"
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete leave request?"
        message="This permanently removes the request."
        confirmLabel="Delete"
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

function ReviewModal({ request, mode, onClose, onDone }) {
  const [notes, setNotes] = useState('')
  const [saving, setSaving] = useState(false)

  const submit = async () => {
    setSaving(true)
    try {
      if (mode === 'approve') {
        await leaveApi.approve(request.id, { review_notes: notes || null })
        toast.success('Leave request approved.')
      } else {
        await leaveApi.reject(request.id, { review_notes: notes || null })
        toast.success('Leave request rejected.')
      }
      onDone()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open
      onClose={onClose}
      title={mode === 'approve' ? 'Approve leave request' : 'Reject leave request'}
      description={request.user?.name ? `${request.user.name} · ${request.days} day(s)` : undefined}
      size="sm"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button
            variant={mode === 'approve' ? 'primary' : 'danger'}
            onClick={submit}
            loading={saving}
          >
            {mode === 'approve' ? 'Approve' : 'Reject'}
          </Button>
        </>
      }
    >
      <Field label="Notes" htmlFor="review-notes" hint="Optional message to the requester.">
        <Textarea id="review-notes" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} />
      </Field>
    </Modal>
  )
}
