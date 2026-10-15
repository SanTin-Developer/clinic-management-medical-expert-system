import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { CalendarRange } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import { RowSkeleton } from '../ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../ui/State.jsx'
import { shiftApi } from '../../api/shifts.js'
import { SHIFT_TYPES, statusBadgeFor } from '../../utils/status.js'
import { formatDate, formatTime, toIsoDate } from '../../utils/format.js'

function weekStartIso() {
  const d = new Date()
  const offset = (d.getDay() + 6) % 7
  d.setDate(d.getDate() - offset)
  return toIsoDate(d)
}

function addDaysIso(iso, days) {
  const d = new Date(`${iso}T00:00:00`)
  d.setDate(d.getDate() + days)
  return toIsoDate(d)
}

export default function ShiftBoardModal({ open, onClose }) {
  const [from, setFrom] = useState(weekStartIso)
  const [to, setTo] = useState(() => addDaysIso(weekStartIso(), 6))
  const [tab, setTab] = useState('board')

  const boardQuery = useQuery({
    queryKey: ['shifts', 'board', from, to],
    queryFn: () => shiftApi.board({ from, to }),
    enabled: open && tab === 'board',
  })

  const uncoveredQuery = useQuery({
    queryKey: ['shifts', 'uncovered', from, to],
    queryFn: () => shiftApi.uncovered({ from, to }),
    enabled: open && tab === 'uncovered',
  })

  const grouped = useMemo(() => {
    const map = new Map()
    for (const shift of boardQuery.data?.data ?? []) {
      const key = shift.shift_date?.slice(0, 10) ?? '—'
      if (!map.has(key)) map.set(key, [])
      map.get(key).push(shift)
    }
    return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]))
  }, [boardQuery.data])

  const activeQuery = tab === 'board' ? boardQuery : uncoveredQuery

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Scheduling board"
      description="Weekly roster and shifts that still have nobody assigned."
      size="xl"
      footer={<Button variant="outline" onClick={onClose}>Close</Button>}
    >
      <div className="space-y-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Field label="From" htmlFor="board-from" className="space-y-1">
            <Input id="board-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          </Field>
          <Field label="To" htmlFor="board-to" className="space-y-1">
            <Input id="board-to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </Field>
          <div className="flex items-end gap-2">
            <Button variant={tab === 'board' ? 'primary' : 'outline'} size="sm" onClick={() => setTab('board')}>
              Roster
            </Button>
            <Button variant={tab === 'uncovered' ? 'primary' : 'outline'} size="sm" onClick={() => setTab('uncovered')}>
              Uncovered
            </Button>
          </div>
        </div>

        {activeQuery.isLoading ? (
          <RowSkeleton rows={5} cols={4} />
        ) : activeQuery.isError ? (
          <ErrorState title="Failed to load" message={activeQuery.error?.message} onRetry={() => activeQuery.refetch()} />
        ) : tab === 'board' ? (
          grouped.length === 0 ? (
            <EmptyState title="No shifts scheduled" message="No shifts fall in this date range." icon={CalendarRange} />
          ) : (
            <div className="space-y-4">
              {grouped.map(([date, shifts]) => (
                <div key={date}>
                  <p className="mb-2 text-sm font-semibold text-ink">{formatDate(date)}</p>
                  <div className="space-y-1.5">
                    {shifts.map((shift) => {
                      const typeMeta = statusBadgeFor(SHIFT_TYPES, shift.type)
                      return (
                        <div key={shift.id} className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-line px-3 py-2">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-medium text-ink">{shift.user?.name ?? '—'}</p>
                            <p className="text-xs text-ink-muted">
                              {shift.shift_template?.name ? `${shift.shift_template.name} · ` : ''}
                              {formatTime(shift.start_time)} – {formatTime(shift.end_time)}
                            </p>
                          </div>
                          <div className="flex items-center gap-2">
                            <Badge tone={typeMeta.tone}>{typeMeta.label}</Badge>
                            {!shift.published_at ? <Badge tone="amber">Draft</Badge> : null}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                </div>
              ))}
            </div>
          )
        ) : (uncoveredQuery.data ?? []).length === 0 ? (
          <EmptyState title="All shifts covered" message="Every template has someone assigned in this range." icon={CalendarRange} />
        ) : (
          <div className="space-y-1.5">
            {uncoveredQuery.data.map((row) => {
              const typeMeta = statusBadgeFor(SHIFT_TYPES, row.type)
              return (
                <div key={`${row.shift_template_id}-${row.date}`} className="flex items-center justify-between rounded-lg border border-line px-3 py-2">
                  <div>
                    <p className="text-sm font-medium text-ink">{row.name}</p>
                    <p className="text-xs text-ink-muted">{formatDate(row.date)}</p>
                  </div>
                  <Badge tone={typeMeta.tone}>{typeMeta.label}</Badge>
                </div>
              )
            })}
          </div>
        )}
      </div>
    </Modal>
  )
}
