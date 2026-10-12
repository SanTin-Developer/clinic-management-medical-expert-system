import { useState } from 'react'
import { toast } from 'sonner'
import { Pencil, Trash2, UserPlus } from 'lucide-react'
import { userApi } from '../api/users.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import UserFormModal from '../components/users/UserFormModal.jsx'
import { formatDate } from '../utils/format.js'

const ROLE_TONES = {
  Admin: 'navy',
  Doctor: 'blue',
  Nurse: 'pink',
  Receptionist: 'amber',
  Pharmacist: 'green',
  Patient: 'gray',
}

function roleName(role) {
  return typeof role === 'string' ? role : role?.name ?? '—'
}

export default function UsersPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(['users'], (params) => userApi.list(params), { page })

  const all = query.data?.data ?? []
  const items = search
    ? all.filter((u) =>
        [u.name, u.username, u.email, ...(u.roles ?? []).map(roleName)]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : all

  const canManage = can('users.create') || can('users.update')

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await userApi.remove(deleting.id)
      toast.success('User deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div>
      <PageHeader
        title="Users & Roles"
        description="Manage staff accounts and their access roles."
        breadcrumb={[{ label: 'Users' }]}
        actions={
          can('users.create') ? (
            <Button
              onClick={() => {
                setEditing(null)
                setFormOpen(true)
              }}
            >
              <UserPlus className="size-4" aria-hidden /> New user
            </Button>
          ) : null
        }
      />

      <Card>
        <div className="mb-4">
          <SearchInput
            placeholder="Search users…"
            className="w-full sm:w-80"
            onDebounced={setSearch}
          />
        </div>

        {query.isLoading ? (
          <RowSkeleton rows={6} cols={4} />
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : all.length === 0 ? (
          <EmptyState
            title="No users yet"
            message="Create a user to get started."
            action={
              can('users.create') ? (
                <Button size="sm" onClick={() => setFormOpen(true)}>
                  New user
                </Button>
              ) : null
            }
          />
        ) : items.length === 0 ? (
          <EmptyState title="No matches" message="No users match your search." />
        ) : (
          <Table minWidth="min-w-[840px]">
            <THead>
              <Tr>
                <Th>Name</Th>
                <Th>Username</Th>
                <Th>Email</Th>
                <Th>Role</Th>
                <Th>Email verified</Th>
                <Th>Joined</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((u) => {
                const roles = (u.roles ?? []).map(roleName)
                const verified = Boolean(u.email_verified_at)
                return (
                  <Tr key={u.id}>
                    <Td className="font-medium text-ink">{u.name}</Td>
                    <Td className="text-ink-muted">{u.username ?? '—'}</Td>
                    <Td className="text-ink-muted">{u.email}</Td>
                    <Td>
                      <span className="flex flex-wrap gap-1">
                        {roles.map((role) => (
                          <Badge key={role} tone={ROLE_TONES[role] ?? 'gray'}>
                            {role}
                          </Badge>
                        ))}
                        {roles.length === 0 ? <span className="text-ink-muted">—</span> : null}
                      </span>
                    </Td>
                    <Td>
                      {verified ? (
                        <Badge tone="green">Verified</Badge>
                      ) : (
                        <Badge tone="amber">Unverified</Badge>
                      )}
                    </Td>
                    <Td className="text-ink-muted">{formatDate(u.created_at)}</Td>
                    <Td>
                      <div className="flex justify-end gap-1">
                        {canManage ? (
                          <button
                            type="button"
                            aria-label={`Edit ${u.name}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                            onClick={() => {
                              setEditing(u)
                              setFormOpen(true)
                            }}
                          >
                            <Pencil className="size-4" aria-hidden />
                          </button>
                        ) : null}
                        {can('users.delete') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${u.name}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeleting(u)}
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

      <UserFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        initial={editing}
        onSaved={() => query.refetch()}
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete user?"
        message={`This permanently removes ${deleting?.name ?? 'this user'} and revokes their access.`}
        confirmLabel="Delete"
      />
    </div>
  )
}