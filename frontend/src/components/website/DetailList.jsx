/**
 * Two-column definition grid used by the Website CMS detail pages.
 * `items` is an array of `{ label, value }`.
 */
export default function DetailList({ items = [] }) {
  return (
    <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
      {items.map((item) => (
        <div key={item.label} className="min-w-0">
          <dt className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
            {item.label}
          </dt>
          <dd className="mt-1 break-words text-sm text-ink">{item.value}</dd>
        </div>
      ))}
    </dl>
  )
}
