import { TriangleAlert, PackageOpen } from 'lucide-react'
import Button from './Button.jsx'

export function EmptyState({ title = 'Nothing here yet', message, action, icon: Icon }) {
  return (
    <div className="flex flex-col items-center gap-2 py-12 text-center">
      <span className="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand">
        {Icon ? <Icon className="size-6" aria-hidden /> : <PackageOpen className="size-6" aria-hidden />}
      </span>
      <h3 className="text-base font-semibold text-ink">{title}</h3>
      {message ? <p className="max-w-md text-sm text-ink-muted">{message}</p> : null}
      {action ? <div className="mt-2">{action}</div> : null}
    </div>
  )
}

export function ErrorState({ message = 'Something went wrong while loading this content.', onRetry, title = 'Something went wrong', compact = false }) {
  return (
    <div
      className={`flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface text-center ${compact ? 'p-6' : 'py-12'}`}
    >
      <span className="grid size-12 place-items-center rounded-2xl bg-red-50 text-red-500">
        <TriangleAlert className="size-6" aria-hidden />
      </span>
      <h3 className="text-base font-semibold text-ink">{title}</h3>
      <p className="max-w-md text-sm text-ink-muted">{message}</p>
      {onRetry ? (
        <Button variant="outline" size="sm" onClick={onRetry}>
          Try again
        </Button>
      ) : null}
    </div>
  )
}

export function ForbiddenState({ message = 'You do not have permission to access this page.' }) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface py-12 text-center">
      <span className="grid size-12 place-items-center rounded-2xl bg-amber-100 text-amber-600">
        <TriangleAlert className="size-6" aria-hidden />
      </span>
      <h3 className="text-base font-semibold text-ink">Access denied</h3>
      <p className="max-w-md text-sm text-ink-muted">{message}</p>
    </div>
  )
}