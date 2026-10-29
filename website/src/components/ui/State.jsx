import { TriangleAlert, PackageOpen } from 'lucide-react'
import Button from './Button.jsx'
import { useLanguage } from '../../hooks/useLanguage.js'

export function EmptyState({ title, message, action, icon: Icon }) {
  const { t } = useLanguage()
  const heading = title ?? t('Nothing here yet')

  return (
    <div className="flex flex-col items-center gap-2 py-12 text-center">
      <span className="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand">
        {Icon ? <Icon className="size-6" aria-hidden /> : <PackageOpen className="size-6" aria-hidden />}
      </span>
      <h3 className="text-base font-semibold text-ink">{heading}</h3>
      {message ? <p className="max-w-md text-sm text-ink-muted">{message}</p> : null}
      {action ? <div className="mt-2">{action}</div> : null}
    </div>
  )
}

export function ErrorState({ message, onRetry, title, compact = false }) {
  const { t } = useLanguage()
  const heading = title ?? t('Something went wrong')
  const detail = message ?? t('Something went wrong while loading this content.')

  return (
    <div
      className={`flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface text-center ${compact ? 'p-6' : 'py-12'}`}
    >
      <span className="grid size-12 place-items-center rounded-2xl bg-red-50 text-red-500 dark:bg-red-500/15 dark:text-red-400">
        <TriangleAlert className="size-6" aria-hidden />
      </span>
      <h3 className="text-base font-semibold text-ink">{heading}</h3>
      <p className="max-w-md text-sm text-ink-muted">{detail}</p>
      {onRetry ? (
        <Button variant="outline" size="sm" onClick={onRetry}>
          {t('Try again')}
        </Button>
      ) : null}
    </div>
  )
}

export function ForbiddenState({ message = 'You do not have permission to access this page.' }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface py-12 text-center">
      <span className="grid size-12 place-items-center rounded-2xl bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
        <TriangleAlert className="size-6" aria-hidden />
      </span>
      <h3 className="text-base font-semibold text-ink">Access denied</h3>
      <p className="max-w-md text-sm text-ink-muted">{message}</p>
    </div>
  )
}