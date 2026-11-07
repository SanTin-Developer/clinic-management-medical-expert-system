import { useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { BedDouble, DoorOpen, Eye, Pencil, Plus } from 'lucide-react'
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
import RoomDetailModal from './RoomDetailModal.jsx'

export default function WardDetailModal({ open, onClose, wardId, onEdit, onChanged }) {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const manageRooms = can('wards.manage')
  const manageBeds = can('beds.manage')
  const viewRooms = can('wards.view') || can('beds.view') || manageRooms || manageBeds
  const [roomFormOpen, setRoomFormOpen] = useState(false)
  const [editingRoom, setEditingRoom] = useState(null)
  const [bedFormRoomId, setBedFormRoomId] = useState(null)
  const [editingBed, setEditingBed] = useState(null)
  const [roomDetailId, setRoomDetailId] = useState(null)

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

  const refreshAll = () => {
    roomsQuery.refetch()
    bedsQuery.refetch()
    queryClient.invalidateQueries({ queryKey: ['room'] })
    onChanged?.()
  }

  const openRoomForm = (room = null) => {
    setEditingRoom(room)
    setRoomFormOpen(true)
  }

  const closeRoomForm = () => {
    setRoomFormOpen(false)
    setEditingRoom(null)
  }

  const openBedForm = (roomId, bed = null) => {
    setEditingBed(bed)
    setBedFormRoomId(roomId)
  }

  const closeBedForm = () => {
    setEditingBed(null)
    setBedFormRoomId(null)
  }

  const changeBedStatus = async (bed, status) => {
    try {
      await bedApi.setStatus(bed.id, status)
      toast.success(`Bed ${bed.label} set to ${status}.`)
      bedsQuery.refetch()
      queryClient.invalidateQueries({ queryKey: ['room'] })
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
                {manageRooms ? (
                  <Button size="sm" variant="outline" onClick={() => openRoomForm()}>
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
                          {viewRooms ? (
                            <div className="flex flex-wrap items-center gap-1">
                              <Button size="sm" variant="ghost" onClick={() => setRoomDetailId(room.id)}>
                                <Eye className="size-4" aria-hidden /> View
                              </Button>
                              {manageRooms ? (
                                <Button size="sm" variant="ghost" onClick={() => openRoomForm(room)}>
                                  <Pencil className="size-4" aria-hidden /> Edit
                                </Button>
                              ) : null}
                              {manageBeds ? (
                                <Button size="sm" variant="ghost" onClick={() => openBedForm(room.id)}>
                                  <Plus className="size-4" aria-hidden /> Add bed
                                </Button>
                              ) : null}
                            </div>
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
                                  {manageBeds ? (
                                    <div className="flex items-center gap-1">
                                      {bed.status !== 'occupied' ? (
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
                                      <button
                                        type="button"
                                        aria-label={`Edit bed ${bed.label}`}
                                        className="rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
                                        onClick={() => openBedForm(room.id, bed)}
                                      >
                                        <Pencil className="size-4" aria-hidden />
                                      </button>
                                    </div>
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
        onClose={closeRoomForm}
        wardId={wardId}
        room={editingRoom}
        onSaved={refreshAll}
      />
      <BedFormModal
        open={Boolean(bedFormRoomId)}
        onClose={closeBedForm}
        rooms={rooms}
        roomId={bedFormRoomId}
        bed={editingBed}
        onSaved={refreshAll}
      />
      <RoomDetailModal
        open={Boolean(roomDetailId)}
        onClose={() => setRoomDetailId(null)}
        roomId={roomDetailId}
        onEditRoom={(room) => openRoomForm(room)}
        onAddBed={(room) => openBedForm(room.id)}
        onEditBed={(bed) => openBedForm(bed.room_id, bed)}
        onChanged={refreshAll}
      />
    </>
  )
}
