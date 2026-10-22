import { useCallback, useMemo, useState } from 'react'
import { OverlayContext } from './OverlayContext.js'

/**
 * Tracks how many "overlay" surfaces (dialogs, sheets, dropdowns) are open.
 *
 * Floating page furniture such as the back-to-top button reads this so it can
 * step out of the way while a dialog is up, instead of floating over it.
 */
export function OverlayProvider({ children }) {
  const [count, setCount] = useState(0)

  const register = useCallback(() => {
    setCount((current) => current + 1)

    let released = false
    return () => {
      if (released) return
      released = true
      setCount((current) => Math.max(0, current - 1))
    }
  }, [])

  const value = useMemo(() => ({ count, register }), [count, register])

  return <OverlayContext.Provider value={value}>{children}</OverlayContext.Provider>
}
