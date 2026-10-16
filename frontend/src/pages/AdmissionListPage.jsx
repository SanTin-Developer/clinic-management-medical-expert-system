import { useState } from 'react'
import { toast } from 'sonner'
import { BedDouble, CalendarPlus, ClipboardCheck, LogOut, UserCheck, XCircle } from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import { admissionApi } from '../api/nursing.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { statusBadgeFor, ADMISSION_STATUS } from '../utils/status.js'
import { formatDateTime, patientFullName } from '../utils/format.js'
import AdmissionFormModal from '../components/nursing/AdmissionFormModal.jsx'

export default function AdmissionListPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [formOpen, setFormOpen] = useState(false)
  const [action, setAction] = useState(null)
  const [viewing, setViewing] = useState(null)

  const query = usePaginated(['admissions'], (params) => admissionApi.list(params), {
    page,
    params: { status: status === 'all' ? undefined : status },
  })

  const admissions = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const runAction = async () => {
    if (!action) return
    try {
      if (action.type === 'discharge') await admissionApi.discharge(action.record.id)
      if (action.type === 'cancel') await admissionApi.cancel(action.record.id)
      toast.success(action.type === 'discharge' ? 'Patient discharged.' : 'Admission cancelled.')
      setAction(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Admissions"
        description="Track admitted patients and manage discharges."
        actions={
          can('admissions.create') ? (
            <Button onClick={() => setFormOpen(true)}>
              <CalendarPlus className="size-4" aria-hidden /> Admit patient
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Admissions" value={stats.total ?? 0} icon={BedDouble} tone="brand" loading={query.isLoading} />
        <StatCard label="Admitted" value={stats.admitted ?? 0} icon={UserCheck} tone="blue" loading={query.isLoading} />
        <StatCard label="Discharged" value={stats.discharged ?? 0} icon={ClipboardCheck} tone="green" loading={query.isLoading} />
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
              { value: 'admitted', label: 'Admitted' },
              { value: 'discharged', label: 'Discharged' },
              { value: 'cancelled', label: 'Cancelled' },
            ]}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={6} cols={5} /></div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : admissions.length === 0 ? (
          <EmptyState title="No admissions" message="Admit a patient to create the first record." icon={BedDouble} />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[900px]">
              <THead>
                <Tr>
                  <Th>Patient</Th>
                  <Th>Ward / Bed</Th>
                  <Th>Attending</Th>
                  <Th>Admitted</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {admissions.map((a) => {
                  const meta = statusBadgeFor(ADMISSION_STATUS, a.status)
                  return (
                    <Tr key={a.id} onClick={() => setViewing(a)}>
                      <Td className="font-medium text-ink">{patientFullName(a.patient)}</Td>
                      <Td>
                        {a.ward?.name ?? '—'}
                        {a.bed?.label ? ` · Bed ${a.bed.label}` : ''}
                      </Td>
                      <Td>{a.attending_doctor?.name ?? '—'}</Td>
                      <Td className="text-ink-muted">{formatDateTime(a.admitted_at)}</Td>
                      <Td><Badge tone={meta.tone}>{meta.label}</Badge></Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-2">
                          {a.status === 'admitted' && can('admissions.discharge') ? (
                            <Button size="sm" variant="outline" onClick={() => setAction({ type: 'discharge', record: a })}>
                              <LogOut className="size-4" aria-hidden /> Discharge
                            </Button>
                          ) : null}
                          {a.status === 'admitted' && can('admissions.update') ? (
                            <Button size="sm" variant="ghost" onClick={() => setAction({ type: 'cancel', record: a })}>
                              Cancel
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

      <AdmissionFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={() => query.refetch()} />

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing ? patientFullName(viewing.patient) : 'Admission'}
        subtitle={viewing?.ward?.name ?? undefined}
        badges={viewing ? [statusBadgeFor(ADMISSION_STATUS, viewing.status)] : []}
        fields={
          viewing
            ? [
                { label: 'Patient number', value: viewing.patient?.patient_number },
                { label: 'Ward', value: viewing.ward?.name },
                { label: 'Bed', value: viewing.bed?.label },
                { label: 'Attending doctor', value: viewing.attending_doctor?.name },
                { label: 'Admitted', value: viewing.admitted_at ? formatDateTime(viewing.admitted_at) : '—' },
                { label: 'Discharged', value: viewing.discharged_at ? formatDateTime(viewing.discharged_at) : '—' },
                { label: 'Reason', value: viewing.reason },
                { label: 'Notes', value: viewing.notes },
              ]
            : []
        }
      />

      <ConfirmDialog
        open={Boolean(action)}
        onClose={() => setAction(null)}
        onConfirm={runAction}
        title={action?.type === 'discharge' ? 'Discharge patient?' : 'Cancel admission?'}
        message={
          action?.type === 'discharge'
            ? 'This frees the assigned bed and closes the admission.'
            : 'This cancels the admission and frees the assigned bed.'
        }
        confirmLabel={action?.type === 'discharge' ? 'Discharge' : 'Cancel admission'}
        danger={action?.type === 'cancel'}
      />
    </div>
  )
}
