import { useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Stethoscope, Pencil, Trash2, Plus, ShieldAlert, Sparkles, CalendarDays, X } from 'lucide-react'
import { expertApi } from '../api/expert.js'
import { useAll, usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Badge from '../components/ui/Badge.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import { RowSkeleton, CardSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import DiseaseFormModal from '../components/expert/DiseaseFormModal.jsx'
import SymptomFormModal from '../components/expert/SymptomFormModal.jsx'
import ExpertRuleFormModal from '../components/expert/ExpertRuleFormModal.jsx'
import { formatDateTime } from '../utils/format.js'
import { DISEASE_SEVERITY } from '../utils/status.js'

export default function ExpertSystemPage() {
  const { can } = useAuth()
  const [tab, setTab] = useState('analyze')

  return (
    <div>
      <PageHeader
        title="Expert System"
        description="Rule-based clinical decision support and its knowledge base."
        breadcrumb={[{ label: 'Expert System' }]}
      />
      <Tabs
        className="mb-5"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'analyze', label: 'Symptom Analysis' },
          { value: 'kb', label: 'Knowledge Base' },
          { value: 'audit', label: 'Audit History' },
        ]}
      />
      {tab === 'analyze' ? (
        <AnalyzeTab canUse={can('expert-system.use')} canViewRules={can('knowledge-base.view')} />
      ) : null}
      {tab === 'kb' ? <KnowledgeBaseTab canView={can('knowledge-base.view')} canManage={can('knowledge-base.manage')} /> : null}
      {tab === 'audit' ? <AuditTab canView={can('audit-logs.view')} /> : null}
    </div>
  )
}

