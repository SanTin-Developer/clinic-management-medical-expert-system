import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Activity, Clock3, Eye, ListFilter, RotateCcw, ScrollText, Users } from 'lucide-react'
import { auditApi } from '../api/audit.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import Input from '../components/ui/Input.jsx'
import Field from '../components/ui/Field.jsx'
import Modal from '../components/ui/Modal.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState, ForbiddenState } from '../components/ui/State.jsx'
import { formatDateTime } from '../utils/format.js'

const EVENT_META = {
  created: { label: 'Created', tone: 'green' },
  updated: { label: 'Updated', tone: 'blue' },
  deleted: { label: 'Deleted', tone: 'red' },
  login: { label: 'Signed in', tone: 'brand' },
  logout: { label: 'Signed out', tone: 'gray' },
  login_failed: { label: 'Failed sign-in', tone: 'amber' },
}

function eventMeta(event) {
  if (!event) {
    return { label: 'Activity', tone: 'gray' }
  }

  return EVENT_META[event] ?? { label: event.replace(/_/g, ' '), tone: 'gray' }
}

function subjectLabel(entry) {
  if (entry.properties?.subject_label) {
    return entry.properties.subject_label
  }

  if (!entry.subject_type) {
    return null
  }

  return entry.subject_type.split('\\').pop()
}

function displayValue(value) {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  if (typeof value === 'boolean') {
    return value ? 'true' : 'false'
  }

  if (typeof value === 'object') {
    return JSON.stringify(value)
  }

  return String(value)
}

