import { forwardRef, useState } from 'react'
import { Eye, EyeOff } from 'lucide-react'
import { cn } from '../../utils/cn.js'

const baseCls =
  'h-10 w-full rounded-lg border bg-surface px-3 text-sm text-ink transition-colors placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30'

const Input = forwardRef(function Input({ className, invalid, type = 'text', ...props }, ref) {
  if (type === 'password') {
    return <PasswordInput ref={ref} className={className} invalid={invalid} {...props} />
  }

  return (
    <input
      ref={ref}
      type={type}
      aria-invalid={invalid || undefined}
      className={cn(baseCls, invalid ? 'border-red-400' : 'border-line', className)}
      {...props}
    />
  )
})

const PasswordInput = forwardRef(function PasswordInput({ className, invalid, ...props }, ref) {
  const [show, setShow] = useState(false)

  return (
    <div className="relative">
      <input
        ref={ref}
        type={show ? 'text' : 'password'}
        aria-invalid={invalid || undefined}
        className={cn(baseCls, 'pr-10', invalid ? 'border-red-400' : 'border-line', className)}
        {...props}
      />
      <button
        type="button"
        onClick={() => setShow((value) => !value)}
        aria-label={show ? 'Hide password' : 'Show password'}
        className="absolute inset-y-0 right-0 flex items-center pr-3 text-ink-muted transition-colors hover:text-brand focus:outline-none focus-visible:text-brand"
      >
        {show ? <EyeOff size={18} aria-hidden="true" /> : <Eye size={18} aria-hidden="true" />}
      </button>
    </div>
  )
})

export default Input