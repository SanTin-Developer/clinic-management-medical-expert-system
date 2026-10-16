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
  lab: Microscope,
  pill: Pill,
  scan: Scan,
  stethoscope: Stethoscope,
  syringe: Syringe,
}

export function resolveIcon(name) {
  return ICONS[String(name ?? '').toLowerCase()] ?? Stethoscope
}

export function Section({ children, className }) {
  return (
    <section className={cn('mx-auto w-full max-w-6xl px-4 sm:px-6', className)}>{children}</section>
  )
}

export function PageBanner({ title, description, image }) {
  return (
    <section className="relative overflow-hidden bg-slate-900 text-white">
      {image ? <img src={image} alt="" className="absolute inset-0 size-full object-cover opacity-30" /> : null}
      <div className="absolute inset-0 bg-gradient-to-r from-slate-950/90 to-slate-900/70" />
      <Section className="relative py-14 sm:py-20">
        <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">{title}</h1>
        {description ? <p className="mt-3 max-w-2xl text-sm text-slate-200 sm:text-base">{description}</p> : null}
      </Section>
    </section>
  )
}

export function SectionHeading({ eyebrow, title, description, align = 'left' }) {
  return (
    <div className={cn('max-w-2xl', align === 'center' && 'mx-auto text-center')}>
      {eyebrow ? (
        <p className="text-sm font-semibold uppercase tracking-wide text-brand">{eyebrow}</p>
      ) : null}
      <h2 className="mt-1 text-2xl font-bold tracking-tight text-ink sm:text-3xl">{title}</h2>
      {description ? <p className="mt-3 text-sm text-ink-muted sm:text-base">{description}</p> : null}
    </div>
  )
}

export function ServiceCard({ service }) {
  const Icon = resolveIcon(service.icon)
  return (
    <article className="group flex flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-[var(--shadow-card)] transition-shadow hover:shadow-lg">
      {service.image?.url ? (
        <img src={service.image.url} alt={service.title} className="h-44 w-full object-cover" />
      ) : (
        <div className="grid h-44 w-full place-items-center bg-brand-soft">
          <Icon className="size-12 text-brand" aria-hidden />
        </div>
      )}
      <div className="flex flex-1 flex-col p-5">
        <span className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
          <Icon className="size-5" aria-hidden />
        </span>
        <h3 className="mt-4 text-base font-semibold text-ink">{service.title}</h3>
        {service.short_description ? (
          <p className="mt-2 flex-1 text-sm text-ink-muted">{service.short_description}</p>
        ) : null}
        <Link to="/services" className="mt-4 inline-flex text-sm font-semibold text-brand hover:text-brand-dark">
          Learn more →
        </Link>
      </div>
    </article>
  )
}

export function TeamCard({ profile }) {
  return (
    <article className="flex flex-col overflow-hidden rounded-2xl border border-line bg-surface text-center shadow-[var(--shadow-card)]">
      {profile.photo?.url ? (
        <img src={profile.photo.url} alt={profile.name} className="h-56 w-full object-cover" />
      ) : (
        <div className="grid h-56 w-full place-items-center bg-brand-soft text-2xl font-bold text-brand">
          {profile.name?.charAt(0) ?? '?'}
        </div>
      )}
      <div className="flex flex-1 flex-col p-5">
        <h3 className="text-base font-semibold text-ink">{profile.name}</h3>
        {profile.position ? <p className="mt-0.5 text-sm font-medium text-brand">{profile.position}</p> : null}
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
    <article className="flex flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-[var(--shadow-card)] transition-shadow hover:shadow-lg">
      <Link to={`/health-news/${article.slug}`} className="block">
        {article.cover?.url ? (
          <img src={article.cover.url} alt={article.title} className="h-44 w-full object-cover" />
        ) : (
          <div className="h-44 w-full bg-brand-soft" />
        )}
      </Link>
      <div className="flex flex-1 flex-col p-5">
        {article.category ? (
          <span className="text-xs font-semibold uppercase tracking-wide text-brand">
            {article.category}
          </span>
        ) : null}
        <h3 className="mt-1 text-base font-semibold text-ink">
          <Link to={`/health-news/${article.slug}`} className="hover:text-brand">
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
