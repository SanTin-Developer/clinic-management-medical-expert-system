import { Link, useOutletContext } from 'react-router-dom'
import {
  Activity,
  Ambulance,
  Baby,
  Brain,
  Cross,
  Eye,
  HeartPulse,
  Microscope,
  Pill,
  Scan,
  Stethoscope,
  Syringe,
} from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { formatDate } from '../../utils/format.js'
import { blockImage, localized, useContentBlocks } from '../../utils/content.js'
import { APP_NAME, DEFAULT_HERO_IMAGE } from '../../constants/index.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { useInView } from './Reveal.jsx'

const ICONS = {
  activity: Activity,
  ambulance: Ambulance,
  baby: Baby,
  brain: Brain,
  cross: Cross,
  eye: Eye,
  heart: HeartPulse,
  heartpulse: HeartPulse,
  lab: Microscope,
  microscope: Microscope,
  pill: Pill,
  scan: Scan,
  stethoscope: Stethoscope,
  syringe: Syringe,
}

export function resolveIcon(name) {
  const key = String(name ?? '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '')
  return ICONS[key] ?? Stethoscope
}

export function Section({ children, className, reveal = true }) {
  const { ref, inView } = useInView()

  return (
    <section
      ref={reveal ? ref : undefined}
      className={cn(
        'mx-auto w-full max-w-7xl px-4 sm:px-6',
        reveal && 'reveal-scroll',
        reveal && inView && 'is-visible',
        className,
      )}
    >
      {children}
    </section>
  )
}

/**
 * Shared inner-page banner backdrop (the About-page look): dark navy base, a
 * very faint background image hidden behind the text, a dark blue-green
 * gradient overlay, aurora glows and the grid texture. Falls back to the CMS
 * hero photo, then to the bundled design asset.
 */
export function BannerBackdrop({ image }) {
  const blocks = useContentBlocks()
  const src = image || blockImage(blocks, 'home_hero_image') || DEFAULT_HERO_IMAGE

  return (
    <div aria-hidden className="pointer-events-none absolute inset-0 bg-[#06243d]">
      <img
        src={src}
        alt=""
        className="absolute inset-0 size-full object-cover object-[50%_30%] opacity-30 mix-blend-luminosity"
      />
      <div className="absolute inset-0 bg-gradient-to-b from-[#06243d]/90 via-[#0a2d4e]/85 to-[#06243d]" />
      <span className="animate-aurora absolute -left-28 -top-24 size-[440px] rounded-full bg-azure-500/35 blur-[120px]" />
      <span
        className="animate-aurora absolute -right-24 top-1/3 size-[360px] rounded-full bg-accent/25 blur-[110px]"
        style={{ animationDelay: '-7s' }}
      />
      <span className="auth-grid absolute inset-0 opacity-40" />
    </div>
  )
}

export function PageBanner({ title, description, image }) {
  const { settings } = useOutletContext() ?? {}

  return (
    <section className="relative isolate overflow-hidden bg-[#06243d]">
      <BannerBackdrop image={image} />
      <Section className="relative pb-14 pt-28 sm:pb-16 sm:pt-32">
        <p className="text-xs font-bold uppercase tracking-[0.25em] text-white/75">
          {settings?.site_name || APP_NAME}
        </p>
        <h1 className="mt-3 text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl">{title}</h1>
        {description ? <p className="mt-4 max-w-2xl text-sm text-white/85 sm:text-base">{description}</p> : null}
      </Section>
    </section>
  )
}

export function SectionHeading({ eyebrow, title, description, align = 'left' }) {
  return (
    <div className={cn('max-w-2xl', align === 'center' && 'mx-auto text-center')}>
      {eyebrow ? (
        <p className="text-xs font-bold uppercase tracking-[0.2em] text-azure-600">{eyebrow}</p>
      ) : null}
      <h2 className="mt-2 text-2xl font-bold tracking-tight text-ink sm:text-3xl">{title}</h2>
      {description ? <p className="mt-3 text-sm text-ink-muted sm:text-base">{description}</p> : null}
    </div>
  )
}

export function ServiceCard({ service }) {
  const Icon = resolveIcon(service.icon)
  const { language, t } = useLanguage()
  const title = localized(service, 'title', language)
  const description = localized(service, 'short_description', language)

  return (
    <article className="group flex flex-col overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-card)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]">
      {service.image?.url ? (
        <img src={service.image.url} alt={title} loading="lazy" className="h-44 w-full object-cover" />
      ) : (
        <div className="grid h-44 w-full place-items-center bg-azure-50">
          <Icon className="size-12 text-azure-500" aria-hidden />
        </div>
      )}
      <div className="flex flex-1 flex-col p-6">
        <span className="grid size-11 place-items-center rounded-2xl bg-azure-100 text-azure-700">
          <Icon className="size-5" aria-hidden />
        </span>
        <h3 className="mt-4 text-base font-semibold text-ink">{title}</h3>
        {description ? (
          <p className="mt-2 flex-1 text-sm text-ink-muted">{description}</p>
        ) : null}
        <Link
          to="/services"
          className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
        >
          {t('Learn more')} →
        </Link>
      </div>
    </article>
  )
}

export function TeamCard({ profile }) {
  const { language } = useLanguage()
  const position = localized(profile, 'position', language)
  const specialization = localized(profile, 'specialization', language)
  const bio = localized(profile, 'bio', language)

  return (
    <article className="group flex flex-col overflow-hidden rounded-3xl border border-line bg-surface text-center shadow-[var(--shadow-card)] transition-transform duration-300 hover:-translate-y-1">
      {profile.photo?.url ? (
        <img src={profile.photo.url} alt={profile.name} loading="lazy" className="h-56 w-full object-cover" />
      ) : (
        <div className="grid h-56 w-full place-items-center bg-gradient-to-br from-azure-400 to-azure-600 text-2xl font-bold text-white">
          {profile.name?.charAt(0) ?? '?'}
        </div>
      )}
      <div className="flex flex-1 flex-col p-6">
        <h3 className="text-base font-semibold text-ink">{profile.name}</h3>
        {position ? <p className="mt-0.5 text-sm font-medium text-azure-700">{position}</p> : null}
        {specialization ? (
          <p className="mt-1 text-xs text-ink-muted">{specialization}</p>
        ) : null}
        {bio ? <p className="mt-3 line-clamp-3 text-sm text-ink-muted">{bio}</p> : null}
      </div>
    </article>
  )
}

export function ArticleCard({ article }) {
  const { language } = useLanguage()
  const title = localized(article, 'title', language)
  const excerpt = localized(article, 'excerpt', language)
  const category = localized(article, 'category', language)

  return (
    <article className="flex flex-col overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-card)] transition-transform duration-300 hover:-translate-y-1">
      <Link to={`/health-news/${article.slug}`} className="block">
        {article.cover?.url ? (
          <img src={article.cover.url} alt={title} loading="lazy" className="h-44 w-full object-cover" />
        ) : (
          <div className="h-44 w-full bg-azure-50" />
        )}
      </Link>
      <div className="flex flex-1 flex-col p-6">
        {category ? (
          <span className="text-xs font-semibold uppercase tracking-wide text-azure-600">
            {category}
          </span>
        ) : null}
        <h3 className="mt-1 text-base font-semibold text-ink">
          <Link to={`/health-news/${article.slug}`} className="hover:text-azure-700">
            {title}
          </Link>
        </h3>
        {excerpt ? (
          <p className="mt-2 line-clamp-3 flex-1 text-sm text-ink-muted">{excerpt}</p>
        ) : null}
        <p className="mt-4 text-xs text-ink-muted">{formatDate(article.published_at)}</p>
      </div>
    </article>
  )
}
