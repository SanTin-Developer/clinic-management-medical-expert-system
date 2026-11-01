import { ChevronRight } from 'lucide-react'
import Badge from '../ui/Badge.jsx'
import { cn } from '../../utils/cn.js'

/**
 * Appointment card used below the `lg` breakpoint. It has a fixed three-line
 * structure so every card in the list is the same size, regardless of how long
 * the patient/doctor/date values are:
 *
 *   [ patient name ......................... status ]  >
 *   [ doctor name                                      ]
 *   [ date · time                                      ]
 *
 * Every text line is single-line with an ellipsis, and the badge and chevron
 * never shrink or wrap. Row actions (edit/delete) are kept in a footer.
 */
export default function AppointmentMobileCard({
  patientName,
  doctorName,
  dateLabel,
  status,
  onOpen,
  actions,
  className,
}) {
  return (
    <div
      onClick={onOpen}
      className={cn(
        'flex w-full items-center gap-3 rounded-xl border border-line bg-surface p-3 shadow-[var(--shadow-card)] transition-colors hover:bg-canvas/70',
        onOpen && 'cursor-pointer',
        className,
      )}
    >
      <div className="min-w-0 flex-1 space-y-0.5">
        <div className="flex min-w-0 items-center gap-2">
          <p className="min-w-0 flex-1 truncate text-sm font-semibold leading-7 text-ink">
            {patientName}
          </p>
          <Badge tone={status.tone} className="shrink-0">
            {status.label}
          </Badge>
        </div>
        <p className="truncate text-xs leading-6 text-ink-muted">{doctorName}</p>
        <p className="truncate text-xs leading-6 text-ink-muted">{dateLabel}</p>

        {actions ? (
          <div
            className="mt-1 flex justify-end gap-1 border-t border-line pt-2"
            onClick={(event) => event.stopPropagation()}
          >
            {actions}
          </div>
        ) : null}
      </div>

      <ChevronRight
        className="size-4 shrink-0 text-ink-muted/60"
        aria-hidden
      />
    </div>
  )
}
