import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  AlertOctagon,
  AlertTriangle,
  Bell,
  Check,
  CheckCheck,
  ExternalLink,
  Info,
  Loader2,
  RotateCcw,
  SearchX,
  XCircle,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Button from '../components/ui/Button.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Select from '../components/ui/Select.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import Modal from '../components/ui/Modal.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState, ForbiddenState } from '../components/ui/State.jsx'
import { systemAlertApi } from '../api/communication.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { useLanguage } from '../hooks/useLanguage.js'
import { formatDateTime, formatRelativeTime } from '../utils/format.js'
import { cn } from '../utils/cn.js'
import {
  ALERT_CATEGORIES,
  ALERT_STATUSES,
  ALERT_SEVERITIES,
  canViewAnyAlert,
  visibleAlertCategories,
} from '../constants/alerts.js'

const SEVERITY_ICONS = {
  critical: AlertOctagon,
  warning: AlertTriangle,
  info: Info,
}

const STATUS_FILTERS = [
  { value: '', label: 'All alerts' },
  { value: 'active', label: 'Active' },
  { value: 'unread', label: 'Unread' },
  { value: 'read', label: 'Read' },
  { value: 'acknowledged', label: 'Acknowledged' },
  { value: 'resolved', label: 'Resolved' },
]

function severityMeta(severity) {
  return ALERT_SEVERITIES[severity] ?? ALERT_SEVERITIES.info
}

function SeverityIcon({ severity }) {
  const Icon = SEVERITY_ICONS[severity] ?? Info
  const tones = {
    critical: 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
    warning: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
  }

  return (
    <span
      className={cn('grid size-9 shrink-0 place-items-center rounded-xl', tones[severity] ?? tones.info)}
      role="img"
      aria-label={severityMeta(severity).label}
    >
      <Icon className="size-4" aria-hidden />
    </span>
  )
}

