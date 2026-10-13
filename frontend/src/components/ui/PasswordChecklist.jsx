import { Check, X } from 'lucide-react'
import { passwordChecks } from '../../utils/password.js'
import { cn } from '../../utils/cn.js'

export default function PasswordChecklist({ value, className, id }) {
  const checks = passwordChecks(value)

  return (
    <ul id={id} className={cn('grid grid-cols-1 gap-1 sm:grid-cols-2', className)} aria-label="Password requirements">
      {checks.map((check) => (
        <li
          key={check.key}
          className={cn(
            'flex items-center gap-1.5 text-xs transition-colors',
            check.met ? 'text-emerald-600 dark:text-emerald-400' : 'text-ink-muted',
          )}
        >
          <span
            className={cn(
              'grid size-4 shrink-0 place-items-center rounded-full',
              check.met
                ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-300'
                : 'bg-slate-100 text-ink-muted dark:bg-slate-700/50',
            )}
          >
            {check.met ? <Check size={11} aria-hidden /> : <X size={11} aria-hidden />}
          </span>
          {check.label}
        </li>
      ))}
    </ul>
  )
}
