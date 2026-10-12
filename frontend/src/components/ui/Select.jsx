import { forwardRef } from 'react'
import { cn } from '../../utils/cn.js'

const Select = forwardRef(function Select({ className, invalid, children, ...props }, ref) {
  return (
    <select
      ref={ref}
      aria-invalid={invalid || undefined}
      className={cn(
        'h-10 w-full appearance-none rounded-lg border bg-surface px-3 text-sm text-ink transition-colors focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30',
        invalid ? 'border-red-400' : 'border-line',
        className,
      )}
      {...props}
    >
      {children}
    </select>
  )
})

export default Select