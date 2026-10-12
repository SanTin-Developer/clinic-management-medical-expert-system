import { useEffect } from 'react'
import { X } from 'lucide-react'
import { cn } from '../../utils/cn.js'

const SIZES = {
  sm: 'sm:max-w-sm',
  md: 'sm:max-w-lg',
  lg: 'sm:max-w-2xl',
  xl: 'sm:max-w-4xl',
}

export default function Modal({
  open,
  onClose,
  title,
  description,
  children,
  footer,
  size = 'md',
}) {
  useEffect(() => {
    if (!open) return
    const onKey = (e) => {
      if (e.key === 'Escape') onClose?.()
    }
    document.addEventListener('keydown', onKey)
    document.body.style.overflow = 'hidden'
    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = ''
    }
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
      <div
        className="absolute inset-0 bg-ink/40 backdrop-blur-[2px]"
        onClick={onClose}
        aria-hidden
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title ?? 'Dialog'}
        className={cn(
          'animate-fade-in relative z-10 max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-surface shadow-[var(--shadow-dialog)] sm:rounded-2xl',
          SIZES[size],
        )}
      >
        {title ? (
          <header className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
            <div>
              <h2 className="text-lg font-semibold text-ink">{title}</h2>
              {description ? (
                <p className="mt-0.5 text-sm text-ink-muted">{description}</p>
              ) : null}
            </div>
            <button
              type="button"
              onClick={onClose}
              aria-label="Close dialog"
              className="no-print rounded-lg p-1.5 text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
            >
              <X className="size-5" aria-hidden />
            </button>
          </header>
        ) : null}
        <div className="p-5">{children}</div>
        {footer ? (
          <footer className="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4">
            {footer}
          </footer>
        ) : null}
      </div>
    </div>
  )
}