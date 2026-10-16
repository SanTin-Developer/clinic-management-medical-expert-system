import { useState } from 'react'
import { Link } from 'react-router-dom'
import { toast } from 'sonner'
import { MailPlus, Pencil, Trash2, Users, UserCheck, UserX, ShieldAlert, CalendarDays } from 'lucide-react'
import { userApi } from '../api/users.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import UserFormModal from '../components/users/UserFormModal.jsx'
import UserStatusActions from '../components/users/UserStatusActions.jsx'
import { formatDate } from '../utils/format.js'

const ROLE_TONES = {
  Admin: 'navy',
  Doctor: 'blue',
  Nurse: 'brand',
  'Head Nurse': 'blue',
  Receptionist: 'amber',
  Pharmacist: 'green',
  Patient: 'gray',
}

const STATUS_META = {
  pending: { label: 'Pending', tone: 'amber' },
  pending_approval: { label: 'Pending approval', tone: 'amber' },
  active: { label: 'Active', tone: 'green' },
  suspended: { label: 'Suspended', tone: 'red' },
  disabled: { label: 'Disabled', tone: 'gray' },
  rejected: { label: 'Rejected', tone: 'red' },
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
  const [viewing, setViewing] = useState(null)

  const query = usePaginated(['users'], (params) => userApi.list(params), { page })

  const all = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const items = search
    ? all.filter((u) =>
        [u.name, u.username, u.email, ...(u.roles ?? []).map(roleName)]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : all

  const canManage = can('users.update')

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
    <div className="space-y-6">
      <PageHeader
        title="Users & Roles"
        description="System accounts and authentication roles. Staff records are managed in the Staff Directory."
        breadcrumb={[{ label: 'Users' }]}
        actions={
          can('users.invite') ? (
            <Link to="/invitations">
              <Button>
                <MailPlus className="size-4" aria-hidden /> Invite staff
              </Button>
            </Link>
          ) : null
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard
          label="Total Users"
          value={stats.total ?? query.data?.total ?? 0}
          icon={Users}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label="Active"
          value={stats.active ?? 0}
          icon={UserCheck}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="Suspended"
          value={stats.suspended ?? 0}
          icon={UserX}
          tone="red"
          loading={query.isLoading}
        />
        <StatCard
          label="Unverified"
          value={stats.unverified ?? 0}
          icon={ShieldAlert}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label="New This Month"
          value={stats.this_month ?? 0}
          icon={CalendarDays}
          tone="blue"
          loading={query.isLoading}
        />
      </div>

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
            message="Staff accounts are provisioned securely via the invitation workflow."
            action={
              can('users.invite') ? (
                <Link to="/invitations">
                  <Button size="sm">
                    <MailPlus className="size-4" aria-hidden /> Invite staff
                  </Button>
                </Link>
              ) : null
            }
          />
        ) : items.length === 0 ? (
          <EmptyState title="No matches" message="No users match your search." />
        ) : (
          <Table minWidth="min-w-[1040px]">
            <THead>
              <Tr>
                <Th>Name</Th>
                <Th>Staff #</Th>
                <Th>Username</Th>
                <Th>Email</Th>
                <Th>Role</Th>
                <Th>Status</Th>
                <Th>2FA</Th>
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
                  <Tr key={u.id} onClick={() => setViewing(u)}>
                    <Td className="font-medium text-ink">{u.name}</Td>
                    <Td>
                      {u.staff_id ? (
                        <Badge tone="blue">{u.staff_id}</Badge>
                      ) : (
                        <span className="text-xs text-ink-muted">Unlinked</span>
                      )}
                    </Td>
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
                      {(() => {
                        const meta = STATUS_META[u.account_status] ?? STATUS_META.active
                        return <Badge tone={meta.tone}>{meta.label}</Badge>
                      })()}
                    </Td>
                    <Td>
                      {u.two_factor_enabled ? (
                        <Badge tone="green">On</Badge>
                      ) : (
                        <Badge tone="gray">Off</Badge>
                      )}
                    </Td>
                    <Td>
                      {verified ? (
                        <Badge tone="green">Verified</Badge>
                      ) : (
                        <Badge tone="amber">Unverified</Badge>
                      )}
                    </Td>
                    <Td className="text-ink-muted">{formatDate(u.created_at)}</Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        {can('users.suspend') ? (
                          <UserStatusActions user={u} onChanged={() => query.refetch()} />
                        ) : null}
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

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.name ?? 'User'}
        subtitle={viewing?.email}
        badges={
          viewing
            ? [
                (() => {
                  const meta = STATUS_META[viewing.account_status] ?? STATUS_META.active
                  return { label: meta.label, tone: meta.tone }
                })(),
                viewing.two_factor_enabled ? { label: '2FA on', tone: 'green' } : null,
                viewing.email_verified_at ? { label: 'Email verified', tone: 'green' } : { label: 'Email unverified', tone: 'amber' },
              ]
            : []
        }
        fields={
          viewing
            ? [
                { label: 'Username', value: viewing.username },
                { label: 'Email', value: viewing.email },
                { label: 'Staff #', value: viewing.staff_id },
                { label: 'Roles', value: (viewing.roles ?? []).map(roleName).join(', ') || '—' },
                { label: 'Status', value: (STATUS_META[viewing.account_status] ?? STATUS_META.active).label },
                { label: '2FA', value: viewing.two_factor_enabled ? 'Enabled' : 'Disabled' },
                { label: 'Email verified', value: viewing.email_verified_at ? formatDate(viewing.email_verified_at) : 'Not verified' },
                { label: 'Joined', value: formatDate(viewing.created_at) },
              ]
            : []
        }
      />

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