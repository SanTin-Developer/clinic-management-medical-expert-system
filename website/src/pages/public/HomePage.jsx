import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowRight } from 'lucide-react'
import { publicWebsiteApi } from '../../api/website.js'
import { blockText, useContentBlocks } from '../../utils/content.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { ArticleCard, Section, SectionHeading, ServiceCard } from '../../components/public/PublicUI.jsx'
import { AboutSection, CtaBanner, HeroSection, PartnersStrip } from '../../components/public/HomeSections.jsx'
import TeamCarousel from '../../components/public/TeamCarousel.jsx'

export default function HomePage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'home'],
    queryFn: publicWebsiteApi.home,
  })

  // Hook must run unconditionally — the copy map below is built after the
  // loading/error guards.
  const blocks = useContentBlocks()
  const { language, t } = useLanguage()

  if (isLoading) {
    return (
      <div className="grid min-h-screen place-items-center">
        <Spinner label={t('Loading website…')} />
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

  // Admin-editable section headings and links (Page Content → Home Page).
  const copy = (key, fallback) => blockText(blocks, key, t(fallback), language)

  const sectionCopy = {
    services: {
      eyebrow: copy('home_services_eyebrow', 'What we offer'),
      heading: copy('home_services_heading', 'Our Medical Services'),
      link: copy('home_services_link', 'View all services'),
    },
    team: {
      eyebrow: copy('home_team_eyebrow', 'Meet the experts'),
      heading: copy('home_team_heading', 'Our Team'),
      link: copy('home_team_link', 'View all team'),
    },
    news: {
      eyebrow: copy('home_news_eyebrow', 'Stay informed'),
      heading: copy('home_news_heading', 'News & Health Tips'),
      link: copy('home_news_link', 'Read all articles'),
    },
    gallery: {
      eyebrow: copy('home_gallery_eyebrow', 'Our facilities'),
      heading: copy('home_gallery_heading', 'A Glimpse Inside'),
      link: copy('home_gallery_link', 'View full gallery'),
    },
  }

  return (
    <>
      <HeroSection settings={settings} />

      <PartnersStrip />

      <AboutSection />

      {services.length > 0 ? (
        <Section className="py-14">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading
              eyebrow={sectionCopy.services.eyebrow}
              title={sectionCopy.services.heading}
            />
            <Link
              to="/services"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              {sectionCopy.services.link}
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
            <SectionHeading
              eyebrow={sectionCopy.team.eyebrow}
              title={sectionCopy.team.heading}
            />
            <Link
              to="/team"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              {sectionCopy.team.link}
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
          <div className="mt-10">
            <TeamCarousel members={team.slice(0, 8)} />
          </div>
        </Section>
      ) : null}

      {articles.length > 0 ? (
        <Section className="py-14">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <SectionHeading
              eyebrow={sectionCopy.news.eyebrow}
              title={sectionCopy.news.heading}
            />
            <Link
              to="/health-news"
              className="inline-flex items-center gap-1.5 text-sm font-semibold text-azure-700 hover:text-azure-600"
            >
              {sectionCopy.news.link}
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
          <SectionHeading
            eyebrow={sectionCopy.gallery.eyebrow}
            title={sectionCopy.gallery.heading}
            align="center"
          />
          <div className="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {gallery.map((item) =>
              item.image?.url ? (
                <img
                  key={item.id}
                  src={item.image.url}
                  alt={item.title || t('Facility photo')}
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
              {sectionCopy.gallery.link}
              <ArrowRight className="size-4" aria-hidden />
            </Link>
          </div>
        </Section>
      ) : null}

      <CtaBanner settings={settings} />
    </>
  )
}
