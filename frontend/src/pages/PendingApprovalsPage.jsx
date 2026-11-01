import { useState } from 'react'
import { RefreshCw, ShieldCheck, UserCheck, CalendarDays } from 'lucide-react'
import { staffApprovalApi } from '../api/staffApprovals.js'
import { usePaginated } from '../hooks/usePaginated.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import StaffApprovalActions from '../components/users/StaffApprovalActions.jsx'
import { formatDateTime } from '../utils/format.js'

function roleName(role) {
  return typeof role === 'string' ? role : role?.name ?? '—'
}

export default function PendingApprovalsPage() {
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [viewing, setViewing] = useState(null)

  const query = usePaginated(['staffApprovals'], (params) => staffApprovalApi.list(params), { page })
  const all = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const items = search
    ? all.filter((u) =>
        [u.name, u.username, u.email, ...(u.roles ?? []).map(roleName)]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : all

  const total = query.data?.total ?? 0

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pending Approvals"
        description="Review staff registrations before they can access the application."
      />

      <div className="stagger-children grid grid-cols-2 gap-4 xl:grid-cols-5">
        <StatCard
          label="Pending Approvals"
          value={stats.total ?? total}
          icon={UserCheck}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label="New This Month"
          value={stats.this_month ?? 0}
          icon={CalendarDays}
          tone="brand"
          loading={query.isLoading}
        />
      </div>

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput
            placeholder="Search pending accounts…"
            className="w-full sm:w-80"
            onDebounced={setSearch}
          />
          <button
            type="button"
            onClick={() => query.refetch()}
            className="inline-flex items-center gap-2 self-start rounded-lg border border-line px-3 py-2 text-sm font-medium text-ink-muted transition-colors hover:bg-canvas hover:text-brand"
          >
            <RefreshCw className={`size-4 ${query.isFetching ? 'animate-spin' : ''}`} aria-hidden />
            Refresh
          </button>
        </div>

        {query.isLoading ? (
          <div className="px-5 pb-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : all.length === 0 ? (
          <EmptyState
            icon={UserCheck}
            title="No pending approvals"
            message="New staff registrations will appear here for review."
          />
        ) : (
          <Table>
            <THead>
              <Tr>
                <Th>Name</Th>
                <Th>Email</Th>
                <Th>Role</Th>
                <Th>Submitted</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((user) => (
                <Tr key={user.id} onClick={() => setViewing(user)}>
                  <Td primary className="font-medium text-ink">
                    {user.name}
                    {user.username ? (
                      <span className="block text-xs font-normal text-ink-muted">@{user.username}</span>
                    ) : null}
                  </Td>
                  <Td className="text-ink-muted">{user.email}</Td>
                  <Td trailing>
                    <div className="flex flex-wrap gap-1">
                      {(user.roles ?? []).map((role) => (
                        <Badge key={roleName(role)} tone="brand">{roleName(role)}</Badge>
                      ))}
                    </div>
                  </Td>
                  <Td leading className="text-ink-muted">{formatDateTime(user.created_at)}</Td>
                  <Td onClick={(e) => e.stopPropagation()}>
                    <StaffApprovalActions user={user} onChanged={() => query.refetch()} />
                  </Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}

        <Pagination
          page={query.data?.current_page ?? page}
          lastPage={query.data?.last_page ?? 1}
          total={total}
          onChange={setPage}
        />
      </Card>

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.name ?? 'Pending account'}
        subtitle={viewing?.email}
        badges={viewing ? [{ label: 'Pending approval', tone: 'amber' }] : []}
        fields={
          viewing
            ? [
                { label: 'Name', value: viewing.name },
                { label: 'Username', value: viewing.username },
                { label: 'Email', value: viewing.email },
                { label: 'Roles', value: (viewing.roles ?? []).map(roleName).join(', ') || '—' },
                { label: 'Submitted', value: viewing.created_at ? formatDateTime(viewing.created_at) : '—' },
              ]
            : []
        }
      />

      <div className="mt-4 flex items-start gap-2 rounded-lg bg-canvas px-3 py-2 text-xs text-ink-muted">
        <ShieldCheck className="mt-0.5 size-4 shrink-0" aria-hidden />
        <p>
          Approving an account makes it eligible to sign in. Rejecting requires a reason and
          permanently blocks the account until an administrator approves it again. Both actions
          require you to confirm your identity.
        </p>
      </div>
    </div>
  )
}
