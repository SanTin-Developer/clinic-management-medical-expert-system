import { forwardRef } from 'react'
import { Loader2 } from 'lucide-react'
import { cva } from 'class-variance-authority'
import { cn } from '../../utils/cn.js'

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 rounded-lg font-semibold whitespace-nowrap transition-colors disabled:opacity-60 disabled:pointer-events-none',
  {
    variants: {
      variant: {
        primary: 'bg-brand text-white hover:bg-brand-dark dark:hover:bg-brand dark:hover:brightness-110',
        secondary: 'bg-brand-soft text-brand-dark hover:bg-brand-faint',
        outline: 'border border-line bg-surface text-ink hover:bg-canvas',
        success: 'bg-emerald-500 text-white hover:bg-emerald-600',
        danger: 'bg-red-500 text-white hover:bg-red-600',
        ghost: 'text-ink-muted hover:bg-canvas hover:text-ink',
      },
      size: {
        sm: 'h-8 px-3 text-xs',
        md: 'h-10 px-4 text-sm',
        lg: 'h-11 px-5 text-sm',
      },
    },
    defaultVariants: {
      variant: 'primary',
      size: 'md',
    },
  },
)

const Button = forwardRef(function Button(
  { className, variant, size, loading, children, disabled, ...props },
  ref,
) {
  return (
    <button
      ref={ref}
      className={cn(buttonVariants({ variant, size }), className)}
      disabled={disabled || loading}
      {...props}
    >
      {loading ? <Loader2 className="size-4 animate-spin" aria-hidden /> : null}
      {children}
    </button>
  )
})

export default Button