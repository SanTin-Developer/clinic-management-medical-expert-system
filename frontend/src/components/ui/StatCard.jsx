import { Link } from 'react-router-dom'
import { ArrowUpRight } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { Shimmer } from './Skeleton.jsx'

const TONES = {
  brand: 'bg-brand-soft text-brand-dark',
  green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
  amber: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  red: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
  navy: 'bg-blue-900/10 text-ink dark:bg-blue-400/15',
}

export default function StatCard({
  label,
  value,
  icon: Icon,
  tone = 'brand',
  sub,
  loading,
  to,
  linkLabel,
}) {
  const content = (
    <div className="flex items-start justify-between gap-3">
      <div className="min-w-0">
        <p className="flex items-center gap-1 text-sm font-medium text-ink-muted">
          {label}
          {to ? (
            <ArrowUpRight
              className="size-3.5 shrink-0 text-ink-muted/70 transition-all duration-200 group-hover:translate-x-0.5 group-hover:text-brand group-focus-visible:translate-x-0.5 group-focus-visible:text-brand"
              aria-hidden
            />
          ) : null}
        </p>
        {loading ? (
          <Shimmer className="mt-2 h-7 w-16" />
        ) : (
          <p className="mt-1 truncate text-xl font-bold text-ink sm:text-2xl">
            {value}
          </p>
        )}
        {sub ? <p className="mt-1 text-xs text-ink-muted">{sub}</p> : null}
      </div>
      {Icon ? (
        <span
          className={cn(
            // Two stat cards share a phone row, so the icon would squeeze the
            // label; it returns from sm upwards.
            'hidden size-10 shrink-0 place-items-center rounded-xl transition-transform duration-200 sm:grid',
            TONES[tone],
            to && 'group-hover:scale-105 group-focus-visible:scale-105',
          )}
        >
          <Icon className="size-5" aria-hidden />
        </span>
      ) : null}
    </div>
  )

  const className = cn(
    'rounded-2xl border border-line bg-surface p-4 shadow-[var(--shadow-card)] sm:p-5',
    to &&
      'group block h-full cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-[var(--shadow-card-hover)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30',
  )

  if (to) {
    const accessibleLabel = `${label}: ${loading ? 'Loading' : value}${sub ? `. ${sub}` : ''}. ${
      linkLabel ?? `View ${label}`
    }`

    return (
      <Link to={to} aria-label={accessibleLabel} className={className}>
        {content}
      </Link>
    )
  }

  return <div className={className}>{content}</div>
}
