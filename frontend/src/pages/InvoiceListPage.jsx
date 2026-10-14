import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Plus, Trash2, Receipt, Clock, TriangleAlert, CircleDollarSign, CheckCircle2 } from 'lucide-react'
import { invoiceApi } from '../api/billing.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import InvoiceFormModal from '../components/billing/InvoiceFormModal.jsx'
import { formatCurrency, formatDate, patientFullName } from '../utils/format.js'
import { statusBadgeFor, INVOICE_STATUS } from '../utils/status.js'

export default function InvoiceListPage() {
  const { can } = useAuth()
  const navigate = useNavigate()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState('unpaid')
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(null)

  const query = usePaginated(['invoices'], (params) => invoiceApi.list(params), { page })

  const all = query.data?.data ?? []
  const stats = query.data?.stats ?? {}
  const items = status === 'all' ? all : all.filter((i) => i.status === status)

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      await invoiceApi.remove(deleting.id)
      toast.success('Invoice deleted.')
      setDeleting(null)
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Billing & Payments"
        description="Invoices, payments and outstanding balances."
        breadcrumb={[{ label: 'Billing' }]}
        actions={
          can('billing.create') ? (
            <Button onClick={() => setFormOpen(true)}>
              <Plus className="size-4" aria-hidden /> New invoice
            </Button>
          ) : null
        }
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <StatCard
          label="Total Invoices"
          value={stats.total ?? query.data?.total ?? 0}
          icon={Receipt}
          tone="brand"
          loading={query.isLoading}
        />
        <StatCard
          label="Unpaid"
          value={stats.unpaid ?? 0}
          icon={Clock}
          tone="amber"
          loading={query.isLoading}
        />
        <StatCard
          label="Outstanding"
          value={formatCurrency(stats.outstanding ?? 0)}
          icon={TriangleAlert}
          tone="red"
          loading={query.isLoading}
        />
        <StatCard
          label="Revenue (all time)"
          value={formatCurrency(stats.total_revenue ?? 0)}
          icon={CircleDollarSign}
          tone="green"
          loading={query.isLoading}
        />
        <StatCard
          label="Paid"
          value={stats.paid ?? 0}
          icon={CheckCircle2}
          tone="blue"
          loading={query.isLoading}
        />
      </div>

      <Card className="px-0 pb-0">
        <div className="px-5 pt-4">
          <Tabs
            value={status}
            onChange={(v) => {
              setStatus(v)
              setPage(1)
            }}
            className="border-b-0"
            tabs={[
              { value: 'unpaid', label: 'Unpaid' },
              { value: 'partially_paid', label: 'Partially paid' },
              { value: 'paid', label: 'Paid' },
              { value: 'cancelled', label: 'Cancelled' },
              { value: 'all', label: 'All' },
            ]}
          />
        </div>

        {query.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : all.length === 0 ? (
          <EmptyState
            title="No invoices yet"
            message="Create an invoice to start billing."
            action={
              can('billing.create') ? (
                <Button size="sm" onClick={() => setFormOpen(true)}>
                  New invoice
                </Button>
              ) : null
            }
          />
        ) : items.length === 0 ? (
          <EmptyState title="Nothing here" message="No invoices match the selected status." />
        ) : (
          <Table minWidth="min-w-[860px]">
            <THead>
              <Tr>
                <Th>Invoice</Th>
                <Th>Patient</Th>
                <Th>Issued</Th>
                <Th>Total</Th>
                <Th>Balance</Th>
                <Th>Status</Th>
                <Th><span className="sr-only">Actions</span></Th>
              </Tr>
            </THead>
            <TBody>
              {items.map((inv) => {
                const meta = statusBadgeFor(INVOICE_STATUS, inv.status)
                return (
                  <Tr key={inv.id} onClick={() => navigate(`/billing/${inv.id}`)}>
                    <Td className="font-mono text-xs text-ink-muted">{inv.invoice_number}</Td>
                    <Td className="font-medium text-ink">{patientFullName(inv.patient)}</Td>
                    <Td className="text-ink-muted">{formatDate(inv.issued_at)}</Td>
                    <Td className="font-medium text-ink">{formatCurrency(inv.total)}</Td>
                    <Td className={Number(inv.balance) > 0 ? 'font-medium text-red-600' : 'text-ink-muted'}>
                      {formatCurrency(inv.balance)}
                    </Td>
                    <Td><Badge tone={meta.tone}>{meta.label}</Badge></Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end">
                        {can('billing.delete') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${inv.invoice_number}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeleting(inv)}
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

        <Pagination page={query.data?.current_page ?? 1} lastPage={query.data?.last_page ?? 1} total={query.data?.total} onChange={setPage} />
      </Card>

      <InvoiceFormModal open={formOpen} onClose={() => setFormOpen(false)} onSaved={() => query.refetch()} />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete invoice?"
        message="This permanently deletes the invoice and its line items."
        confirmLabel="Delete"
      />
    </div>
  )
}