import { useEffect, useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, ChevronDown, Mail, MapPin, Phone, Plus } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { API_BASE_URL } from '../../api/client.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { Reveal } from '../../components/public/Reveal.jsx'
import {
  BannerBackdrop,
  resolveIcon,
  Section,
  SectionHeading,
} from '../../components/public/PublicUI.jsx'
import { cn } from '../../utils/cn.js'

/**
 * Services positioning copy (developer-locked brand copy). These are
 * intentional statements — not verified facts, statistics, credentials or
 * claims about medical capabilities. Everything factual and variable (service
 * titles, descriptions, images, featured flags, contact details) is loaded
 * from the CMS through the existing public endpoints.
 */
const SERVICES_EYEBROW = 'What We Offer'
const SERVICES_HEADING = 'Our Services'
const SERVICES_INTRO =
  'Comprehensive medical care delivered by experienced professionals.'
const FEATURED_HEADING = 'Services We Highlight'
const FEATURED_DESCRIPTION = 'A closer look at the services featured across our clinic.'
const CATALOGUE_HEADING = 'All Services'
const CATALOGUE_DESCRIPTION = 'Browse the full list — select a service to read more.'
const CLOSING = {
  heading: 'Not Sure Where to Start?',
  text: 'Tell us what you are looking for and our team will help you find the right service for you and your family.',
}

/**
 * Splits the locked hero heading at its first space so the final word renders
 * as the gradient accent. The copy above stays the single source of truth.
 */
const HEADING_SPACE = SERVICES_HEADING.indexOf(' ')
const HEADING_LEAD = HEADING_SPACE > 0 ? SERVICES_HEADING.slice(0, HEADING_SPACE) : SERVICES_HEADING
const HEADING_ACCENT =
  HEADING_SPACE > 0 ? SERVICES_HEADING.slice(HEADING_SPACE + 1).trim() : ''

/**
 * Renders CMS rich-text bodies as readable plain text so no raw HTML from the
 * editor ever reaches the page. Paragraph tags become line breaks that the
 * `whitespace-pre-line` rendering preserves. Mirrors AboutPage's helper.
 */
