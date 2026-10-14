import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { UserPlus, Trash2, Pencil, Users } from 'lucide-react'
import { patientApi } from '../api/patients.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Badge from '../components/ui/Badge.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import PatientFormModal from '../components/patients/PatientFormModal.jsx'
import { ageFromDateOfBirth, patientFullName } from '../utils/format.js'

export default function PatientListPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(['patients'], (params) => patientApi.list(params), {
    page,
  })

  const items = query.data?.data ?? []
  const filtered = search
    ? items.filter((p) =>
        [p.first_name, p.last_name, p.patient_number, p.phone_number, p.email]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : items

  const canManage = can('patients.create') || can('patients.update')

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await patientApi.remove(deleting.id)
      toast.success('Patient deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const stats = query.data?.stats ?? {}

  return (
    <div className="space-y-6">
      <PageHeader
        title="Patients"
        description="Register and manage patient records."
        breadcrumb={[{ label: 'Patients' }]}
        actions={
          can('patients.create') ? (
            <Button
              onClick={() => {
                setEditing(null)
                setFormOpen(true)
              }}
            >
              <UserPlus className="size-4" aria-hidden /> New patient
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard label="Total Patients" value={stats.total ?? query.data?.total ?? items.length} icon={Users} tone="brand" loading={query.isLoading} />
        <StatCard label="New This Month" value={stats.new_this_month ?? 0} icon={Users} tone="green" loading={query.isLoading} />
        <StatCard label="Male" value={stats.male ?? 0} icon={Users} tone="blue" loading={query.isLoading} />
        <StatCard label="Female" value={stats.female ?? 0} icon={Users} tone="amber" loading={query.isLoading} />
        <StatCard label="With Allergies" value={stats.with_allergies ?? 0} icon={Users} tone="red" loading={query.isLoading} />
      </div>

      <Card>
        <div className="mb-4">
          <SearchInput
            placeholder="Search by name, patient number, phone or email…"
            className="w-full sm:w-80"
            onDebounced={setSearch}
            aria-label="Search patients"
          />
        </div>

        {query.isLoading ? (
          <RowSkeleton rows={6} cols={5} />
        ) : query.isError ? (
          <ErrorState message={query.error.message} onRetry={() => query.refetch()} />
        ) : items.length === 0 ? (
          <EmptyState
            title="No patients yet"
            message="Register the first patient to get started."
            action={
              can('patients.create') ? (
                <Button
                  size="sm"
                  onClick={() => {
                    setEditing(null)
                    setFormOpen(true)
                  }}
                >
                  New patient
                </Button>
              ) : null
            }
          />
        ) : filtered.length === 0 ? (
          <EmptyState title="No matches" message="No patients match your search." />
        ) : (
          <Table minWidth="min-w-[720px]">
            <THead>
              <Tr>
                <Th>Patient number</Th>
                <Th>Name</Th>
                <Th>Age / Gender</Th>
                <Th>Phone</Th>
                <Th>Blood type</Th>
                <Th>
                  <span className="sr-only">Actions</span>
                </Th>
              </Tr>
            </THead>
            <TBody>
              {filtered.map((p) => {
                const age = ageFromDateOfBirth(p.date_of_birth)
                return (
                  <Tr key={p.id} onClick={() => navigate(`/patients/${p.id}`)}>
                    <Td className="font-mono text-xs text-ink-muted">{p.patient_number}</Td>
                    <Td className="font-medium text-ink">{patientFullName(p)}</Td>
                    <Td className="text-ink-muted">
                      {age !== null ? `${age} y` : '—'} ·{' '}
                      {p.gender ? p.gender.charAt(0).toUpperCase() + p.gender.slice(1) : '—'}
                    </Td>
                    <Td>{p.phone_number ?? '—'}</Td>
                    <Td>
                      {p.blood_type ? (
                        <Badge tone="brand">{p.blood_type}</Badge>
                      ) : (
                        <span className="text-ink-muted">—</span>
                      )}
                    </Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        {canManage ? (
                          <button
                            type="button"
                            aria-label={`Edit ${patientFullName(p)}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                            onClick={() => {
                              setEditing(p)
                              setFormOpen(true)
                            }}
                          >
                            <Pencil className="size-4" aria-hidden />
                          </button>
                        ) : null}
                        {can('patients.delete') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${patientFullName(p)}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeleting(p)}
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

        <Pagination
          page={query.data?.current_page ?? 1}
          lastPage={query.data?.last_page ?? 1}
          total={query.data?.total}
          onChange={setPage}
        />
      </Card>

      <PatientFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        initial={editing}
        onSaved={() => query.refetch()}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete patient?"
        message={`This will permanently remove ${deleting ? patientFullName(deleting) : 'this patient'} and orphan their clinical records. This cannot be undone.`}
        confirmLabel="Delete"
      />
    </div>
  )
}