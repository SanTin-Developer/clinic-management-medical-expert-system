import { useState } from 'react'
import { Link, useOutletContext } from 'react-router-dom'
import { useMutation } from '@tanstack/react-query'
import { ArrowRight, CheckCircle2, ChevronDown, Clock, Mail, MapPin, Phone, Send } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import Button from '../../components/ui/Button.jsx'
import Input from '../../components/ui/Input.jsx'
import { Reveal } from '../../components/public/Reveal.jsx'
import { Section, SectionHeading } from '../../components/public/PublicUI.jsx'
import { cn } from '../../utils/cn.js'

/**
 * Contact positioning copy (developer-locked brand copy). These are
 * intentional statements — not verified facts. Every channel shown on this
 * page (phone, email, address, hours, map embed) is loaded from the CMS
 * website settings through the existing bootstrap endpoint.
 */
const CONTACT_EYEBROW = 'Get in Touch'
const CONTACT_HEADING = 'Contact Us'
const CONTACT_DESCRIPTION =
  'We are here to help. Reach out to us through any of the channels below.'
const REACH_HEADING = 'Ways to Reach Us'
const REACH_LEAD =
  'Pick whichever is easiest — every channel reaches the same clinic team.'
const FIND_HEADING = 'Visit Our Clinic'

/**
 * Contact form copy (developer-locked). The form posts to the existing
 * public contact endpoint; all validation lives there too.
 */
const SEND_HEADING = 'Send Us a Message'
const SEND_LEAD =
  'Fill the form and your message goes straight to our clinic team — or use any of the channels above.'
const SEND_NOTE = "We'll get back to you as soon as we can."
const SEND_ERROR_FALLBACK = 'Something went wrong. Please try again.'
const SEND_SUCCESS_FALLBACK = 'Thanks! Your message has been sent.'

/**
 * Splits the locked hero heading at its first space so the trailing word
 * renders as the gradient accent. The copy above stays the single source of
 * truth — this only decides where the line break falls.
 */
const HEADING_SPACE = CONTACT_HEADING.indexOf(' ')
const HEADING_LEAD = HEADING_SPACE > 0 ? CONTACT_HEADING.slice(0, HEADING_SPACE) : CONTACT_HEADING
const HEADING_ACCENT = HEADING_SPACE > 0 ? CONTACT_HEADING.slice(HEADING_SPACE + 1).trim() : ''

/**
 * Channel card for the "Ways to Reach Us" grid. Hover lifts the card, sweeps
 * the gradient top bar and fills the icon chip — the same motifs the rest of
 * the redesigned public pages use.
 */
function ChannelCard({ icon: Icon, title, children }) {
  return (
    <article className="group relative flex h-full flex-col overflow-hidden rounded-3xl border border-line bg-surface p-6 shadow-[var(--shadow-card)] transition-all duration-300 hover:-translate-y-1 hover:border-azure-400/70 hover:shadow-[var(--shadow-card-hover)]">
      <span
        aria-hidden
        className="absolute inset-x-0 top-0 h-1 origin-left scale-x-0 bg-gradient-to-r from-azure-500 via-azure-600 to-accent transition-transform duration-500 group-hover:scale-x-100"
      />
      <span className="grid size-12 place-items-center rounded-2xl bg-azure-50 text-azure-600 transition-colors duration-300 group-hover:bg-azure-500 group-hover:text-white">
        <Icon className="size-5" aria-hidden />
      </span>
      <h3 className="mt-4 font-display text-base font-bold text-ink">{title}</h3>
      <div className="mt-2 space-y-1 text-sm leading-relaxed text-ink-muted">{children}</div>
    </article>
  )
}

/** Compact glass pill used for the instant actions in the hero. */
function QuickChip({ icon: Icon, href, children, external = false }) {
  const className =
    'inline-flex max-w-full items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-5 py-3 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20'

  if (external) {
    return (
      <a href={href} target="_blank" rel="noreferrer" className={className}>
        <Icon className="size-4 shrink-0" aria-hidden />
        <span className="truncate">{children}</span>
      </a>
    )
  }

  return (
    <a href={href} className={className}>
      <Icon className="size-4 shrink-0" aria-hidden />
      <span className="truncate">{children}</span>
    </a>
  )
}

/**
 * Message form for the public Contact page. Posts to the public contact
 * endpoint (validation lives server-side too); on success the form is
 * replaced by a confirmation panel.
 */
