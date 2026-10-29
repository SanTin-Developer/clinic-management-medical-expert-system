import { LANGUAGES } from '../../i18n/index.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { cn } from '../../utils/cn.js'

/**
 * Segmented EN / ខ្មែរ switch. The choice is saved by the LanguageProvider, so
 * it survives navigation and repeat visits.
 */
export default function LanguageToggle({ className, tone = 'light' }) {
  const { language, setLanguage, t } = useLanguage()

  return (
    <div
      role="group"
      aria-label={t('Language')}
      className={cn(
        'inline-flex items-center rounded-full border p-0.5',
        tone === 'dark'
          ? 'border-white/25 bg-white/10'
          : 'border-line bg-surface/80 backdrop-blur',
        className,
      )}
    >
      {LANGUAGES.map((option) => {
        const active = language === option.value
        return (
          <button
            key={option.value}
            type="button"
            lang={option.value}
            onClick={() => setLanguage(option.value)}
            aria-pressed={active}
            aria-label={option.label}
            className={cn(
              'rounded-full px-2.5 py-1 text-xs font-semibold transition-colors',
              active
                ? tone === 'dark'
                  ? 'bg-white text-[#0B4A8F]'
                  : 'bg-[#1E88C8] text-white'
                : tone === 'dark'
                  ? 'text-white/80 hover:text-white'
                  : 'text-ink-muted hover:text-[#0B4A8F]',
            )}
          >
            {option.shortLabel}
          </button>
        )
      })}
    </div>
  )
}
