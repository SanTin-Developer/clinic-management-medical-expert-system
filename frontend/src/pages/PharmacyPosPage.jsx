import { useEffect, useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, CreditCard, Download, FileText, Minus, PackageOpen, Plus, Printer, Search, ShoppingCart, Trash2, UserRound, X } from 'lucide-react'
import { toast } from 'sonner'
import Card from '../components/ui/Card.jsx'
import Button from '../components/ui/Button.jsx'
import Input from '../components/ui/Input.jsx'
import Badge from '../components/ui/Badge.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Modal from '../components/ui/Modal.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { pharmacyPosApi } from '../api/pharmacy.js'
import { invoiceApi } from '../api/billing.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatCurrency, formatDateTime, formatNumber } from '../utils/format.js'

function medicineDetails(medicine) {
  return [medicine.strength, medicine.form].filter(Boolean).join(' · ') || 'No strength/form recorded'
}

export default function PharmacyPosPage() {
  const { user, can } = useAuth()
  const navigate = useNavigate()
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [page, setPage] = useState(1)
  const [cart, setCart] = useState([])
  const [amountReceived, setAmountReceived] = useState('')
  const [paying, setPaying] = useState(false)
  const [checkoutKey, setCheckoutKey] = useState(null)
  const [confirmingCheckout, setConfirmingCheckout] = useState(false)
  const [confirmingClear, setConfirmingClear] = useState(false)
  const [receipt, setReceipt] = useState(null)
  const [startedAt, setStartedAt] = useState(() => new Date())
  const canCheckout = can('inventory.manage')

  useEffect(() => {
    const id = window.setTimeout(() => {
      setSearch(searchDraft.trim())
      setPage(1)
    }, 300)
    return () => window.clearTimeout(id)
  }, [searchDraft])

  const products = useQuery({
    queryKey: ['pos-products', { search, page }],
    queryFn: () => pharmacyPosApi.products({ search: search || undefined, page }),
  })
  const rows = products.data?.data ?? []
  const categories = [...new Set(rows.map((medicine) => medicine.category).filter(Boolean))].sort()
  const filteredRows = category ? rows.filter((medicine) => medicine.category === category) : rows
  const itemCount = cart.reduce((total, row) => total + row.quantity, 0)
  const subtotal = useMemo(() => cart.reduce((sum, row) => sum + Number(row.price) * row.quantity, 0), [cart])
  const received = Number(amountReceived || 0)
  const hasEnteredPayment = amountReceived !== '' && Number.isFinite(received) && received > 0
  const paymentShortfall = hasEnteredPayment && received < subtotal
  const change = hasEnteredPayment && received >= subtotal ? received - subtotal : null

  const setQuantity = (medicine, quantity) => {
    const maximum = Number(medicine.usable_stock ?? 0)
    const parsed = Number(quantity)
    const next = Math.max(0, Math.min(Number.isInteger(parsed) ? parsed : 0, maximum))
    setCheckoutKey(null)
    setCart((current) => next === 0
      ? current.filter((row) => row.id !== medicine.id)
      : current.some((row) => row.id === medicine.id)
        ? current.map((row) => row.id === medicine.id ? { ...row, quantity: next } : row)
        : [...current, { ...medicine, quantity: next }])
  }

  const clearFilters = () => {
    setSearchDraft('')
    setSearch('')
    setCategory('')
    setPage(1)
  }

  const startNewSale = () => {
    setCart([])
    setAmountReceived('')
    setCheckoutKey(null)
    setReceipt(null)
    setStartedAt(new Date())
    products.refetch()
  }

  const checkout = async () => {
    if (!cart.length || paymentShortfall || !hasEnteredPayment || !canCheckout) return
    const idempotencyKey = checkoutKey ?? crypto.randomUUID()
    setCheckoutKey(idempotencyKey)
    setPaying(true)
    try {
      const result = await pharmacyPosApi.checkout({
        idempotency_key: idempotencyKey,
        items: cart.map((row) => ({ medicine_id: row.id, quantity: row.quantity })),
        payment: { amount: subtotal, amount_received: received, method: 'cash' },
      })
      setReceipt({ sale: result, change: Math.max(0, received - subtotal) })
      setCart([])
      setAmountReceived('')
      setCheckoutKey(null)
      products.refetch()
      toast.success(`Sale ${result.sale_number} completed.`)
    } catch (error) {
      toast.error(error.message)
    } finally {
      setPaying(false)
    }
  }

  return (
    <div className="space-y-5">
      <section className="rounded-2xl border border-line bg-surface px-5 py-4 shadow-[var(--shadow-card)] sm:px-6">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex min-w-0 items-center gap-3">
            <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand-dark"><ShoppingCart className="size-5" aria-hidden /></div>
            <div className="min-w-0"><h1 className="text-xl font-bold tracking-tight text-ink">Pharmacy POS</h1><p className="mt-0.5 truncate text-sm text-ink-muted">Walk-in cash sale · Cashier: {user?.name ?? 'Current user'}</p></div>
          </div>
          <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-ink-muted"><span className="inline-flex items-center gap-1.5"><span className="size-2 rounded-full bg-emerald-500" />New transaction</span><span>Started {formatDateTime(startedAt)}</span><Badge tone="brand">Cash</Badge></div>
        </div>
      </section>

      <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(340px,36%)]">
        <Card title="Medicines" subtitle="Search the active catalog. Only unexpired, usable stock can be sold." className="min-w-0" bodyClassName="p-4 sm:p-5" actions={<Badge tone="blue">{products.data?.total ?? 0} catalog items</Badge>}>
          <div className="flex flex-col gap-3 lg:flex-row">
            <div className="relative min-w-0 flex-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden />
              <Input value={searchDraft} onChange={(event) => setSearchDraft(event.target.value)} placeholder="Search name, code, category, strength or form…" aria-label="Search medicines" className="pl-9 pr-9" />
              {searchDraft ? <button type="button" onClick={() => setSearchDraft('')} aria-label="Clear medicine search" className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-ink-muted hover:bg-canvas hover:text-ink"><X className="size-4" /></button> : null}
            </div>
            {categories.length > 0 ? <select aria-label="Filter by category" value={category} onChange={(event) => { setCategory(event.target.value); setPage(1) }} className="h-10 rounded-lg border border-line bg-surface px-3 text-sm text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/20"><option value="">All categories</option>{categories.map((item) => <option key={item} value={item}>{item}</option>)}</select> : null}
            {(searchDraft || category) ? <Button variant="ghost" size="md" onClick={clearFilters}>Clear filters</Button> : null}
          </div>
          <div className="mt-5">
            {products.isLoading ? <RowSkeleton rows={6} cols={2} /> : null}
            {products.isError ? <ErrorState compact message={products.error.message} onRetry={products.refetch} /> : null}
            {!products.isLoading && !products.isError && filteredRows.length === 0 ? <EmptyState icon={PackageOpen} title={search || category ? 'No matching medicines' : 'No medicines available for this page'} message={search || category ? 'Try another search or clear the active filters.' : 'Add active medicines and receive unexpired stock before selling from POS.'} /> : null}
            <div className="grid gap-3 sm:grid-cols-2 2xl:grid-cols-3">
              {filteredRows.map((medicine) => {
                const available = Number(medicine.usable_stock ?? 0)
                const inCart = cart.find((row) => row.id === medicine.id)
                const canAdd = available > 0
                const atMaximum = inCart?.quantity >= available
                return <article key={medicine.id} className="flex min-w-0 flex-col rounded-xl border border-line bg-surface p-4 transition-colors hover:border-brand/40">
                  <div className="flex items-start justify-between gap-2"><div className="min-w-0"><p className="truncate font-semibold text-ink" title={medicine.name}>{medicine.name}</p><p className="mt-1 text-xs text-ink-muted">{medicine.code || 'No code'}</p></div><Badge tone={canAdd ? 'green' : 'gray'}>{canAdd ? 'In stock' : 'Unavailable'}</Badge></div>
                  <p className="mt-3 min-h-5 text-xs text-ink-muted">{medicineDetails(medicine)}</p>{medicine.category ? <p className="mt-1 truncate text-xs text-ink-muted">{medicine.category}</p> : null}
                  <div className="mt-4 flex items-end justify-between gap-3 border-t border-line pt-3"><div><p className="text-base font-bold text-ink">{formatCurrency(medicine.price)}</p><p className="mt-0.5 text-xs text-ink-muted">{formatNumber(available)} available</p></div><Button size="sm" disabled={!canAdd || atMaximum || !canCheckout} onClick={() => setQuantity(medicine, (inCart?.quantity ?? 0) + 1)}><Plus className="size-3.5" aria-hidden />Add</Button></div>
                  {!canAdd ? <p className="mt-3 text-xs font-medium text-amber-700">{medicine.unavailable_reason || 'This medicine cannot be sold.'}</p> : null}{canAdd && !canCheckout ? <p className="mt-3 text-xs font-medium text-amber-700">You do not have permission to create POS sales.</p> : null}
                </article>
              })}
            </div>
          </div>
          <Pagination page={products.data?.current_page ?? 1} lastPage={products.data?.last_page ?? 1} total={products.data?.total} onChange={setPage} />
        </Card>

        <aside className="xl:sticky xl:top-5">
          <Card title="Current cart" subtitle={itemCount ? `${itemCount} item${itemCount === 1 ? '' : 's'} selected` : 'No items selected'} bodyClassName="p-4 sm:p-5" actions={cart.length ? <Button variant="ghost" size="sm" onClick={() => setConfirmingClear(true)}><Trash2 className="size-3.5" />Clear</Button> : null}>
            {cart.length === 0 ? <div className="py-8"><EmptyState icon={ShoppingCart} title="Cart is empty" message="Choose an available medicine to begin a cash sale." /></div> : <div className="space-y-4">
              <div className="max-h-[36vh] space-y-3 overflow-y-auto pr-1">{cart.map((row) => <div key={row.id} className="border-b border-line pb-3 last:border-0 last:pb-0"><div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="truncate text-sm font-semibold text-ink" title={row.name}>{row.name}</p><p className="mt-0.5 text-xs text-ink-muted">{medicineDetails(row)}</p></div><button type="button" className="rounded p-1 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Remove ${row.name}`} onClick={() => setQuantity(row, 0)}><Trash2 className="size-4" /></button></div><div className="mt-3 flex items-center justify-between gap-3"><div className="flex items-center rounded-lg border border-line"><button type="button" className="rounded-l-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink" aria-label={`Decrease ${row.name}`} onClick={() => setQuantity(row, row.quantity - 1)}><Minus className="size-3.5" /></button><input type="number" min="1" max={Number(row.usable_stock)} step="1" inputMode="numeric" className="w-12 border-x border-line bg-transparent px-1 py-1 text-center text-sm font-semibold text-ink focus:outline-none focus:ring-0" value={row.quantity} onChange={(event) => {
                    const val = event.target.value
                    if (val === '') return
                    setQuantity(row, Number(val))
                  }} onBlur={(event) => {
                    const val = Number(event.target.value)
                    if (!Number.isInteger(val) || val < 1) {
                      setQuantity(row, 1)
                    } else if (val > Number(row.usable_stock)) {
                      setQuantity(row, Number(row.usable_stock))
                    }
                  }} /><button type="button" className="rounded-r-lg p-1.5 text-ink-muted hover:bg-canvas hover:text-ink disabled:cursor-not-allowed disabled:opacity-40" aria-label={`Increase ${row.name}`} disabled={row.quantity >= Number(row.usable_stock)} onClick={() => setQuantity(row, row.quantity + 1)}><Plus className="size-3.5" /></button></div><div className="text-right"><p className="text-xs text-ink-muted">{formatCurrency(row.price)} each</p><p className="text-sm font-bold text-ink">{formatCurrency(Number(row.price) * row.quantity)}</p></div></div></div>)}</div>
              <div className="rounded-xl bg-canvas p-3 text-sm"><div className="flex items-center justify-between text-ink-muted"><span>Customer</span><span className="inline-flex items-center gap-1"><UserRound className="size-3.5" />Walk-in</span></div><div className="mt-2 flex items-center justify-between text-ink-muted"><span>Payment method</span><span className="inline-flex items-center gap-1"><CreditCard className="size-3.5" />Cash</span></div></div>
              <div className="space-y-2 border-t border-line pt-4 text-sm"><div className="flex justify-between text-ink-muted"><span>Subtotal</span><span>{formatCurrency(subtotal)}</span></div><div className="flex items-center justify-between rounded-lg bg-brand-soft px-3 py-2.5 text-base font-bold text-brand-dark"><span>Total</span><span>{formatCurrency(subtotal)}</span></div></div>
              <div><label className="mb-1.5 block text-sm font-semibold text-ink" htmlFor="pos-received">Amount received</label><Input id="pos-received" type="number" min={subtotal} step="0.01" inputMode="decimal" value={amountReceived} onChange={(event) => setAmountReceived(event.target.value)} placeholder={subtotal.toFixed(2)} /><div className="mt-2 flex justify-between text-sm"><span className="text-ink-muted">Change due</span><span className="font-semibold text-ink">{change === null ? '—' : formatCurrency(change)}</span></div>{paymentShortfall ? <p className="mt-2 text-xs font-medium text-red-600">Amount received must cover the total.</p> : null}</div>
              {!canCheckout ? <p className="text-xs font-medium text-amber-700">Your account can view stock but cannot complete a POS sale.</p> : null}<Button className="w-full" size="lg" disabled={!hasEnteredPayment || paymentShortfall || !canCheckout} loading={paying} onClick={() => setConfirmingCheckout(true)}><CreditCard className="size-4" />Checkout cash sale</Button>
            </div>}
          </Card>
        </aside>
      </div>
      <ConfirmDialog open={confirmingClear} onClose={() => setConfirmingClear(false)} onConfirm={() => { setCart([]); setAmountReceived(''); setCheckoutKey(null); setConfirmingClear(false) }} title="Clear current cart?" message="All selected medicines and the entered cash amount will be removed." confirmLabel="Clear cart" />
      <ConfirmDialog open={confirmingCheckout} onClose={() => setConfirmingCheckout(false)} onConfirm={() => { setConfirmingCheckout(false); checkout() }} title="Complete cash sale?" message={`The server will confirm stock, prices, and payment for ${formatCurrency(subtotal)} before the sale is completed.`} confirmLabel="Complete sale" danger={false} loading={paying} />
      <ReceiptModal receipt={receipt} canViewInvoice={can('billing.view')} onClose={() => setReceipt(null)} onNewSale={startNewSale} onViewInvoice={() => navigate(`/billing/${receipt.sale.invoice_id}`)} onPrintInvoice={() => navigate(`/billing/${receipt.sale.invoice_id}?print=1`)} />
    </div>
  )
}

