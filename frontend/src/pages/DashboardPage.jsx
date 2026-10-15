import { useNavigate } from 'react-router-dom'
import {
  Activity,
  CalendarDays,
  CircleDollarSign,
  HeartPulse,
  PackageSearch,
  Pill,
  QrCode,
  Stethoscope,
  Users,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { reportApi } from '../api/reports.js'
import { dashboardApi } from '../api/dashboard.js'
import { appointmentApi } from '../api/appointments.js'
import { prescriptionApi } from '../api/prescriptions.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import { useDashboardRealtime } from '../hooks/useDashboardRealtime.js'
import { isRealtimeConfigured } from '../lib/echo.js'
import StatCard from '../components/ui/StatCard.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import { CardSkeleton, RowSkeleton } from '../components/ui/Skeleton.jsx'
import Button from '../components/ui/Button.jsx'
import TrendAreaChart from '../components/charts/TrendAreaChart.jsx'
import BarChart from '../components/charts/BarChart.jsx'
import DonutChart from '../components/charts/DonutChart.jsx'
import {
  formatCurrency,
  formatDate,
  formatNumber,
  formatTime,
  patientFullName,
} from '../utils/format.js'
import { statusBadgeFor, APPOINTMENT_STATUS, PRESCRIPTION_STATUS } from '../utils/status.js'

const STATUS_COLORS = {
  scheduled: '#2563eb',
  confirmed: '#0ea5e9',
  completed: '#0d9488',
  cancelled: '#ef4444',
  no_show: '#f59e0b',
  rescheduled: '#8b5cf6',
}

const FALLBACK_COLORS = ['#2563eb', '#0d9488', '#f59e0b', '#8b5cf6', '#ef4444', '#0ea5e9']

const COMPACT_CURRENCY = new Intl.NumberFormat('en-US', {
  notation: 'compact',
  style: 'currency',
  currency: 'USD',
  maximumFractionDigits: 1,
})

function compactCurrency(value) {
  return COMPACT_CURRENCY.format(Number(value ?? 0))
}

function ChartSkeleton({ height = 200 }) {
  return (
    <div
      className="w-full animate-pulse rounded-xl bg-brand-soft/70"
      style={{ height }}
      aria-hidden
    />
  )
}

function ChartError({ message }) {
  return (
    <p className="py-10 text-center text-sm text-red-600">
      {message || 'Unable to load chart data.'}
    </p>
  )
}

function greeting() {
  const hour = new Date().getHours()
  if (hour >= 5 && hour < 12) return 'Good morning'
  if (hour >= 12 && hour < 17) return 'Good afternoon'
  if (hour >= 17 && hour < 21) return 'Good evening'
  return 'Good night'
}

export default function DashboardPage() {
  const { user, can } = useAuth()
  const navigate = useNavigate()

  const summary = useQuery({
    queryKey: ['report-summary'],
    queryFn: reportApi.summary,
    enabled: can('reports.view'),
  })

  const appointments = usePaginated(
    ['appointments'],
    (params) => appointmentApi.list(params),
    { enabled: can('appointments.view') },
  )

  const prescriptions = usePaginated(
    ['prescriptions'],
    (params) => prescriptionApi.list(params),
    { enabled: can('prescriptions.view') },
  )

  const live = isRealtimeConfigured()

  const analytics = useQuery({
    queryKey: ['dashboard-analytics'],
    queryFn: dashboardApi.analytics,
    enabled: can('reports.view'),
    staleTime: 60_000,
    refetchInterval: live ? false : 60_000,
  })

  useDashboardRealtime(can('reports.view'))

  const statusData = (analytics.data?.appointments?.by_status ?? []).map((item, index) => ({
    label: item.status,
    value: Number(item.total ?? 0),
    color: STATUS_COLORS[item.status] ?? FALLBACK_COLORS[index % FALLBACK_COLORS.length],
  }))

  const today = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(new Date())

  return (
    <div className="space-y-6">
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-brand-dark via-brand to-brand-darker px-6 py-7 text-white shadow-[0_24px_50px_-28px_rgb(37_99_235_/_0.95)] ring-1 ring-white/10 sm:px-8 sm:py-9">
        <div
          className="pointer-events-none absolute inset-0 auth-grid opacity-50"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -right-12 -top-16 size-56 rounded-full bg-white/15 blur-2xl animate-float"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -bottom-24 right-24 size-52 rounded-full bg-sky-300/25 blur-2xl animate-float-slow"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute -left-10 bottom-0 size-40 rounded-full bg-blue-400/20 blur-2xl animate-float-reverse"
          aria-hidden
        />
        <div className="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-50 backdrop-blur-sm">
              {today}
            </p>
            <h1 className="mt-3 text-2xl font-bold tracking-tight text-white sm:text-3xl">
              {greeting()}, {user?.name?.split(' ')[0] ?? 'there'}
            </h1>
            <p className="mt-1.5 max-w-xl text-sm text-blue-50/90">
              Here's what's happening across the clinic today.
            </p>
          </div>

          <div
            className="relative hidden size-44 shrink-0 items-center justify-center lg:flex"
            aria-hidden
          >
            <span className="absolute inset-0 rounded-full bg-white/10 ring-1 ring-white/20 backdrop-blur-sm" />
            <span className="absolute inset-5 rounded-full bg-white/10 ring-1 ring-white/15" />
            <span className="absolute inset-11 rounded-full bg-white/10 ring-1 ring-white/15" />
            <span className="relative grid size-20 place-items-center rounded-3xl bg-white/20 text-white shadow-lg ring-1 ring-white/30 backdrop-blur-sm animate-float">
              <Stethoscope className="size-10" aria-hidden />
            </span>
            <span className="absolute -top-1 right-3 grid size-11 place-items-center rounded-2xl bg-white/20 text-white shadow-lg ring-1 ring-white/30 backdrop-blur-sm animate-float-slow">
              <HeartPulse className="size-5" aria-hidden />
            </span>
            <span className="absolute bottom-2 -left-1 grid size-11 place-items-center rounded-2xl bg-white/20 text-white shadow-lg ring-1 ring-white/30 backdrop-blur-sm animate-float-reverse">
              <Pill className="size-5" aria-hidden />
            </span>
            <span className="absolute bottom-0 right-1 grid size-10 place-items-center rounded-2xl bg-white/20 text-white shadow-lg ring-1 ring-white/30 backdrop-blur-sm animate-float">
              <CalendarDays className="size-5" aria-hidden />
            </span>
          </div>
        </div>
      </div>

      {can('reports.view') ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <StatCard
            label="Patients"
            value={
              summary.isSuccess ? (summary.data.patients.total ?? 0).toLocaleString() : '—'
            }
            sub={
              summary.isSuccess
                ? `${summary.data.patients.new_this_month} new this month`
                : 'Loading…'
            }
            icon={Users}
            tone="brand"
            loading={summary.isLoading}
          />
          <StatCard
            label="Appointments"
            value={
              summary.isSuccess ? (summary.data.appointments.total ?? 0).toLocaleString() : '—'
            }
            sub={
              summary.isSuccess
                ? `${summary.data.appointments.by_status?.scheduled ?? 0} scheduled`
                : 'Loading…'
            }
            icon={CalendarDays}
            tone="blue"
            loading={summary.isLoading}
          />
          <StatCard
            label="Revenue this month"
            value={summary.isSuccess ? formatCurrency(summary.data.billing.revenue_this_month) : '—'}
            sub={
              summary.isSuccess
                ? `${formatCurrency(summary.data.billing.outstanding_balance)} outstanding`
                : 'Loading…'
            }
            icon={CircleDollarSign}
            tone="green"
            loading={summary.isLoading}
          />
          <StatCard
            label="Low-stock medicines"
            value={
              summary.isSuccess ? (summary.data.inventory.low_stock ?? 0).toLocaleString() : '—'
            }
            sub={
              summary.isSuccess
                ? `${summary.data.inventory.expiring_within_30_days} expiring in 30 days`
                : 'Loading…'
            }
            icon={PackageSearch}
            tone="amber"
            loading={summary.isLoading}
          />
        </div>
      ) : (
        <Card>
          <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
            <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand-soft text-brand">
              <HeartPulse className="size-6" aria-hidden />
            </span>
            <div className="min-w-0 flex-1">
              <h2 className="text-base font-semibold text-ink">Welcome to the clinic dashboard</h2>
              <p className="mt-0.5 text-sm text-ink-muted">
                Use the navigation to work with the areas you have access to. Summary statistics are
                available to users with report permissions.
              </p>
            </div>
          </div>
        </Card>
      )}

      {can('reports.view') ? (
        <div className="space-y-6">
          <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <Card
              className="xl:col-span-2"
              title="Appointment volume"
              subtitle="Daily appointments over the last 14 days"
              actions={
                <Badge tone={live ? 'green' : 'gray'}>
                  <span
                    className={`size-1.5 rounded-full ${live ? 'bg-emerald-500' : 'bg-slate-400'}`}
                    aria-hidden
                  />
                  {live ? 'Live' : 'Auto-refresh'}
                </Badge>
              }
            >
              {analytics.isLoading ? (
                <ChartSkeleton height={220} />
              ) : analytics.isError ? (
                <ChartError message={analytics.error.message} />
              ) : (
                <TrendAreaChart
                  data={analytics.data.appointments.trend}
                  color="#2563eb"
                  formatValue={formatNumber}
                />
              )}
            </Card>

            <Card title="Appointment status" subtitle="All recorded appointments">
              {analytics.isLoading ? (
                <ChartSkeleton height={176} />
              ) : analytics.isError ? (
                <ChartError message={analytics.error.message} />
              ) : (
                <DonutChart
                  data={statusData}
                  centerLabel="Appointments"
                  formatValue={formatNumber}
                />
              )}
            </Card>
          </div>

          <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <Card
              className="xl:col-span-2"
              title="Revenue"
              subtitle="Collected payments over the last 6 months"
            >
              {analytics.isLoading ? (
                <ChartSkeleton height={220} />
              ) : analytics.isError ? (
                <ChartError message={analytics.error.message} />
              ) : (
                <BarChart
                  data={analytics.data.revenue.trend}
                  color="#0d9488"
                  formatValue={formatCurrency}
                  formatAxis={compactCurrency}
                />
              )}
            </Card>

            <Card title="Patient registrations" subtitle="New patients over the last 14 days">
              {analytics.isLoading ? (
                <ChartSkeleton height={180} />
              ) : analytics.isError ? (
                <ChartError message={analytics.error.message} />
              ) : (
                <TrendAreaChart
                  data={analytics.data.patients.trend}
                  color="#7c3aed"
                  height={180}
                  formatValue={formatNumber}
                />
              )}
            </Card>
          </div>
        </div>
      ) : null}

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div className="xl:col-span-2">
          {can('appointments.view') ? (
            <Card
              title="Recent appointments"
              actions={
                <Button variant="outline" size="sm" onClick={() => navigate('/appointments')}>
                  View all
                </Button>
              }
            >
              {appointments.isLoading ? (
                <RowSkeleton rows={5} cols={4} />
              ) : appointments.isError ? (
                <p className="py-6 text-center text-sm text-red-600">{appointments.error.message}</p>
              ) : appointments.data?.data?.length ? (
                <Table>
                  <THead>
                    <Tr>
                      <Th>Patient</Th>
                      <Th>Doctor</Th>
                      <Th>When</Th>
                      <Th>Status</Th>
                    </Tr>
                  </THead>
                  <TBody>
                    {appointments.data.data.map((apt) => {
                      const status = statusBadgeFor(APPOINTMENT_STATUS, apt.status)
                      return (
                        <Tr
                          key={apt.id}
                          onClick={() => navigate(`/appointments/${apt.id}`)}
                        >
                          <Td className="font-medium text-ink">
                            {patientFullName(apt.patient)}
                          </Td>
                          <Td>{apt.doctor?.name ?? '—'}</Td>
                          <Td className="text-ink-muted">
                            {formatDate(apt.appointment_date)} · {formatTime(apt.appointment_time)}
                          </Td>
                          <Td>
                            <Badge tone={status.tone}>{status.label}</Badge>
                          </Td>
                        </Tr>
                      )
                    })}
                  </TBody>
                </Table>
              ) : (
                <p className="py-8 text-center text-sm text-ink-muted">
                  No appointments yet.
                </p>
              )}
            </Card>
          ) : (
            <Card title="Quick actions">
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {[
                  { label: 'Scan attendance', to: '/attendance/scan', icon: QrCode, p: 'attendance.clock' },
                  { label: 'Patients', to: '/patients', icon: Users, p: 'patients.view' },
                  { label: 'Appointments', to: '/appointments', icon: CalendarDays, p: 'appointments.view' },
                  { label: 'Prescriptions', to: '/prescriptions', icon: Pill, p: 'prescriptions.view' },
                  { label: 'Pharmacy', to: '/pharmacy', icon: PackageSearch, p: 'medicines.view' },
                ]
                  .filter((item) => can(item.p))
                  .map((item) => (
                    <button
                      key={item.to}
                      type="button"
                      onClick={() => navigate(item.to)}
                      className="flex items-center gap-3 rounded-xl border border-line bg-canvas px-4 py-3 text-left transition-colors hover:border-brand/40 hover:bg-brand-soft/40"
                    >
                      <item.icon className="size-5 text-brand" aria-hidden />
                      <span className="text-sm font-semibold text-ink">{item.label}</span>
                    </button>
                  ))}
              </div>
            </Card>
          )}
        </div>

        <div className="space-y-6">
          {can('prescriptions.view') ? (
            <Card title="Pending prescriptions">
              {prescriptions.isLoading ? (
                <CardSkeleton rows={3} />
              ) : prescriptions.isError ? (
                <p className="py-4 text-center text-sm text-red-600">
                  {prescriptions.error.message}
                </p>
              ) : prescriptions.data?.data?.filter((p) => p.status === 'pending').length ? (
                <ul className="divide-y divide-line">
                  {prescriptions.data.data
                    .filter((p) => p.status === 'pending')
                    .slice(0, 6)
                    .map((rx) => (
                      <li key={rx.id}>
                        <button
                          type="button"
                          onClick={() => navigate(`/prescriptions/${rx.id}`)}
                          className="flex w-full items-center justify-between gap-3 py-2.5 text-left"
                        >
                          <span className="min-w-0">
                            <span className="block truncate text-sm font-medium text-ink">
                              {patientFullName(rx.patient)}
                            </span>
                            <span className="block text-xs text-ink-muted">
                              {rx.prescription_number}
                            </span>
                          </span>
                          <Badge
                            tone={statusBadgeFor(PRESCRIPTION_STATUS, rx.status).tone}
                          >
                            {statusBadgeFor(PRESCRIPTION_STATUS, rx.status).label}
                          </Badge>
                        </button>
                      </li>
                    ))}
                </ul>
              ) : (
                <p className="py-6 text-center text-sm text-ink-muted">
                  No pending prescriptions.
                </p>
              )}
            </Card>
          ) : null}

          {can('billing.view') && summary.isSuccess ? (
            <Card title="Billing snapshot">
              <dl className="space-y-3 text-sm">
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Unpaid invoices</dt>
                  <dd className="font-semibold text-ink">
                    {summary.data.billing.unpaid ?? 0}
                  </dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Outstanding balance</dt>
                  <dd className="font-semibold text-red-600">
                    {formatCurrency(summary.data.billing.outstanding_balance)}
                  </dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Revenue (all time)</dt>
                  <dd className="font-semibold text-emerald-600">
                    {formatCurrency(summary.data.billing.revenue_total)}
                  </dd>
                </div>
              </dl>
            </Card>
          ) : null}

          {can('expert-system.use') ? (
            <Card title="Expert system">
              <div className="flex items-start gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                  <Activity className="size-5" aria-hidden />
                </span>
                <div className="min-w-0">
                  <p className="text-sm text-ink">Run a symptom-based differential analysis.</p>
                  <Button variant="outline" size="sm" className="mt-3" onClick={() => navigate('/expert-system')}>
                    Open expert system
                  </Button>
                </div>
              </div>
            </Card>
          ) : null}
        </div>
      </div>
    </div>
  )
}