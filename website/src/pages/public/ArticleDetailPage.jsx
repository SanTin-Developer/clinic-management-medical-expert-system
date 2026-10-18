import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowLeft, CalendarDays, User } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { formatDate } from '../../utils/format.js'
import { ArticleCard, Section } from '../../components/public/PublicUI.jsx'

export default function ArticleDetailPage() {
  const { slug } = useParams()

  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['website', 'article', slug],
    queryFn: () => publicWebsiteApi.getArticle(slug),
  })

  if (isLoading) {
    return (
      <div className="grid min-h-[60vh] place-items-center">
        <Spinner label="Loading article…" />
      </div>
    )
  }

  if (isError) {
    return (
      <Section className="py-16">
        <ErrorState
          title={error?.status === 404 ? 'Article not found' : undefined}
          message={error?.status === 404 ? 'This article is unavailable or has been removed.' : undefined}
          onRetry={error?.status === 404 ? undefined : refetch}
        />
      </Section>
    )
  }

  const article = data?.data ?? {}
  const related = data?.related ?? []

  return (
    <>
      <section className="brand-gradient hero-glow relative overflow-hidden">
        <Section className="relative pb-14 pt-28 sm:pb-16 sm:pt-32">
          <div className="mx-auto max-w-3xl">
            <Link
              to="/health-news"
              className="inline-flex items-center gap-2 text-sm font-medium text-white/80 hover:text-white"
            >
              <ArrowLeft className="size-4" aria-hidden />
              Back to news
            </Link>

            {article.category ? (
              <span className="mt-6 block text-xs font-bold uppercase tracking-[0.2em] text-white/75">
                {article.category}
              </span>
            ) : null}
            <h1 className="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl">{article.title}</h1>

            <div className="mt-4 flex flex-wrap items-center gap-4 text-xs text-white/80">
              <span className="inline-flex items-center gap-1.5">
                <CalendarDays className="size-4" aria-hidden />
                {formatDate(article.published_at)}
              </span>
              {article.author?.name ? (
                <span className="inline-flex items-center gap-1.5">
                  <User className="size-4" aria-hidden />
                  {article.author.name}
                </span>
              ) : null}
            </div>
          </div>
        </Section>
      </section>

      <Section className="py-12">
        <div className="mx-auto max-w-3xl">
          {article.cover?.url ? (
            <img
              src={article.cover.url}
              alt={article.title}
              className="mb-8 w-full rounded-3xl object-cover shadow-[var(--shadow-card)]"
            />
          ) : null}

          {article.excerpt ? (
            <p className="text-lg font-medium text-ink-muted">{article.excerpt}</p>
          ) : null}

          <div className="mt-6 whitespace-pre-line text-sm leading-relaxed text-ink sm:text-base">
            {article.body}
          </div>

          {Array.isArray(article.tags) && article.tags.length > 0 ? (
            <div className="mt-8 flex flex-wrap gap-2">
              {article.tags.map((tag) => (
                <span key={tag} className="rounded-full bg-azure-100 px-3 py-1 text-xs font-medium text-azure-700">
                  #{tag}
                </span>
              ))}
            </div>
          ) : null}

          <div className="mt-10 border-t border-line pt-8">
            <Link
              to="/health-news"
              className="inline-flex items-center gap-2 rounded-full bg-azure-500 px-6 py-3 text-sm font-semibold text-white shadow-[var(--shadow-card)] transition-colors hover:bg-azure-600"
            >
              <ArrowLeft className="size-4" aria-hidden />
              Back to News &amp; Health
            </Link>
          </div>
        </div>

        {related.length > 0 ? (
          <div className="mx-auto mt-16 max-w-5xl">
            <h2 className="text-xl font-bold text-ink">Related articles</h2>
            <div className="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
              {related.map((item) => (
                <ArticleCard key={item.id} article={item} />
              ))}
            </div>
          </div>
        ) : null}
      </Section>
    </>
  )
}
