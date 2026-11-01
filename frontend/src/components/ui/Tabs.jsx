import { cn } from '../../utils/cn.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function Tabs({ tabs, value, onChange, className }) {
  const { t } = useLanguage()

  return (
    <div
      role="tablist"
      aria-label={t('Section tabs')}
      className={cn(
        // Phones wrap the strip so no tab is clipped; wider screens scroll.
        'no-scrollbar flex w-full min-w-0 max-w-full flex-wrap gap-1 border-b border-line sm:flex-nowrap sm:overflow-x-auto',
        className,
      )}
    >
      {tabs.map((tab) => {
        const active = value === tab.value
        return (
          <button
            key={tab.value}
            type="button"
            role="tab"
            aria-selected={active}
            onClick={() => onChange(tab.value)}
            data-active={active}
            className={cn(
              // The transparent border keeps the tab height stable while the
              // animated underline (.tab-trigger::after) draws over it.
              'tab-trigger flex min-h-11 items-center gap-2 whitespace-nowrap border-b-2 border-transparent px-3 py-2.5 text-sm font-medium transition-[color,transform] duration-200 active:scale-[0.97] sm:min-h-0 sm:px-4',
              active ? 'text-brand-dark' : 'text-ink-muted hover:text-ink',
            )}
          >
            {t(tab.label)}
            {typeof tab.count === 'number' ? (
              <span
                className={cn(
                  'rounded-full px-2 py-0.5 text-xs font-semibold',
                  active ? 'bg-brand-soft text-brand-dark' : 'bg-canvas text-ink-muted',
                )}
              >
                {tab.count}
              </span>
            ) : null}
          </button>
        )
      })}
    </div>
  )
}