import { useMemo, useState } from 'react'
import { NavLink } from 'react-router-dom'
import { ChevronDown, LayoutGrid, Search, X } from 'lucide-react'
import Dropdown from '../ui/Dropdown.jsx'
import { filterNavigationSections } from '../../constants/navigation.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useChatUnread } from '../../hooks/useChatUnread.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { cn } from '../../utils/cn.js'

export default function NavbarMenu() {
  const { user } = useAuth()
  const { t } = useLanguage()
  const chatUnread = useChatUnread()
  const [isOpen, setIsOpen] = useState(false)
  const [query, setQuery] = useState('')

  const sections = useMemo(
    () =>
      filterNavigationSections(user?.permissions ?? []).map((section) => ({
        ...section,
        label: t(section.label),
        items: section.items.map((item) => ({
          ...item,
          label: t(item.label),
        })),
      })),
    [user, t],
  )

  const term = query.trim().toLowerCase()

  const filteredSections = useMemo(() => {
    if (!term) return sections

    return sections
      .map((section) => {
        const matchesSection = section.label.toLowerCase().includes(term)
        return {
          ...section,
          items: matchesSection
            ? section.items
            : section.items.filter((item) =>
                item.label.toLowerCase().includes(term),
              ),
        }
      })
      .filter((section) => section.items.length > 0)
  }, [sections, term])

  const totalItems = sections.reduce(
    (total, section) => total + section.items.length,
    0,
  )
  const matchCount = filteredSections.reduce(
    (total, section) => total + section.items.length,
    0,
  )
  const isFiltering = term.length > 0

  const handleOpenChange = (next) => {
    setIsOpen(next)
    if (!next) setQuery('')
  }

  const handleNavigate = () => {
    setIsOpen(false)
    setQuery('')
  }

  return (
    <Dropdown
      isOpen={isOpen}
      onOpenChange={handleOpenChange}
      align="left"
      width="w-[min(46rem,calc(100vw-22rem))]"
      className="p-3"
      scrollable
      panelRole="dialog"
      hasPopup="dialog"
      panelLabel={t('Application menu')}
      trigger={
        <button
          type="button"
          aria-label={t('Application menu')}
          title={t('Application menu')}
          className={cn(
            'hidden items-center gap-2 rounded-xl border border-line bg-surface px-2 py-2 text-ink-muted transition-colors hover:bg-canvas hover:text-ink lg:flex',
            isOpen &&
              'border-brand/30 bg-brand-faint text-brand-dark dark:bg-brand/10 dark:text-white',
          )}
        >
          <LayoutGrid className="size-4" aria-hidden />
          <span className="hidden text-sm font-semibold lg:inline">{t('Menu')}</span>
          <ChevronDown
            className={cn(
              'hidden size-3.5 transition-transform lg:block',
              isOpen && 'rotate-180',
            )}
            aria-hidden
          />
        </button>
      }
    >
      <div className="sticky top-0 z-10 -mx-3 -mt-3 flex items-center gap-2 border-b border-line bg-surface px-3 pb-3 pt-3">
        <Search className="size-4 shrink-0 text-ink-muted" aria-hidden />
        <input
          autoFocus
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={t('Find a page…')}
          aria-label={t('Find a page')}
          className="min-w-0 flex-1 bg-transparent text-sm text-ink outline-none placeholder:text-ink-muted/70"
        />
        {query ? (
          <button
            type="button"
            onClick={() => setQuery('')}
            aria-label={t('Clear search')}
            className="grid size-7 shrink-0 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
          >
            <X className="size-3.5" aria-hidden />
          </button>
        ) : null}
      </div>

      <div className="flex items-center justify-between gap-2 px-1 pb-2 pt-3">
        <p className="text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-muted">
          {isFiltering ? t('Search results') : t('All pages')}
        </p>
        <p className="text-[11px] font-medium text-ink-muted">
          {isFiltering
            ? t('{count} found', { count: matchCount })
            : t('{count} links', { count: totalItems })}
        </p>
      </div>

      {filteredSections.length === 0 ? (
        <div className="grid place-items-center gap-2 px-6 py-10 text-center">
          <span className="grid size-10 place-items-center rounded-full bg-canvas text-ink-muted">
            <Search className="size-4" aria-hidden />
          </span>
          <p className="text-sm font-semibold text-ink">{t('No pages found')}</p>
          <p className="max-w-xs text-xs text-ink-muted">
            {t('Try a different keyword, or clear the search to see every page.')}
          </p>
          <button
            type="button"
            onClick={() => setQuery('')}
            className="mt-1 rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink transition-colors hover:bg-canvas"
          >
            {t('Clear search')}
          </button>
        </div>
      ) : (
        <div className="columns-1 gap-5 sm:columns-2 lg:columns-3">
          {filteredSections.map((section) => (
            <section
              key={section.id}
              aria-labelledby={`nav-menu-${section.id}`}
              className="mb-5 break-inside-avoid last:mb-0"
            >
              <p
                id={`nav-menu-${section.id}`}
                className="px-2 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-ink-muted/80"
              >
                {section.label}
              </p>
              <div className="space-y-0.5">
                {section.items.map((item) => (
                  <NavLink
                    key={item.to}
                    to={item.to}
                    end={item.end}
                    onClick={handleNavigate}
                    title={
                      item.comingSoon
                        ? t('{label} (coming soon)', { label: item.label })
                        : item.label
                    }
                    className="block"
                  >
                    {({ isActive }) => (
                      <span
                        className={cn(
                          'group flex items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm transition-colors',
                          isActive
                            ? 'bg-brand-soft font-semibold text-brand-dark dark:bg-brand/20 dark:text-white'
                            : 'text-ink-muted hover:bg-canvas hover:text-ink',
                          item.comingSoon && 'opacity-60',
                        )}
                      >
                        <item.icon
                          className={cn(
                            'size-4 shrink-0',
                            isActive
                              ? 'text-brand'
                              : 'text-ink-muted group-hover:text-brand',
                          )}
                          aria-hidden
                        />
                        <span className="min-w-0 flex-1 truncate">
                          {item.label}
                        </span>
                        {item.to === '/chat' && chatUnread > 0 ? (
                          <span className="grid min-w-5 place-items-center rounded-full bg-brand-soft px-1.5 text-[10px] font-bold leading-5 text-brand-dark">
                            {chatUnread > 9 ? '9+' : chatUnread}
                          </span>
                        ) : null}
                        {item.comingSoon ? (
                          <span className="rounded-full bg-gradient-to-r from-amber-400 to-orange-500 px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-white shadow-sm">
                            {t('Soon')}
                          </span>
                        ) : null}
                      </span>
                    )}
                  </NavLink>
                ))}
              </div>
            </section>
          ))}
        </div>
      )}

      <div className="mt-3 flex items-center justify-between gap-2 border-t border-line px-1 pt-3">
        <p className="text-[11px] text-ink-muted">
          {t('Type to filter the menu, or press Esc to close.')}
        </p>
        <span className="hidden items-center gap-1 text-[10px] font-semibold text-ink-muted/70 sm:flex">
          <kbd className="rounded border border-line bg-canvas px-1.5 py-0.5 font-sans">
            Esc
          </kbd>
          {t('close')}
        </span>
      </div>
    </Dropdown>
  )
}
