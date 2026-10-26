import { useCallback, useEffect, useMemo, useState } from 'react'
import { STORAGE_KEY, ThemeContext } from './ThemeContext.js'

const VALID_MODES = ['light', 'dark', 'system']

function getSystemTheme() {
  if (typeof window === 'undefined') return 'light'
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

function resolveInitialMode() {
  if (typeof window === 'undefined') return 'system'

  const stored = window.localStorage.getItem(STORAGE_KEY)
  if (stored && VALID_MODES.includes(stored)) {
    return stored
  }

  // Migrate legacy values (we used to store the resolved theme)
  if (stored === 'dark' || stored === 'light') {
    return stored
  }

  return 'system'
}

export function ThemeProvider({ children }) {
  const [mode, setMode] = useState(resolveInitialMode)
  const [resolvedTheme, setResolvedTheme] = useState(() => (mode === 'system' ? getSystemTheme() : mode))

  useEffect(() => {
    if (mode !== 'system') {
      setResolvedTheme(mode)
      return undefined
    }

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const updateResolvedTheme = () => {
      setResolvedTheme(mediaQuery.matches ? 'dark' : 'light')
    }

    updateResolvedTheme()
    mediaQuery.addEventListener('change', updateResolvedTheme)

    return () => mediaQuery.removeEventListener('change', updateResolvedTheme)
  }, [mode])

  useEffect(() => {
    const root = document.documentElement
    root.classList.toggle('dark', resolvedTheme === 'dark')
    window.localStorage.setItem(STORAGE_KEY, mode)
  }, [mode, resolvedTheme])

  const toggleTheme = useCallback(() => {
    setMode((current) => {
      if (current === 'light') return 'dark'
      if (current === 'dark') return 'system'
      return 'light'
    })
  }, [])

  const setTheme = useCallback((nextTheme) => {
    if (nextTheme === 'light' || nextTheme === 'dark') {
      setMode(nextTheme)
      return
    }
    if (nextTheme === 'system') {
      setMode('system')
    }
  }, [])

  const value = useMemo(
    () => ({
      mode,
      setMode,
      theme: resolvedTheme,
      resolvedTheme,
      isDark: resolvedTheme === 'dark',
      toggleTheme,
      setTheme,
    }),
    [mode, resolvedTheme, toggleTheme, setTheme],
  )

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>
}
