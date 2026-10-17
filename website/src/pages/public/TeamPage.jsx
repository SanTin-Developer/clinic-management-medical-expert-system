import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { PageBanner, Section, SectionHeading } from '../../components/public/PublicUI.jsx'
import TeamGrid from '../../components/public/TeamGrid.jsx'

export default function TeamPage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'team'],
    queryFn: publicWebsiteApi.listTeam,
  })

  const team = data?.data ?? []

  return (
    <>
      <PageBanner
        title="Our Team"
        description="Meet the dedicated professionals committed to your health and wellbeing."
      />
      <Section className="py-14 sm:py-20">
        {isLoading ? (
          <div className="grid place-items-center py-16">
            <Spinner label="Loading team…" />
          </div>
        ) : isError ? (
          <ErrorState onRetry={refetch} />
        ) : team.length === 0 ? (
          <EmptyState title="No team members yet" message="Our team profiles are coming soon." />
        ) : (
          <>
            <SectionHeading
              align="center"
              eyebrow="Meet our medical team"
              title="The People Behind Your Care"
              description="Meet the dedicated professionals committed to providing compassionate, patient-focused healthcare."
            />
            <div className="mt-10 sm:mt-14">
              <TeamGrid members={team} />
            </div>
          </>
        )}
      </Section>
    </>
  )
}
