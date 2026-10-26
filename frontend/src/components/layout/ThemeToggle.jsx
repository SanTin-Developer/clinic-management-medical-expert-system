import { Moon, Sun } from 'lucide-react'
import { useTheme } from '../../hooks/useTheme.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function ThemeToggle() {
  const { isDark, toggleTheme } = useTheme()
  const { t } = useLanguage()

  return (
    <button
      type="button"
      onClick={toggleTheme}
      aria-label={isDark ? t('Switch to light mode') : t('Switch to dark mode')}
      title={isDark ? t('Light mode') : t('Dark mode')}
      className="grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
    >
      {isDark ? <Sun className="size-5" aria-hidden /> : <Moon className="size-5" aria-hidden />}
    </button>
  )
}
