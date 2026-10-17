import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { ArticleCard, Section, SectionHeading, ServiceCard, TeamCard } from '../../components/public/PublicUI.jsx'
import { AboutSection, CtaBanner, HeroSection, PartnersStrip } from '../../components/public/HomeSections.jsx'

export default function HomePage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'home'],
    queryFn: publicWebsiteApi.home,
  })

  if (isLoading) {
    return (
      <div className="grid min-h-screen place-items-center">
        <Spinner label="Loading website…" />
      </div>
    )
  }

  if (isError) {
    return (
      <Section className="py-32">
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
      <HeroSection settings={settings} />

      <PartnersStrip />

      <AboutSection settings={settings} />

      {services.length > 0 ? (
        <Section className="py-14">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading eyebrow="What we offer" title="Our Medical Services" />
            <Link
              to="/services"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              View all services
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {services.map((service) => (
              <ServiceCard key={service.id} service={service} />
            ))}
          </div>
        </Section>
      ) : null}

      {team.length > 0 ? (
        <Section className="py-14">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading eyebrow="Meet the experts" title="Our Team" />
            <Link
              to="/team"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              Meet everyone
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
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
            <Link
              to="/health-news"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              Read all articles
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {articles.map((article) => (
              <ArticleCard key={article.id} article={article} />
            ))}
          </div>
        </Section>
      ) : null}

      {gallery.length > 0 ? (
        <Section className="py-14">
          <SectionHeading eyebrow="Our facilities" title="A Glimpse Inside" align="center" />
          <div className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {gallery.map((item) =>
              item.image?.url ? (
                <img
                  key={item.id}
                  src={item.image.url}
                  alt={item.title ?? 'Facility photo'}
                  loading="lazy"
                  className="h-40 w-full rounded-2xl object-cover"
                />
              ) : null,
            )}
          </div>
          <div className="mt-8 text-center">
            <Link
              to="/gallery"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              View full gallery
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
        </Section>
      ) : null}

      <CtaBanner settings={settings} />
    </>
  )
}
