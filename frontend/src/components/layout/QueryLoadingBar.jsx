import { useEffect, useRef, useState } from 'react'
import { useIsFetching, useIsMutating } from '@tanstack/react-query'

/**
 * Slim branded progress bar shown whenever React Query is fetching or
 * mutating. Gives every page and tab the same loading feel, including
 * background refetches where cached data is still on screen.
 */
export default function QueryLoadingBar() {
  // Only count first loads (queries with no data yet) so background polling
  // and silent refetches do not make the bar flash every minute.
  const fetching = useIsFetching({
    predicate: (query) => query.state.status === 'pending',
  })
  const mutating = useIsMutating()
  const loading = fetching + mutating > 0

  const [visible, setVisible] = useState(false)
  const [progress, setProgress] = useState(0)
  const wasLoading = useRef(false)

  useEffect(() => {
    if (loading) {
      wasLoading.current = true

      const start = setTimeout(() => {
        setVisible(true)
        setProgress((current) => (current > 0 ? Math.min(current, 90) : 12))
      }, 120)

      const tick = setInterval(() => {
        setProgress((current) =>
          current >= 90 ? 90 : current + Math.max(1, (90 - current) * 0.1),
        )
      }, 180)

      return () => {
        clearTimeout(start)
        clearInterval(tick)
      }
    }

    if (!wasLoading.current) return undefined

    wasLoading.current = false
    setProgress(100)
    const hide = setTimeout(() => {
      setVisible(false)
      setProgress(0)
    }, 350)
    return () => clearTimeout(hide)
  }, [loading])

  if (!visible) return null

  return (
    <div aria-hidden className="pointer-events-none fixed inset-x-0 top-0 z-[120] h-0.5">
      <div
        className="h-full bg-gradient-to-r from-brand/0 via-brand to-brand/0 shadow-[0_0_12px_rgba(37,99,235,0.55)] transition-[width] duration-300 ease-out"
        style={{ width: `${progress}%` }}
      />
    </div>
  )
}
