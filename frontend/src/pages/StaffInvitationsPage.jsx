import { useState } from 'react'
import { toast } from 'sonner'
import { RefreshCw, Send, UserPlus, X, MailPlus, Clock, CheckCircle2, CalendarClock, CalendarDays } from 'lucide-react'
import { staffInvitationApi, invitationStatus, INVITATION_STATUS_META } from '../api/staffInvitations.js'
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
import InviteStaffModal from '../components/users/InviteStaffModal.jsx'
import { formatDateTime } from '../utils/format.js'

export default function StaffInvitationsPage() {
  const { can } = useAuth()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [inviteOpen, setInviteOpen] = useState(false)
  const [revoking, setRevoking] = useState(null)
  const [busyId, setBusyId] = useState(null)
  const [viewing, setViewing] = useState(null)

  const query = usePaginated(['staffInvitations'], (params) => staffInvitationApi.list(params), { page })
  const all = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const items = search
    ? all.filter((inv) =>
        [inv.name, inv.email, inv.role]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : all

  const handleResend = async (invitation) => {
    setBusyId(invitation.id)
    try {
      await staffInvitationApi.resend(invitation.id)
      toast.success('Invitation re-sent.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusyId(null)
    }
  }

  const confirmRevoke = async () => {
    if (!revoking) return
    setBusyId(revoking.id)
    try {
      await staffInvitationApi.revoke(revoking.id)
      toast.success('Invitation revoked.')
      setRevoking(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Staff Invitations"
        description="Invite staff members and track outstanding invitations."
        actions={
          can('users.invite') ? (
            <Button onClick={() => setInviteOpen(true)}>
              <UserPlus className="size-4" aria-hidden /> Invite staff
            </Button>
          ) : null
        }
      />

      <div className="stagger-children grid grid-cols-2 gap-4 xl:grid-cols-5">
        <StatCard
          label="Total Invitations"
          value={stats.total ?? query.data?.total ?? 0}
          icon={MailPlus}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label="Pending"
          value={stats.pending ?? 0}
          icon={Clock}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label="Accepted"
          value={stats.accepted ?? 0}
          icon={CheckCircle2}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="Expired"
          value={stats.expired ?? 0}
          icon={CalendarClock}
          tone="red"
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

      <Card bodyClassName="p-0">
        <div className="p-5">
          <SearchInput
            placeholder="Search invitations…"
            className="w-full sm:w-80"
            onDebounced={setSearch}
          />
        </div>

        {query.isLoading ? (
          <div className="px-5 pb-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : all.length === 0 ? (
          <EmptyState
            icon={UserPlus}
            title="No invitations yet"
            message="Invite a staff member to give them access."
            action={
              can('users.invite') ? (
                <Button size="sm" onClick={() => setInviteOpen(true)}>
                  Invite staff
                </Button>
              ) : null
            }
          />
        ) : items.length === 0 ? (
          <EmptyState title="No matches" message="No invitations match your search." />
        ) : (
          <Table minWidth="min-w-[880px]">
            <THead>
              <Tr>
                <Th>Name</Th>
                <Th>Target Staff</Th>
                <Th>Email</Th>
                <Th>Role</Th>
                <Th>Status</Th>
                <Th>Expires</Th>
                <Th>Invited by</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((invitation) => {
                const status = invitationStatus(invitation)
                const meta = INVITATION_STATUS_META[status] ?? INVITATION_STATUS_META.pending
                const canResend = status === 'pending' || status === 'expired'
                const canRevoke = status === 'pending'
                return (
                  <Tr key={invitation.id} onClick={() => setViewing(invitation)}>
                    <Td primary className="font-medium text-ink">{invitation.name}</Td>
                    <Td>
                      {invitation.staff ? (
                        <span className="inline-flex items-center gap-1.5">
                          <Badge tone="blue">{invitation.staff.staff_number}</Badge>
                          <span className="text-xs text-ink-muted">
                            {invitation.staff.first_name} {invitation.staff.last_name}
                          </span>
                        </span>
                      ) : (
                        <span className="text-xs text-ink-muted">General</span>
                      )}
                    </Td>
                    <Td className="text-ink-muted">{invitation.email}</Td>
                    <Td>
                      <Badge tone="brand">{invitation.role}</Badge>
                    </Td>
                    <Td trailing>
                      <Badge tone={meta.tone}>{meta.label}</Badge>
                    </Td>
                    <Td leading className="text-ink-muted">{formatDateTime(invitation.expires_at)}</Td>
                    <Td className="text-ink-muted">{invitation.inviter?.name ?? '—'}</Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        {canResend ? (
                          <button
                            type="button"
                            aria-label={`Resend invitation to ${invitation.name}`}
                            disabled={busyId === invitation.id}
                            className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-canvas hover:text-brand disabled:opacity-50"
                            onClick={() => handleResend(invitation)}
                          >
                            {busyId === invitation.id ? (
                              <RefreshCw className="size-4 animate-spin" aria-hidden />
                            ) : (
                              <Send className="size-4" aria-hidden />
                            )}
                          </button>
                        ) : null}
                        {canRevoke ? (
                          <button
                            type="button"
                            aria-label={`Revoke invitation for ${invitation.name}`}
                            className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setRevoking(invitation)}
                          >
                            <X className="size-4" aria-hidden />
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
          page={query.data?.current_page ?? page}
          lastPage={query.data?.last_page ?? 1}
          total={query.data?.total}
          onChange={setPage}
        />
      </Card>

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.name ?? 'Invitation'}
        subtitle={viewing?.email}
        badges={
          viewing
            ? [INVITATION_STATUS_META[invitationStatus(viewing)] ?? INVITATION_STATUS_META.pending]
            : []
        }
        fields={
          viewing
            ? [
                { label: 'Name', value: viewing.name },
                { label: 'Email', value: viewing.email },
                { label: 'Role', value: viewing.role },
                { label: 'Target staff', value: viewing.staff ? `${viewing.staff.staff_number} · ${viewing.staff.first_name} ${viewing.staff.last_name}` : 'General' },
                { label: 'Status', value: (INVITATION_STATUS_META[invitationStatus(viewing)] ?? INVITATION_STATUS_META.pending).label },
                { label: 'Expires', value: viewing.expires_at ? formatDateTime(viewing.expires_at) : '—' },
                { label: 'Invited by', value: viewing.inviter?.name },
                { label: 'Invited', value: viewing.created_at ? formatDateTime(viewing.created_at) : '—' },
              ]
            : []
        }
      />

      <InviteStaffModal open={inviteOpen} onClose={() => setInviteOpen(false)} onSent={() => query.refetch()} />

      <ConfirmDialog
        open={Boolean(revoking)}
        onClose={() => setRevoking(null)}
        onConfirm={confirmRevoke}
        title="Revoke this invitation?"
        message={`The invitation link sent to ${revoking?.email ?? 'this address'} will stop working immediately.`}
        confirmLabel="Revoke"
        loading={busyId === revoking?.id}
      />
    </div>
  )
}
