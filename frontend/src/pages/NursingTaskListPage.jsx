import { useState } from 'react'
import { toast } from 'sonner'
import { CircleCheckBig, ClipboardList, Clock, ListChecks, Plus, XCircle } from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import { nursingTaskApi } from '../api/nursing.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime, patientFullName } from '../utils/format.js'
import { NURSING_TASK_STATUS, NURSING_TASK_TYPES, TASK_PRIORITY, statusBadgeFor } from '../utils/status.js'
import NursingTaskFormModal from '../components/nursing/NursingTaskFormModal.jsx'

export default function NursingTaskListPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [formOpen, setFormOpen] = useState(false)
  const [completing, setCompleting] = useState(null)

  const query = usePaginated(['nursing-tasks'], (params) => nursingTaskApi.list(params), {
    page,
    params: { status: status === 'all' ? undefined : status },
  })

  const tasks = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const complete = async () => {
    if (!completing) return
    try {
      await nursingTaskApi.complete(completing.id)
      toast.success('Task completed.')
      setCompleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Nursing Tasks"
        description="Care activities scheduled for assigned patients."
        actions={
          can('nursing-tasks.create') ? (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="size-4" aria-hidden /> New task
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total Tasks" value={stats.total ?? 0} icon={ClipboardList} tone="brand" loading={query.isLoading} />
        <StatCard label="Pending" value={stats.pending ?? 0} icon={Clock} tone="amber" loading={query.isLoading} />
        <StatCard label="Completed" value={stats.completed ?? 0} icon={CircleCheckBig} tone="green" loading={query.isLoading} />
        <StatCard label="Cancelled" value={stats.cancelled ?? 0} icon={XCircle} tone="red" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <Tabs
            value={status}
            onChange={(v) => { setStatus(v); setPage(1) }}
            className="border-b-0"
            tabs={[
              { value: 'all', label: 'All' },
              { value: 'pending', label: 'Pending' },
              { value: 'in_progress', label: 'In progress' },
              { value: 'completed', label: 'Completed' },
              { value: 'cancelled', label: 'Cancelled' },
            ]}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={6} cols={5} /></div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : tasks.length === 0 ? (
          <EmptyState title="No nursing tasks" message="Create a task to schedule care for an assigned patient." icon={ListChecks} />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[900px]">
              <THead>
                <Tr>
                  <Th>Task</Th>
                  <Th>Patient</Th>
                  <Th>Assigned</Th>
                  <Th>Scheduled</Th>
                  <Th>Priority</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {tasks.map((task) => {
                  const typeMeta = statusBadgeFor(NURSING_TASK_TYPES, task.task_type)
                  const statusMeta = statusBadgeFor(NURSING_TASK_STATUS, task.status)
                  const priorityMeta = statusBadgeFor(TASK_PRIORITY, task.priority)
                  return (
                    <Tr key={task.id}>
                      <Td>
                        <div className="font-medium text-ink">{task.title}</div>
                        <Badge tone={typeMeta.tone} className="mt-1">{typeMeta.label}</Badge>
                      </Td>
                      <Td className="text-ink">{patientFullName(task.patient)}</Td>
                      <Td>{task.assigned_nurse?.name ?? '—'}</Td>
                      <Td className="text-ink-muted">{formatDateTime(task.scheduled_at)}</Td>
                      <Td><Badge tone={priorityMeta.tone}>{priorityMeta.label}</Badge></Td>
                      <Td><Badge tone={statusMeta.tone}>{statusMeta.label}</Badge></Td>
                      <Td>
                        <div className="flex justify-end">
                          {task.status !== 'completed' && task.status !== 'cancelled' && can('nursing-tasks.complete') ? (
                            <Button size="sm" variant="outline" onClick={() => setCompleting(task)}>
                              <CircleCheckBig className="size-4" aria-hidden /> Complete
                            </Button>
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

      <NursingTaskFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={() => query.refetch()} />

      <ConfirmDialog
        open={Boolean(completing)}
        onClose={() => setCompleting(null)}
        onConfirm={complete}
        title="Mark task complete?"
        message={completing ? `Mark “${completing.title}” as completed?` : ''}
        confirmLabel="Mark complete"
        danger={false}
      />
    </div>
  )
}
