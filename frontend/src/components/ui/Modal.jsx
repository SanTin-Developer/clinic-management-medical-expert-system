import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { X } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useOverlayFlag } from '../../hooks/useOverlay.js'
import { useScrollLock } from '../../hooks/useScrollLock.js'
import { useLanguage } from '../../hooks/useLanguage.js'

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
  const { t } = useLanguage()
  const bodyRef = useRef(null)
  useOverlayFlag(open)
  useScrollLock(open)

  useEffect(() => {
    if (!open) return
    const onKey = (e) => {
      if (e.key === 'Escape') onClose?.()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [open, onClose])

  // Always open with the form's first field in view. The dialog is mounted
  // fresh on every open, but being explicit keeps this true even if a caller
  // reuses the same node while the page behind is scrolled far down.
  useEffect(() => {
    if (!open) return
    if (bodyRef.current) bodyRef.current.scrollTop = 0
  }, [open])

  if (!open) return null

  // Rendered in a portal on <body> rather than inline in the page. Page
  // containers animate with a filled `transform`, which would otherwise turn
  // them into the containing block for this `fixed` overlay — making the
  // dialog scroll with the page instead of sticking to the viewport.
  return createPortal(
    <div className="fixed inset-0 z-50 flex items-start justify-center px-3 pt-[max(0.75rem,env(safe-area-inset-top))] sm:px-4 sm:pt-6">
      <div
        className="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"
        onClick={onClose}
        aria-hidden
      />
      <div
        role="dialog"
        aria-modal="true"
        aria-label={title ?? t('Dialog')}
        className={cn(
          'animate-in fade-in-0 zoom-in-95 slide-in-from-top-2 relative z-10 flex max-h-[calc(100dvh-1.5rem)] w-full flex-col overflow-hidden rounded-2xl border border-line/80 bg-surface shadow-2xl ring-1 ring-black/5 sm:max-h-[94vh]',
          SIZES[size],
        )}
      >
        {title ? (
          <header className="flex shrink-0 items-start justify-between gap-4 border-b border-line px-4 py-3.5 sm:px-5 sm:py-4">
            <div className="min-w-0">
              <h2 className="text-lg font-semibold text-ink">{title}</h2>
              {description ? (
                <p className="mt-0.5 text-sm text-ink-muted">{description}</p>
              ) : null}
            </div>
            <button
              type="button"
              onClick={onClose}
              aria-label={t('Close dialog')}
              className="no-print -mr-1 grid size-10 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink sm:size-8"
            >
              <X className="size-5" aria-hidden />
            </button>
          </header>
        ) : null}
        <div
          ref={bodyRef}
          className="min-h-0 flex-1 overflow-y-auto overscroll-contain p-4 sm:p-5"
        >
          {children}
        </div>
        {footer ? (
          <footer className="flex shrink-0 flex-wrap justify-end gap-2 border-t border-line bg-surface px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5 sm:py-4 sm:pb-4">
            {footer}
          </footer>
        ) : null}
      </div>
    </div>,
    document.body,
  )
}
