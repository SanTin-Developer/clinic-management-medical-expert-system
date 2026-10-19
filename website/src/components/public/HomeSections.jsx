import { Fragment } from 'react'
import { Link } from 'react-router-dom'
import {
  ArrowRight,
  Calendar,
  CheckCircle2,
  Clock,
  HeartPulse,
  MapPin,
  ShieldCheck,
  Users,
} from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { Reveal } from './Reveal.jsx'
import {
  ABOUT_CLINIC_IMAGE,
  DEFAULT_HERO_IMAGE,
  HERO_DESCRIPTION,
  HERO_EYEBROW,
  HERO_HEADING,
  HERO_HIGHLIGHT,
  HERO_STATS,
  PARTNER_PLACEHOLDERS,
} from '../../constants/index.js'

const ABOUT_PILLARS = [
  'Patient-first, respectful care',
  'Experienced clinical team',
  'Modern, comfortable facilities',
  'Clear communication at every step',
]

const STAT_ICONS = {
  patients: Users,
  doctors: HeartPulse,
  hours: Clock,
}

/**
 * Solid (filled) partner glyphs rendered via `fill="currentColor"` so the strip
 * uses filled marks rather than the default outline icon style.
 */
const PARTNER_GLYPHS = {
  leaf: 'M6.05 8.05C3.32 10.78 3.32 15.2 6.03 17.93c1.47-3.4 4.09-6.24 7.36-7.93-2.77 2.34-4.71 5.61-5.39 9.32 2.6 1.23 5.8.78 7.95-1.37C19.43 14.47 20 4 20 4S9.53 4.57 6.05 8.05z',
  plus: 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
  heart:
    'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
  wave: 'M17 16.99c-1.35 0-2.2.42-2.95.8-.65.33-1.18.6-2.05.6-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8s-2.2.42-2.95.8c-.65.32-1.18.6-2.05.6v1.95c1.35 0 2.2-.42 2.95-.8.65-.33 1.17-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.57.8 2.95.8s2.2-.42 2.95-.8c.65-.32 1.18-.6 2.05-.6.9 0 1.41.25 2.05.6.75.38 1.57.8 2.95.8v-1.95c-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8zm0-4.45c-1.35 0-2.2.43-2.95.8-.65.32-1.18.6-2.05.6-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8s-2.2.43-2.95.8c-.65.32-1.17.6-2.05.6v1.95c1.35 0 2.2-.43 2.95-.8.65-.33 1.17-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.57.8 2.95.8s2.2-.43 2.95-.8c.65-.32 1.18-.6 2.05-.6.9 0 1.41.25 2.05.6.75.38 1.57.8 2.95.8V13.3c-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8zm2.95-9.55c-.75-.38-1.57-.8-2.95-.8s-2.2.42-2.95.8c-.65.32-1.18.6-2.05.6-.9 0-1.4-.25-2.05-.6-.75-.38-1.57-.8-2.95-.8s-2.2.42-2.95.8c-.65.33-1.17.6-2.05.6v1.95c1.35 0 2.2-.43 2.95-.8.65-.33 1.17-.6 2.05-.6s1.4.25 2.05.6c.75.38 1.57.8 2.95.8s2.2-.42 2.95-.8c.65-.32 1.18-.6 2.05-.6.9 0 1.41.25 2.05.6.75.38 1.57.8 2.95.8V4c-.9 0-1.4-.25-2.05-.6z',
}

function PartnerGlyph({ name, className }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden className={className}>
      <path d={PARTNER_GLYPHS[name] ?? PARTNER_GLYPHS.leaf} />
    </svg>
  )
}

