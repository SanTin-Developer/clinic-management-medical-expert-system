import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Loader2, Search, X } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { searchApi } from '../../api/search.js'

function ResultList({ groups, activeIndex, onSelect, onHoverIndex, isLoading, isError, term }) {
  if (term.length < 2) {
    return <p className="px-4 py-6 text-center text-sm text-ink-muted">Type at least 2 characters to search.</p>
  }
  if (isLoading) {
    return (
      <p className="flex items-center justify-center gap-2 px-4 py-6 text-center text-sm text-ink-muted">
        <Loader2 className="size-4 animate-spin" aria-hidden /> Searching…
      </p>
    )
  }
  if (isError) {
    return <p className="px-4 py-6 text-center text-sm text-ink-muted">Search is unavailable right now.</p>
  }
  if (groups.length === 0) {
    return <p className="px-4 py-6 text-center text-sm text-ink-muted">No results found.</p>
  }

  const offsets = groups.reduce((acc, group, groupIndex) => {
    acc.push(groupIndex === 0 ? 0 : acc[groupIndex - 1] + groups[groupIndex - 1].items.length)
    return acc
  }, [])

  return (
    <div>
      {groups.map((group, groupIndex) => (
        <div key={group.type}>
          <p className="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-ink-muted">
            {group.label}
          </p>
          {group.items.map((item, itemIndex) => {
            const flatIndex = offsets[groupIndex] + itemIndex
            return (
              <button
                key={`${group.type}-${item.id}`}
                type="button"
                onMouseEnter={() => onHoverIndex(flatIndex)}
                onClick={() => onSelect(item)}
                className={cn(
                  'block w-full border-l-2 px-3 py-2 text-left transition-colors',
                  activeIndex === flatIndex
                    ? 'border-brand bg-canvas'
                    : 'border-transparent hover:bg-canvas/60',
                )}
              >
                <span className="block truncate text-sm font-medium text-ink">{item.title}</span>
                {item.subtitle ? (
                  <span className="mt-0.5 block truncate text-xs text-ink-muted">{item.subtitle}</span>
                ) : null}
              </button>
            )
          })}
        </div>
      ))}
    </div>
  )
}

export default function GlobalSearch() {
  const navigate = useNavigate()
  const [term, setTerm] = useState('')
  const [debounced, setDebounced] = useState('')
  const [open, setOpen] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(0)
  const containerRef = useRef(null)
  const inputRef = useRef(null)

  useEffect(() => {
    const id = setTimeout(() => setDebounced(term.trim()), 300)
    return () => clearTimeout(id)
  }, [term])

  const enabled = debounced.length >= 2

  const query = useQuery({
    queryKey: ['global-search', debounced],
    queryFn: () => searchApi.global(debounced),
    enabled,
    staleTime: 30_000,
  })

  const groups = useMemo(
    () => (query.data?.groups ?? []).filter((group) => group.items?.length > 0),
    [query.data],
  )

  const flatItems = useMemo(
    () => groups.flatMap((group) => group.items.map((item) => ({ ...item, type: group.type }))),
    [groups],
  )

  const safeIndex = flatItems.length > 0 ? Math.min(activeIndex, flatItems.length - 1) : 0

  useEffect(() => {
    if (!open) return undefined
    const onDown = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [open])

  useEffect(() => {
    const onShortcut = (event) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        setOpen(true)
        inputRef.current?.focus()
      }
    }
    document.addEventListener('keydown', onShortcut)
    return () => document.removeEventListener('keydown', onShortcut)
  }, [])

  const reset = () => {
    setTerm('')
    setDebounced('')
    setActiveIndex(0)
    setOpen(false)
    setMobileOpen(false)
  }

  const go = (item) => {
    if (!item?.url) return
    navigate(item.url)
    reset()
  }

  const onKeyDown = (event) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((index) => Math.min(index + 1, Math.max(flatItems.length - 1, 0)))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((index) => Math.max(index - 1, 0))
    } else if (event.key === 'Enter') {
      if (flatItems[safeIndex]) {
        event.preventDefault()
        go(flatItems[safeIndex])
      }
    } else if (event.key === 'Escape') {
      setOpen(false)
      setMobileOpen(false)
    }
  }

  const field = (
    <div className="relative">
      <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden />
      <input
        ref={inputRef}
        type="text"
        role="searchbox"
        inputMode="search"
        value={term}
        onChange={(event) => {
          setTerm(event.target.value)
          setActiveIndex(0)
          setOpen(true)
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={onKeyDown}
        placeholder="Search patients, appointments…"
        aria-label="Global search"
        className="h-10 w-full rounded-xl border border-line bg-surface pl-9 pr-9 text-sm text-ink outline-none transition focus:border-brand focus:ring-2 focus:ring-brand/30"
      />
      {term ? (
        <button
          type="button"
          onClick={reset}
          aria-label="Clear search"
          className="absolute right-2 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-md text-ink-muted hover:bg-canvas hover:text-ink"
        >
          <X className="size-4" aria-hidden />
        </button>
      ) : (
        <kbd className="pointer-events-none absolute right-3 top-1/2 hidden -translate-y-1/2 rounded border border-line px-1.5 py-0.5 text-[10px] font-medium text-ink-muted lg:block">
          Ctrl K
        </kbd>
      )}
    </div>
  )

  return (
    <>
      <div ref={containerRef} className="relative hidden w-full max-w-md sm:block">
        {field}
        {open ? (
          <div className="absolute inset-x-0 top-12 z-40 max-h-[70vh] overflow-y-auto rounded-xl border border-line bg-surface py-1 shadow-[var(--shadow-dialog)]">
            <ResultList
              groups={groups}
              activeIndex={safeIndex}
              onHoverIndex={setActiveIndex}
              onSelect={go}
              isLoading={enabled && query.isLoading}
              isError={query.isError}
              term={debounced}
            />
          </div>
        ) : null}
      </div>

      <button
        type="button"
        onClick={() => setMobileOpen(true)}
        aria-label="Open search"
        className="grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas sm:hidden"
      >
        <Search className="size-5" aria-hidden />
      </button>

      {mobileOpen ? (
        <div className="fixed inset-0 z-50 flex flex-col bg-canvas sm:hidden">
          <div className="flex items-center gap-2 border-b border-line p-3">
            <div className="min-w-0 flex-1">{field}</div>
            <button
              type="button"
              onClick={() => setMobileOpen(false)}
              aria-label="Close search"
              className="grid size-10 shrink-0 place-items-center rounded-lg text-ink-muted hover:bg-surface"
            >
              <ArrowLeft className="size-5" aria-hidden />
            </button>
          </div>
          <div className="flex-1 overflow-y-auto">
            <ResultList
              groups={groups}
              activeIndex={safeIndex}
              onHoverIndex={setActiveIndex}
              onSelect={go}
              isLoading={enabled && query.isLoading}
              isError={query.isError}
              term={debounced}
            />
          </div>
        </div>
      ) : null}
    </>
  )
}
