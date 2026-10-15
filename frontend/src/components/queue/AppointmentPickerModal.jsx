import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CalendarClock, Plus } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import { EmptyState, ErrorState } from '../ui/State.jsx'
import { RowSkeleton } from '../ui/Skeleton.jsx'
import { queueApi } from '../../api/queue.js'
import { patientFullName } from '../../utils/format.js'
import { APPOINTMENT_STATUS, statusBadgeFor } from '../../utils/status.js'

export default function AppointmentPickerModal({ open, date, onClose, onChanged }) {
  const query = useQuery({
    queryKey: ['queue', 'appointments', date],
    queryFn: () => queueApi.appointments({ date }),
    enabled: open,
  })

  const appointments = query.data?.data ?? []

  const add = async (appointment) => {
    try {
      await queueApi.create({ appointment_id: appointment.id })
      toast.success(`${patientFullName(appointment.patient)} added to the queue.`)
      query.refetch()
      onChanged?.()
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Add from appointments"
      description="Pull today's scheduled appointments into the queue."
      size="xl"
    >
      {query.isLoading ? (
        <RowSkeleton rows={4} cols={1} />
      ) : query.isError ? (
        <ErrorState title="Failed to load appointments" message={query.error?.message} onRetry={() => query.refetch()} />
      ) : appointments.length === 0 ? (
        <EmptyState title="No appointments" message="There are no scheduled appointments for this date." icon={CalendarClock} />
      ) : (
        <ul className="divide-y divide-line">
          {appointments.map((appointment) => {
            const statusMeta = statusBadgeFor(APPOINTMENT_STATUS, appointment.status)
            return (
              <li key={appointment.id} className="flex items-center justify-between gap-4 py-3">
                <div className="min-w-0">
                  <p className="truncate font-medium text-ink">
                    {patientFullName(appointment.patient)}
                  </p>
                  <p className="mt-0.5 flex items-center gap-2 text-sm text-ink-muted">
                    <span>{appointment.appointment_time?.slice(0, 5) ?? '—'}</span>
                    <span aria-hidden>·</span>
                    <span className="truncate">{appointment.doctor?.name ?? 'Unassigned'}</span>
                    <Badge tone={statusMeta.tone}>{statusMeta.label}</Badge>
                  </p>
                </div>
                {appointment.already_queued ? (
                  <Badge tone="green">In queue</Badge>
                ) : (
                  <Button size="sm" variant="outline" onClick={() => add(appointment)}>
                    <Plus className="size-4" aria-hidden /> Add
                  </Button>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </Modal>
  )
}
