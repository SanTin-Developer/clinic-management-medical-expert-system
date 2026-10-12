import { forwardRef } from 'react'
import { cn } from '../../utils/cn.js'

const Textarea = forwardRef(function Textarea({ className, invalid, ...props }, ref) {
  return (
    <textarea
      ref={ref}
      aria-invalid={invalid || undefined}
      className={cn(
        'w-full rounded-lg border bg-surface px-3 py-2 text-sm text-ink transition-colors placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30',
        invalid ? 'border-red-400' : 'border-line',
        className,
      )}
      {...props}
    />
  )
})

export default Textarea