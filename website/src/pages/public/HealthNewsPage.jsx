import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import Input from '../../components/ui/Input.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { cn } from '../../utils/cn.js'
import { formatDate } from '../../utils/format.js'
import { ArticleCard, PageBanner, Section } from '../../components/public/PublicUI.jsx'

export default function HealthNewsPage() {
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [page, setPage] = useState(1)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'articles', { search, category, page }],
    queryFn: () =>
      publicWebsiteApi.listArticles({
        search: search || undefined,
        category: category || undefined,
        page,
      }),
    placeholderData: (prev) => prev,
  })

  const articles = data?.data ?? []
  const featured = data?.featured ?? null
  const categories = data?.categories ?? []
  const filtered = Boolean(search) || Boolean(category)

  const selectCategory = (next) => {
    setCategory(next === category ? '' : next)
    setPage(1)
  }

  return (
    <>
      <PageBanner
        title="News & Health"
        description="Clinic updates, health tips and articles from our medical team."
      />
      <Section className="py-12">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative sm:max-w-xs">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden />
            <Input
              value={search}
              onChange={(e) => {
                setSearch(e.target.value)
                setPage(1)
              }}
              placeholder="Search articles…"
              className="pl-9"
              aria-label="Search articles"
            />
          </div>
          {categories.length > 0 ? (
            <div className="flex flex-wrap gap-2">
              {categories.map((cat) => (
                <button
                  key={cat}
                  type="button"
                  onClick={() => selectCategory(cat)}
                  className={cn(
                    'rounded-full border px-4 py-1.5 text-sm font-medium capitalize transition-colors',
                    category === cat
                      ? 'border-brand bg-brand text-white'
                      : 'border-line bg-surface text-ink-muted hover:text-ink',
                  )}
                >
                  {cat}
                </button>
              ))}
            </div>
          ) : null}
        </div>

        {featured && !filtered ? (
          <Link
            to={`/health-news/${featured.slug}`}
            className="group mt-8 grid overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-card)] lg:grid-cols-2"
          >
            {featured.cover?.url ? (
              <img src={featured.cover.url} alt={featured.title} className="h-64 w-full object-cover lg:h-full" />
            ) : (
              <div className="h-64 w-full bg-brand-soft lg:h-full" />
            )}
            <div className="flex flex-col justify-center p-8">
              <span className="text-xs font-semibold uppercase tracking-wide text-brand">Featured</span>
              <h2 className="mt-2 text-2xl font-bold text-ink group-hover:text-brand">{featured.title}</h2>
              {featured.excerpt ? (
                <p className="mt-3 line-clamp-3 text-sm text-ink-muted">{featured.excerpt}</p>
              ) : null}
              <p className="mt-4 text-xs text-ink-muted">{formatDate(featured.published_at)}</p>
            </div>
          </Link>
        ) : null}

        <div className="mt-8">
          {isLoading && !data ? (
            <RowSkeleton rows={3} cols={3} />
          ) : isError ? (
            <ErrorState onRetry={refetch} />
          ) : articles.length === 0 ? (
            <EmptyState title="No articles found" message="Try a different search or category." />
          ) : (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {articles.map((article) => (
                <ArticleCard key={article.id} article={article} />
              ))}
            </div>
          )}
        </div>

        {data ? (
          <div className="mt-8 overflow-hidden rounded-2xl border border-line bg-surface">
            <Pagination page={data.current_page} lastPage={data.last_page} total={data.total} onChange={setPage} />
          </div>
        ) : null}
      </Section>
    </>
  )
}
