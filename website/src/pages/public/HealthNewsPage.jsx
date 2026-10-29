import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, ChevronDown, Newspaper, Search } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import Input from '../../components/ui/Input.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { cn } from '../../utils/cn.js'
import { blockText, localized, splitHeading, useContentBlocks } from '../../utils/content.js'
import { formatDate } from '../../utils/format.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { Reveal } from '../../components/public/Reveal.jsx'
import { BannerBackdrop, Section, SectionHeading } from '../../components/public/PublicUI.jsx'

/**
 * News & Health positioning copy — the design defaults shown whenever the
 * matching content block (Page Content → News Page) is empty. These are
 * intentional statements — not verified facts or medical claims. Everything
 * factual and variable (titles, excerpts, categories, covers, dates) is
 * loaded from the CMS through the existing public article endpoints.
 */
const NEWS_EYEBROW = 'From Our Medical Team'
const NEWS_HEADING = 'News & Health'
const NEWS_DESCRIPTION = 'Clinic updates, health tips and articles from our medical team.'
const LATEST_HEADING = 'Latest Stories'
const CLOSING = {
  heading: 'Have a Health Question?',
  text: 'Our team is happy to talk through anything you have read — or help you find the right service for you and your family.',
}

// How long each story stays on screen. Deliberately slower than a normal
// carousel so visitors have time to read the headline and excerpt.
const FEATURED_SLIDE_INTERVAL = 8000
const FEATURED_SLIDE_LIMIT = 5
// Space (px) between slides in the 20% / 60% / 20% coverflow layout.
const FEATURED_SLIDE_GAP = 16

// Signed distance from the active slide, wrapping around so there is
// always a neighbour on both sides (coverflow style: 20% / 60% / 20%).
function wrappedOffset(index, active, total) {
  let offset = (index - active + total) % total
  if (offset > total / 2) offset -= total
  return offset
}

// 3D placement for a card given its signed offset from the active slide.
// The active card sits flat and forward; neighbours yaw inward, scale down
// and recede so only ~20% of each peeks in from the edges.
function placement(offset) {
  const distance = Math.abs(offset)
  const sign = offset === 0 ? 1 : Math.sign(offset)

  if (distance === 0) {
    return {
      transform: 'translate3d(0, 0, 0) rotateY(0deg) scale(1)',
      opacity: 1,
      zIndex: 40,
    }
  }

  if (distance === 1) {
    // ~93% of a card width plus the gap lands the neighbour so about a
    // fifth of it shows past the container edge; the inward yaw + scale
    // give it depth.
    return {
      transform: `translate3d(calc(${sign} * (93% + ${FEATURED_SLIDE_GAP}px)), 0, 40px) rotateY(${-sign * 16}deg) scale(0.84)`,
      opacity: 0.9,
      zIndex: 30,
    }
  }

  // Parked just beyond the neighbours, invisible, ready to wrap around.
  return {
    transform: `translate3d(calc(${sign} * 150%), 0, -80px) rotateY(${-sign * 22}deg) scale(0.7)`,
    opacity: 0,
    zIndex: 20,
  }
}

