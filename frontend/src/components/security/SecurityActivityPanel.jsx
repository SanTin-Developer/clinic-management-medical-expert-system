import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Activity, ShieldCheck } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import { CardSkeleton } from '../ui/Skeleton.jsx'
import { EmptyState } from '../ui/State.jsx'
import { securityEvents } from '../../api/auth.js'
import { formatDateTime } from '../../utils/format.js'
import { useLanguage } from '../../hooks/useLanguage.js'

const EVENT_LABELS = {
  'login.succeeded': 'Signed in',
  'login.failed': 'Failed sign-in',
  'login.blocked': 'Sign-in blocked',
  'login.locked': 'Account locked',
  logout: 'Signed out',
  'password.changed': 'Password changed',
  'two_factor.enrolled': 'Two-factor setup started',
  'two_factor.confirmed': 'Two-factor enabled',
  'two_factor.disabled': 'Two-factor disabled',
  'two_factor.failed': 'Failed two-factor attempt',
  'two_factor.challenge_issued': 'Two-factor challenge issued',
  'recovery_codes.regenerated': 'Recovery codes regenerated',
  'recovery_code.used': 'Recovery code used',
  'trusted_device.added': 'Trusted device added',
  'trusted_device.revoked': 'Trusted device revoked',
  'trusted_device.revoked_all': 'All trusted devices revoked',
  'email_change.requested': 'Email change requested',
  'email.changed': 'Email address changed',
  'account.invited': 'Staff invited',
  'account.activated': 'Account activated',
  'account.suspended': 'Account suspended',
  'account.reactivated': 'Account reactivated',
  'account.disabled': 'Account disabled',
  'step_up.granted': 'Identity confirmation',
  'step_up.denied': 'Identity confirmation failed',
  'step_up.used': 'Sensitive action confirmed',
}

const SEVERITY_TONES = {
  info: 'gray',
  warning: 'amber',
  critical: 'red',
}

// A coarse, advisory risk score (0 = routine). Values at or above this are
// highlighted so unusual activity stands out. It never blocks anything.
const RISK_ATTENTION_THRESHOLD = 2

function eventLabel(event) {
  return EVENT_LABELS[event] ?? event
}

export default function SecurityActivityPanel() {
  const { t } = useLanguage()
  const { data, isLoading, isError, error, refetch } = useQuery({
    queryKey: ['securityEvents'],
    queryFn: securityEvents,
  })

  const [expanded, setExpanded] = useState(false)
  const showDetails = expanded || !data || data.length <= 5

  return (
    <Card
      title={t('Recent security activity')}
      subtitle={t('The latest authentication and account events on your account.')}
      actions={
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('Refresh')}
        </Button>
      }
    >
      {isLoading ? (
        <CardSkeleton rows={5} />
      ) : isError ? (
        <p className="py-6 text-center text-sm text-red-600">{error.message}</p>
      ) : !data || data.length === 0 ? (
        <EmptyState
          icon={ShieldCheck}
          title={t('No security activity yet')}
          message={t('Sign-ins and account changes will appear here.')}
        />
      ) : (
        <div className="space-y-4">
          {/* Summary view */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="font-semibold text-ink">
                  {t(
                    data.length === 1
                      ? '{count} recent activity event'
                      : '{count} recent activity events',
                    { count: data.length },
                  )}
                </span>
                {data.length > 5 && (
                  <span className="text-xs text-ink-muted">
                    {t('(showing recent {range})', {
                      range: showDetails ? t('all') : '5',
                    })}
                  </span>
                )}
              </div>
              {data.length > 5 && (
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
                {data.map((event) => (
                  <li key={event.id} className="flex items-start gap-3 py-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                      <Activity className="size-4" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                        {t(eventLabel(event.event))}
                        <Badge tone={SEVERITY_TONES[event.severity] ?? 'gray'}>{t(event.severity)}</Badge>
                        {typeof event.risk_score === 'number' && event.risk_score >= RISK_ATTENTION_THRESHOLD ? (
                          <Badge tone="amber">{t('risk {score}', { score: event.risk_score })}</Badge>
                        ) : null}
                      </p>
                      <p className="text-xs text-ink-muted">
                        {formatDateTime(event.created_at)}
                        {event.ip_address ? ` · ${event.ip_address}` : ''}
                      </p>
                    </div>
                  </li>
                ))}
              </ul>
            ) : (
              <div className="space-y-2">
                {data.slice(0, 5).map((event) => (
                  <div key={event.id} className="flex items-start gap-3 rounded-lg border border-line p-3">
                    <span className="grid size-8 shrink-0 place-items-center rounded-xl bg-canvas text-ink-muted">
                      <Activity className="size-4" aria-hidden />
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-ink">
                        {t(eventLabel(event.event))}
                        <Badge tone={SEVERITY_TONES[event.severity] ?? 'gray'}>{t(event.severity)}</Badge>
                        {typeof event.risk_score === 'number' && event.risk_score >= RISK_ATTENTION_THRESHOLD ? (
                          <Badge tone="amber">{t('risk {score}', { score: event.risk_score })}</Badge>
                        ) : null}
                      </p>
                      <p className="text-xs text-ink-muted">
                        {formatDateTime(event.created_at)}
                        {event.ip_address ? ` · ${event.ip_address}` : ''}
                      </p>
                    </div>
                  </div>
                ))}
                {data.length > 5 && (
                  <div className="text-center">
                    <p className="text-sm text-ink-muted">
                      +{' '}
                      {t(
                        data.length - 5 === 1
                          ? '{count} more event'
                          : '{count} more events',
                        { count: data.length - 5 },
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
