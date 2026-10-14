import { useId } from 'react'
import { useElementWidth } from '../../hooks/useElementWidth.js'

function smoothPath(points) {
  if (points.length === 0) return ''
  if (points.length === 1) return `M ${points[0].x} ${points[0].y}`

  let d = `M ${points[0].x} ${points[0].y}`
  const tension = 0.18

  for (let i = 0; i < points.length - 1; i += 1) {
    const p0 = points[i - 1] ?? points[i]
    const p1 = points[i]
    const p2 = points[i + 1]
    const p3 = points[i + 2] ?? p2

    const c1x = p1.x + (p2.x - p0.x) * tension
    const c1y = p1.y + (p2.y - p0.y) * tension
    const c2x = p2.x - (p3.x - p1.x) * tension
    const c2y = p2.y - (p3.y - p1.y) * tension

    d += ` C ${c1x} ${c1y}, ${c2x} ${c2y}, ${p2.x} ${p2.y}`
  }

  return d
}

/**
 * Dependency-free SVG area/line chart with a soft medical-style gradient.
 */
export default function TrendAreaChart({
  data = [],
  valueKey = 'total',
  labelKey = 'label',
  color = '#2563eb',
  height = 220,
  formatValue = (value) => value,
  emptyLabel = 'No data yet',
}) {
  const gradientId = `trend-${useId().replace(/:/g, '')}`
  const [ref, width] = useElementWidth(640)

  const values = data.map((item) => Number(item[valueKey] ?? 0))
  const max = Math.max(1, ...values)

  const padX = 10
  const padTop = 18
  const padBottom = 30
  const innerW = Math.max(1, width - padX * 2)
  const innerH = Math.max(1, height - padTop - padBottom)
  const baseline = padTop + innerH
  const stepX = data.length > 1 ? innerW / (data.length - 1) : 0

  const points = data.map((item, index) => ({
    x: padX + index * stepX,
    y: baseline - (values[index] / max) * innerH,
    label: item[labelKey],
    value: values[index],
  }))

  const linePath = smoothPath(points)
  const areaPath = points.length
    ? `${linePath} L ${points[points.length - 1].x} ${baseline} L ${points[0].x} ${baseline} Z`
    : ''

  const labelEvery = Math.max(1, Math.ceil(data.length / 6))

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
        <svg width={width} height={height} role="img" aria-label="Trend chart">
          <defs>
            <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity="0.28" />
              <stop offset="100%" stopColor={color} stopOpacity="0.02" />
            </linearGradient>
          </defs>

          {[0, 0.25, 0.5, 0.75, 1].map((ratio) => (
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

          <path d={areaPath} fill={`url(#${gradientId})`} />
          <path
            d={linePath}
            fill="none"
            stroke={color}
            strokeWidth="2.5"
            strokeLinecap="round"
            strokeLinejoin="round"
          />

          {points.map((point, index) => (
            <g key={`${point.label}-${index}`}>
              <circle
                cx={point.x}
                cy={point.y}
                r={index === points.length - 1 ? 4.5 : 3}
                fill={index === points.length - 1 ? color : 'white'}
                stroke={color}
                strokeWidth="2"
              >
                <title>{`${point.label}: ${formatValue(point.value)}`}</title>
              </circle>
              {index % labelEvery === 0 || index === points.length - 1 ? (
                <text
                  x={point.x}
                  y={height - 8}
                  textAnchor="middle"
                  className="fill-ink-muted"
                  fontSize="11"
                >
                  {point.label}
                </text>
              ) : null}
            </g>
          ))}
        </svg>
      )}
    </div>
  )
}
