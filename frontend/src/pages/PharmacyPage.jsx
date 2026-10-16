import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import {
  PackagePlus,
  Pencil,
  Plus,
  Trash2,
  ArrowLeftRight,
  Pill,
  CheckCircle2,
  XCircle,
  Boxes,
  TriangleAlert,
  CalendarClock,
  Ban,
  ArrowDownToLine,
  ArrowUpFromLine,
  RefreshCw,
} from 'lucide-react'
import { medicineApi, inventoryApi, stockMovementApi } from '../api/pharmacy.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import StatCard from '../components/ui/StatCard.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../components/ui/RecordDetailModal.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import Table, { THead, TBody, Tr, Th, Td } from '../components/ui/Table.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import Select from '../components/ui/Select.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import MedicineFormModal from '../components/pharmacy/MedicineFormModal.jsx'
import InventoryFormModal from '../components/pharmacy/InventoryFormModal.jsx'
import StockMovementFormModal from '../components/pharmacy/StockMovementFormModal.jsx'
import { formatCurrency, formatDate } from '../utils/format.js'
import { statusBadgeFor, STOCK_MOVEMENT_TYPE } from '../utils/status.js'

export default function PharmacyPage({ initialTab = 'medicines', inventoryOnly = false }) {
  const { can } = useAuth()
  const [tab, setTab] = useState(initialTab)
  const [page, setPage] = useState(1)
  const [medicineSearch, setMedicineSearch] = useState('')
  const [inventoryPage, setInventoryPage] = useState(1)
  const [inventorySearch, setInventorySearch] = useState('')
  const [inventoryStatus, setInventoryStatus] = useState('all')
  const [movementPage, setMovementPage] = useState(1)
  const [movementSearch, setMovementSearch] = useState('')
  const [movementType, setMovementType] = useState('')

  useEffect(() => {
    setTab(initialTab)
  }, [initialTab])

  const medicines = usePaginated(['medicines'], (params) => medicineApi.list(params), {
    page,
    params: { search: medicineSearch || undefined },
    enabled: can('medicines.view'),
  })
  const inventory = usePaginated(['inventory'], (params) => inventoryApi.list(params), {
    page: inventoryPage,
    params: {
      search: inventorySearch || undefined,
      low_stock: inventoryStatus === 'low' ? 1 : undefined,
      expiring_soon: inventoryStatus === 'expiring' ? 1 : undefined,
      expired: inventoryStatus === 'expired' ? 1 : undefined,
    },
    enabled: can('inventory.view'),
  })
  const movements = usePaginated(['stock-movements'], (params) => stockMovementApi.list(params), {
    page: movementPage,
    params: { search: movementSearch || undefined, type: movementType || undefined },
    enabled: can('inventory.view'),
  })

  const [form, setForm] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [viewing, setViewing] = useState(null)

  const medicineRows = medicines.data?.data ?? []
  const inventoryRows = inventory.data?.data ?? []
  const movementRows = movements.data?.data ?? []

  const canManageMedicines = can('medicines.create') || can('medicines.update')
  const canManageInventory = can('inventory.manage')

  const refresh = () => {
    medicines.refetch()
    inventory.refetch()
    movements.refetch()
  }

  const confirmDelete = async () => {
    if (!deleting) return
    try {
      if (deleting.kind === 'medicine') await medicineApi.remove(deleting.id)
      if (deleting.kind === 'inventory') await inventoryApi.remove(deleting.id)
      toast.success('Deleted.')
      setDeleting(null)
      refresh()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const activeQuery = { medicines, inventory, movements }[tab]

  const medicineStats = medicines.data?.stats ?? {}
  const inventoryStats = inventory.data?.stats ?? {}
  const movementStats = movements.data?.stats ?? {}

  return (
    <div className="space-y-6">
      <PageHeader
        title={inventoryOnly ? 'Inventory & Supplies' : 'Pharmacy'}
        description={inventoryOnly ? 'Current stock, batch expiry, receiving and auditable stock movements.' : 'Medicine catalog and prescription dispensing workspace.'}
        actions={!inventoryOnly && can('medicines.create') ? (
          <Button onClick={() => setForm({ kind: 'medicine' })}>
            <PackagePlus className="size-4" aria-hidden /> New medicine
          </Button>
        ) : null}
      />

      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        {tab === 'medicines' ? (
          <>
            <StatCard
              label="Total Medicines"
              value={medicineStats.total ?? medicines.data?.total ?? 0}
              icon={Pill}
              tone="brand"
              loading={medicines.isLoading}
            />
            <StatCard
              label="Active"
              value={medicineStats.active ?? 0}
              icon={CheckCircle2}
              tone="green"
              loading={medicines.isLoading}
            />
            <StatCard
              label="Inactive"
              value={medicineStats.inactive ?? 0}
              icon={XCircle}
              tone="amber"
              loading={medicines.isLoading}
            />
          </>
        ) : tab === 'inventory' ? (
          <>
            <StatCard
              label="Total Batches"
              value={inventoryStats.total}
              icon={Boxes}
              tone="brand"
              loading={inventory.isLoading}
            />
            <StatCard
              label="Low Stock"
              value={inventoryStats.low_stock}
              icon={TriangleAlert}
              tone="amber"
              loading={inventory.isLoading}
            />
            <StatCard
              label="Expiring Soon"
              value={inventoryStats.expiring_soon}
              icon={CalendarClock}
              tone="blue"
              loading={inventory.isLoading}
            />
            <StatCard
              label="Expired"
              value={inventoryStats.expired}
              icon={Ban}
              tone="red"
              loading={inventory.isLoading}
            />
            <StatCard
              label="Out of Stock"
              value={inventoryStats.out_of_stock}
              icon={XCircle}
              tone="red"
              loading={inventory.isLoading}
            />
          </>
        ) : (
          <>
            <StatCard
              label="Total Movements"
              value={movementStats.total}
              icon={ArrowLeftRight}
              tone="brand"
              loading={movements.isLoading}
            />
            <StatCard
              label="Stock In"
              value={movementStats.in}
              icon={ArrowDownToLine}
              tone="green"
              loading={movements.isLoading}
            />
            <StatCard
              label="Stock Out"
              value={movementStats.out}
              icon={ArrowUpFromLine}
              tone="red"
              loading={movements.isLoading}
            />
            <StatCard
              label="Adjustments"
              value={movementStats.adjustment}
              icon={RefreshCw}
              tone="amber"
              loading={movements.isLoading}
            />
          </>
        )}
      </div>

      <Card className="overflow-hidden px-0 pb-0" bodyClassName="p-0">
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 pt-4">
          {inventoryOnly ? (
            <Tabs
              value={tab}
              onChange={(v) => setTab(v)}
              className="border-b-0"
              tabs={[
                { value: 'inventory', label: 'Stock & batches' },
                { value: 'movements', label: 'Stock movements' },
              ]}
            />
          ) : (
            <div className="flex items-center gap-3 pb-2">
              <span className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand-dark">
                <Pill className="size-5" aria-hidden />
              </span>
              <div>
                <h2 className="font-semibold text-ink">Medicine catalog</h2>
                <p className="text-xs text-ink-muted">Formulary details, selling price and availability status.</p>
              </div>
            </div>
          )}
          <div className="flex gap-2">
            {tab === 'inventory' && can('inventory.manage') ? (
              <Button size="sm" onClick={() => setForm({ kind: 'inventory' })}>
                <Plus className="size-4" aria-hidden /> Add stock
              </Button>
            ) : null}
            {tab === 'movements' && can('inventory.manage') ? (
              <Button size="sm" onClick={() => setForm({ kind: 'movement' })}>
                <ArrowLeftRight className="size-4" aria-hidden /> Record movement
              </Button>
            ) : null}
          </div>
        </div>

        {tab === 'medicines' ? (
          <div className="border-t border-line bg-canvas/35 px-5 py-4">
            <SearchInput
              placeholder="Search medicines by name or code…"
              className="w-full sm:w-80"
              onDebounced={(value) => { setMedicineSearch(value); setPage(1) }}
            />
          </div>
        ) : tab === 'inventory' ? (
          <div className="flex flex-wrap gap-3 border-t border-line bg-canvas/35 px-5 py-4">
            <SearchInput
              placeholder="Search medicine, code, or batch…"
              className="w-full sm:w-80"
              onDebounced={(value) => { setInventorySearch(value); setInventoryPage(1) }}
            />
            <Select value={inventoryStatus} onChange={(event) => { setInventoryStatus(event.target.value); setInventoryPage(1) }} className="sm:w-52">
              <option value="all">All batch statuses</option>
              <option value="low">Low stock</option>
              <option value="expiring">Expiring within 30 days</option>
              <option value="expired">Expired stock</option>
            </Select>
          </div>
        ) : (
          <div className="flex flex-wrap gap-3 border-t border-line bg-canvas/35 px-5 py-4">
            <SearchInput
              placeholder="Search medicine, batch, reference…"
              className="w-full sm:w-80"
              onDebounced={(value) => { setMovementSearch(value); setMovementPage(1) }}
            />
            <Select value={movementType} onChange={(event) => { setMovementType(event.target.value); setMovementPage(1) }} className="sm:w-48">
              <option value="">All movement types</option>
              <option value="in">Stock in</option>
              <option value="out">Stock out</option>
              <option value="adjustment">Adjustment</option>
            </Select>
          </div>
        )}

        {activeQuery.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : activeQuery.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{activeQuery.error.message}</p>
        ) : tab === 'medicines' ? (
          medicineRows.length === 0 ? (
            <EmptyState
              title={medicineSearch ? 'No matching medicines' : 'No medicines yet'}
              message={
                medicineSearch
                  ? 'Try a different medicine name or code.'
                  : 'Add medicines to the formulary to begin stocking.'
              }
            />
          ) : (
            <Table minWidth="min-w-[840px]">
              <THead>
                <Tr>
                  <Th>Code</Th>
                  <Th>Name</Th>
                  <Th>Category</Th>
                  <Th>Strength / Form</Th>
                  <Th>Price</Th>
                  <Th>Status</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {medicineRows.map((m) => (
                  <Tr key={m.id} onClick={() => setViewing({ kind: 'medicine', row: m })}>
                    <Td className="font-mono text-xs text-ink-muted">{m.code}</Td>
                    <Td>
                      <p className="font-medium text-ink">{m.name}</p>
                      {m.generic_name ? <p className="text-xs text-ink-muted">{m.generic_name}</p> : null}
                    </Td>
                    <Td>{m.category ?? '—'}</Td>
                    <Td className="text-ink-muted">
                      {[m.strength, m.form].filter(Boolean).join(' · ') || '—'}
                    </Td>
                    <Td className="font-medium text-ink">{formatCurrency(m.price)}</Td>
                    <Td>{m.is_active ? <Badge tone="green">Active</Badge> : <Badge tone="gray">Inactive</Badge>}</Td>
                    <Td onClick={(e) => e.stopPropagation()}>
                      <div className="flex justify-end gap-1">
                        {canManageMedicines ? (
                          <button
                            type="button"
                            aria-label={`Edit ${m.name}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                            onClick={() => setForm({ kind: 'medicine', initial: m })}
                          >
                            <Pencil className="size-4" aria-hidden />
                          </button>
                        ) : null}
                        {can('medicines.delete') ? (
                          <button
                            type="button"
                            aria-label={`Delete ${m.name}`}
                            className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                            onClick={() => setDeleting({ kind: 'medicine', id: m.id })}
                          >
                            <Trash2 className="size-4" aria-hidden />
                          </button>
                        ) : null}
                      </div>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          )
        ) : tab === 'inventory' ? (
          inventoryRows.length === 0 ? (
            <EmptyState title={inventorySearch || inventoryStatus !== 'all' ? 'No matching stock batches' : 'No stock batches'} message={inventorySearch || inventoryStatus !== 'all' ? 'Try changing the search or batch status filter.' : 'Receive stock for a medicine to create its first batch.'} />
          ) : (
            <Table minWidth="min-w-[840px]">
              <THead>
                <Tr>
                  <Th>Medicine</Th>
                  <Th>Batch</Th>
                  <Th>Quantity</Th>
                  <Th>Expiration</Th>
                  <Th>Received</Th>
                  <Th>Location</Th>
                  <Th><span className="sr-only">Actions</span></Th>
                </Tr>
              </THead>
              <TBody>
                {inventoryRows.map((item) => {
                  const low = item.quantity <= (item.medicine?.reorder_level ?? 0)
                  return (
                    <Tr key={item.id} onClick={() => setViewing({ kind: 'inventory', row: item })}>
                      <Td className="font-medium text-ink">{item.medicine?.name ?? `Medicine #${item.medicine_id}`}</Td>
                      <Td className="font-mono text-xs text-ink-muted">{item.batch_number || '—'}</Td>
                      <Td>
                        <Badge tone={low ? 'amber' : 'green'}>{item.quantity}</Badge>
                      </Td>
                      <Td className="text-ink-muted">{formatDate(item.expiration_date)}</Td>
                      <Td className="text-ink-muted">{formatDate(item.received_date)}</Td>
                      <Td>{item.location ?? '—'}</Td>
                      <Td onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-end gap-1">
                          {canManageInventory ? (
                            <button
                              type="button"
                              aria-label={`Edit batch ${item.batch_number || item.id}`}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => setForm({ kind: 'inventory', initial: item })}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                          ) : null}
                          {can('inventory.manage') ? (
                            <button
                              type="button"
                              aria-label="Delete stock batch"
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setDeleting({ kind: 'inventory', id: item.id })}
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
          )
        ) : movementRows.length === 0 ? (
          <EmptyState title={movementSearch || movementType ? 'No matching stock movements' : 'No stock movements'} message={movementSearch || movementType ? 'Try changing the search or movement type filter.' : 'Receiving, dispensing and adjustments will appear here.'} />
        ) : (
          <Table minWidth="min-w-[840px]">
            <THead>
              <Tr>
                <Th>Medicine</Th>
                <Th>Batch</Th>
                <Th>Type</Th>
                <Th>Quantity</Th>
                <Th>Reference</Th>
                <Th>Reason</Th>
                <Th>By</Th>
                <Th>When</Th>
              </Tr>
            </THead>
            <TBody>
              {movementRows.map((m) => {
                const type = statusBadgeFor(STOCK_MOVEMENT_TYPE, m.type)
                return (
                  <Tr key={m.id} onClick={() => setViewing({ kind: 'movement', row: m })}>
                    <Td className="font-medium text-ink">{m.medicine?.name ?? `Medicine #${m.medicine_id}`}</Td>
                    <Td className="font-mono text-xs text-ink-muted">{m.inventory_item?.batch_number ?? '—'}</Td>
                    <Td><Badge tone={type.tone}>{type.label}</Badge></Td>
                    <Td>{m.quantity}</Td>
                    <Td className="font-mono text-xs text-ink-muted">{m.reference || '—'}</Td>
                    <Td className="max-w-56 truncate text-ink-muted">{m.reason || '—'}</Td>
                    <Td>{m.user?.name ?? '—'}</Td>
                    <Td className="text-ink-muted">{formatDate(m.created_at)}</Td>
                  </Tr>
                )
              })}
            </TBody>
          </Table>
        )}

        {tab === 'medicines' ? (
          <Pagination page={medicines.data?.current_page ?? 1} lastPage={medicines.data?.last_page ?? 1} total={medicines.data?.total} onChange={setPage} />
        ) : tab === 'inventory' ? (
          <Pagination page={inventory.data?.current_page ?? 1} lastPage={inventory.data?.last_page ?? 1} total={inventory.data?.total} onChange={setInventoryPage} />
        ) : (
          <Pagination page={movements.data?.current_page ?? 1} lastPage={movements.data?.last_page ?? 1} total={movements.data?.total} onChange={setMovementPage} />
        )}
      </Card>

      {form?.kind === 'medicine' ? (
        <MedicineFormModal
          open
          onClose={() => setForm(null)}
          initial={form.initial}
          onSaved={refresh}
        />
      ) : null}
      {form?.kind === 'inventory' ? (
        <InventoryFormModal open onClose={() => setForm(null)} initial={form.initial} onSaved={refresh} />
      ) : null}
      {form?.kind === 'movement' ? (
        <StockMovementFormModal open onClose={() => setForm(null)} onSaved={refresh} />
      ) : null}

      <RecordDetailModal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={
          viewing?.kind === 'medicine'
            ? viewing.row.name
            : viewing?.kind === 'inventory'
              ? viewing.row.medicine?.name ?? `Medicine #${viewing.row.medicine_id}`
              : viewing?.kind === 'movement'
                ? viewing.row.medicine?.name ?? `Medicine #${viewing.row.medicine_id}`
                : ''
        }
        subtitle={
          viewing?.kind === 'medicine'
            ? viewing.row.generic_name
            : viewing?.kind === 'inventory'
              ? (viewing.row.batch_number ? `Batch ${viewing.row.batch_number}` : undefined)
              : viewing?.kind === 'movement'
                ? statusBadgeFor(STOCK_MOVEMENT_TYPE, viewing.row.type).label
                : undefined
        }
        badges={
          viewing?.kind === 'medicine'
            ? [viewing.row.is_active ? { label: 'Active', tone: 'green' } : { label: 'Inactive', tone: 'gray' }]
            : viewing?.kind === 'movement'
              ? [statusBadgeFor(STOCK_MOVEMENT_TYPE, viewing.row.type)]
              : []
        }
        fields={
          viewing?.kind === 'medicine'
            ? [
                { label: 'Code', value: viewing.row.code },
                { label: 'Name', value: viewing.row.name },
                { label: 'Generic name', value: viewing.row.generic_name },
                { label: 'Category', value: viewing.row.category },
                { label: 'Strength', value: viewing.row.strength },
                { label: 'Form', value: viewing.row.form },
                { label: 'Price', value: formatCurrency(viewing.row.price) },
                { label: 'Reorder level', value: viewing.row.reorder_level },
                { label: 'Description', value: viewing.row.description },
              ]
            : viewing?.kind === 'inventory'
              ? [
                  { label: 'Medicine', value: viewing.row.medicine?.name ?? `Medicine #${viewing.row.medicine_id}` },
                  { label: 'Batch number', value: viewing.row.batch_number },
                  { label: 'Quantity', value: viewing.row.quantity },
                  { label: 'Expiration date', value: viewing.row.expiration_date ? formatDate(viewing.row.expiration_date) : '—' },
                  { label: 'Received date', value: viewing.row.received_date ? formatDate(viewing.row.received_date) : '—' },
                  { label: 'Location', value: viewing.row.location },
                ]
              : viewing?.kind === 'movement'
                ? [
                    { label: 'Medicine', value: viewing.row.medicine?.name ?? `Medicine #${viewing.row.medicine_id}` },
                    { label: 'Batch', value: viewing.row.inventory_item?.batch_number },
                    { label: 'Type', value: statusBadgeFor(STOCK_MOVEMENT_TYPE, viewing.row.type).label },
                    { label: 'Quantity', value: viewing.row.quantity },
                    { label: 'Reference', value: viewing.row.reference },
                    { label: 'Reason', value: viewing.row.reason },
                    { label: 'Recorded by', value: viewing.row.user?.name },
                    { label: 'When', value: viewing.row.created_at ? formatDate(viewing.row.created_at) : '—' },
                  ]
                : []
        }
      />

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title={deleting?.kind === 'medicine' ? 'Delete medicine?' : 'Delete stock batch?'}
        message="This action is permanent and cannot be undone."
        confirmLabel="Delete"
      />
    </div>
  )
}
