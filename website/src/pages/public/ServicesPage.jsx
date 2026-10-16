import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../../api/website.js'
import { API_BASE_URL } from '../../api/client.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { PageBanner, Section, ServiceCard } from '../../components/public/PublicUI.jsx'

export default function ServicesPage() {
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['website', 'services'],
    queryFn: publicWebsiteApi.listServices,
  })

  useEffect(() => {
    if (isError && import.meta.env.DEV) {
      console.error(`[website] Failed to load services from ${API_BASE_URL}`, error)
    }
  }, [isError, error])

  const services = data?.data ?? []

  return (
    <>
      <PageBanner
        title="Our Services"
        description="Comprehensive medical care delivered by experienced professionals."
      />
      <Section className="py-12">
        {isLoading ? (
          <div className="grid place-items-center py-16">
            <Spinner label="Loading services…" />
          </div>
        ) : isError ? (
          <ErrorState
            title="Services unavailable"
            message="We couldn't load our services right now. Please try again in a moment."
            onRetry={refetch}
          />
        ) : services.length === 0 ? (
          <EmptyState title="No services yet" message="Our service catalogue is being updated. Please check back soon." />
        ) : (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {services.map((service) => (
              <ServiceCard key={service.id} service={service} />
            ))}
          </div>
        )}
      </Section>
    </>
  )
}
