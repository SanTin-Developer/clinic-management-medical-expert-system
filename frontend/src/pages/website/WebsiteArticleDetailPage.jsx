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
import ArticleFormModal from '../../components/website/ArticleFormModal.jsx'
import DetailList from '../../components/website/DetailList.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatDate, formatNumber } from '../../utils/format.js'

export default function WebsiteArticleDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [busy, setBusy] = useState(false)

  const query = useQuery({
    queryKey: ['website', 'admin', 'articles', id],
    queryFn: () => websiteAdminApi.getArticle(id),
    enabled: Boolean(id),
  })

  const article = query.data ?? null
  const published = article?.status === 'published'

  const togglePublish = async () => {
    try {
      if (published) {
        await websiteAdminApi.unpublishArticle(article.id)
        toast.success('Article unpublished.')
      } else {
        await websiteAdminApi.publishArticle(article.id)
        toast.success('Article published.')
      }
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteArticle(article.id)
      toast.success('Article deleted.')
      navigate('/website/articles')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (query.isLoading) return <CardSkeleton />
  if (query.isError) return <ErrorState onRetry={query.refetch} />
  if (!article) return <ErrorState onRetry={query.refetch} />

  const tags = Array.isArray(article.tags) ? article.tags : []

  return (
    <>
      <PageHeader
        title={article.title}
        description={article.category || `/${article.slug}`}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/website/articles')}>
              <ArrowLeft className="size-4" aria-hidden />
              Back to articles
            </Button>
            {can('website.articles.publish') ? (
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
            {can('website.articles.manage') ? (
              <>
                <Button onClick={() => setFormOpen(true)}>
                  <Pencil className="size-4" aria-hidden />
                  Edit
                </Button>
                <Button
                  variant="ghost"
                  onClick={() => setDeleting(true)}
                  aria-label="Delete article"
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </>
            ) : null}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2" title="Article details">
          <DetailList
            items={[
              { label: 'Slug', value: `/${article.slug}` },
              { label: 'Category', value: article.category || '—' },
              {
                label: 'Status',
                value: (
                  <span className="flex flex-wrap items-center gap-2">
                    <Badge tone={published ? 'green' : 'gray'}>{article.status}</Badge>
                    {article.is_featured ? <Badge tone="brand">Featured</Badge> : null}
                  </span>
                ),
              },
              { label: 'Published', value: formatDate(article.published_at) },
              { label: 'Views', value: formatNumber(article.views ?? 0) },
              { label: 'Tags', value: tags.length ? tags.join(', ') : '—' },
            ]}
          />

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Excerpt</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {article.excerpt || '—'}
            </p>
          </div>

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Body</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {article.body || 'No content yet.'}
            </p>
          </div>
        </Card>

        <Card title="Cover image">
          {article.cover?.url ? (
            <img
              src={article.cover.url}
              alt={article.title}
              className="w-full rounded-2xl border border-line object-cover"
            />
          ) : (
            <div className="grid place-items-center rounded-2xl border border-dashed border-line bg-canvas py-10 text-center text-sm text-ink-muted">
              No cover image
            </div>
          )}
        </Card>
      </div>

      <ArticleFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={() => query.refetch()}
        article={article}
      />

      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        onConfirm={confirmDelete}
        loading={busy}
        title="Delete article"
        message={`Delete "${article.title}"? This cannot be undone.`}
        confirmLabel="Delete"
      />
    </>
  )
}
