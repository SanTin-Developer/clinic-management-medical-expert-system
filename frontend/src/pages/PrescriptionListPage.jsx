import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Pill, Trash2, ClipboardCheck, Clock, Calendar, XCircle } from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import { prescriptionApi } from '../api/prescriptions.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import PrescriptionFormModal from '../components/prescriptions/PrescriptionFormModal.jsx'
import { patientFullName } from '../utils/format.js'
import { statusBadgeFor, PRESCRIPTION_STATUS } from '../utils/status.js'

export default function PrescriptionListPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(['prescriptions'], (params) => prescriptionApi.list(params), { page })

  const all = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const items = status === 'all' ? all : all.filter((p) => p.status === status)

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await prescriptionApi.remove(deleting.id)
      toast.success('Prescription deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Prescriptions"
        description="Create and track medication prescriptions."
        breadcrumb={[{ label: 'Prescriptions' }]}
        actions={
          can('prescriptions.create') ? (
            <Button onClick={() => setFormOpen(true)}>
              <Pill className="size-4" aria-hidden /> New prescription
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard
          label="Total Prescriptions"
          value={stats.total ?? query.data?.total ?? 0}
          icon={Pill}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label="Pending"
          value={stats.pending ?? 0}
          icon={Clock}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label="Dispensed"
          value={stats.dispensed ?? 0}
          icon={ClipboardCheck}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="Cancelled"
          value={stats.cancelled ?? 0}
          icon={XCircle}
          tone="red"
          loading={query.isLoading}
        />
        <StatCard
          label="This Month"
          value={stats.this_month ?? 0}
          icon={Calendar}
          tone="blue"
          loading={query.isLoading}
        />
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <Tabs
            value={status}
            onChange={(v) => {
              setStatus(v)
              setPage(1)
            }}
            className="border-b-0"
            tabs={[
              { value: 'all', label: 'All' },
              { value: 'pending', label: 'Pending' },
              { value: 'dispensed', label: 'Dispensed' },
              { value: 'cancelled', label: 'Cancelled' },
            ]}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : all.length === 0 ? (
          <EmptyState
            title="No prescriptions yet"
            message="Create a prescription to get started."
            action={
              can('prescriptions.create') ? (
                <Button size="sm" onClick={() => setFormOpen(true)}>
                  New prescription
                </Button>
              ) : null
            }
          />
        ) : items.length === 0 ? (
          <EmptyState title="Nothing here" message="No prescriptions match the selected status." />
        ) : (
          <Table minWidth="min-w-[820px]">
            <THead>
              <Tr>
                <Th>Prescription</Th>
                <Th>Patient</Th>
                <Th>Doctor</Th>
                <Th>Items</Th>
                <Th>Status</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((rx) => {
                const statusMeta = statusBadgeFor(PRESCRIPTION_STATUS, rx.status)
                return (
                  <Tr key={rx.id} onClick={() => navigate(`/prescriptions/${rx.id}`)}>
                    <Td className="font-mono text-xs text-ink-muted">{rx.prescription_number}</Td>
                    <Td className="font-medium text-ink">{patientFullName(rx.patient)}</Td>
                    <Td>{rx.doctor?.name ?? '—'}</Td>
                    <Td>{rx.items?.length ?? 0}</Td>
                    <Td>
                      <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge>
                    </Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end">
                        {can('prescriptions.delete') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${rx.prescription_number}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeleting(rx)}
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
        )}

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <PrescriptionFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={() => query.refetch()} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete prescription?"
        message="This permanently deletes the prescription and its items."
        confirmLabel="Delete"
      />
    </div>
  )
}