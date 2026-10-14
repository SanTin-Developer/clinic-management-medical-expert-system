/**
 * Dependency-free SVG donut chart (stroke-dasharray segments) with a legend.
 */
export default function DonutChart({
  data = [],
  size = 176,
  thickness = 22,
  centerLabel = 'Total',
  formatValue = (value) => value,
  emptyLabel = 'No data yet',
}) {
  const segments = data.filter((item) => Number(item.value) > 0)
  const total = segments.reduce((sum, item) => sum + Number(item.value), 0)

  const radius = (size - thickness) / 2
  const circumference = 2 * Math.PI * radius
  const center = size / 2

  const laidOut =
    total > 0
      ? segments.reduce(
          (acc, item) => {
            const value = Number(item.value)
            const length = (value / total) * circumference
            acc.slice.push({
              label: item.label,
              color: item.color,
              value,
              length,
              offset: acc.offset,
            })
            acc.offset += length
            return acc
          },
          { slice: [], offset: 0 },
        ).slice
      : []

  return (
    <div className="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:gap-6">
      <div className="relative shrink-0" style={{ width: size, height: size }}>
        {total === 0 ? (
          <div className="grid size-full place-items-center rounded-full border border-dashed border-line text-center text-xs text-ink-muted">
            {emptyLabel}
          </div>
        ) : (
          <svg width={size} height={size} role="img" aria-label="Distribution chart">
            <circle
              cx={center}
              cy={center}
              r={radius}
              fill="none"
              className="stroke-line"
              strokeWidth={thickness}
            />
            <g transform={`rotate(-90 ${center} ${center})`}>
              {laidOut.map((slice) => (
                <circle
                  key={slice.label}
                  cx={center}
                  cy={center}
                  r={radius}
                  fill="none"
                  stroke={slice.color}
                  strokeWidth={thickness}
                  strokeDasharray={`${slice.length} ${circumference - slice.length}`}
                  strokeDashoffset={-slice.offset}
                  strokeLinecap="butt"
                >
                  <title>{`${slice.label}: ${formatValue(slice.value)}`}</title>
                </circle>
              ))}
            </g>
          </svg>
        )}
        <div className="pointer-events-none absolute inset-0 grid place-content-center text-center">
          <span className="text-2xl font-bold text-ink">{formatValue(total)}</span>
          <span className="text-xs font-medium uppercase tracking-wide text-ink-muted">
            {centerLabel}
          </span>
        </div>
      </div>

      {segments.length > 0 ? (
        <ul className="w-full space-y-2">
          {segments.map((item) => {
            const value = Number(item.value)
            const pct = total ? Math.round((value / total) * 100) : 0
            return (
              <li key={item.label} className="flex items-center gap-3 text-sm">
                <span
                  className="size-3 shrink-0 rounded-full"
                  style={{ backgroundColor: item.color }}
                  aria-hidden
                />
                <span className="flex-1 capitalize text-ink-muted">{item.label}</span>
                <span className="font-semibold text-ink">{formatValue(value)}</span>
                <span className="w-10 text-right text-xs text-ink-muted">{pct}%</span>
              </li>
            )
          })}
        </ul>
      ) : null}
    </div>
  )
}
