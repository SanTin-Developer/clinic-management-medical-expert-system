import { useNavigate } from 'react-router-dom'
import {
  Activity,
  CalendarDays,
  CircleDollarSign,
  HeartPulse,
  PackageSearch,
  Pill,
  Users,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { reportApi } from '../api/reports.js'
import { appointmentApi } from '../api/appointments.js'
import { prescriptionApi } from '../api/prescriptions.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import StatCard from '../components/ui/StatCard.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import { CardSkeleton, RowSkeleton } from '../components/ui/Skeleton.jsx'
import Button from '../components/ui/Button.jsx'
import {
  formatCurrency,
  formatDate,
  formatTime,
  patientFullName,
} from '../utils/format.js'
import { statusBadgeFor, APPOINTMENT_STATUS, PRESCRIPTION_STATUS } from '../utils/status.js'

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

  const today = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(new Date())

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-sm font-medium text-brand">{today}</p>
          <h1 className="mt-1 text-2xl font-bold tracking-tight text-ink">
            {greeting()}, {user?.name?.split(' ')[0] ?? 'there'}
          </h1>
          <p className="mt-1 text-sm text-ink-muted">
            Here's what's happening across the clinic today.
          </p>
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