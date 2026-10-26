import { useNavigate } from 'react-router-dom'
import { MessageSquare } from 'lucide-react'
import { useAuth } from '../../hooks/useAuth.js'
import { useChatUnread } from '../../hooks/useChatUnread.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function MessagesButton() {
  const { can } = useAuth()
  const { t } = useLanguage()
  const navigate = useNavigate()
  const unread = useChatUnread()

  if (!can('chat.view')) return null

  return (
    <button
      type="button"
      onClick={() => navigate('/chat')}
      aria-label={unread > 0 ? t('Messages, {count} unread', { count: unread }) : t('Messages')}
      title={t('Messages')}
      className="relative grid size-10 place-items-center rounded-lg text-ink-muted transition-colors hover:bg-canvas hover:text-ink"
    >
      <MessageSquare className="size-5" aria-hidden />
      {unread > 0 ? (
        <span className="absolute right-1 top-1 grid min-w-4 place-items-center rounded-full bg-danger px-1 text-[10px] font-bold leading-4 text-white">
          {unread > 9 ? '9+' : unread}
        </span>
      ) : null}
    </button>
  )
}