function FeaturedSlider({ slides }) {
  const [active, setActive] = useState(0)
  const [paused, setPaused] = useState(false)
  const { language, t } = useLanguage()

  // If the underlying articles change, keep the active index in range.
  useEffect(() => {
    setActive((current) => (current < slides.length ? current : 0))
  }, [slides.length])

  // Auto-advance, unless the tab is in the background or the visitor
  // prefers reduced motion.
  useEffect(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
    if (paused || slides.length < 2 || reducedMotion) return undefined

    const timer = setTimeout(() => {
      setActive((current) => (current + 1) % slides.length)
    }, FEATURED_SLIDE_INTERVAL)
    return () => clearTimeout(timer)
  }, [active, paused, slides.length])

  // Pause rotation while the tab is in the background.
  useEffect(() => {
    const onVisibility = () => setPaused(document.hidden)
    document.addEventListener('visibilitychange', onVisibility)
    return () => document.removeEventListener('visibilitychange', onVisibility)
  }, [])

  return (
    <div
      className="relative mt-8 h-[24rem] overflow-hidden [perspective:1600px] [perspective-origin:50%_45%] sm:h-[28rem]"
    >
      {/* Soft glow behind the active card, brightened for the dark stage. */}
      <div
        className="pointer-events-none absolute left-1/2 top-1/2 size-[520px] max-w-full -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(75,167,221,0.38),transparent)]"
        aria-hidden
      />

      {slides.map((article, index) => {
        const offset = wrappedOffset(index, active, slides.length)
        const isActive = offset === 0
        const style = placement(offset)

        return (
          <div
            key={article.id}
            className={cn(
              'absolute inset-y-0 flex items-center transition-transform duration-700 ease-out will-change-transform motion-reduce:transition-none',
              isActive && 'drop-shadow-[0_40px_70px_rgba(0,0,0,0.45)]',
            )}
            style={{
              left: '20%',
              width: '60%',
              transformStyle: 'preserve-3d',
              ...style,
            }}
          >
            <Link
              to={`/health-news/${article.slug}`}
              aria-hidden={!isActive}
              tabIndex={isActive ? undefined : -1}
              className={cn(
                'group flex h-full w-full flex-col overflow-hidden rounded-3xl border bg-surface pb-10 shadow-[var(--shadow-card)] transition-[border-color,box-shadow] duration-500',
                isActive ? 'border-azure-300/60' : 'border-line',
              )}
            >
              {article.cover?.url ? (
                <img
                  src={article.cover.url}
                  alt={localized(article, 'title', language)}
                  className="h-44 w-full shrink-0 object-cover sm:h-56"
                />
              ) : (
                <div className="grid h-44 w-full shrink-0 place-items-center bg-gradient-to-br from-navy-600 via-azure-600 to-azure-400 sm:h-56">
                  <Newspaper className="size-12 text-white/70" aria-hidden />
                </div>
              )}
              <div className="flex min-h-0 flex-1 flex-col p-6 text-left">
                <span className="text-xs font-semibold uppercase tracking-wide text-azure-600">
                  {localized(article, 'category', language) || t('News & Health')}
                </span>
                <h2 className="mt-1 line-clamp-2 text-lg font-bold text-ink group-hover:text-azure-700 sm:text-xl">
                  {localized(article, 'title', language)}
                </h2>
                {localized(article, 'excerpt', language) ? (
                  <p className="mt-2 line-clamp-2 hidden text-sm text-ink-muted sm:block">
                    {localized(article, 'excerpt', language)}
                  </p>
                ) : null}
                <p className="mt-auto pt-3 text-xs text-ink-muted">{formatDate(article.published_at)}</p>
              </div>
            </Link>
          </div>
        )
      })}

      {slides.length > 1 ? (
        <div className="absolute inset-x-0 bottom-3 z-50 flex items-center justify-center gap-2">
          {slides.map((article, index) => (
            <button
              key={article.id}
              type="button"
              onClick={() => setActive(index)}
              aria-label={t('Show story: {title}', { title: localized(article, 'title', language) })}
              aria-current={index === active}
              className={cn(
                'h-2 rounded-full transition-all',
                index === active ? 'w-6 bg-azure-500' : 'w-2 bg-white/40 hover:bg-white/70',
              )}
            />
          ))}
        </div>
      ) : null}

      {slides.length > 1 ? (
        <div
          key={active}
          className="absolute inset-x-0 bottom-0 z-50 h-1 origin-left bg-azure-400 motion-reduce:hidden"
          style={{
            animation: `featured-slide-progress ${FEATURED_SLIDE_INTERVAL}ms linear both`,
            animationPlayState: paused ? 'paused' : 'running',
          }}
        />
      ) : null}
    </div>
  )
}

/**
 * Editorial article tile: cover with navy scrim, category chip on the photo,
 * a stretched link over the whole card and a read-more footer row. Distinct
 * from the plain ArticleCard the home page already uses.
 */
function ArticleTile({ article }) {
  const { language, t } = useLanguage()
  const title = localized(article, 'title', language)
  const excerpt = localized(article, 'excerpt', language)
  const category = localized(article, 'category', language)

  return (
    <article className="group relative flex h-full flex-col overflow-hidden rounded-[28px] border border-line bg-surface shadow-[var(--shadow-card)] transition-all duration-300 hover:-translate-y-1 hover:border-azure-400/70 hover:shadow-[var(--shadow-card-hover)]">
      <span
        aria-hidden
        className="absolute inset-x-0 top-0 z-10 h-1 origin-left scale-x-0 bg-gradient-to-r from-azure-500 via-azure-600 to-accent transition-transform duration-500 group-hover:scale-x-100"
      />

      <div className="relative overflow-hidden">
        {article.cover?.url ? (
          <>
            <img
              src={article.cover.url}
              alt={title}
              loading="lazy"
              className="h-52 w-full object-cover transition-transform duration-700 group-hover:scale-105"
            />
            <span
              aria-hidden
              className="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy-700/70 via-navy-700/10 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
            />
          </>
        ) : (
          <div className="auth-grid grid h-52 w-full place-items-center bg-gradient-to-br from-navy-600 via-azure-600 to-azure-400">
            <Newspaper className="size-14 text-white/70" aria-hidden />
          </div>
        )}
        {category ? (
          <span className="absolute left-4 top-4 rounded-full border border-white/40 bg-white/85 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.14em] text-[#0B4A8F] backdrop-blur">
            {category}
          </span>
        ) : null}
      </div>

      <div className="flex flex-1 flex-col p-6">
        <h3 className="font-display text-base font-bold leading-snug text-ink transition-colors group-hover:text-azure-700 sm:text-lg">
          <Link
            to={`/health-news/${article.slug}`}
            className="line-clamp-2 after:absolute after:inset-0 after:content-['']"
          >
            {title}
          </Link>
        </h3>
        {excerpt ? (
          <p className="mt-2 line-clamp-2 flex-1 text-sm leading-relaxed text-ink-muted">
            {excerpt}
          </p>
        ) : null}
        <div className="mt-5 flex items-center justify-between border-t border-line pt-4">
          <time className="text-xs text-ink-muted" dateTime={article.published_at}>
            {formatDate(article.published_at)}
          </time>
          <span className="inline-flex items-center gap-1.5 text-xs font-bold text-azure-700 transition-transform duration-300 group-hover:translate-x-1">
            {t('Read')}
            <ArrowRight className="size-3.5" aria-hidden />
          </span>
        </div>
      </div>
    </article>
  )
}

