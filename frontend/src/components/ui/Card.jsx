import { cn } from '../../utils/cn.js'

export function Card({ title, subtitle, actions, children, className, bodyClassName }) {
  return (
    <section
      className={cn(
        'rounded-2xl border border-line bg-surface shadow-[var(--shadow-card)]',
        className,
      )}
    >
      {title || actions ? (
        <header className="flex items-center justify-between gap-3 border-b border-line px-5 py-4">
          <div>
            {title ? <h2 className="text-base font-semibold text-ink">{title}</h2> : null}
            {subtitle ? <p className="mt-0.5 text-xs text-ink-muted">{subtitle}</p> : null}
          </div>
          {actions ? <div className="flex items-center gap-2">{actions}</div> : null}
        </header>
      ) : null}
      <div className={cn('p-5', bodyClassName)}>{children}</div>
    </section>
  )
}

export default Card