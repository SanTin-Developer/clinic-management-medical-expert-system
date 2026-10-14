import { useId } from 'react'
import { useElementWidth } from '../../hooks/useElementWidth.js'

/**
 * Dependency-free SVG column chart used for monthly revenue.
 */
export default function BarChart({
  data = [],
  valueKey = 'total',
  labelKey = 'label',
  color = '#0d9488',
  height = 220,
  formatValue = (value) => value,
  formatAxis = (value) => value,
  emptyLabel = 'No data yet',
}) {
  const gradientId = `bar-${useId().replace(/:/g, '')}`
  const [ref, width] = useElementWidth(640)

  const values = data.map((item) => Number(item[valueKey] ?? 0))
  const max = Math.max(1, ...values)

  const padX = 10
  const padTop = 24
  const padBottom = 30
  const innerW = Math.max(1, width - padX * 2)
  const innerH = Math.max(1, height - padTop - padBottom)
  const slot = data.length ? innerW / data.length : 0
  const barWidth = Math.max(6, Math.min(46, slot * 0.55))

  return (
    <div ref={ref} className="w-full">
      {data.length === 0 ? (
        <div
          className="flex items-center justify-center rounded-xl border border-dashed border-line text-sm text-ink-muted"
          style={{ height }}
        >
          {emptyLabel}
        </div>
      ) : (
        <svg width={width} height={height} role="img" aria-label="Bar chart">
          <defs>
            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity="0.95" />
              <stop offset="100%" stopColor={color} stopOpacity="0.45" />
            </linearGradient>
          </defs>

          {[0, 0.5, 1].map((ratio) => (
            <line
              key={ratio}
              x1={padX}
              x2={width - padX}
              y1={padTop + innerH * ratio}
              y2={padTop + innerH * ratio}
              className="stroke-line"
              strokeWidth="1"
              strokeDasharray={ratio === 1 ? '0' : '4 6'}
            />
          ))}

          {data.map((item, index) => {
            const value = values[index]
            const barHeight = (value / max) * innerH
            const x = padX + index * slot + (slot - barWidth) / 2
            const y = padTop + innerH - barHeight

            return (
              <g key={`${item[labelKey]}-${index}`}>
                <rect
                  x={x}
                  y={y}
                  width={barWidth}
                  height={Math.max(barHeight, value > 0 ? 3 : 0)}
                  rx="6"
                  fill={`url(#${gradientId})`}
                >
                  <title>{`${item[labelKey]}: ${formatValue(value)}`}</title>
                </rect>
                <text
                  x={x + barWidth / 2}
                  y={y - 7}
                  textAnchor="middle"
                  className="fill-ink"
                  fontSize="11"
                  fontWeight="600"
                >
                  {formatAxis(value)}
                </text>
                <text
                  x={x + barWidth / 2}
                  y={height - 8}
                  textAnchor="middle"
                  className="fill-ink-muted"
                  fontSize="11"
                >
                  {item[labelKey]}
                </text>
              </g>
            )
          })}
        </svg>
      )}
    </div>
  )
}