export default function NotificationsPage() {
  const { can, user } = useAuth()
  const { t } = useLanguage()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [severity, setSeverity] = useState('')
  const [category, setCategory] = useState('')
  const [detail, setDetail] = useState(null)
  const [busyId, setBusyId] = useState(null)

  const allowed = canViewAnyAlert(user?.permissions ?? [])
  const categories = visibleAlertCategories(user?.permissions ?? [])
  const canManage = can('notifications.manage')

  const query = usePaginated(
    ['system-alerts', 'page', { page, search, status, severity, category }],
    () =>
      systemAlertApi.list({
        page,
        search: search || undefined,
        status: status || undefined,
        severity: severity || undefined,
        category: category || undefined,
      }),
    { page, enabled: allowed },
  )

  const summary = useQuery({
    queryKey: ['system-alerts', 'summary'],
    queryFn: systemAlertApi.summary,
    enabled: allowed,
    staleTime: 30_000,
  })

  const items = query.data?.data ?? []
  const stats = summary.data ?? {}
  const hasFilters = Boolean(search || status || severity || category)

  const resetFilters = () => {
    setSearch('')
    setStatus('')
    setSeverity('')
    setCategory('')
    setPage(1)
  }

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['system-alerts'] })
    queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }

  const runAction = async (alert, action, successMessage) => {
    if (!alert?.id || busyId) return
    setBusyId(alert.id)
    try {
      await action(alert.id)
      invalidate()
      toast.success(successMessage)
      if (detail?.id === alert.id) setDetail(null)
    } catch (error) {
      toast.error(error.message || t('Something went wrong.'))
    } finally {
      setBusyId(null)
    }
  }

  const markRead = (alert) => runAction(alert, systemAlertApi.markRead, t('Alert marked as read.'))
  const acknowledge = (alert) =>
    runAction(alert, systemAlertApi.acknowledge, t('Alert acknowledged.'))
  const resolve = (alert) => runAction(alert, systemAlertApi.resolve, t('Alert resolved.'))

  const markAllRead = async () => {
    try {
      await systemAlertApi.markAllRead()
      invalidate()
      toast.success(t('All visible alerts marked as read.'))
    } catch (error) {
      toast.error(error.message || t('Something went wrong.'))
    }
  }

  const openDestination = (url) => {
    if (!url) return
    setDetail(null)
    if (/^https?:\/\//i.test(url)) {
      window.open(url, '_blank', 'noopener,noreferrer')
      return
    }
    navigate(url)
  }

  if (!allowed) {
    return (
      <div className="space-y-6">
        <PageHeader title={t('Notifications')} />
        <Card>
          <ForbiddenState
            message={t(
              'You need permission to view at least one alert category (for example inventory, appointments, staff, or attendance).',
            )}
          />
        </Card>
      </div>
    )
  }

  const detailRows = detail
    ? [
        { label: t('Category'), value: ALERT_CATEGORIES[detail.category]?.label ?? detail.category },
        { label: t('Severity'), value: severityMeta(detail.severity).label },
        { label: t('Status'), value: ALERT_STATUSES[detail.status]?.label ?? detail.status },
        { label: t('Module'), value: detail.module ?? '—' },
        { label: t('Created'), value: formatDateTime(detail.created_at) },
        detail.acknowledged_at
          ? { label: t('Acknowledged'), value: formatDateTime(detail.acknowledged_at) }
          : null,
        detail.resolved_at ? { label: t('Resolved'), value: formatDateTime(detail.resolved_at) } : null,
      ].filter(Boolean)
    : []

  return (
    <div className="space-y-6">
      <PageHeader
        title={t('Notifications')}
        description={t(
          'Operational alerts from across the clinic. Reading an alert never resolves it.',
        )}
        actions={
          query.isSuccess && (stats.unread ?? 0) > 0 ? (
            <Button variant="outline" size="sm" onClick={markAllRead}>
              <CheckCheck className="size-4" aria-hidden /> {t('Mark all read')}
            </Button>
          ) : null
        }
      />

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard
          label={t('Unread')}
          value={stats.unread ?? 0}
          icon={Bell}
          tone="brand"
          loading={summary.isLoading}
        />
        <StatCard
          label={t('Critical')}
          value={stats.critical ?? 0}
          icon={AlertOctagon}
          tone="red"
          loading={summary.isLoading}
        />
        <StatCard
          label={t('Warnings')}
          value={stats.warning ?? 0}
          icon={AlertTriangle}
          tone="amber"
          loading={summary.isLoading}
        />
        <StatCard
          label={t('Acknowledged')}
          value={stats.acknowledged ?? 0}
          icon={Check}
          tone="navy"
          loading={summary.isLoading}
        />
      </div>

      <Card className="p-4">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div className="w-full lg:max-w-xs">
            <Select
              aria-label={t('Filter by status')}
              value={status}
              onChange={(e) => {
                setStatus(e.target.value)
                setPage(1)
              }}
            >
              {STATUS_FILTERS.map((option) => (
                <option key={option.value} value={option.value}>
                  {t(option.label)}
                </option>
              ))}
            </Select>
          </div>
          <div className="w-full lg:max-w-xs">
            <Select
              aria-label={t('Filter by severity')}
              value={severity}
              onChange={(e) => {
                setSeverity(e.target.value)
                setPage(1)
              }}
            >
              <option value="">{t('All severities')}</option>
              {Object.entries(ALERT_SEVERITIES).map(([value, meta]) => (
                <option key={value} value={value}>
                  {t(meta.label)}
                </option>
              ))}
            </Select>
          </div>
          <div className="w-full lg:max-w-xs">
            <Select
              aria-label={t('Filter by category')}
              value={category}
              onChange={(e) => {
                setCategory(e.target.value)
                setPage(1)
              }}
            >
              <option value="">{t('All categories')}</option>
              {categories.map((option) => (
                <option key={option.value} value={option.value}>
                  {t(option.label)}
                </option>
              ))}
            </Select>
          </div>
          <div className="w-full flex-1">
            <SearchInput
              placeholder={t('Search alerts…')}
              onDebounced={(value) => {
                setSearch(value)
                setPage(1)
              }}
            />
          </div>
          {hasFilters ? (
            <Button variant="ghost" size="sm" onClick={resetFilters}>
              <RotateCcw className="size-4" aria-hidden /> {t('Reset')}
            </Button>
          ) : null}
        </div>
      </Card>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <div className="space-y-3 p-4">
            {Array.from({ length: 5 }).map((_, index) => (
              <RowSkeleton key={index} />
            ))}
          </div>
        ) : query.isError ? (
          <div className="p-4">
            <ErrorState
              message={t('We could not load your alerts. Please try again.')}
              onRetry={() => query.refetch()}
            />
          </div>
        ) : items.length === 0 ? (
          <EmptyState
            icon={hasFilters ? SearchX : Bell}
            title={hasFilters ? t('No matching alerts') : t("You're all caught up.")}
            message={
              hasFilters
                ? t('Try clearing the filters to see every alert.')
                : t('New operational alerts will appear here.')
            }
            action={
              hasFilters ? (
                <Button variant="outline" size="sm" onClick={resetFilters}>
                  <RotateCcw className="size-4" aria-hidden /> {t('Clear filters')}
                </Button>
              ) : null
            }
          />
        ) : (
          <>
            <ul className="divide-y divide-line">
              {items.map((alert) => {
                const isBusy = busyId === alert.id
                const severity = severityMeta(alert.severity)
                const statusMeta = ALERT_STATUSES[alert.status] ?? ALERT_STATUSES.active
                const isResolved = alert.status === 'resolved'

                return (
                  <li
                    key={alert.id}
                    className={cn(
                      'flex flex-col gap-3 p-4 transition-colors hover:bg-canvas/50 sm:flex-row sm:items-start',
                      !alert.read_at && !isResolved && 'bg-brand-faint/60',
                    )}
                  >
                    <button
                      type="button"
                      onClick={() => setDetail(alert)}
                      className="flex min-w-0 flex-1 items-start gap-3 text-left"
                    >
                      <SeverityIcon severity={alert.severity} />
                      <span className="min-w-0 flex-1">
                        <span className="flex flex-wrap items-center gap-2">
                          {!alert.read_at && !isResolved ? (
                            <span className="size-2 shrink-0 rounded-full bg-brand" aria-hidden />
                          ) : null}
                          <span className="text-sm font-semibold text-ink">{alert.title}</span>
                        </span>
                        {alert.message ? (
                          <span className="mt-0.5 block text-sm text-ink-muted">{alert.message}</span>
                        ) : null}
                        <span className="mt-2 flex flex-wrap items-center gap-1.5">
                          <Badge tone={severity.tone}>{t(severity.label)}</Badge>
                          <Badge tone={statusMeta.tone}>{t(statusMeta.label)}</Badge>
                          {ALERT_CATEGORIES[alert.category] ? (
                            <Badge tone="gray">{t(ALERT_CATEGORIES[alert.category].label)}</Badge>
                          ) : null}
                          {alert.module ? <Badge tone="gray">{alert.module}</Badge> : null}
                        </span>
                        <span className="mt-1.5 block text-xs text-ink-muted">
                          {formatDateTime(alert.created_at)} ·{' '}
                          {formatRelativeTime(alert.created_at)}
                        </span>
                      </span>
                    </button>

                    <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                      {isBusy ? (
                        <Loader2 className="size-4 animate-spin text-ink-muted" aria-hidden />
                      ) : null}
                      {!alert.read_at && !isResolved ? (
                        <Button variant="ghost" size="sm" disabled={isBusy} onClick={() => markRead(alert)}>
                          <Check className="size-4" aria-hidden /> {t('Mark read')}
                        </Button>
                      ) : null}
                      {canManage && !isResolved ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          disabled={isBusy}
                          onClick={() => acknowledge(alert)}
                        >
                          {t('Acknowledge')}
                        </Button>
                      ) : null}
                      {canManage && !isResolved ? (
                        <Button
                          variant="outline"
                          size="sm"
                          disabled={isBusy}
                          onClick={() => resolve(alert)}
                        >
                          <XCircle className="size-4" aria-hidden /> {t('Resolve')}
                        </Button>
                      ) : null}
                      {alert.url ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openDestination(alert.url)}
                          aria-label={t('Open related page')}
                        >
                          <ExternalLink className="size-4" aria-hidden />
                          <span className="hidden sm:inline">{t('Open')}</span>
                        </Button>
                      ) : null}
                    </div>
                  </li>
                )
              })}
            </ul>
            <Pagination
              page={query.data?.current_page ?? page}
              lastPage={query.data?.last_page ?? 1}
              total={query.data?.total ?? items.length}
              onChange={setPage}
            />
          </>
        )}
      </Card>

      <Modal
        open={Boolean(detail)}
        onClose={() => setDetail(null)}
        title={detail?.title ?? t('Alert')}
        size="sm"
        footer={
          detail ? (
            <>
              {canManage && detail.status !== 'resolved' ? (
                <Button
                  variant="outline"
                  className="mr-auto"
                  loading={busyId === detail.id}
                  onClick={() => resolve(detail)}
                >
                  <XCircle className="size-4" aria-hidden /> {t('Resolve')}
                </Button>
              ) : null}
              <Button variant="outline" onClick={() => setDetail(null)}>
                {t('Close')}
              </Button>
              {detail.url ? (
                <Button onClick={() => openDestination(detail.url)}>
                  <ExternalLink className="size-4" aria-hidden /> {t('Open')}
                </Button>
              ) : null}
            </>
          ) : null
        }
      >
        {detail ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={severityMeta(detail.severity).tone}>
                {t(severityMeta(detail.severity).label)}
              </Badge>
              <Badge tone={(ALERT_STATUSES[detail.status] ?? ALERT_STATUSES.active).tone}>
                {t((ALERT_STATUSES[detail.status] ?? ALERT_STATUSES.active).label)}
              </Badge>
              <span className="text-xs text-ink-muted">{formatDateTime(detail.created_at)}</span>
            </div>
            {detail.message ? (
              <p className="whitespace-pre-wrap break-words text-sm leading-relaxed text-ink">
                {detail.message}
              </p>
            ) : null}
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-xl border border-line bg-canvas/60 p-3 text-sm">
              {detailRows.map((row) => (
                <div key={row.label} className="contents">
                  <dt className="text-ink-muted">{row.label}</dt>
                  <dd className="break-words font-medium text-ink">{row.value}</dd>
                </div>
              ))}
            </dl>
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
