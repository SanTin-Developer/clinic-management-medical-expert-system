import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, Loader2, Search, X } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { searchApi } from '../../api/search.js'
import { useLanguage } from '../../hooks/useLanguage.js'

function ResultList({ groups, activeIndex, onSelect, onHoverIndex, isLoading, isError, term }) {
  const { t } = useLanguage()

  if (term.length < 2) {
    return <p className="px-4 py-6 text-center text-sm text-ink-muted">{t('Type at least 2 characters to search.')}</p>
  }
  if (isLoading) {
    return (
      <p className="flex items-center justify-center gap-2 px-4 py-6 text-center text-sm text-ink-muted">
        <Loader2 className="size-4 animate-spin" aria-hidden /> {t('Searching…')}
      </p>
    )
  }
  if (isError) {
    return <p className="px-4 py-6 text-center text-sm text-ink-muted">{t('Search is unavailable right now.')}</p>
  }
  if (groups.length === 0) {
    return <p className="px-4 py-6 text-center text-sm text-ink-muted">{t('No results found.')}</p>
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
            {t(group.label)}
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

function SearchField({
  inputRef,
  term,
  onChange,
  onFocus,
  onKeyDown,
  onClear,
  autoFocus = false,
  showShortcut = false,
}) {
  const { t } = useLanguage()

  return (
    <div className="relative">
      <Search
        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted"
        aria-hidden
      />
      <input
        ref={inputRef}
        autoFocus={autoFocus}
        type="text"
        role="searchbox"
        inputMode="search"
        autoComplete="off"
        spellCheck={false}
        value={term}
        onChange={onChange}
        onFocus={onFocus}
        onKeyDown={onKeyDown}
        placeholder={t('Search patients, appointments, records…')}
        aria-label={t('Global search')}
        className="h-10 w-full rounded-xl border border-line bg-surface pl-9 pr-10 text-sm text-ink outline-none transition placeholder:text-ink-muted/70 focus:border-brand focus:ring-2 focus:ring-brand/25"
      />
      {term ? (
        <button
          type="button"
          onClick={onClear}
          aria-label={t('Clear search')}
          className="absolute right-2 top-1/2 grid size-6 -translate-y-1/2 place-items-center rounded-md text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
        >
          <X className="size-4" aria-hidden />
        </button>
      ) : showShortcut ? (
        <kbd className="pointer-events-none absolute right-2.5 top-1/2 hidden -translate-y-1/2 rounded border border-line bg-canvas/60 px-1.5 py-0.5 text-[10px] font-medium text-ink-muted lg:block">
          Ctrl K
        </kbd>
      ) : null}
    </div>
  )
}

export default function GlobalSearch() {
  const { t } = useLanguage()
  const navigate = useNavigate()
  const [term, setTerm] = useState('')
  const [debounced, setDebounced] = useState('')
  const [open, setOpen] = useState(false)
  const [mobileOpen, setMobileOpen] = useState(false)
  const [activeIndex, setActiveIndex] = useState(0)
  const containerRef = useRef(null)
  const desktopInputRef = useRef(null)
  const overlayInputRef = useRef(null)

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
    if (!open && !mobileOpen) return undefined

    const onEscape = (event) => {
      if (event.key !== 'Escape') return
      setOpen(false)
      setMobileOpen(false)
    }

    document.addEventListener('keydown', onEscape)
    return () => document.removeEventListener('keydown', onEscape)
  }, [open, mobileOpen])

  useEffect(() => {
    const onShortcut = (event) => {
      if (!(event.metaKey || event.ctrlKey) || event.key.toLowerCase() !== 'k') return

      event.preventDefault()
      const isDesktop = window.matchMedia('(min-width: 640px)').matches

      if (isDesktop) {
        setMobileOpen(false)
        setOpen(true)
        desktopInputRef.current?.focus()
        return
      }

      setOpen(false)
      setMobileOpen(true)
      window.requestAnimationFrame(() => overlayInputRef.current?.focus())
    }
    document.addEventListener('keydown', onShortcut)
    return () => document.removeEventListener('keydown', onShortcut)
  }, [])

  const clearSearch = () => {
    setTerm('')
    setDebounced('')
    setActiveIndex(0)
  }

  const reset = () => {
    clearSearch()
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

  const updateTerm = (event) => {
    setTerm(event.target.value)
    setActiveIndex(0)
  }

  const handleInlineChange = (event) => {
    updateTerm(event)
    setOpen(true)
  }

  const handleOverlayChange = (event) => {
    updateTerm(event)
  }

  const openOverlay = () => {
    setOpen(false)
    setMobileOpen(true)
  }

  const closeOverlay = () => {
    setMobileOpen(false)
  }

  return (
    <>
      <div ref={containerRef} className="relative hidden min-w-0 flex-1 sm:block sm:max-w-md">
        <SearchField
          inputRef={desktopInputRef}
          term={term}
          onChange={handleInlineChange}
          onFocus={() => setOpen(true)}
          onKeyDown={onKeyDown}
          onClear={clearSearch}
          showShortcut
        />
        {open ? (
          <div className="animate-fade-in absolute inset-x-0 top-12 z-40 max-h-[70vh] overflow-y-auto rounded-xl border border-line bg-surface py-1 shadow-[var(--shadow-dialog)]">
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
        onClick={openOverlay}
        aria-label={t('Open global search')}
        title={t('Search (Ctrl K)')}
        className="grid size-10 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink sm:hidden"
      >
        <Search className="size-5" aria-hidden />
      </button>

      {mobileOpen ? (
        <div
          className="fixed inset-0 z-[60] sm:hidden"
          role="dialog"
          aria-modal="true"
          aria-label={t('Global search')}
        >
          <div
            aria-hidden="true"
            onClick={closeOverlay}
            className="absolute inset-0 bg-slate-950/40 backdrop-blur-sm"
          />
          <div className="animate-fade-in relative mx-auto flex h-full w-full flex-col bg-canvas sm:mt-16 sm:h-auto sm:max-h-[calc(100vh-8rem)] sm:max-w-2xl sm:overflow-hidden sm:rounded-2xl sm:border sm:border-line sm:bg-surface sm:shadow-[var(--shadow-dialog)]">
            <div className="flex shrink-0 items-center gap-2 border-b border-line p-3 sm:p-4">
              <div className="min-w-0 flex-1">
                <SearchField
                  autoFocus
                  inputRef={overlayInputRef}
                  term={term}
                  onChange={handleOverlayChange}
                  onKeyDown={onKeyDown}
                  onClear={clearSearch}
                />
              </div>
              <button
                type="button"
                onClick={closeOverlay}
                aria-label={t('Close search')}
                className="grid size-10 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-surface hover:text-ink sm:hover:bg-canvas"
              >
                <ArrowLeft className="size-5 sm:hidden" aria-hidden />
                <X className="hidden size-5 sm:block" aria-hidden />
              </button>
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto">
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
        </div>
      ) : null}
    </>
  )
}
