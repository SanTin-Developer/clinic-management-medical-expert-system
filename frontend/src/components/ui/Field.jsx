import { cn } from '../../utils/cn.js'

export default function Field({
  label,
  htmlFor,
  hint,
  error,
  required,
  children,
  className,
}) {
  return (
    <div className={cn('space-y-1.5', className)}>
      {label ? (
        <label
          htmlFor={htmlFor}
          className="block text-sm font-medium text-ink"
        >
          {label}
          {required ? (
            <span className="ml-0.5 text-brand" aria-hidden>
              *
            </span>
          ) : null}
        </label>
      ) : null}
      {children}
      {hint ? <p className="text-xs text-ink-muted">{hint}</p> : null}
      {error ? (
        <p className="text-xs font-medium text-red-600" role="alert">
          {error}
        </p>
      ) : null}
    </div>
  )
}