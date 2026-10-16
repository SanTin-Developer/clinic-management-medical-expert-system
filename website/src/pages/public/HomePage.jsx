import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, CalendarClock, CheckCircle2, Phone, ShieldCheck, Sparkles } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { ArticleCard, Section, SectionHeading, ServiceCard, TeamCard } from '../../components/public/PublicUI.jsx'

const HIGHLIGHTS = [
  { icon: ShieldCheck, title: 'Trusted Care', text: 'Qualified, compassionate professionals you can rely on.' },
  { icon: CalendarClock, title: 'Easy Access', text: 'Convenient hours and quick appointment scheduling.' },
  { icon: Sparkles, title: 'Modern Facilities', text: 'Clean, comfortable and well-equipped clinic spaces.' },
]

export default function HomePage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'home'],
    queryFn: publicWebsiteApi.home,
  })

  if (isLoading) {
    return (
      <div className="grid min-h-[60vh] place-items-center">
        <Spinner label="Loading website…" />
      </div>
    )
  }

  if (isError) {
    return (
      <Section className="py-16">
        <ErrorState onRetry={refetch} />
      </Section>
    )
  }

  const settings = data?.settings ?? {}
  const services = data?.services ?? []
  const team = data?.team ?? []
  const articles = data?.articles ?? []
  const gallery = data?.gallery ?? []

  return (
    <>
      <section className="relative overflow-hidden bg-slate-900 text-white">
        {settings.hero_media?.url ? (
          <img
            src={settings.hero_media.url}
            alt=""
            className="absolute inset-0 size-full object-cover opacity-40"
          />
        ) : null}
        <div className="absolute inset-0 bg-gradient-to-r from-slate-950/90 to-slate-900/60" />
        <Section className="relative py-20 sm:py-28">
          <div className="max-w-2xl">
            <p className="text-sm font-semibold uppercase tracking-widest text-brand-soft">
              {settings.tagline ?? 'Welcome'}
            </p>
            <h1 className="mt-3 text-3xl font-bold tracking-tight sm:text-5xl">
              {settings.hero_title ?? settings.site_name ?? 'Quality Healthcare for Everyone'}
            </h1>
            {settings.hero_subtitle ? (
              <p className="mt-5 text-base text-slate-200 sm:text-lg">{settings.hero_subtitle}</p>
            ) : null}
            <div className="mt-8 flex flex-wrap gap-3">
              <Link
                to="/contact"
                className="inline-flex items-center gap-2 rounded-lg bg-brand px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-brand-dark"
              >
                Book an Appointment
                <ArrowRight className="size-4" aria-hidden />
              </Link>
              <Link
                to="/services"
                className="inline-flex items-center gap-2 rounded-lg border border-white/30 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20"
              >
                Our Services
              </Link>
              {settings.primary_phone ? (
                <a
                  href={`tel:${settings.primary_phone}`}
                  className="inline-flex items-center gap-2 rounded-lg px-5 py-3 text-sm font-semibold text-white transition-colors hover:text-brand-soft"
                >
                  <Phone className="size-4" aria-hidden />
                  {settings.primary_phone}
                </a>
              ) : null}
            </div>
          </div>
        </Section>
      </section>

      <Section className="py-14">
        <div className="grid gap-5 sm:grid-cols-3">
          {HIGHLIGHTS.map((item) => (
            <div key={item.title} className="rounded-2xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]">
              <span className="grid size-11 place-items-center rounded-xl bg-brand-soft text-brand">
                <item.icon className="size-5" aria-hidden />
              </span>
              <h3 className="mt-4 text-base font-semibold text-ink">{item.title}</h3>
              <p className="mt-1.5 text-sm text-ink-muted">{item.text}</p>
            </div>
          ))}
        </div>
      </Section>

      {services.length > 0 ? (
        <Section className="py-6">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading eyebrow="What we offer" title="Our Medical Services" />
            <Link to="/services" className="text-sm font-semibold text-brand hover:text-brand-dark">
              View all services →
            </Link>
          </div>
          <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {services.map((service) => (
              <ServiceCard key={service.id} service={service} />
            ))}
          </div>
        </Section>
      ) : null}

      {settings.about_summary ? (
        <Section className="py-14">
          <div className="grid items-center gap-10 rounded-3xl border border-line bg-surface p-8 shadow-[var(--shadow-card)] lg:grid-cols-2 lg:p-12">
            <div>
              <SectionHeading eyebrow="About us" title={`About ${settings.site_name ?? 'our clinic'}`} />
              <p className="mt-4 whitespace-pre-line text-sm text-ink-muted sm:text-base">
                {settings.about_summary}
              </p>
              <Link
                to="/about"
                className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-brand hover:text-brand-dark"
              >
                Read more about us
                <ArrowRight className="size-4" aria-hidden />
              </Link>
            </div>
            <ul className="grid gap-3">
              {['Patient-first approach', 'Experienced medical team', 'Comprehensive care services', 'Comfortable environment'].map(
                (point) => (
                  <li key={point} className="flex items-center gap-3 rounded-xl border border-line bg-canvas px-4 py-3">
                    <CheckCircle2 className="size-5 shrink-0 text-emerald-500" aria-hidden />
                    <span className="text-sm font-medium text-ink">{point}</span>
                  </li>
                ),
              )}
            </ul>
          </div>
        </Section>
      ) : null}

      {team.length > 0 ? (
        <Section className="py-6">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading eyebrow="Meet the experts" title="Our Team" />
            <Link to="/team" className="text-sm font-semibold text-brand hover:text-brand-dark">
              Meet everyone →
            </Link>
          </div>
          <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {team.map((profile) => (
              <TeamCard key={profile.id} profile={profile} />
            ))}
          </div>
        </Section>
      ) : null}

      {articles.length > 0 ? (
        <Section className="py-14">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading eyebrow="Stay informed" title="News & Health Tips" />
            <Link to="/health-news" className="text-sm font-semibold text-brand hover:text-brand-dark">
              Read all articles →
            </Link>
          </div>
          <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {articles.map((article) => (
              <ArticleCard key={article.id} article={article} />
            ))}
          </div>
        </Section>
      ) : null}

      {gallery.length > 0 ? (
        <Section className="py-6">
          <SectionHeading eyebrow="Our facilities" title="A Glimpse Inside" align="center" />
          <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {gallery.map((item) =>
              item.image?.url ? (
                <img
                  key={item.id}
                  src={item.image.url}
                  alt={item.title ?? 'Facility photo'}
                  className="h-40 w-full rounded-2xl object-cover"
                />
              ) : null,
            )}
          </div>
          <div className="mt-8 text-center">
            <Link to="/gallery" className="text-sm font-semibold text-brand hover:text-brand-dark">
              View full gallery →
            </Link>
          </div>
        </Section>
      ) : null}

      <Section className="py-16">
        <div className="rounded-3xl bg-brand px-8 py-12 text-center text-white">
          <h2 className="text-2xl font-bold tracking-tight sm:text-3xl">Ready to visit us?</h2>
          <p className="mx-auto mt-3 max-w-xl text-sm text-white/80 sm:text-base">
            Reach out to schedule an appointment or ask us anything about our services.
          </p>
          <div className="mt-6 flex flex-wrap justify-center gap-3">
            <Link
              to="/contact"
              className="inline-flex items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-semibold text-brand transition-colors hover:bg-white/90"
            >
              Contact Us
            </Link>
            {settings.primary_phone ? (
              <a
                href={`tel:${settings.primary_phone}`}
                className="inline-flex items-center gap-2 rounded-lg border border-white/40 px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/10"
              >
                <Phone className="size-4" aria-hidden />
                Call {settings.primary_phone}
              </a>
            ) : null}
          </div>
        </div>
      </Section>
    </>
  )
}
