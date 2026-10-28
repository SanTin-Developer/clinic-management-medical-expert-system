import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck, ExternalLink, Loader2, Newspaper, Trash2, XCircle } from 'lucide-react'
import { toast } from 'sonner'
import Dropdown from '../ui/Dropdown.jsx'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import { cn } from '../../utils/cn.js'
import { notificationApi, systemAlertApi } from '../../api/communication.js'
import { getEcho } from '../../lib/echo.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { formatDateTime } from '../../utils/format.js'
import { playNotificationSound, primeNotificationAudio } from '../../utils/notificationSound.js'

const BROADCAST_EVENT = '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated'

const KIND_LABELS = {
  new_message: 'Message',
  telegram_delivery_failed: 'Delivery issue',
  system_announcement: 'Announcement',
  patient_assigned: 'Patient assignment',
  nursing_task_assigned: 'Nursing task',
  nursing_task_overdue: 'Nursing task overdue',
}

function describe(notification, t) {
  if (notification._type === 'system') {
    const priority = notification.severity === 'critical' ? 'urgent' : notification.severity === 'warning' ? 'high' : 'normal'
    return {
      title: notification.title,
      body: notification.message ?? '',
      url: notification.url,
      priority,
    }
  }
  const data = notification.data ?? {}
  const kind = notification.kind ?? data.kind

  if (kind === 'new_message') {
    return {
      title: data.sender_name
        ? t('{name} sent a message', { name: data.sender_name })
        : t('New message'),
      body: data.preview ?? data.conversation_title ?? '',
      url: data.url,
    }
  }
  if (kind === 'telegram_delivery_failed') {
    return {
      title: t('Telegram delivery failed'),
      body: t('A Telegram copy of your message could not be delivered.'),
      url: data.url,
    }
  }
  if (kind === 'system_announcement') {
    return {
      title: data.title ?? t('Announcement'),
      body: data.body ?? '',
      url: data.url ?? '/news',
      priority: data.priority ?? 'normal',
    }
  }
  if (kind === 'patient_assigned') {
    return {
      title: t('New patient assignment'),
      body: [data.patient_name, data.ward_name, data.shift].filter(Boolean).join(' • '),
      url: data.url,
    }
  }
  if (kind === 'nursing_task_assigned') {
    return {
      title: data.title ?? t('Nursing task assigned'),
      body: [data.patient_name, data.task_type, data.priority].filter(Boolean).join(' • '),
      url: data.url,
      priority: data.priority,
    }
  }
  if (kind === 'nursing_task_overdue') {
    return {
      title: t('Nursing task overdue'),
      body: [data.title, data.patient_name, data.minutes_overdue ? t('{minutes} min overdue', { minutes: data.minutes_overdue }) : null]
        .filter(Boolean)
        .join(' • '),
      url: data.url,
      priority: 'urgent',
    }
  }
  return {
    title: data.title ?? t('Notification'),
    body: data.body ?? data.message ?? '',
    url: data.url,
  }
}

function kindLabel(notification, t) {
  if (notification._type === 'system') {
    if (notification.severity === 'critical') return t('Critical alert')
    if (notification.severity === 'warning') return t('Warning')
    return t('System alert')
  }
  const kind = notification.kind ?? notification.data?.kind
  return t(KIND_LABELS[kind] ?? 'Notification')
}

function detailsFor(notification, t) {
  if (notification._type === 'system') {
    const rows = []
    const add = (label, value) => {
      if (value === null || value === undefined || value === '') return
      rows.push({ label: t(label), value: String(value) })
    }
    add('Category', notification.category)
    add('Severity', notification.severity)
    add('Status', notification.status)
    add('Module', notification.module)
    return rows
  }
  const data = notification.data ?? {}
  const kind = notification.kind ?? data.kind
  const rows = []
  const add = (label, value) => {
    if (value === null || value === undefined || value === '') return
    rows.push({ label: t(label), value: String(value) })
  }

  if (kind === 'new_message') {
    add('Conversation', data.conversation_title)
    add('From', data.sender_name)
    add('Chat type', data.is_group ? t('Group') : t('Direct message'))
  } else if (kind === 'patient_assigned') {
    add('Patient', data.patient_name)
    add('Ward', data.ward_name)
    add('Shift', data.shift)
  } else if (kind === 'nursing_task_assigned') {
    add('Patient', data.patient_name)
    add('Task type', data.task_type)
    add('Priority', data.priority)
    add('Scheduled', data.scheduled_at ? formatDateTime(data.scheduled_at) : null)
  } else if (kind === 'nursing_task_overdue') {
    add('Patient', data.patient_name)
    add('Task type', data.task_type)
    add('Priority', data.priority)
    add('Scheduled', data.scheduled_at ? formatDateTime(data.scheduled_at) : null)
    add('Due', data.due_at ? formatDateTime(data.due_at) : null)
    add('Minutes overdue', data.minutes_overdue)
  } else if (kind === 'system_announcement') {
    add('Priority', data.priority)
  }

  return rows
}

