import { cn } from '../../utils/cn.js'

const TONES = {
  brand: 'bg-brand-soft text-brand-dark',
  green: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
  blue: 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
  amber: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
  red: 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300',
  navy: 'bg-blue-900/10 text-ink dark:bg-blue-400/15',
}

export default function StatCard({ label, value, icon: Icon, tone = 'brand', sub, loading }) {
  return (
    <div className="rounded-2xl border border-line bg-surface p-5 shadow-[var(--shadow-card)]">
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-medium text-ink-muted">{label}</p>
          {loading ? (
            <div className="mt-2 h-7 w-16 animate-pulse rounded-md bg-brand-soft/70" />
          ) : (
            <p className="mt-1 truncate text-2xl font-bold text-ink">{value}</p>
          )}
          {sub ? <p className="mt-1 text-xs text-ink-muted">{sub}</p> : null}
        </div>
        {Icon ? (
          <span className={cn('grid size-10 shrink-0 place-items-center rounded-xl', TONES[tone])}>
            <Icon className="size-5" aria-hidden />
          </span>
        ) : null}
      </div>
    </div>
  )
}