function ContactForm() {
  const [successMessage, setSuccessMessage] = useState(null)
  const [error, setError] = useState(null)
  const mutation = useMutation({ mutationFn: publicWebsiteApi.submitContactMessage })

  const handleSubmit = (event) => {
    event.preventDefault()
    setError(null)

    const form = event.currentTarget
    const payload = Object.fromEntries(
      [...new FormData(form).entries()].map(([key, value]) => [key, String(value).trim()]),
    )

    if (!payload.name || !payload.email || !payload.message) {
      setError('Please fill in your name, email and message.')
      return
    }

    mutation.mutate(payload, {
      onSuccess: (response) => setSuccessMessage(response?.message ?? SEND_SUCCESS_FALLBACK),
      onError: (err) => setError(err.message || SEND_ERROR_FALLBACK),
    })
  }

  if (successMessage) {
    return (
      <div className="rounded-[28px] border border-line bg-surface p-8 text-center shadow-[var(--shadow-card)] sm:p-10">
        <span className="mx-auto grid size-16 place-items-center rounded-full bg-accent/10 text-accent">
          <CheckCircle2 className="size-8" aria-hidden />
        </span>
        <h3 className="mt-5 font-display text-2xl font-extrabold text-ink">Message sent</h3>
        <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-ink-muted">{successMessage}</p>
        <div className="mt-6">
          <Button variant="outline" className="rounded-full px-6" onClick={() => setSuccessMessage(null)}>
            Send another message
          </Button>
        </div>
      </div>
    )
  }

  return (
    <form
      onSubmit={handleSubmit}
      className="relative overflow-hidden rounded-[28px] border border-line bg-surface p-6 shadow-[var(--shadow-card)] sm:p-8"
    >
      <span
        aria-hidden
        className="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-azure-500 via-azure-600 to-accent"
      />

      {error ? (
        <div
          role="alert"
          className="mb-5 rounded-xl border border-red-300 bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
        >
          {error}
        </div>
      ) : null}

      <div className="grid gap-5 sm:grid-cols-2">
        <div>
          <label htmlFor="contact-name" className="mb-1.5 block text-sm font-semibold text-ink">
            Name <span className="text-ink-muted">*</span>
          </label>
          <Input
            id="contact-name"
            name="name"
            required
            maxLength={255}
            autoComplete="name"
            placeholder="Your full name"
            className="h-11 rounded-xl"
          />
        </div>
        <div>
          <label htmlFor="contact-email" className="mb-1.5 block text-sm font-semibold text-ink">
            Email <span className="text-ink-muted">*</span>
          </label>
          <Input
            id="contact-email"
            name="email"
            type="email"
            required
            maxLength={255}
            autoComplete="email"
            placeholder="you@example.com"
            className="h-11 rounded-xl"
          />
        </div>
        <div>
          <label htmlFor="contact-phone" className="mb-1.5 block text-sm font-semibold text-ink">
            Phone <span className="text-sm font-normal text-ink-muted">(optional)</span>
          </label>
          <Input
            id="contact-phone"
            name="phone"
            type="tel"
            maxLength={30}
            autoComplete="tel"
            placeholder="How we can call you"
            className="h-11 rounded-xl"
          />
        </div>
        <div>
          <label htmlFor="contact-subject" className="mb-1.5 block text-sm font-semibold text-ink">
            Subject <span className="text-sm font-normal text-ink-muted">(optional)</span>
          </label>
          <Input
            id="contact-subject"
            name="subject"
            maxLength={255}
            placeholder="What is this about?"
            className="h-11 rounded-xl"
          />
        </div>
        <div className="sm:col-span-2">
          <label htmlFor="contact-message" className="mb-1.5 block text-sm font-semibold text-ink">
            Message <span className="text-ink-muted">*</span>
          </label>
          <textarea
            id="contact-message"
            name="message"
            required
            maxLength={5000}
            rows={5}
            placeholder="Tell us how we can help…"
            className="min-h-32 w-full resize-y rounded-xl border border-line bg-surface px-3 py-2.5 text-sm text-ink transition-colors placeholder:text-ink-muted/60 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
          />
        </div>
      </div>

      <div className="mt-6 flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-xs text-ink-muted">{SEND_NOTE}</p>
        <Button
          type="submit"
          loading={mutation.isPending}
          className="h-12 rounded-full bg-accent px-8 text-sm font-semibold text-accent-ink hover:bg-accent-dark"
        >
          Send Message
          <Send className="size-4" aria-hidden />
        </Button>
      </div>
    </form>
  )
}

