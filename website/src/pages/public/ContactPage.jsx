import { useOutletContext } from 'react-router-dom'
import { Clock, Mail, MapPin, Phone } from 'lucide-react'
import { PageBanner, Section } from '../../components/public/PublicUI.jsx'

function InfoCard({ icon: Icon, title, children }) {
  return (
    <div className="rounded-2xl border border-line bg-surface p-6 shadow-[var(--shadow-card)]">
      <span className="grid size-11 place-items-center rounded-xl bg-brand-soft text-brand">
        <Icon className="size-5" aria-hidden />
      </span>
      <h3 className="mt-4 text-base font-semibold text-ink">{title}</h3>
      <div className="mt-2 space-y-1 text-sm text-ink-muted">{children}</div>
    </div>
  )
}

export default function ContactPage() {
  const { settings } = useOutletContext() ?? {}
  const map = settings?.map_embed_url

  return (
    <>
      <PageBanner
        title="Contact Us"
        description="We are here to help. Reach out to us through any of the channels below."
      />
      <Section className="py-12">
        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {(settings?.primary_phone || settings?.secondary_phone) && (
            <InfoCard icon={Phone} title="Phone">
              {settings.primary_phone ? (
                <a href={`tel:${settings.primary_phone}`} className="block hover:text-brand">
                  {settings.primary_phone}
                </a>
              ) : null}
              {settings.secondary_phone ? (
                <a href={`tel:${settings.secondary_phone}`} className="block hover:text-brand">
                  {settings.secondary_phone}
                </a>
              ) : null}
            </InfoCard>
          )}

          {settings?.email ? (
            <InfoCard icon={Mail} title="Email">
              <a href={`mailto:${settings.email}`} className="block hover:text-brand">
                {settings.email}
              </a>
            </InfoCard>
          ) : null}

          {settings?.address ? (
            <InfoCard icon={MapPin} title="Address">
              <p>{settings.address}</p>
            </InfoCard>
          ) : null}

          {settings?.business_hours ? (
            <InfoCard icon={Clock} title="Opening Hours">
              <p className="whitespace-pre-line">{settings.business_hours}</p>
            </InfoCard>
          ) : null}
        </div>

        {map ? (
          <div className="mt-10 overflow-hidden rounded-2xl border border-line">
            {map.includes('<iframe') ? (
              <div className="[&_iframe]:h-96 [&_iframe]:w-full" dangerouslySetInnerHTML={{ __html: map }} />
            ) : (
              <iframe
                src={map}
                title="Clinic location map"
                className="h-96 w-full"
                loading="lazy"
                referrerPolicy="no-referrer-when-downgrade"
              />
            )}
          </div>
        ) : null}
      </Section>
    </>
  )
}
