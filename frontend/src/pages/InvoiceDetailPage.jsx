import { useEffect, useRef, useState } from 'react'
import { useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, Banknote, Download, Pencil, Printer, Trash2, XCircle } from 'lucide-react'
import { invoiceApi } from '../api/billing.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import InvoiceFormModal from '../components/billing/InvoiceFormModal.jsx'
import PaymentFormModal from '../components/billing/PaymentFormModal.jsx'
import { CardSkeleton } from '../components/ui/Skeleton.jsx'
import { ErrorState } from '../components/ui/State.jsx'
import { formatCurrency, formatDate, patientFullName } from '../utils/format.js'
import { PAYMENT_METHODS, statusBadgeFor, INVOICE_STATUS } from '../utils/status.js'

export default function InvoiceDetailPage() {
  const { id } = useParams()
  const [searchParams] = useSearchParams()
  const { can } = useAuth()
  const navigate = useNavigate()

  const query = useQuery({
    queryKey: ['invoice', id],
    queryFn: () => invoiceApi.get(id),
  })

  const [editOpen, setEditOpen] = useState(false)
  const [paymentOpen, setPaymentOpen] = useState(false)
  const [confirmState, setConfirmState] = useState(null)
  const [busy, setBusy] = useState(false)
  const [downloading, setDownloading] = useState(false)
  const printStarted = useRef(false)

  const inv = query.data
  const meta = inv ? statusBadgeFor(INVOICE_STATUS, inv.status) : null
  const open = inv && ['unpaid', 'partially_paid'].includes(inv.status)

  useEffect(() => {
    if (!inv || searchParams.get('print') !== '1' || printStarted.current) return
    printStarted.current = true
    const id = window.setTimeout(() => window.print(), 150)
    return () => window.clearTimeout(id)
  }, [inv, searchParams])

  const runConfirm = async () => {
    if (!confirmState) return
    setBusy(true)
    try {
      if (confirmState === 'cancel') await invoiceApi.cancel(id)
      else await invoiceApi.remove(id)
      toast.success(confirmState === 'cancel' ? 'Invoice cancelled.' : 'Invoice deleted.')
      setConfirmState(null)
      if (confirmState === 'delete') navigate('/billing')
      else query.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  const downloadPdf = async () => {
    setDownloading(true)
    try {
      await invoiceApi.downloadPdf(id)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDownloading(false)
    }
  }

  if (query.isError) {
    return (
      <ErrorState title="Could not load invoice" message={query.error.message} onRetry={() => query.refetch()} />
    )
  }

  return (
    <div>
      <PageHeader
        title={inv ? inv.invoice_number : 'Invoice'}
        description={inv && inv.patient ? `For ${patientFullName(inv.patient)}` : undefined}
        breadcrumb={[{ label: 'Billing', to: '/billing' }, { label: 'Invoice' }]}
        actions={
          <>
            <Button variant="outline" className="no-print" onClick={() => navigate('/billing')}>
              <ArrowLeft className="size-4" aria-hidden /> Back
            </Button>
            <Button variant="outline" className="no-print" onClick={() => window.print()}>
              <Printer className="size-4" aria-hidden /> Print
            </Button>
            <Button variant="outline" className="no-print" loading={downloading} onClick={downloadPdf}>
              <Download className="size-4" aria-hidden /> Download PDF
            </Button>
            {can('billing.update') && open ? (
              <>
                <Button onClick={() => setEditOpen(true)}>
                  <Pencil className="size-4" aria-hidden /> Edit
                </Button>
                <Button variant="success" onClick={() => setPaymentOpen(true)}>
                  <Banknote className="size-4" aria-hidden /> Record payment
                </Button>
              </>
            ) : null}
            {can('billing.update') && open && Number(inv?.amount_paid) === 0 ? (
              <Button variant="danger" className="no-print" onClick={() => setConfirmState('cancel')}>
                <XCircle className="size-4" aria-hidden /> Cancel invoice
              </Button>
            ) : null}
            {can('billing.delete') ? (
              <Button variant="outline" className="no-print" onClick={() => setConfirmState('delete')}>
                <Trash2 className="size-4" aria-hidden /> Delete
              </Button>
            ) : null}
          </>
        }
      />

      {query.isLoading ? (
        <CardSkeleton rows={5} />
      ) : (
        <div className="space-y-6 print-area">
          <div className="grid grid-cols-1 gap-6 print-break lg:grid-cols-3">
            <Card title="Invoice summary" className="print-break lg:col-span-2 print:border-0">
              <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Patient</p>
                  <p className="mt-1 text-sm font-semibold text-ink">{patientFullName(inv.patient)}</p>
                  <p className="text-xs text-ink-muted">{inv.patient?.patient_number}</p>
                </div>
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Status</p>
                  <p className="mt-1">{meta ? <Badge tone={meta.tone}>{meta.label}</Badge> : '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Issued</p>
                  <p className="mt-1 text-sm text-ink">{formatDate(inv.issued_at)}</p>
                </div>
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Due</p>
                  <p className="mt-1 text-sm text-ink">{inv.due_date ? formatDate(inv.due_date) : '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Created by</p>
                  <p className="mt-1 text-sm text-ink">{inv.cashier?.name ?? '—'}</p>
                </div>
                <div>
                  <p className="text-xs font-medium uppercase tracking-wide text-ink-muted">Consultation</p>
                  <p className="mt-1 text-sm text-ink">{inv.consultation ? `#${inv.consultation.id}` : '—'}</p>
                </div>
              </div>
            </Card>
            <Card title="Totals" className="print-break print:border-0">
              <dl className="space-y-3 text-sm">
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Subtotal</dt>
                  <dd className="font-medium text-ink">{formatCurrency(inv.subtotal)}</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Discount</dt>
                  <dd className="font-medium text-ink">−{formatCurrency(inv.discount)}</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Tax</dt>
                  <dd className="font-medium text-ink">{formatCurrency(inv.tax)}</dd>
                </div>
                <div className="flex items-center justify-between border-t border-line pt-3">
                  <dt className="font-semibold text-ink">Total</dt>
                  <dd className="font-bold text-ink">{formatCurrency(inv.total)}</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Paid</dt>
                  <dd className="font-medium text-emerald-600">{formatCurrency(inv.amount_paid)}</dd>
                </div>
                <div className="flex items-center justify-between">
                  <dt className="text-ink-muted">Balance</dt>
                  <dd className="font-medium text-red-600">{formatCurrency(inv.balance)}</dd>
                </div>
              </dl>
            </Card>
          </div>

          <Card title="Line items" className="print-break print:border-0">
            <div className="overflow-x-auto rounded-xl border border-line">
              <table className="w-full min-w-[560px] text-left text-sm">
                <thead className="bg-canvas text-xs uppercase tracking-wide text-ink-muted">
                  <tr>
                    <th className="px-4 py-3 font-semibold">Description</th>
                    <th className="px-4 py-3 font-semibold text-right">Qty</th>
                    <th className="px-4 py-3 font-semibold text-right">Unit price</th>
                    <th className="px-4 py-3 font-semibold text-right">Amount</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line">
                  {(inv.items ?? []).map((item) => (
                    <tr key={item.id}>
                      <td className="px-4 py-3 font-medium text-ink">{item.description}</td>
                      <td className="px-4 py-3 text-right text-ink-muted">{item.quantity}</td>
                      <td className="px-4 py-3 text-right text-ink-muted">{formatCurrency(item.unit_price)}</td>
                      <td className="px-4 py-3 text-right font-medium text-ink">{formatCurrency(item.amount)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>

          <Card title="Payments" className="print-break print:border-0">
            {(inv.payments ?? []).length === 0 ? (
              <p className="py-4 text-center text-sm text-ink-muted">No payments recorded yet.</p>
            ) : (
              <ul className="divide-y divide-line">
                {(inv.payments ?? []).map((payment) => {
                  const method = PAYMENT_METHODS[payment.method]
                  return (
                    <li key={payment.id} className="flex flex-wrap items-center gap-3 py-3">
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-semibold text-ink">{formatCurrency(payment.amount)}</p>
                        <p className="text-xs text-ink-muted">
                          {method?.label ?? payment.method} · {formatDate(payment.paid_at)} · by {payment.receiver?.name ?? '—'}
                        </p>
                      </div>
                      {payment.reference ? <span className="font-mono text-xs text-ink-muted">{payment.reference}</span> : null}
                    </li>
                  )
                })}
              </ul>
            )}
          </Card>
        </div>
      )}

      <InvoiceFormModal
        key={`${inv?.id ?? 'loading'}-${inv?.updated_at ?? ''}`}
        open={editOpen}
        onClose={() => setEditOpen(false)}
        initial={inv}
        onSaved={() => query.refetch()}
      />
      <PaymentFormModal open={paymentOpen} onClose={() => setPaymentOpen(false)} invoice={inv} onSaved={() => query.refetch()} />

      <ConfirmDialog
        open={Boolean(confirmState)}
        onClose={() => setConfirmState(null)}
        onConfirm={runConfirm}
        title={confirmState === 'cancel' ? 'Cancel invoice?' : 'Delete invoice?'}
        message={
          confirmState === 'cancel'
            ? 'The invoice will be cancelled. Only invoices with no payments can be cancelled.'
            : 'This permanently deletes the invoice and its line items.'
        }
        confirmLabel={confirmState === 'cancel' ? 'Cancel invoice' : 'Delete'}
        loading={busy}
      />
    </div>
  )
}
