import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Pencil, Trash2, Globe, EyeOff, FileText } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import StatCard from '../../components/ui/StatCard.jsx'
import Select from '../../components/ui/Select.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import Table, { TBody, Td, Th, THead, Tr } from '../../components/ui/Table.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import PageFormModal from '../../components/website/PageFormModal.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

export default function WebsitePagesPage() {
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
    ['website', 'admin', 'pages', { search, status }],
    (params) => websiteAdminApi.listPages({ ...params, search: search || undefined, status: status || undefined }),
    { page },
  )

  const rows = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const togglePublish = async (row) => {
    try {
      if (row.status === 'published') {
        await websiteAdminApi.unpublishPage(row.id)
        toast.success('Page unpublished.')
      } else {
        await websiteAdminApi.publishPage(row.id)
        toast.success('Page published.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deletePage(deleting.id)
      toast.success('Page deleted.')
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
        title="Pages"
        description="Manage informational pages such as About, Privacy Policy and Terms of Use."
        actions={
          can('website.pages.manage') ? (
            <Button
              onClick={() => {
                setEditing(null)
                setFormOpen(true)
              }}
            >
              <Plus className="size-4" aria-hidden />
              New page
            </Button>
          ) : null
        }
      />

      <div className="mb-5 grid gap-4 sm:grid-cols-3">
        <StatCard label="Total pages" value={stats.total ?? 0} icon={FileText} tone="brand" loading={query.isLoading} />
        <StatCard label="Published" value={stats.published ?? 0} icon={Globe} tone="green" loading={query.isLoading} />
        <StatCard label="Drafts" value={stats.draft ?? 0} icon={EyeOff} tone="amber" loading={query.isLoading} />
      </div>

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput onDebounced={(value) => { setSearch(value); setPage(1) }} placeholder="Search pages…" className="sm:max-w-xs" />
          <Select
            value={status}
            onChange={(e) => { setStatus(e.target.value); setPage(1) }}
            className="sm:w-40"
            aria-label="Filter by status"
          >
            <option value="">All statuses</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={5} cols={4} />
          </div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState onRetry={query.refetch} />
          </div>
        ) : rows.length === 0 ? (
          <EmptyState title="No pages yet" message="Create your first informational page." icon={FileText} />
        ) : (
          <div className="p-4">
            <Table>
              <THead>
                <Tr>
                  <Th>Title</Th>
                  <Th>Status</Th>
                  <Th>Footer</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </THead>
              <TBody>
                {rows.map((row) => (
                  <Tr key={row.id} onClick={() => navigate(`/website/pages/${row.id}`)}>
                    <Td>
                      <div className="font-medium text-ink">{row.title}</div>
                      <div className="text-xs text-ink-muted">/{row.slug}{row.is_system ? ' · system' : ''}</div>
                    </Td>
                    <Td>
                      <Badge tone={row.status === 'published' ? 'green' : 'gray'}>{row.status}</Badge>
                    </Td>
                    <Td className="text-ink-muted">{row.show_in_footer ? 'Yes' : '—'}</Td>
                    <Td onClick={(event) => event.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1">
                        {can('website.pages.publish') ? (
                          <Button size="sm" variant={row.status === 'published' ? 'outline' : 'secondary'} onClick={() => togglePublish(row)}>
                            {row.status === 'published' ? 'Unpublish' : 'Publish'}
                          </Button>
                        ) : null}
                        {can('website.pages.manage') ? (
                          <>
                            <Button size="sm" variant="ghost" onClick={() => {
                              if (['privacy-policy', 'terms-of-use'].includes(row.slug)) {
                                navigate(`/website/pages/${row.id}`)
                                return
                              }
                              setEditing(row)
                              setFormOpen(true)
                            }} aria-label="Edit">
                              <Pencil className="size-4" aria-hidden />
                            </Button>
                            <Button size="sm" variant="ghost" onClick={() => setDeleting(row)} aria-label="Delete" disabled={row.is_system}>
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

      <PageFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={query.refetch} page={editing} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete page"
        message={`Delete “${deleting?.title}”? This cannot be undone. If no other record uses the cover image, it is also permanently deleted from Cloudinary.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
