import { useMemo, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  addMonths,
  eachDayOfInterval,
  endOfMonth,
  endOfWeek,
  format,
  isSameMonth,
  isToday,
  startOfMonth,
  startOfWeek,
  subMonths,
} from 'date-fns'
import { Calendar, ChevronLeft, ChevronRight } from 'lucide-react'
import { km } from 'date-fns/locale'
import Dropdown from '../ui/Dropdown.jsx'
import { cn } from '../../utils/cn.js'
import { useLanguage } from '../../hooks/useLanguage.js'

const WEEKDAYS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']

export default function NavbarCalendar() {
  const navigate = useNavigate()
  const { language, t } = useLanguage()
  const dateLocale = language === 'km' ? km : undefined
  const [isOpen, setIsOpen] = useState(false)
  const [viewMonth, setViewMonth] = useState(() => startOfMonth(new Date()))

  const days = useMemo(() => {
    const start = startOfWeek(startOfMonth(viewMonth))
    const end = endOfWeek(endOfMonth(viewMonth))
    return eachDayOfInterval({ start, end })
  }, [viewMonth])

  const handleOpenChange = (nextOpen) => {
    if (nextOpen) {
      setViewMonth(startOfMonth(new Date()))
    }
    setIsOpen(nextOpen)
  }

  const goToDate = (day) => {
    navigate(`/appointments?date=${format(day, 'yyyy-MM-dd')}`)
    setIsOpen(false)
  }

  const goToAppointments = () => {
    navigate('/appointments')
    setIsOpen(false)
  }

  return (
    <Dropdown
      isOpen={isOpen}
      onOpenChange={handleOpenChange}
      panelRole="dialog"
      hasPopup="dialog"
      panelLabel={t('Calendar')}
      width="w-72"
      className="p-3"
      trigger={
        <button
          type="button"
          className="grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas"
          aria-label={t('Open calendar')}
        >
          <Calendar className="size-5" aria-hidden />
        </button>
      }
    >
      <div className="flex items-center justify-between gap-2">
        <button
          type="button"
          onClick={() => setViewMonth((current) => subMonths(current, 1))}
          aria-label={t('Previous month')}
          className="grid size-8 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
        >
          <ChevronLeft className="size-4" aria-hidden />
        </button>
        <p className="text-sm font-semibold text-ink" aria-live="polite">
          {format(viewMonth, 'MMMM yyyy', { locale: dateLocale })}
        </p>
        <button
          type="button"
          onClick={() => setViewMonth((current) => addMonths(current, 1))}
          aria-label={t('Next month')}
          className="grid size-8 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
        >
          <ChevronRight className="size-4" aria-hidden />
        </button>
      </div>

      <div className="mt-2 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-ink-muted">
        {WEEKDAYS.map((weekday) => (
          <span key={weekday}>{t(weekday)}</span>
        ))}
      </div>

      <div className="mt-1 grid grid-cols-7 gap-1">
        {days.map((day) => {
          const inMonth = isSameMonth(day, viewMonth)
          const today = isToday(day)
          return (
            <button
              key={day.toISOString()}
              type="button"
              onClick={() => goToDate(day)}
              aria-label={format(day, 'PPPP', { locale: dateLocale })}
              className={cn(
                'grid size-8 place-items-center rounded-lg text-sm transition-colors',
                inMonth
                  ? 'text-ink hover:bg-brand-soft hover:text-brand-dark'
                  : 'text-ink-muted/40 hover:bg-canvas',
                today && 'bg-brand font-semibold text-white hover:bg-brand-dark hover:text-white',
              )}
            >
              {format(day, 'd')}
            </button>
          )
        })}
      </div>

      <div className="mt-2 flex items-center justify-between border-t border-line pt-2">
        <button
          type="button"
          onClick={() => goToDate(new Date())}
          className="rounded-lg px-2 py-1 text-xs font-semibold text-brand-dark transition-colors hover:bg-brand-soft"
        >
          {t('Today')}
        </button>
        <button
          type="button"
          onClick={goToAppointments}
          className="rounded-lg px-2 py-1 text-xs font-semibold text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
        >
          {t('All appointments')}
        </button>
      </div>
    </Dropdown>
  )
}