function ReceiptModal({ receipt, canViewInvoice, onClose, onNewSale, onViewInvoice, onPrintInvoice }) {
  const sale = receipt?.sale
  const invoice = sale?.invoice
  const total = Number(sale?.total ?? 0)
  const [downloading, setDownloading] = useState(false)
  const downloadPdf = async () => {
    if (!sale?.invoice_id) return
    setDownloading(true)
    try {
      await invoiceApi.downloadPdf(sale.invoice_id)
    } catch (error) {
      toast.error(error.message)
    } finally {
      setDownloading(false)
    }
  }
  return <Modal open={Boolean(receipt)} onClose={onClose} title="Sale completed" description="The cash sale was confirmed by the server." footer={<>{canViewInvoice ? <><Button variant="outline" onClick={onViewInvoice}><FileText className="size-4" />View invoice</Button><Button variant="outline" onClick={onPrintInvoice}><Printer className="size-4" />Print invoice</Button><Button variant="outline" loading={downloading} onClick={downloadPdf}><Download className="size-4" />Download PDF</Button></> : null}<Button onClick={onNewSale}>New sale</Button></>}>
    <div className="space-y-5"><div className="flex items-start gap-3 rounded-xl bg-emerald-50 p-4 text-emerald-800"><CheckCircle2 className="mt-0.5 size-5 shrink-0" /><div><p className="font-semibold">Payment received</p><p className="mt-0.5 text-sm">Receipt details are ready for this completed transaction.</p></div></div><dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm"><div><dt className="text-ink-muted">Sale reference</dt><dd className="mt-0.5 font-semibold text-ink">{sale?.sale_number ?? '—'}</dd></div><div><dt className="text-ink-muted">Invoice reference</dt><dd className="mt-0.5 font-semibold text-ink">{invoice?.invoice_number ?? '—'}</dd></div><div><dt className="text-ink-muted">Payment</dt><dd className="mt-0.5 font-semibold text-ink">Cash</dd></div><div><dt className="text-ink-muted">Change due</dt><dd className="mt-0.5 font-semibold text-ink">{formatCurrency(receipt?.change)}</dd></div></dl><div className="border-y border-line py-3"><div className="flex justify-between text-sm"><span className="text-ink-muted">Final total</span><span className="text-lg font-bold text-ink">{formatCurrency(total)}</span></div></div><div className="space-y-2 text-sm">{sale?.items?.map((item) => <div key={item.id} className="flex justify-between gap-4"><span className="min-w-0 truncate text-ink-muted">{item.medicine?.name ?? 'Medicine'} × {item.quantity}</span><span className="font-medium text-ink">{formatCurrency(item.total)}</span></div>)}</div></div>
  </Modal>
}
