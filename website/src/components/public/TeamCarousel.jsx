import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { ChevronLeft, ChevronRight, Mail, Phone } from 'lucide-react'
import { cn } from '../../utils/cn.js'
import { localized } from '../../utils/content.js'
import { useLanguage } from '../../hooks/useLanguage.js'

const AUTOPLAY_MS = 3000
const SWIPE_THRESHOLD = 48

/**
 * Per-breakpoint card geometry. `max` is how many cards are visible on each
 * side of the active profile.
 */
const SIZES = {
  lg: { width: 340, height: 486, x1: 0.62, x2: 1.04, s1: 0.86, s2: 0.7, rotate: 15, max: 2 },
  md: { width: 300, height: 444, x1: 0.64, x2: 1.02, s1: 0.85, s2: 0.7, rotate: 11, max: 1 },
  sm: { width: 288, height: 432, x1: 0.6, x2: 0.98, s1: 0.85, s2: 0.7, rotate: 0, max: 0 },
}

function sizeForViewport() {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return 'lg'
  }
  if (window.matchMedia('(min-width: 1024px)').matches) return 'lg'
  if (window.matchMedia('(min-width: 640px)').matches) return 'md'
  return 'sm'
}

/**
 * Distance between a card and the active profile, folded to the shortest path
 * so the carousel wraps seamlessly in both directions.
 */
function wrapOffset(index, active, total) {
  let distance = index - active
  const half = Math.floor(total / 2)
  if (distance > half) distance -= total
  if (distance < -half) distance += total
  return distance
}

