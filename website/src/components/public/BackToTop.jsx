import { useEffect, useState } from 'react'
import { ArrowUp } from 'lucide-react'
import { cn } from '../../utils/cn.js'

/** Show the button once the visitor has scrolled meaningfully down the page. */
const SHOW_AFTER_PX = 320

/**
 * Floating "back to top" control in the bottom-right corner, available on every
 * page and every device:
 *
 *  - Phones (<md): floats above the mobile tab bar, plus the home-indicator
 *    safe area so it never sits under a notch or gesture bar.
 *  - Tablets/desktop (md+): a standard corner inset, with the landscape notch
 *    safe area applied to the right edge.
 *  - Hidden (and unfocusable) until there is somewhere to scroll back to, with
 *    a visible focus ring for keyboard/switch access and instant scrolling for
 *    visitors who prefer reduced motion.
 *
 * It deliberately sits behind the transient install sheet (z-45) until that
 * sheet is dismissed.
 */
export default function BackToTop() {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > SHOW_AFTER_PX)
    const frame = requestAnimationFrame(onScroll)
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)
    return () => {
      cancelAnimationFrame(frame)
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
    }
  }, [])

  const scrollToTop = () => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    window.scrollTo({ top: 0, left: 0, behavior: reduce ? 'instant' : 'smooth' })
  }

  return (
    <button
      type="button"
      onClick={scrollToTop}
      aria-label="Back to top"
      aria-hidden={!visible}
      tabIndex={visible ? 0 : -1}
      className={cn(
        'fixed z-40 grid size-11 place-items-center rounded-full bg-accent text-white shadow-[0_14px_30px_-10px_rgba(76,175,80,0.75)] transition duration-300 hover:bg-accent-dark active:scale-90 focus-visible:ring-2 focus-visible:ring-white/80 focus-visible:outline-none sm:size-12',
        'right-[max(1rem,env(safe-area-inset-right))] bottom-[calc(5.25rem_+_env(safe-area-inset-bottom))]',
        'md:right-6 md:bottom-6',
        visible ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0',
      )}
    >
      <ArrowUp className="size-5" aria-hidden />
    </button>
  )
}
