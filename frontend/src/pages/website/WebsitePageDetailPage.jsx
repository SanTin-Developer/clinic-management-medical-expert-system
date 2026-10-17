import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, EyeOff, Globe, Pencil, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import { CardSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import PageFormModal from '../../components/website/PageFormModal.jsx'
import DetailList from '../../components/website/DetailList.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatDate } from '../../utils/format.js'

export default function WebsitePageDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [busy, setBusy] = useState(false)

  const query = useQuery({
    queryKey: ['website', 'admin', 'pages', id],
    queryFn: () => websiteAdminApi.getPage(id),
    enabled: Boolean(id),
  })

  const page = query.data ?? null
  const published = page?.status === 'published'

  const togglePublish = async () => {
    try {
      if (published) {
        await websiteAdminApi.unpublishPage(page.id)
        toast.success('Page unpublished.')
      } else {
        await websiteAdminApi.publishPage(page.id)
        toast.success('Page published.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deletePage(page.id)
      toast.success('Page deleted.')
      navigate('/website/pages')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (query.isLoading) return <CardSkeleton />
  if (query.isError) return <ErrorState onRetry={query.refetch} />
  if (!page) return <ErrorState onRetry={query.refetch} />

  return (
    <>
      <PageHeader
        title={page.title}
        description={`/${page.slug}`}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/website/pages')}>
              <ArrowLeft className="size-4" aria-hidden />
              Back to pages
            </Button>
            {can('website.pages.publish') ? (
              <Button variant={published ? 'outline' : 'primary'} onClick={togglePublish}>
                {published ? (
                  <>
                    <EyeOff className="size-4" aria-hidden />
                    Unpublish
                  </>
                ) : (
                  <>
                    <Globe className="size-4" aria-hidden />
                    Publish
                  </>
                )}
              </Button>
            ) : null}
            {can('website.pages.manage') ? (
              <>
                <Button onClick={() => setFormOpen(true)}>
                  <Pencil className="size-4" aria-hidden />
                  Edit
                </Button>
                <Button
                  variant="ghost"
                  onClick={() => setDeleting(true)}
                  aria-label="Delete page"
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </>
            ) : null}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2" title="Page details">
          <DetailList
            items={[
              { label: 'Slug', value: `/${page.slug}` },
              {
                label: 'Status',
                value: <Badge tone={published ? 'green' : 'gray'}>{page.status}</Badge>,
              },
              { label: 'Footer link', value: page.show_in_footer ? 'Shown in footer' : '—' },
              { label: 'Published', value: formatDate(page.published_at) },
              { label: 'Last updated', value: formatDate(page.updated_at) },
              { label: 'System page', value: page.is_system ? 'Yes — limited editing' : 'No' },
            ]}
          />

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Excerpt</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {page.excerpt || '—'}
            </p>
          </div>

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Body</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {page.body || 'No content yet.'}
            </p>
          </div>
        </Card>

        <div className="space-y-6">
          <Card title="Cover image">
            {page.cover?.url ? (
              <img
                src={page.cover.url}
                alt={page.title}
                className="w-full rounded-2xl border border-line object-cover"
              />
            ) : (
              <div className="grid place-items-center rounded-2xl border border-dashed border-line bg-canvas py-10 text-center text-sm text-ink-muted">
                No cover image
              </div>
            )}
          </Card>

          <Card title="SEO">
            <DetailList
              items={[
                { label: 'SEO title', value: page.seo_title || '—' },
                { label: 'SEO description', value: page.seo_description || '—' },
              ]}
            />
          </Card>
        </div>
      </div>

      <PageFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={() => query.refetch()}
        page={page}
      />

      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        onConfirm={confirmDelete}
        loading={busy}
        title="Delete page"
        message={`Delete "${page.title}"? This cannot be undone.`}
        confirmLabel="Delete"
      />
    </>
  )
}
