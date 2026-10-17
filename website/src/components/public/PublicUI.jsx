import { Link } from 'react-router-dom'
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

export function Section({ children, className }) {
  return (
    <section className={cn('mx-auto w-full max-w-7xl px-4 sm:px-6', className)}>{children}</section>
  )
}

export function PageBanner({ title, description, image }) {
  return (
    <section className="brand-gradient hero-glow relative overflow-hidden">
      {image ? (
        <img src={image} alt="" className="absolute inset-0 size-full object-cover opacity-20" />
      ) : null}
      <Section className="relative pb-14 pt-28 sm:pb-16 sm:pt-32">
        <p className="text-xs font-bold uppercase tracking-[0.25em] text-white/75">RoyalCare</p>
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
  return (
    <article className="group flex flex-col overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-card)] transition-transform duration-300 hover:-translate-y-1 hover:shadow-[var(--shadow-card-hover)]">
      {service.image?.url ? (
        <img src={service.image.url} alt={service.title} loading="lazy" className="h-44 w-full object-cover" />
      ) : (
        <div className="grid h-44 w-full place-items-center bg-azure-50">
          <Icon className="size-12 text-azure-500" aria-hidden />
        </div>
      )}
      <div className="flex flex-1 flex-col p-6">
        <span className="grid size-11 place-items-center rounded-2xl bg-azure-100 text-azure-700">
          <Icon className="size-5" aria-hidden />
        </span>
        <h3 className="mt-4 text-base font-semibold text-ink">{service.title}</h3>
        {service.short_description ? (
          <p className="mt-2 flex-1 text-sm text-ink-muted">{service.short_description}</p>
        ) : null}
        <Link
          to="/services"
          className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
        >
          Learn more →
        </Link>
      </div>
    </article>
  )
}

export function TeamCard({ profile }) {
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
        {profile.position ? <p className="mt-0.5 text-sm font-medium text-azure-700">{profile.position}</p> : null}
        {profile.specialization ? (
          <p className="mt-1 text-xs text-ink-muted">{profile.specialization}</p>
        ) : null}
        {profile.bio ? <p className="mt-3 line-clamp-3 text-sm text-ink-muted">{profile.bio}</p> : null}
      </div>
    </article>
  )
}

export function ArticleCard({ article }) {
  return (
    <article className="flex flex-col overflow-hidden rounded-3xl border border-line bg-surface shadow-[var(--shadow-card)] transition-transform duration-300 hover:-translate-y-1">
      <Link to={`/health-news/${article.slug}`} className="block">
        {article.cover?.url ? (
          <img src={article.cover.url} alt={article.title} loading="lazy" className="h-44 w-full object-cover" />
        ) : (
          <div className="h-44 w-full bg-azure-50" />
        )}
      </Link>
      <div className="flex flex-1 flex-col p-6">
        {article.category ? (
          <span className="text-xs font-semibold uppercase tracking-wide text-azure-600">
            {article.category}
          </span>
        ) : null}
        <h3 className="mt-1 text-base font-semibold text-ink">
          <Link to={`/health-news/${article.slug}`} className="hover:text-azure-700">
            {article.title}
          </Link>
        </h3>
        {article.excerpt ? (
          <p className="mt-2 line-clamp-3 flex-1 text-sm text-ink-muted">{article.excerpt}</p>
        ) : null}
        <p className="mt-4 text-xs text-ink-muted">{formatDate(article.published_at)}</p>
      </div>
    </article>
  )
}
