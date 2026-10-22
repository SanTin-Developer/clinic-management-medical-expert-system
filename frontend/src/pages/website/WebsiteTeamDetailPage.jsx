import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, EyeOff, Globe, Pencil, Sparkles, Trash2, Users } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import { CardSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import TeamFormModal from '../../components/website/TeamFormModal.jsx'
import DetailList from '../../components/website/DetailList.jsx'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

export default function WebsiteTeamDetailPage() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { can } = useAuth()
  const [formOpen, setFormOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [busy, setBusy] = useState(false)

  const query = useQuery({
    queryKey: ['website', 'admin', 'team', id],
    queryFn: () => websiteAdminApi.getTeam(id),
    enabled: Boolean(id),
  })

  const profile = query.data ?? null

  const togglePublish = async () => {
    try {
      await websiteAdminApi.updateTeam(profile.id, { is_published: !profile.is_published })
      toast.success(profile.is_published ? 'Profile unpublished.' : 'Profile published.')
      query.refetch()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteTeam(profile.id)
      toast.success('Team profile deleted.')
      navigate('/website/team')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  if (query.isLoading) return <CardSkeleton />
  if (query.isError) return <ErrorState onRetry={query.refetch} />
  if (!profile) return <ErrorState onRetry={query.refetch} />

  return (
    <>
      <PageHeader
        title={profile.name}
        description={profile.position}
        actions={
          <>
            <Button variant="outline" onClick={() => navigate('/website/team')}>
              <ArrowLeft className="size-4" aria-hidden />
              Back to team
            </Button>
            {can('website.team.manage') ? (
              <>
                <Button
                  variant={profile.is_published ? 'outline' : 'primary'}
                  onClick={togglePublish}
                >
                  {profile.is_published ? (
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
                  aria-label="Delete profile"
                >
                  <Trash2 className="size-4" aria-hidden />
                </Button>
              </>
            ) : null}
          </>
        }
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2" title="Profile details">
          <DetailList
            items={[
              {
                label: 'Specialization',
                value: profile.specialization || '—',
              },
              {
                label: 'Status',
                value: (
                  <span className="flex flex-wrap items-center gap-2">
                    <Badge tone={profile.is_published ? 'green' : 'gray'}>
                      {profile.is_published ? 'published' : 'draft'}
                    </Badge>
                    {profile.is_featured ? (
                      <Badge tone="brand">
                        <Sparkles className="size-3" aria-hidden />
                        Featured
                      </Badge>
                    ) : null}
                  </span>
                ),
              },
              {
                label: 'Email',
                value: profile.email || '—',
              },
              {
                label: 'Phone',
                value: profile.phone || '—',
              },
              {
                label: 'Sort order',
                value: profile.sort_order ?? 0,
              },
              {
                label: 'Linked staff',
                value: profile.staff_id ? `Staff #${profile.staff_id}` : '—',
              },
            ]}
          />

          <div className="mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-ink-muted">Bio</h3>
            <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-muted">
              {profile.bio || 'No biography provided.'}
            </p>
          </div>
        </Card>

        <Card title="Photo">
          {profile.photo?.url ? (
            <img
              src={profile.photo.url}
              alt={profile.name}
              className="w-full rounded-2xl border border-line object-cover"
            />
          ) : (
            <div className="grid place-items-center gap-2 rounded-2xl border border-dashed border-line bg-canvas py-10 text-center">
              <span className="grid size-14 place-items-center rounded-full bg-brand-soft text-xl font-bold text-brand">
                {profile.name?.charAt(0) ?? '?'}
              </span>
              <p className="text-sm text-ink-muted">No photo uploaded</p>
            </div>
          )}
          <div className="mt-4 flex items-center gap-2 rounded-xl bg-canvas px-3 py-2 text-xs text-ink-muted">
            <Users className="size-4 shrink-0 text-brand" aria-hidden />
            {profile.position || '—'}
          </div>
        </Card>
      </div>

      <TeamFormModal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        onSaved={() => query.refetch()}
        profile={profile}
      />

      <ConfirmDialog
        open={deleting}
        onClose={() => setDeleting(false)}
        onConfirm={confirmDelete}
        loading={busy}
        title="Delete team profile"
        message={`Delete "${profile.name}"? This cannot be undone. If no other record uses the photo, it is also permanently deleted from Cloudinary.`}
        confirmLabel="Delete"
      />
    </>
  )
}
