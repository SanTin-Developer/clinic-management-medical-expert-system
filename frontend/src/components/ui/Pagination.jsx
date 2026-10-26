import { ChevronLeft, ChevronRight } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useLanguage } from '../../hooks/useLanguage.js'

function pageList(page, lastPage) {
  const pages = new Set()
  pages.add(1)
  pages.add(lastPage)
  for (let p = page - 1; p <= page + 1; p += 1) {
    if (p >= 1 && p <= lastPage) pages.add(p)
  }
  return [...pages].sort((a, b) => a - b)
}

export default function Pagination({ page, lastPage, total, onChange }) {
  const { t } = useLanguage()

  if (!lastPage || lastPage <= 1) return null

  const pages = pageList(page, lastPage)

  return (
    <nav
      className="flex items-center justify-between gap-3 border-t border-line px-5 py-3"
      aria-label={t('Pagination')}
    >
      <p className="text-sm text-ink-muted">
        {total !== undefined ? t('{count} records', { count: total }) : t('Page')} ·{' '}
        {t('page {page} of {lastPage}', { page, lastPage })}
      </p>
      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={() => onChange(page - 1)}
          disabled={page <= 1}
          aria-label={t('Previous page')}
          className="grid size-8 place-items-center rounded-lg border border-line bg-surface text-ink-muted transition-colors hover:bg-canvas disabled:opacity-40"
        >
          <ChevronLeft className="size-4" aria-hidden />
        </button>
        {pages.map((p, i) => {
          const gap = i > 0 && pages[i - 1] !== p - 1
          const active = p === page
          return (
            <span key={p} className="flex items-center gap-1">
              {gap ? <span className="px-1 text-ink-muted">…</span> : null}
              <button
                type="button"
                onClick={() => onChange(p)}
                aria-current={active ? 'page' : undefined}
                className={cn(
                  'grid size-8 place-items-center rounded-lg text-sm font-medium transition-colors',
                  active
                    ? 'bg-brand text-white'
                    : 'border border-line bg-surface text-ink-muted hover:bg-canvas',
                )}
              >
                {p}
              </button>
            </span>
          )
        })}
        <button
          type="button"
          onClick={() => onChange(page + 1)}
          disabled={page >= lastPage}
          aria-label={t('Next page')}
          className="grid size-8 place-items-center rounded-lg border border-line bg-surface text-ink-muted transition-colors hover:bg-canvas disabled:opacity-40"
        >
          <ChevronRight className="size-4" aria-hidden />
        </button>
      </div>
    </nav>
  )
}