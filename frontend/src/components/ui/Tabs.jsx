import { cn } from '../../utils/cn.js'

export default function Tabs({ tabs, value, onChange, className }) {
  return (
    <div
      role="tablist"
      aria-label="Section tabs"
      className={cn(
        'no-scrollbar flex gap-1 overflow-x-auto border-b border-line',
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
            className={cn(
              'flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              active
                ? 'border-brand text-brand-dark'
                : 'border-transparent text-ink-muted hover:text-ink',
            )}
          >
            {tab.label}
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