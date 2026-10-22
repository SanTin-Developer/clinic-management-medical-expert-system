import { Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import {
  Activity,
  FileText,
  FolderOpen,
  Images,
  Inbox,
  Maximize2,
  Newspaper,
  Settings,
  Stethoscope,
  Users,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import StatCard from '../../components/ui/StatCard.jsx'
import Badge from '../../components/ui/Badge.jsx'
import Button from '../../components/ui/Button.jsx'
import Table, { TBody, Td, Th, THead, Tr } from '../../components/ui/Table.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import SitePreview from '../../components/website/SitePreview.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { PUBLIC_SITE_URL } from '../../constants/sitePreview.js'
import { formatDate } from '../../utils/format.js'

const SHORTCUTS = [
  { to: '/website/settings', label: 'Website Settings', description: 'Branding, contact info and SEO', icon: Settings },
  { to: '/website/pages', label: 'Pages', description: 'About, Privacy, Terms and custom pages', icon: FileText },
  { to: '/website/services', label: 'Services', description: 'Medical services shown publicly', icon: Activity },
  { to: '/website/team', label: 'Our Team', description: 'Public doctor and staff profiles', icon: Users },
  { to: '/website/gallery', label: 'Gallery', description: 'Facility and clinic photos', icon: Images },
  { to: '/website/articles', label: 'News & Health', description: 'Articles published on the website', icon: Newspaper },
  { to: '/website/contact-messages', label: 'Contact Messages', description: 'Messages from the public Contact page', icon: Inbox },
  { to: '/website/media', label: 'Media Library', description: 'Uploaded images and files', icon: FolderOpen },
]

export default function WebsiteDashboardPage() {
  const navigate = useNavigate()
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'admin', 'dashboard'],
    queryFn: websiteAdminApi.dashboard,
  })

  const stats = data?.stats ?? {}
  const articles = data?.recent_articles ?? []

  return (
    <>
      <PageHeader
        title="Website Management"
        description="Manage the public clinic website content."
        actions={
          <>
            <Button variant="outline" onClick={() => window.open(PUBLIC_SITE_URL, '_blank', 'noopener')}>
              Open website
            </Button>
            <Button onClick={() => navigate('/website/preview')}>
              <Maximize2 className="size-4" aria-hidden />
              Full preview
            </Button>
          </>
        }
      />

      {isError ? (
        <ErrorState onRetry={refetch} />
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Pages" value={stats.pages ?? 0} sub={`${stats.pages_published ?? 0} published`} icon={FileText} tone="brand" loading={isLoading} />
            <StatCard label="Services" value={stats.services ?? 0} sub={`${stats.services_published ?? 0} published`} icon={Stethoscope} tone="green" loading={isLoading} />
            <StatCard label="Team" value={stats.team ?? 0} sub={`${stats.team_published ?? 0} published`} icon={Users} tone="blue" loading={isLoading} />
            <StatCard label="Articles" value={stats.articles ?? 0} sub={`${stats.articles_published ?? 0} published`} icon={Newspaper} tone="amber" loading={isLoading} />
          </div>

          <div className="mt-6 grid gap-6 lg:grid-cols-3">
            <div className="lg:col-span-2">
              <Card
                title="Recent articles"
                subtitle="Latest News & Health posts"
                actions={
                  <Link to="/website/articles" className="text-sm font-semibold text-brand hover:text-brand-dark">
                    Manage
                  </Link>
                }
                bodyClassName="p-0"
              >
                {isLoading ? (
                  <div className="p-5">
                    <RowSkeleton rows={4} cols={3} />
                  </div>
                ) : articles.length === 0 ? (
                  <p className="p-5 text-sm text-ink-muted">No articles yet.</p>
                ) : (
                  <Table minWidth="min-w-[480px]">
                    <THead>
                      <Tr>
                        <Th>Title</Th>
                        <Th>Status</Th>
                        <Th>Published</Th>
                      </Tr>
                    </THead>
                    <TBody>
                      {articles.map((article) => (
                        <Tr key={article.id} onClick={() => navigate(`/website/articles/${article.id}`)}>
                          <Td className="font-medium text-ink">{article.title}</Td>
                          <Td>
                            <Badge tone={article.status === 'published' ? 'green' : 'gray'}>
                              {article.status}
                            </Badge>
                          </Td>
                          <Td className="text-ink-muted">{article.published_at ? formatDate(article.published_at) : '—'}</Td>
                        </Tr>
                      ))}
                    </TBody>
                  </Table>
                )}
              </Card>
            </div>

            <Card title="Quick links" bodyClassName="p-3">
              <div className="flex flex-col">
                {SHORTCUTS.map((item) => (
                  <Link
                    key={item.to}
                    to={item.to}
                    className="flex items-start gap-3 rounded-xl px-3 py-3 transition-colors hover:bg-canvas"
                  >
                    <span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
                      <item.icon className="size-4" aria-hidden />
                    </span>
                    <span>
                      <span className="block text-sm font-semibold text-ink">{item.label}</span>
                      <span className="block text-xs text-ink-muted">{item.description}</span>
                    </span>
                  </Link>
                ))}
              </div>
            </Card>
          </div>

          <Card
            title="Live website preview"
            subtitle="See how your changes look on the public site."
            actions={
              <Button variant="outline" size="sm" onClick={() => navigate('/website/preview')}>
                <Maximize2 className="size-4" aria-hidden />
                Open full preview
              </Button>
            }
            bodyClassName="p-0"
          >
            <SitePreview height={560} />
          </Card>
        </>
      )}
    </>
  )
}