function plainText(html) {
  return String(html ?? '')
    .replace(/<\s*br\s*\/?>/gi, '\n')
    .replace(/<\s*\/\s*p\s*>/gi, '\n\n')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/gi, ' ')
    .replace(/&amp;/gi, '&')
    .replace(/&lt;/gi, '<')
    .replace(/&gt;/gi, '>')
    .replace(/&quot;/gi, '"')
    .replace(/&#0*39;|&apos;/gi, "'")
    .replace(/[ \t]+/g, ' ')
    .replace(/ *\n */g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim()
}

/**
 * Dark running ticker under the hero: every published service title drifts
 * across the band, separated by accent plus glyphs. Duplicated once so the
 * existing -50% marquee keyframe loops seamlessly; decorative, so it is
 * hidden from assistive tech (the full catalogue follows below).
 */
function ServicesTicker({ services }) {
  if (services.length === 0) return null

  return (
    <div
      aria-hidden
      className="overflow-hidden border-y border-white/10 bg-white/[0.04] py-4"
    >
      <div className="marquee-track flex w-max items-center gap-8 pr-8">
        {[...services, ...services].map((service, index) => (
          <span
            key={`${service.id}-${index}`}
            className="flex items-center gap-3 whitespace-nowrap text-sm font-semibold uppercase tracking-[0.16em] text-white/50"
          >
            <Plus className="size-3.5 shrink-0 text-accent" aria-hidden />
            {service.title}
          </span>
        ))}
      </div>
    </div>
  )
}

/**
 * Showcase card for a featured service. The first featured service gets the
 * wide split treatment (photo left, navy content panel right); the rest are
 * compact cards. Missing photos fall back to a branded gradient panel.
 */
function FeaturedServiceCard({ service, wide = false }) {
  const Icon = resolveIcon(service.icon)
  const text = service.short_description || plainText(service.description)

  return (
    <article
      className={cn(
        'group relative overflow-hidden rounded-[28px] border border-line bg-surface shadow-[var(--shadow-card)] transition-all duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]',
        wide && 'lg:col-span-2 lg:flex lg:items-stretch',
      )}
    >
      <span
        aria-hidden
        className="absolute inset-x-0 top-0 z-10 h-1 origin-left scale-x-0 bg-gradient-to-r from-azure-500 via-azure-600 to-accent transition-transform duration-500 group-hover:scale-x-100"
      />

      <div className={cn('relative overflow-hidden', wide ? 'lg:w-[52%]' : '')}>
        {service.image?.url ? (
          <img
            src={service.image.url}
            alt={service.title}
            loading="lazy"
            className={cn(
              'w-full object-cover transition-transform duration-700 group-hover:scale-105',
              wide ? 'h-56 sm:h-72 lg:h-full lg:min-h-[330px]' : 'h-52',
            )}
          />
        ) : (
          <div
            className={cn(
              'auth-grid relative grid w-full place-items-center bg-gradient-to-br from-navy-600 via-azure-600 to-azure-400',
              wide ? 'h-56 sm:h-72 lg:h-full' : 'h-52',
            )}
          >
            <span
              aria-hidden
              className="absolute -top-10 left-1/2 size-48 -translate-x-1/2 rounded-full bg-white/20 blur-2xl"
            />
            <Icon className="relative size-16 text-white/80" aria-hidden />
          </div>
        )}
      </div>

      <div
        className={cn(
          'flex flex-1 flex-col p-6 sm:p-7',
          wide && 'bg-navy-600 lg:p-9',
        )}
      >
        <span
          className={cn(
            'grid size-11 shrink-0 place-items-center rounded-2xl',
            wide
              ? 'bg-white/10 text-white'
              : 'bg-azure-50 text-azure-600 transition-colors duration-300 group-hover:bg-azure-500 group-hover:text-white',
          )}
        >
          <Icon className="size-5" aria-hidden />
        </span>
        <h3
          className={cn(
            'mt-4 font-display text-xl font-bold sm:text-2xl',
            wide ? 'text-white' : 'text-ink',
          )}
        >
          {service.title}
        </h3>
        {text ? (
          <p
            className={cn(
              'mt-3 text-sm leading-relaxed',
              wide ? 'text-white/75 sm:text-base' : 'text-ink-muted',
            )}
          >
            {text}
          </p>
        ) : null}
        <Link
          to="/contact"
          className={cn(
            'mt-6 inline-flex w-fit items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold transition-colors',
            wide
              ? 'bg-accent text-accent-ink hover:bg-accent-dark'
              : 'border border-line text-azure-700 hover:border-azure-400 hover:bg-azure-50',
          )}
        >
          Ask about this service
          <ArrowRight className="size-4" aria-hidden />
        </Link>
      </div>
    </article>
  )
}

export default function ServicesPage() {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['website', 'services'],
    queryFn: publicWebsiteApi.listServices,
  })
  const { settings } = useOutletContext() ?? {}

  /** Row id of the open catalogue entry (single-open accordion). */
  const [openServiceId, setOpenServiceId] = useState(null)

  useEffect(() => {
    if (isError && import.meta.env.DEV) {
      console.error(`[website] Failed to load services from ${API_BASE_URL}`, error)
    }
  }, [isError, error])

  const services = data?.data ?? []
  const featured = services.filter((service) => service.is_featured)
  const phone = settings?.primary_phone ?? settings?.secondary_phone

  return (
    <>
      {/* Section 1 — Cinematic hero: mirrors the About page hero family with a
          left-aligned headline and a glass preview panel of live CMS titles. */}
      <section className="relative isolate overflow-hidden bg-[#06243d]">
        <BannerBackdrop />

        <Section reveal={false} className="relative pb-16 pt-32 sm:pt-40">
          <div className="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">
            <div>
              <Reveal>
                <span className="inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.28em] text-white/80 backdrop-blur">
                  <span className="relative flex size-1.5">
                    <span
                      aria-hidden
                      className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75"
                    />
                    <span className="relative inline-flex size-1.5 rounded-full bg-accent" />
                  </span>
                  {SERVICES_EYEBROW}
                </span>
              </Reveal>

              <Reveal delay={90}>
                <h1 className="mt-6 font-display text-[clamp(38px,5vw,64px)] font-extrabold leading-[1.06] tracking-[-0.02em]">
                  <span className="text-white">{HEADING_LEAD} </span>
                  {HEADING_ACCENT ? (
                    <span className="bg-gradient-to-r from-[#4CAF50] to-[#66BB6A] bg-clip-text text-transparent">
                      {HEADING_ACCENT}
                    </span>
                  ) : null}
                </h1>
              </Reveal>

              <Reveal delay={170}>
                <p className="mt-5 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg">
                  {SERVICES_INTRO}
                </p>
              </Reveal>

              <Reveal delay={240}>
                <div className="mt-8 flex flex-wrap items-center gap-3">
                  <Link
                    to="/contact"
                    className="inline-flex items-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink shadow-[0_16px_34px_-14px_rgb(76_175_80_/_0.75)] transition-transform hover:-translate-y-0.5 hover:bg-accent-dark"
                  >
                    Book an Appointment
                    <ArrowRight className="size-4" aria-hidden />
                  </Link>
                  <a
                    href="#catalogue"
                    className="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20"
                  >
                    Browse Services
                  </a>
                </div>
              </Reveal>
            </div>

            {services.length > 0 ? (
              <Reveal delay={320} className="hidden lg:block">
                <div className="rounded-[28px] border border-white/15 bg-white/10 p-6 shadow-[0_28px_64px_-24px_rgba(0,0,0,0.5)] backdrop-blur-xl">
                  <p className="text-[11px] font-bold uppercase tracking-[0.28em] text-white/55">
                    Quick look
                  </p>
                  <ul className="mt-5 space-y-3.5">
                    {services.slice(0, 5).map((service) => {
                      const Icon = resolveIcon(service.icon)
                      return (
                        <li key={service.id} className="flex items-center gap-3">
                          <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-white/10 text-white">
                            <Icon className="size-4" aria-hidden />
                          </span>
                          <span className="min-w-0 flex-1 truncate text-sm font-medium text-white/85">
                            {service.title}
                          </span>
                        </li>
                      )
                    })}
                  </ul>
                  <a
                    href="#catalogue"
                    className="mt-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-5 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-white/20"
                  >
                    Browse all {services.length} services
                    <ArrowRight className="size-3.5" aria-hidden />
                  </a>
                </div>
              </Reveal>
            ) : null}
          </div>

          <a
            href={featured.length > 0 ? '#featured' : '#catalogue'}
            aria-label="Scroll to featured services"
            className="mx-auto mt-12 hidden w-fit text-white/45 transition-colors hover:text-white sm:block"
          >
            <ChevronDown className="size-6 animate-bounce" aria-hidden />
          </a>
        </Section>

        <ServicesTicker services={services} />
      </section>

      {isLoading ? (
        <Section className="py-16">
          <div className="grid place-items-center py-8">
            <Spinner label="Loading services…" />
          </div>
        </Section>
      ) : isError ? (
        <Section className="py-16">
          <ErrorState
            title="Services unavailable"
            message="We couldn't load our services right now. Please try again in a moment."
            onRetry={refetch}
          />
        </Section>
      ) : services.length === 0 ? (
        <Section className="py-16">
          <EmptyState
            title="No services yet"
            message="Our service catalogue is being updated. Please check back soon."
          />
        </Section>
      ) : (
        <>
          {/* Section 2 — Featured spotlight (CMS: is_featured). The first card
              takes the wide split treatment, the rest sit in a two-up grid. */}
          {featured.length > 0 ? (
            <section id="featured" className="scroll-mt-24 bg-white py-16 sm:py-20">
              <Section reveal={false}>
                <Reveal>
                  <SectionHeading
                    eyebrow="01 — Spotlight"
                    title={FEATURED_HEADING}
                    description={FEATURED_DESCRIPTION}
                  />
                </Reveal>
                <div className="mt-10 grid gap-5 lg:grid-cols-2">
                  {featured.map((service, index) => (
                    <Reveal
                      key={service.id}
                      delay={(index % 2) * 80}
                      className={cn('h-full', index === 0 && 'lg:col-span-2')}
                    >
                      <FeaturedServiceCard service={service} wide={index === 0} />
                    </Reveal>
                  ))}
                </div>
              </Section>
            </section>
          ) : null}

          {/* Section 3 — Full catalogue: an expanding index. Each row opens to
              the full CMS description plus an optional photo, so the whole
              catalogue stays scannable without leaving the page. */}
          <section
            id="catalogue"
            className="scroll-mt-24 border-t border-line bg-canvas py-16 sm:py-20"
          >
            <Section reveal={false}>
              <Reveal className="flex flex-wrap items-end justify-between gap-4">
                <SectionHeading
                  eyebrow="02 — Full Catalogue"
                  title={CATALOGUE_HEADING}
                  description={CATALOGUE_DESCRIPTION}
                />
                <span className="rounded-full border border-line bg-surface px-4 py-2 text-xs font-bold text-azure-700 shadow-sm">
                  {services.length} {services.length === 1 ? 'service' : 'services'}
                </span>
              </Reveal>

              <div className="mt-8 overflow-hidden rounded-[28px] border border-line bg-surface shadow-[var(--shadow-card)]">
                {services.map((service, index) => {
                  const Icon = resolveIcon(service.icon)
                  const detail = plainText(service.description) || service.short_description
                  const isExpandable = Boolean(detail) || Boolean(service.image?.url)
                  const isOpen = openServiceId === service.id

                  return (
                    <div
                      key={service.id}
                      className="border-b border-line last:border-b-0"
                    >
                      <button
                        type="button"
                        onClick={
                          isExpandable
                            ? () => setOpenServiceId((current) => (current === service.id ? null : service.id))
                            : undefined
                        }
                        aria-expanded={isExpandable ? isOpen : undefined}
                        aria-controls={isExpandable ? `service-detail-${service.id}` : undefined}
                        className={cn(
                          'group flex w-full items-center gap-4 px-4 py-5 text-left transition-colors duration-300 sm:gap-5 sm:px-6',
                          isExpandable ? 'cursor-pointer' : 'cursor-default',
                          isOpen
                            ? 'bg-azure-50/70'
                            : isExpandable && 'hover:bg-canvas',
                        )}
                      >
                        <span className="font-display text-xs font-extrabold text-azure-400 transition-colors group-hover:text-azure-600">
                          {String(index + 1).padStart(2, '0')}
                        </span>
                        <span
                          className={cn(
                            'grid size-11 shrink-0 place-items-center rounded-2xl transition-colors duration-300',
                            isOpen
                              ? 'bg-azure-500 text-white'
                              : 'bg-azure-50 text-azure-600 group-hover:bg-azure-500 group-hover:text-white',
                          )}
                        >
                          <Icon className="size-5" aria-hidden />
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="flex flex-wrap items-center gap-2">
                            <span className="font-display text-sm font-bold text-ink sm:text-base">
                              {service.title}
                            </span>
                            {service.is_featured ? (
                              <span className="rounded-full bg-accent/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-accent-dark">
                                Featured
                              </span>
                            ) : null}
                          </span>
                          {service.short_description ? (
                            <span className="mt-0.5 line-clamp-1 text-sm text-ink-muted">
                              {service.short_description}
                            </span>
                          ) : null}
                        </span>
                        {isExpandable ? (
                          <ChevronDown
                            className={cn(
                              'size-5 shrink-0 text-azure-500 transition-transform duration-300',
                              isOpen && 'rotate-180',
                            )}
                            aria-hidden
                          />
                        ) : null}
                      </button>

                      {isExpandable ? (
                        <div
                          id={`service-detail-${service.id}`}
                          aria-hidden={!isOpen}
                          className="grid transition-[grid-template-rows] duration-500 ease-out"
                          style={{ gridTemplateRows: isOpen ? '1fr' : '0fr' }}
                        >
                          <div className="overflow-hidden">
                            <div className="flex flex-col gap-5 px-4 pb-6 sm:px-6 sm:pt-1 md:flex-row md:items-start md:gap-8">
                              {detail ? (
                                <p className="whitespace-pre-line text-sm leading-relaxed text-ink-muted md:flex-1 sm:text-base">
                                  {detail}
                                </p>
                              ) : null}
                              {service.image?.url ? (
                                <img
                                  src={service.image.url}
                                  alt={service.title}
                                  loading="lazy"
                                  className="h-44 w-full shrink-0 rounded-2xl border border-line object-cover md:w-72 lg:w-80"
                                />
                              ) : null}
                            </div>
                          </div>
                        </div>
                      ) : null}
                    </div>
                  )
                })}
              </div>
            </Section>
          </section>

          {/* Section 4 — Closing CTA: dark band echoing the hero, with a glass
              card carrying the live contact details from the CMS settings. */}
          <section className="relative isolate overflow-hidden bg-[#06243d]">
            <BannerBackdrop />

            <Section
              reveal={false}
              className="relative grid gap-12 py-20 sm:py-24 lg:grid-cols-2 lg:items-center lg:gap-16"
            >
              <Reveal>
                <p className="text-xs font-bold uppercase tracking-[0.28em] text-white/60">
                  03 — Next Steps
                </p>
                <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                  {CLOSING.heading}
                </h2>
                <p className="mt-4 max-w-xl text-sm leading-relaxed text-white/75 sm:text-base">
                  {CLOSING.text}
                </p>
                <div className="mt-8 flex flex-wrap items-center gap-3">
                  <Link
                    to="/contact"
                    className="inline-flex items-center justify-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink transition-colors hover:bg-accent-dark"
                  >
                    Book an Appointment
                    <ArrowRight className="size-4" aria-hidden />
                  </Link>
                  <Link
                    to="/team"
                    className="inline-flex items-center justify-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20"
                  >
                    Meet Our Team
                  </Link>
                </div>
              </Reveal>

              <Reveal delay={110}>
                <div className="rounded-[28px] border border-white/15 bg-white/10 p-6 backdrop-blur-xl sm:p-7">
                  <p className="text-[11px] font-bold uppercase tracking-[0.28em] text-white/55">
                    Reach us directly
                  </p>
                  <ul className="mt-5 space-y-4">
                    {phone ? (
                      <li className="flex items-center gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-white">
                          <Phone className="size-4" aria-hidden />
                        </span>
                        <a
                          href={`tel:${phone}`}
                          className="min-w-0 truncate text-sm font-medium text-white/85 transition-colors hover:text-white"
                        >
                          {phone}
                        </a>
                      </li>
                    ) : null}
                    {settings?.email ? (
                      <li className="flex items-center gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-white">
                          <Mail className="size-4" aria-hidden />
                        </span>
                        <a
                          href={`mailto:${settings.email}`}
                          className="min-w-0 truncate text-sm font-medium text-white/85 transition-colors hover:text-white"
                        >
                          {settings.email}
                        </a>
                      </li>
                    ) : null}
                    {settings?.address ? (
                      <li className="flex items-start gap-3">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/10 text-white">
                          <MapPin className="size-4" aria-hidden />
                        </span>
                        <span className="text-sm font-medium leading-relaxed text-white/85">
                          {settings.address}
                        </span>
                      </li>
                    ) : null}
                    {!phone && !settings?.email && !settings?.address ? (
                      <li className="text-sm text-white/75">
                        Open the{' '}
                        <Link to="/contact" className="font-semibold text-white underline">
                          contact page
                        </Link>{' '}
                        to reach our team.
                      </li>
                    ) : null}
                  </ul>
                </div>
              </Reveal>
            </Section>
          </section>
        </>
      )}
    </>
  )
}
