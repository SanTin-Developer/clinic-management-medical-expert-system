import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useAuth } from '../hooks/useAuth.js'
import {
  LANGUAGE_STORAGE_KEY,
  localeForLanguage,
  normalizeLanguage,
  resolveInitialLanguage,
  translate,
} from '../i18n/index.js'
import { setFormatLocale } from '../utils/format.js'
import { LanguageContext } from './LanguageContext.js'

function persistLanguage(language) {
  try {
    localStorage.setItem(LANGUAGE_STORAGE_KEY, language)
  } catch {
    // Storage can be unavailable in private or embedded contexts.
  }
}

export function LanguageProvider({ children }) {
  const { user } = useAuth()
  const [language, setLanguageState] = useState(() => {
    const initial = resolveInitialLanguage()
    setFormatLocale(initial)
    return initial
  })
  const appliedUserLocale = useRef(null)

  const setLanguage = useCallback((value) => {
    const next = normalizeLanguage(value)
    if (!next) return

    setFormatLocale(next)
    setLanguageState(next)
    persistLanguage(next)
  }, [])

  useEffect(() => {
    if (!user) {
      appliedUserLocale.current = null
      return
    }

    const next = normalizeLanguage(user.locale)
    if (!next || next === appliedUserLocale.current) return

    appliedUserLocale.current = next
    setLanguage(next)
  }, [user, setLanguage])

  useEffect(() => {
    document.documentElement.lang = language
    document.documentElement.dataset.language = language
  }, [language])

  const t = useCallback((key, vars) => translate(language, key, vars), [language])

  const value = useMemo(
    () => ({
      language,
      locale: localeForLanguage(language),
      isKhmer: language === 'km',
      setLanguage,
      t,
    }),
    [language, setLanguage, t],
  )

  return <LanguageContext.Provider value={value}>{children}</LanguageContext.Provider>
}
