import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell, CheckCheck } from 'lucide-react'
import { toast } from 'sonner'
import Dropdown from '../ui/Dropdown.jsx'
import { cn } from '../../utils/cn.js'
import { notificationApi } from '../../api/communication.js'
import { getEcho } from '../../lib/echo.js'
import { useAuth } from '../../hooks/useAuth.js'
import { formatDateTime } from '../../utils/format.js'

const BROADCAST_EVENT = '.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated'

function describe(notification) {
  const data = notification.data ?? {}
  if (notification.kind === 'new_message' || data.kind === 'new_message') {
    return {
      title: data.sender_name ? `${data.sender_name} sent a message` : 'New message',
      body: data.preview ?? data.conversation_title ?? '',
      url: data.url,
    }
  }
  if (notification.kind === 'telegram_delivery_failed' || data.kind === 'telegram_delivery_failed') {
    return {
      title: 'Telegram delivery failed',
      body: 'A Telegram copy of your message could not be delivered.',
      url: data.url,
    }
  }
  return {
    title: data.title ?? 'Notification',
    body: data.body ?? data.message ?? '',
    url: data.url,
  }
}

export default function NotificationBell() {
  const { user } = useAuth()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const unread = useQuery({
    queryKey: ['notifications', 'unread'],
    queryFn: notificationApi.unreadCount,
    refetchInterval: 60_000,
  })

  const list = useQuery({
    queryKey: ['notifications', 'list'],
    queryFn: () => notificationApi.list({ page: 1 }),
    staleTime: 30_000,
  })

  useEffect(() => {
    if (!user?.id) return undefined
    const echo = getEcho()
    if (!echo) return undefined

    const channel = echo.private(`notifications.${user.id}`)
    channel.listen(BROADCAST_EVENT, (payload) => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
      const info = describe({ kind: payload?.kind, data: payload })
      toast.info(info.title, { description: info.body || undefined })
    })

    return () => {
      echo.leave(`notifications.${user.id}`)
    }
  }, [user?.id, queryClient])

  const unreadCount = unread.data ?? 0
  const notifications = list.data?.data ?? []

  const openNotification = async (notification) => {
    const info = describe(notification)
    if (!notification.read_at) {
      try {
        await notificationApi.markRead(notification.id)
        queryClient.invalidateQueries({ queryKey: ['notifications'] })
      } catch {
        // Ignore; navigation should still proceed.
      }
    }
    if (info.url) navigate(info.url)
  }

  const markAll = async () => {
    try {
      await notificationApi.markAllRead()
      queryClient.invalidateQueries({ queryKey: ['notifications'] })
    } catch (err) {
      toast.error(err.message)
    }
  }

  return (
    <Dropdown
      width="w-80"
      trigger={
        <button
          type="button"
          aria-label={`Notifications${unreadCount ? ` (${unreadCount} unread)` : ''}`}
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
        <span className="text-sm font-semibold text-ink">Notifications</span>
        {unreadCount > 0 ? (
          <button
            type="button"
            onClick={markAll}
            className="flex items-center gap-1 text-xs font-semibold text-brand hover:text-brand-dark"
          >
            <CheckCheck className="size-3.5" aria-hidden /> Mark all read
          </button>
        ) : null}
      </div>
      <div className="max-h-96 overflow-y-auto border-t border-line">
        {list.isLoading ? (
          <p className="px-4 py-6 text-center text-sm text-ink-muted">Loading…</p>
        ) : notifications.length === 0 ? (
          <p className="px-4 py-6 text-center text-sm text-ink-muted">You're all caught up.</p>
        ) : (
          notifications.map((notification) => {
            const info = describe(notification)
            return (
              <button
                key={notification.id}
                type="button"
                onClick={() => openNotification(notification)}
                className={cn(
                  'block w-full border-b border-line px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-canvas',
                  !notification.read_at && 'bg-brand-faint',
                )}
              >
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
              </button>
            )
          })
        )}
      </div>
    </Dropdown>
  )
}