function StatCard({ stat, className, style }) {
  const Icon = STAT_ICONS[stat.key] ?? ShieldCheck
  return (
    <article
      style={style}
      className={cn(
        'flex w-full min-[720px]:w-[210px] items-start gap-3 rounded-[20px] border border-white/50 bg-white/65 p-4 shadow-[0_10px_30px_rgba(11,74,143,0.08)] backdrop-blur-[12px]',
        className,
      )}
    >
      <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/70 text-[#1E88C8]">
        <Icon className="size-5" aria-hidden />
      </span>
      <div className="min-w-0">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-[#4A5568]">{stat.title}</p>
        <p className="text-xl font-extrabold leading-tight text-[#0B4A8F]">{stat.value}</p>
        <p className="mt-0.5 text-[11px] text-[#4A5568]/70">{stat.label}</p>
      </div>
    </article>
  )
}

export function HeroSection({ settings = {} }) {
  // API serializes the relation as snake_case (`hero_media`), not `heroMedia`.
  const heroImage = settings.hero_media?.url ?? DEFAULT_HERO_IMAGE
  const eyebrow = settings.tagline?.trim() || HERO_EYEBROW
  const heroTitle = settings.hero_title?.trim()
  const description = settings.hero_subtitle?.trim() || HERO_DESCRIPTION
  const highlight = settings.about_summary?.trim() || HERO_HIGHLIGHT

  return (
    <section className="relative flex min-h-[clamp(640px,52vw,900px)] flex-col overflow-hidden bg-[#F8FBFF]">
      <img
        src={heroImage}
        alt="Smiling RoyalCare Clinic doctor holding a clipboard in the bright clinic lobby"
        className="animate-hero-image-in absolute inset-0 h-full w-full object-cover object-[60%_12%]"
      />
      {/* Desktop: white veil fades left-to-right behind the copy. */}
      <div
        className="absolute inset-0 hidden lg:block"
        style={{
          backgroundImage:
            'linear-gradient(90deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 30%, rgba(255,255,255,0) 55%)',
        }}
        aria-hidden
      />
      {/* Phones/tablets: vertical veil so every line stays readable over the photo. */}
      <div
        className="absolute inset-0 bg-gradient-to-b from-white/90 via-white/75 to-white/55 lg:hidden"
        aria-hidden
      />
      <div
        className="absolute inset-x-0 bottom-0 h-[100px]"
        style={{ backgroundImage: 'linear-gradient(to top, #ffffff, rgba(255,255,255,0))' }}
        aria-hidden
      />

      <div className="reveal reveal-5 pointer-events-none absolute inset-0 hidden min-[1100px]:block">
        <div className="relative mx-auto h-full w-full max-w-[1200px] px-6">
          <StatCard
            stat={HERO_STATS[0]}
            className="animate-float-subtle absolute left-[50%] top-[16%]"
            style={{ animationDelay: '0s' }}
          />
          <div className="absolute right-[3%] top-[46%] z-[5] flex w-[210px] flex-col gap-4">
            <StatCard
              stat={HERO_STATS[1]}
              className="animate-float-subtle relative"
              style={{ animationDelay: '0.9s' }}
            />
            <StatCard
              stat={HERO_STATS[2]}
              className="animate-float-subtle relative"
              style={{ animationDelay: '1.8s' }}
            />
          </div>
        </div>
      </div>

      <div className="relative mx-auto flex w-full max-w-[1200px] flex-1 items-center px-4 pb-[80px] pt-[110px] sm:px-6">
        <div className="mt-6 max-w-[640px]">
          {/* Eyebrow: the CMS tagline in a glass pill with a live pulse dot. */}
          <p className="reveal reveal-1 inline-flex items-center gap-2.5 rounded-full border border-[#1E88C8]/25 bg-white/70 px-4 py-2 text-[13px] font-semibold text-[#0B4A8F] shadow-[0_14px_34px_-22px_rgb(11_74_143_/_0.8)] backdrop-blur-md">
            <span className="relative flex size-2 shrink-0" aria-hidden>
              <span className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75 motion-reduce:hidden" />
              <span className="relative inline-flex size-2 rounded-full bg-accent" />
            </span>
            {eyebrow}
          </p>

          {/* Headline: brand-blue gradient when the CMS supplies a single title. */}
          <h1 className="reveal reveal-2 mt-5 font-display text-[clamp(36px,5vw,66px)] font-extrabold leading-[1.04] tracking-[-0.03em]">
            {heroTitle ? (
              <span className="block bg-gradient-to-r from-[#0B4A8F] via-[#155E9E] to-[#1E88C8] bg-clip-text text-transparent">
                {heroTitle}
              </span>
            ) : (
              <>
                <span className="block text-[#0B4A8F]">{HERO_HEADING[0]}</span>
                <span className="block bg-gradient-to-r from-[#43A047] to-[#66BB6A] bg-clip-text text-transparent sm:whitespace-nowrap">
                  {HERO_HEADING[1]}
                </span>
              </>
            )}
          </h1>

          <p className="reveal reveal-3 mt-5 max-w-[540px] text-[18px] leading-[1.75] text-[#4A5568]">
            {description}
          </p>

          {/* CMS About summary as a glass callout card with green text. */}
          <div className="reveal reveal-4 mt-6 flex max-w-[580px] items-start gap-3 rounded-2xl border border-accent/30 bg-white/75 p-4 shadow-[0_20px_44px_-30px_rgb(11_74_143_/_0.9)] backdrop-blur-md">
            <span className="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-accent/15 text-accent-dark">
              <ShieldCheck className="size-4" aria-hidden />
            </span>
            <p className="text-[15px] font-semibold leading-snug text-[#2E7D32] lg:text-base">{highlight}</p>
          </div>

          {/* Primary (green, matches header CTA) + secondary appointment CTA.
              Stacked full-width on phones, side-by-side from 480px. */}
          <div className="reveal reveal-5 mt-8 flex flex-col gap-3 min-[480px]:flex-row min-[480px]:flex-wrap min-[480px]:items-center min-[480px]:gap-4">
            <Link
              to="/services"
              className="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink shadow-[0_18px_38px_-16px_rgb(76_175_80_/_0.9)] transition hover:-translate-y-0.5 hover:bg-accent-dark min-[480px]:w-auto"
            >
              Explore Our Services
              <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" aria-hidden />
            </Link>
            <Link
              to="/contact"
              className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-[#0B4A8F]/25 bg-white/75 px-6 py-3.5 text-sm font-semibold text-[#0B4A8F] shadow-[var(--shadow-card)] backdrop-blur-md transition hover:-translate-y-0.5 hover:border-[#0B4A8F]/50 hover:bg-white min-[480px]:w-auto"
            >
              <Calendar className="size-4" aria-hidden />
              Book an Appointment
            </Link>
          </div>
        </div>
      </div>

      {/* Stat strip for phones/tablets: tidy 2-up grid, then a scroll row. */}
      <div className="relative pb-10 min-[1100px]:hidden">
        <div className="mx-auto w-full max-w-[1200px] px-4 sm:px-6">
          <div className="grid grid-cols-2 gap-3 min-[720px]:flex min-[720px]:snap-x min-[720px]:overflow-x-auto">
            {HERO_STATS.map((stat, index) => (
              <StatCard
                key={stat.key}
                stat={stat}
                className="animate-float-subtle shrink-0 min-[720px]:snap-start"
                style={{ animationDelay: `${index * 0.8}s` }}
              />
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

export function PartnersStrip() {
  return (
    <section className="border-y border-line bg-white">
      <Reveal className="mx-auto flex w-full max-w-[1200px] flex-col gap-6 px-4 py-8 sm:px-6 lg:flex-row lg:items-center lg:gap-12">
        <p className="shrink-0 text-sm font-medium leading-snug text-[#4A5568]">
          <span className="block">Trusted by Leading</span>
          <span className="block">Partners</span>
        </p>
        <div className="flex flex-1 flex-wrap items-center gap-x-8 gap-y-4 lg:flex-nowrap lg:justify-between">
          {PARTNER_PLACEHOLDERS.map((partner, index) => (
            <Fragment key={partner.key}>
              {index > 0 ? (
                <span className="hidden h-8 w-px shrink-0 bg-line sm:block" aria-hidden />
              ) : null}
              <span className="inline-flex items-center gap-2.5 text-[#7A93B0] opacity-70 transition hover:opacity-100">
                <PartnerGlyph name={partner.icon} className="size-6" />
                <span className="text-[15px] font-semibold">{partner.label}</span>
              </span>
            </Fragment>
          ))}
        </div>
      </Reveal>
    </section>
  )
}

export function AboutSection({ settings = {} }) {
  const summary =
    settings.about_summary ??
    'RoyalCare Clinic brings together experienced clinicians, modern facilities and a patient-first approach to deliver care you and your family can trust.'

  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 lg:py-28">
      <Reveal className="grid items-center gap-12 lg:grid-cols-2 lg:gap-16">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.28em] text-azure-600">About RoyalCare</p>
          <h2 className="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-ink sm:text-4xl lg:text-5xl">
            Compassionate Care, Advanced Medicine
          </h2>
          <p className="mt-5 whitespace-pre-line text-base leading-relaxed text-ink-muted">{summary}</p>

          <ul className="mt-7 grid gap-3 sm:grid-cols-2">
            {ABOUT_PILLARS.map((point) => (
              <li key={point} className="flex items-start gap-3 rounded-2xl bg-canvas px-4 py-3">
                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-azure-500" aria-hidden />
                <span className="text-sm font-medium text-ink">{point}</span>
              </li>
            ))}
          </ul>

          <Link
            to="/about"
            className="mt-8 inline-flex items-center gap-2 rounded-full bg-brand px-6 py-3 text-sm font-semibold text-white transition-transform hover:-translate-y-0.5 hover:bg-brand-dark"
          >
            More about us
            <ArrowRight className="size-4" aria-hidden />
          </Link>
        </div>

        <div className="relative">
          <div className="overflow-hidden rounded-[2rem] shadow-[var(--shadow-float)]">
            <img
              src={ABOUT_CLINIC_IMAGE}
              alt="Inside RoyalCare Clinic"
              loading="lazy"
              className="block h-auto w-full"
            />
          </div>
          <span className="absolute -bottom-5 left-6 hidden items-center gap-3 rounded-2xl bg-accent px-5 py-4 text-accent-ink shadow-[var(--shadow-float)] sm:inline-flex">
            <HeartPulse className="size-6" aria-hidden />
            <span className="text-sm font-bold">Patient-first care</span>
          </span>
        </div>
      </Reveal>
    </section>
  )
}

// Clinic location (used by the home page CTA map).
const CLINIC_COORDS = { lat: 11.589353, lng: 104.928170 }

// Backdrop photo for the home page "Ready to visit us?" banner.
const CTA_BACKGROUND_IMAGE =
  'https://res.cloudinary.com/drercy9vt/image/upload/v1792296154/Home/Clinic%20Management%20System%20and%20Medical%20Expert%20systems/website/media/0afda111-e83c-48c7-898d-172acd364d24.jpg'

export function CtaBanner({ settings = {} }) {
  const phone = settings.primary_phone ?? settings.secondary_phone
  const address = settings.address

  // Keyless Google Maps embed pinned to the clinic's exact coordinates, with
  // the address as the search label so the pin is labelled correctly.
  const coords = `${CLINIC_COORDS.lat},${CLINIC_COORDS.lng}`
  const mapQuery = encodeURIComponent(address ? `${address} (${coords})` : coords)
  const mapSrc = `https://maps.google.com/maps?q=${mapQuery}&z=16&ll=${coords}&output=embed`
  const directionsHref = `https://www.google.com/maps/dir/?api=1&destination=${coords}`

  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6">
      <Reveal className="brand-gradient-deep hero-glow relative overflow-hidden rounded-hero px-8 py-14">
        {/* Clinic photo backdrop, dimmed so the white copy and map stay readable. */}
        <div
          className="absolute inset-0 bg-cover bg-center"
          style={{ backgroundImage: `url("${CTA_BACKGROUND_IMAGE}")` }}
          aria-hidden
        />
        <div
          className="absolute inset-0 bg-gradient-to-r from-[#0a2d4e]/92 via-[#0a2d4e]/78 to-[#0f4c7a]/60"
          aria-hidden
        />
        <div className="relative z-10 grid items-center gap-10 lg:grid-cols-2 lg:gap-14">
          <div className="text-center text-white lg:text-left">
            <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">Ready to visit us?</h2>
            <p className="mt-3 text-sm text-white/80 sm:text-base">
              Reach out to schedule an appointment or ask us anything about our services.
            </p>
            <div className="mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row lg:justify-start">
              <Link
                to="/contact"
                className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-6 py-3.5 text-sm font-semibold text-accent-ink transition-colors hover:bg-accent-dark sm:w-auto"
              >
                Contact Us
              </Link>
              {phone ? (
                <a
                  href={`tel:${phone}`}
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/40 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20 sm:w-auto"
                >
                  <HeartPulse className="size-4" aria-hidden />
                  Call {phone}
                </a>
              ) : null}
            </div>
          </div>

          <div className="overflow-hidden rounded-3xl border border-white/25 bg-white/10 p-2 shadow-[var(--shadow-float)] backdrop-blur">
            <div className="relative h-64 w-full overflow-hidden rounded-2xl sm:h-72">
              <iframe
                title="Clinic location map"
                src={mapSrc}
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
                className="absolute inset-0 h-full w-full border-0"
              />
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-3 text-white">
              {address ? (
                <span className="inline-flex min-w-0 items-center gap-2 text-sm">
                  <MapPin className="size-4 shrink-0" aria-hidden />
                  <span className="truncate">{address}</span>
                </span>
              ) : (
                <span />
              )}
              <a
                href={directionsHref}
                target="_blank"
                rel="noreferrer"
                className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-white/25"
              >
                Get directions
                <ArrowRight className="size-3.5" aria-hidden />
              </a>
            </div>
          </div>
        </div>
      </Reveal>
    </section>
  )
}
