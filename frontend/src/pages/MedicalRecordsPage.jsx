import { useState } from 'react'
import { toast } from 'sonner'
import { Activity, FileText, Microscope, Plus, Stethoscope, Trash2 } from 'lucide-react'
import { medicalRecordApi, consultationApi, diagnosisApi, vitalSignApi } from '../api/clinical.js'
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

  const active = { records, consultations, diagnoses, vitals }[tab]
  const activeItems = active.data?.data ?? []

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
        <Td className="font-medium text-ink">{patientFullName(row.patient)}</Td>
        <Td className="text-ink-muted">{row.appointment?.appointment_number ?? '—'}</Td>
        <Td>{row.doctor?.name ?? '—'}</Td>
        <Td className="max-w-56 truncate text-ink-muted">{truncate(row.treatment_plan, 50)}</Td>
        <Td className="text-ink-muted">{formatDateTime(row.created_at)}</Td>
      </>
    ),
    consultations: (row) => (
      <>
        <Td className="font-medium text-ink">{patientFullName(row.patient)}</Td>
        <Td className="max-w-56 truncate text-ink-muted">{row.chief_complaint || '—'}</Td>
        <Td>{row.doctor?.name ?? '—'}</Td>
        <Td className="max-w-56 truncate text-ink-muted">{row.assessment || '—'}</Td>
        <Td className="text-ink-muted">{formatDateTime(row.created_at)}</Td>
      </>
    ),
    diagnoses: (row) => {
      const severity = row.severity ? DISEASE_SEVERITY[row.severity] : null
      return (
        <>
          <Td className="font-medium text-ink">{patientFullName(row.patient)}</Td>
          <Td>{row.diagnosis_name}</Td>
          <Td>{row.doctor?.name ?? '—'}</Td>
          <Td>
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
        <Td className="font-medium text-ink">{patientFullName(row.patient)}</Td>
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

  return (
    <div>
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
                <Tr key={row.id}>
                  {renderRow[tab](row)}
                  <Td>
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