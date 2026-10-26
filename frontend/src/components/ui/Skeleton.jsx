import { Loader2 } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export function Spinner({ label, className }) {
  const { t } = useLanguage()

  return (
    <div
      role="status"
      className={cn('inline-flex items-center gap-2 text-ink-muted', className)}
    >
      <Loader2 className="size-5 animate-spin text-brand" aria-hidden />
      {label ? <span className="text-sm">{label}</span> : <span className="sr-only">{t('Loading…')}</span>}
    </div>
  )
}

export function Skeleton({ className }) {
  return <div className={cn('animate-pulse rounded-lg bg-brand-soft/70', className)} />
}

export function CardSkeleton({ rows = 4 }) {
  return (
    <div className="rounded-2xl border border-line bg-surface p-5 shadow-[var(--shadow-card)]">
      <Skeleton className="h-4 w-36" />
      <div className="mt-5 space-y-3">
        {Array.from({ length: rows }).map((_, i) => (
          <div key={i} className="flex items-center gap-4">
            <Skeleton className="h-4 flex-1" />
            <Skeleton className="h-4 w-20" />
          </div>
        ))}
      </div>
    </div>
  )
}

export function RowSkeleton({ rows = 5, cols = 4 }) {
  return (
    <div className="space-y-3">
      {Array.from({ length: rows }).map((_, r) => (
        <div key={r} className="flex items-center gap-6 rounded-lg border border-line bg-surface px-4 py-3">
          {Array.from({ length: cols }).map((__, c) => (
            <Skeleton key={c} className={cn('h-4', c === 0 ? 'w-40' : 'flex-1')} />
          ))}
        </div>
      ))}
    </div>
  )
}