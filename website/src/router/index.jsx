import { createBrowserRouter, Link, Navigate, useParams } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import { Spinner } from '../components/ui/Skeleton.jsx'
import { PageBanner, Section } from '../components/public/PublicUI.jsx'
import { useLanguage } from '../hooks/useLanguage.js'

const PublicLayout = lazy(() => import('../components/public/PublicLayout.jsx'))
const AboutPage = lazy(() => import('../pages/public/AboutPage.jsx'))
const HomePage = lazy(() => import('../pages/public/HomePage.jsx'))
const PageView = lazy(() => import('../pages/public/PageView.jsx'))
const ServicesPage = lazy(() => import('../pages/public/ServicesPage.jsx'))
const TeamPage = lazy(() => import('../pages/public/TeamPage.jsx'))
const GalleryPage = lazy(() => import('../pages/public/GalleryPage.jsx'))
const HealthNewsPage = lazy(() => import('../pages/public/HealthNewsPage.jsx'))
const ArticleDetailPage = lazy(() => import('../pages/public/ArticleDetailPage.jsx'))
const ContactPage = lazy(() => import('../pages/public/ContactPage.jsx'))
const PrivacyPolicyPage = lazy(() => import('../pages/public/PrivacyPolicyPage.jsx'))
const TermsOfUsePage = lazy(() => import('../pages/public/TermsOfUsePage.jsx'))

function withSuspense(element) {
  return (
    <Suspense
      fallback={
        <div className="grid min-h-screen place-items-center bg-canvas">
          <Spinner label="Loading page…" />
        </div>
      }
    >
      {element}
    </Suspense>
  )
}

function PageViewRoute() {
  const { slug } = useParams()
  return <PageView slug={slug} />
}

function NotFound() {
  const { t } = useLanguage()

  return (
    <>
      <PageBanner
        title={t('Page not found')}
        description={t('The page you are looking for does not exist or may have been moved.')}
      />
      <Section className="py-16 text-center">
        <Link
          to="/"
          className="inline-flex rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-dark"
        >
          {t('Back to home')}
        </Link>
      </Section>
    </>
  )
}

export const router = createBrowserRouter([
  {
    element: withSuspense(<PublicLayout />),
    children: [
      { index: true, element: withSuspense(<HomePage />) },
      { path: 'home', element: withSuspense(<HomePage />) },
      { path: 'about', element: withSuspense(<AboutPage />) },
      { path: 'services', element: withSuspense(<ServicesPage />) },
      { path: 'team', element: withSuspense(<TeamPage />) },
      { path: 'gallery', element: withSuspense(<GalleryPage />) },
      { path: 'health-news', element: withSuspense(<HealthNewsPage />) },
      { path: 'news', element: withSuspense(<HealthNewsPage />) },
      { path: 'health-news/:slug', element: withSuspense(<ArticleDetailPage />) },
      { path: 'news/:slug', element: withSuspense(<ArticleDetailPage />) },
      { path: 'contact', element: withSuspense(<ContactPage />) },
      { path: 'privacy-policy', element: withSuspense(<PrivacyPolicyPage />) },
      { path: 'terms-of-use', element: withSuspense(<TermsOfUsePage />) },
      { path: 'terms', element: withSuspense(<TermsOfUsePage />) },
      // The CMS "about" record is also the data source for the canonical About
      // page, so legacy /pages/about links redirect instead of rendering the
      // basic page template a second time.
      { path: 'pages/about', element: <Navigate to="/about" replace /> },
      { path: 'pages/privacy-policy', element: withSuspense(<PrivacyPolicyPage />) },
      { path: 'pages/terms-of-use', element: withSuspense(<TermsOfUsePage />) },
      { path: 'pages/:slug', element: withSuspense(<PageViewRoute />) },
      { path: '*', element: withSuspense(<NotFound />) },
    ],
  },
])

export default router
