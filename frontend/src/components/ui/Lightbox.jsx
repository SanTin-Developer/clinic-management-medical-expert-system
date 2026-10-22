import { useEffect } from 'react'
import { ChevronLeft, ChevronRight, Download, ExternalLink, X } from 'lucide-react'
import { cn } from '../../utils/cn.js'

/**
 * Full-screen image preview overlay.
 *
 * Opens with a plain <img> so it works for any URL the admin can already see
 * (Cloudinary or local) without fetching anything extra. Supports optional
 * prev/next navigation when the caller passes a list, Escape to close, arrow
 * keys to step through, and locks background scrolling while open.
 *
 * `actions` renders extra controls next to the close button (for example a
 * Delete button on the media library).
 */
export default function Lightbox({
  open,
  onClose,
  src,
  alt = '',
  title,
  description,
  actions,
  hasPrev = false,
  hasNext = false,
  onPrev,
  onNext,
}) {
  useEffect(() => {
    if (!open) return undefined

    const onKey = (event) => {
      if (event.key === 'Escape') {
        onClose?.()
      } else if (event.key === 'ArrowLeft' && hasPrev) {
        onPrev?.()
      } else if (event.key === 'ArrowRight' && hasNext) {
        onNext?.()
      }
    }

    document.addEventListener('keydown', onKey)
    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    return () => {
      document.removeEventListener('keydown', onKey)
      document.body.style.overflow = previous
    }
  }, [open, onClose, hasPrev, hasNext, onPrev, onNext])

  if (!open) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={title || 'Image preview'}
      className="fixed inset-0 z-[60] flex flex-col bg-slate-950/85 backdrop-blur-sm"
    >
      <header className="flex shrink-0 items-start justify-between gap-4 p-4 text-white sm:p-5">
        <div className="min-w-0">
          {title ? <h2 className="truncate text-base font-semibold">{title}</h2> : null}
          {description ? (
            <p className="mt-0.5 truncate text-xs text-white/70">{description}</p>
          ) : null}
        </div>
        <div className="flex shrink-0 items-center gap-1.5">
          {actions}
          {src ? (
            <>
              <a
                href={src}
                download
                aria-label="Download image"
                className="grid size-9 place-items-center rounded-lg bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
              >
                <Download className="size-4" aria-hidden />
              </a>
              <a
                href={src}
                target="_blank"
                rel="noreferrer"
                aria-label="Open original in a new tab"
                className="grid size-9 place-items-center rounded-lg bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
              >
                <ExternalLink className="size-4" aria-hidden />
              </a>
            </>
          ) : null}
          <button
            type="button"
            onClick={onClose}
            aria-label="Close preview"
            className="grid size-9 place-items-center rounded-lg bg-white/10 text-white transition-colors hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
          >
            <X className="size-5" aria-hidden />
          </button>
        </div>
      </header>

      <div className="relative flex min-h-0 flex-1 items-center justify-center px-4 pb-6 sm:px-16">
        {hasPrev ? (
          <button
            type="button"
            onClick={onPrev}
            aria-label="Previous image"
            className={cn(
              'absolute left-2 z-10 grid size-11 place-items-center rounded-full bg-white/10 text-white transition-colors',
              'hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 sm:left-4',
            )}
          >
            <ChevronLeft className="size-6" aria-hidden />
          </button>
        ) : null}

        <img
          src={src}
          alt={alt}
          onClick={(event) => event.stopPropagation()}
          className="max-h-full max-w-full rounded-xl object-contain shadow-2xl"
        />

        {hasNext ? (
          <button
            type="button"
            onClick={onNext}
            aria-label="Next image"
            className={cn(
              'absolute right-2 z-10 grid size-11 place-items-center rounded-full bg-white/10 text-white transition-colors',
              'hover:bg-white/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/70 sm:right-4',
            )}
          >
            <ChevronRight className="size-6" aria-hidden />
          </button>
        ) : null}
      </div>

      <button
        type="button"
        aria-label="Close preview"
        onClick={onClose}
        className="absolute inset-0 -z-10 cursor-default"
        tabIndex={-1}
      />
    </div>
  )
}