export default function HealthNewsPage() {
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [page, setPage] = useState(1)
  const { language, t } = useLanguage()

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

  // The category filter list arrives as plain strings, so resolve a Khmer
  // label from the article records that carry the `category_km` sibling.
  const categoryLabels = new Map()
  for (const article of [featured, ...articles]) {
    if (article?.category && !categoryLabels.has(article.category)) {
      categoryLabels.set(article.category, localized(article, 'category', language))
    }
  }
  const categoryLabel = (value) => categoryLabels.get(value) ?? value

  // Admin-editable copy (Page Content → News Page).
  const blocks = useContentBlocks()
  const heading = splitHeading(blockText(blocks, 'news_heading', t(NEWS_HEADING), language))
  const copy = {
    eyebrow: blockText(blocks, 'news_eyebrow', t(NEWS_EYEBROW), language),
    description: blockText(blocks, 'news_description', t(NEWS_DESCRIPTION), language),
    latestHeading: blockText(blocks, 'news_latest_heading', t(LATEST_HEADING), language),
    closingHeading: blockText(blocks, 'news_closing_heading', t(CLOSING.heading), language),
    closingText: blockText(blocks, 'news_closing_text', t(CLOSING.text), language),
  }

  const selectCategory = (next) => {
    setCategory(next === category ? '' : next)
    setPage(1)
  }

  const filterLabel = [search ? `“${search}”` : null, category ? categoryLabel(category) : null]
    .filter(Boolean)
    .join(' · ')

  return (
    <>
      {/* Section 1 — Cinematic hero: dark stage holding the headline, the live
          search/category filters as glass controls, and the 3D featured slider. */}
      <section className="relative isolate overflow-hidden bg-[#06243d]">
        <BannerBackdrop />

        <Section reveal={false} className="relative pb-16 pt-32 text-center sm:pt-40">
          <Reveal>
            <span className="inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.28em] text-white/80 backdrop-blur">
              <span className="relative flex size-1.5">
                <span
                  aria-hidden
                  className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75"
                />
                <span className="relative inline-flex size-1.5 rounded-full bg-accent" />
              </span>
              {copy.eyebrow}
            </span>
          </Reveal>

          <Reveal delay={90}>
            <h1 className="mx-auto mt-6 font-display text-[clamp(36px,5vw,62px)] font-extrabold leading-[1.06] tracking-[-0.02em]">
              <span className="text-white">{heading.lead} </span>
              {heading.accent ? (
                <span className="bg-gradient-to-r from-[#4CAF50] to-[#66BB6A] bg-clip-text text-transparent">
                  {heading.accent}
                </span>
              ) : null}
            </h1>
          </Reveal>

          <Reveal delay={170}>
            <p className="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">
              {copy.description}
            </p>
          </Reveal>

          <Reveal delay={240}>
            <div className="mx-auto mt-8 flex max-w-3xl flex-col items-center gap-4 sm:flex-row sm:justify-center">
              <div className="relative w-full sm:w-72">
                <Search
                  className="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-muted"
                  aria-hidden
                />
                <Input
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value)
                    setPage(1)
                  }}
                  placeholder={t('Search articles…')}
                  aria-label={t('Search articles')}
                  className="h-11 rounded-full border-white/25 pl-10 shadow-[0_14px_34px_-16px_rgba(0,0,0,0.6)]"
                />
              </div>

              {categories.length > 0 ? (
                <div className="flex flex-wrap items-center justify-center gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setCategory('')
                      setPage(1)
                    }}
                    className={cn(
                      'rounded-full border px-4 py-2 text-sm font-medium transition-colors',
                      !category
                        ? 'border-accent bg-accent text-accent-ink shadow-[0_12px_28px_-14px_rgba(76,175,80,0.8)]'
                        : 'border-white/20 bg-white/10 text-white/75 backdrop-blur hover:bg-white/15 hover:text-white',
                    )}
                  >
                    {t('All')}
                  </button>
                  {categories.map((cat) => (
                    <button
                      key={cat}
                      type="button"
                      onClick={() => selectCategory(cat)}
                      className={cn(
                        'rounded-full border px-4 py-2 text-sm font-medium capitalize transition-colors',
                        category === cat
                          ? 'border-accent bg-accent text-accent-ink shadow-[0_12px_28px_-14px_rgba(76,175,80,0.8)]'
                          : 'border-white/20 bg-white/10 text-white/75 backdrop-blur hover:bg-white/15 hover:text-white',
                      )}
                    >
                      {categoryLabel(cat)}
                    </button>
                  ))}
                </div>
              ) : null}
            </div>
          </Reveal>

          {featured && !filtered ? (
            <div className="mt-14">
              <Reveal className="flex items-center justify-center gap-4">
                <span aria-hidden className="h-px w-10 bg-white/20" />
                <p className="text-xs font-bold uppercase tracking-[0.28em] text-white/60">
                  {t('Featured Stories')}
                </p>
                <span aria-hidden className="h-px w-10 bg-white/20" />
              </Reveal>
              <div className="text-left">
                <FeaturedSlider
                  slides={[featured, ...articles.filter((article) => article.id !== featured.id)].slice(
                    0,
                    FEATURED_SLIDE_LIMIT,
                  )}
                />
              </div>
            </div>
          ) : null}

          <a
            href="#latest"
            aria-label={t('Scroll to latest stories')}
            className="mx-auto mt-12 hidden w-fit text-white/45 transition-colors hover:text-white sm:block"
          >
            <ChevronDown className="size-6 animate-bounce" aria-hidden />
          </a>
        </Section>
      </section>

      {/* Section 2 — Latest stories (CMS: paginated articles + categories). */}
      <section id="latest" className="scroll-mt-24 bg-white py-14 sm:py-16">
        <Section reveal={false}>
          <Reveal className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading
              eyebrow={t('01 — Latest Stories')}
              title={filtered ? t('Filtered Results') : copy.latestHeading}
              description={
                filtered
                  ? t('Matching {label}', { label: filterLabel })
                  : t('Browse every article from our clinic team.')
              }
            />
            {data && !isError ? (
              <span className="rounded-full border border-line bg-canvas px-4 py-2 text-xs font-bold text-azure-700 shadow-sm">
                {data.total} {data.total === 1 ? t('article') : t('articles')}
              </span>
            ) : null}
          </Reveal>

          <div className="mt-8">
            {isLoading && !data ? (
              <RowSkeleton rows={3} cols={3} />
            ) : isError ? (
              <ErrorState onRetry={refetch} />
            ) : articles.length === 0 ? (
              <EmptyState
                title={t('No articles found')}
                message={t('Try a different search or category.')}
              />
            ) : (
              <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {articles.map((article, index) => (
                  <Reveal key={article.id} delay={(index % 3) * 80} className="h-full">
                    <ArticleTile article={article} />
                  </Reveal>
                ))}
              </div>
            )}
          </div>

          {data && !isError ? (
            <div className="mt-10 overflow-hidden rounded-2xl border border-line bg-surface">
              <Pagination page={data.current_page} lastPage={data.last_page} total={data.total} onChange={setPage} />
            </div>
          ) : null}
        </Section>
      </section>

      {/* Section 3 — Closing CTA: dark bookend echoing the hero. */}
      {data && !isError ? (
        <section className="relative isolate overflow-hidden bg-[#06243d]">
          <BannerBackdrop />
          <span
            aria-hidden
            className="absolute left-1/2 top-1/2 size-[520px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/10"
          />
          <span
            aria-hidden
            className="absolute left-1/2 top-1/2 size-[840px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/5"
          />

          <Section reveal={false} className="relative py-20 text-center sm:py-24">
            <Reveal className="mx-auto max-w-2xl text-white">
              <p className="text-xs font-bold uppercase tracking-[0.28em] text-white/60">
                {t('02 — Next Steps')}
              </p>
              <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">
                {copy.closingHeading}
              </h2>
              <p className="mt-4 text-sm leading-relaxed text-white/75 sm:text-base">
                {copy.closingText}
              </p>
              <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link
                  to="/contact"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink transition-colors hover:bg-accent-dark sm:w-auto"
                >
                  {t('Contact Us')}
                  <ArrowRight className="size-4" aria-hidden />
                </Link>
                <Link
                  to="/services"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20 sm:w-auto"
                >
                  {t('Browse Services')}
                </Link>
              </div>
            </Reveal>
          </Section>
        </section>
      ) : null}
    </>
  )
}
