import { useQuery } from '@tanstack/react-query'
import {
  AlertTriangle,
  CalendarClock,
  CircleDollarSign,
  PackageX,
  Pill,
  TrendingDown,
  TrendingUp,
} from 'lucide-react'
import StatCard from '../components/ui/StatCard.jsx'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import { pharmacyDashboardApi } from '../api/pharmacy.js'
import { formatCurrency, formatDate, formatDateTime, patientFullName } from '../utils/format.js'
import { statusBadgeFor, STOCK_MOVEMENT_TYPE } from '../utils/status.js'

export default function PharmacyDashboardPage() {
  const query = useQuery({
    queryKey: ['pharmacy', 'dashboard'],
    queryFn: () => pharmacyDashboardApi.get(),
  })

  const data = query.data?.data ?? {}
  const inventory = data.inventory ?? {}
  const prescriptions = data.prescriptions ?? {}
  const lowStock = data.low_stock ?? []
  const expiringSoon = data.expiring_soon ?? []
  const recentMovements = data.recent_movements ?? []
  const recentDispensed = data.recent_dispensed ?? []

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pharmacy Dashboard"
        description="Stock health, expiry risk, and dispensing activity at a glance."
      />

      {query.isError ? (
        <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {query.error.message}
        </p>
      ) : null}

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
          label="Pending Prescriptions"
          value={prescriptions.pending ?? 0}
          icon={Pill}
          tone="amber"
          sub={`${prescriptions.dispensed_today ?? 0} dispensed today`}
          loading={query.isLoading}
        />
        <StatCard
          label="Low Stock"
          value={inventory.low_stock_count ?? 0}
          icon={TrendingDown}
          tone="red"
          sub={`${inventory.out_of_stock_count ?? 0} out of stock`}
          loading={query.isLoading}
        />
        <StatCard
          label="Expiring in 30 days"
          value={inventory.expiring_soon_count ?? 0}
          icon={CalendarClock}
          tone="navy"
          sub={`${inventory.expired_count ?? 0} already expired`}
          loading={query.isLoading}
        />
        <StatCard
          label="Stock Value"
          value={formatCurrency(inventory.stock_value ?? 0)}
          icon={CircleDollarSign}
          tone="green"
          sub={`${inventory.total_on_hand ?? 0} units on hand`}
          loading={query.isLoading}
        />
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <Card title="Low stock medicines" subtitle="At or below reorder level">
          {query.isLoading ? (
            <RowSkeleton rows={4} cols={3} />
          ) : lowStock.length === 0 ? (
            <EmptyState title="All medicines stocked" message="No medicine is at its reorder level." icon={TrendingUp} />
          ) : (
            <Table minWidth="min-w-[420px]">
              <THead>
                <Tr>
                  <Th>Medicine</Th>
                  <Th>Stock</Th>
                  <Th>Reorder level</Th>
                </Tr>
              </THead>
              <TBody>
                {lowStock.map((row) => (
                  <Tr key={row.id}>
                    <Td>
                      <div className="font-medium text-ink">{row.name}</div>
                      <div className="text-xs text-ink-muted">{row.code}</div>
                    </Td>
                    <Td>
                      <Badge tone={row.stock <= 0 ? 'red' : 'amber'}>{row.stock}</Badge>
                    </Td>
                    <Td className="text-ink-muted">{row.reorder_level}</Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          )}
        </Card>

        <Card title="Expiring soon" subtitle="Usable batches within 30 days">
          {query.isLoading ? (
            <RowSkeleton rows={4} cols={3} />
          ) : expiringSoon.length === 0 ? (
            <EmptyState title="Nothing expiring soon" message="No batch expires within the next 30 days." icon={CalendarClock} />
          ) : (
            <Table minWidth="min-w-[420px]">
              <THead>
                <Tr>
                  <Th>Medicine</Th>
                  <Th>Batch</Th>
                  <Th>Qty</Th>
                  <Th>Expires</Th>
                </Tr>
              </THead>
              <TBody>
                {expiringSoon.map((row) => (
                  <Tr key={row.id}>
                    <Td className="font-medium text-ink">{row.medicine ?? '—'}</Td>
                    <Td className="text-ink-muted">{row.batch_number ?? '—'}</Td>
                    <Td>{row.quantity}</Td>
                    <Td className="text-ink-muted">{formatDate(row.expiration_date)}</Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          )}
        </Card>
      </div>

      <Card title="Recent stock movements">
        {query.isLoading ? (
          <RowSkeleton rows={5} cols={4} />
        ) : recentMovements.length === 0 ? (
          <EmptyState title="No stock movements" message="Record a stock movement to see it here." icon={AlertTriangle} />
        ) : (
          <Table minWidth="min-w-[640px]">
            <THead>
              <Tr>
                <Th>Medicine</Th>
                <Th>Type</Th>
                <Th>Qty</Th>
                <Th>Reason</Th>
                <Th>By</Th>
                <Th>When</Th>
              </Tr>
            </THead>
            <TBody>
              {recentMovements.map((row) => {
                const meta = statusBadgeFor(STOCK_MOVEMENT_TYPE, row.type)
                return (
                  <Tr key={row.id}>
                    <Td className="font-medium text-ink">{row.medicine?.name ?? '—'}</Td>
                    <Td><Badge tone={meta.tone}>{meta.label}</Badge></Td>
                    <Td>{row.quantity}</Td>
                    <Td className="text-ink-muted">{row.reason ?? '—'}</Td>
                    <Td className="text-ink-muted">{row.user?.name ?? '—'}</Td>
                    <Td className="text-ink-muted">{formatDateTime(row.created_at)}</Td>
                  </Tr>
                )
              })}
            </TBody>
          </Table>
        )}
      </Card>

      <Card title="Recently dispensed prescriptions">
        {query.isLoading ? (
          <RowSkeleton rows={5} cols={3} />
        ) : recentDispensed.length === 0 ? (
          <EmptyState title="No dispensed prescriptions" message="Dispensed prescriptions will appear here." icon={PackageX} />
        ) : (
          <Table minWidth="min-w-[560px]">
            <THead>
              <Tr>
                <Th>Prescription</Th>
                <Th>Patient</Th>
                <Th>Dispensed by</Th>
                <Th>Dispensed at</Th>
              </Tr>
            </THead>
            <TBody>
              {recentDispensed.map((row) => (
                <Tr key={row.id}>
                  <Td className="font-medium text-ink">{row.prescription_number}</Td>
                  <Td>{patientFullName(row.patient)}</Td>
                  <Td className="text-ink-muted">{row.dispensed_by?.name ?? '—'}</Td>
                  <Td className="text-ink-muted">{formatDateTime(row.dispensed_at)}</Td>
                </Tr>
              ))}
            </TBody>
          </Table>
        )}
      </Card>
    </div>
  )
}
