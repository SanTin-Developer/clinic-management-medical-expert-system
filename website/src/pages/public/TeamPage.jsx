import { Mail, Phone } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { publicWebsiteApi } from '../../api/website.js'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { PageBanner, Section, TeamCard } from '../../components/public/PublicUI.jsx'

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
      <Section className="py-12">
        {isLoading ? (
          <div className="grid place-items-center py-16">
            <Spinner label="Loading team…" />
          </div>
        ) : isError ? (
          <ErrorState onRetry={refetch} />
        ) : team.length === 0 ? (
          <EmptyState title="No team members yet" message="Our team profiles are coming soon." />
        ) : (
          <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {team.map((profile) => (
              <div key={profile.id}>
                <TeamCard profile={profile} />
                {profile.email || profile.phone ? (
                  <div className="mt-2 flex items-center justify-center gap-3 text-xs text-ink-muted">
                    {profile.email ? (
                      <a href={`mailto:${profile.email}`} className="inline-flex items-center gap-1 hover:text-brand">
                        <Mail className="size-3.5" aria-hidden /> Email
                      </a>
                    ) : null}
                    {profile.phone ? (
                      <a href={`tel:${profile.phone}`} className="inline-flex items-center gap-1 hover:text-brand">
                        <Phone className="size-3.5" aria-hidden /> Call
                      </a>
                    ) : null}
                  </div>
                ) : null}
              </div>
            ))}
          </div>
        )}
      </Section>
    </>
  )
}
