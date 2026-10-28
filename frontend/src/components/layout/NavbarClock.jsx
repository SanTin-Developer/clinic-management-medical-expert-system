import { useEffect, useMemo, useState } from 'react'
import { Moon, Sun } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useLanguage } from '../../hooks/useLanguage.js'

const TIME_FORMAT = new Intl.DateTimeFormat('en-US', {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hour12: true,
})

function TimeCell({ value, accent = false, tick = false }) {
  return (
    <span
      className={cn(
        'flex min-w-7 items-center justify-center rounded-md border px-1.5 py-1 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]',
        accent
          ? 'border-sky-400/30 bg-sky-950 shadow-[inset_0_1px_0_rgba(255,255,255,0.08),0_0_14px_-4px_rgba(56,189,248,0.8)]'
          : 'border-white/10 bg-slate-950',
      )}
    >
      <span
        key={value}
        className={cn(
          'bg-gradient-to-b bg-clip-text font-mono text-sm font-bold leading-none tabular-nums tracking-wider text-transparent',
          accent ? 'from-sky-200 to-sky-400' : 'from-white to-slate-300',
          tick && 'animate-clock-tick',
        )}
      >
        {value}
      </span>
    </span>
  )
}

function PeriodCell({ period, isDaytime }) {
  return (
    <span
      className={cn(
        'flex min-w-7 items-center justify-center rounded-md border px-1.5 py-1 shadow-[inset_0_1px_0_rgba(255,255,255,0.08)]',
        isDaytime
          ? 'border-amber-400/30 bg-amber-950/80'
          : 'border-sky-400/30 bg-sky-950',
      )}
    >
      <span
        key={period}
        className={cn(
          'animate-clock-tick font-mono text-[10px] font-bold uppercase leading-none tracking-widest',
          isDaytime ? 'text-amber-300' : 'text-sky-300',
        )}
      >
        {period}
      </span>
    </span>
  )
}

function TimeSeparator() {
  return (
    <span className="flex flex-col items-center gap-1" aria-hidden="true">
      <span className="size-1 animate-pulse rounded-full bg-brand shadow-[0_0_6px_rgba(37,99,235,0.9)]" />
      <span className="size-1 animate-pulse rounded-full bg-brand shadow-[0_0_6px_rgba(37,99,235,0.9)] [animation-delay:150ms]" />
    </span>
  )
}

export default function NavbarClock() {
  const { locale, t } = useLanguage()
  const [now, setNow] = useState(() => new Date())
  const dateFormat = useMemo(
    () =>
      new Intl.DateTimeFormat(locale, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
      }),
    [locale],
  )

  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 1000)
    return () => window.clearInterval(timer)
  }, [])

  const parts = TIME_FORMAT.formatToParts(now)
  const part = (type) => parts.find((entry) => entry.type === type)?.value ?? ''
  const hours = part('hour')
  const minutes = part('minute')
  const seconds = part('second')
  const period = part('dayPeriod')
  const timeLabel = `${hours}:${minutes}:${seconds} ${period}`

  const isDaytime = now.getHours() >= 6 && now.getHours() < 18
  const PeriodIcon = isDaytime ? Sun : Moon

  return (
    <div
      className="hidden select-none items-center gap-2 rounded-xl border border-brand/15 bg-gradient-to-br from-brand-faint/80 via-surface to-surface px-2 py-1 shadow-[0_10px_30px_-18px_rgba(37,99,235,0.9)] ring-1 ring-inset ring-white/60 dark:border-brand/25 dark:from-brand-faint dark:via-surface dark:to-surface dark:ring-white/5 lg:flex"
      title={t('{date} · Local time', { date: dateFormat.format(now) })}
    >
      <span
        className={cn(
          'relative grid size-7 shrink-0 place-items-center rounded-lg text-white',
          isDaytime
            ? 'bg-gradient-to-br from-amber-400 to-orange-500 shadow-[0_0_18px_-4px_rgba(245,158,11,0.9)]'
            : 'bg-gradient-to-br from-sky-500 to-brand-darker shadow-[0_0_18px_-4px_rgba(56,189,248,0.85)]',
        )}
      >
        <PeriodIcon className="size-3.5" aria-hidden />
        <span className="absolute inset-0 rounded-lg ring-1 ring-inset ring-white/20" aria-hidden="true" />
      </span>

      <time
        dateTime={now.toISOString()}
        aria-label={t('Current time {time}', { time: timeLabel })}
        className="flex items-center gap-1"
      >
        <TimeCell value={hours} />
        <TimeSeparator />
        <TimeCell value={minutes} />
        <TimeSeparator />
        <TimeCell value={seconds} accent tick />
        <PeriodCell period={period} isDaytime={isDaytime} />
      </time>
    </div>
  )
}
