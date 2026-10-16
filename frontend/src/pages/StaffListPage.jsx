import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  Eye,
  Link2,
  Pencil,
  Plus,
  RotateCcw,
  Trash2,
  UserCheck,
  UserX,
  Users,
  Clock,
} from 'lucide-react'
import { staffApi } from '../api/staff.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import StaffFormModal from '../components/staff/StaffFormModal.jsx'
import StaffLinkUserModal from '../components/staff/StaffLinkUserModal.jsx'
import { formatDate } from '../utils/format.js'
import {
  EMPLOYMENT_STATUS,
  EMPLOYMENT_STATUS_OPTIONS,
  EMPLOYMENT_TYPE,
  EMPLOYMENT_TYPE_OPTIONS,
  statusBadgeFor,
} from '../utils/status.js'

export default function StaffListPage() {
  const { can } = useAuth()
  const navigate = useNavigate()

  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [department, setDepartment] = useState('')
  const [jobTitle, setJobTitle] = useState('')
  const [employmentStatus, setEmploymentStatus] = useState('')
  const [employmentType, setEmploymentType] = useState('')
  const [hasAccount, setHasAccount] = useState('')
  const [sort, setSort] = useState('created_at')
  const [direction, setDirection] = useState('desc')

  const [departments, setDepartments] = useState([])
  const [jobTitles, setJobTitles] = useState([])
  const [formOpen, setFormOpen] = useState(false)
  const [editingStaff, setEditingStaff] = useState(null)
  const [linkingStaff, setLinkingStaff] = useState(null)
  const [deletingStaff, setDeletingStaff] = useState(null)

  useEffect(() => {
    staffApi.departments()
      .then((deps) => setDepartments(deps.map((d) => ({ value: d, label: d }))))
      .catch(() => {})

    staffApi.jobTitles()
      .then((titles) => setJobTitles(titles.map((t) => ({ value: t, label: t }))))
      .catch(() => {})
  }, [])

  const query = usePaginated(
    ['staff', { page, search, department, jobTitle, employmentStatus, employmentType, hasAccount, sort, direction }],
    (params) =>
      staffApi.list({
        ...params,
        page,
        search: search || undefined,
        department: department || undefined,
        job_title: jobTitle || undefined,
        employment_status: employmentStatus || undefined,
        employment_type: employmentType || undefined,
        has_account: hasAccount || undefined,
        sort,
        direction,
      }),
    { page },
  )

  const items = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const hasActiveFilters = Boolean(
    search || department || jobTitle || employmentStatus || employmentType || hasAccount,
  )

  const handleResetFilters = () => {
    setSearch('')
    setDepartment('')
    setJobTitle('')
    setEmploymentStatus('')
    setEmploymentType('')
    setHasAccount('')
    setPage(1)
  }

  const handleSort = (field) => {
    if (sort === field) {
      setDirection((d) => (d === 'asc' ? 'desc' : 'asc'))
    } else {
      setSort(field)
      setDirection('asc')
    }
    setPage(1)
  }

  const renderSortHeader = (field, label) => {
    const active = sort === field
    return (
      <button
        type="button"
        className="inline-flex items-center gap-1 font-semibold text-ink transition-colors hover:text-brand"
        onClick={() => handleSort(field)}
        title={`Sort by ${label}`}
      >
        <span>{label}</span>
        {active ? (
          direction === 'asc' ? (
            <ArrowUp className="size-3 text-brand" aria-hidden />
          ) : (
            <ArrowDown className="size-3 text-brand" aria-hidden />
          )
        ) : (
          <ArrowUpDown className="size-3 text-ink-muted/50" aria-hidden />
        )}
      </button>
    )
  }

  const confirmDelete = async () => {
    if (!deletingStaff) return
    try {
      await staffApi.remove(deletingStaff.id)
      toast.success('Staff profile removed.')
      setDeletingStaff(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message || 'Failed to remove staff profile.')
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Staff Directory"
        description="Complete registry of all clinic employees, independent of system logins."
        breadcrumb={[{ label: 'Staff Directory' }]}
        actions={
          can('staff.create') ? (
            <Button
              onClick={() => {
                setEditingStaff(null)
                setFormOpen(true)
              }}
            >
              <Plus className="size-4" aria-hidden /> Add staff member
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard
          label="Total Staff"
          value={stats.total ?? query.data?.total ?? 0}
          icon={Users}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label="Employed"
          value={stats.employed ?? 0}
          icon={UserCheck}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="On Probation"
          value={stats.probation ?? 0}
          icon={Clock}
          tone="blue"
          loading={query.isLoading}
        />
        <StatCard
          label="On Leave"
          value={stats.on_leave ?? 0}
          icon={UserX}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label="Linked Accounts"
          value={stats.with_account ?? 0}
          icon={Link2}
          tone="navy"
          loading={query.isLoading}
        />
      </div>

      <Card>
        {/* Filters */}
        <div className="mb-4 space-y-3">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
            <div className="lg:col-span-2">
              <SearchInput
                placeholder="Search by name, staff #, email, phone, title…"
                className="w-full"
                value={search}
                onDebounced={(val) => {
                  setSearch(val)
                  setPage(1)
                }}
              />
            </div>

            <Select
              value={department}
              onChange={(event) => {
                setDepartment(event.target.value)
                setPage(1)
              }}
            >
              {[{ value: '', label: 'All Departments' }, ...departments].map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </Select>

            <Select
              value={jobTitle}
              onChange={(event) => {
                setJobTitle(event.target.value)
                setPage(1)
              }}
            >
              {[{ value: '', label: 'All Positions' }, ...jobTitles].map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </Select>

            <Select
              value={employmentType}
              onChange={(event) => {
                setEmploymentType(event.target.value)
                setPage(1)
              }}
            >
              {[{ value: '', label: 'All Types' }, ...EMPLOYMENT_TYPE_OPTIONS].map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </Select>

            <Select
              value={employmentStatus}
              onChange={(event) => {
                setEmploymentStatus(event.target.value)
                setPage(1)
              }}
            >
              {[{ value: '', label: 'All Statuses' }, ...EMPLOYMENT_STATUS_OPTIONS].map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </Select>

            <Select
              value={hasAccount}
              onChange={(event) => {
                setHasAccount(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All Accounts</option>
              <option value="true">Linked to User</option>
              <option value="false">Unlinked (No User)</option>
            </Select>
          </div>

          {hasActiveFilters ? (
            <div className="flex items-center justify-between text-xs text-ink-muted pt-1">
              <span>
                Filters active {query.data?.total != null ? `(${query.data.total} matching staff)` : ''}
              </span>
              <button
                type="button"
                className="inline-flex items-center gap-1 font-medium text-brand hover:underline"
                onClick={handleResetFilters}
              >
                <RotateCcw className="size-3" aria-hidden /> Reset all filters
              </button>
            </div>
          ) : null}
        </div>

        {query.isLoading ? (
          <RowSkeleton rows={6} cols={6} />
        ) : query.isError ? (
          <ErrorState
            title="Failed to load staff directory"
            message={query.error?.message || 'An error occurred while fetching staff records.'}
            onRetry={() => query.refetch()}
          />
        ) : items.length === 0 ? (
          <EmptyState
            title="No staff members found"
            message={
              search || department || employmentStatus || hasAccount
                ? 'No staff members match the selected filters.'
                : 'Start by adding an employee to the directory.'
            }
            action={
              can('staff.create') && !search && !department ? (
                <Button size="sm" onClick={() => setFormOpen(true)}>
                  Add staff member
                </Button>
              ) : null
            }
          />
        ) : (
          <Table minWidth="min-w-[1000px]">
            <THead>
              <Tr>
                <Th>{renderSortHeader('staff_number', 'Staff #')}</Th>
                <Th>{renderSortHeader('first_name', 'Name & Contact')}</Th>
                <Th>{renderSortHeader('department', 'Department & Role')}</Th>
                <Th>Employment</Th>
                <Th>{renderSortHeader('employment_status', 'Status')}</Th>
                <Th>System Account</Th>
                <Th>{renderSortHeader('hire_date', 'Hired')}</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((s) => {
                const statusMeta = statusBadgeFor(EMPLOYMENT_STATUS, s.employment_status)
                const typeMeta = statusBadgeFor(EMPLOYMENT_TYPE, s.employment_type)
                const hasLogin = Boolean(s.user_id && s.user)

                return (
                  <Tr key={s.id} onClick={() => navigate(`/staff/${s.id}`)}>
                    <Td className="font-mono text-xs font-semibold text-brand">
                      {s.staff_number}
                    </Td>
                    <Td>
                      <div className="font-medium text-ink">
                        {s.full_name || `${s.first_name} ${s.last_name}`}
                      </div>
                      <div className="text-xs text-ink-muted">
                        {s.email || s.phone || '—'}
                      </div>
                    </Td>
                    <Td>
                      <div className="font-medium text-ink">{s.job_title || '—'}</div>
                      <div className="text-xs text-ink-muted">{s.department || '—'}</div>
                    </Td>
                    <Td>
                      <Badge tone={typeMeta.tone}>{typeMeta.label}</Badge>
                    </Td>
                    <Td>
                      <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge>
                    </Td>
                    <Td>
                      {hasLogin ? (
                        <div className="flex items-center gap-1.5">
                          <UserCheck className="size-3.5 text-green-600" aria-hidden />
                          <span className="text-xs text-ink font-medium">@{s.user.username}</span>
                          <Badge tone={s.user.account_status === 'active' ? 'green' : 'amber'}>
                            {s.user.account_status}
                          </Badge>
                        </div>
                      ) : (
                        <div className="flex items-center gap-1 text-xs text-ink-muted">
                          <UserX className="size-3.5" aria-hidden />
                          <span>No login</span>
                        </div>
                      )}
                    </Td>
                    <Td className="text-ink-muted">{formatDate(s.hire_date)}</Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        <button
                          type="button"
                          aria-label={`View details for ${s.full_name}`}
                          className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                          onClick={() => navigate(`/staff/${s.id}`)}
                          title="View staff details"
                        >
                          <Eye className="size-4" aria-hidden />
                        </button>

                        {can('staff.update') ? (
                          <>
                            {!hasLogin ? (
                              <button
                                type="button"
                                aria-label={`Link user account to ${s.full_name}`}
                                className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-brand-50 hover:text-brand"
                                onClick={() => setLinkingStaff(s)}
                                title="Link system user account"
                              >
                                <Link2 className="size-4" aria-hidden />
                              </button>
                            ) : null}

                            <button
                              type="button"
                              aria-label={`Edit ${s.full_name}`}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => {
                                setEditingStaff(s)
                                setFormOpen(true)
                              }}
                              title="Edit staff profile"
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                          </>
                        ) : null}

                        {can('staff.delete') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${s.full_name}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeletingStaff(s)}
                            title="Delete staff profile"
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

      <StaffFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        initial={editingStaff}
        onSaved={() => query.refetch()}
      />

      {linkingStaff ? (
        <StaffLinkUserModal
          open={Boolean(linkingStaff)}
          onClose={() => setLinkingStaff(null)}
          staff={linkingStaff}
          onSaved={() => query.refetch()}
        />
      ) : null}

      <ConfirmDialog
        open={Boolean(deletingStaff)}
        onClose={() => setDeletingStaff(null)}
        onConfirm={confirmDelete}
        title="Delete staff profile?"
        message={`This will remove ${deletingStaff?.full_name || deletingStaff?.staff_number} from the directory. Any linked user account will remain intact.`}
        confirmLabel="Delete profile"
      />
    </div>
  )
}
