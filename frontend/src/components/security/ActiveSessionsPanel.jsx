import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Laptop, LogOut, Smartphone } from 'lucide-react'
import { listSessions, revokeOtherSessions, revokeSession } from '../../api/auth.js'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import { CardSkeleton } from '../ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../ui/State.jsx'
import { formatDateTime } from '../../utils/format.js'
import { useLanguage } from '../../hooks/useLanguage.js'

function getSessionIcon(name = '') {
  const lower = name.toLowerCase()
  if (lower.includes('phone') || lower.includes('mobile') || lower.includes('android') || lower.includes('ios')) {
    return Smartphone
  }
  return Laptop
}

export default function ActiveSessionsPanel() {
  const { t } = useLanguage()
  const sessions = useQuery({
    queryKey: ['sessions'],
    queryFn: listSessions,
  })

  const [revoking, setRevoking] = useState(null)
  const [revokingOthers, setRevokingOthers] = useState(false)
  const [expanded, setExpanded] = useState(false)

  const handleRevoke = async (session) => {
    if (session.is_current) return
    setRevoking(session.id)
    try {
      await revokeSession(session.id)
      toast.success(t('Session revoked.'))
      sessions.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setRevoking(null)
    }
  }

  const handleRevokeOthers = async () => {
    setRevokingOthers(true)
    try {
      await revokeOtherSessions()
      toast.success(t('Other sessions revoked.'))
      sessions.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setRevokingOthers(false)
    }
  }

  const hasOthers = sessions.data?.some((session) => !session.is_current)
  const showDetails = expanded || !hasOthers || sessions.data?.length <= 3

  return (
    <Card
      title={t('Active sessions')}
      subtitle={t('Sessions currently signed in to your account.')}
      actions={
        hasOthers ? (
          <Button variant="outline" size="sm" onClick={handleRevokeOthers} loading={revokingOthers}>
            <LogOut className="size-4" aria-hidden /> {t('Sign out of other sessions')}
          </Button>
        ) : null
      }
    >
      {sessions.isLoading ? (
        <CardSkeleton rows={3} />
      ) : sessions.isError ? (
        <ErrorState message={sessions.error.message} onRetry={() => sessions.refetch()} />
      ) : sessions.data?.length === 0 ? (
        <EmptyState
          icon={Laptop}
          title={t('No active sessions')}
          message={t("You don't have any active sessions right now.")}
        />
      ) : (
        <div className="space-y-4">
          {/* Summary view */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="font-semibold text-ink">
                  {t(
                    sessions.data.length === 1
                      ? '{count} active session'
                      : '{count} active sessions',
                    { count: sessions.data.length },
                  )}
                </span>
                {sessions.data.filter((s) => !s.is_current).length > 0 ? (
                  <span className="text-xs text-ink-muted">
                    {t(
                      sessions.data.filter((s) => !s.is_current).length === 1
                        ? '(includes {count} other device)'
                        : '(includes {count} other devices)',
                      { count: sessions.data.filter((s) => !s.is_current).length },
                    )}
                  </span>
                ) : null}
              </div>
              {hasOthers && sessions.data.length > 3 && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => setExpanded(!expanded)}
                >
                  {expanded ? t('Show less') : t('Show all')}
                </Button>
              )}
            </div>
            {showDetails ? (
              <ul className="divide-y divide-line">
                {sessions.data.map((session) => {
                  const Icon = getSessionIcon(session.name)
                  return (
                    <li key={session.id} className="flex items-center gap-3 py-3">
                      <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                        <Icon className="size-5" aria-hidden />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-2 text-sm font-medium text-ink">
                          <span className="truncate">{session.name || t('Session')}</span>
                          {session.is_current ? <Badge tone="green">{t('This session')}</Badge> : null}
                        </p>
                        <p className="text-xs text-ink-muted">
                          {t('Last used {date}', {
                            date: session.last_used_at ? formatDateTime(session.last_used_at) : '—',
                          })}
                          {session.created_at
                            ? ` · ${t('created {date}', { date: formatDateTime(session.created_at) })}`
                            : ''}
                          {session.expires_at
                            ? ` · ${t('expires {date}', { date: formatDateTime(session.expires_at) })}`
                            : ''}
                        </p>
                      </div>
                      {!session.is_current ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleRevoke(session)}
                          loading={revoking === session.id}
                        >
                          {t('Sign out')}
                        </Button>
                      ) : null}
                    </li>
                  )
                })}
              </ul>
            ) : (
              <div className="space-y-2">
                {sessions.data.slice(0, 3).map((session) => {
                  const Icon = getSessionIcon(session.name)
                  return (
                    <div key={session.id} className="flex items-center gap-3 rounded-lg border border-line p-3">
                      <span className="grid size-8 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                        <Icon className="size-4" aria-hidden />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="flex items-center gap-2 text-sm font-medium text-ink">
                          <span className="truncate">{session.name || t('Session')}</span>
                          {session.is_current ? <Badge tone="green">{t('This session')}</Badge> : null}
                        </p>
                        <p className="text-xs text-ink-muted">
                          {t('Last used {date}', {
                            date: session.last_used_at ? formatDateTime(session.last_used_at) : '—',
                          })}
                        </p>
                      </div>
                      {!session.is_current ? (
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleRevoke(session)}
                          loading={revoking === session.id}
                        >
                          {t('Sign out')}
                        </Button>
                      ) : null}
                    </div>
                  )
                })}
                {hasOthers && sessions.data.length > 3 && (
                  <div className="text-center">
                    <p className="text-sm text-ink-muted">
                      +{' '}
                      {t(
                        sessions.data.length - 3 === 1
                          ? '{count} more session'
                          : '{count} more sessions',
                        { count: sessions.data.length - 3 },
                      )}
                    </p>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </Card>
  )
}
