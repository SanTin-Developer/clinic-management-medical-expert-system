import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { BedDouble, DoorOpen, Plus } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import Select from '../ui/Select.jsx'
import { Spinner } from '../ui/Skeleton.jsx'
import { EmptyState } from '../ui/State.jsx'
import { wardApi, roomApi, bedApi } from '../../api/nursing.js'
import { useAll } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { BED_STATUS, BED_STATUS_OPTIONS, ROOM_TYPES, statusBadgeFor, WARD_TYPES } from '../../utils/status.js'
import { formatCurrency, patientFullName } from '../../utils/format.js'
import RoomFormModal from './RoomFormModal.jsx'
import BedFormModal from './BedFormModal.jsx'

export default function WardDetailModal({ open, onClose, wardId, onEdit, onChanged }) {
  const { can } = useAuth()
  const manage = can('beds.manage') || can('wards.manage')
  const [roomFormOpen, setRoomFormOpen] = useState(false)
  const [bedFormRoomId, setBedFormRoomId] = useState(null)

  const wardQuery = useQuery({
    queryKey: ['ward', wardId, 'detail'],
    queryFn: () => wardApi.get(wardId),
    enabled: open && Boolean(wardId),
  })

  const roomsQuery = useAll(
    ['rooms', wardId],
    (params) => roomApi.list(params),
    { params: { ward_id: wardId }, enabled: open && Boolean(wardId) },
  )

  const bedsQuery = useAll(
    ['beds', wardId],
    (params) => bedApi.list(params),
    { params: { ward_id: wardId }, enabled: open && Boolean(wardId) },
  )

  const ward = wardQuery.data
  const rooms = roomsQuery.data ?? []
  const bedsByRoom = useMemo(() => {
    const map = {}
    for (const bed of bedsQuery.data ?? []) {
      ;(map[bed.room_id] ??= []).push(bed)
    }
    return map
  }, [bedsQuery.data])

  const changeBedStatus = async (bed, status) => {
    try {
      await bedApi.setStatus(bed.id, status)
      toast.success(`Bed ${bed.label} set to ${status}.`)
      bedsQuery.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const typeMeta = statusBadgeFor(WARD_TYPES, ward?.ward_type)

  return (
    <>
      <Modal
        open={open}
        onClose={onClose}
        title={ward?.name ?? 'Ward'}
        description={ward ? `Code ${ward.code}${ward.floor ? ` · Floor ${ward.floor}` : ''}` : undefined}
        size="xl"
        footer={
          <>
            <Button variant="outline" onClick={onClose}>Close</Button>
            {can('wards.manage') && onEdit ? (
              <Button variant="secondary" onClick={() => onEdit(ward)}>Edit ward</Button>
            ) : null}
          </>
        }
      >
        {wardQuery.isError ? (
          <p className="py-6 text-center text-sm text-red-600">{wardQuery.error.message}</p>
        ) : !ward ? (
          <div className="py-10 text-center"><Spinner label="Loading ward…" /></div>
        ) : (
          <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={typeMeta.tone}>{typeMeta.label}</Badge>
              <Badge tone={ward.is_active ? 'green' : 'gray'}>{ward.is_active ? 'Active' : 'Inactive'}</Badge>
              <span className="text-sm text-ink-muted">{rooms.length} rooms</span>
            </div>

            {ward.activeAdmissions?.length ? (
              <div>
                <h3 className="mb-2 text-sm font-semibold text-ink">Currently admitted</h3>
                <div className="flex flex-wrap gap-2">
                  {ward.activeAdmissions.map((a) => (
                    <Badge key={a.id} tone="blue">
                      {patientFullName(a.patient)}
                    </Badge>
                  ))}
                </div>
              </div>
            ) : null}

            <div>
              <div className="mb-2 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-ink">Rooms &amp; beds</h3>
                {manage ? (
                  <Button size="sm" variant="outline" onClick={() => setRoomFormOpen(true)}>
                    <Plus className="size-4" aria-hidden /> Add room
                  </Button>
                ) : null}
              </div>

              {roomsQuery.isLoading ? (
                <div className="py-6 text-center"><Spinner label="Loading rooms…" /></div>
              ) : rooms.length === 0 ? (
                <EmptyState title="No rooms yet" message="Add a room to start placing beds." icon={DoorOpen} />
              ) : (
                <div className="space-y-3">
                  {rooms.map((room) => {
                    const roomType = statusBadgeFor(ROOM_TYPES, room.room_type)
                    const beds = bedsByRoom[room.id] ?? []
                    return (
                      <div key={room.id} className="rounded-xl border border-line bg-canvas/40 p-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <div className="flex items-center gap-2">
                            <span className="font-medium text-ink">Room {room.room_number}</span>
                            <Badge tone={roomType.tone}>{roomType.label}</Badge>
                            <span className="text-xs text-ink-muted">{beds.length} beds</span>
                            {room.price_per_day != null ? (
                              <span className="text-xs font-medium text-ink-muted">{formatCurrency(room.price_per_day)}/day</span>
                            ) : null}
                          </div>
                          {manage ? (
                            <Button size="sm" variant="ghost" onClick={() => setBedFormRoomId(room.id)}>
                              <Plus className="size-4" aria-hidden /> Add bed
                            </Button>
                          ) : null}
                        </div>
                        {beds.length === 0 ? (
                          <p className="mt-2 text-xs text-ink-muted">No beds in this room.</p>
                        ) : (
                          <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {beds.map((bed) => {
                              const bedMeta = statusBadgeFor(BED_STATUS, bed.status)
                              return (
                                <div key={bed.id} className="flex items-center justify-between gap-2 rounded-lg border border-line bg-surface px-3 py-2">
                                  <span className="flex items-center gap-2 text-sm text-ink">
                                    <BedDouble className="size-4 text-ink-muted" aria-hidden /> {bed.label}
                                    <Badge tone={bedMeta.tone}>{bedMeta.label}</Badge>
                                  </span>
                                  {manage && bed.status !== 'occupied' ? (
                                    <Select
                                      className="h-8 w-32 text-xs"
                                      value={bed.status}
                                      onChange={(e) => changeBedStatus(bed, e.target.value)}
                                      aria-label={`Status for bed ${bed.label}`}
                                    >
                                      {BED_STATUS_OPTIONS.filter((o) => o.value !== 'occupied').map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                      ))}
                                    </Select>
                                  ) : null}
                                </div>
                              )
                            })}
                          </div>
                        )}
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          </div>
        )}
      </Modal>

      <RoomFormModal
        open={roomFormOpen}
        onClose={() => setRoomFormOpen(false)}
        wardId={wardId}
        onSaved={() => { roomsQuery.refetch(); bedsQuery.refetch(); onChanged?.() }}
      />
      <BedFormModal
        open={Boolean(bedFormRoomId)}
        onClose={() => setBedFormRoomId(null)}
        rooms={rooms}
        roomId={bedFormRoomId}
        onSaved={() => { roomsQuery.refetch(); bedsQuery.refetch(); onChanged?.() }}
      />
    </>
  )
}
