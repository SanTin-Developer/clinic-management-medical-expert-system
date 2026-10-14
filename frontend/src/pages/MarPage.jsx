import { useState } from 'react'
import { toast } from 'sonner'
import { CalendarClock, CheckCircle2, ChevronDown, Pill, Plus, ShieldX, TimerOff } from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Dropdown, { DropdownItem } from '../components/ui/Dropdown.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import { medicationAdministrationApi } from '../api/nursing.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime, patientFullName } from '../utils/format.js'
import { MEDICATION_ROUTES, MEDICATION_STATUS, statusBadgeFor } from '../utils/status.js'
import MarFormModal from '../components/nursing/MarFormModal.jsx'

export default function MarPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('all')
  const [formOpen, setFormOpen] = useState(false)
  const [administering, setAdministering] = useState(null)
  const [statusAction, setStatusAction] = useState(null)

  const canRecord = can('medications.record')

  const query = usePaginated(['medication-administrations'], (params) => medicationAdministrationApi.list(params), {
    page,
    params: { status: status === 'all' ? undefined : status },
  })

  const records = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const administer = async () => {
    if (!administering) return
    try {
      await medicationAdministrationApi.administer(administering.id)
      toast.success('Medication administered.')
      setAdministering(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const updateStatus = async (newStatus) => {
    if (!statusAction) return
    try {
      await medicationAdministrationApi.setStatus(statusAction.id, newStatus)
      toast.success(`Status set to ${newStatus}.`)
      setStatusAction(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Medication Administration"
        description="Medication Administration Record (MAR) for admitted patients."
        actions={
          canRecord ? (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="size-4" aria-hidden /> Schedule medication
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <StatCard label="Total Doses" value={stats.total ?? 0} icon={Pill} tone="brand" loading={query.isLoading} />
        <StatCard label="Scheduled" value={stats.scheduled ?? 0} icon={CalendarClock} tone="amber" loading={query.isLoading} />
        <StatCard label="Administered" value={stats.administered ?? 0} icon={CheckCircle2} tone="green" loading={query.isLoading} />
        <StatCard label="Held / Refused" value={(stats.held ?? 0) + (stats.refused ?? 0)} icon={ShieldX} tone="navy" loading={query.isLoading} />
        <StatCard label="Missed" value={stats.missed ?? 0} icon={TimerOff} tone="red" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <Tabs
            value={status}
            onChange={(v) => { setStatus(v); setPage(1) }}
            className="border-b-0"
            tabs={[
              { value: 'all', label: 'All' },
              { value: 'scheduled', label: 'Scheduled' },
              { value: 'administered', label: 'Administered' },
              { value: 'held', label: 'Held' },
              { value: 'refused', label: 'Refused' },
              { value: 'missed', label: 'Missed' },
              { value: 'cancelled', label: 'Cancelled' },
            ]}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={6} cols={6} /></div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : records.length === 0 ? (
          <EmptyState title="No medication records" message="Schedule a medication to build the MAR." icon={Pill} />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[980px]">
              <THead>
                <Tr>
                  <Th>Patient</Th>
                  <Th>Medicine / Dose</Th>
                  <Th>Route</Th>
                  <Th>Scheduled</Th>
                  <Th>Administered</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {records.map((record) => {
                  const statusMeta = statusBadgeFor(MEDICATION_STATUS, record.status)
                  const routeMeta = statusBadgeFor(MEDICATION_ROUTES, record.route)
                  const actionable = record.status === 'scheduled'
                  return (
                    <Tr key={record.id}>
                      <Td className="font-medium text-ink">{patientFullName(record.patient)}</Td>
                      <Td>
                        <div className="text-ink">{record.medicine?.name ?? '—'}</div>
                        {record.dose ? <div className="text-xs text-ink-muted">{record.dose}</div> : null}
                      </Td>
                      <Td>
                        {record.route ? <Badge tone={routeMeta.tone}>{routeMeta.label}</Badge> : '—'}
                      </Td>
                      <Td className="text-ink-muted">{formatDateTime(record.scheduled_at)}</Td>
                      <Td className="text-ink-muted">
                        {record.administered_at ? (
                          <>
                            {formatDateTime(record.administered_at)}
                            {record.administered_by?.name ? (
                              <span className="block text-xs">by {record.administered_by.name}</span>
                            ) : null}
                          </>
                        ) : (
                          '—'
                        )}
                      </Td>
                      <Td><Badge tone={statusMeta.tone}>{statusMeta.label}</Badge></Td>
                      <Td>
                        <div className="flex justify-end gap-2">
                          {actionable && canRecord ? (
                            <>
                              <Button size="sm" onClick={() => setAdministering(record)}>Administer</Button>
                              <Dropdown
                                trigger={
                                  <Button size="sm" variant="outline" aria-label="More status options">
                                    More <ChevronDown className="size-4" aria-hidden />
                                  </Button>
                                }
                              >
                                <DropdownItem onClick={() => { updateStatus('held') }}>Held</DropdownItem>
                                <DropdownItem onClick={() => { updateStatus('refused') }}>Refused</DropdownItem>
                                <DropdownItem onClick={() => { updateStatus('missed') }}>Missed</DropdownItem>
                                <DropdownItem danger onClick={() => { updateStatus('cancelled') }}>Cancelled</DropdownItem>
                              </Dropdown>
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

      <MarFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={() => query.refetch()} />

      <ConfirmDialog
        open={Boolean(administering)}
        onClose={() => setAdministering(null)}
        onConfirm={administer}
        title="Administer medication?"
        message={
          administering
            ? `${patientFullName(administering.patient)} · ${administering.medicine?.name ?? 'medication'}${administering.dose ? ` (${administering.dose})` : ''}`
            : ''
        }
        confirmLabel="Administer"
        danger={false}
      />
    </div>
  )
}
