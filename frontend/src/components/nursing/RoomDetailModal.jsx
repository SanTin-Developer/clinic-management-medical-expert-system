import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { BedDouble, Pencil, Plus, Trash2 } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import Select from '../ui/Select.jsx'
import ConfirmDialog from '../ui/ConfirmDialog.jsx'
import { Spinner } from '../ui/Skeleton.jsx'
import { EmptyState } from '../ui/State.jsx'
import { roomApi, bedApi } from '../../api/nursing.js'
import { useAuth } from '../../hooks/useAuth.js'
import { BED_STATUS, BED_STATUS_OPTIONS, ROOM_TYPES, statusBadgeFor } from '../../utils/status.js'
import { formatCurrency } from '../../utils/format.js'

export default function RoomDetailModal({
  open,
  onClose,
  roomId,
  onEditRoom,
  onAddBed,
  onEditBed,
  onChanged,
}) {
  const { can } = useAuth()
  const canManageRoom = can('wards.manage')
  const canManageBeds = can('beds.manage')
  const [busyBedId, setBusyBedId] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [deleteBusy, setDeleteBusy] = useState(false)

  const query = useQuery({
    queryKey: ['room', roomId, 'detail'],
    queryFn: () => roomApi.get(roomId),
    enabled: open && Boolean(roomId),
  })

  const room = query.data
  const beds = room?.beds ?? []
  const typeMeta = statusBadgeFor(ROOM_TYPES, room?.room_type)

  const changeBedStatus = async (bed, status) => {
    setBusyBedId(bed.id)
    try {
      await bedApi.setStatus(bed.id, status)
      toast.success(`Bed ${bed.label} set to ${status}.`)
      await query.refetch()
      onChanged?.()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusyBedId(null)
    }
  }

  const confirmDelete = async () => {
    if (!deleting) return
    setDeleteBusy(true)
    try {
      if (deleting.type === 'bed') {
        await bedApi.remove(deleting.item.id)
        toast.success(`Bed ${deleting.item.label} deleted.`)
        setDeleting(null)
        await query.refetch()
      } else {
        await roomApi.remove(deleting.item.id)
        toast.success('Room deleted.')
        setDeleting(null)
        onClose?.()
      }
      onChanged?.()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeleteBusy(false)
    }
  }

  return (
    <>
      <Modal
        open={open}
        onClose={onClose}
        title={room ? `Room ${room.room_number}` : 'Room'}
        description={room?.ward?.name}
        size="lg"
        footer={
          <>
            <Button variant="outline" onClick={onClose}>Close</Button>
            {canManageRoom && room ? (
              <Button
                variant="danger"
                onClick={() => setDeleting({ type: 'room', item: room })}
              >
                Delete room
              </Button>
            ) : null}
            {canManageRoom && room ? (
              <Button variant="secondary" onClick={() => onEditRoom?.(room)}>
                <Pencil className="size-4" aria-hidden /> Edit room
              </Button>
            ) : null}
            {canManageBeds && room ? (
              <Button onClick={() => onAddBed?.(room)}>
                <Plus className="size-4" aria-hidden /> Add bed
              </Button>
            ) : null}
          </>
        }
      >
        {query.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{query.error.message}</p>
        ) : !room ? (
          <div className="py-10 text-center"><Spinner label="Loading room…" /></div>
        ) : (
          <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={typeMeta.tone}>{typeMeta.label}</Badge>
              <Badge tone={room.is_active ? 'green' : 'gray'}>
                {room.is_active ? 'Active' : 'Inactive'}
              </Badge>
              <span className="text-sm text-ink-muted">{beds.length} beds</span>
            </div>

            <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3">
              <div>
                <dt className="text-xs font-medium text-ink-muted">Ward</dt>
                <dd className="mt-0.5 text-sm text-ink">{room.ward?.name ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-ink-muted">Capacity</dt>
                <dd className="mt-0.5 text-sm text-ink">{room.capacity ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-ink-muted">Price / day</dt>
                <dd className="mt-0.5 text-sm text-ink">
                  {room.price_per_day != null ? formatCurrency(room.price_per_day) : '—'}
                </dd>
              </div>
            </dl>

            {room.notes ? (
              <div>
                <h3 className="text-sm font-semibold text-ink">Notes</h3>
                <p className="mt-1 whitespace-pre-line text-sm text-ink-muted">{room.notes}</p>
              </div>
            ) : null}

            <div>
              <div className="mb-2 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-ink">Beds</h3>
                {canManageBeds ? (
                  <Button size="sm" variant="outline" onClick={() => onAddBed?.(room)}>
                    <Plus className="size-4" aria-hidden /> Add bed
                  </Button>
                ) : null}
              </div>

              {beds.length === 0 ? (
                <EmptyState
                  title="No beds in this room"
                  message="Add a bed so patients can be admitted to this room."
                  icon={BedDouble}
                  action={
                    canManageBeds ? (
                      <Button size="sm" onClick={() => onAddBed?.(room)}>Add bed</Button>
                    ) : null
                  }
                />
              ) : (
                <div className="space-y-2">
                  {beds.map((bed) => {
                    const bedMeta = statusBadgeFor(BED_STATUS, bed.status)
                    return (
                      <div
                        key={bed.id}
                        className="flex flex-wrap items-start justify-between gap-2 rounded-lg border border-line bg-canvas/40 px-3 py-2"
                      >
                        <div className="min-w-0">
                          <div className="flex items-center gap-2">
                            <BedDouble className="size-4 text-ink-muted" aria-hidden />
                            <span className="text-sm font-medium text-ink">Bed {bed.label}</span>
                            <Badge tone={bedMeta.tone}>{bedMeta.label}</Badge>
                          </div>
                          {bed.notes ? (
                            <p className="mt-1 text-xs text-ink-muted">{bed.notes}</p>
                          ) : null}
                        </div>
                        {canManageBeds ? (
                          <div className="flex items-center gap-1">
                            {bed.status !== 'occupied' ? (
                              <Select
                                className="h-8 w-32 text-xs"
                                value={bed.status}
                                onChange={(e) => changeBedStatus(bed, e.target.value)}
                                disabled={busyBedId === bed.id}
                                aria-label={`Status for bed ${bed.label}`}
                              >
                                {BED_STATUS_OPTIONS.filter((o) => o.value !== 'occupied').map((o) => (
                                  <option key={o.value} value={o.value}>{o.label}</option>
                                ))}
                              </Select>
                            ) : null}
                            <button
                              type="button"
                              aria-label={`Edit bed ${bed.label}`}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                              onClick={() => onEditBed?.(bed)}
                            >
                              <Pencil className="size-4" aria-hidden />
                            </button>
                            <button
                              type="button"
                              aria-label={`Delete bed ${bed.label}`}
                              className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                              onClick={() => setDeleting({ type: 'bed', item: bed })}
                            >
                              <Trash2 className="size-4" aria-hidden />
                            </button>
                          </div>
                        ) : null}
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        loading={deleteBusy}
        title={deleting?.type === 'bed' ? 'Delete bed?' : 'Delete room?'}
        message={
          deleting?.type === 'bed'
            ? `Bed ${deleting.item.label} will be removed. Beds with an active admission cannot be deleted.`
            : 'This permanently deletes the room and its beds. Rooms with occupied beds cannot be deleted.'
        }
        confirmLabel="Delete"
      />
    </>
  )
}
