import { KM_TRANSLATIONS as MANUAL_TRANSLATIONS } from './translations.js'
import { KM_TRANSLATIONS as GENERATED_TRANSLATIONS } from './translations.generated.js'
import { KM_TRANSLATIONS as OVERRIDE_TRANSLATIONS } from './translations.overrides.js'

// Hand-written and domain-specific entries win over the generated ones so
// wording can be refined without touching the generated file.
export const KM_TRANSLATIONS = {
  ...GENERATED_TRANSLATIONS,
  ...MANUAL_TRANSLATIONS,
  ...OVERRIDE_TRANSLATIONS,
}

export const DEFAULT_LANGUAGE = 'en'
export const LANGUAGE_STORAGE_KEY = 'royalcare.language'

const KHMER_SCRIPT = /[\u1780-\u17ff]/

export function containsKhmer(value) {
  return KHMER_SCRIPT.test(String(value ?? ''))
}

/**
 * Returns the Khmer value for an English source string, or null when the
 * string is not in the dictionary.
 */
export function lookupTranslation(source) {
  if (typeof source !== 'string') return null
  return Object.prototype.hasOwnProperty.call(KM_TRANSLATIONS, source)
    ? KM_TRANSLATIONS[source]
    : null
}

export const LANGUAGES = [
  { value: 'en', label: 'English', nativeLabel: 'English', locale: 'en-US' },
  { value: 'km', label: 'Khmer', nativeLabel: 'ខ្មែរ', locale: 'km-KH' },
]

export function normalizeLanguage(value) {
  const normalized = String(value ?? '').trim().toLowerCase()
  return normalized === 'en' || normalized === 'km' ? normalized : null
}

export function localeForLanguage(language) {
  return normalizeLanguage(language) === 'km' ? 'km-KH' : 'en-US'
}

function interpolate(template, vars) {
  if (!vars) return template

  return template.replace(/\{(\w+)\}/g, (match, name) => {
    const value = vars[name]
    return value === undefined || value === null ? match : String(value)
  })
}

export function translate(language, key, vars) {
  if (key === undefined || key === null) return ''

  const normalized = normalizeLanguage(language) ?? DEFAULT_LANGUAGE
  const source = String(key)
  const template =
    normalized === 'km' && Object.prototype.hasOwnProperty.call(KM_TRANSLATIONS, source)
      ? KM_TRANSLATIONS[source]
      : source

  return interpolate(template, vars)
}

export function resolveInitialLanguage() {
  try {
    const stored = normalizeLanguage(localStorage.getItem(LANGUAGE_STORAGE_KEY))
    if (stored) return stored
  } catch {
    // Storage can be unavailable in private or embedded contexts.
  }

  try {
    if (normalizeLanguage(navigator.language?.split('-')[0]) === 'km') {
      return 'km'
    }
  } catch {
    // Ignore missing navigator in non-browser environments.
  }

  return DEFAULT_LANGUAGE
}
