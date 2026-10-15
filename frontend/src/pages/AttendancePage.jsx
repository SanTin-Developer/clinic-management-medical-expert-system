import { useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  CalendarCheck,
  Check,
  Clock,
  LogIn,
  LogOut,
  Pencil,
  Plus,
  Trash2,
  UserX,
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
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { attendanceApi } from '../api/attendance.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime } from '../utils/format.js'
import { ATTENDANCE_STATUS, ATTENDANCE_STATUS_OPTIONS, statusBadgeFor } from '../utils/status.js'
import AttendanceFormModal from '../components/attendance/AttendanceFormModal.jsx'

export default function AttendancePage() {
  const { can } = useAuth()
  const canManage = can('attendance.manage')
  const canClock = can('attendance.clock')
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [clocking, setClocking] = useState(false)

  const query = usePaginated(
    ['attendance', { search, status, from, to }],
    (params) =>
      attendanceApi.list({
        ...params,
        search: search || undefined,
        status: status || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
    { page },
  )

  const todayQuery = useQuery({
    queryKey: ['attendance', 'today'],
    queryFn: () => attendanceApi.today(),
    enabled: canClock,
  })

  const records = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const today = todayQuery.data?.data ?? null

  const refreshToday = () => {
    queryClient.invalidateQueries({ queryKey: ['attendance', 'today'] })
    query.refetch()
  }

  const clock = async (action) => {
    setClocking(true)
    try {
      await attendanceApi[action]()
      toast.success(action === 'clockIn' ? 'Clocked in.' : 'Clocked out.')
      refreshToday()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setClocking(false)
    }
  }

  const approve = async (record) => {
    try {
      await attendanceApi.approve(record.id)
      toast.success('Attendance approved.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await attendanceApi.remove(deleting.id)
      toast.success('Attendance record deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Staff Attendance"
        description={canManage
          ? 'Monitor clock-ins, approve records, and correct times.'
          : 'Clock in and out and review your attendance history.'}
        breadcrumb={[{ label: 'Staff Attendance' }]}
        actions={
          canManage ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden /> New record
            </Button>
          ) : null
        }
      />

      {canClock ? (
        <Card className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-sm font-medium text-ink-muted">Today</p>
            <p className="mt-1 text-lg font-semibold text-ink">
              {today?.clock_in ? `Clocked in at ${formatDateTime(today.clock_in)}` : 'Not clocked in yet'}
            </p>
            {today?.clock_out ? (
              <p className="text-sm text-ink-muted">Clocked out at {formatDateTime(today.clock_out)}</p>
            ) : null}
          </div>
          <div className="flex gap-2">
            <Button
              onClick={() => clock('clockIn')}
              loading={clocking}
              disabled={Boolean(today?.clock_in)}
            >
              <LogIn className="size-4" aria-hidden /> Clock in
            </Button>
            <Button
              variant="outline"
              onClick={() => clock('clockOut')}
              loading={clocking}
              disabled={!today?.clock_in || Boolean(today?.clock_out)}
            >
              <LogOut className="size-4" aria-hidden /> Clock out
            </Button>
          </div>
        </Card>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Records" value={stats.total ?? 0} icon={CalendarCheck} tone="brand" loading={query.isLoading} />
        <StatCard label="Present" value={stats.present ?? 0} icon={Clock} tone="green" loading={query.isLoading} />
        <StatCard label="Late" value={stats.late ?? 0} icon={Clock} tone="amber" loading={query.isLoading} />
        <StatCard label="Absent" value={stats.absent ?? 0} icon={UserX} tone="navy" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="grid grid-cols-1 gap-3 px-5 pt-4 sm:grid-cols-2 lg:grid-cols-4">
          {canManage ? (
            <div className="lg:col-span-1">
              <SearchInput
                placeholder="Search staff…"
                onDebounced={(value) => { setSearch(value); setPage(1) }}
              />
            </div>
          ) : null}
          <Select aria-label="Filter by status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All statuses</option>
            {ATTENDANCE_STATUS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Field label="From" htmlFor="att-from" className="space-y-1">
            <Input id="att-from" type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} />
          </Field>
          <Field label="To" htmlFor="att-to" className="space-y-1">
            <Input id="att-to" type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} />
          </Field>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={6} /></div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState title="Failed to load attendance" message={query.error?.message} onRetry={() => query.refetch()} />
          </div>
        ) : records.length === 0 ? (
          <EmptyState
            title="No attendance records"
            message={canManage ? 'No records match your filters.' : 'Clock in to start tracking your attendance.'}
            icon={CalendarCheck}
          />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[900px]">
              <THead>
                <Tr>
                  <Th>Staff</Th>
                  <Th>Date</Th>
                  <Th>Clock in</Th>
                  <Th>Clock out</Th>
                  <Th>Hours</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {records.map((record) => {
                  const statusMeta = statusBadgeFor(ATTENDANCE_STATUS, record.status)
                  return (
                    <Tr key={record.id}>
                      <Td className="font-medium text-ink">{record.user?.name ?? '—'}</Td>
                      <Td className="whitespace-nowrap text-ink-muted">{formatDateTime(record.work_date)}</Td>
                      <Td className="whitespace-nowrap text-ink-muted">{record.clock_in ? formatDateTime(record.clock_in) : '—'}</Td>
                      <Td className="whitespace-nowrap text-ink-muted">{record.clock_out ? formatDateTime(record.clock_out) : '—'}</Td>
                      <Td className="text-ink-muted">{record.hours_worked != null ? record.hours_worked.toFixed(2) : '—'}</Td>
                      <Td>
                        <div className="flex items-center gap-2">
                          <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge>
                          {record.approved_at ? <Badge tone="green">Approved</Badge> : null}
                        </div>
                      </Td>
                      <Td>
                        {canManage ? (
                          <div className="flex justify-end gap-1">
                            {!record.approved_at ? (
                              <button
                                type="button"
                                aria-label="Approve record"
                                title="Approve"
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-green-50 hover:text-green-700"
                                onClick={() => approve(record)}
                              >
                                <Check className="size-4" aria-hidden />
                              </button>
                            ) : null}
                            <button
                              type="button"
                              aria-label="Edit record"
                              title="Edit"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => { setEditing(record); setFormOpen(true) }}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                            <button
                              type="button"
                              aria-label="Delete record"
                              title="Delete"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setDeleting(record)}
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

      <AttendanceFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        record={editing}
        onSaved={() => query.refetch()}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete attendance record?"
        message="This permanently removes the record."
        confirmLabel="Delete"
      />
    </div>
  )
}
