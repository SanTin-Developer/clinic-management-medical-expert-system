import { useEffect, useState } from 'react'
import { ArrowUp } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { useOverlayOpen } from '../../hooks/useOverlay.js'
import { useLanguage } from '../../hooks/useLanguage.js'

/** Show the button once the admin has scrolled meaningfully inside the main pane. */
const SHOW_AFTER_PX = 320

/**
 * Floating "back to top" control for the admin app, mirroring the public
 * website's button.
 *
 * The admin layout scrolls inside <main> rather than the window, so this reads
 * the element passed through `scrollRef` and scrolls that container instead of
 * the document. It stays hidden (and unfocusable) until there is somewhere to
 * scroll back to, keeps a visible focus ring, and uses instant scrolling when
 * the user prefers reduced motion.
 *
 * It also steps aside whenever an overlay (dialog, sheet) is open so it never
 * floats on top of a modal or its confirmation buttons.
 */
export default function BackToTop({ scrollRef }) {
  const { t } = useLanguage()
  const [scrolled, setScrolled] = useState(false)
  const overlayOpen = useOverlayOpen()
  const visible = scrolled && !overlayOpen

  useEffect(() => {
    const target = scrollRef?.current
    const getTop = () => (target ? target.scrollTop : window.scrollY)
    const onChange = () => setScrolled(getTop() > SHOW_AFTER_PX)

    const frame = requestAnimationFrame(onChange)
    const source = target ?? window
    source.addEventListener('scroll', onChange, { passive: true })
    window.addEventListener('resize', onChange)

    return () => {
      cancelAnimationFrame(frame)
      source.removeEventListener('scroll', onChange)
      window.removeEventListener('resize', onChange)
    }
  }, [scrollRef])

  const scrollToTop = () => {
    const target = scrollRef?.current
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    const behavior = reduce ? 'instant' : 'smooth'

    if (target) {
      target.scrollTo({ top: 0, left: 0, behavior })
      return
    }

    window.scrollTo({ top: 0, left: 0, behavior })
  }

  return (
    <button
      type="button"
      onClick={scrollToTop}
      aria-label={t('Back to top')}
      aria-hidden={!visible}
      tabIndex={visible ? 0 : -1}
      className={cn(
        'fixed z-30 grid size-11 place-items-center rounded-full bg-brand text-white shadow-[0_14px_30px_-10px_rgba(37,99,235,0.75)] transition duration-300 hover:bg-brand-dark active:scale-90 focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:outline-none sm:size-12',
        // Sit above the phone tab bar instead of behind it.
        'right-[max(1rem,env(safe-area-inset-right))] bottom-[calc(4.5rem+env(safe-area-inset-bottom))] sm:right-6 sm:bottom-6',
        visible ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0',
      )}
    >
      <ArrowUp className="size-5" aria-hidden />
    </button>
  )
}
