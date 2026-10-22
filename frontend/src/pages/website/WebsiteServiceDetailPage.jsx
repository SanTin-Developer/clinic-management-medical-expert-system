import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, EyeOff, Globe, Pencil, Sparkles, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import { CardSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import ServiceFormModal from '../../components/website/ServiceFormModal.jsx'
import DetailList from '../../components/website/DetailList.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatDate } from '../../utils/format.js'

export default function WebsiteServiceDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [busy, setBusy] = useState(false)

  const query = useQuery({
    queryKey: ['website', 'admin', 'services', id],
    queryFn: () => websiteAdminApi.getService(id),
    enabled: Boolean(id),
  })

  const service = query.data ?? null

  const togglePublish = async () => {
    try {
      await websiteAdminApi.updateService(service.id, { is_published: !service.is_published })
      toast.success(service.is_published ? 'Service unpublished.' : 'Service published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteService(service.id)
      toast.success('Service deleted.')
      navigate('/website/services')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (query.isLoading) return <CardSkeleton />
  if (query.isError) return <ErrorState onRetry={query.refetch} />
  if (!service) return <ErrorState onRetry={query.refetch} />

  return (
    <>
      <PageHeader
        title={service.title}
        description={service.short_description || `/${service.slug}`}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/website/services')}>
              <ArrowLeft className="size-4" aria-hidden />
              Back to services
            </Button>
            {can('website.services.manage') ? (
              <>
                <Button
                  variant={service.is_published ? 'outline' : 'primary'}
                  onClick={togglePublish}
                >
                  {service.is_published ? (
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
                <Button onClick={() => setFormOpen(true)}>
                  <Pencil className="size-4" aria-hidden />
                  Edit
                </Button>
                <Button
                  variant="ghost"
                  onClick={() => setDeleting(true)}
                  aria-label="Delete service"
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </>
            ) : null}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2" title="Service details">
          <DetailList
            items={[
              { label: 'Slug', value: `/${service.slug}` },
              { label: 'Icon key', value: service.icon || '—' },
              {
                label: 'Status',
                value: (
                  <span className="flex flex-wrap items-center gap-2">
                    <Badge tone={service.is_published ? 'green' : 'gray'}>
                      {service.is_published ? 'published' : 'draft'}
                    </Badge>
                    {service.is_featured ? (
                      <Badge tone="brand">
                        <Sparkles className="size-3" aria-hidden />
                        Featured
                      </Badge>
                    ) : null}
                  </span>
                ),
              },
              { label: 'Sort order', value: service.sort_order ?? 0 },
              { label: 'Created', value: formatDate(service.created_at) },
              { label: 'Updated', value: formatDate(service.updated_at) },
            ]}
          />

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Short description</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {service.short_description || '—'}
            </p>
          </div>

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Description</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {service.description || 'No description provided.'}
            </p>
          </div>
        </Card>

        <Card title="Image">
          {service.image?.url ? (
            <img
              src={service.image.url}
              alt={service.title}
              className="w-full rounded-2xl border border-line object-cover"
            />
          ) : (
            <div className="grid place-items-center rounded-2xl border border-dashed border-line bg-canvas py-10 text-center text-sm text-ink-muted">
              No image uploaded
            </div>
          )}
        </Card>
      </div>

      <ServiceFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={() => query.refetch()}
        service={service}
      />

      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        onConfirm={confirmDelete}
        loading={busy}
        title="Delete service"
        message={`Delete "${service.title}"? This cannot be undone. If no other record uses its image, that image is also permanently deleted from Cloudinary.`}
        confirmLabel="Delete"
      />
    </>
  )
}