export default function NotificationBell() {
  const { user, can } = useAuth()
  const { t } = useLanguage()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const greetedRef = useRef(false)
  const [open, setOpen] = useState(false)
  const [detail, setDetail] = useState(null)
  const [deletingId, setDeletingId] = useState(null)
  const canManageAlerts = can('notifications.manage')

  const unread = useQuery({
    queryKey: ['notifications', 'unread'],
    queryFn: notificationApi.unreadCount,
    refetchInterval: 60_000,
  })

  const systemUnread = useQuery({
    queryKey: ['system-alerts', 'unread'],
    queryFn: systemAlertApi.unreadCount,
    refetchInterval: 60_000,
  })

  const list = useQuery({
    queryKey: ['notifications', 'list'],
    queryFn: () => notificationApi.list({ page: 1 }),
    staleTime: 30_000,
  })

  const systemList = useQuery({
    queryKey: ['system-alerts', 'list'],
    queryFn: () => systemAlertApi.list({ per_page: 10 }),
    staleTime: 30_000,
  })

  useEffect(() => {
    primeNotificationAudio()

    const unlock = () => {
      primeNotificationAudio()
      window.removeEventListener('pointerdown', unlock)
      window.removeEventListener('keydown', unlock)
      window.removeEventListener('touchstart', unlock)
    }

    window.addEventListener('pointerdown', unlock)
    window.addEventListener('keydown', unlock)
    window.addEventListener('touchstart', unlock)

    return () => {
      window.removeEventListener('pointerdown', unlock)
      window.removeEventListener('keydown', unlock)
      window.removeEventListener('touchstart', unlock)
    }
  }, [])

  useEffect(() => {
    if (!user?.id) return undefined
    const echo = getEcho()
    if (!echo) return undefined

    const channel = echo.private(`notifications.${user.id}`)
    channel.listen(BROADCAST_EVENT, (payload) => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      playNotificationSound()
      const info = describe({ kind: payload?.kind, data: payload }, t)
      toast.info(info.title, { description: info.body || undefined })
    })

    // System alerts broadcast a payload-free invalidation signal on a private
    // channel gated by alert-category permissions. The client still re-reads
    // every alert through the permission-filtered API.
    const alertChannel = echo.private('system-alerts')
    alertChannel.listen('.system.alerts.updated', () => {
      queryClient.invalidateQueries({ queryKey: ['system-alerts'] })
    })

    return () => {
      echo.leave(`notifications.${user.id}`)
      echo.leave('system-alerts')
    }
  }, [user?.id, queryClient, t])

  useEffect(() => {
    if (greetedRef.current) return
    const count = unread.data ?? 0
    if (count <= 0) return

    greetedRef.current = true
    playNotificationSound()
    toast.info(
      count === 1
        ? t('You have 1 new notification')
        : t('You have {count} new notifications', { count }),
      { id: 'notification-greeting', description: t('Open the bell to review them.') },
    )
  }, [unread.data, t])

  const unreadCount = (unread.data ?? 0) + (systemUnread.data ?? 0)
  const notifications = list.data?.data ?? []
  const systemAlerts = (systemList.data?.data ?? []).map((a) => ({
    ...a,
    _type: 'system',
    read_at: a.read_at,
    created_at: a.created_at,
    data: a.data ?? {},
  }))
  const allItems = [...systemAlerts, ...notifications].sort((a, b) => {
    const da = new Date(a.created_at).getTime()
    const db = new Date(b.created_at).getTime()
    return db - da
  })
  const detailInfo = detail ? describe(detail, t) : null
  const detailMeta = detail ? detailsFor(detail, t) : []

  const openNotification = (notification) => {
    setOpen(false)
    setDetail(notification)
    if (!notification.read_at) {
      if (notification._type === 'system') {
        systemAlertApi
          .markRead(notification.id)
          .then(() => {
            queryClient.invalidateQueries({ queryKey: ['system-alerts'] })
            queryClient.invalidateQueries({ queryKey: ['notifications'] })
          })
          .catch(() => {})
      } else {
        notificationApi
          .markRead(notification.id)
          .then(() => queryClient.invalidateQueries({ queryKey: ['notifications'] }))
          .catch(() => {
            // Keep the detail open even if the read receipt could not be saved.
          })
      }
    }
  }

  const openDestination = () => {
    const url = detailInfo?.url
    if (!url) return
    setDetail(null)
    if (/^https?:\/\//i.test(url)) {
      window.location.assign(url)
      return
    }
    navigate(url)
  }

  const acknowledgeNotification = async (notification) => {
    if (!notification?.id || deletingId) return
    setDeletingId(notification.id)
    try {
      await systemAlertApi.acknowledge(notification.id)
      await queryClient.invalidateQueries({ queryKey: ['system-alerts'] })
      toast.success(t('Alert acknowledged.'))
    } catch (err) {
      toast.error(err.message || t('Something went wrong.'))
    } finally {
      setDeletingId(null)
    }
  }

  const deleteNotification = async (notification) => {
    if (!notification?.id || deletingId) return
    setDeletingId(notification.id)
    try {
      if (notification._type === 'system') {
        await systemAlertApi.resolve(notification.id)
        await queryClient.invalidateQueries({ queryKey: ['system-alerts'] })
        await queryClient.invalidateQueries({ queryKey: ['notifications'] })
      } else {
        await notificationApi.remove(notification.id)
        await queryClient.invalidateQueries({ queryKey: ['notifications'] })
      }
      if (detail?.id === notification.id) setDetail(null)
      toast.success(t('Notification deleted.'))
    } catch (err) {
      toast.error(err.message || t('Something went wrong.'))
    } finally {
      setDeletingId(null)
    }
  }

  const markAll = async () => {
    try {
      await notificationApi.markAllRead()
      await systemAlertApi.markAllRead()
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      queryClient.invalidateQueries({ queryKey: ['system-alerts'] })
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <>
      <Dropdown
        width="w-80"
        isOpen={open}
        onOpenChange={setOpen}
        panelRole="dialog"
        hasPopup="dialog"
        panelLabel={t('Notifications')}
        trigger={
          <button
            type="button"
            aria-label={
              unreadCount
                ? t('Notifications ({count} unread)', { count: unreadCount })
                : t('Notifications')
            }
            className="relative grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas"
          >
            <Bell className="size-5" aria-hidden />
            {unreadCount > 0 ? (
              <span className="absolute right-1.5 top-1.5 grid min-w-4 place-items-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-4 text-white">
                {unreadCount > 9 ? '9+' : unreadCount}
              </span>
            ) : null}
          </button>
        }
      >
        <div className="flex items-center justify-between px-4 py-2">
          <span className="text-sm font-semibold text-ink">{t('Notifications')}</span>
          {unreadCount > 0 ? (
            <button
              type="button"
              onClick={markAll}
              className="flex items-center gap-1 text-xs font-semibold text-brand hover:text-brand-dark"
            >
              <CheckCheck className="size-3.5" aria-hidden /> {t('Mark all read')}
            </button>
          ) : null}
        </div>
        <div className="max-h-96 overflow-y-auto border-t border-line">
          {list.isLoading && systemList.isLoading ? (
            <p className="px-4 py-6 text-center text-sm text-ink-muted">{t('Loading…')}</p>
          ) : allItems.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-ink-muted">{t("You're all caught up.")}</p>
          ) : (
            allItems.map((notification) => {
              const info = describe(notification, t)
              const isDeleting = deletingId === notification.id

              return (
                <div
                  key={`${notification._type || 'notif'}-${notification.id}`}
                  className={cn(
                    'flex items-start border-b border-l-4 border-line border-l-transparent last:border-b-0',
                    !notification.read_at && 'bg-brand-faint',
                    info.priority === 'urgent' && 'border-l-red-500',
                    notification._type === 'system' && notification.severity === 'warning' && info.priority !== 'urgent' && 'border-l-amber-500',
                  )}
                >
                  <button
                    type="button"
                    aria-haspopup="dialog"
                    onClick={() => openNotification(notification)}
                    className="flex min-w-0 flex-1 items-start px-4 py-3 text-left transition-colors hover:bg-canvas/60"
                  >
                    <span className="flex min-w-0 flex-1 flex-col">
                      <span className="flex items-center gap-2">
                        {!notification.read_at ? (
                          <span className="size-2 shrink-0 rounded-full bg-brand" aria-hidden />
                        ) : null}
                        <span className="truncate text-sm font-semibold text-ink">{info.title}</span>
                      </span>
                      {info.body ? (
                        <span className="mt-0.5 block truncate text-xs text-ink-muted">{info.body}</span>
                      ) : null}
                      <span className="mt-0.5 block text-[11px] text-ink-muted">
                        {formatDateTime(notification.created_at)}
                      </span>
                    </span>
                  </button>
                  <button
                    type="button"
                    onClick={() => deleteNotification(notification)}
                    disabled={isDeleting}
                    aria-label={
                      notification._type === 'system'
                        ? t('Resolve alert')
                        : t('Delete notification')
                    }
                    title={
                      notification._type === 'system'
                        ? t('Resolve alert')
                        : t('Delete notification')
                    }
                    className="mr-2 mt-3 grid size-7 shrink-0 place-items-center rounded-lg text-ink-muted/70 transition-colors hover:bg-red-50 hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 disabled:opacity-50 dark:hover:bg-red-500/10"
                  >
                    {isDeleting ? (
                      <Loader2 className="size-3.5 animate-spin" aria-hidden />
                    ) : notification._type === 'system' ? (
                      <XCircle className="size-3.5" aria-hidden />
                    ) : (
                      <Trash2 className="size-3.5" aria-hidden />
                    )}
                  </button>
                </div>
              )
            })
          )}
        </div>
        <div className="flex border-t border-line">
          <button
            type="button"
            onClick={() => {
              setOpen(false)
              navigate('/notifications')
            }}
            className="flex flex-1 items-center justify-center gap-1 px-4 py-2.5 text-xs font-semibold text-brand transition-colors hover:bg-canvas"
          >
            <Bell className="size-3.5" aria-hidden /> {t('View all alerts')}
          </button>
          <button
            type="button"
            onClick={() => {
              setOpen(false)
              navigate('/news')
            }}
            className="flex flex-1 items-center justify-center gap-1 border-l border-line px-4 py-2.5 text-xs font-semibold text-brand transition-colors hover:bg-canvas"
          >
            <Newspaper className="size-3.5" aria-hidden /> {t('News & announcements')}
          </button>
        </div>
      </Dropdown>
      <Modal
        open={Boolean(detail)}
        onClose={() => setDetail(null)}
        title={detailInfo?.title ?? t('Notification')}
        size="sm"
        footer={
          detail ? (
            <>
              {detail._type === 'system' && canManageAlerts && detail.status !== 'resolved' ? (
                <Button
                  variant="outline"
                  className="mr-auto"
                  loading={deletingId === detail.id}
                  onClick={() => acknowledgeNotification(detail)}
                >
                  {t('Acknowledge')}
                </Button>
              ) : null}
              {detail._type !== 'system' ? (
                <Button
                  variant="ghost"
                  className="mr-auto text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10"
                  loading={deletingId === detail.id}
                  onClick={() => deleteNotification(detail)}
                >
                  {deletingId === detail.id ? null : <Trash2 className="size-4" aria-hidden />}
                  {t('Delete')}
                </Button>
              ) : null}
              {detail._type === 'system' && canManageAlerts && detail.status !== 'resolved' ? (
                <Button
                  variant="ghost"
                  className="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-500/10"
                  loading={deletingId === detail.id}
                  onClick={() => deleteNotification(detail)}
                >
                  {deletingId === detail.id ? null : <XCircle className="size-4" aria-hidden />}
                  {t('Resolve')}
                </Button>
              ) : null}
              <Button
                variant="outline"
                onClick={() => setDetail(null)}
                disabled={deletingId === detail.id}
              >
                {t('Close')}
              </Button>
              {detailInfo?.url ? (
                <Button onClick={openDestination} disabled={deletingId === detail.id}>
                  <ExternalLink className="size-4" aria-hidden /> {t('Open')}
                </Button>
              ) : null}
            </>
          ) : null
        }
      >
        {detail ? (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2 text-xs text-ink-muted">
              <span className="inline-flex items-center rounded-full bg-brand-soft px-2 py-0.5 font-semibold text-brand">
                {kindLabel(detail, t)}
              </span>
              <span>{formatDateTime(detail.created_at)}</span>
            </div>
            <p className="whitespace-pre-wrap break-words text-sm leading-relaxed text-ink">
              {detailInfo?.body || t('No additional details.')}
            </p>
            {detailMeta.length > 0 ? (
              <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 rounded-xl border border-line bg-canvas/60 p-3 text-sm">
                {detailMeta.map((row) => (
                  <div key={row.label} className="contents">
                    <dt className="text-ink-muted">{row.label}</dt>
                    <dd className="break-words font-medium text-ink">{row.value}</dd>
                  </div>
                ))}
              </dl>
            ) : null}
          </div>
        ) : null}
      </Modal>
    </>
  )
}
