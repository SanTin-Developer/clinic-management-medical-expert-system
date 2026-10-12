import { Link } from 'react-router-dom'
import { ChevronRight, Home } from 'lucide-react'

export default function Breadcrumb({ items = [] }) {
  if (!items.length) return null

  return (
    <nav aria-label="Breadcrumb">
      <ol className="flex flex-wrap items-center gap-1 text-sm text-ink-muted">
        {items.map((item, i) => {
          const isLast = i === items.length - 1
          return (
            <li key={`${item.label}-${i}`} className="flex items-center gap-1">
              {i === 0 ? <Home className="size-3.5" aria-hidden /> : null}
              {i > 0 ? <ChevronRight className="size-3.5" aria-hidden /> : null}
              {item.to && !isLast ? (
                <Link className="transition-colors hover:text-ink" to={item.to}>
                  {item.label}
                </Link>
              ) : (
                <span className={isLast ? 'font-medium text-ink' : undefined}>
                  {item.label}
                </span>
              )}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}