function AnalyzeTab({ canUse, canViewRules }) {
  const [selected, setSelected] = useState([])
  const [result, setResult] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [search, setSearch] = useState('')
  const [onlyRuleLinked, setOnlyRuleLinked] = useState(false)

  const symptoms = useAll(['symptoms', 'all'], (params) => expertApi.symptoms(params))
  const rules = useAll(['expert-rules', 'all'], (params) => expertApi.expertRules(params), {
    enabled: canViewRules,
  })

  const ruleSymptomIds = useMemo(() => {
    const ids = new Set()
    for (const rule of rules.data ?? []) {
      if (rule.is_active === false) continue
      for (const condition of rule.conditions ?? []) {
        if (condition.symptom_id == null || condition.operator === 'absent') continue
        ids.add(condition.symptom_id)
      }
    }
    return ids
  }, [rules.data])

  const activeSymptoms = useMemo(() => {
    const needle = search.trim().toLowerCase()
    return (symptoms.data ?? [])
      .filter((s) => s.is_active)
      .filter((s) => (onlyRuleLinked && canViewRules ? ruleSymptomIds.has(s.id) : true))
      .filter((s) =>
        needle
          ? `${s.name ?? ''} ${s.code ?? ''}`.toLowerCase().includes(needle)
          : true,
      )
      .sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''))
  }, [symptoms.data, search, onlyRuleLinked, ruleSymptomIds, canViewRules])

  const selectedSymptoms = useMemo(
    () => (symptoms.data ?? []).filter((s) => selected.includes(s.id)),
    [symptoms.data, selected],
  )

  const unusedSelected = useMemo(
    () => (canViewRules ? selectedSymptoms.filter((s) => !ruleSymptomIds.has(s.id)) : []),
    [selectedSymptoms, ruleSymptomIds, canViewRules],
  )

  const canFilterByRules = canViewRules && ruleSymptomIds.size > 0

  const toggle = (id) =>
    setSelected((curr) => (curr.includes(id) ? curr.filter((i) => i !== id) : [...curr, id]))

  const runEvaluate = async () => {
    if (selected.length === 0) {
      setError('Select at least one symptom to evaluate.')
      return
    }
    setLoading(true)
    setError(null)
    try {
      const data = await expertApi.evaluate(selected)
      setResult(data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="grid grid-cols-1 gap-6 lg:grid-cols-[420px_1fr]">
      <Card title="Select symptoms" subtitle={`${selected.length} selected`}>
        {!canUse ? (
          <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
            <ShieldAlert className="mt-0.5 size-5 shrink-0" aria-hidden />
            <p>
              You can inspect the library, but you need the{' '}
              <span className="font-semibold">expert-system.use</span> permission to run an
              evaluation. Contact an administrator if you should have access.
            </p>
          </div>
        ) : null}
        {symptoms.isLoading ? (
          <RowSkeleton rows={5} cols={1} />
        ) : symptoms.isError ? (
          <ErrorState message={symptoms.error.message} onRetry={() => symptoms.refetch()} />
        ) : (
          <>
            {selectedSymptoms.length > 0 ? (
              <div className="mb-3 flex flex-wrap gap-1.5" aria-label="Selected symptoms">
                {selectedSymptoms.map((s) => (
                  <span
                    key={s.id}
                    className="inline-flex max-w-full items-center gap-1.5 rounded-full bg-brand-soft py-1 pl-2.5 pr-1 text-xs font-medium text-brand-dark"
                  >
                    <span className="max-w-[180px] truncate">{s.name}</span>
                    <button
                      type="button"
                      onClick={() => toggle(s.id)}
                      aria-label={`Remove ${s.name}`}
                      className="grid size-4 shrink-0 place-items-center rounded-full text-brand transition-colors hover:bg-brand hover:text-white"
                    >
                      <X className="size-3" aria-hidden />
                    </button>
                  </span>
                ))}
              </div>
            ) : null}
            <div className="mb-3 space-y-2">
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search symptoms…"
                aria-label="Search symptoms"
                className="h-10 w-full rounded-xl border border-line bg-surface px-3 text-sm outline-none transition focus:ring-2 focus:ring-brand"
              />
              {canFilterByRules ? (
                <label className="flex items-center gap-2 text-xs font-medium text-ink-muted">
                  <input
                    type="checkbox"
                    className="size-4 accent-brand"
                    checked={onlyRuleLinked}
                    onChange={(e) => setOnlyRuleLinked(e.target.checked)}
                  />
                  Only show symptoms used by a rule ({ruleSymptomIds.size})
                </label>
              ) : null}
            </div>
            {activeSymptoms.length === 0 ? (
              <p className="text-sm text-ink-muted">
                {onlyRuleLinked
                  ? 'No rule-linked symptom matches your search.'
                  : symptoms.data?.length === 0
                    ? 'No active symptoms yet. Add them from the Knowledge Base tab.'
                    : 'No symptom matches your search.'}
              </p>
            ) : (
              <div className="max-h-[420px] overflow-y-auto pr-1">
                {activeSymptoms.map((s) => {
                  const checked = selected.includes(s.id)
                  const inRule = canViewRules && ruleSymptomIds.has(s.id)
                  return (
                    <label
                      key={s.id}
                      role="checkbox"
                      aria-checked={checked}
                      tabIndex={0}
                      className="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-1.5 text-sm transition-colors hover:bg-canvas aria-checked:bg-brand-soft"
                    >
                      <input
                        type="checkbox"
                        className="size-4 accent-brand"
                        checked={checked}
                        disabled={!canUse}
                        onChange={() => toggle(s.id)}
                      />
                      <span className="min-w-0 flex-1">
                        <span className="block truncate font-medium text-ink">{s.name}</span>
                        {s.code ? <span className="block font-mono text-xs text-ink-muted">{s.code}</span> : null}
                      </span>
                      {inRule ? (
                        <span className="shrink-0 rounded-full bg-brand-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand">
                          In a rule
                        </span>
                      ) : null}
                    </label>
                  )
                })}
              </div>
            )}
          </>
        )}
        <div className="mt-4">
          <Button className="w-full" onClick={runEvaluate} disabled={!canUse || loading} loading={loading}>
            <Sparkles className="size-4" aria-hidden /> Evaluate symptoms
          </Button>
          {error ? (
            <p role="alert" className="mt-2 text-sm font-medium text-red-600">
              {error}
            </p>
          ) : null}
        </div>
      </Card>

      <Card title="Evaluation results">
        {result === null && !loading ? (
          <EmptyState
            icon={Stethoscope}
            title="No evaluation yet"
            message="Select symptoms on the left and run an evaluation to see matched rules."
          />
        ) : loading ? (
          <CardSkeleton rows={4} />
        ) : result.matched_rule_count === 0 ? (
          <div className="space-y-3">
            <EmptyState
              icon={Sparkles}
              title="No rules matched"
              message="No active rule matched this combination. Expert rules match a specific set of symptoms (a disease pattern), so a partial selection may not trigger one — try adding symptoms that belong together."
            />
            {unusedSelected.length > 0 ? (
              <p className="rounded-xl bg-canvas px-4 py-3 text-sm text-ink-muted">
                Not part of any rule:{' '}
                <span className="font-medium text-ink">
                  {unusedSelected.map((s) => s.name).join(', ')}
                </span>
                . Selecting only these can never match.
              </p>
            ) : null}
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-brand-soft px-4 py-3">
              <div>
                <p className="text-sm font-semibold text-brand">You have {result.matched_rule_count} matched rule{result.matched_rule_count === 1 ? '' : 's'}.</p>
                <p className="text-xs text-ink-muted">Engine {result.engine} v{result.engine_version}</p>
              </div>
              <Badge tone={result.is_clinically_validated ? 'green' : 'gray'}>
                {result.is_clinically_validated ? 'Clinically validated' : 'For decision support only'}
              </Badge>
            </div>
            <ul className="space-y-3">
              {result.matched_rules.map((rule) => (
                <li key={rule.rule_code} className="rounded-2xl border border-line p-4">
                  <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                      <p className="text-base font-semibold text-ink">{rule.rule_name}</p>
                      <p className="font-mono text-xs text-ink-muted">{rule.rule_code}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge tone="navy">Priority {rule.priority}</Badge>
                      <Badge tone={DISEASE_SEVERITY[rule.severity_level]?.tone ?? 'gray'}>
                        {(DISEASE_SEVERITY[rule.severity_level]?.label ?? rule.severity_level) ?? 'Not set'}
                      </Badge>
                    </div>
                  </div>
                  <p className="mt-2 text-lg font-semibold text-brand">{(Number(rule.confidence_score) * 100).toFixed(0)}% confidence</p>
                  <div className="mt-1 flex flex-wrap gap-1.5">
                    {[
                      ['Disease', rule.disease_name ?? rule.disease_code],
                      ['Matched groups', rule.matched_condition_groups?.join(', ') ?? '—'],
                    ].map(([label, value]) => (
                      <span key={label} className="inline-flex items-center gap-1.5 rounded-full bg-canvas px-3 py-1 text-xs text-ink-muted">
                        <span className="font-semibold text-ink">{label}:</span> {value}
                      </span>
                    ))}
                  </div>
                  {rule.explanation ? (
                    <p className="mt-3 text-sm leading-relaxed text-ink-muted">{rule.explanation}</p>
                  ) : null}
                </li>
              ))}
            </ul>
            {result.notice ? (
              <p className="flex items-start gap-2 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-800">
                <ShieldAlert className="mt-0.5 size-4 shrink-0" aria-hidden />
                <span>{result.notice}</span>
              </p>
            ) : null}
          </div>
        )}
      </Card>
    </div>
  )
}

const KB_TABS = [
  { value: 'diseases', label: 'Diseases' },
  { value: 'symptoms', label: 'Symptoms' },
  { value: 'rules', label: 'Expert Rules' },
]

function KnowledgeBaseTab({ canView, canManage }) {
  const [kind, setKind] = useState('diseases')
  const [open, setOpen] = useState(false)
  const [editing, setEditing] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)

  const fetcher = kind === 'diseases' ? expertApi.diseases : kind === 'symptoms' ? expertApi.symptoms : expertApi.expertRules
  const query = usePaginated(['knowledge-base', kind], (params) => fetcher(params), { page })

  const all = query.data?.data ?? []
  const items = search
    ? all.filter((it) =>
        [it.name, it.code, it.name_km, it.disease?.name].filter(Boolean).some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : all

  const shouldRefresh = () => {
    query.refetch()
    setEditing(null)
    setOpen(false)
  }

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      if (kind === 'diseases') await expertApi.removeDisease(deleting.id)
      else if (kind === 'symptoms') await expertApi.removeSymptom(deleting.id)
      else await expertApi.removeExpertRule(deleting.id)
      toast.success(`${kind === 'rules' ? 'Rule' : kind === 'diseases' ? 'Disease' : 'Symptom'} deleted.`)
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const refreshKey = [kind, (editing ? editing.id : 0), open, query.data?.current_page ?? 1].join('-')

  const modalFor = () => {
    switch (kind) {
      case 'diseases':
        return <DiseaseFormModal key={refreshKey} open={open} onClose={() => setOpen(false)} initial={editing} onSaved={shouldRefresh} />
      case 'symptoms':
        return <SymptomFormModal key={refreshKey} open={open} onClose={() => setOpen(false)} initial={editing} onSaved={shouldRefresh} />
      default:
        return <ExpertRuleFormModal key={refreshKey} open={open} onClose={() => setOpen(false)} initial={editing} onSaved={shouldRefresh} />
    }
  }

  if (!canView) {
    return (
      <Card>
        <EmptyState
          icon={ShieldAlert}
          title="Knowledge base access required"
          message="You don't have the knowledge-base.view permission."
        />
      </Card>
    )
  }

  return (
    <Card>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <Tabs value={kind} onChange={(v) => { setKind(v); setSearch(''); setPage(1) }} tabs={KB_TABS} />
        {canManage ? (
          <Button size="sm" onClick={() => { setEditing(null); setOpen(true) }}>
            <Plus className="size-4" aria-hidden /> New {kind === 'rules' ? 'rule' : kind === 'diseases' ? 'disease' : 'symptom'}
          </Button>
        ) : null}
      </div>
      <div className="mb-4">
        <SearchInput placeholder={`Search ${kind}…`} className="w-full sm:w-80" onDebounced={setSearch} />
      </div>

      {query.isLoading ? (
        <RowSkeleton rows={6} cols={4} />
      ) : query.isError ? (
        <ErrorState message={query.error.message} onRetry={() => query.refetch()} />
      ) : all.length === 0 ? (
        <EmptyState title={`No ${kind} yet`} message={canManage ? 'Add the first entry to the knowledge base.' : 'The knowledge base is empty.'} />
      ) : items.length === 0 ? (
        <EmptyState title="No matches" message="Nothing matches your search." />
      ) : (
        <div className="overflow-x-auto rounded-xl border border-line">
          <table className="w-full min-w-[720px] text-left text-sm">
            <thead className="bg-canvas text-xs uppercase tracking-wide text-ink-muted">
              <tr>
                {kind === 'rules' ? (
                  <>
                    <th className="px-4 py-3 font-semibold">Code</th>
                    <th className="px-4 py-3 font-semibold">Name</th>
                    <th className="px-4 py-3 font-semibold">Disease</th>
                    <th className="px-4 py-3 font-semibold">Priority</th>
                    <th className="px-4 py-3 font-semibold">Confidence</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th><span className="sr-only">Actions</span></th>
                  </>
                ) : (
                  <>
                    <th className="px-4 py-3 font-semibold">Code</th>
                    <th className="px-4 py-3 font-semibold">Name</th>
                    <th className="px-4 py-3 font-semibold">{kind === 'diseases' ? 'Category' : 'Khmer name'}</th>
                    <th className="px-4 py-3 font-semibold">Severity</th>
                    <th className="px-4 py-3 font-semibold">Status</th>
                    <th className="px-4 py-3 font-semibold">Linked</th>
                    <th><span className="sr-only">Actions</span></th>
                  </>
                )}
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {items.map((it) => (
                <tr key={it.id}>
                  <td className="px-4 py-3 font-mono text-xs text-ink-muted">{it.code}</td>
                  <td className="px-4 py-3 font-medium text-ink">{it.name}</td>
                  {kind === 'rules' ? (
                    <>
                      <td className="px-4 py-3 text-ink-muted">{it.disease?.name ?? `#${it.disease_id}`}</td>
                      <td className="px-4 py-3 text-ink-muted">{it.priority}</td>
                      <td className="px-4 py-3 text-ink-muted">{Number(it.confidence_score) * 100}%</td>
                    </>
                  ) : (
                    <>
                      <td className="px-4 py-3 text-ink-muted">{kind === 'diseases' ? (it.category || '—') : (it.name_km || '—')}</td>
                      <td className="px-4 py-3">
                        <Badge tone={DISEASE_SEVERITY[it.severity_level]?.tone ?? 'gray'}>
                          {(DISEASE_SEVERITY[it.severity_level]?.label ?? it.severity_level) ?? 'Not set'}
                        </Badge>
                      </td>
                      <td className="px-4 py-3">
                        {it.is_active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Inactive</Badge>}
                      </td>
                      <td className="px-4 py-3 text-ink-muted">
                        {kind === 'diseases' ? (it.symptoms?.length ?? 0) : (it.diseases?.length ?? 0)} linked
                      </td>
                    </>
                  )}
                  {kind === 'rules' ? (
                    <td className="px-4 py-3">
                      {it.is_active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Inactive</Badge>}
                    </td>
                  ) : null}
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-1">
                      {canManage ? (
                        <>
                          <button type="button" aria-label={`Edit ${it.name}`} className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink" onClick={() => { setEditing(it); setOpen(true) }}>
                            <Pencil className="size-4" aria-hidden />
                          </button>
                          <button type="button" aria-label={`Delete ${it.name}`} className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600" onClick={() => setDeleting(it)}>
                            <Trash2 className="size-4" aria-hidden />
                          </button>
                        </>
                      ) : null}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      {modalFor()}

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title={`Delete ${deleting?.name ?? 'entry'}?`}
        message={kind === 'rules' ? 'The rule will be removed from future evaluations.' : 'Entries that are referenced by expert rules cannot be deleted.'}
        confirmLabel="Delete"
      />
    </Card>
  )
}

function AuditTab({ canView }) {
  const [page, setPage] = useState(1)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const query = usePaginated(['audit-logs', { page, from, to }], (params) => expertApi.auditLogs(params), { page })

  if (!canView) {
    return (
      <Card>
        <EmptyState icon={ShieldAlert} title="Audit access required" message="You don't have the audit-logs.view permission." />
      </Card>
    )
  }

  return (
    <Card title="Evaluation audit trail" subtitle="Historical expert-system evaluations recorded by the engine's audit table.">
      <div className="mb-4 flex flex-wrap items-end gap-3">
        <label className="flex flex-col gap-1 text-xs font-medium text-ink-muted" htmlFor="aud-from">
          From
          <input id="aud-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="h-10 w-40 rounded-xl border border-line bg-surface px-3 text-sm outline-none transition focus:ring-2 focus:ring-brand focus:ring-offset-0" />
        </label>
        <label className="flex flex-col gap-1 text-xs font-medium text-ink-muted" htmlFor="aud-to">
          To
          <input id="aud-to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="h-10 w-40 rounded-xl border border-line bg-surface px-3 text-sm outline-none transition focus:ring-2 focus:ring-brand focus:ring-offset-0" />
        </label>
        <Button size="sm" variant="outline" onClick={() => { setPage(1); query.refetch() }}>
          Apply
        </Button>
        <Button size="sm" variant="ghost" onClick={() => { setFrom(''); setTo(''); setPage(1); query.refetch() }}>
          Clear
        </Button>
      </div>
      {query.isLoading ? (
        <RowSkeleton rows={5} cols={4} />
      ) : query.isError ? (
        <ErrorState message={query.error.message} onRetry={() => query.refetch()} />
      ) : query.data?.data.length === 0 ? (
        <EmptyState icon={CalendarDays} title="No evaluations recorded" message="Audit trail is empty. Enable EXPERT_AUDIT_ENABLED in the expert system to start recording." />
      ) : (
        <div className="overflow-x-auto rounded-xl border border-line">
          <table className="w-full min-w-[640px] text-left text-sm">
            <thead className="bg-canvas text-xs uppercase tracking-wide text-ink-muted">
              <tr>
                <th className="px-4 py-3 font-semibold">When</th>
                <th className="px-4 py-3 font-semibold">Matches</th>
                <th className="px-4 py-3 font-semibold">Rule codes</th>
                <th className="px-4 py-3 font-semibold">Engine</th>
                <th className="px-4 py-3 font-semibold">Flag</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {query.data.data.map((log) => (
                <tr key={log.id}>
                  <td className="px-4 py-3 text-ink-muted">{formatDateTime(log.created_at)}</td>
                  <td className="px-4 py-3 font-semibold text-ink">{log.matched_rule_count}</td>
                  <td className="px-4 py-3">
                    <span className="line-clamp-2 max-w-xs font-mono text-xs text-ink-muted">{log.matched_rule_codes ?? '—'}</span>
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-ink-muted">{log.engine_version ?? '—'}</td>
                  <td className="px-4 py-3">
                    {log.is_clinically_validated ? <Badge tone="green">Validated</Badge> : <Badge tone="gray">—</Badge>}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
    </Card>
  )
}