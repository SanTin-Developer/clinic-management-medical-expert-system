import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Bell } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Toggle from '../ui/Toggle.jsx'
import { CardSkeleton } from '../ui/Skeleton.jsx'
import { ErrorState } from '../ui/State.jsx'
import { notificationApi } from '../../api/communication.js'
import { useLanguage } from '../../hooks/useLanguage.js'

const OPTIONS = [
  { key: 'new_message', label: 'Direct messages', hint: 'Alerts when a colleague sends you a private message.' },
  { key: 'group_updates', label: 'Group messages', hint: 'Alerts for activity in group conversations.' },
  { key: 'system_announcements', label: 'System announcements', hint: 'Important notices from clinic administrators.' },
  { key: 'telegram', label: 'Telegram delivery', hint: 'Mirror these notifications to your linked Telegram account.' },
]

export default function NotificationPreferencesPanel() {
  const { t } = useLanguage()
  const queryClient = useQueryClient()

  const preferences = useQuery({
    queryKey: ['notification-preferences'],
    queryFn: notificationApi.preferences,
  })

  const update = async (key, value) => {
    try {
      const data = await notificationApi.updatePreferences({ [key]: value })
      queryClient.setQueryData(['notification-preferences'], data)
    } catch (err) {
      toast.error(err.message)
      preferences.refetch()
    }
  }

  return (
    <Card
      title={t('Notification preferences')}
      subtitle={t('Choose which events reach you in-app and via Telegram.')}
    >
      {preferences.isLoading ? (
        <CardSkeleton rows={4} />
      ) : preferences.isError ? (
        <ErrorState message={preferences.error.message} onRetry={() => preferences.refetch()} />
      ) : (
        <div className="space-y-1">
          {OPTIONS.map((option) => (
            <div key={option.key} className="border-b border-line py-3 last:border-b-0">
              <Toggle
                label={t(option.label)}
                hint={t(option.hint)}
                checked={Boolean(preferences.data?.[option.key])}
                onChange={(value) => update(option.key, value)}
              />
            </div>
          ))}
          <p className="flex items-center gap-1.5 pt-3 text-xs text-ink-muted">
            <Bell className="size-3.5" aria-hidden />
            {t('In-app notifications always appear in the bell menu; these preferences control which events are delivered.')}
          </p>
        </div>
      )}
    </Card>
  )
}
