import { useContext, useEffect } from 'react'
import { OverlayContext } from '../context/OverlayContext.js'

/** Number of currently open overlays (dialogs, sheets, dropdowns). */
export function useOverlayCount() {
  return useContext(OverlayContext).count
}

/** Returns true when at least one overlay is open. */
export function useOverlayOpen() {
  return useContext(OverlayContext).count > 0
}

/**
 * Keeps the overlay count in sync with a boolean flag for as long as it stays
 * true. No-ops outside an OverlayProvider so components stay usable standalone.
 */
export function useOverlayFlag(open = false) {
  const { register } = useContext(OverlayContext)

  useEffect(() => {
    if (!open) return undefined

    return register()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])
}
