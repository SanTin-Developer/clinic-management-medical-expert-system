import { Loader2 } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import PageHeader from './PageHeader.jsx'
import Card from './Card.jsx'

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

export function Shimmer({ className }) {
  return <div aria-hidden className={cn('skeleton-shimmer rounded-lg', className)} />
}

export function Skeleton({ className }) {
  return <Shimmer className={className} />
}

/** Branded loading pill used at the top of skeleton pages. */
export function LoadingPill({ label, className }) {
  const { t } = useLanguage()

  return (
    <span
      className={cn(
        'inline-flex items-center gap-2 rounded-full border border-brand/20 bg-brand-faint px-3.5 py-1.5 text-xs font-semibold text-brand-dark shadow-sm',
        className,
      )}
    >
      <Loader2 className="size-3.5 animate-spin text-brand" aria-hidden />
      {label ?? t('Loading…')}
    </span>
  )
}

/** Thin indeterminate progress bar shared by page-level loading states. */
export function LoadingBar({ className }) {
  return (
    <div aria-hidden className={cn('h-1 overflow-hidden rounded-full bg-brand-soft/60', className)}>
      <div className="loading-bar h-full w-1/3 rounded-full bg-gradient-to-r from-brand/0 via-brand to-brand/0" />
    </div>
  )
}

export function CardSkeleton({ rows = 4, className }) {
  return (
    <div
      aria-hidden
      className={cn(
        'rounded-2xl border border-line bg-surface p-5 shadow-[var(--shadow-card)]',
        className,
      )}
    >
      <div className="flex items-center gap-3">
        <Shimmer className="size-9 shrink-0 rounded-xl" />
        <div className="min-w-0 flex-1 space-y-2">
          <Shimmer className="h-4 w-40 max-w-[70%]" />
          <Shimmer className="h-2.5 w-28 max-w-[45%]" />
        </div>
        <div className="hidden items-center gap-2 sm:flex">
          <span className="size-1.5 animate-pulse rounded-full bg-brand" />
          <Shimmer className="h-3 w-14" />
        </div>
      </div>
      <div className="mt-5 space-y-3">
        {Array.from({ length: rows }).map((_, i) => (
          <div key={i} className="flex items-center gap-4">
            <Shimmer className={cn('h-4', i % 3 === 0 ? 'w-1/2' : 'flex-1')} />
            <Shimmer className="h-4 w-20 shrink-0" />
          </div>
        ))}
      </div>
    </div>
  )
}

export function RowSkeleton({ rows = 5, cols = 4 }) {
  return (
    <div className="space-y-3" aria-hidden>
      {Array.from({ length: rows }).map((_, r) => (
        <div
          key={r}
          className="animate-fade-in-up flex items-center gap-4 rounded-xl border border-line/80 bg-surface px-4 py-3.5 md:gap-6"
          style={{ animationDelay: `${r * 40}ms` }}
        >
          <Shimmer className="size-8 shrink-0 rounded-full" />
          {Array.from({ length: cols }).map((__, c) => (
            <Shimmer
              key={c}
              className={cn(
                'h-4',
                c === 0 ? 'w-28 shrink-0 md:w-40' : 'flex-1',
                c > 2 && 'hidden sm:block',
              )}
            />
          ))}
        </div>
      ))}
    </div>
  )
}

const STAT_GRID = { gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))' }

/**
 * Full page-shaped skeleton used while a page's primary data loads.
 * Mirrors the billing loading design: branded pill, progress bar,
 * staggered cards, and shimmering table rows.
 */
export function PageSkeleton({
  title = 'Loading…',
  description = 'Gathering the latest information…',
  stats = 0,
  cards = 1,
  rows = 5,
}) {
  const statDelay = stats * 55

  return (
    <div className="space-y-6" role="status" aria-live="polite" aria-label={title}>
      <PageHeader
        title={title}
        description={description}
        actions={<LoadingPill label="Loading" />}
      />

      <LoadingBar className="-mt-3" />

      {stats > 0 ? (
        <div className="grid gap-4" style={STAT_GRID}>
          {Array.from({ length: stats }).map((_, index) => (
            <div
              key={index}
              className="animate-fade-in-up"
              style={{ animationDelay: `${index * 55}ms` }}
            >
              <Card className="h-full p-4">
                <div className="flex items-center gap-2">
                  <Shimmer className="size-7 shrink-0 rounded-lg" />
                  <Shimmer className="h-3.5 w-20" />
                </div>
                <Shimmer className="mt-3 h-7 w-24" />
                <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-line/60">
                  <div className="h-full w-2/3 animate-pulse rounded-full bg-brand/30" />
                </div>
              </Card>
            </div>
          ))}
        </div>
      ) : null}

      {Array.from({ length: cards }).map((_, index) => (
        <div
          key={index}
          className="animate-fade-in-up"
          style={{ animationDelay: `${statDelay + index * 60 + 120}ms` }}
        >
          <Card className="overflow-hidden p-0" bodyClassName="p-0">
            <div className="flex items-center gap-3 border-b border-line px-5 py-4">
              <Shimmer className="size-8 shrink-0 rounded-lg" />
              <div className="min-w-0 flex-1 space-y-2">
                <Shimmer className="h-4 w-40 max-w-[60%]" />
                <Shimmer className="h-2.5 w-28 max-w-[40%]" />
              </div>
              <Shimmer className="hidden h-8 w-24 rounded-lg sm:block" />
            </div>
            <div className="divide-y divide-line/70">
              {Array.from({ length: rows }).map((_, r) => (
                <div key={r} className="flex items-center gap-4 px-5 py-4">
                  {Array.from({ length: 7 }).map((__, c) => (
                    <Shimmer
                      key={c}
                      className={cn(
                        'h-4',
                        c === 0 ? 'w-28 shrink-0 md:w-40' : 'flex-1',
                        c > 4 && 'hidden lg:block',
                        c === 6 && 'hidden xl:block',
                      )}
                    />
                  ))}
                </div>
              ))}
            </div>
          </Card>
        </div>
      ))}
    </div>
  )
}
