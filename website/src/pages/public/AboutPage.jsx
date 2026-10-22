import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  ArrowRight,
  CheckCircle2,
  ChevronDown,
  Cpu,
  Eye,
  Heart,
  HeartPulse,
  Lock,
  MessageCircle,
  Newspaper,
  Target,
} from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { RowSkeleton, Spinner } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { Reveal } from '../../components/public/Reveal.jsx'
import { BannerBackdrop, Section, SectionHeading } from '../../components/public/PublicUI.jsx'
import TeamGrid from '../../components/public/TeamGrid.jsx'
import { cn } from '../../utils/cn.js'
import { ABOUT_CLINIC_IMAGE, APP_NAME, CLINIC_SUMMARY } from '../../constants/index.js'

/**
 * About Us positioning copy (developer-locked brand copy). These are
 * intentional statements — not verified facts, statistics, credentials or
 * claims about medical capabilities. Everything factual and variable (page
 * excerpt/body/cover, the About content blocks, gallery photos, staff
 * profiles) is loaded from the CMS through the existing public endpoints.
 *
 * The editable copy of this page lives in the `about` content blocks (Page
 * Content → About Page); the constants below are only the fallbacks used when
 * the admin has not written a value yet.
 */
const ABOUT_HEADING = 'Caring for Your Health, Every Step of the Way'
const ABOUT_INTRO =
  'We’re committed to patient-centered care, clear communication, and empowering individuals and families to make informed decisions about their health and well-being.'
const WHO_HEADING = 'Respectful Care. Clear Communication. Trusted Expertise.'

/** In-page anchors surfaced as quick-jump chips in the hero (labels are CMS-editable). */
const SECTION_LINKS = [
  { href: '#story', label: 'Our Story', key: 'about_nav_story_label' },
  { href: '#mission', label: 'Mission & Vision', key: 'about_nav_mission_label' },
  { href: '#values', label: 'Core Values', key: 'about_nav_values_label' },
  { href: '#facilities', label: 'Our Facilities', key: 'about_nav_facilities_label' },
  { href: '#team', label: 'Our Team', key: 'about_nav_team_label' },
]

/** Section eyebrow labels ("01 — Who We Are", …) — admin-editable via content blocks. */
const SECTION_EYEBROWS = {
  story: '01 — Who We Are',
  mission: '02 — Our Direction',
  values: '03 — What Guides Us',
  facilities: '04 — Our Facilities',
  team: '05 — Our People',
  commitment: '06 — Our Commitment',
  cta: '07 — Next Steps',
}

/** Facilities and team section headings + button labels (CMS-editable). */
const FACILITIES_COPY = {
  heading: 'A Glimpse Inside',
  text: 'Take a look at our clinic, modern spaces, and the people committed to providing compassionate care.',
  link: 'View All Facilities',
}

const TEAM_COPY = {
  heading: 'The People Behind Your Care',
  text: 'Meet our dedicated team of healthcare professionals committed to your health, comfort, and wellbeing.',
  link: 'Meet Our Team',
}

const MISSION = {
  title: 'Our Mission',
  text: 'To provide compassionate, patient-centered care by supporting informed healthcare decisions through clear communication, respect, and accessible health information.',
}

const VISION = {
  title: 'Our Vision',
  text: 'To create a healthcare experience where every person feels informed, respected, heard, and fully supported on their journey to better health.',
}

const CORE_VALUES = [
  {
    icon: HeartPulse,
    title: 'Patient-Centered Care',
    text: 'Care shaped around each person’s needs, comfort and questions — before, during and after every visit.',
  },
  {
    icon: Heart,
    title: 'Respect and Compassion',
    text: 'Every person is treated with dignity, patience and genuine consideration.',
  },
  {
    icon: MessageCircle,
    title: 'Clear Communication',
    text: 'Plain, honest explanations so you understand your options and next steps.',
  },
  {
    icon: Lock,
    title: 'Privacy and Confidentiality',
    text: 'Your personal details and health information are handled carefully and discreetly.',
  },
  {
    icon: Cpu,
    title: 'Responsible Healthcare Technology',
    text: 'Modern tools are used thoughtfully to enhance care — never to replace the human connection.',
  },
]