export default function ContactPage() {
  const { settings } = useOutletContext() ?? {}

  const phone = settings?.primary_phone ?? settings?.secondary_phone
  const email = settings?.email
  const address = settings?.address
  const hours = settings?.business_hours
  const map = settings?.map_embed_url

  const hasChannels = Boolean(phone || email || address || hours)
  const hasFindContent = Boolean(map || address || hours)

  // Address-based Maps links: no coordinates are invented; the CMS address
  // itself is the query/destination so the pin always matches the listing.
  const mapsSearchHref = address
    ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`
    : null
  const directionsHref = address
    ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(address)}`
    : null

  return (
    <>
      {/* Section 1 — Cinematic hero: headline plus instant glass action chips
          (call, email, directions) straight from the CMS settings. */}
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

        <Section reveal={false} className="relative pb-16 pt-32 text-center sm:pt-40">
          <Reveal>
            <span className="inline-flex items-center gap-2.5 rounded-full border border-white/25 bg-white/10 px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.28em] text-white/80 backdrop-blur">
              <span className="relative flex size-1.5">
                <span
                  aria-hidden
                  className="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75"
                />
                <span className="relative inline-flex size-1.5 rounded-full bg-accent" />
              </span>
              {CONTACT_EYEBROW}
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
              {CONTACT_DESCRIPTION}
            </p>
          </Reveal>

          {phone || email || directionsHref ? (
            <Reveal delay={240}>
              <div className="mx-auto mt-8 flex max-w-3xl flex-wrap items-center justify-center gap-3">
                {phone ? <QuickChip icon={Phone} href={`tel:${phone}`}>{phone}</QuickChip> : null}
                {email ? <QuickChip icon={Mail} href={`mailto:${email}`}>{email}</QuickChip> : null}
                {directionsHref ? (
                  <QuickChip icon={MapPin} href={directionsHref} external>
                    Get Directions
                  </QuickChip>
                ) : null}
              </div>
            </Reveal>
          ) : null}

          <a
            href="#channels"
            aria-label="Scroll to contact channels"
            className="mx-auto mt-12 hidden w-fit text-white/45 transition-colors hover:text-white sm:block"
          >
            <ChevronDown className="size-6 animate-bounce" aria-hidden />
          </a>
        </Section>
      </section>

      {/* Section 2 — Ways to Reach Us: sticky editorial rail beside the live
          CMS channel cards. */}
      <section id="channels" className="scroll-mt-24 bg-white py-16 sm:py-20">
        <Section
          reveal={false}
          className="grid gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16"
        >
          <div className="lg:sticky lg:top-28 lg:self-start">
            <Reveal>
              <SectionHeading
                eyebrow="01 — Reach Us"
                title={REACH_HEADING}
                description={REACH_LEAD}
              />
              <p className="mt-6 text-sm leading-relaxed text-ink-muted">
                {CONTACT_DESCRIPTION}
              </p>
            </Reveal>
          </div>

          <div className="grid gap-5 sm:grid-cols-2">
            {phone ? (
              <Reveal delay={0} className="h-full">
                <ChannelCard icon={Phone} title="Phone">
                  {settings.primary_phone ? (
                    <a
                      href={`tel:${settings.primary_phone}`}
                      className="block font-medium text-ink transition-colors hover:text-azure-700"
                    >
                      {settings.primary_phone}
                    </a>
                  ) : null}
                  {settings.secondary_phone ? (
                    <a
                      href={`tel:${settings.secondary_phone}`}
                      className="block font-medium text-ink transition-colors hover:text-azure-700"
                    >
                      {settings.secondary_phone}
                    </a>
                  ) : null}
                </ChannelCard>
              </Reveal>
            ) : null}

            {email ? (
              <Reveal delay={70} className="h-full">
                <ChannelCard icon={Mail} title="Email">
                  <a
                    href={`mailto:${email}`}
                    className="block break-words font-medium text-ink transition-colors hover:text-azure-700"
                  >
                    {email}
                  </a>
                </ChannelCard>
              </Reveal>
            ) : null}

            {address ? (
              <Reveal delay={140} className="h-full">
                <ChannelCard icon={MapPin} title="Address">
                  <p className="font-medium text-ink">{address}</p>
                </ChannelCard>
              </Reveal>
            ) : null}

            {hours ? (
              <Reveal delay={210} className="h-full">
                <ChannelCard icon={Clock} title="Opening Hours">
                  <p className="whitespace-pre-line">{hours}</p>
                </ChannelCard>
              </Reveal>
            ) : null}

            {!hasChannels ? (
              <Reveal className="sm:col-span-2">
                <ChannelCard icon={Mail} title="Contact details coming soon">
                  <p>
                    Our contact details are being updated. Please check back soon, or visit the{' '}
                    <Link to="/home" className="font-semibold text-azure-700 hover:text-azure-600">
                      home page
                    </Link>{' '}
                    for the latest information.
                  </p>
                </ChannelCard>
              </Reveal>
            ) : null}
          </div>
        </Section>
      </section>

      {/* Section 3 — Send a Message: the form posts to the public contact
          endpoint and is replaced by a confirmation panel on success. */}
      <section id="send" className="scroll-mt-24 bg-white py-16 sm:py-20">
        <Section reveal={false}>
          <Reveal>
            <SectionHeading
              align="center"
              eyebrow="02 — Send a Message"
              title={SEND_HEADING}
              description={SEND_LEAD}
            />
          </Reveal>
          <Reveal delay={110} className="mx-auto mt-10 max-w-3xl">
            <ContactForm />
          </Reveal>
        </Section>
      </section>

      {/* Section 4 — Find Us: dark band with a glass-framed map, address and
          directions pulled straight from the CMS settings. */}
      {hasFindContent ? (
        <section
          id="find"
          className="relative isolate scroll-mt-24 overflow-hidden bg-[#06243d]"
        >
          <span
            aria-hidden
            className="animate-aurora absolute -right-20 top-0 size-[440px] rounded-full bg-azure-500/30 blur-[120px]"
          />
          <span
            aria-hidden
            className="animate-aurora absolute -left-24 bottom-0 size-[340px] rounded-full bg-accent/20 blur-[110px]"
            style={{ animationDelay: '-9s' }}
          />
          <span aria-hidden className="auth-grid absolute inset-0 opacity-30" />

          <Section
            reveal={false}
            className={cn(
              'relative grid gap-10 py-16 sm:py-20 lg:gap-14',
              map ? 'lg:grid-cols-2 lg:items-center' : '',
            )}
          >
            <Reveal>
              <p className="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.28em] text-white/60">
                <span aria-hidden className="h-px w-10 bg-white/25" />
                03 — Find Us
              </p>
              <h2 className="mt-4 font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                {FIND_HEADING}
              </h2>

              <div className="mt-6 space-y-3">
                {address ? (
                  <div className="flex items-start gap-3 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/15 text-white">
                      <MapPin className="size-4" aria-hidden />
                    </span>
                    <span className="pt-1.5 text-sm font-medium leading-relaxed text-white/85">
                      {address}
                    </span>
                  </div>
                ) : null}
                {hours ? (
                  <div className="flex items-start gap-3 rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-xl">
                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-white/15 text-white">
                      <Clock className="size-4" aria-hidden />
                    </span>
                    <span className="whitespace-pre-line pt-1.5 text-sm font-medium leading-relaxed text-white/85">
                      {hours}
                    </span>
                  </div>
                ) : null}
              </div>

              {directionsHref ? (
                <div className="mt-7 flex flex-wrap items-center gap-3">
                  <a
                    href={directionsHref}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 rounded-full bg-accent px-7 py-3.5 text-sm font-semibold text-accent-ink shadow-[0_16px_34px_-14px_rgb(76_175_80_/_0.75)] transition-transform hover:-translate-y-0.5 hover:bg-accent-dark"
                  >
                    Get Directions
                    <ArrowRight className="size-4" aria-hidden />
                  </a>
                  {mapsSearchHref ? (
                    <a
                      href={mapsSearchHref}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-7 py-3.5 text-sm font-semibold text-white backdrop-blur transition-colors hover:bg-white/20"
                    >
                      Open in Maps
                    </a>
                  ) : null}
                </div>
              ) : null}
            </Reveal>

            {map ? (
              <Reveal delay={110}>
                <div className="rounded-[28px] border border-white/15 bg-white/10 p-2 shadow-[0_28px_64px_-24px_rgba(0,0,0,0.5)] backdrop-blur-xl">
                  <div className="relative h-80 overflow-hidden rounded-[22px] sm:h-96">
                    {map.includes('<iframe') ? (
                      <div
                        className="absolute inset-0 [&_iframe]:h-full [&_iframe]:w-full"
                        dangerouslySetInnerHTML={{ __html: map }}
                      />
                    ) : (
                      <iframe
                        src={map}
                        title="Clinic location map"
                        className="absolute inset-0 h-full w-full border-0"
                        loading="lazy"
                        referrerPolicy="no-referrer-when-downgrade"
                      />
                    )}
                    {address ? (
                      <span className="absolute bottom-3 left-3 inline-flex max-w-[calc(100%-1.5rem)] items-center gap-2 rounded-full border border-white/50 bg-white/90 px-4 py-2 text-xs font-semibold text-[#0B4A8F] backdrop-blur">
                        <MapPin className="size-3.5 shrink-0 text-[#1E88C8]" aria-hidden />
                        <span className="truncate">{address}</span>
                      </span>
                    ) : null}
                  </div>
                </div>
              </Reveal>
            ) : null}
          </Section>
        </section>
      ) : null}
    </>
  )
}
