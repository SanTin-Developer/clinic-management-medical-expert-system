import { useQuery } from '@tanstack/react-query'
import { conversationApi } from '../api/communication.js'
import { useAuth } from './useAuth.js'

export function useChatUnread() {
  const { can } = useAuth()
  const enabled = can('chat.view')

  const query = useQuery({
    queryKey: ['conversations', 'list'],
    queryFn: () => conversationApi.list({ page: 1 }),
    enabled,
    refetchInterval: 60_000,
  })

  const total = (query.data?.data ?? []).reduce(
    (sum, conversation) => sum + (conversation.unread_count ?? 0),
    0,
  )

  return enabled ? total : 0
}
