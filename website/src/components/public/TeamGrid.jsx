import { Mail, Phone } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { Reveal } from './Reveal.jsx'

function initialsOf(name) {
  const parts = String(name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase()
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
}

/**
 * Editorial profile card for the "Our Team" page. Portrait lead-in with a navy
 * scrim, a specialization chip, and a contact row — all built from the existing
 * team data fields so nothing is invented and missing data degrades gracefully.
 */
export function TeamProfileCard({ member }) {
  const hasContact = Boolean(member.email || member.phone)

  return (
    <article
      className={cn(
        'group relative flex h-full flex-col overflow-hidden rounded-[26px] border border-line bg-surface',
        'shadow-[var(--shadow-card)] transition-all duration-500',
        'hover:-translate-y-1.5 hover:border-azure-200 hover:shadow-[var(--shadow-card-hover)]',
      )}
    >
      <span
        aria-hidden
        className="absolute inset-x-0 top-0 z-20 h-1 origin-left scale-x-0 bg-gradient-to-r from-azure-500 via-azure-600 to-navy-600 transition-transform duration-500 group-hover:scale-x-100"
      />

      <div className="relative aspect-[4/5] w-full overflow-hidden bg-gradient-to-br from-navy-600 via-azure-600 to-azure-400">
        {member.photo?.url ? (
          <>
            <img
              src={member.photo.url}
              alt={member.name ?? 'Team member'}
              loading="lazy"
              className="h-full w-full object-cover object-top transition-transform duration-[900ms] ease-out group-hover:scale-[1.06]"
            />
            <div
              aria-hidden
              className="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy-700/85 via-navy-700/25 to-transparent"
            />
          </>
        ) : (
          <>
            <span aria-hidden className="auth-grid absolute inset-0 opacity-50" />
            <span
              aria-hidden
              className="pointer-events-none absolute -top-12 left-1/2 size-56 -translate-x-1/2 rounded-full bg-white/15 blur-2xl"
            />
            <span
              aria-hidden
              className="pointer-events-none absolute inset-0 bg-gradient-to-t from-navy-700/80 via-transparent to-transparent"
            />
            <div className="relative grid h-full w-full place-items-center">
              <span className="grid size-24 place-items-center rounded-full border border-white/30 bg-white/10 font-display text-3xl font-bold tracking-wide text-white shadow-[0_18px_40px_-18px_rgba(0,0,0,0.6)] backdrop-blur transition-transform duration-500 group-hover:scale-105">
                {initialsOf(member.name)}
              </span>
            </div>
          </>
        )}

        {member.specialization ? (
          <span className="absolute left-4 top-4 rounded-full border border-white/40 bg-white/85 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.14em] text-azure-700 backdrop-blur">
            {member.specialization}
          </span>
        ) : null}

        <div className="absolute inset-x-0 bottom-0 p-5">
          <h3 className="font-display text-lg font-bold leading-tight text-white">{member.name}</h3>
          {member.position ? (
            <p className="mt-0.5 text-sm font-medium text-azure-100">{member.position}</p>
          ) : null}
        </div>
      </div>

      <div className="flex flex-1 flex-col p-5">
        {member.bio ? (
          <p className="line-clamp-3 text-sm leading-relaxed text-ink-muted">{member.bio}</p>
        ) : null}

        {hasContact ? (
          <div className="mt-auto flex flex-wrap items-center gap-2 pt-4">
            {member.email ? (
              <a
                href={`mailto:${member.email}`}
                className="inline-flex items-center gap-1.5 rounded-full bg-azure-50 px-3 py-1.5 text-xs font-semibold text-azure-700 transition-colors hover:bg-azure-100"
              >
                <Mail className="size-3.5" aria-hidden /> Email
              </a>
            ) : null}
            {member.phone ? (
              <a
                href={`tel:${member.phone}`}
                className="inline-flex items-center gap-1.5 rounded-full bg-azure-50 px-3 py-1.5 text-xs font-semibold text-azure-700 transition-colors hover:bg-azure-100"
              >
                <Phone className="size-3.5" aria-hidden /> Call
              </a>
            ) : null}
          </div>
        ) : null}
      </div>
    </article>
  )
}

/** Responsive grid of team profile cards with a staggered scroll reveal. */
export default function TeamGrid({ members }) {
  return (
    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
      {members.map((member, index) => (
        <Reveal key={member.id ?? index} delay={(index % 3) * 80} className="h-full">
          <TeamProfileCard member={member} />
        </Reveal>
      ))}
    </div>
  )
}
