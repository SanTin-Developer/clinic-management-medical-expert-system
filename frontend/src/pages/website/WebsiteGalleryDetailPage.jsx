import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, EyeOff, Globe, Images, Pencil, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import { CardSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import GalleryFormModal from '../../components/website/GalleryFormModal.jsx'
import DetailList from '../../components/website/DetailList.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatDate } from '../../utils/format.js'

export default function WebsiteGalleryDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [busy, setBusy] = useState(false)

  const query = useQuery({
    queryKey: ['website', 'admin', 'gallery', id],
    queryFn: () => websiteAdminApi.getGallery(id),
    enabled: Boolean(id),
  })

  const item = query.data ?? null

  const togglePublish = async () => {
    try {
      await websiteAdminApi.updateGallery(item.id, { is_published: !item.is_published })
      toast.success(item.is_published ? 'Item unpublished.' : 'Item published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteGallery(item.id)
      toast.success('Gallery item deleted.')
      navigate('/website/gallery')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (query.isLoading) return <CardSkeleton />
  if (query.isError) return <ErrorState onRetry={query.refetch} />
  if (!item) return <ErrorState onRetry={query.refetch} />

  return (
    <>
      <PageHeader
        title={item.title || 'Gallery item'}
        description={item.caption || item.category || 'Facilities & gallery image'}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/website/gallery')}>
              <ArrowLeft className="size-4" aria-hidden />
              Back to gallery
            </Button>
            {can('website.gallery.manage') ? (
              <>
                <Button
                  variant={item.is_published ? 'outline' : 'primary'}
                  onClick={togglePublish}
                >
                  {item.is_published ? (
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
                  aria-label="Delete gallery item"
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </>
            ) : null}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2" title="Image">
          {item.image?.url ? (
            <img
              src={item.image.url}
              alt={item.title ?? 'Gallery image'}
              className="w-full rounded-2xl border border-line object-cover"
            />
          ) : (
            <div className="grid place-items-center gap-2 rounded-2xl border border-dashed border-line bg-canvas py-16 text-center">
              <Images className="size-8 text-brand" aria-hidden />
              <p className="text-sm text-ink-muted">No image uploaded</p>
            </div>
          )}
          {item.caption ? (
            <p className="mt-4 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {item.caption}
            </p>
          ) : null}
        </Card>

        <Card title="Details">
          <DetailList
            items={[
              { label: 'Title', value: item.title || '—' },
              { label: 'Category', value: item.category || '—' },
              {
                label: 'Status',
                value: (
                  <Badge tone={item.is_published ? 'green' : 'gray'}>
                    {item.is_published ? 'published' : 'draft'}
                  </Badge>
                ),
              },
              { label: 'Sort order', value: item.sort_order ?? 0 },
              { label: 'Created', value: formatDate(item.created_at) },
              { label: 'Updated', value: formatDate(item.updated_at) },
            ]}
          />
        </Card>
      </div>

      <GalleryFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={() => query.refetch()}
        item={item}
      />

      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        onConfirm={confirmDelete}
        loading={busy}
        title="Delete gallery item"
        message={`Delete "${item.title || 'this image'}"? This cannot be undone. If no other record uses the image, it is also permanently deleted from Cloudinary.`}
        confirmLabel="Delete"
      />
    </>
  )
}
