import { useRef, useState } from 'react'
import { ExternalLink, Monitor, RefreshCw, Smartphone, Tablet } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { PREVIEW_PAGES, PREVIEW_VIEWPORTS, publicSiteUrl } from '../../constants/sitePreview.js'

const VIEWPORT_ICONS = { Monitor, Tablet, Smartphone }

/**
 * Live embedded preview of the public website.
 *
 * Renders the public SPA in an iframe so admins can verify their content
 * changes without leaving the dashboard. Supports switching pages and device
 * widths, a manual refresh (content is cached client-side by the public site),
 * and opening the current view in a new tab.
 *
 * `path` controls the page shown; pass `onPathChange` to make the built-in page
 * switcher interactive, or omit it and render your own controls above the frame.
 */
export default function SitePreview({
  path = '/',
  onPathChange,
  height = 620,
  showPagePicker = true,
  className,
}) {
  const [viewport, setViewport] = useState('desktop')
  const [reloadKey, setReloadKey] = useState(0)
  const [loading, setLoading] = useState(true)
  const iframeRef = useRef(null)

  const activePath = path || '/'
  const url = publicSiteUrl(activePath)
  const activeViewport = PREVIEW_VIEWPORTS.find((item) => item.id === viewport) ?? PREVIEW_VIEWPORTS[0]

  // Track the last URL we marked as loading so we only show the veil on real
  // navigations/reloads rather than calling setState synchronously in effects.
  const [lastUrlKey, setLastUrlKey] = useState(`${url}|${reloadKey}`)
  if (lastUrlKey !== `${url}|${reloadKey}`) {
    setLastUrlKey(`${url}|${reloadKey}`)
    setLoading(true)
  }

  const reload = () => setReloadKey((key) => key + 1)

  return (
    <div className={cn('overflow-hidden rounded-xl border border-line bg-surface', className)}>
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-slate-50 px-3 py-2.5 dark:bg-white/5">
        {showPagePicker ? (
          <div className="flex items-center gap-1 overflow-x-auto no-scrollbar">
            {PREVIEW_PAGES.map((page) => {
              const active = page.path === activePath
              return (
                <button
                  key={page.id}
                  type="button"
                  onClick={() => onPathChange?.(page.path)}
                  aria-current={active ? 'page' : undefined}
                  className={cn(
                    'whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                    active
                      ? 'bg-white text-ink shadow-[0_1px_2px_rgba(15,23,42,0.08)] dark:bg-white/10'
                      : 'text-ink-muted hover:text-ink',
                  )}
                >
                  {page.label}
                </button>
              )
            })}
          </div>
        ) : (
          <span className="truncate text-xs font-medium text-ink-muted">{activePath}</span>
        )}

        <div className="flex items-center gap-1">
          <div className="flex items-center gap-0.5 rounded-lg border border-line bg-surface p-0.5">
            {PREVIEW_VIEWPORTS.map((item) => {
              const Icon = VIEWPORT_ICONS[item.icon] ?? Monitor
              const active = item.id === viewport
              return (
                <button
                  key={item.id}
                  type="button"
                  onClick={() => setViewport(item.id)}
                  aria-label={`${item.label} preview`}
                  aria-pressed={active}
                  title={item.label}
                  className={cn(
                    'grid size-7 place-items-center rounded-md transition-colors',
                    active ? 'bg-brand-soft text-brand' : 'text-ink-muted hover:text-ink',
                  )}
                >
                  <Icon className="size-3.5" aria-hidden />
                </button>
              )
            })}
          </div>

          <button
            type="button"
            onClick={reload}
            aria-label="Reload preview"
            title="Reload preview"
            className="grid size-8 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
          >
            <RefreshCw className="size-4" aria-hidden />
          </button>

          <a
            href={url}
            target="_blank"
            rel="noreferrer"
            aria-label="Open in new tab"
            title="Open in new tab"
            className="grid size-8 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
          >
            <ExternalLink className="size-4" aria-hidden />
          </a>
        </div>
      </div>

      <div className="relative bg-slate-100 dark:bg-slate-950/40" style={{ height }}>
        {loading ? (
          <div className="absolute inset-0 z-10 grid place-items-center bg-surface/70 backdrop-blur-sm">
            <span className="flex items-center gap-2 text-sm text-ink-muted">
              <RefreshCw className="size-4 animate-spin" aria-hidden />
              Loading preview…
            </span>
          </div>
        ) : null}

        <div className="grid h-full place-items-center overflow-auto p-0">
          <iframe
            key={`${url}-${reloadKey}`}
            ref={iframeRef}
            src={url}
            title="Public website preview"
            onLoad={() => setLoading(false)}
            className="h-full border-0 bg-white shadow-[0_10px_30px_-18px_rgba(15,23,42,0.35)] transition-[width] duration-300"
            style={{
              width: activeViewport.width ? `${activeViewport.width}px` : '100%',
              maxWidth: '100%',
            }}
          />
        </div>
      </div>
    </div>
  )
}
