import { useId } from 'react'
import { cn } from '../../utils/cn.js'

export default function Toggle({ label, checked, onChange, disabled, className, hint }) {
  const buttonId = useId()
  return (
    <div className={cn('flex items-center justify-between gap-3', className)}>
      <span className="flex flex-col">
        <label htmlFor={buttonId} className="text-sm font-medium text-ink cursor-pointer">
          {label}
        </label>
        {hint ? <span className="text-xs text-ink-muted">{hint}</span> : null}
      </span>
      <button
        id={buttonId}
        type="button"
        role="switch"
        aria-checked={Boolean(checked)}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50',
          checked ? 'bg-brand' : 'bg-line',
        )}
      >
        <span
          className={cn(
            'inline-block size-4 translate-x-0 rounded-full bg-white shadow transition-transform',
            checked && 'translate-x-6',
          )}
        />
      </button>
    </div>
  )
}