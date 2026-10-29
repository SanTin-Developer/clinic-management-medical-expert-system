import { useEffect, useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { ChevronDown, ChevronLeft, ChevronRight, Images, Maximize2 } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import Modal from '../../components/ui/Modal.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { cn } from '../../utils/cn.js'
import { blockText, localized, splitHeading, useContentBlocks } from '../../utils/content.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { Reveal } from '../../components/public/Reveal.jsx'
import { BannerBackdrop, Section } from '../../components/public/PublicUI.jsx'

/**
 * Facilities & Gallery positioning copy — the design defaults shown whenever
 * the matching content block (Page Content → Gallery Page) is empty. Photos,
 * titles, captions and categories are all loaded from the CMS through the
 * existing public gallery endpoint.
 */
const GALLERY_EYEBROW = 'Inside Our Clinic'
const GALLERY_HEADING = 'Facilities & Gallery'
const GALLERY_DESCRIPTION =
  'Take a look at our clinic, our spaces and the people behind your care.'
const GALLERY_SECTION_EYEBROW = '01 — The Gallery'
const GALLERY_SECTION_TITLE = 'Inside the Clinic'
const GALLERY_SECTION_HELP = 'Tap any photo to view it full size.'

export default function GalleryPage() {
  const { language, t } = useLanguage()
  const [category, setCategory] = useState('')
  const [activeIndex, setActiveIndex] = useState(null)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'gallery', category],
    queryFn: () => publicWebsiteApi.listGallery(category ? { category } : undefined),
  })

  const items = useMemo(() => data?.data ?? [], [data])
  const categories = data?.categories ?? []

  // Admin-editable copy (Page Content → Gallery Page).
  const blocks = useContentBlocks()
  const heading = splitHeading(
    blockText(blocks, 'gallery_heading', t(GALLERY_HEADING), language),
  )
  const copy = {
    eyebrow: blockText(blocks, 'gallery_eyebrow', t(GALLERY_EYEBROW), language),
    description: blockText(blocks, 'gallery_description', t(GALLERY_DESCRIPTION), language),
    sectionEyebrow: blockText(
      blocks,
      'gallery_section_eyebrow',
      t(GALLERY_SECTION_EYEBROW),
      language,
    ),
    sectionHeading: blockText(
      blocks,
      'gallery_section_heading',
      t(GALLERY_SECTION_TITLE),
      language,
    ),
    sectionHelp: blockText(blocks, 'gallery_section_help', t(GALLERY_SECTION_HELP), language),
  }

  // The category list arrives as plain English strings. Where a loaded photo
  // carries a Khmer category we show that label instead, so chips match the
  // localized captions on the tiles.
  const categoryLabel = (value) => {
    const match = items.find((item) => item.category === value)
    return (match ? localized(match, 'category', language) : '') || value
  }

  // Clamp the lightbox to the currently loaded list so a category switch
  // can never point at a stale index.
  const active = activeIndex !== null ? (items[activeIndex] ?? null) : null

  const showRelative = (step) => {
    setActiveIndex((current) =>
      current === null ? current : (current + step + items.length) % items.length,
    )
  }

  // Arrow-key navigation while the lightbox is open (Escape is handled by
  // the shared Modal component).
  useEffect(() => {
    if (activeIndex === null || items.length < 2) return undefined

    const onKey = (event) => {
      if (event.key === 'ArrowLeft') {
        setActiveIndex((current) =>
          current === null ? current : (current - 1 + items.length) % items.length,
        )
      }
      if (event.key === 'ArrowRight') {
        setActiveIndex((current) =>
          current === null ? current : (current + 1) % items.length,
        )
      }
    }

    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [activeIndex, items.length])

  const selectCategory = (next) => {
    setCategory(next)
    setActiveIndex(null)
  }

  const photoCount =
    items.length === 1
      ? t('{count} photo', { count: items.length })
      : t('{count} photos', { count: items.length })

  return (
    <>
      {/* Section 1 — Cinematic hero: headline plus glass category filters
          that drive the gallery below. */}
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

          {categories.length > 0 ? (
            <Reveal delay={240}>
              <div className="mx-auto mt-8 flex max-w-3xl flex-wrap items-center justify-center gap-2">
                <button
                  type="button"
                  onClick={() => selectCategory('')}
                  className={cn(
                    'rounded-full border px-4 py-2 text-sm font-medium transition-colors',
                    !category
                      ? 'border-accent bg-accent text-accent-ink shadow-[0_12px_28px_-14px_rgba(76,175,80,0.8)]'
                      : 'border-white/20 bg-white/10 text-white/75 backdrop-blur hover:bg-white/15 hover:text-white',
                  )}
                >
                  {t('All photos')}
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
            </Reveal>
          ) : null}

          <a
            href="#gallery"
            aria-label={t('Scroll to gallery')}
            className="mx-auto mt-10 hidden w-fit text-white/45 transition-colors hover:text-white sm:block"
          >
            <ChevronDown className="size-6 animate-bounce" aria-hidden />
          </a>
        </Section>
      </section>

      {/* Section 2 — The gallery: masonry of dark-framed tiles with hover
          captions, opening into a keyboard-navigable lightbox. */}
      <section id="gallery" className="scroll-mt-24 bg-white py-14 sm:py-16">
        <Section reveal={false}>
          <Reveal className="flex flex-wrap items-end justify-between gap-4">
            <div className="max-w-2xl">
              <p className="text-xs font-bold uppercase tracking-[0.2em] text-azure-600">
                {copy.sectionEyebrow}
              </p>
              <h2 className="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">
                {copy.sectionHeading}
              </h2>
              <p className="mt-3 text-sm text-ink-muted sm:text-base">{copy.sectionHelp}</p>
            </div>
            {data && !isError ? (
              <span className="rounded-full border border-line bg-canvas px-4 py-2 text-xs font-bold text-azure-700 shadow-sm">
                {photoCount}
                {category ? ` · ${categoryLabel(category)}` : ''}
              </span>
            ) : null}
          </Reveal>

          <div className="mt-8">
            {isLoading ? (
              <div className="grid place-items-center py-16">
                <Spinner label={t('Loading gallery…')} />
              </div>
            ) : isError ? (
              <ErrorState
                title={t('Gallery unavailable')}
                message={t(
                  "We couldn't load the gallery right now. Please try again in a moment.",
                )}
                onRetry={refetch}
              />
            ) : items.length === 0 ? (
              <EmptyState
                title={t('No photos yet')}
                message={
                  category
                    ? t('No photos are listed under “{category}” right now.', {
                        category: categoryLabel(category),
                      })
                    : t('Gallery images will appear here soon.')
                }
                icon={Images}
              />
            ) : (
              <div className="columns-2 gap-4 sm:columns-3 lg:columns-4 [&>*]:mb-4">
                {items.map((item, index) =>
                  item.image?.url ? (
                    <button
                      key={item.id}
                      type="button"
                      onClick={() => setActiveIndex(index)}
                      className="group relative block w-full overflow-hidden rounded-[26px] border border-navy-700/30 text-left transition-all duration-500 hover:-translate-y-1 hover:shadow-[0_30px_60px_-30px_rgba(6,36,61,0.9)]"
                    >
                      <img
                        src={item.image.url}
                        alt={
                          localized(item, 'title', language) ||
                          localized(item, 'caption', language) ||
                          t('Gallery image')
                        }
                        loading="lazy"
                        className="w-full object-cover transition-transform duration-700 group-hover:scale-105"
                      />
                      <span
                        aria-hidden
                        className="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy-700/90 via-navy-700/25 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"
                      />
                      <span
                        aria-hidden
                        className="pointer-events-none absolute right-3 top-3 grid size-9 place-items-center rounded-full border border-white/40 bg-white/20 text-white opacity-0 backdrop-blur transition-opacity duration-300 group-hover:opacity-100"
                      >
                        <Maximize2 className="size-4" />
                      </span>
                      <span className="pointer-events-none absolute inset-x-0 bottom-0 translate-y-2 p-4 opacity-0 transition-all duration-500 group-hover:translate-y-0 group-hover:opacity-100">
                        {localized(item, 'title', language) ? (
                          <span className="block font-display text-sm font-bold text-white">
                            {localized(item, 'title', language)}
                          </span>
                        ) : null}
                        {localized(item, 'category', language) ? (
                          <span className="mt-0.5 block text-[11px] font-semibold uppercase tracking-[0.14em] text-azure-200">
                            {localized(item, 'category', language)}
                          </span>
                        ) : null}
                      </span>
                    </button>
                  ) : null,
                )}
              </div>
            )}
          </div>
        </Section>
      </section>

      {/* Lightbox: full-size photo with prev/next controls, arrow-key
          navigation and a position counter. */}
      <Modal
        open={Boolean(active)}
        onClose={() => setActiveIndex(null)}
        title={localized(active, 'title', language) || t('Gallery photo')}
        description={localized(active, 'category', language) || undefined}
        size="xl"
      >
        {active?.image?.url ? (
          <figure className="relative">
            <img
              src={active.image.url}
              alt={
                localized(active, 'title', language) ||
                localized(active, 'caption', language) ||
                t('Gallery image')
              }
              className="max-h-[70vh] w-full rounded-xl bg-canvas object-contain"
            />

            {items.length > 1 ? (
              <>
                <button
                  type="button"
                  onClick={() => showRelative(-1)}
                  aria-label={t('Previous photo')}
                  className="absolute left-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-white/30 bg-black/45 text-white backdrop-blur transition-colors hover:bg-black/75"
                >
                  <ChevronLeft className="size-5" aria-hidden />
                </button>
                <button
                  type="button"
                  onClick={() => showRelative(1)}
                  aria-label={t('Next photo')}
                  className="absolute right-3 top-1/2 grid size-10 -translate-y-1/2 place-items-center rounded-full border border-white/30 bg-black/45 text-white backdrop-blur transition-colors hover:bg-black/75"
                >
                  <ChevronRight className="size-5" aria-hidden />
                </button>
                <span className="pointer-events-none absolute right-3 top-3 rounded-full bg-black/55 px-3 py-1 text-xs font-semibold text-white backdrop-blur">
                  {(activeIndex ?? 0) + 1} / {items.length}
                </span>
              </>
            ) : null}

            {localized(active, 'caption', language) ? (
              <figcaption className="mt-3 text-sm text-ink-muted">
                {localized(active, 'caption', language)}
              </figcaption>
            ) : null}
          </figure>
        ) : null}
      </Modal>
    </>
  )
}
