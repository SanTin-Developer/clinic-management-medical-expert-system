import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, FileDown, Plus } from 'lucide-react'
import { reportApi, exportReport } from '../api/reports.js'
import { userApi } from '../api/users.js'
import { useAll } from '../hooks/usePaginated.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Input from '../components/ui/Input.jsx'
import Field from '../components/ui/Field.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import { CardSkeleton } from '../components/ui/Skeleton.jsx'
import { ErrorState } from '../components/ui/State.jsx'
import {
  Activity,
  CalendarDays,
  CircleDollarSign,
  PackageSearch,
} from 'lucide-react'
import { formatCurrency, formatDate } from '../utils/format.js'
import { PAYMENT_METHODS, statusBadgeFor, APPOINTMENT_STATUS } from '../utils/status.js'

export default function ReportsPage() {
  const [tab, setTab] = useState('overview')
  const [appt, setAppt] = useState({ from: '', to: '' })
  const [rev, setRev] = useState({ from: '', to: '' })

  const summary = useQuery({ queryKey: ['report-summary'], queryFn: reportApi.summary })

  const appointmentsReport = useQuery({
    queryKey: ['report-appointments', appt],
    queryFn: () => reportApi.appointments(appt),
    enabled: tab === 'appointments',
  })

  const revenueReport = useQuery({
    queryKey: ['report-revenue', rev],
    queryFn: () => reportApi.revenue(rev),
    enabled: tab === 'revenue',
  })

  const inventoryReport = useQuery({
    queryKey: ['report-inventory'],
    queryFn: reportApi.inventory,
    enabled: tab === 'inventory',
  })

  const doctors = useAll(['users', 'all'], (params) => userApi.list(params))
  const doctorNames = useMemo(() => {
    const map = new Map()
    ;(doctors.data ?? []).forEach((u) => map.set(String(u.id), u.name))
    return map
  }, [doctors.data])

  const [exporting, setExporting] = useState(null)

  const runExport = async (type, format, params = {}) => {
    setExporting(`${type}-${format}`)
    try {
      await exportReport(type, format, params)
      toast.success('Report exported.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setExporting(null)
    }
  }

  return (
    <div>
      <PageHeader
        title="Reports"
        description="Operational overview and exportable reports."
        breadcrumb={[{ label: 'Reports' }]}
      />

      <Tabs
        className="mb-5"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'overview', label: 'Overview' },
          { value: 'appointments', label: 'Appointments' },
          { value: 'revenue', label: 'Revenue' },
          { value: 'inventory', label: 'Inventory' },
        ]}
      />

      {tab === 'overview' ? (
        summary.isError ? (
          <ErrorState message={summary.error.message} onRetry={() => summary.refetch()} />
        ) : (
          <div className="space-y-6">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <StatCard label="Patients" value={summary.data ? summary.data.patients.total.toLocaleString() : '—'} sub={summary.data ? `${summary.data.patients.new_this_month} new this month` : undefined} icon={Activity} loading={summary.isLoading} tone="brand" />
              <StatCard label="Appointments" value={summary.data ? summary.data.appointments.total.toLocaleString() : '—'} sub={summary.data ? `${summary.data.appointments.by_status?.scheduled ?? 0} scheduled` : undefined} icon={CalendarDays} loading={summary.isLoading} tone="blue" />
              <StatCard label="Revenue (all time)" value={summary.data ? formatCurrency(summary.data.billing.revenue_total) : '—'} sub={summary.data ? `${formatCurrency(summary.data.billing.outstanding_balance)} outstanding` : undefined} icon={CircleDollarSign} loading={summary.isLoading} tone="green" />
              <StatCard label="Consultations" value={summary.data ? summary.data.consultations.total.toLocaleString() : '—'} sub={summary.data ? `${summary.data.diagnoses.total} diagnoses` : undefined} icon={PackageSearch} loading={summary.isLoading} tone="amber" />
            </div>
            <Card title="Quick export">
              <div className="flex flex-wrap gap-2">
                {[
                  { type: 'appointments', label: 'Appointments' },
                  { type: 'revenue', label: 'Revenue' },
                  { type: 'inventory', label: 'Inventory' },
                ].map((r) => (
                  <span key={r.type} className="inline-flex items-center gap-2">
                    <Button
                      size="sm"
                      variant="outline"
                      loading={exporting === `${r.type}-export`}
                      onClick={() => runExport(r.type, 'export')}
                    >
                      <FileDown className="size-4" aria-hidden /> {r.label} CSV
                    </Button>
                    <Button
                      size="sm"
                      variant="outline"
                      loading={exporting === `${r.type}-pdf`}
                      onClick={() => runExport(r.type, 'pdf')}
                    >
                      <Download className="size-4" aria-hidden /> {r.label} PDF
                    </Button>
                  </span>
                ))}
              </div>
            </Card>
          </div>
        )
      ) : null}

      {tab === 'appointments' ? (
        <Card>
          <ReportFilters
            from={appt.from}
            to={appt.to}
            onChange={(k, v) => setAppt((s) => ({ ...s, [k]: v }))}
            onApply={() => appointmentsReport.refetch()}
            onCsv={() => runExport('appointments', 'export', appt)}
            onPdf={() => runExport('appointments', 'pdf', appt)}
            exporting={exporting}
          />
          {appointmentsReport.isLoading ? (
            <CardSkeleton rows={5} />
          ) : appointmentsReport.isError ? (
            <ErrorState message={appointmentsReport.error.message} onRetry={() => appointmentsReport.refetch()} />
          ) : (
            <div className="mt-5">
              <p className="mb-3 text-sm text-ink-muted">
                Total appointments in range: <span className="font-semibold text-ink">{appointmentsReport.data.total}</span>
              </p>
              <h3 className="mb-2 text-sm font-semibold text-ink">By status</h3>
              <div className="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-3">
                {Object.entries(appointmentsReport.data.by_status ?? {}).map(([status, count]) => {
                  const meta = statusBadgeFor(APPOINTMENT_STATUS, status)
                  return (
                    <div key={status} className="flex items-center justify-between rounded-lg border border-line px-3 py-2">
                      <Badge tone={meta.tone}>{meta.label}</Badge>
                      <span className="text-sm font-semibold text-ink">{count}</span>
                    </div>
                  )
                })}
              </div>
              <h3 className="mb-2 text-sm font-semibold text-ink">By doctor</h3>
              {Object.keys(appointmentsReport.data.by_doctor_id ?? {}).length === 0 ? (
                <p className="text-sm text-ink-muted">No data.</p>
              ) : (
                <ul className="divide-y divide-line">
                  {Object.entries(appointmentsReport.data.by_doctor_id ?? {}).map(([doctorId, count]) => (
                    <li key={doctorId} className="flex items-center justify-between py-2 text-sm">
                      <span className="font-medium text-ink">{doctorNames.get(String(doctorId)) ?? `Doctor #${doctorId}`}</span>
                      <span className="text-ink-muted">{count}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </Card>
      ) : null}

      {tab === 'revenue' ? (
        <Card>
          <ReportFilters
            from={rev.from}
            to={rev.to}
            onChange={(k, v) => setRev((s) => ({ ...s, [k]: v }))}
            onApply={() => revenueReport.refetch()}
            onCsv={() => runExport('revenue', 'export', rev)}
            onPdf={() => runExport('revenue', 'pdf', rev)}
            exporting={exporting}
          />
          {revenueReport.isLoading ? (
            <CardSkeleton rows={5} />
          ) : revenueReport.isError ? (
            <ErrorState message={revenueReport.error.message} onRetry={() => revenueReport.refetch()} />
          ) : (
            <div className="mt-5">
              <div className="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Total revenue" value={formatCurrency(revenueReport.data.total)} icon={CircleDollarSign} tone="green" />
                <StatCard label="Outstanding balance" value={formatCurrency(revenueReport.data.outstanding_balance)} icon={CircleDollarSign} tone="amber" />
                <StatCard label="Days in range" value={String(Object.keys(revenueReport.data.by_day ?? {}).length)} icon={CalendarDays} tone="blue" />
              </div>
              <h3 className="mb-2 text-sm font-semibold text-ink">By day</h3>
              {Object.keys(revenueReport.data.by_day ?? {}).length === 0 ? (
                <p className="text-sm text-ink-muted">No payments in range.</p>
              ) : (
                <div className="mb-5 space-y-1.5">
                  {Object.entries(revenueReport.data.by_day).map(([day, amount]) => (
                    <div key={day} className="flex items-center gap-3 text-sm">
                      <span className="w-24 shrink-0 text-ink-muted">{formatDate(day)}</span>
                      <div className="h-4 flex-1 overflow-hidden rounded bg-brand-soft">
                        <div className="h-full rounded bg-brand" style={{ width: `${Math.max(4, (Number(amount) / maxDay(revenueReport.data.by_day)) * 100)}%` }} />
                      </div>
                      <span className="w-24 shrink-0 text-right font-medium text-ink">{formatCurrency(amount)}</span>
                    </div>
                  ))}
                </div>
              )}
              <h3 className="mb-2 text-sm font-semibold text-ink">By method</h3>
              <ul className="divide-y divide-line">
                {Object.entries(revenueReport.data.by_method ?? {}).map(([method, amount]) => (
                  <li key={method} className="flex items-center justify-between py-2 text-sm">
                    <span className="font-medium text-ink">{PAYMENT_METHODS[method]?.label ?? method}</span>
                    <span className="text-ink-muted">{formatCurrency(amount)}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </Card>
      ) : null}

      {tab === 'inventory' ? (
        <Card>
          {inventoryReport.isLoading ? (
            <CardSkeleton rows={5} />
          ) : inventoryReport.isError ? (
            <ErrorState message={inventoryReport.error.message} onRetry={() => inventoryReport.refetch()} />
          ) : (
            <div className="space-y-5">
              <div className="w-full max-w-xs">
                <StatCard label="Total stock value" value={formatCurrency(inventoryReport.data.total_stock_value)} icon={PackageSearch} tone="green" />
              </div>
              <section>
                <h3 className="mb-2 text-sm font-semibold text-ink">Low stock medicines</h3>
                {inventoryReport.data.low_stock.length === 0 ? (
                  <p className="text-sm text-ink-muted">All medicines are above their reorder level.</p>
                ) : (
                  <Table minWidth="min-w-[480px]">
                    <THead>
                      <Tr>
                        <Th>Medicine</Th>
                        <Th>Code</Th>
                        <Th className="text-right">In stock</Th>
                        <Th className="text-right">Reorder level</Th>
                      </Tr>
                    </THead>
                    <TBody>
                      {inventoryReport.data.low_stock.map((m) => (
                        <Tr key={m.id}>
                          <Td primary className="font-medium text-ink">{m.name}</Td>
                          <Td leading className="font-mono text-xs text-ink-muted">{m.code}</Td>
                          <Td className="text-right text-amber-600">{m.stock}</Td>
                          <Td className="text-right text-ink-muted">{m.reorder_level}</Td>
                        </Tr>
                      ))}
                    </TBody>
                  </Table>
                )}
              </section>
              <section>
                <h3 className="mb-2 text-sm font-semibold text-ink">Expiring within 30 days</h3>
                {inventoryReport.data.expiring_within_30_days.length === 0 ? (
                  <p className="text-sm text-ink-muted">No batches expiring soon.</p>
                ) : (
                  <ul className="divide-y divide-line">
                    {inventoryReport.data.expiring_within_30_days.map((item) => (
                      <li key={item.id} className="flex flex-wrap items-center gap-3 py-2 text-sm">
                        <span className="text-ink-muted">#{item.medicine_id}</span>
                        <span className="font-mono text-xs text-ink-muted">{item.batch_number}</span>
                        <Badge tone="amber">Qty {item.quantity}</Badge>
                        <span className="ml-auto text-ink-muted">Expires {formatDate(item.expiration_date)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            </div>
          )}
        </Card>
      ) : null}
    </div>
  )
}

function maxDay(byDay) {
  return Math.max(1, ...Object.values(byDay).map(Number))
}

function ReportFilters({ from, to, onChange, onApply, onCsv, onPdf, exporting }) {
  const apply = onApply
  return (
    <div className="flex flex-wrap items-end gap-3">
      <Field label="From" htmlFor="rep-from" className="w-40">
        <Input id="rep-from" type="date" value={from} onChange={(e) => onChange('from', e.target.value)} />
      </Field>
      <Field label="To" htmlFor="rep-to" className="w-40">
        <Input id="rep-to" type="date" value={to} onChange={(e) => onChange('to', e.target.value)} />
      </Field>
      <Button size="sm" variant="outline" onClick={apply}>
        <Plus className="size-4" aria-hidden /> Apply
      </Button>
      <div className="flex gap-2">
        <Button size="sm" variant="outline" loading={exporting === 'csv'} onClick={onCsv}>
          <FileDown className="size-4" aria-hidden /> CSV
        </Button>
        <Button size="sm" variant="outline" loading={exporting === 'pdf'} onClick={onPdf}>
          <Download className="size-4" aria-hidden /> PDF
        </Button>
      </div>
    </div>
  )
}