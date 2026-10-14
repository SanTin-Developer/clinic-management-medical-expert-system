import { useEffect, useRef, useState } from 'react'

/**
 * Observes an element's rendered width so SVG charts can be drawn crisp at
 * real pixel dimensions (rather than scaling a fixed viewBox).
 */
export function useElementWidth(fallback = 640) {
  const ref = useRef(null)
  const [width, setWidth] = useState(fallback)

  useEffect(() => {
    const el = ref.current
    if (!el || typeof ResizeObserver === 'undefined') return undefined

    const observer = new ResizeObserver((entries) => {
      const next = entries[0]?.contentRect?.width
      if (!next) return
      setWidth((prev) => (Math.abs(prev - next) > 0.5 ? next : prev))
    })

    observer.observe(el)
    const initial = el.getBoundingClientRect().width
    if (initial) setWidth(initial)

    return () => observer.disconnect()
  }, [])

  return [ref, width]
}