const COMMITMENTS = [
  {
    icon: MessageCircle,
    title: 'Respectful patient communication',
    text: 'We listen first and explain things in language that is easy to follow.',
  },
  {
    icon: Lock,
    title: 'Appropriate privacy and confidentiality',
    text: 'Your information is treated as private and safeguarded at every step.',
  },
  {
    icon: Newspaper,
    title: 'Clear health information',
    text: 'Straightforward guidance that helps you understand what matters for your wellbeing.',
  },
  {
    icon: CheckCircle2,
    title: 'Informed decisions',
    text: 'Your options are laid out clearly, so choices feel considered — never rushed.',
  },
]

const CLOSING = {
  heading: 'We’re Here to Help',
  text: 'Have questions about RoyalCare Clinic? Contact our team to learn more about our clinic, services, and how we can support your health journey.',
}

/** Button labels — kept as developer defaults, overridable from the admin. */
const HERO_PRIMARY_LINK = 'Contact Us'
const HERO_SECONDARY_LINK = 'Explore Our Facilities'
const CTA_PRIMARY_LINK = 'Contact Us'
const CTA_SECONDARY_LINK = 'Explore Health Articles'

/** Edge fade so the facility marquee appears to drift in from the page. */
const MARQUEE_EDGE_MASK =
  'linear-gradient(90deg, transparent 0%, #000 5%, #000 95%, transparent 100%)'

/**
 * Turns the bootstrap `content_blocks` array into a key → block map so each
 * section can look up its admin-editable copy. Blocks the admin has not saved
 * yet simply fall back to the developer defaults above.
 */
function indexBlocks(blocks) {
  const map = {}
  for (const block of blocks ?? []) {
    map[block.key] = block
  }
  return map
}

/**
 * Removes repeated gallery entries before they reach the facilities marquee.
 * The CMS can contain two records for the same photo/facility (for example
 * after a re-seed or a manual re-upload); only the first one is rendered.
 */
