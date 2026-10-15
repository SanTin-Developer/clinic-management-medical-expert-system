import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Activity, Clock3, Eye, RotateCcw, ShieldCheck, Cpu, ScrollText } from 'lucide-react'
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

export default function AuditLogListPage() {
  const { can } = useAuth()

  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [engineVersion, setEngineVersion] = useState('')
  const [validated, setValidated] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [detailId, setDetailId] = useState(null)

  const allowed = can('audit-logs.view')

  const query = usePaginated(
    ['audit-logs', { page, search, engineVersion, validated, from, to }],
    (params) =>
      auditApi.list({
        ...params,
        page,
        search: search || undefined,
        engine_version: engineVersion || undefined,
        validated: validated || undefined,
        from: from || undefined,
        to: to || undefined,
      }),
    { page, enabled: allowed },
  )

  const items = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const engineVersions = stats.engine_versions ?? []
  const hasActiveFilters = Boolean(search || engineVersion || validated || from || to)

  const resetFilters = () => {
    setSearch('')
    setEngineVersion('')
    setValidated('')
    setFrom('')
    setTo('')
    setPage(1)
  }

  if (!allowed) {
    return (
      <div className="space-y-6">
        <PageHeader
          title="Audit Logs"
          description="A record of clinical decision-support evaluations and system activity."
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
        description="A record of clinical decision-support evaluations and system activity."
        breadcrumb={[{ label: 'Audit Logs' }]}
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Total evaluations"
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
          label="Clinically validated"
          value={stats.validated ?? 0}
          icon={ShieldCheck}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="Engine versions"
          value={engineVersions.length}
          icon={Cpu}
          tone="navy"
          loading={query.isLoading}
        />
      </div>

      <Card>
        <div className="mb-4 space-y-3">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div className="lg:col-span-2">
              <SearchInput
                placeholder="Search rule codes, symptoms, engine…"
                className="w-full"
                value={search}
                onDebounced={(value) => {
                  setSearch(value)
                  setPage(1)
                }}
              />
            </div>

            <Select
              aria-label="Filter by engine version"
              value={engineVersion}
              onChange={(event) => {
                setEngineVersion(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All engine versions</option>
              {engineVersions.map((version) => (
                <option key={version} value={version}>
                  {version}
                </option>
              ))}
            </Select>

            <Select
              aria-label="Filter by validation status"
              value={validated}
              onChange={(event) => {
                setValidated(event.target.value)
                setPage(1)
              }}
            >
              <option value="">All results</option>
              <option value="true">Clinically validated</option>
              <option value="false">Not validated</option>
            </Select>

            <div className="grid grid-cols-2 gap-2">
              <Field label="From" htmlFor="audit-from" className="space-y-1">
                <Input
                  id="audit-from"
                  type="date"
                  value={from}
                  onChange={(event) => {
                    setFrom(event.target.value)
                    setPage(1)
                  }}
                />
              </Field>
              <Field label="To" htmlFor="audit-to" className="space-y-1">
                <Input
                  id="audit-to"
                  type="date"
                  value={to}
                  onChange={(event) => {
                    setTo(event.target.value)
                    setPage(1)
                  }}
                />
              </Field>
            </div>
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
          <RowSkeleton rows={6} cols={5} />
        ) : query.isError ? (
          <ErrorState
            title="Failed to load audit logs"
            message={query.error?.message || 'An error occurred while fetching audit logs.'}
            onRetry={() => query.refetch()}
          />
        ) : items.length === 0 ? (
          <EmptyState
            icon={ScrollText}
            title="No audit entries found"
            message={
              hasActiveFilters
                ? 'No audit entries match the selected filters.'
                : 'The evaluation audit trail is empty. Enable expert-system audit to start recording.'
            }
          />
        ) : (
          <Table minWidth="min-w-[820px]">
            <THead>
              <Tr>
                <Th>When</Th>
                <Th>Matches</Th>
                <Th>Rule codes</Th>
                <Th>Engine</Th>
                <Th>Flag</Th>
                <Th>
                  <span className="sr-only">Actions</span>
                </Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((log) => (
                <Tr key={log.id}>
                  <Td className="whitespace-nowrap text-ink-muted">
                    {formatDateTime(log.created_at)}
                  </Td>
                  <Td className="font-semibold text-ink">{log.matched_rule_count}</Td>
                  <Td>
                    <span className="line-clamp-2 max-w-xs font-mono text-xs text-ink-muted">
                      {log.matched_rule_codes || '—'}
                    </span>
                  </Td>
                  <Td className="font-mono text-xs text-ink-muted">
                    {log.engine_version || '—'}
                  </Td>
                  <Td>
                    {log.is_clinically_validated ? (
                      <Badge tone="green">Validated</Badge>
                    ) : (
                      <Badge tone="gray">—</Badge>
                    )}
                  </Td>
                  <Td>
                    <div className="flex justify-end">
                      <button
                        type="button"
                        aria-label={`View audit entry ${log.id}`}
                        className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                        onClick={() => setDetailId(log.id)}
                        title="View details"
                      >
                        <Eye className="size-4" aria-hidden />
                      </button>
                    </div>
                  </Td>
                </Tr>
              ))}
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

      <AuditDetailModal id={detailId} onClose={() => setDetailId(null)} />
    </div>
  )
}

function AuditDetailModal({ id, onClose }) {
  const { data: entry, isLoading, isError, error } = useQuery({
    queryKey: ['audit-log', id],
    queryFn: () => auditApi.get(id),
    enabled: Boolean(id),
  })

  if (!id) return null

  const rows = entry
    ? [
        ['Recorded', formatDateTime(entry.created_at)],
        ['Matched rules', entry.matched_rule_count],
        ['Rule codes', entry.matched_rule_codes || '—'],
        ['Symptom IDs', entry.symptom_ids || '—'],
        ['Engine version', entry.engine_version || '—'],
        ['Clinically validated', entry.is_clinically_validated ? 'Yes' : 'No'],
      ]
    : []

  return (
    <Modal open={Boolean(id)} onClose={onClose} title="Audit entry" size="md">
      {isLoading ? (
        <RowSkeleton rows={4} cols={2} />
      ) : isError ? (
        <ErrorState message={error?.message || 'Failed to load audit entry.'} compact />
      ) : (
        <dl className="divide-y divide-line">
          {rows.map(([label, value]) => (
            <div key={label} className="grid grid-cols-3 gap-3 py-2.5">
              <dt className="text-sm font-medium text-ink-muted">{label}</dt>
              <dd className="col-span-2 break-words text-sm text-ink">{value}</dd>
            </div>
          ))}
        </dl>
      )}
    </Modal>
  )
}
