import { useState } from 'react'
import { toast } from 'sonner'
import { Activity, FileText, Microscope, Plus, Stethoscope, Trash2, CalendarDays, CheckCircle2, Clock } from 'lucide-react'
import { medicalRecordApi, consultationApi, diagnosisApi, vitalSignApi } from '../api/clinical.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import MedicalRecordFormModal from '../components/medical/MedicalRecordFormModal.jsx'
import ConsultationFormModal from '../components/medical/ConsultationFormModal.jsx'
import DiagnosisFormModal from '../components/medical/DiagnosisFormModal.jsx'
import VitalSignFormModal from '../components/medical/VitalSignFormModal.jsx'
import { formatDateTime, patientFullName, truncate } from '../utils/format.js'
import { DISEASE_SEVERITY } from '../utils/status.js'

const PERSISTED_TABS = ['records', 'consultations', 'diagnoses', 'vitals']

export default function MedicalRecordsPage() {
  const { can } = useAuth()
  const [tab, setTab] = useState('records')
  const [page, setPage] = useState(1)

  const records = usePaginated(['medical-records'], (params) => medicalRecordApi.list(params), { page })
  const consultations = usePaginated(['consultations'], (params) => consultationApi.list(params), { page })
  const diagnoses = usePaginated(['diagnoses'], (params) => diagnosisApi.list(params), { page })
  const vitals = usePaginated(['vital-signs'], (params) => vitalSignApi.list(params), { page })

  const [createOpen, setCreateOpen] = useState(false)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)
  const [viewing, setViewing] = useState(null)

  const active = { records, consultations, diagnoses, vitals }[tab]
  const activeItems = active.data?.data ?? []
  const stats = active.data?.stats ?? {}

  const canCreateTab =
    (tab === 'records' && can('medical-records.create')) ||
    (tab === 'consultations' && can('consultations.create')) ||
    (tab === 'diagnoses' && can('diagnoses.create')) ||
    (tab === 'vitals' && can('vitals.create'))

  const removers = {
    records: medicalRecordApi.remove,
    consultations: consultationApi.remove,
    diagnoses: diagnosisApi.remove,
    vitals: vitalSignApi.remove,
  }

  const confirmDelete = async () => {
    if (!deleting) return
    setBusy(true)
    try {
      await removers[deleting.tab](deleting.id)
      toast.success('Deleted.')
      setDeleting(null)
      active.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  const refetchAll = () => {
    if (PERSISTED_TABS.includes(tab)) active.refetch()
  }

  const headers = {
    records: ['Patient', 'Appointment', 'Doctor', 'Treatment plan', 'Created'],
    consultations: ['Patient', 'Chief complaint', 'Doctor', 'Assessment', 'Created'],
    diagnoses: ['Patient', 'Diagnosis', 'Doctor', 'Status', 'Severity'],
    vitals: ['Patient', 'Temp', 'Blood pressure', 'Heart rate', 'O₂ sat'],
  }

  const renderRow = {
    records: (row) => (
      <>
        <Td primary className="font-medium text-ink">{patientFullName(row.patient)}</Td>
        <Td leading className="text-ink-muted">{row.appointment?.appointment_number ?? '—'}</Td>
        <Td>{row.doctor?.name ?? '—'}</Td>
        <Td className="max-w-56 truncate text-ink-muted">{truncate(row.treatment_plan, 50)}</Td>
        <Td className="text-ink-muted">{formatDateTime(row.created_at)}</Td>
      </>
    ),
    consultations: (row) => (
      <>
        <Td primary className="font-medium text-ink">{patientFullName(row.patient)}</Td>
        <Td className="max-w-56 truncate text-ink-muted">{row.chief_complaint || '—'}</Td>
        <Td>{row.doctor?.name ?? '—'}</Td>
        <Td className="max-w-56 truncate text-ink-muted">{row.assessment || '—'}</Td>
        <Td leading className="text-ink-muted">{formatDateTime(row.created_at)}</Td>
      </>
    ),
    diagnoses: (row) => {
      const severity = row.severity ? DISEASE_SEVERITY[row.severity] : null
      return (
        <>
          <Td primary className="font-medium text-ink">{patientFullName(row.patient)}</Td>
          <Td>{row.diagnosis_name}</Td>
          <Td>{row.doctor?.name ?? '—'}</Td>
          <Td trailing>
            {row.is_confirmed ? (
              <Badge tone="green">Confirmed</Badge>
            ) : (
              <Badge tone="amber">Provisional</Badge>
            )}
          </Td>
          <Td>
            {severity ? (
              <Badge tone={severity.tone}>{severity.label}</Badge>
            ) : (
              <span className="text-ink-muted">—</span>
            )}
          </Td>
        </>
      )
    },
    vitals: (row) => (
      <>
        <Td primary className="font-medium text-ink">{patientFullName(row.patient)}</Td>
        <Td>{row.temperature != null ? `${row.temperature}°C` : '—'}</Td>
        <Td>
          {row.blood_pressure_systolic || row.blood_pressure_diastolic
            ? `${row.blood_pressure_systolic ?? '—'}/${row.blood_pressure_diastolic ?? '—'}`
            : '—'}
        </Td>
        <Td>{row.heart_rate ?? '—'}</Td>
        <Td>{row.oxygen_saturation != null ? `${row.oxygen_saturation}%` : '—'}</Td>
      </>
    ),
  }

  const meta = {
    records: { icon: FileText, label: 'Medical Record' },
    consultations: { icon: Stethoscope, label: 'Consultation' },
    diagnoses: { icon: Microscope, label: 'Diagnosis' },
    vitals: { icon: Activity, label: 'Vital Signs' },
  }

  const detailFor = (tabKey, row) => {
    if (!row) return { title: '', subtitle: undefined, badges: [], fields: [] }
    const subtitle = patientFullName(row.patient)
    if (tabKey === 'records') {
      return {
        title: meta.records.label,
        subtitle,
        badges: [],
        fields: [
          { label: 'Patient', value: patientFullName(row.patient) },
          { label: 'Appointment', value: row.appointment?.appointment_number },
          { label: 'Doctor', value: row.doctor?.name },
          { label: 'Treatment plan', value: row.treatment_plan },
          { label: 'Notes', value: row.notes },
          { label: 'Created', value: row.created_at ? formatDateTime(row.created_at) : '—' },
        ],
      }
    }
    if (tabKey === 'consultations') {
      return {
        title: meta.consultations.label,
        subtitle,
        badges: [],
        fields: [
          { label: 'Patient', value: patientFullName(row.patient) },
          { label: 'Chief complaint', value: row.chief_complaint },
          { label: 'Doctor', value: row.doctor?.name },
          { label: 'Assessment', value: row.assessment },
          { label: 'Plan', value: row.plan },
          { label: 'Created', value: row.created_at ? formatDateTime(row.created_at) : '—' },
        ],
      }
    }
    if (tabKey === 'diagnoses') {
      const severity = row.severity ? DISEASE_SEVERITY[row.severity] : null
      return {
        title: row.diagnosis_name ?? meta.diagnoses.label,
        subtitle,
        badges: [
          row.is_confirmed ? { label: 'Confirmed', tone: 'green' } : { label: 'Provisional', tone: 'amber' },
          severity ? { label: severity.label, tone: severity.tone } : null,
        ],
        fields: [
          { label: 'Patient', value: patientFullName(row.patient) },
          { label: 'Diagnosis', value: row.diagnosis_name },
          { label: 'ICD code', value: row.icd_code },
          { label: 'Doctor', value: row.doctor?.name },
          { label: 'Severity', value: severity?.label },
          { label: 'Notes', value: row.notes },
          { label: 'Created', value: row.created_at ? formatDateTime(row.created_at) : '—' },
        ],
      }
    }
    return {
      title: meta.vitals.label,
      subtitle,
      badges: [],
      fields: [
        { label: 'Patient', value: patientFullName(row.patient) },
        { label: 'Temperature', value: row.temperature != null ? `${row.temperature}°C` : '—' },
        {
          label: 'Blood pressure',
          value:
            row.blood_pressure_systolic || row.blood_pressure_diastolic
              ? `${row.blood_pressure_systolic ?? '—'}/${row.blood_pressure_diastolic ?? '—'}`
              : '—',
        },
        { label: 'Heart rate', value: row.heart_rate != null ? `${row.heart_rate} bpm` : '—' },
        { label: 'Respiratory rate', value: row.respiratory_rate != null ? `${row.respiratory_rate} /min` : '—' },
        { label: 'O₂ saturation', value: row.oxygen_saturation != null ? `${row.oxygen_saturation}%` : '—' },
        { label: 'Recorded', value: row.created_at ? formatDateTime(row.created_at) : '—' },
      ],
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Medical Records"
        description="Clinical records, consultations, diagnoses and vital signs."
        breadcrumb={[{ label: 'Medical Records' }]}
        actions={
          canCreateTab ? (
            <Button onClick={() => setCreateOpen(true)}>
              <Plus className="size-4" aria-hidden /> New {meta[tab].label}
            </Button>
          ) : null
        }
      />

      <div className="stagger-children grid grid-cols-2 gap-4 xl:grid-cols-5">
        {tab === 'records' ? (
          <>
            <StatCard
              label="Total Records"
              value={stats.total ?? active.data?.total ?? 0}
              icon={FileText}
              tone="brand"
              loading={active.isLoading}
            />
            <StatCard
              label="New This Month"
              value={stats.this_month ?? 0}
              icon={CalendarDays}
              tone="blue"
              loading={active.isLoading}
            />
          </>
        ) : tab === 'consultations' ? (
          <>
            <StatCard
              label="Total Consultations"
              value={stats.total ?? active.data?.total ?? 0}
              icon={Stethoscope}
              tone="brand"
              loading={active.isLoading}
            />
            <StatCard
              label="New This Month"
              value={stats.this_month ?? 0}
              icon={CalendarDays}
              tone="blue"
              loading={active.isLoading}
            />
          </>
        ) : tab === 'diagnoses' ? (
          <>
            <StatCard
              label="Total Diagnoses"
              value={stats.total ?? active.data?.total ?? 0}
              icon={Microscope}
              tone="brand"
              loading={active.isLoading}
            />
            <StatCard
              label="Confirmed"
              value={stats.confirmed ?? 0}
              icon={CheckCircle2}
              tone="green"
              loading={active.isLoading}
            />
            <StatCard
              label="Provisional"
              value={stats.provisional ?? 0}
              icon={Clock}
              tone="amber"
              loading={active.isLoading}
            />
            <StatCard
              label="New This Month"
              value={stats.this_month ?? 0}
              icon={CalendarDays}
              tone="blue"
              loading={active.isLoading}
            />
          </>
        ) : (
          <>
            <StatCard
              label="Total Vital Sign Records"
              value={stats.total ?? active.data?.total ?? 0}
              icon={Activity}
              tone="brand"
              loading={active.isLoading}
            />
            <StatCard
              label="New This Month"
              value={stats.this_month ?? 0}
              icon={CalendarDays}
              tone="blue"
              loading={active.isLoading}
            />
          </>
        )}
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <Tabs
            value={tab}
            onChange={(v) => {
              setTab(v)
              setPage(1)
            }}
            className="border-b-0"
            tabs={[
              { value: 'records', label: 'Medical Records' },
              { value: 'consultations', label: 'Consultations' },
              { value: 'diagnoses', label: 'Diagnoses' },
              { value: 'vitals', label: 'Vital Signs' },
            ]}
          />
        </div>

        {active.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : active.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{active.error.message}</p>
        ) : activeItems.length === 0 ? (
          <EmptyState
            title={`No ${meta[tab].label.toLowerCase()}s yet`}
            message="Records you add will appear here."
            action={
              canCreateTab ? (
                <Button size="sm" onClick={() => setCreateOpen(true)}>
                  New {meta[tab].label}
                </Button>
              ) : null
            }
          />
        ) : (
          <Table minWidth="min-w-[820px]">
            <THead>
              <Tr>
                {headers[tab].map((h) => (
                  <Th key={h}>{h}</Th>
                ))}
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {activeItems.map((row) => (
                <Tr key={row.id} onClick={() => setViewing({ tab, row })}>
                  {renderRow[tab](row)}
                  <Td onClick={(e) => e.stopPropagation()}>
                    <div className="flex justify-end">
                      <button
                        type="button"
                        aria-label="Delete"
                        className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                        onClick={() => setDeleting({ id: row.id, tab })}
                        disabled={!canCreateTab}
                      >
                        <Trash2 className="size-4" aria-hidden />
                      </button>
                    </div>
                  </Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}

        <Pagination
          page={active.data?.current_page ?? 1}
          lastPage={active.data?.last_page ?? 1}
          total={active.data?.total}
          onChange={setPage}
        />
      </Card>

      {tab === 'records' ? (
        <MedicalRecordFormModal open={createOpen} onClose={() => setCreateOpen(false)} onSaved={refetchAll} />
      ) : null}
      {tab === 'consultations' ? (
        <ConsultationFormModal open={createOpen} onClose={() => setCreateOpen(false)} onSaved={refetchAll} />
      ) : null}
      {tab === 'diagnoses' ? (
        <DiagnosisFormModal open={createOpen} onClose={() => setCreateOpen(false)} onSaved={refetchAll} />
      ) : null}
      {tab === 'vitals' ? (
        <VitalSignFormModal open={createOpen} onClose={() => setCreateOpen(false)} onSaved={refetchAll} />
      ) : null}

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing ? detailFor(viewing.tab, viewing.row).title : ''}
        subtitle={viewing ? detailFor(viewing.tab, viewing.row).subtitle : undefined}
        badges={viewing ? detailFor(viewing.tab, viewing.row).badges : []}
        fields={viewing ? detailFor(viewing.tab, viewing.row).fields : []}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete record?"
        message="This will permanently remove the record. This cannot be undone."
        confirmLabel="Delete"
        loading={busy}
      />
    </div>
  )
}