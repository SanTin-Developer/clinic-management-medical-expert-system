import { useQuery } from '@tanstack/react-query'
import DOMPurify from 'dompurify'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { BannerBackdrop, Section } from '../../components/public/PublicUI.jsx'
import { Reveal } from '../../components/public/Reveal.jsx'
import { useLanguage } from '../../hooks/useLanguage.js'
import { cn } from '../../utils/cn.js'
import { localized } from '../../utils/content.js'

export default function PageView({ slug }) {
  const { language, t } = useLanguage()
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['website', 'page', slug],
    queryFn: () => publicWebsiteApi.getPage(slug),
  })

  if (isLoading) {
    return (
      <div className="grid min-h-[60vh] place-items-center">
        <Spinner label={t('Loading page…')} />
      </div>
    )
  }

  if (isError) {
    return (
      <Section className="py-16">
        <ErrorState
          title={error?.status === 404 ? t('Page not found') : undefined}
          message={
            error?.status === 404
              ? t('The page you are looking for is unavailable.')
              : undefined
          }
          onRetry={error?.status === 404 ? undefined : refetch}
        />
      </Section>
    )
  }

  const page = data?.data ?? {}
  const title = localized(page, 'title', language)
  const excerpt = localized(page, 'excerpt', language)
  const body = localized(page, 'body', language)

  return (
    <>
      <section className="relative isolate overflow-hidden bg-[#06243d]">
        <BannerBackdrop image={page.cover?.url} />

        <Section reveal={false} className="relative pb-16 pt-32 text-center sm:pb-20 sm:pt-40">
          <Reveal>
            <span className="inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.28em] text-white/80 backdrop-blur">
              <span className="relative flex size-1.5">
                <span
                  aria-hidden
                  className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75"
                />
                <span className="relative inline-flex size-1.5 rounded-full bg-accent" />
              </span>
              {title}
            </span>
          </Reveal>

          <Reveal delay={90}>
            <h1 className="mx-auto mt-6 max-w-4xl font-display text-[clamp(36px,5vw,56px)] font-extrabold leading-[1.08] tracking-[-0.02em] text-white">
              {title}
            </h1>
          </Reveal>

          {excerpt ? (
            <Reveal delay={170}>
              <p className="mx-auto mt-5 max-w-3xl text-base leading-relaxed text-white/80 sm:text-lg">
                {excerpt}
              </p>
            </Reveal>
          ) : null}
        </Section>
      </section>

      <section className="bg-canvas py-12 sm:py-16">
        <Section reveal={false}>
          <div className="mx-auto max-w-4xl">
            <Reveal>
              <article
                className={cn(
                  'relative overflow-hidden rounded-[2rem] border border-line bg-surface shadow-[var(--shadow-card)]',
                  'p-6 sm:p-10 lg:p-12',
                )}
              >
                <span
                  aria-hidden
                  className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-azure-500 via-azure-600 to-accent"
                />
                <div>
                  {/<[a-z][\s\S]*>/i.test(body) ? (
                    <div
                      className="prose prose-slate max-w-none prose-headings:font-display prose-headings:tracking-tight prose-headings:text-ink prose-p:leading-relaxed prose-p:text-ink/90 prose-li:leading-relaxed prose-li:text-ink/90 prose-a:text-azure-700 prose-a:underline-offset-4 hover:prose-a:text-azure-600 prose-strong:text-ink"
                      dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(body) }}
                    />
                  ) : (
                    <div className="whitespace-pre-line text-base leading-relaxed text-ink/90 sm:text-[17px]">
                      {body}
                    </div>
                  )}
                </div>
              </article>
            </Reveal>
          </div>
        </Section>
      </section>
    </>
  )
}
