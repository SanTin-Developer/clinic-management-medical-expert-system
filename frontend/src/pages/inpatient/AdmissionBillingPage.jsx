import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, FileText, Receipt, CreditCard, Plus, RefreshCw, AlertCircle, XCircle, Wallet } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Button from '../../components/ui/Button.jsx'
import Card from '../../components/ui/Card.jsx'
import Badge from '../../components/ui/Badge.jsx'
import { Table, TBody as TableBody, Td as TableCell, Th as TableHead, THead as TableHeader, Tr as TableRow } from '../../components/ui/Table.jsx'
import Input from '../../components/ui/Input.jsx'
import Select from '../../components/ui/Select.jsx'
import { Shimmer, LoadingBar, LoadingPill } from '../../components/ui/Skeleton.jsx'
import { cn } from '../../utils/cn.js'
import { formatCurrency, formatDate, toIsoDate } from '../../utils/format.js'
import { inpatientBillingApi } from '../../api/inpatientBilling.js'

const statusTone = (status) => {
  if (status === 'invoiced' || status === 'paid') return 'green'
  if (status === 'unbilled' || status === 'unpaid' || status === 'partially_paid') return 'amber'
  if (status === 'voided') return 'red'
  return 'gray'
}

export default function AdmissionBillingPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [showAddCharge, setShowAddCharge] = useState(false)
  const [showPayment, setShowPayment] = useState(false)
  const [chargeForm, setChargeForm] = useState({
    description: '',
    category: 'service',
    quantity: 1,
    unit_price: '',
    charge_date: toIsoDate(new Date()),
    discount_amount: 0,
  })
  const [paymentForm, setPaymentForm] = useState({
    amount: '',
    method: 'cash',
    invoice_id: '',
    reference: '',
    notes: '',
  })

  const { data, isLoading, error } = useQuery({
    queryKey: ['admission-billing', id],
    queryFn: async () => {
      const res = await inpatientBillingApi.ledger(id)
      return res ?? {}
    },
  })

  const generateRoomCharges = useMutation({
    mutationFn: () => inpatientBillingApi.generateRoomCharges(id),
    onSuccess: (res) => {
      toast.success(res?.message || 'Room charges generated')
      queryClient.invalidateQueries({ queryKey: ['admission-billing', id] })
    },
    onError: (err) => toast.error(err?.response?.data?.message || 'Failed to generate room charges'),
  })

  const generateDailyInvoice = useMutation({
    mutationFn: () => inpatientBillingApi.generateDailyInvoice(id),
    onSuccess: () => {
      toast.success('Daily invoice generated')
      queryClient.invalidateQueries({ queryKey: ['admission-billing', id] })
    },
    onError: (err) => toast.error(err?.response?.data?.message || err?.response?.data?.errors?.charges?.[0] || 'Failed to generate invoice'),
  })

  const addCharge = useMutation({
    mutationFn: (data) => inpatientBillingApi.addCharge(id, data),
    onSuccess: () => {
      toast.success('Charge added')
      setShowAddCharge(false)
      setChargeForm({ description: '', category: 'service', quantity: 1, unit_price: '', charge_date: toIsoDate(new Date()), discount_amount: 0 })
      queryClient.invalidateQueries({ queryKey: ['admission-billing', id] })
    },
    onError: (err) => toast.error(err?.response?.data?.message || Object.values(err?.response?.data?.errors || {})?.[0]?.[0] || 'Failed to add charge'),
  })

  const recordPayment = useMutation({
    mutationFn: (data) => inpatientBillingApi.recordPayment(id, data),
    onSuccess: () => {
      toast.success('Payment recorded')
      setShowPayment(false)
      setPaymentForm({ amount: '', method: 'cash', invoice_id: '', reference: '', notes: '' })
      queryClient.invalidateQueries({ queryKey: ['admission-billing', id] })
    },
    onError: (err) => toast.error(err?.response?.data?.message || Object.values(err?.response?.data?.errors || {})?.[0]?.[0] || 'Failed to record payment'),
  })

  const voidCharge = useMutation({
    mutationFn: ({ chargeId, reason }) => inpatientBillingApi.voidCharge(chargeId, reason),
    onSuccess: () => {
      toast.success('Charge voided')
      queryClient.invalidateQueries({ queryKey: ['admission-billing', id] })
    },
    onError: (err) => toast.error(err?.response?.data?.message || 'Failed to void charge'),
  })

  const dischargeReconciliation = useMutation({
    mutationFn: () => inpatientBillingApi.dischargeReconciliation(id),
    onSuccess: () => {
      toast.success('Discharge reconciliation completed')
      queryClient.invalidateQueries({ queryKey: ['admission-billing', id] })
    },
    onError: (err) => toast.error(err?.response?.data?.message || err?.response?.data?.errors?.charges?.[0] || 'Failed to reconcile'),
  })

  if (isLoading) return <AdmissionBillingLoading onBack={() => navigate('/admissions')} />
  if (error) {
    return (
      <div className="p-6">
        <Button variant="outline" size="sm" onClick={() => navigate('/admissions')}>
          <ArrowLeft className="h-4 w-4 mr-1" aria-hidden /> Back
        </Button>
        <p className="mt-4 text-red-600">Failed to load billing</p>
      </div>
    )
  }

  const admission = data?.admission
  const charges = data?.charges || []
  const invoices = data?.invoices || []
  const deposits = data?.deposits || []
  const summary = data?.summary || {}

  const handleAddCharge = (e) => {
    e.preventDefault()
    if (!chargeForm.description || !chargeForm.unit_price) {
      toast.error('Description and unit price are required')
      return
    }
    addCharge.mutate({
      ...chargeForm,
      quantity: Number(chargeForm.quantity) || 1,
      unit_price: Number(chargeForm.unit_price),
      discount_amount: Number(chargeForm.discount_amount) || 0,
    })
  }

  const handlePayment = (e) => {
    e.preventDefault()
    if (!paymentForm.amount || Number(paymentForm.amount) <= 0) {
      toast.error('Amount must be greater than zero')
      return
    }
    const payload = { ...paymentForm }
    if (!payload.invoice_id) delete payload.invoice_id
    payload.amount = Number(payload.amount)
    recordPayment.mutate(payload)
  }

  const handleVoidCharge = (charge) => {
    const reason = window.prompt('Reason for voiding this charge:')
    if (!reason) return
    voidCharge.mutate({ chargeId: charge.id, reason })
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Admission Billing"
        description={admission ? `${admission.patient?.first_name} ${admission.patient?.last_name} • ${admission.admission_number}` : ''}
        actions={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" onClick={() => navigate('/admissions')}>
              <ArrowLeft className="h-4 w-4 mr-1" aria-hidden /> Back
            </Button>
            <Button variant="outline" size="sm" onClick={() => setShowAddCharge(true)}>
              <Plus className="h-4 w-4 mr-1" /> Add Charge
            </Button>
            <Button variant="outline" size="sm" onClick={() => generateRoomCharges.mutate()} disabled={generateRoomCharges.isPending}>
              <RefreshCw className="h-4 w-4 mr-1" /> Generate Room Charges
            </Button>
            <Button variant="outline" size="sm" onClick={() => setShowPayment(true)}>
              <CreditCard className="h-4 w-4 mr-1" /> Record Payment/Deposit
            </Button>
            <Button size="sm" onClick={() => generateDailyInvoice.mutate()} disabled={generateDailyInvoice.isPending || summary.unbilled_total <= 0}>
              <Receipt className="h-4 w-4 mr-1" /> Generate Daily Invoice
            </Button>
            <Button variant="secondary" size="sm" onClick={() => dischargeReconciliation.mutate()} disabled={dischargeReconciliation.isPending}>
              <AlertCircle className="h-4 w-4 mr-1" /> Discharge Reconciliation
            </Button>
          </div>
        }
      />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
        <Card className="p-4"><div className="text-sm text-muted-foreground">Unbilled</div><div className="text-xl font-semibold">{formatCurrency(summary.unbilled_total)}</div></Card>
        <Card className="p-4"><div className="text-sm text-muted-foreground">Total Invoiced</div><div className="text-xl font-semibold">{formatCurrency(summary.total_invoiced)}</div></Card>
        <Card className="p-4"><div className="text-sm text-muted-foreground">Total Paid</div><div className="text-xl font-semibold text-green-600">{formatCurrency(summary.total_paid)}</div></Card>
        <Card className="p-4"><div className="text-sm text-muted-foreground">Deposits</div><div className="text-xl font-semibold">{formatCurrency(summary.available_deposits)}</div></Card>
        <Card className="p-4"><div className="text-sm text-muted-foreground">Outstanding</div><div className="text-xl font-semibold text-red-600">{formatCurrency(summary.outstanding_balance)}</div></Card>
      </div>

      {showAddCharge && (
        <Card className="p-4">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold">Add Charge</h3>
            <Button variant="ghost" size="sm" onClick={() => setShowAddCharge(false)}><XCircle className="h-4 w-4" /></Button>
          </div>
          <form onSubmit={handleAddCharge} className="grid gap-3 md:grid-cols-6">
            <div className="md:col-span-2 space-y-1">
              <label className="text-sm text-ink">Description</label>
              <Input value={chargeForm.description} onChange={(e) => setChargeForm(f => ({ ...f, description: e.target.value }))} required />
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Category</label>
              <Select value={chargeForm.category} onChange={(e) => setChargeForm(f => ({ ...f, category: e.target.value }))}>
                <option value="service">Service</option>
                <option value="medicine">Medicine</option>
                <option value="supply">Supply</option>
                <option value="nursing">Nursing</option>
                <option value="other">Other</option>
              </Select>
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Qty</label>
              <Input type="number" step="0.01" min="0.01" value={chargeForm.quantity} onChange={(e) => setChargeForm(f => ({ ...f, quantity: e.target.value }))} />
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Unit Price</label>
              <Input type="number" step="0.01" min="0" value={chargeForm.unit_price} onChange={(e) => setChargeForm(f => ({ ...f, unit_price: e.target.value }))} required />
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Charge Date</label>
              <Input type="date" value={chargeForm.charge_date} onChange={(e) => setChargeForm(f => ({ ...f, charge_date: e.target.value }))} />
            </div>
            <div className="md:col-span-6 flex justify-end">
              <Button type="submit" disabled={addCharge.isPending}>Add Charge</Button>
            </div>
          </form>
        </Card>
      )}

      {showPayment && (
        <Card className="p-4">
          <div className="flex items-center justify-between mb-4">
            <h3 className="font-semibold">Record Payment / Deposit</h3>
            <Button variant="ghost" size="sm" onClick={() => setShowPayment(false)}><XCircle className="h-4 w-4" /></Button>
          </div>
          <form onSubmit={handlePayment} className="grid gap-3 md:grid-cols-6">
            <div className="space-y-1">
              <label className="text-sm text-ink">Amount</label>
              <Input type="number" step="0.01" min="0.01" value={paymentForm.amount} onChange={(e) => setPaymentForm(f => ({ ...f, amount: e.target.value }))} required />
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Method</label>
              <Select value={paymentForm.method} onChange={(e) => setPaymentForm(f => ({ ...f, method: e.target.value }))}>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="insurance">Insurance</option>
                <option value="other">Other</option>
              </Select>
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Invoice (optional)</label>
              <Select value={paymentForm.invoice_id || 'none'} onChange={(e) => setPaymentForm(f => ({ ...f, invoice_id: e.target.value === 'none' ? '' : e.target.value }))}>
                <option value="none">Deposit to admission</option>
                {invoices.map((inv) => (
                  <option key={inv.id} value={String(inv.id)}>{inv.invoice_number} (Bal {formatCurrency(inv.balance)})</option>
                ))}
              </Select>
            </div>
            <div className="space-y-1">
              <label className="text-sm text-ink">Reference</label>
              <Input value={paymentForm.reference} onChange={(e) => setPaymentForm(f => ({ ...f, reference: e.target.value }))} />
            </div>
            <div className="md:col-span-2 space-y-1">
              <label className="text-sm text-ink">Notes</label>
              <Input value={paymentForm.notes} onChange={(e) => setPaymentForm(f => ({ ...f, notes: e.target.value }))} />
            </div>
            <div className="md:col-span-6 flex justify-end">
              <Button type="submit" disabled={recordPayment.isPending}>Record</Button>
            </div>
          </form>
        </Card>
      )}

      <Card>
        <div className="p-4 border-b flex items-center gap-2"><FileText className="h-4 w-4" /><h3 className="font-semibold">Charge Ledger</h3></div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Date</TableHead>
              <TableHead>Category</TableHead>
              <TableHead>Description</TableHead>
              <TableHead>Qty</TableHead>
              <TableHead>Unit Price</TableHead>
              <TableHead>Discount</TableHead>
              <TableHead>Total</TableHead>
              <TableHead>Status</TableHead>
              <TableHead>Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {charges.length === 0 && <TableRow><TableCell colSpan={9} className="text-center text-muted-foreground">No charges yet</TableCell></TableRow>}
            {charges.map((c) => (
              <TableRow key={c.id}>
                <TableCell leading>{formatDate(c.charge_date)}</TableCell>
                <TableCell className="capitalize">{c.category}</TableCell>
                <TableCell primary>{c.description}</TableCell>
                <TableCell>{c.quantity}</TableCell>
                <TableCell>{formatCurrency(c.unit_price)}</TableCell>
                <TableCell>{c.discount_amount > 0 ? formatCurrency(c.discount_amount) : '-'}</TableCell>
                <TableCell>{formatCurrency(c.total)}</TableCell>
                <TableCell trailing><Badge tone={statusTone(c.status)}>{c.status}</Badge></TableCell>
                <TableCell actions>
                  {c.status === 'unbilled' && (
                    <Button size="sm" variant="ghost" onClick={() => handleVoidCharge(c)}>
                      <XCircle className="h-4 w-4 mr-1" /> Void
                    </Button>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Card>

      <Card>
        <div className="p-4 border-b flex items-center gap-2"><Receipt className="h-4 w-4" /><h3 className="font-semibold">Invoices</h3></div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Invoice #</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Date</TableHead>
              <TableHead>Total</TableHead>
              <TableHead>Paid</TableHead>
              <TableHead>Balance</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {invoices.length === 0 && <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">No invoices yet</TableCell></TableRow>}
            {invoices.map((inv) => (
              <TableRow key={inv.id}>
                <TableCell leading>{inv.invoice_number}</TableCell>
                <TableCell primary className="capitalize">{inv.invoice_type?.replace('_', ' ')}</TableCell>
                <TableCell>{formatDate(inv.issued_at)}</TableCell>
                <TableCell>{formatCurrency(inv.total)}</TableCell>
                <TableCell>{formatCurrency(inv.amount_paid)}</TableCell>
                <TableCell>{formatCurrency(inv.balance)}</TableCell>
                <TableCell trailing><Badge tone={statusTone(inv.status)}>{inv.status}</Badge></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Card>

      <Card>
        <div className="p-4 border-b flex items-center gap-2"><CreditCard className="h-4 w-4" /><h3 className="font-semibold">Deposits & Payments</h3></div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Date</TableHead>
              <TableHead>Type</TableHead>
              <TableHead>Method</TableHead>
              <TableHead>Amount</TableHead>
              <TableHead>Balance</TableHead>
              <TableHead>Status</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {deposits.length === 0 && <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground">No deposits</TableCell></TableRow>}
            {deposits.map((d) => (
              <TableRow key={d.id}>
                <TableCell leading>{formatDate(d.received_at)}</TableCell>
                <TableCell className="capitalize">{d.type}</TableCell>
                <TableCell className="capitalize">{d.method}</TableCell>
                <TableCell primary>{formatCurrency(d.amount)}</TableCell>
                <TableCell>{formatCurrency(d.balance)}</TableCell>
                <TableCell trailing><Badge tone={statusTone(d.status)}>{d.status}</Badge></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </Card>
    </div>
  )
}

const LOADING_STATS = [
  { label: 'Unbilled', icon: FileText, tone: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' },
  { label: 'Total Invoiced', icon: Receipt, tone: 'bg-brand-soft text-brand-dark' },
  { label: 'Total Paid', icon: CreditCard, tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' },
  { label: 'Deposits', icon: Wallet, tone: 'bg-blue-900/10 text-ink dark:bg-blue-400/15' },
  { label: 'Outstanding', icon: AlertCircle, tone: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300' },
]

const LOADING_CELLS = ['w-14', 'w-20', 'flex-1', 'w-10', 'w-16', 'w-16', 'w-16', 'w-14', 'w-10']

function AdmissionBillingLoading({ onBack }) {
  return (
    <div className="space-y-6" role="status" aria-live="polite" aria-label="Loading admission billing">
      <PageHeader
        title="Admission Billing"
        description="Pulling together charges, invoices, deposits and the live balance…"
        actions={
          <div className="flex flex-wrap items-center gap-2">
            {onBack ? (
              <Button variant="outline" size="sm" onClick={onBack}>
                <ArrowLeft className="h-4 w-4 mr-1" aria-hidden /> Back
              </Button>
            ) : null}
            <LoadingPill label="Syncing ledger" />
          </div>
        }
      />

      <LoadingBar className="-mt-3" />

      <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
        {LOADING_STATS.map((stat, index) => {
          const Icon = stat.icon

          return (
            <div
              key={stat.label}
              className="animate-fade-in-up"
              style={{ animationDelay: `${index * 55}ms` }}
            >
              <Card className="h-full p-4">
                <div className="flex items-center gap-2 text-sm font-medium text-ink-muted">
                  <span className={cn('grid size-7 shrink-0 place-items-center rounded-lg', stat.tone)}>
                    <Icon className="size-3.5" aria-hidden />
                  </span>
                  {stat.label}
                </div>
                <Shimmer className="mt-3 h-7 w-24" />
                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-line/60">
                  <div className="h-full w-2/3 animate-pulse rounded-full bg-brand/30" />
                </div>
              </Card>
            </div>
          )
        })}
      </div>

      <LoadingTableCard title="Charge Ledger" icon={FileText} columns={9} rows={5} delay={300} />
      <LoadingTableCard title="Invoices" icon={Receipt} columns={7} rows={3} delay={360} />
      <LoadingTableCard title="Deposits & Payments" icon={CreditCard} columns={6} rows={3} delay={420} />
    </div>
  )
}

function LoadingTableCard({ title, icon: Icon, columns, rows, delay = 0 }) {
  return (
    <div className="animate-fade-in-up" style={{ animationDelay: `${delay}ms` }}>
      <Card className="overflow-hidden p-0" bodyClassName="p-0">
        <div className="flex items-center gap-3 border-b border-line px-5 py-4">
          <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand-dark">
            <Icon className="size-4" aria-hidden />
          </span>
          <div className="min-w-0 space-y-2">
            <h2 className="text-base font-semibold text-ink">{title}</h2>
            <Shimmer className="h-2.5 w-28" />
          </div>
        </div>
        <div className="divide-y divide-line/70">
          {Array.from({ length: rows }).map((_, row) => (
            <div key={row} className="flex items-center gap-4 px-5 py-4">
              {LOADING_CELLS.slice(0, columns).map((width, column) => (
                <Shimmer
                  key={column}
                  className={cn('h-4', width, column > 2 && 'hidden md:block')}
                />
              ))}
            </div>
          ))}
        </div>
      </Card>
    </div>
  )
}
