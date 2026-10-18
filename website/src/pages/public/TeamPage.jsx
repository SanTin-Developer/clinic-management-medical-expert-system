import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight, ChevronDown, Plus } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { cn } from '../../utils/cn.js'
import { Reveal } from '../../components/public/Reveal.jsx'
import { Section, SectionHeading } from '../../components/public/PublicUI.jsx'
import TeamGrid from '../../components/public/TeamGrid.jsx'

/**
 * Our Team positioning copy (developer-locked brand copy). These are
 * intentional statements — not verified facts. Everything factual and
 * variable (names, positions, specializations, bios, photos, contact
 * details) is loaded from the CMS through the existing public team
 * endpoint.
 */
const TEAM_EYEBROW = 'Get to Know Us'
const TEAM_HEADING = 'Our Team'
const TEAM_DESCRIPTION =
  'Meet the dedicated professionals committed to your health and wellbeing.'
const PEOPLE_HEADING = 'The People Behind Your Care'
const PEOPLE_LEAD =
  'Meet the dedicated professionals committed to providing compassionate, patient-focused healthcare.'
const CLOSING = {
  heading: 'Book With Our Team',
  text: 'Choose a service and reach out — we will help you find the right care for you and your family.',
}

/**
 * Splits the locked hero heading at its first space so the trailing word
 * renders as the gradient accent. The copy above stays the single source of
 * truth — this only decides where the line break falls.
 */
const HEADING_SPACE = TEAM_HEADING.indexOf(' ')
const HEADING_LEAD = HEADING_SPACE > 0 ? TEAM_HEADING.slice(0, HEADING_SPACE) : TEAM_HEADING
const HEADING_ACCENT = HEADING_SPACE > 0 ? TEAM_HEADING.slice(HEADING_SPACE + 1).trim() : ''

/**
 * Dark running ticker under the hero: every specialization found in the live
 * team data drifts across the band, separated by accent plus glyphs.
 * Duplicated once so the existing -50% marquee keyframe loops seamlessly;
 * decorative, so it is hidden from assistive tech (the filter and grid
 * below carry the same information).
 */
function SpecializationTicker({ items }) {
  if (items.length < 2) return null

  return (
    <div aria-hidden className="overflow-hidden border-y border-white/10 bg-white/[0.04] py-4">
      <div className="marquee-track flex w-max items-center gap-8 pr-8">
        {[...items, ...items].map((item, index) => (
          <span
            key={`${item}-${index}`}
            className="flex items-center gap-3 whitespace-nowrap text-sm font-semibold uppercase tracking-[0.16em] text-white/50"
          >
            <Plus className="size-3.5 shrink-0 text-accent" aria-hidden />
            {item}
          </span>
        ))}
      </div>
    </div>
  )
}

