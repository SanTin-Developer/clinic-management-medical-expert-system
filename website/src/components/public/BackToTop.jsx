import { useEffect, useState } from 'react'
import { ArrowUp } from 'lucide-react'
import { cn } from '../../utils/cn.js'

/** Show the button once the visitor has scrolled meaningfully down the page. */
const SHOW_AFTER_PX = 360

/**
 * Floating "back to top" control in the bottom-right corner. Sits above the
 * phone tab bar (and behind the transient install sheet), fades in after the
 * first ~360px of scrolling and smooth-scrolls to the top — instantly for
 * visitors who prefer reduced motion.
 */
export default function BackToTop() {
  const [visible, setVisible] = useState(false)

  useEffect(() => {
    const onScroll = () => setVisible(window.scrollY > SHOW_AFTER_PX)
    const frame = requestAnimationFrame(onScroll)
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => {
      cancelAnimationFrame(frame)
      window.removeEventListener('scroll', onScroll)
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
      className={cn(
        'fixed right-4 z-40 grid size-11 place-items-center rounded-full bg-accent text-white shadow-[0_14px_30px_-10px_rgba(76,175,80,0.75)] transition duration-300 hover:bg-accent-dark active:scale-90',
        'bottom-[calc(5.25rem_+_env(safe-area-inset-bottom))] md:right-6 md:bottom-6',
        visible ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-3 opacity-0',
      )}
    >
      <ArrowUp className="size-5" aria-hidden />
    </button>
  )
}
