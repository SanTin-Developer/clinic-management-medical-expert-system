import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Pencil, Plus, Sparkles, Trash2, Users } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import Select from '../../components/ui/Select.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import Table, { TBody, Td, Th, THead, Tr } from '../../components/ui/Table.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import TeamFormModal from '../../components/website/TeamFormModal.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

export default function WebsiteTeamPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'team', { search, status }],
    (params) => websiteAdminApi.listTeam({ ...params, search: search || undefined, is_published: status || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []

  const togglePublish = async (row) => {
    try {
      await websiteAdminApi.updateTeam(row.id, { is_published: !row.is_published })
      toast.success(row.is_published ? 'Profile unpublished.' : 'Profile published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteTeam(deleting.id)
      toast.success('Team profile deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <PageHeader
        title="Our Team"
        description="Public profiles of your doctors and clinic staff."
        actions={
          can('website.team.manage') ? (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="size-4" aria-hidden />
              New profile
            </Button>
          ) : null
        }
      />

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search team…" className="sm:max-w-xs" />
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }} className="sm:w-40" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="1">Published</option>
            <option value="0">Draft</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5"><RowSkeleton rows={5} cols={4} /></div>
        ) : query.isError ? (
          <div className="p-5"><ErrorState onRetry={query.refetch} /></div>
        ) : rows.length === 0 ? (
          <EmptyState title="No team profiles yet" message="Add your clinic's doctors and staff." icon={Users} />
        ) : (
          <div className="p-4">
            <Table>
              <THead>
                <Tr>
                  <Th>Name</Th>
                  <Th>Featured</Th>
                  <Th>Status</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </THead>
              <TBody>
                {rows.map((row) => (
                  <Tr key={row.id} onClick={() => navigate(`/website/team/${row.id}`)}>
                    <Td primary>
                      <div className="flex items-center gap-3">
                        {row.photo?.url ? (
                          <img src={row.photo.url} alt="" className="size-10 rounded-full object-cover" />
                        ) : (
                          <span className="grid size-10 place-items-center rounded-full bg-brand-soft text-sm font-bold text-brand">
                            {row.name?.charAt(0) ?? '?'}
                          </span>
                        )}
                        <div>
                          <div className="font-medium text-ink">{row.name}</div>
                          <div className="text-xs text-ink-muted">{row.position}</div>
                        </div>
                      </div>
                    </Td>
                    <Td>{row.is_featured ? <Badge tone="brand"><Sparkles className="size-3" aria-hidden />Featured</Badge> : '—'}</Td>
                    <Td trailing><Badge tone={row.is_published ? 'green' : 'gray'}>{row.is_published ? 'published' : 'draft'}</Badge></Td>
                    <Td onClick={(event) => event.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1">
                        {can('website.team.manage') ? (
                          <>
                            <Button size="sm" variant={row.is_published ? 'outline' : 'secondary'} onClick={() => togglePublish(row)}>
                              {row.is_published ? 'Unpublish' : 'Publish'}
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => { setEditing(row); setFormOpen(true) }} aria-label="Edit">
                              <Pencil className="size-4" aria-hidden />
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setDeleting(row)} aria-label="Delete">
                              <Trash2 className="size-4" aria-hidden />
                            </Button>
                          </>
                        ) : null}
                      </div>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          </div>
        )}

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <TeamFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={query.refetch} profile={editing} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete team profile"
        message={`Delete “${deleting?.name}”? This cannot be undone. If no other record uses the photo, it is also permanently deleted from Cloudinary.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
