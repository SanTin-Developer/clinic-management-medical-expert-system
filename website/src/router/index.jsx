import { createBrowserRouter, Link, useParams } from 'react-router-dom'
import { lazy, Suspense } from 'react'
import { Spinner } from '../components/ui/Skeleton.jsx'
import { PageBanner, Section } from '../components/public/PublicUI.jsx'

const PublicLayout = lazy(() => import('../components/public/PublicLayout.jsx'))
const HomePage = lazy(() => import('../pages/public/HomePage.jsx'))
const PageView = lazy(() => import('../pages/public/PageView.jsx'))
const ServicesPage = lazy(() => import('../pages/public/ServicesPage.jsx'))
const TeamPage = lazy(() => import('../pages/public/TeamPage.jsx'))
const GalleryPage = lazy(() => import('../pages/public/GalleryPage.jsx'))
const HealthNewsPage = lazy(() => import('../pages/public/HealthNewsPage.jsx'))
const ArticleDetailPage = lazy(() => import('../pages/public/ArticleDetailPage.jsx'))
const ContactPage = lazy(() => import('../pages/public/ContactPage.jsx'))

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
  return (
    <>
      <PageBanner
        title="Page not found"
        description="The page you are looking for does not exist or may have been moved."
      />
      <Section className="py-16 text-center">
        <Link
          to="/"
          className="inline-flex rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-brand-dark"
        >
          Back to home
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
      { path: 'about', element: withSuspense(<PageView slug="about" />) },
      { path: 'services', element: withSuspense(<ServicesPage />) },
      { path: 'team', element: withSuspense(<TeamPage />) },
      { path: 'gallery', element: withSuspense(<GalleryPage />) },
      { path: 'health-news', element: withSuspense(<HealthNewsPage />) },
      { path: 'news', element: withSuspense(<HealthNewsPage />) },
      { path: 'health-news/:slug', element: withSuspense(<ArticleDetailPage />) },
      { path: 'news/:slug', element: withSuspense(<ArticleDetailPage />) },
      { path: 'contact', element: withSuspense(<ContactPage />) },
      { path: 'privacy-policy', element: withSuspense(<PageView slug="privacy-policy" />) },
      { path: 'terms-of-use', element: withSuspense(<PageView slug="terms-of-use" />) },
      { path: 'terms', element: withSuspense(<PageView slug="terms-of-use" />) },
      { path: 'pages/:slug', element: withSuspense(<PageViewRoute />) },
      { path: '*', element: withSuspense(<NotFound />) },
    ],
  },
])

export default router
