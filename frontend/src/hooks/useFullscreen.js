import { useCallback, useEffect, useState } from 'react'

function fullscreenElement() {
  return document.fullscreenElement ?? document.webkitFullscreenElement ?? null
}

/**
 * Small wrapper around the Fullscreen API. `toggle(element)` enters fullscreen
 * on the given element (or the document) or exits when already active. All
 * browser errors are surfaced as a friendly message instead of throwing.
 */
export function useFullscreen() {
  const [isFullscreen, setIsFullscreen] = useState(() => Boolean(fullscreenElement()))
  const [error, setError] = useState('')

  useEffect(() => {
    const onChange = () => setIsFullscreen(Boolean(fullscreenElement()))
    document.addEventListener('fullscreenchange', onChange)
    document.addEventListener('webkitfullscreenchange', onChange)
    return () => {
      document.removeEventListener('fullscreenchange', onChange)
      document.removeEventListener('webkitfullscreenchange', onChange)
    }
  }, [])

  const enter = useCallback(async (element) => {
    const target = element ?? document.documentElement
    try {
      if (target.requestFullscreen) {
        await target.requestFullscreen()
      } else if (target.webkitRequestFullscreen) {
        target.webkitRequestFullscreen()
      } else {
        setError('Fullscreen is not supported in this browser.')
        return false
      }
      setError('')
      return true
    } catch {
      setError('Unable to enter fullscreen. Your browser may have blocked the request.')
      return false
    }
  }, [])

  const exit = useCallback(async () => {
    try {
      if (document.exitFullscreen) {
        await document.exitFullscreen()
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen()
      }
    } catch {
      setError('Unable to exit fullscreen.')
    }
  }, [])

  const toggle = useCallback(
    (element) => (fullscreenElement() ? exit() : enter(element)),
    [enter, exit],
  )

  return { isFullscreen, toggle, enter, exit, error, clearError: () => setError('') }
}
