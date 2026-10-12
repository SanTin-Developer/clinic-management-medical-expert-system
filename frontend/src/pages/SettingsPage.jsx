import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Monitor, Trash2, UserRound } from 'lucide-react'
import { listDevices, revokeDevice, revokeAllDevices, resendVerificationEmail } from '../api/auth.js'
import { useAuth } from '../hooks/useAuth.js'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Badge from '../components/ui/Badge.jsx'
import Button from '../components/ui/Button.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Tabs from '../components/ui/Tabs.jsx'
import { CardSkeleton } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import { formatDateTime } from '../utils/format.js'

export default function SettingsPage() {
  const { user } = useAuth()
  const [tab, setTab] = useState('profile')
  const [revokingAll, setRevokingAll] = useState(false)

  const devices = useQuery({
    queryKey: ['devices'],
    queryFn: listDevices,
    enabled: tab === 'security',
  })

  const handleRevokeAll = async () => {
    setRevokingAll(true)
    try {
      await revokeAllDevices()
      toast.success('All trusted devices revoked.')
      devices.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setRevokingAll(false)
    }
  }

  return (
    <div>
      <PageHeader
        title="Settings"
        description="Your profile and security preferences."
        breadcrumb={[{ label: 'Settings' }]}
      />

      <Tabs
        className="mb-5"
        value={tab}
        onChange={setTab}
        tabs={[
          { value: 'profile', label: 'Profile' },
          { value: 'security', label: 'Security' },
        ]}
      />

      {tab === 'profile' ? (
        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <Card title="Account profile">
            <div className="flex items-center gap-4">
              <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-brand text-lg font-bold text-white">
                {(user?.name ?? ' ')
                  .split(/\s+/)
                  .filter(Boolean)
                  .slice(0, 2)
                  .map((p) => p[0]?.toUpperCase() ?? '')
                  .join('')}
              </span>
              <div className="min-w-0">
                <p className="text-lg font-semibold text-ink">{user?.name}</p>
                <p className="truncate text-sm text-ink-muted">{user?.email}</p>
              </div>
            </div>
            <dl className="mt-6 space-y-3 border-t border-line pt-4 text-sm">
              <div className="flex items-center justify-between">
                <dt className="text-ink-muted">Roles</dt>
                <dd className="flex gap-1">
                  {(user?.roles ?? []).map((role) => {
                    const name = typeof role === 'string' ? role : role?.name
                    return name ? (
                      <Badge key={name} tone="pink">
                        {name}
                      </Badge>
                    ) : null
                  })}
                </dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-ink-muted">Username</dt>
                <dd className="font-medium text-ink">{user?.username ?? '—'}</dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-ink-muted">Email verified</dt>
                <dd className="flex items-center gap-2">
                  {user?.email_verified ? (
                    <Badge tone="green">Verified</Badge>
                  ) : (
                    <>
                      <Badge tone="amber">Unverified</Badge>
                      <button
                        type="button"
                        className="text-xs font-semibold text-brand hover:text-brand-dark"
                        onClick={async () => {
                          try {
                            const res = await resendVerificationEmail()
                            toast.success(res?.message ?? 'Verification email sent.')
                          } catch (err) {
                            toast.error(err.message)
                          }
                        }}
                      >
                        Resend link
                      </button>
                    </>
                  )}
                </dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="text-ink-muted">Permissions</dt>
                <dd className="font-semibold text-ink">{user?.permissions?.length ?? 0}</dd>
              </div>
            </dl>
          </Card>
          <Card title="Security settings" className="lg:col-span-2">
            <div className="flex items-start gap-3">
              <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                <UserRound className="size-5" aria-hidden />
              </span>
              <div>
                <p className="text-sm font-medium text-ink">Password &amp; profile changes</p>
                <p className="mt-1 text-sm text-ink-muted">
                  The current API does not expose endpoints to update your own profile, email or
                  password. To change these details, contact an administrator or request a password
                  reset from the sign-in screen.
                </p>
                <ul className="mt-3 list-inside list-disc space-y-1 text-sm text-ink-muted">
                  <li>Password reset: use the "Forgot password" flow on the sign-in page.</li>
                  <li>Email / name changes: ask an administrator with user management access.</li>
                </ul>
              </div>
            </div>
          </Card>
        </div>
      ) : null}

      {tab === 'security' ? (
        <Card
          title="Trusted devices"
          subtitle="Devices you've allowed to skip email verification for 30 days."
          actions={
            devices.data?.length ? (
              <Button variant="outline" size="sm" onClick={() => setRevokingAll(true)} loading={revokingAll}>
                <Trash2 className="size-4" aria-hidden /> Revoke all
              </Button>
            ) : null
          }
        >
          {devices.isLoading ? (
            <CardSkeleton rows={3} />
          ) : devices.isError ? (
            <ErrorState message={devices.error.message} onRetry={() => devices.refetch()} />
          ) : devices.data?.length === 0 ? (
            <EmptyState
              icon={Monitor}
              title="No trusted devices"
              message="Devices you mark as trusted while verifying your email will appear here."
            />
          ) : (
            <ul className="divide-y divide-line">
              {devices.data.map((device) => (
                <li key={device.id} className="flex items-center gap-3 py-3">
                  <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                    <Monitor className="size-5" aria-hidden />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="flex items-center gap-2 text-sm font-medium text-ink">
                      <span className="truncate">{device.name ?? 'Trusted device'}</span>
                      {device.is_current ? <Badge tone="green">This device</Badge> : null}
                    </p>
                    <p className="text-xs text-ink-muted">
                      {device.ip_address ?? 'Unknown IP'} · last used {formatDateTime(device.last_used_at)}
                      {device.expires_at ? ` · expires ${formatDateTime(device.expires_at)}` : ''}
                    </p>
                  </div>
                  {!device.is_current ? (
                    <button
                      type="button"
                      aria-label={`Revoke ${device.name ?? 'device'}`}
                      className="rounded-lg p-2 text-ink-muted transition-colors hover:bg-red-50 hover:text-red-600"
                      onClick={async () => {
                        try {
                          await revokeDevice(device.id)
                          toast.success('Device revoked.')
                          devices.refetch()
                        } catch (err) {
                          toast.error(err.message)
                        }
                      }}
                    >
                      <Trash2 className="size-4" aria-hidden />
                    </button>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </Card>
      ) : null}

      <ConfirmDialog
        open={revokingAll}
        onClose={() => setRevokingAll(false)}
        onConfirm={handleRevokeAll}
        title="Revoke all trusted devices?"
        message="All devices will need email verification again on their next sign-in."
        confirmLabel="Revoke all"
        loading={revokingAll}
      />
    </div>
  )
}