export default function AuditLogListPage() {
  const { can } = useAuth()

  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [event, setEvent] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [detailId, setDetailId] = useState(null)

  const allowed = can('audit-logs.view')

  const query = usePaginated(
    ['audit-logs', { page, search, event, from, to }],
    (params) =>
      auditApi.list({
        ...params,
        page,
        search: search || undefined,
        event: event || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
    { page, enabled: allowed },
  )

  const items = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const events = stats.events ?? []
  const hasActiveFilters = Boolean(search || event || from || to)

  const resetFilters = () => {
    setSearch('')
    setEvent('')
    setFrom('')
    setTo('')
    setPage(1)
  }

  if (!allowed) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Audit Logs"
          description="A chronological record of user activity across the clinic."
        />
        <Card>
          <ForbiddenState message="You need the audit-logs.view permission to view audit logs." />
        </Card>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Audit Logs"
        description="A chronological record of user activity across the clinic — sign-ins and record changes."
        breadcrumb={[{ label: 'Audit Logs' }]}
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total activity"
          value={stats.total ?? query.data?.total ?? 0}
          icon={Activity}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label="Last 24 hours"
          value={stats.last_24h ?? 0}
          icon={Clock3}
          tone="blue"
          loading={query.isLoading}
        />
        <StatCard
          label="Active users"
          value={stats.users ?? 0}
          icon={Users}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="Event types"
          value={events.length}
          icon={ListFilter}
          tone="navy"
          loading={query.isLoading}
        />
      </div>

      <Card>
        <div className="mb-4 space-y-3">
          <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div className="lg:col-span-2">
              <SearchInput
                placeholder="Search users, descriptions, IP…"
                className="w-full"
                value={search}
                onDebounced={(value) => {
                  setSearch(value)
                  setPage(1)
                }}
              />
            </div>

            <Select
              aria-label="Filter by event"
              value={event}
              onChange={(selectEvent) => {
                setEvent(selectEvent.target.value)
                setPage(1)
              }}
            >
              <option value="">All events</option>
              {events.map((name) => (
                <option key={name} value={name}>
                  {eventMeta(name).label}
                </option>
              ))}
            </Select>

            <Field label="From" htmlFor="activity-from" className="space-y-1">
              <Input
                id="activity-from"
                type="date"
                value={from}
                onChange={(inputEvent) => {
                  setFrom(inputEvent.target.value)
                  setPage(1)
                }}
              />
            </Field>
            <Field label="To" htmlFor="activity-to" className="space-y-1">
              <Input
                id="activity-to"
                type="date"
                value={to}
                onChange={(inputEvent) => {
                  setTo(inputEvent.target.value)
                  setPage(1)
                }}
              />
            </Field>
          </div>

          {hasActiveFilters ? (
            <div className="flex items-center justify-between pt-1 text-xs text-ink-muted">
              <span>
                Filters active
                {query.data?.total != null ? ` (${query.data.total} matching entries)` : ''}
              </span>
              <button
                type="button"
                className="inline-flex items-center gap-1 font-medium text-brand hover:underline"
                onClick={resetFilters}
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
            title="Failed to load activity logs"
            message={query.error?.message || 'An error occurred while fetching activity logs.'}
            onRetry={() => query.refetch()}
          />
        ) : items.length === 0 ? (
          <EmptyState
            icon={ScrollText}
            title="No activity entries found"
            message={
              hasActiveFilters
                ? 'No activity entries match the selected filters.'
                : 'No user activity has been recorded yet.'
            }
          />
        ) : (
          <Table minWidth="min-w-[960px]">
            <THead>
              <Tr>
                <Th>When</Th>
                <Th>User</Th>
                <Th>Event</Th>
                <Th>Description</Th>
                <Th>Subject</Th>
                <Th>IP</Th>
                <Th>
                  <span className="sr-only">Actions</span>
                </Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((log) => {
                const meta = eventMeta(log.event)
                const subject = subjectLabel(log)

                return (
                  <Tr key={log.id} onClick={() => setDetailId(log.id)}>
                    <Td className="whitespace-nowrap text-ink-muted">
                      {formatDateTime(log.created_at)}
                    </Td>
                    <Td>
                      <div className="flex flex-col">
                        <span className="font-medium text-ink">{log.user_name || 'System'}</span>
                        {log.user_role ? (
                          <span className="text-xs text-ink-muted">{log.user_role}</span>
                        ) : null}
                      </div>
                    </Td>
                    <Td>
                      <Badge tone={meta.tone}>{meta.label}</Badge>
                    </Td>
                    <Td className="text-ink">{log.description}</Td>
                    <Td className="text-ink-muted">{subject || '—'}</Td>
                    <Td className="font-mono text-xs text-ink-muted">{log.ip_address || '—'}</Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end">
                        <button
                          type="button"
                          aria-label={`View activity entry ${log.id}`}
                          className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                          onClick={() => setDetailId(log.id)}
                          title="View details"
                        >
                          <Eye className="size-4" aria-hidden />
                        </button>
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

      <ActivityDetailModal id={detailId} onClose={() => setDetailId(null)} />
    </div>
  )
}

function ActivityDetailModal({ id, onClose }) {
  const { data: entry, isLoading, isError, error } = useQuery({
    queryKey: ['audit-log', id],
    queryFn: () => auditApi.get(id),
    enabled: Boolean(id),
  })

  if (!id) return null

  const meta = entry ? eventMeta(entry.event) : null

  const rows = entry
    ? [
        ['Recorded', formatDateTime(entry.created_at)],
        ['User', entry.user_name || 'System'],
        ['Role', entry.user_role || '—'],
        ['Description', entry.description],
        ['Subject', subjectLabel(entry) || '—'],
        ['Subject reference', entry.subject_type ? `${entry.subject_type} #${entry.subject_id}` : '—'],
        ['IP address', entry.ip_address || '—'],
        ['User agent', entry.user_agent || '—'],
      ]
    : []

  return (
    <Modal open={Boolean(id)} onClose={onClose} title="Activity entry" size="md">
      {isLoading ? (
        <RowSkeleton rows={4} cols={2} />
      ) : isError ? (
        <ErrorState message={error?.message || 'Failed to load activity entry.'} compact />
      ) : (
        <div className="space-y-4">
          {meta ? (
            <div>
              <Badge tone={meta.tone}>{meta.label}</Badge>
            </div>
          ) : null}

          <dl className="divide-y divide-line">
            {rows.map(([label, value]) => (
              <div key={label} className="grid grid-cols-3 gap-3 py-2.5">
                <dt className="text-sm font-medium text-ink-muted">{label}</dt>
                <dd className="col-span-2 break-words text-sm text-ink">{value}</dd>
              </div>
            ))}
          </dl>

          <PropertiesSection properties={entry?.properties} />
        </div>
      )}
    </Modal>
  )
}

function PropertiesSection({ properties }) {
  const changes = properties?.changes
  const attributes = properties?.attributes

  if (!changes && !attributes) {
    return null
  }

  return (
    <div className="space-y-3">
      {changes ? (
        <div>
          <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-muted">
            Changes
          </p>
          <ul className="divide-y divide-line rounded-lg border border-line">
            {Object.entries(changes).map(([field, diff]) => (
              <li key={field} className="grid grid-cols-3 gap-3 px-3 py-2 text-sm">
                <span className="font-medium text-ink-muted">{field}</span>
                <span className="col-span-2 break-words text-ink">
                  <span className="text-ink-muted line-through">{displayValue(diff?.from)}</span>
                  {' → '}
                  <span>{displayValue(diff?.to)}</span>
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}

      {attributes ? (
        <div>
          <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-ink-muted">
            Recorded values
          </p>
          <ul className="divide-y divide-line rounded-lg border border-line">
            {Object.entries(attributes).map(([field, value]) => (
              <li key={field} className="grid grid-cols-3 gap-3 px-3 py-2 text-sm">
                <span className="font-medium text-ink-muted">{field}</span>
                <span className="col-span-2 break-words text-ink">{displayValue(value)}</span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </div>
  )
}
