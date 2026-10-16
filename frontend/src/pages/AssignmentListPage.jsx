import { useState } from 'react'
import { toast } from 'sonner'
import { CheckCircle2, Clock, Plus, UserCog, Users, XCircle } from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Field from '../components/ui/Field.jsx'
import Input from '../components/ui/Input.jsx'
import Select from '../components/ui/Select.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import { assignmentApi } from '../api/nursing.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime, patientFullName } from '../utils/format.js'
import { ASSIGNMENT_STATUS, statusBadgeFor } from '../utils/status.js'
import AssignmentFormModal from '../components/nursing/AssignmentFormModal.jsx'

const SCOPE_OPTIONS = [
  { value: '', label: 'All scopes' },
  { value: 'patient', label: 'Patient only' },
  { value: 'ward', label: 'Ward only' },
]

const STATUS_OPTIONS = [
  { value: '', label: 'All statuses' },
  { value: 'active', label: 'Active' },
  { value: 'ended', label: 'Ended' },
]

export default function AssignmentListPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [shift, setShift] = useState('')
  const [type, setType] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [ending, setEnding] = useState(null)
  const [viewing, setViewing] = useState(null)

  const canManage = can('assignments.manage')

  const query = usePaginated(['assignments'], (params) => assignmentApi.list(params), {
    page,
    params: {
      shift: shift || undefined,
      type: type || undefined,
      status: status || undefined,
    },
  })

  const assignments = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const end = async () => {
    if (!ending) return
    try {
      await assignmentApi.end(ending.id)
      toast.success('Assignment ended.')
      setEnding(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Nurse Assignments"
        description="Who is responsible for which patient or ward."
        actions={
          canManage ? (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="size-4" aria-hidden /> New assignment
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total Assignments" value={stats.total ?? 0} icon={UserCog} tone="brand" loading={query.isLoading} />
        <StatCard label="Active now" value={stats.active ?? 0} icon={CheckCircle2} tone="green" loading={query.isLoading} />
        <StatCard label="Upcoming" value={stats.upcoming ?? 0} icon={Clock} tone="amber" loading={query.isLoading} />
        <StatCard label="Ended" value={stats.ended ?? 0} icon={XCircle} tone="navy" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-end">
          <Field label="Shift" htmlFor="asg-filter-shift" className="sm:w-48">
            <Input
              id="asg-filter-shift"
              value={shift}
              onChange={(e) => { setShift(e.target.value); setPage(1) }}
              placeholder="e.g. Morning"
            />
          </Field>
          <Field label="Scope" htmlFor="asg-filter-type" className="sm:w-48">
            <Select id="asg-filter-type" value={type} onChange={(e) => { setType(e.target.value); setPage(1) }}>
              {SCOPE_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
          <Field label="Status" htmlFor="asg-filter-status" className="sm:w-48">
            <Select id="asg-filter-status" value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              {STATUS_OPTIONS.map((o) => (
                <option key={o.value} value={o.value}>{o.label}</option>
              ))}
            </Select>
          </Field>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={6} cols={5} /></div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : assignments.length === 0 ? (
          <EmptyState title="No assignments" message="Assign a nurse to a patient or ward to begin." icon={Users} />
        ) : (
          <div className="p-5">
            <Table minWidth="min-w-[980px]">
              <THead>
                <Tr>
                  <Th>Nurse</Th>
                  <Th>Assigned to</Th>
                  <Th>Shift</Th>
                  <Th>Starts</Th>
                  <Th>Ends</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {assignments.map((a) => {
                  const meta = statusBadgeFor(ASSIGNMENT_STATUS, a.status)
                  return (
                    <Tr key={a.id} onClick={() => setViewing(a)}>
                      <Td className="font-medium text-ink">{a.nurse?.name ?? '—'}</Td>
                      <Td>
                        {a.patient ? (
                          <span className="inline-flex items-center gap-1.5"><Users className="size-3.5 text-ink-muted" aria-hidden />{patientFullName(a.patient)}</span>
                        ) : a.ward ? (
                          <span className="text-ink">Ward · {a.ward.name}</span>
                        ) : (
                          '—'
                        )}
                      </Td>
                      <Td>{a.shift ?? '—'}</Td>
                      <Td className="text-ink-muted">
                        <span className="inline-flex items-center gap-1"><Clock className="size-3.5" aria-hidden />{formatDateTime(a.starts_at)}</span>
                      </Td>
                      <Td className="text-ink-muted">{a.ends_at ? formatDateTime(a.ends_at) : '—'}</Td>
                      <Td><Badge tone={meta.tone}>{meta.label}</Badge></Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-2">
                          {a.status === 'active' && canManage ? (
                            <>
                              <Button size="sm" variant="outline" onClick={() => setEditing(a)}>Edit</Button>
                              <Button size="sm" variant="outline" onClick={() => setEnding(a)}>End</Button>
                            </>
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

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <AssignmentFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={() => query.refetch()}
      />

      <AssignmentFormModal
        open={Boolean(editing)}
        assignment={editing}
        onClose={() => setEditing(null)}
        onSaved={() => query.refetch()}
      />

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.nurse?.name ?? 'Assignment'}
        subtitle={
          viewing?.patient
            ? `Patient · ${patientFullName(viewing.patient)}`
            : viewing?.ward
              ? `Ward · ${viewing.ward.name}`
              : undefined
        }
        badges={viewing ? [statusBadgeFor(ASSIGNMENT_STATUS, viewing.status)] : []}
        fields={
          viewing
            ? [
                { label: 'Nurse', value: viewing.nurse?.name },
                { label: 'Patient', value: viewing.patient ? patientFullName(viewing.patient) : '—' },
                { label: 'Ward', value: viewing.ward?.name },
                { label: 'Shift', value: viewing.shift },
                { label: 'Starts', value: viewing.starts_at ? formatDateTime(viewing.starts_at) : '—' },
                { label: 'Ends', value: viewing.ends_at ? formatDateTime(viewing.ends_at) : '—' },
                { label: 'Notes', value: viewing.notes },
              ]
            : []
        }
      />

      <ConfirmDialog
        open={Boolean(ending)}
        onClose={() => setEnding(null)}
        onConfirm={end}
        title="End assignment?"
        message={ending ? `End ${ending.nurse?.name ?? 'this nurse'}'s assignment? They will lose access to the patient unless covered by another assignment.` : ''}
        confirmLabel="End assignment"
      />
    </div>
  )
}
