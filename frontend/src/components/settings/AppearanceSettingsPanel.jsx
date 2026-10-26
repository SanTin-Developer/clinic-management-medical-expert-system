import { Monitor, Moon, Sun } from 'lucide-react'
import Card from '../ui/Card.jsx'
import { useTheme } from '../../hooks/useTheme.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { cn } from '../../utils/cn.js'

const OPTIONS = [
  {
    value: 'light',
    label: 'Light',
    description: 'Always use light mode.',
    icon: Sun,
  },
  {
    value: 'dark',
    label: 'Dark',
    description: 'Always use dark mode.',
    icon: Moon,
  },
  {
    value: 'system',
    label: 'System',
    description: 'Follow your device appearance.',
    icon: Monitor,
  },
]

export default function AppearanceSettingsPanel() {
  const { mode, setMode, resolvedTheme } = useTheme()
  const { t } = useLanguage()

  return (
    <Card title={t('Appearance')} subtitle={t('Choose how the app looks for you.')}>
      <div className="space-y-4">
        <p className="text-sm text-ink-muted">
          {t('Currently using {mode} mode.', { mode: t(resolvedTheme) })}
        </p>
        <div className="grid gap-2 sm:grid-cols-3">
          {OPTIONS.map((option) => {
            const Icon = option.icon
            const isActive = mode === option.value

            return (
              <button
                key={option.value}
                type="button"
                onClick={() => setMode(option.value)}
                className={cn(
                  'flex flex-col items-start gap-2 rounded-xl border p-4 text-left transition-colors',
                  isActive
                    ? 'border-brand bg-brand-soft/40 ring-1 ring-brand/20'
                    : 'border-line bg-surface hover:bg-canvas',
                )}
                aria-pressed={isActive}
              >
                <div className="flex items-center gap-2">
                  <span
                    className={cn(
                      'grid size-9 place-items-center rounded-lg',
                      isActive ? 'bg-brand text-white' : 'bg-canvas text-ink-muted',
                    )}
                  >
                    <Icon className="size-4" aria-hidden />
                  </span>
                  <span className="text-sm font-semibold text-ink">{t(option.label)}</span>
                </div>
                <p className="text-xs text-ink-muted">{t(option.description)}</p>
              </button>
            )
          })}
        </div>
      </div>
    </Card>
  )
}
