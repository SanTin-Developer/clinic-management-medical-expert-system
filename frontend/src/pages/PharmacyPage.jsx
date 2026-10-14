import { useState } from 'react'
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
import { usePaginated, useAll } from '../hooks/usePaginated.js'
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
import SearchInput from '../components/ui/SearchInput.jsx'
import { RowSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState } from '../components/ui/State.jsx'
import MedicineFormModal from '../components/pharmacy/MedicineFormModal.jsx'
import InventoryFormModal from '../components/pharmacy/InventoryFormModal.jsx'
import StockMovementFormModal from '../components/pharmacy/StockMovementFormModal.jsx'
import { formatCurrency, formatDate } from '../utils/format.js'
import { statusBadgeFor, STOCK_MOVEMENT_TYPE } from '../utils/status.js'

export default function PharmacyPage() {
  const { can } = useAuth()
  const [tab, setTab] = useState('medicines')
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')

  const medicines = usePaginated(['medicines'], (params) => medicineApi.list(params), { page })
  const inventory = useAll(['inventory', 'all'], (params) => inventoryApi.list(params))
  const movements = useAll(['stock-movements', 'all'], (params) => stockMovementApi.list(params))

  const [form, setForm] = useState(null)
  const [deleting, setDeleting] = useState(null)

  const allMedicines = medicines.data?.data ?? []
  const filtered = search
    ? allMedicines.filter((m) =>
        [m.name, m.code, m.generic_name, m.category, m.form]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(search.toLowerCase())),
      )
    : allMedicines

  const canManageMedicines = can('medicines.create') || can('medicines.update')
  const canManageInventory = can('inventory.create') || can('inventory.update')

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
  const inventoryList = inventory.data ?? []
  const now = Date.now()
  const soon = now + 30 * 24 * 60 * 60 * 1000
  const inventoryStats = {
    total: inventoryList.length,
    low_stock: inventoryList.filter((i) => i.quantity <= (i.medicine?.reorder_level ?? 0)).length,
    expiring_soon: inventoryList.filter((i) => {
      if (!i.expiration_date) return false
      const t = new Date(i.expiration_date).getTime()
      return t >= now && t <= soon
    }).length,
    expired: inventoryList.filter(
      (i) => i.expiration_date && new Date(i.expiration_date).getTime() < now,
    ).length,
    out_of_stock: inventoryList.filter((i) => i.quantity <= 0).length,
  }
  const movementList = movements.data ?? []
  const movementStats = {
    total: movementList.length,
    in: movementList.filter((m) => m.type === 'in').length,
    out: movementList.filter((m) => m.type === 'out').length,
    adjustment: movementList.filter((m) => m.type === 'adjustment').length,
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Pharmacy"
        description="Medicines, inventory batches and stock movements."
        breadcrumb={[{ label: 'Pharmacy' }]}
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

      <Card className="px-0 pb-0">
        <div className="flex flex-wrap items-center justify-between gap-3 px-5 pt-4">
          <Tabs
            value={tab}
            onChange={(v) => setTab(v)}
            className="border-b-0"
            tabs={[
              { value: 'medicines', label: 'Medicines' },
              { value: 'inventory', label: 'Inventory' },
              { value: 'movements', label: 'Stock movements' },
            ]}
          />
          <div className="flex gap-2">
            {tab === 'medicines' && can('medicines.create') ? (
              <Button size="sm" onClick={() => setForm({ kind: 'medicine' })}>
                <PackagePlus className="size-4" aria-hidden /> New medicine
              </Button>
            ) : null}
            {tab === 'inventory' && can('inventory.create') ? (
              <Button size="sm" onClick={() => setForm({ kind: 'inventory' })}>
                <Plus className="size-4" aria-hidden /> Add stock
              </Button>
            ) : null}
            {tab === 'movements' && can('inventory.create') ? (
              <Button size="sm" onClick={() => setForm({ kind: 'movement' })}>
                <ArrowLeftRight className="size-4" aria-hidden /> Record movement
              </Button>
            ) : null}
          </div>
        </div>

        {tab === 'medicines' ? (
          <div className="px-5 pt-4">
            <SearchInput
              placeholder="Search medicines…"
              className="w-full sm:w-80"
              onDebounced={setSearch}
            />
          </div>
        ) : null}

        {activeQuery.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={6} cols={5} />
          </div>
        ) : activeQuery.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{activeQuery.error.message}</p>
        ) : tab === 'medicines' ? (
          filtered.length === 0 ? (
            <EmptyState
              title={allMedicines.length === 0 ? 'No medicines yet' : 'No matches'}
              message={
                allMedicines.length === 0
                  ? 'Add medicines to the formulary to begin stocking.'
                  : 'No medicines match your search.'
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
                {filtered.map((m) => (
                  <Tr key={m.id}>
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
                    <Td>
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
          (inventory.data ?? []).length === 0 ? (
            <EmptyState title="No stock batches" message="Add stock to a medicine to see batches here." />
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
                {(inventory.data ?? []).map((item) => {
                  const low = item.quantity <= (item.medicine?.reorder_level ?? 0)
                  return (
                    <Tr key={item.id}>
                      <Td className="font-medium text-ink">{item.medicine?.name ?? `Medicine #${item.medicine_id}`}</Td>
                      <Td className="font-mono text-xs text-ink-muted">{item.batch_number || '—'}</Td>
                      <Td>
                        <Badge tone={low ? 'amber' : 'green'}>{item.quantity}</Badge>
                      </Td>
                      <Td className="text-ink-muted">{formatDate(item.expiration_date)}</Td>
                      <Td className="text-ink-muted">{formatDate(item.received_date)}</Td>
                      <Td>{item.location ?? '—'}</Td>
                      <Td>
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
                          {can('inventory.delete') ? (
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
        ) : (movements.data ?? []).length === 0 ? (
          <EmptyState title="No stock movements" message="Movements (in, out, adjustment) will appear here." />
        ) : (
          <Table minWidth="min-w-[840px]">
            <THead>
              <Tr>
                <Th>Medicine</Th>
                <Th>Type</Th>
                <Th>Quantity</Th>
                <Th>Reference</Th>
                <Th>Reason</Th>
                <Th>By</Th>
                <Th>When</Th>
              </Tr>
            </THead>
            <TBody>
              {(movements.data ?? []).map((m) => {
                const type = statusBadgeFor(STOCK_MOVEMENT_TYPE, m.type)
                return (
                  <Tr key={m.id}>
                    <Td className="font-medium text-ink">{m.medicine?.name ?? `Medicine #${m.medicine_id}`}</Td>
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
        ) : null}
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