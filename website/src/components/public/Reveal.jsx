import { useEffect, useRef, useState } from 'react'
import { cn } from '../../utils/cn.js'

/**
 * Tracks the user's reduced-motion preference so animations can opt out.
 */
export function usePrefersReducedMotion() {
  const [reduced, setReduced] = useState(
    () =>
      typeof window !== 'undefined' &&
      typeof window.matchMedia === 'function' &&
      window.matchMedia('(prefers-reduced-motion: reduce)').matches,
  )

  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return undefined
    }

    const query = window.matchMedia('(prefers-reduced-motion: reduce)')
    const update = () => setReduced(query.matches)
    update()
    query.addEventListener('change', update)

    return () => query.removeEventListener('change', update)
  }, [])

  return reduced
}

/**
 * Reports when an element first enters the viewport. Falls back to "visible"
 * when IntersectionObserver is unavailable so content is never hidden.
 */
export function useInView({ threshold = 0.15, rootMargin = '0px 0px -8% 0px', once = true } = {}) {
  const ref = useRef(null)
  const [inView, setInView] = useState(false)

  useEffect(() => {
    const element = ref.current
    if (!element) {
      return undefined
    }

    if (typeof IntersectionObserver === 'undefined') {
      setInView(true)
      return undefined
    }

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            setInView(true)
            if (once) {
              observer.unobserve(entry.target)
            }
          } else if (!once) {
            setInView(false)
          }
        })
      },
      { threshold, rootMargin },
    )

    observer.observe(element)

    return () => observer.disconnect()
  }, [threshold, rootMargin, once])

  return { ref, inView }
}

/**
 * Wraps content in a gentle "rise into view" animation, matching the hero
 * section's entrance. Only transform/opacity are animated, so it stays cheap.
 */
export function Reveal({ as: Tag = 'div', className, delay = 0, children, ...rest }) {
  const { ref, inView } = useInView()
  const style = delay ? { transitionDelay: `${delay}ms` } : undefined

  return (
    <Tag
      ref={ref}
      className={cn('reveal-scroll', inView && 'is-visible', className)}
      style={style}
      {...rest}
    >
      {children}
    </Tag>
  )
}
