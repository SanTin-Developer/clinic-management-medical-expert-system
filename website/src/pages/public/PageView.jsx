import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { PageBanner, Section } from '../../components/public/PublicUI.jsx'

export default function PageView({ slug }) {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['website', 'page', slug],
    queryFn: () => publicWebsiteApi.getPage(slug),
  })

  if (isLoading) {
    return (
      <div className="grid min-h-[60vh] place-items-center">
        <Spinner label="Loading page…" />
      </div>
    )
  }

  if (isError) {
    return (
      <Section className="py-16">
        <ErrorState
          title={error?.status === 404 ? 'Page not found' : undefined}
          message={error?.status === 404 ? 'The page you are looking for is unavailable.' : undefined}
          onRetry={error?.status === 404 ? undefined : refetch}
        />
      </Section>
    )
  }

  const page = data?.data ?? {}

  return (
    <>
      <PageBanner title={page.title} image={page.cover?.url} />
      <Section className="py-12">
        <div className="mx-auto max-w-3xl">
          {page.excerpt ? (
            <p className="text-lg font-medium text-ink-muted">{page.excerpt}</p>
          ) : null}
          <div className="mt-6 whitespace-pre-line text-sm leading-relaxed text-ink sm:text-base">
            {page.body}
          </div>
        </div>
      </Section>
    </>
  )
}