function uniqueGalleryItems(items) {
  const seen = new Set()
  return items.filter((item) => {
    if (!item.image?.url) return false

    const caption = (item.title || item.caption || '').trim().toLowerCase()
    const image = item.image.url.split('?')[0]
    const key = caption ? `caption:${caption}` : `image:${image}`

    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

/**
 * Renders CMS rich-text bodies as readable plain text so no raw HTML from the
 * editor ever reaches the page. Paragraph tags become line breaks that the
 * `whitespace-pre-line` rendering preserves.
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

function setMetaDescription(content) {
  if (!content) return
  let meta = document.querySelector("meta[name='description']")
  if (!meta) {
    meta = document.createElement('meta')
    meta.name = 'description'
    document.head.appendChild(meta)
  }
  meta.content = content
}

/**
 * One continuously scrolling row of facility photos. The item list is rendered
 * twice so the -50% keyframe loop is seamless; the duplicate copy is hidden
 * from assistive tech and the row pauses on hover so captions can be read.
 */
function FacilitiesMarquee({ items, reverse = false }) {
  const row = reverse ? [...items].reverse() : items
  const doubled = [...row, ...row]

  return (
    <div
      className="flex overflow-hidden"
      style={{ maskImage: MARQUEE_EDGE_MASK, WebkitMaskImage: MARQUEE_EDGE_MASK }}
    >
      <div className={cn('marquee-track flex w-max gap-4 pr-4', reverse && 'marquee-track-reverse')}>
        {doubled.map((item, index) => (
          <figure
            key={`${item.id}-${index}`}
            aria-hidden={index >= row.length ? 'true' : undefined}
            className="group relative w-[260px] shrink-0 overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-card)] transition-shadow duration-300 hover:shadow-[var(--shadow-card-hover)] sm:w-[360px]"
          >
            <img
              src={item.image.url}
              alt={item.title || item.caption || 'RoyalCare clinic facility'}
              loading="lazy"
              className="h-48 w-full object-cover transition-transform duration-700 group-hover:scale-105 sm:h-56"
            />
            {item.title || item.caption ? (
              <figcaption className="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-navy-700/90 via-navy-700/50 to-transparent p-4 pt-12">
                <h3 className="line-clamp-1 text-sm font-semibold text-white">
                  {item.title || item.caption}
                </h3>
                {item.title && item.caption ? (
                  <p className="mt-0.5 line-clamp-1 text-xs text-white/75">{item.caption}</p>
                ) : null}
              </figcaption>
            ) : null}
          </figure>
        ))}
      </div>
    </div>
  )
}

/**
 * About Us page. Reuses the existing website CMS end to end: the `about` page
 * title/excerpt/body/cover/SEO, the editable About content blocks, the
 * published gallery, and published team profiles — no duplicate endpoints.
 *
 * The layout is intentionally unlike the home page: a cinematic dark hero with
 * quick-jump chips, an editorial story split, a glowing mission band, a bento
 * grid of values, an endless facility marquee, a sticky commitment rail and a
 * ringed closing CTA.
 */
export default function AboutPage() {
  const bootstrapQuery = useQuery({
    queryKey: ['website', 'bootstrap'],
    queryFn: publicWebsiteApi.bootstrap,
    staleTime: 5 * 60_000,
  })

  const pageQuery = useQuery({
    queryKey: ['website', 'page', 'about'],
    queryFn: () => publicWebsiteApi.getPage('about'),
  })

  const teamQuery = useQuery({
    queryKey: ['website', 'team'],
    queryFn: publicWebsiteApi.listTeam,
    staleTime: 60_000,
  })

  const galleryQuery = useQuery({
    queryKey: ['website', 'gallery', ''],
    queryFn: () => publicWebsiteApi.listGallery(),
    staleTime: 60_000,
  })

  const settings = bootstrapQuery.data?.settings ?? {}
  const page = pageQuery.data?.data ?? {}
  const team = teamQuery.data?.data ?? []
  const featuredGallery = uniqueGalleryItems(galleryQuery.data?.data ?? []).slice(0, 6)
  // Split the unique facilities across the two drifting rows so the same card
  // is never shown twice at once. Each row still duplicates itself internally
  // for the seamless marquee loop.
  const facilityRows =
    featuredGallery.length > 1
      ? [
          featuredGallery.filter((_, index) => index % 2 === 0),
          featuredGallery.filter((_, index) => index % 2 === 1),
        ]
      : [featuredGallery]

  /**
   * Admin-editable copy. Each section prefers the saved CMS block, then falls
   * back to the developer default so the page never renders empty.
   */
  const blocks = indexBlocks(bootstrapQuery.data?.content_blocks)
  const body = (key, fallback) => blocks[key]?.body?.trim() || fallback
  const list = (key, fallback) => {
    const value = blocks[key]?.data
    return Array.isArray(value) && value.length > 0 ? value : fallback
  }

  const heroHeading = body('about_hero_title', ABOUT_HEADING)
  const heroComma = heroHeading.indexOf(',')
  const heroMain = heroComma > 0 ? heroHeading.slice(0, heroComma + 1) : heroHeading
  const heroAccent = heroComma > 0 ? heroHeading.slice(heroComma + 1).trim() : ''
  const heroIntro = body('about_hero_subtitle', ABOUT_INTRO)
  const heroBadge = body('about_hero_badge', '')

  const storyHeading = body('about_story_heading', WHO_HEADING)
  const storySubheading = body('about_story_subheading', '')
  const storyNote = body('about_story_note', '')
  const storyImageUrl =
    blocks.about_story_image?.image?.url || blocks.home_hero_image?.image?.url || ABOUT_CLINIC_IMAGE

  const missionHeading = body('about_mission_heading', 'Mission and Vision')
  const mission = {
    title: body('about_mission_title', MISSION.title),
    text: body('about_mission_text', MISSION.text),
  }
  const vision = {
    title: body('about_vision_title', VISION.title),
    text: body('about_vision_text', VISION.text),
  }

  const valuesHeading = body('about_values_heading', 'Our Core Values')
  const valuesSubheading = body('about_values_subheading', '')
  const values = list('about_values', CORE_VALUES).map((item, index) => ({
    icon: CORE_VALUES[index]?.icon ?? HeartPulse,
    title: item.title ?? '',
    text: item.text ?? '',
  }))

  const commitmentHeading = body('about_commitment_heading', 'Our Commitment to Patients')
  const commitmentSubheading = body('about_commitment_subheading', '')
  const commitments = list('about_commitments', COMMITMENTS).map((item, index) => ({
    icon: COMMITMENTS[index]?.icon ?? MessageCircle,
    title: item.title ?? '',
    text: item.text ?? '',
  }))

  const ctaHeading = body('about_cta_heading', CLOSING.heading)
  const ctaSubheading = body('about_cta_subheading', CLOSING.text)

  /** Section eyebrow labels and link/button texts, all overridable from the admin. */
  const eyebrows = {
    story: body('about_story_eyebrow', SECTION_EYEBROWS.story),
    mission: body('about_mission_eyebrow', SECTION_EYEBROWS.mission),
    values: body('about_values_eyebrow', SECTION_EYEBROWS.values),
    facilities: body('about_facilities_eyebrow', SECTION_EYEBROWS.facilities),
    team: body('about_team_eyebrow', SECTION_EYEBROWS.team),
    commitment: body('about_commitment_eyebrow', SECTION_EYEBROWS.commitment),
    cta: body('about_cta_eyebrow', SECTION_EYEBROWS.cta),
  }

  const sectionLinks = SECTION_LINKS.map((item) => ({
    href: item.href,
    label: body(item.key, item.label),
  }))

  const heroPrimaryLabel = body('about_hero_primary_link', HERO_PRIMARY_LINK)
  const heroSecondaryLabel = body('about_hero_secondary_link', HERO_SECONDARY_LINK)

  const facilities = {
    heading: body('about_facilities_heading', FACILITIES_COPY.heading),
    text: body('about_facilities_text', FACILITIES_COPY.text),
    link: body('about_facilities_link', FACILITIES_COPY.link),
  }

  const teamSection = {
    heading: body('about_team_heading', TEAM_COPY.heading),
    text: body('about_team_text', TEAM_COPY.text),
    link: body('about_team_link', TEAM_COPY.link),
  }

  const ctaPrimaryLabel = body('about_cta_primary_link', CTA_PRIMARY_LINK)
  const ctaSecondaryLabel = body('about_cta_secondary_link', CTA_SECONDARY_LINK)

  const seoTitle =
    page.seo_title || (page.title ? `${page.title} — ${settings.site_name || APP_NAME}` : '')
  const seoDescription = page.seo_description || plainText(page.excerpt)

  /**
   * Publishes page-specific SEO, then re-asserts it on the next tick: the
   * shared layout writes the site-wide title/meta in its own effect when
   * settings resolve, and this keeps the page values winning regardless of
   * which request finishes last. Leaving the page restores the site-wide
   * title so every other route keeps showing the CMS document title.
   */
  useEffect(() => {
    if (!seoTitle && !seoDescription) return undefined

    const apply = () => {
      if (seoTitle) document.title = seoTitle
      if (seoDescription) setMetaDescription(seoDescription)
    }

    apply()
    const timer = setTimeout(apply, 0)

    return () => {
      clearTimeout(timer)
      document.title = settings.seo_title || settings.site_name || APP_NAME
      if (settings.seo_description) setMetaDescription(settings.seo_description)
    }
  }, [
    seoTitle,
    seoDescription,
    settings.seo_title,
    settings.site_name,
    settings.seo_description,
  ])

  if (pageQuery.isLoading) {
    return (
      <div className="grid min-h-[60vh] place-items-center">
        <Spinner label="Loading page…" />
      </div>
    )
  }

  if (pageQuery.isError) {
    const notFound = pageQuery.error?.status === 404
    return (
      <Section className="py-24">
        <ErrorState
          title={notFound ? 'About page unavailable' : undefined}
          message={notFound ? 'This page is being updated. Please check back soon.' : undefined}
          onRetry={notFound ? undefined : pageQuery.refetch}
        />
      </Section>
    )
  }

  // The lead paragraph belongs to the About page record; the shared clinic
  // blurb is the fallback when the admin has not written an excerpt yet.
  const lead = plainText(page.excerpt) || CLINIC_SUMMARY
  const bodyHtml = page.body ?? ''
  const bodyText = bodyHtml ? (/<[a-z][\s\S]*>/i.test(bodyHtml) ? '' : plainText(bodyHtml)) : plainText(page.excerpt)
  const heroImage = page.cover?.url || ABOUT_CLINIC_IMAGE

  return (
    <>
      {/* Section 1 — Cinematic hero: dark full-bleed banner with aurora glow,
          a masked cover photo, quick-jump chips and a scroll cue. */}
      <section className="relative isolate overflow-hidden bg-[#06243d]">
        <BannerBackdrop image={heroImage} />

        <Section reveal={false} className="relative pb-20 pt-32 text-center sm:pb-24 sm:pt-40">
          <Reveal>
            <span className="inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.28em] text-white/80 backdrop-blur">
              <span className="relative flex size-1.5">
                <span
                  aria-hidden
                  className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75"
                />
                <span className="relative inline-flex size-1.5 rounded-full bg-accent" />
              </span>
              {heroBadge || `About ${settings.site_name || 'RoyalCare'}`}
            </span>
          </Reveal>

          <Reveal delay={90}>
            <h1 className="mx-auto mt-6 max-w-4xl font-display text-[clamp(36px,5.4vw,68px)] font-extrabold leading-[1.06] tracking-[-0.02em]">
              <span className="block text-white">{heroMain}</span>
              {heroAccent ? (
                <span className="block bg-gradient-to-r from-[#4CAF50] to-[#66BB6A] bg-clip-text text-transparent">
                  {heroAccent}
                </span>
              ) : null}
            </h1>
          </Reveal>

          <Reveal delay={170}>
            <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">
              {heroIntro}
            </p>
          </Reveal>

          <Reveal delay={240}>
            <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
              <Link
                to="/contact"
                className="inline-flex items-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink shadow-[0_16px_34px_-14px_rgb(76_175_80_/_0.75)] transition-transform hover:-translate-y-0.5 hover:bg-accent-dark"
              >
                {heroPrimaryLabel}
                <ArrowRight className="size-4" aria-hidden />
              </Link>
              <Link
                to="/gallery"
                className="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20"
              >
                {heroSecondaryLabel}
              </Link>
            </div>
          </Reveal>

          <Reveal delay={320}>
            <nav
              aria-label="About page sections"
              className="mx-auto mt-10 flex max-w-3xl flex-wrap items-center justify-center gap-2"
            >
              {sectionLinks.filter(
                (item) => item.href !== '#team' || team.length > 0,
              ).map((item) => (
                <a
                  key={item.href}
                  href={item.href}
                  className="rounded-full border border-white/15 bg-white/5 px-4 py-2 text-xs font-semibold text-white/70 backdrop-blur transition-colors hover:border-azure-400/60 hover:bg-white/15 hover:text-white"
                >
                  {item.label}
                </a>
              ))}
            </nav>
          </Reveal>

          <a
            href="#story"
            aria-label="Scroll to our story"
            className="mx-auto mt-12 hidden w-fit text-white/45 transition-colors hover:text-white sm:block"
          >
            <ChevronDown className="size-6 animate-bounce" aria-hidden />
          </a>
        </Section>
      </section>

      {/* Section 2 — Who We Are (CMS: About content blocks + page excerpt/body).
          Editorial split: photo left, numbered story right. */}
      <section id="story" className="scroll-mt-24 bg-white">
        <Section
          reveal={false}
          className="grid items-center gap-12 py-16 sm:py-20 lg:grid-cols-2 lg:gap-16"
        >
          <Reveal className="relative order-2 lg:order-1">
            <div className="overflow-hidden rounded-[2rem] border border-line shadow-[var(--shadow-float)]">
              <img
                src={storyImageUrl}
                alt="Inside RoyalCare Clinic"
                loading="lazy"
                className="block aspect-[4/3] w-full object-cover"
              />
            </div>
            <span
              aria-hidden
              className="absolute -left-4 -top-4 hidden size-24 rounded-2xl border-2 border-azure-200 sm:block"
            />
            {storyNote ? (
              <div className="absolute -bottom-5 right-4 inline-flex max-w-[88%] items-center gap-3 rounded-2xl border border-white/60 bg-white/90 px-5 py-4 shadow-[var(--shadow-float)] backdrop-blur">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-azure-50 text-azure-600">
                  <MessageCircle className="size-5" aria-hidden />
                </span>
                <span className="text-sm font-semibold text-ink">{storyNote}</span>
              </div>
            ) : null}
          </Reveal>

          <Reveal delay={90} className="order-1 lg:order-2">
            <p className="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.28em] text-azure-600">
              <span aria-hidden className="h-px w-10 bg-azure-400" />
              {eyebrows.story}
            </p>
            <h2 className="mt-4 font-display text-3xl font-extrabold leading-tight tracking-tight text-ink sm:text-4xl">
              {storyHeading}
            </h2>
            {storySubheading ? (
              <p className="mt-5 text-base leading-relaxed text-ink-muted sm:text-lg">{storySubheading}</p>
            ) : null}
            <p className="mt-5 text-base leading-relaxed text-ink-muted sm:text-lg">{lead}</p>
            {/<[a-z][\s\S]*>/i.test(bodyHtml) &&
            !bodyHtml.toLowerCase().includes('edit this page from the website management') ? (
              <div
                className="prose prose-slate mt-4 max-w-none prose-p:leading-relaxed prose-p:text-ink-muted prose-strong:text-ink"
                dangerouslySetInnerHTML={{ __html: bodyHtml }}
              />
            ) : bodyText && !bodyText.toLowerCase().includes('edit this page from the website management') ? (
              <p className="mt-4 whitespace-pre-line text-base leading-relaxed text-ink-muted sm:text-[17px]">
                {bodyText}
              </p>
            ) : null}
          </Reveal>
        </Section>
      </section>

      {/* Section 3 — Mission & Vision: full-bleed glowing band split down the
          middle, replacing the two plain cards the home page already uses. */}
      <section
        id="mission"
        className="brand-gradient-deep hero-glow relative isolate scroll-mt-24 overflow-hidden"
      >
        <span
          aria-hidden
          className="animate-aurora absolute -left-20 top-1/4 size-[380px] rounded-full bg-azure-400/30 blur-[110px]"
        />
        <span
          aria-hidden
          className="animate-aurora absolute -right-16 bottom-0 size-[320px] rounded-full bg-accent/20 blur-[100px]"
          style={{ animationDelay: '-9s' }}
        />
        <span aria-hidden className="auth-grid absolute inset-0 opacity-30" />

        <Section reveal={false} className="relative py-16 sm:py-20">
          <Reveal className="mx-auto max-w-2xl text-center">
            <p className="text-xs font-bold uppercase tracking-[0.28em] text-white/60">
              {eyebrows.mission}
            </p>
            <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
              {missionHeading}
            </h2>
          </Reveal>

          <div className="relative mt-12 grid gap-8 lg:grid-cols-2 lg:gap-0">
            <span
              aria-hidden
              className="absolute left-1/2 top-0 hidden h-full w-px bg-gradient-to-b from-transparent via-white/30 to-transparent lg:block"
            />
            <span
              aria-hidden
              className="absolute left-1/2 top-1/2 hidden size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/50 bg-accent shadow-[0_0_28px_rgba(76,175,80,0.9)] lg:block"
            />

            {[mission, vision].map((item, index) => {
              const Icon = index === 0 ? Target : Eye
              return (
                <Reveal
                  key={item.title}
                  delay={index * 110}
                  className={index === 0 ? 'lg:pr-14' : 'lg:pl-14'}
                >
                  <article className="h-full rounded-3xl border border-white/15 bg-white/10 p-7 backdrop-blur-xl sm:p-8">
                    <span className="grid size-14 place-items-center rounded-2xl border border-white/20 bg-white/15 text-white">
                      <Icon className="size-6" aria-hidden />
                    </span>
                    <h3 className="mt-5 font-display text-xl font-bold text-white sm:text-2xl">
                      {item.title}
                    </h3>
                    <p className="mt-3 text-sm leading-relaxed text-white/75 sm:text-base">
                      {item.text}
                    </p>
                  </article>
                </Reveal>
              )
            })}
          </div>
        </Section>
      </section>

      {/* Section 4 — Core Values: bento grid. The lead value sits in a wide
          navy card, the rest alternate as wide/compact light cards. */}
      <section id="values" className="scroll-mt-24 bg-canvas py-16 sm:py-20">
        <Section reveal={false}>
          <Reveal>
            <SectionHeading
              align="center"
              eyebrow={eyebrows.values}
              title={valuesHeading}
              description={valuesSubheading || undefined}
            />
          </Reveal>

          <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {values.map((value, index) => {
              const Icon = value.icon
              const isDark = index === 0
              const isWide = index === 0 || index >= 3
              return (
                <Reveal
                  key={value.title}
                  delay={(index % 4) * 70}
                  className={cn('h-full', isWide && 'sm:col-span-2')}
                >
                  <article
                    className={cn(
                      'group relative flex h-full flex-col justify-center gap-4 overflow-hidden rounded-3xl p-6 transition-all duration-300 hover:-translate-y-1 sm:p-7',
                      isWide && 'lg:flex-row lg:items-center lg:gap-6',
                      isDark
                        ? 'border border-white/15 bg-navy-600 shadow-[var(--shadow-card)]'
                        : 'border border-line bg-surface hover:border-azure-400 hover:shadow-[var(--shadow-card)]',
                    )}
                  >
                    <span
                      aria-hidden
                      className="absolute inset-x-0 top-0 h-1 origin-left scale-x-0 bg-gradient-to-r from-azure-500 via-azure-600 to-accent transition-transform duration-500 group-hover:scale-x-100"
                    />
                    <span
                      className={cn(
                        'grid size-12 shrink-0 place-items-center rounded-2xl transition-colors duration-300',
                        isDark
                          ? 'bg-white/10 text-white'
                          : 'bg-canvas text-azure-600 group-hover:bg-azure-500 group-hover:text-white',
                      )}
                    >
                      <Icon className="size-5" aria-hidden />
                    </span>
                    <div className={cn(isWide && 'lg:flex-1')}>
                      <h3
                        className={cn(
                          'font-display text-base font-bold sm:text-lg',
                          isDark ? 'text-white' : 'text-ink',
                        )}
                      >
                        {value.title}
                      </h3>
                      <p
                        className={cn(
                          'mt-2 text-sm leading-relaxed',
                          isDark ? 'text-white/75' : 'text-ink-muted',
                        )}
                      >
                        {value.text}
                      </p>
                    </div>
                  </article>
                </Reveal>
              )
            })}
          </div>
        </Section>
      </section>

      {/* Section 5 — Our Facilities: two endless marquee rows (existing gallery
          API + CMS) drifting in opposite directions. */}
      <section id="facilities" className="scroll-mt-24 overflow-hidden border-y border-line bg-white py-16 sm:py-20">
        <Section reveal={false} className="flex flex-wrap items-end justify-between gap-4">
          <Reveal>
            <SectionHeading
              eyebrow={eyebrows.facilities}
              title={facilities.heading}
              description={facilities.text}
            />
          </Reveal>
          <Reveal delay={80}>
            <Link
              to="/gallery"
              className="inline-flex items-center gap-1.5 rounded-full border border-line px-5 py-2.5 text-sm font-semibold text-azure-700 transition-colors hover:border-azure-400 hover:bg-azure-50"
            >
              {facilities.link}
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </Reveal>
        </Section>

        {galleryQuery.isLoading ? (
          <Section reveal={false} className="mt-10">
            <RowSkeleton rows={2} cols={3} />
          </Section>
        ) : featuredGallery.length === 0 ? (
          <Section reveal={false} className="mt-10">
            <EmptyState
              title="Facilities gallery coming soon"
              message="Photos of our clinic spaces will appear here once they have been published."
              action={
                <Link
                  to="/gallery"
                  className="inline-flex rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-dark"
                >
                  Visit the gallery
                </Link>
              }
            />
          </Section>
        ) : (
          <div className="mt-10 space-y-4">
            {facilityRows
              .filter((row) => row.length > 0)
              .map((row, index) => (
                <FacilitiesMarquee key={index} items={row} reverse={index % 2 === 1} />
              ))}
          </div>
        )}
      </section>

      {/* Section 6 — Meet Our Team (published profiles; hidden when empty) */}
      {team.length > 0 ? (
        <section id="team" className="scroll-mt-24 bg-canvas py-16 sm:py-20">
          <Section reveal={false}>
            <Reveal>
              <SectionHeading
                align="center"
                eyebrow={eyebrows.team}
                title={teamSection.heading}
                description={teamSection.text}
              />
            </Reveal>
            <div className="mt-10">
              <TeamGrid members={team.slice(0, 6)} />
            </div>
            <div className="mt-10 text-center">
              <Link
                to="/team"
                className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 transition-colors hover:text-azure-600"
              >
                {teamSection.link}
                <ArrowRight className="size-4" aria-hidden />
              </Link>
            </div>
          </Section>
        </section>
      ) : null}

      {/* Section 7 — Our Commitment: sticky editorial rail with a numbered list
          instead of another row of cards. */}
      <section id="commitment" className="scroll-mt-24 border-t border-line bg-white">
        <Section
          reveal={false}
          className="grid gap-10 py-16 sm:py-20 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16"
        >
          <div className="lg:sticky lg:top-28 lg:self-start">
            <Reveal>
              <p className="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.28em] text-azure-600">
                <span aria-hidden className="h-px w-10 bg-azure-400" />
                {eyebrows.commitment}
              </p>
              <h2 className="mt-4 font-display text-3xl font-extrabold leading-tight tracking-tight text-ink sm:text-4xl">
                {commitmentHeading}
              </h2>
              <p className="mt-4 max-w-md text-base leading-relaxed text-ink-muted">
                {commitmentSubheading}
              </p>
            </Reveal>
          </div>

          <ol className="border-y border-line">
            {commitments.map((item, index) => {
              const Icon = item.icon
              return (
                <Reveal key={item.title} delay={index * 70} as="li">
                  <article className="group -mx-4 flex items-start gap-4 border-b border-line px-4 py-6 transition-colors duration-300 last:border-b-0 hover:bg-canvas sm:gap-5 sm:rounded-2xl">
                    <span className="font-display text-sm font-extrabold text-azure-400 transition-colors group-hover:text-azure-600">
                      0{index + 1}
                    </span>
                    <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-azure-50 text-azure-600 transition-colors duration-300 group-hover:bg-azure-500 group-hover:text-white">
                      <Icon className="size-5" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                      <h3 className="text-sm font-semibold text-ink sm:text-base">{item.title}</h3>
                      <p className="mt-1.5 text-sm leading-relaxed text-ink-muted">{item.text}</p>
                    </div>
                    <ArrowRight
                      className="mt-1 hidden size-4 shrink-0 -translate-x-2 text-azure-600 opacity-0 transition-all duration-300 group-hover:translate-x-0 group-hover:opacity-100 sm:block"
                      aria-hidden
                    />
                  </article>
                </Reveal>
              )
            })}
          </ol>
        </Section>
      </section>

      {/* Section 8 — Final Call to Action: full-bleed dark band with concentric
          rings, closing the page the way the hero opened it. */}
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

        <Section reveal={false} className="relative py-20 sm:py-24">
          <Reveal className="mx-auto max-w-2xl text-center text-white">
            <p className="text-xs font-bold uppercase tracking-[0.28em] text-white/60">
              {eyebrows.cta}
            </p>
            <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">
              {ctaHeading}
            </h2>
            <p className="mt-4 text-sm leading-relaxed text-white/75 sm:text-base">
              {ctaSubheading}
            </p>
            <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <Link
                to="/contact"
                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink transition-colors hover:bg-accent-dark sm:w-auto"
              >
                {ctaPrimaryLabel}
              </Link>
              <Link
                to="/health-news"
                className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20 sm:w-auto"
              >
                {ctaSecondaryLabel}
              </Link>
            </div>
          </Reveal>
        </Section>
      </section>
    </>
  )
}