export default function TeamPage() {
  const [specialization, setSpecialization] = useState('')

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'team'],
    queryFn: publicWebsiteApi.listTeam,
  })

  const team = data?.data ?? []

  // Live, CMS-derived values only — no invented counts.
  const specializations = useMemo(
    () => [...new Set(team.map((member) => member.specialization?.trim()).filter(Boolean))],
    [team],
  )
  const photoMembers = useMemo(
    () => team.filter((member) => member.photo?.url).slice(0, 4),
    [team],
  )
  const filtered = specialization
    ? team.filter((member) => (member.specialization ?? '').trim() === specialization)
    : team

  return (
    <>
      {/* Section 1 — Cinematic hero: headline, live member count with an
          avatar cluster, and a specialization ticker built from the data. */}
      <section className="relative isolate overflow-hidden bg-[#06243d]">
        <span
          aria-hidden
          className="animate-aurora absolute -left-28 -top-24 size-[440px] rounded-full bg-azure-500/35 blur-[120px]"
        />
        <span
          aria-hidden
          className="animate-aurora absolute -right-24 top-1/3 size-[360px] rounded-full bg-accent/25 blur-[110px]"
          style={{ animationDelay: '-7s' }}
        />
        <span aria-hidden className="auth-grid absolute inset-0 opacity-40" />

        <Section reveal={false} className="relative pb-14 pt-32 text-center sm:pt-40">
          <Reveal>
            <span className="inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.28em] text-white/80 backdrop-blur">
              <span className="relative flex size-1.5">
                <span
                  aria-hidden
                  className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75"
                />
                <span className="relative inline-flex size-1.5 rounded-full bg-accent" />
              </span>
              {TEAM_EYEBROW}
            </span>
          </Reveal>

          <Reveal delay={90}>
            <h1 className="mx-auto mt-6 font-display text-[clamp(36px,5vw,62px)] font-extrabold leading-[1.06] tracking-[-0.02em]">
              <span className="text-white">{HEADING_LEAD} </span>
              {HEADING_ACCENT ? (
                <span className="bg-gradient-to-r from-[#4CAF50] to-[#66BB6A] bg-clip-text text-transparent">
                  {HEADING_ACCENT}
                </span>
              ) : null}
            </h1>
          </Reveal>

          <Reveal delay={170}>
            <p className="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">
              {TEAM_DESCRIPTION}
            </p>
          </Reveal>

          {!isLoading && !isError && team.length > 0 ? (
            <Reveal delay={240}>
              <div className="mt-9 flex flex-wrap items-center justify-center gap-4">
                {photoMembers.length > 0 ? (
                  <div className="flex -space-x-3">
                    {photoMembers.map((member) => (
                      <span
                        key={member.id}
                        className="size-12 overflow-hidden rounded-full border-2 border-white/60 shadow-[0_10px_24px_-10px_rgba(0,0,0,0.7)]"
                      >
                        <img
                          src={member.photo.url}
                          alt=""
                          className="h-full w-full object-cover"
                        />
                      </span>
                    ))}
                  </div>
                ) : null}
                <p className="text-sm text-white/70">
                  <span className="font-display font-bold text-white">{team.length}</span>{' '}
                  {team.length === 1 ? 'team member' : 'team members'}
                </p>
              </div>
            </Reveal>
          ) : null}

          <a
            href="#team"
            aria-label="Scroll to team profiles"
            className="mx-auto mt-10 hidden w-fit text-white/45 transition-colors hover:text-white sm:block"
          >
            <ChevronDown className="size-6 animate-bounce" aria-hidden />
          </a>
        </Section>

        <SpecializationTicker items={specializations} />
      </section>

      {/* Section 2 — The people: centered heading, live specialization
          filter chips, and the editorial profile grid. */}
      <section id="team" className="scroll-mt-24 bg-white py-16 sm:py-20">
        <Section reveal={false}>
          <Reveal>
            <SectionHeading
              align="center"
              eyebrow="01 — The People"
              title={PEOPLE_HEADING}
              description={PEOPLE_LEAD}
            />
          </Reveal>

          {isLoading ? (
            <div className="grid place-items-center py-16">
              <Spinner label="Loading team…" />
            </div>
          ) : isError ? (
            <ErrorState onRetry={refetch} />
          ) : team.length === 0 ? (
            <EmptyState title="No team members yet" message="Our team profiles are coming soon." />
          ) : (
            <>
              {specializations.length > 0 ? (
                <Reveal delay={90}>
                  <div className="mt-8 flex flex-wrap justify-center gap-2">
                    <button
                      type="button"
                      onClick={() => setSpecialization('')}
                      className={cn(
                        'rounded-full border px-4 py-2 text-sm font-medium transition-colors',
                        !specialization
                          ? 'border-accent bg-accent text-accent-ink shadow-[0_12px_28px_-14px_rgba(76,175,80,0.8)]'
                          : 'border-line bg-surface text-ink-muted hover:border-azure-300 hover:text-ink',
                      )}
                    >
                      All
                    </button>
                    {specializations.map((spec) => (
                      <button
                        key={spec}
                        type="button"
                        onClick={() => setSpecialization(spec === specialization ? '' : spec)}
                        className={cn(
                          'rounded-full border px-4 py-2 text-sm font-medium transition-colors',
                          specialization === spec
                            ? 'border-accent bg-accent text-accent-ink shadow-[0_12px_28px_-14px_rgba(76,175,80,0.8)]'
                            : 'border-line bg-surface text-ink-muted hover:border-azure-300 hover:text-ink',
                        )}
                      >
                        {spec}
                      </button>
                    ))}
                  </div>
                </Reveal>
              ) : null}

              <div className="mt-10 sm:mt-14">
                {filtered.length === 0 ? (
                  <EmptyState
                    title="No profiles in this area yet"
                    message={`No team members are listed under “${specialization}” right now.`}
                  />
                ) : (
                  <TeamGrid members={filtered} />
                )}
              </div>
            </>
          )}
        </Section>
      </section>

      {/* Section 3 — Closing CTA: dark bookend echoing the hero. */}
      {!isLoading && !isError && team.length > 0 ? (
        <section className="relative isolate overflow-hidden bg-[#06243d]">
          <span
            aria-hidden
            className="animate-aurora absolute left-1/2 top-0 size-[520px] -translate-x-1/2 -translate-y-1/3 rounded-full bg-azure-500/30 blur-[130px]"
          />
          <span
            aria-hidden
            className="absolute left-1/2 top-1/2 size-[520px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/10"
          />
          <span
            aria-hidden
            className="absolute left-1/2 top-1/2 size-[840px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/5"
          />
          <span aria-hidden className="auth-grid absolute inset-0 opacity-30" />

          <Section reveal={false} className="relative py-20 text-center sm:py-24">
            <Reveal className="mx-auto max-w-2xl text-white">
              <p className="text-xs font-bold uppercase tracking-[0.28em] text-white/60">
                02 — Next Steps
              </p>
              <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight sm:text-4xl">
                {CLOSING.heading}
              </h2>
              <p className="mt-4 text-sm leading-relaxed text-white/75 sm:text-base">
                {CLOSING.text}
              </p>
              <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link
                  to="/contact"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink transition-colors hover:bg-accent-dark sm:w-auto"
                >
                  Book an Appointment
                  <ArrowRight className="size-4" aria-hidden />
                </Link>
                <Link
                  to="/services"
                  className="inline-flex w-full items-center justify-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20 sm:w-auto"
                >
                  Browse Services
                </Link>
              </div>
            </Reveal>
          </Section>
        </section>
      ) : null}
    </>
  )
}
