import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { AlertTriangle, CheckCheck, Clock, Newspaper, Sparkles } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Button from '../components/ui/Button.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Select from '../components/ui/Select.jsx'
import Modal from '../components/ui/Modal.jsx'
import SearchInput from '../components/ui/SearchInput.jsx'
import Toggle from '../components/ui/Toggle.jsx'
import Pagination from '../components/ui/Pagination.jsx'
import { Skeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { newsApi } from '../api/news.js'
import { usePaginated } from '../hooks/usePaginated.js'
import { formatDateTime, formatRelativeTime } from '../utils/format.js'
import { cn } from '../utils/cn.js'
import {
  ANNOUNCEMENT_CATEGORY,
  ANNOUNCEMENT_CATEGORY_OPTIONS,
  ANNOUNCEMENT_PRIORITY,
  ANNOUNCEMENT_PRIORITY_OPTIONS,
  statusBadgeFor,
} from '../utils/status.js'

function NewsCard({ post, onOpen }) {
  const category = statusBadgeFor(ANNOUNCEMENT_CATEGORY, post.category)
  const priority = statusBadgeFor(ANNOUNCEMENT_PRIORITY, post.priority)
  const unread = !post.is_read
  const authorName = post.author?.name ?? 'RoyalCare Clinic'

  return (
    <article
      title={post.published_at ? `Published ${formatDateTime(post.published_at)}` : undefined}
      className={cn(
        'group relative flex h-full flex-col overflow-hidden rounded-2xl border bg-surface shadow-[var(--shadow-card)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)] focus-within:-translate-y-1',
        unread ? 'border-brand/40 ring-1 ring-brand/15' : 'border-line hover:border-brand/40',
      )}
    >
      <button
        type="button"
        onClick={() => onOpen(post)}
        aria-label={`Open announcement: ${post.title}`}
        className="absolute inset-0 z-10 cursor-pointer rounded-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
      />

      <div className="relative aspect-[16/9] w-full overflow-hidden">
        {post.poster_url ? (
          <img
            src={post.poster_url}
            alt=""
            loading="lazy"
            className="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.06]"
          />
        ) : (
          <div className="flex size-full items-center justify-center bg-gradient-to-br from-brand via-brand to-brand-darker">
            <Newspaper className="size-10 text-white/85" aria-hidden />
          </div>
        )}

        <div
          className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-slate-950/70 via-slate-950/20 to-transparent"
          aria-hidden
        />

        <div className="absolute left-3 top-3 flex flex-wrap gap-1.5">
          {post.category ? <Badge tone={category.tone}>{category.label}</Badge> : null}
          {post.priority && post.priority !== 'normal' ? (
            <Badge tone={priority.tone}>{priority.label}</Badge>
          ) : null}
        </div>

        {unread ? (
          <span className="absolute right-3 top-3 inline-flex items-center gap-1 rounded-full bg-brand px-2.5 py-0.5 text-[11px] font-semibold text-white shadow-sm">
            <span className="size-1.5 rounded-full bg-white" aria-hidden /> New
          </span>
        ) : null}
      </div>

      <div className="flex flex-1 flex-col p-5">
        <h3 className="line-clamp-2 text-base font-semibold leading-snug text-ink transition-colors group-hover:text-brand-dark">
          {post.title}
        </h3>
        <p className="mt-2 line-clamp-3 flex-1 text-sm text-ink-muted">{post.body}</p>

        <div className="mt-4 flex items-center justify-between gap-3 border-t border-line pt-3 text-xs text-ink-muted">
          <span className="inline-flex min-w-0 items-center gap-2">
            <span className="grid size-6 shrink-0 place-items-center rounded-full bg-brand-soft text-[11px] font-semibold text-brand-dark">
              {authorName.charAt(0).toUpperCase()}
            </span>
            <span className="truncate">{authorName}</span>
          </span>
          <span className="inline-flex shrink-0 items-center gap-1 whitespace-nowrap">
            <Clock className="size-3.5" aria-hidden />
            {post.published_at ? formatRelativeTime(post.published_at) : '—'}
          </span>
        </div>
      </div>
    </article>
  )
}

function NewsGridSkeleton() {
  return (
    <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      {Array.from({ length: 6 }).map((_, i) => (
        <div key={i} className="overflow-hidden rounded-2xl border border-line bg-surface">
          <Skeleton className="aspect-[16/9] w-full rounded-none" />
          <div className="space-y-3 p-5">
            <Skeleton className="h-4 w-3/4" />
            <Skeleton className="h-3 w-full" />
            <Skeleton className="h-3 w-5/6" />
            <div className="flex items-center justify-between pt-2">
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-3 w-16" />
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

export default function NewsPage() {
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [priority, setPriority] = useState('')
  const [unreadOnly, setUnreadOnly] = useState(false)
  const [viewing, setViewing] = useState(null)
  const [markingAll, setMarkingAll] = useState(false)

  const query = usePaginated(
    ['news', { search, category, priority, unreadOnly }],
    (params) =>
      newsApi.list({
        ...params,
        search: search || undefined,
        category: category || undefined,
        priority: priority || undefined,
        unread: unreadOnly ? 1 : undefined,
      }),
    { page },
  )

  const posts = query.data?.data ?? []
  const featured = query.data?.featured ?? null
  const unreadCount = query.data?.unread_count ?? 0
  const hasFilters = Boolean(search || category || priority || unreadOnly)

  const openPost = async (post) => {
    setViewing(post)
    if (!post.is_read) {
      try {
        await newsApi.read(post.id)
        queryClient.invalidateQueries({ queryKey: ['news'] })
      } catch {
        // Ignore read failures; the post is still displayed.
      }
    }
  }

  const markAllRead = async () => {
    setMarkingAll(true)
    try {
      await newsApi.markAllRead()
      toast.success('All announcements marked as read.')
      queryClient.invalidateQueries({ queryKey: ['news'] })
    } catch (err) {
      toast.error(err.message)
    } finally {
      setMarkingAll(false)
    }
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="News & Updates"
        description="Announcements and notices shared with you across the clinic."
        breadcrumb={[{ label: 'News & Updates' }]}
        actions={
          unreadCount > 0 ? (
            <Button variant="outline" onClick={markAllRead} loading={markingAll}>
              <CheckCheck className="size-4" aria-hidden /> Mark all read
            </Button>
          ) : null
        }
      />

      {featured ? (
        <button
          type="button"
          onClick={() => openPost(featured)}
          className="group relative flex w-full items-start gap-3 overflow-hidden rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-left transition-all duration-300 hover:-translate-y-0.5 hover:shadow-[var(--shadow-card-hover)] dark:border-red-500/30 dark:bg-red-500/10"
        >
          <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-red-600 text-white">
            <AlertTriangle className="size-5" aria-hidden />
          </span>
          <span className="min-w-0 flex-1">
            <span className="block text-xs font-bold uppercase tracking-wide text-red-700 dark:text-red-300">
              Urgent notice
            </span>
            <span className="mt-0.5 block truncate text-sm font-semibold text-ink">{featured.title}</span>
            <span className="mt-0.5 block truncate text-xs text-ink-muted">{featured.body}</span>
          </span>
          <span
            className="hidden shrink-0 items-center gap-1 text-xs font-medium text-red-700 sm:inline-flex dark:text-red-300"
            title={featured.published_at ? formatDateTime(featured.published_at) : ''}
          >
            <Clock className="size-3.5" aria-hidden />
            {featured.published_at ? formatRelativeTime(featured.published_at) : ''}
          </span>
        </button>
      ) : null}

      <Card>
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <div className="lg:col-span-1">
            <SearchInput
              placeholder="Search news…"
              onDebounced={(value) => { setSearch(value); setPage(1) }}
            />
          </div>
          <Select
            aria-label="Filter by category"
            value={category}
            onChange={(e) => { setCategory(e.target.value); setPage(1) }}
          >
            <option value="">All categories</option>
            {ANNOUNCEMENT_CATEGORY_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Select
            aria-label="Filter by priority"
            value={priority}
            onChange={(e) => { setPriority(e.target.value); setPage(1) }}
          >
            <option value="">All priorities</option>
            {ANNOUNCEMENT_PRIORITY_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </Select>
          <Toggle
            label="Unread only"
            checked={unreadOnly}
            onChange={(v) => { setUnreadOnly(v); setPage(1) }}
            className="pb-1"
          />
        </div>
      </Card>

      {query.isLoading ? (
        <NewsGridSkeleton />
      ) : query.isError ? (
        <ErrorState
          title="Failed to load news"
          message={query.error?.message}
          onRetry={() => query.refetch()}
        />
      ) : posts.length === 0 ? (
        <Card>
          <EmptyState
            title={hasFilters ? 'No matching announcements' : 'No announcements'}
            message={
              hasFilters
                ? 'Try adjusting your search or filters.'
                : "There are no announcements to show right now. You're all caught up."
            }
            icon={Newspaper}
          />
        </Card>
      ) : (
        <>
          <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            {posts.map((post) => (
              <NewsCard key={post.id} post={post} onOpen={openPost} />
            ))}
          </div>
          <Card bodyClassName="p-0">
            <Pagination
              page={query.data?.current_page ?? 1}
              lastPage={query.data?.last_page ?? 1}
              total={query.data?.total}
              onChange={setPage}
            />
          </Card>
        </>
      )}

      <Modal
        open={Boolean(viewing)}
        onClose={() => setViewing(null)}
        title={viewing?.title ?? 'Announcement'}
        size="md"
      >
        {viewing ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              {viewing.category ? (
                <Badge tone={statusBadgeFor(ANNOUNCEMENT_CATEGORY, viewing.category).tone}>
                  {statusBadgeFor(ANNOUNCEMENT_CATEGORY, viewing.category).label}
                </Badge>
              ) : null}
              <Badge tone={statusBadgeFor(ANNOUNCEMENT_PRIORITY, viewing.priority).tone}>
                {statusBadgeFor(ANNOUNCEMENT_PRIORITY, viewing.priority).label} priority
              </Badge>
              <span className="ml-auto inline-flex items-center gap-1 text-xs text-ink-muted">
                <Clock className="size-3.5" aria-hidden />
                {viewing.published_at ? formatRelativeTime(viewing.published_at) : 'Not published'}
              </span>
            </div>
            {viewing.poster_url ? (
              <img
                src={viewing.poster_url}
                alt=""
                className="max-h-80 w-full rounded-xl border border-line object-cover"
              />
            ) : null}
            <p className="whitespace-pre-wrap text-sm text-ink">{viewing.body}</p>
            <div className="flex flex-wrap items-center gap-2 border-t border-line pt-3 text-xs text-ink-muted">
              <Sparkles className="size-3.5" aria-hidden />
              {viewing.published_at
                ? `Published ${formatDateTime(viewing.published_at)} (${formatRelativeTime(viewing.published_at)})`
                : 'Not published yet'}
              {viewing.author?.name ? ` · by ${viewing.author.name}` : ''}
            </div>
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