function initialsOf(name) {
  const parts = String(name ?? '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase()
  return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase()
}

/** Maps a card's offset to its transform/opacity/stacking style. */
function placement(offset, config) {
  const distance = Math.abs(offset)
  const sign = offset === 0 ? 1 : Math.sign(offset)
  const yaw = -sign * config.rotate
  const translate = (factor) => `translate3d(${sign * factor * config.width}px, 0, 0)`

  if (distance === 0) {
    return {
      transform: 'translate3d(0, 0, 0) rotateY(0deg) scale(1)',
      opacity: 1,
      zIndex: 40,
      pointerEvents: 'auto',
    }
  }

  if (distance <= config.max) {
    const factor = distance === 1 ? config.x1 : config.x2
    const scale = distance === 1 ? config.s1 : config.s2
    return {
      transform: `${translate(factor)} rotateY(${yaw}deg) scale(${scale})`,
      opacity: distance === 1 ? 0.92 : 0.45,
      zIndex: distance === 1 ? 30 : 20,
      pointerEvents: 'auto',
    }
  }

  // Parked just beyond the outermost visible card. Kept invisible so it can
  // silently jump across the infinite boundary.
  const outerFactor = config.max >= 2 ? config.x2 : config.x1
  const park = outerFactor * 1.3
  return {
    transform: `${translate(park)} rotateY(${yaw}deg) scale(${Math.max(0.62, config.s2 - 0.08)})`,
    opacity: 0,
    zIndex: 10,
    pointerEvents: 'none',
  }
}

export default function TeamCarousel({ members }) {
  const { language, t } = useLanguage()
  const total = members.length
  const [active, setActive] = useState(0)
  const [tabHidden, setTabHidden] = useState(false)
  const [size, setSize] = useState(sizeForViewport)
  const [instant, setInstant] = useState(() => new Set())

  const dragRef = useRef(null)
  const previousOffsets = useRef([])

  // Track the responsive breakpoint bucket.
  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return undefined
    }

    const large = window.matchMedia('(min-width: 1024px)')
    const small = window.matchMedia('(min-width: 640px)')
    const update = () => setSize(sizeForViewport())
    update()
    large.addEventListener('change', update)
    small.addEventListener('change', update)

    return () => {
      large.removeEventListener('change', update)
      small.removeEventListener('change', update)
    }
  }, [])

  const goTo = useCallback(
    (index) => setActive(((index % total) + total) % total),
    [total],
  )
  const next = useCallback(() => setActive((current) => (current + 1) % total), [total])
  const prev = useCallback(() => setActive((current) => (current - 1 + total) % total), [total])

  // A single autoplay timer that restarts whenever the active profile changes,
  // so manual navigation resets the delay. It keeps running while the pointer
  // rests on the carousel and only freezes when the browser tab is hidden.
  useEffect(() => {
    if (tabHidden || total <= 1) {
      return undefined
    }

    const timer = window.setTimeout(() => {
      setActive((current) => (current + 1) % total)
    }, AUTOPLAY_MS)

    return () => window.clearTimeout(timer)
  }, [active, tabHidden, total])

  // Freeze autoplay while the browser tab is in the background.
  useEffect(() => {
    const onVisibilityChange = () => setTabHidden(document.hidden)
    document.addEventListener('visibilitychange', onVisibilityChange)
    return () => document.removeEventListener('visibilitychange', onVisibilityChange)
  }, [])

  const offsets = useMemo(
    () => members.map((_, index) => wrapOffset(index, active, total)),
    [members, active, total],
  )

  // A card that crosses the infinite boundary (e.g. far-left to far-right) is
  // moved instantly while invisible, instead of sliding across the stage.
  useLayoutEffect(() => {
    const jumping = new Set()
    offsets.forEach((offset, index) => {
      const before = previousOffsets.current[index]
      if (before !== undefined && Math.abs(offset - before) > 1) {
        jumping.add(index)
      }
    })
    previousOffsets.current = offsets
    if (jumping.size > 0) {
      setInstant(jumping)
    }
  }, [offsets])

  useEffect(() => {
    if (instant.size === 0) return undefined
    const frame = window.requestAnimationFrame(() => setInstant(new Set()))
    return () => window.cancelAnimationFrame(frame)
  }, [instant])

  const config = SIZES[size]

  const handleKeyDown = (event) => {
    if (event.key === 'ArrowRight') {
      event.preventDefault()
      next()
    } else if (event.key === 'ArrowLeft') {
      event.preventDefault()
      prev()
    }
  }

  const handlePointerDown = (event) => {
    dragRef.current = { x: event.clientX, y: event.clientY }
  }

  const handlePointerUp = (event) => {
    const start = dragRef.current
    dragRef.current = null
    if (!start) return

    const deltaX = event.clientX - start.x
    const deltaY = event.clientY - start.y
    if (Math.abs(deltaX) < SWIPE_THRESHOLD || Math.abs(deltaX) < Math.abs(deltaY)) {
      return
    }

    if (deltaX < 0) next()
    else prev()
  }

  return (
    <div
      className="relative select-none"
      role="group"
      aria-roledescription={t('carousel')}
      aria-label={t('RoyalCare medical team')}
      onKeyDown={handleKeyDown}
    >
      <div
        className="carousel-stage relative mx-auto w-full overflow-hidden"
        style={{ height: config.height + 48 }}
        onPointerDown={handlePointerDown}
        onPointerUp={handlePointerUp}
        onPointerCancel={() => {
          dragRef.current = null
        }}
      >
        <div
          className="pointer-events-none absolute left-1/2 top-1/2 size-[540px] max-w-full -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(closest-side,rgba(30,136,200,0.16),transparent)]"
          aria-hidden
        />

        {members.map((member, index) => {
          const offset = offsets[index]
          const distance = Math.abs(offset)
          const isActive = offset === 0
          const isVisible = distance <= config.max
          const baseStyle = placement(offset, config)
          const style = instant.has(index)
            ? { ...baseStyle, opacity: 0, transition: 'none' }
            : baseStyle

          const name = member.name ?? t('Team member')
          const position = localized(member, 'position', language)
          const specialization = localized(member, 'specialization', language)
          const bio = localized(member, 'bio', language)

          return (
            <article
              key={member.id ?? index}
              className={cn(
                'carousel-card absolute left-1/2 top-6 overflow-hidden rounded-[28px] border bg-surface',
                isActive
                  ? 'border-white/80 shadow-[0_44px_90px_-32px_rgba(11,74,143,0.55)] ring-1 ring-azure-300/50'
                  : 'border-white/60 shadow-[0_26px_60px_-34px_rgba(11,74,143,0.45)]',
              )}
              style={{
                width: config.width,
                height: config.height,
                marginLeft: -config.width / 2,
                ...style,
              }}
              aria-hidden={!isVisible}
            >
              <button
                type="button"
                onClick={() => goTo(index)}
                tabIndex={isVisible ? 0 : -1}
                aria-label={
                  isActive
                    ? t('{name} (current profile)', { name })
                    : t('Show {name}', { name })
                }
                aria-current={isActive ? 'true' : undefined}
                className={cn(
                  'group flex h-full w-full flex-col text-left outline-none',
                  isVisible ? 'cursor-pointer' : 'cursor-default',
                )}
              >
                <div className="relative h-[58%] w-full shrink-0 overflow-hidden bg-azure-50">
                  {member.photo?.url ? (
                    <img
                      src={member.photo.url}
                      alt={name}
                      loading="lazy"
                      className="h-full w-full object-cover object-top transition-transform duration-[900ms] ease-out group-hover:scale-[1.05]"
                    />
                  ) : (
                    <div className="grid h-full w-full place-items-center bg-gradient-to-br from-azure-400 to-navy-600 text-3xl font-bold text-white">
                      {initialsOf(member.name)}
                    </div>
                  )}
                  <div
                    className="pointer-events-none absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-surface via-surface/70 to-transparent"
                    aria-hidden
                  />
                </div>

                <div
                  key={isActive ? `profile-${active}` : 'profile-static'}
                  className="flex flex-1 flex-col overflow-hidden px-6 pb-6 pt-1"
                >
                  <h3
                    className={cn(
                      'font-display font-bold leading-tight text-ink',
                      isActive ? 'animate-fade-in-up text-xl' : 'text-base',
                    )}
                  >
                    {member.name}
                  </h3>
                  {position ? (
                    <p
                      className={cn(
                        'text-sm font-semibold text-azure-700',
                        isActive && 'animate-fade-in-up',
                      )}
                      style={isActive ? { animationDelay: '0.08s' } : undefined}
                    >
                      {position}
                    </p>
                  ) : null}
                  {specialization ? (
                    <p
                      className={cn(
                        'mt-0.5 text-[11px] font-medium uppercase tracking-[0.14em] text-ink-muted',
                        isActive && 'animate-fade-in-up',
                      )}
                      style={isActive ? { animationDelay: '0.16s' } : undefined}
                    >
                      {specialization}
                    </p>
                  ) : null}
                  {isActive && bio ? (
                    <p
                      className="mt-3 line-clamp-2 animate-fade-in-up text-sm leading-relaxed text-ink-muted lg:line-clamp-3"
                      style={{ animationDelay: '0.24s' }}
                    >
                      {bio}
                    </p>
                  ) : null}
                  {isActive && (member.email || member.phone) ? (
                    <div
                      className="mt-4 flex animate-fade-in-up items-center gap-2"
                      style={{ animationDelay: '0.32s' }}
                    >
                      {member.email ? (
                        <a
                          href={`mailto:${member.email}`}
                          onClick={(event) => event.stopPropagation()}
                          className="inline-flex items-center gap-1.5 rounded-full bg-azure-50 px-3 py-1.5 text-xs font-semibold text-azure-700 transition-colors hover:bg-azure-100"
                        >
                          <Mail className="size-3.5" aria-hidden /> {t('Email')}
                        </a>
                      ) : null}
                      {member.phone ? (
                        <a
                          href={`tel:${member.phone}`}
                          onClick={(event) => event.stopPropagation()}
                          className="inline-flex items-center gap-1.5 rounded-full bg-azure-50 px-3 py-1.5 text-xs font-semibold text-azure-700 transition-colors hover:bg-azure-100"
                        >
                          <Phone className="size-3.5" aria-hidden /> {t('Call')}
                        </a>
                      ) : null}
                    </div>
                  ) : null}
                </div>
              </button>
            </article>
          )
        })}
      </div>

      {total > 1 ? (
        <>
          <button
            type="button"
            onClick={prev}
            aria-label={t('Show previous team member')}
            className="absolute left-0 top-1/2 z-50 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/70 bg-white/90 text-azure-700 shadow-[0_18px_36px_-16px_rgba(11,74,143,0.45)] backdrop-blur transition-all duration-300 hover:scale-105 hover:bg-white hover:text-azure-600 active:scale-95 sm:left-2"
          >
            <ChevronLeft className="size-5" aria-hidden />
          </button>
          <button
            type="button"
            onClick={next}
            aria-label={t('Show next team member')}
            className="absolute right-0 top-1/2 z-50 grid size-11 -translate-y-1/2 place-items-center rounded-full border border-white/70 bg-white/90 text-azure-700 shadow-[0_18px_36px_-16px_rgba(11,74,143,0.45)] backdrop-blur transition-all duration-300 hover:scale-105 hover:bg-white hover:text-azure-600 active:scale-95 sm:right-2"
          >
            <ChevronRight className="size-5" aria-hidden />
          </button>
        </>
      ) : null}

      {total > 1 ? (
        <div
          className="mt-7 flex items-center justify-center gap-2"
          role="group"
          aria-label={t('Choose a team member')}
        >
          {members.map((member, index) => (
            <button
              key={member.id ?? index}
              type="button"
              onClick={() => goTo(index)}
              aria-label={t('Show {name}', { name: member.name ?? t('Team member') })}
              aria-current={index === active ? 'true' : undefined}
              className={cn(
                'h-2 rounded-full transition-all duration-300',
                index === active ? 'w-7 bg-azure-600' : 'w-2 bg-line hover:bg-azure-300',
              )}
            />
          ))}
        </div>
      ) : null}

      <p className="sr-only" aria-live="polite">
        {members[active]?.name ?? ''}
      </p>
    </div>
  )
}
