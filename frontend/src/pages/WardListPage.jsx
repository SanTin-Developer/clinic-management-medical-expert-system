import { useState } from 'react'
import { toast } from 'sonner'
import { BedDouble, Building2, DoorOpen, Pencil, Plus, Trash2, UserCheck, XCircle } from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import { wardApi } from '../api/nursing.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { statusBadgeFor, WARD_TYPES } from '../utils/status.js'
import WardFormModal from '../components/nursing/WardFormModal.jsx'
import WardDetailModal from '../components/nursing/WardDetailModal.jsx'

export default function WardListPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [detailId, setDetailId] = useState(null)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(['wards'], (params) => wardApi.list(params), {
    page,
    params: { search: search || undefined },
  })

  const wards = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await wardApi.remove(deleting.id)
      toast.success('Ward deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Wards & Beds"
        description="Manage wards, rooms, and bed availability."
        actions={
          can('wards.manage') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden /> New ward
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Total Wards" value={stats.total ?? 0} icon={Building2} tone="brand" loading={query.isLoading} />
        <StatCard label="Active Wards" value={stats.active ?? 0} icon={DoorOpen} tone="green" loading={query.isLoading} />
        <StatCard label="Inactive" value={stats.inactive ?? 0} icon={XCircle} tone="navy" loading={query.isLoading} />
        <StatCard label="Currently Admitted" value={stats.admitted ?? 0} icon={UserCheck} tone="blue" loading={query.isLoading} />
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <SearchInput
            placeholder="Search by name or code…"
            onDebounced={(value) => { setSearch(value); setPage(1) }}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={5} /></div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : wards.length === 0 ? (
          <EmptyState
            title="No wards yet"
            message="Create a ward to start organizing rooms and beds."
            icon={Building2}
            action={
              can('wards.manage') ? (
                <Button size="sm" onClick={() => { setEditing(null); setFormOpen(true) }}>
                  New ward
                </Button>
              ) : null
            }
          />
        ) : (
          <div className="p-5 pt-4">
            <Table minWidth="min-w-[860px]">
              <THead>
                <Tr>
                  <Th>Ward</Th>
                  <Th>Code</Th>
                  <Th>Floor</Th>
                  <Th>Type</Th>
                  <Th>Rooms</Th>
                  <Th>Admitted</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {wards.map((ward) => {
                  const typeMeta = statusBadgeFor(WARD_TYPES, ward.ward_type)
                  return (
                    <Tr key={ward.id} onClick={() => setDetailId(ward.id)}>
                      <Td className="font-medium text-ink">{ward.name}</Td>
                      <Td className="font-mono text-xs text-ink-muted">{ward.code}</Td>
                      <Td>{ward.floor ?? '—'}</Td>
                      <Td><Badge tone={typeMeta.tone}>{typeMeta.label}</Badge></Td>
                      <Td>{ward.rooms_count ?? 0}</Td>
                      <Td>
                        <span className="inline-flex items-center gap-1 text-ink">
                          <BedDouble className="size-3.5 text-ink-muted" aria-hidden />
                          {ward.active_admissions_count ?? 0}
                        </span>
                      </Td>
                      <Td>
                        <Badge tone={ward.is_active ? 'green' : 'gray'}>
                          {ward.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-1">
                          {can('wards.manage') ? (
                            <>
                              <button
                                type="button"
                                aria-label={`Edit ${ward.name}`}
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                                onClick={() => { setEditing(ward); setFormOpen(true) }}
                              >
                                <Pencil className="size-4" aria-hidden />
                              </button>
                              <button
                                type="button"
                                aria-label={`Delete ${ward.name}`}
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                                onClick={() => setDeleting(ward)}
                              >
                                <Trash2 className="size-4" aria-hidden />
                              </button>
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

      <WardFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        ward={editing}
        onSaved={() => query.refetch()}
      />

      <WardDetailModal
        open={Boolean(detailId)}
        onClose={() => setDetailId(null)}
        wardId={detailId}
        onEdit={(ward) => { setEditing(ward); setFormOpen(true) }}
        onChanged={() => query.refetch()}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete ward?"
        message="This permanently deletes the ward. Wards with active admissions cannot be deleted."
        confirmLabel="Delete"
      />
    </div>
  )
}
