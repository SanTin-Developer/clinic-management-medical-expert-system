import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowLeft, MessagesSquare, Plus, Send, UserPlus, Users } from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Button from '../components/ui/Button.jsx'
import Badge from '../components/ui/Badge.jsx'
import Dropdown, { DropdownItem } from '../components/ui/Dropdown.jsx'
import Modal from '../components/ui/Modal.jsx'
import Textarea from '../components/ui/Textarea.jsx'
import { Spinner } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import NewConversationModal from '../components/communication/NewConversationModal.jsx'
import { cn } from '../utils/cn.js'
import { conversationApi } from '../api/communication.js'
import { getEcho } from '../lib/echo.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime, formatTime, truncate } from '../utils/format.js'

const BROADCAST_EVENT = '.message.sent'
const READ_EVENT = '.messages.read'

function initials(name = '') {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function normalizeMessage(message) {
  return {
    id: message.id,
    client_id: message.client_id ?? null,
    status: message.status ?? 'sent',
    conversation_id: message.conversation_id,
    body: message.body,
    source: message.source ?? 'web',
    created_at: message.created_at,
    sender: {
      id: message.sender?.id ?? message.sender_id ?? null,
      name: message.sender?.name ?? 'Unknown',
      username: message.sender?.username ?? null,
    },
  }
}

function ConversationRow({ conversation, active, onClick }) {
  const last = conversation.last_message
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex w-full items-center gap-3 border-b border-line px-4 py-3 text-left transition-colors last:border-b-0',
        active ? 'bg-brand-faint' : 'hover:bg-canvas',
      )}
    >
      <span className="grid size-10 shrink-0 place-items-center rounded-full bg-brand-soft text-sm font-bold text-brand">
        {conversation.is_group ? <Users className="size-5" aria-hidden /> : initials(conversation.title)}
      </span>
      <span className="min-w-0 flex-1">
        <span className="flex items-center justify-between gap-2">
          <span className="truncate text-sm font-semibold text-ink">{conversation.title}</span>
          {last ? (
            <span className="shrink-0 text-[11px] text-ink-muted">{formatTime(last.created_at?.slice(11, 16))}</span>
          ) : null}
        </span>
        <span className="mt-0.5 flex items-center justify-between gap-2">
          <span className="truncate text-xs text-ink-muted">
            {last ? `${last.sender?.name ? `${last.sender.name}: ` : ''}${truncate(last.body, 48)}` : 'No messages yet'}
          </span>
          {conversation.unread_count > 0 ? (
            <span className="grid min-w-5 shrink-0 place-items-center rounded-full bg-brand px-1.5 text-[10px] font-bold leading-5 text-white">
              {conversation.unread_count > 9 ? '9+' : conversation.unread_count}
            </span>
          ) : null}
        </span>
      </span>
    </button>
  )
}

export default function ChatPage() {
  const { user, can } = useAuth()
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [messages, setMessages] = useState([])
  const [loadingMessages, setLoadingMessages] = useState(false)
  const [loadingOlder, setLoadingOlder] = useState(false)
  const [page, setPage] = useState(1)
  const [hasMore, setHasMore] = useState(false)
  const [draft, setDraft] = useState('')
  const [sending, setSending] = useState(false)
  const [newOpen, setNewOpen] = useState(false)
  const [membersOpen, setMembersOpen] = useState(false)
  const scrollRef = useRef(null)

  const activeId = id ? Number(id) : null

  const conversations = useQuery({
    queryKey: ['conversations', 'list'],
    queryFn: () => conversationApi.list({ page: 1 }),
    refetchInterval: 60_000,
  })

  const active = useQuery({
    queryKey: ['conversation', activeId],
    queryFn: () => conversationApi.get(activeId),
    enabled: Boolean(activeId),
  })

  const items = conversations.data?.data ?? []
  const currentConversation = active.data ?? items.find((c) => c.id === activeId) ?? null

  useEffect(() => {
    if (!activeId) {
      setMessages([])
      return undefined
    }

    let cancelled = false
    setLoadingMessages(true)
    setLoadingOlder(false)
    setMessages([])
    setPage(1)
    setHasMore(false)

    conversationApi
      .messages(activeId, { page: 1 })
      .then((res) => {
        if (cancelled) return
        const ordered = [...(res.data ?? [])].reverse().map(normalizeMessage)
        setMessages(ordered)
        setHasMore((res.current_page ?? 1) < (res.last_page ?? 1))
      })
      .catch((err) => {
        if (!cancelled) toast.error(err.message)
      })
      .finally(() => {
        if (!cancelled) setLoadingMessages(false)
      })

    conversationApi
      .markRead(activeId)
      .then(() => queryClient.invalidateQueries({ queryKey: ['conversations'] }))
      .catch(() => {})

    return () => {
      cancelled = true
    }
  }, [activeId, queryClient])

  useEffect(() => {
    if (!activeId) return undefined
    const echo = getEcho()
    if (!echo) return undefined

    const channel = echo.private(`conversation.${activeId}`)
    channel.listen(BROADCAST_EVENT, (payload) => {
      const message = normalizeMessage(payload)
      setMessages((prev) => {
        if (prev.some((m) => m.id === message.id)) return prev
        const pending = prev.findIndex(
          (m) =>
            m.client_id &&
            m.status !== 'sent' &&
            m.body === message.body &&
            m.sender.id === message.sender.id,
        )
        if (pending !== -1) {
          const next = [...prev]
          next[pending] = { ...message, client_id: prev[pending].client_id }
          return next
        }
        return [...prev, message]
      })
      conversationApi.markRead(activeId, message.id).catch(() => {})
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
    })

    channel.listen(READ_EVENT, () => {
      queryClient.invalidateQueries({ queryKey: ['conversation', activeId] })
    })

    return () => {
      echo.leave(`conversation.${activeId}`)
    }
  }, [activeId, queryClient])

  const newestId = messages.length ? messages[messages.length - 1].id : null

  useEffect(() => {
    const node = scrollRef.current
    if (node) node.scrollTop = node.scrollHeight
  }, [newestId, loadingMessages])

  const loadOlder = async () => {
    if (!activeId || loadingOlder || !hasMore) return
    const nextPage = page + 1
    setLoadingOlder(true)
    try {
      const res = await conversationApi.messages(activeId, { page: nextPage })
      const older = [...(res.data ?? [])].reverse().map(normalizeMessage)
      setMessages((prev) => {
        const known = new Set(prev.map((m) => m.id))
        return [...older.filter((m) => !known.has(m.id)), ...prev]
      })
      setPage(nextPage)
      setHasMore((res.current_page ?? nextPage) < (res.last_page ?? nextPage))
    } catch (err) {
      toast.error(err.message)
    } finally {
      setLoadingOlder(false)
    }
  }

  const sendBody = async (body, clientId) => {
    try {
      const saved = normalizeMessage(await conversationApi.send(activeId, body))
      setMessages((prev) =>
        prev.map((m) => (m.client_id === clientId ? { ...saved, client_id: clientId } : m)),
      )
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
    } catch (err) {
      setMessages((prev) =>
        prev.map((m) => (m.client_id === clientId ? { ...m, status: 'failed' } : m)),
      )
      toast.error(err.message)
    }
  }

  const handleSend = async (event) => {
    event?.preventDefault()
    const body = draft.trim()
    if (!body || !activeId || sending) return

    const clientId = `local-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const optimistic = {
      id: clientId,
      client_id: clientId,
      status: 'sending',
      conversation_id: activeId,
      body,
      source: 'web',
      created_at: new Date().toISOString(),
      sender: { id: user?.id ?? null, name: user?.name ?? 'You', username: user?.username ?? null },
    }

    setMessages((prev) => [...prev, optimistic])
    setDraft('')
    setSending(true)
    await sendBody(body, clientId)
    setSending(false)
  }

  const retryMessage = async (message) => {
    if (!message.client_id) return
    setMessages((prev) =>
      prev.map((m) => (m.client_id === message.client_id ? { ...m, status: 'sending' } : m)),
    )
    await sendBody(message.body, message.client_id)
  }

  const receiptLabel = (message) => {
    if (!message.id || String(message.id).startsWith('local-')) return null
    const others = (currentConversation?.participants ?? []).filter((p) => p.id !== user?.id)
    if (others.length === 0) return null
    const readers = others.filter((p) => (p.last_read_message_id ?? 0) >= message.id).length
    if (readers === 0) return 'Delivered'
    if (readers >= others.length) return 'Seen'
    return `Seen by ${readers}`
  }

  const handleKeyDown = (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      handleSend()
    }
  }

  const handleCreated = (conversation) => {
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
    navigate(`/chat/${conversation.id}`)
  }

  const leaveConversation = async () => {
    if (!activeId) return
    try {
      await conversationApi.removeParticipant(activeId, user.id)
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
      toast.success('You left the conversation.')
      navigate('/chat')
    } catch (err) {
      toast.error(err.message)
    }
  }

  const canManageActive = useMemo(() => {
    if (!currentConversation) return false
    return can('chat.manage') || currentConversation.created_by === user?.id
  }, [currentConversation, can, user?.id])

  return (
    <div className="flex h-[calc(100vh-9rem)] flex-col">
      <PageHeader
        title="Team Chat"
        description="Secure direct and group messaging for clinic staff."
        breadcrumb={[{ label: 'Team Chat' }]}
        actions={
          can('chat.create') ? (
            <Button onClick={() => setNewOpen(true)}>
              <Plus className="size-4" aria-hidden /> New conversation
            </Button>
          ) : null
        }
      />

      <div className="grid min-h-0 flex-1 grid-cols-1 grid-rows-[minmax(0,1fr)] gap-4 md:grid-cols-[320px_1fr]">
        <Card
          bodyClassName="flex min-h-0 flex-1 flex-col p-0"
          className={cn('min-h-0 overflow-hidden', activeId ? 'hidden md:flex md:flex-col' : 'flex flex-col')}
        >
          <div className="border-b border-line px-4 py-3">
            <h2 className="text-sm font-semibold text-ink">Conversations</h2>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto">
            {conversations.isLoading ? (
              <div className="grid place-items-center py-10">
                <Spinner />
              </div>
            ) : conversations.isError ? (
              <ErrorState compact message={conversations.error.message} onRetry={() => conversations.refetch()} />
            ) : items.length === 0 ? (
              <EmptyState
                icon={MessagesSquare}
                title="No conversations"
                message="Start a direct or group chat with your colleagues."
              />
            ) : (
              items.map((conversation) => (
                <ConversationRow
                  key={conversation.id}
                  conversation={conversation}
                  active={conversation.id === activeId}
                  onClick={() => navigate(`/chat/${conversation.id}`)}
                />
              ))
            )}
          </div>
        </Card>

        <Card
          bodyClassName="flex min-h-0 flex-1 flex-col p-0"
          className={cn('min-h-0 overflow-hidden', activeId ? 'flex flex-col' : 'hidden md:flex md:flex-col')}
        >
          {!activeId ? (
            <div className="grid flex-1 place-items-center">
              <EmptyState
                icon={MessagesSquare}
                title="Select a conversation"
                message="Choose a conversation from the list or start a new one to begin messaging."
              />
            </div>
          ) : (
            <>
              <div className="flex items-center gap-3 border-b border-line px-4 py-3">
                <button
                  type="button"
                  onClick={() => navigate('/chat')}
                  aria-label="Back to conversations"
                  className="grid size-9 place-items-center rounded-lg text-ink-muted hover:bg-canvas md:hidden"
                >
                  <ArrowLeft className="size-5" aria-hidden />
                </button>
                <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand">
                  {currentConversation?.is_group ? (
                    <Users className="size-4" aria-hidden />
                  ) : (
                    initials(currentConversation?.title)
                  )}
                </span>
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-semibold text-ink">
                    {currentConversation?.title ?? 'Conversation'}
                  </p>
                  {currentConversation?.is_group ? (
                    <button
                      type="button"
                      onClick={() => setMembersOpen(true)}
                      className="truncate text-xs text-ink-muted hover:text-brand hover:underline"
                    >
                      {(currentConversation.participants ?? []).length} participants
                    </button>
                  ) : null}
                </div>
                {currentConversation?.is_group ? <Badge tone="brand">Group</Badge> : null}
                <Dropdown
                  trigger={
                    <button
                      type="button"
                      aria-label="Conversation options"
                      className="grid size-9 place-items-center rounded-lg text-ink-muted hover:bg-canvas"
                    >
                      <span className="text-lg leading-none">⋯</span>
                    </button>
                  }
                >
                  {currentConversation?.is_group ? (
                    <DropdownItem onClick={() => setMembersOpen(true)}>
                      <Users className="size-4" aria-hidden /> View members
                    </DropdownItem>
                  ) : null}
                  {currentConversation?.is_group && canManageActive ? (
                    <DropdownItem onClick={() => setNewOpen(true)}>
                      <UserPlus className="size-4" aria-hidden /> New group
                    </DropdownItem>
                  ) : null}
                  <DropdownItem danger onClick={leaveConversation}>
                    Leave conversation
                  </DropdownItem>
                </Dropdown>
              </div>

              <div ref={scrollRef} className="min-h-0 flex-1 space-y-3 overflow-y-auto bg-canvas/40 px-4 py-4">
                {hasMore ? (
                  <div className="flex justify-center pb-1">
                    <Button variant="ghost" size="sm" loading={loadingOlder} onClick={loadOlder}>
                      Load older messages
                    </Button>
                  </div>
                ) : null}
                {loadingMessages ? (
                  <div className="grid place-items-center py-10">
                    <Spinner />
                  </div>
                ) : messages.length === 0 ? (
                  <p className="py-10 text-center text-sm text-ink-muted">
                    No messages yet. Say hello!
                  </p>
                ) : (
                  messages.map((message) => {
                    const mine = message.sender.id === user?.id
                    const receipt = mine && message.status === 'sent' ? receiptLabel(message) : null
                    return (
                      <div key={message.id} className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
                        <div className={cn('flex max-w-[80%] flex-col', mine ? 'items-end' : 'items-start')}>
                          {!mine && currentConversation?.is_group ? (
                            <span className="mb-0.5 px-1 text-[11px] font-semibold text-ink-muted">
                              {message.sender.name}
                            </span>
                          ) : null}
                          <div
                            className={cn(
                              'whitespace-pre-wrap break-words rounded-2xl px-3.5 py-2 text-sm',
                              mine
                                ? 'rounded-br-md bg-brand text-white'
                                : 'rounded-bl-md border border-line bg-surface text-ink',
                            )}
                          >
                            {message.body}
                          </div>
                          <span className="mt-1 flex items-center gap-1.5 px-1 text-[10px] text-ink-muted">
                            {message.source === 'telegram' ? (
                              <span className="rounded-full bg-brand-soft px-1.5 font-semibold text-brand">
                                Telegram
                              </span>
                            ) : null}
                            {formatDateTime(message.created_at)}
                            {mine && message.status === 'sending' ? (
                              <span className="italic">sending…</span>
                            ) : null}
                            {mine && message.status === 'failed' ? (
                              <button
                                type="button"
                                onClick={() => retryMessage(message)}
                                className="font-semibold text-red-600 hover:underline"
                              >
                                Failed — retry
                              </button>
                            ) : null}
                            {receipt ? (
                              <span className={receipt === 'Seen' ? 'font-semibold text-brand' : ''}>
                                {receipt}
                              </span>
                            ) : null}
                          </span>
                        </div>
                      </div>
                    )
                  })
                )}
              </div>

              <form onSubmit={handleSend} className="border-t border-line p-3">
                <div className="flex items-end gap-2">
                  <Textarea
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={handleKeyDown}
                    rows={1}
                    maxLength={4000}
                    placeholder="Type a message… (Enter to send, Shift+Enter for a new line)"
                    className="max-h-32 resize-none"
                  />
                  <Button type="submit" loading={sending} disabled={!draft.trim()} aria-label="Send message">
                    <Send className="size-4" aria-hidden />
                  </Button>
                </div>
              </form>
            </>
          )}
        </Card>
      </div>

      <NewConversationModal
        open={newOpen}
        onClose={() => setNewOpen(false)}
        onCreated={handleCreated}
        canGroup
      />

      <Modal
        open={membersOpen}
        onClose={() => setMembersOpen(false)}
        title="Group members"
        description={currentConversation?.title ?? undefined}
      >
        <ul className="divide-y divide-line">
          {(currentConversation?.participants ?? []).map((participant) => (
            <li key={participant.id} className="flex items-center gap-3 py-2.5">
              <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand">
                {initials(participant.name)}
              </span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold text-ink">
                  {participant.name}
                  {participant.id === user?.id ? (
                    <span className="ml-1 text-xs font-normal text-ink-muted">(you)</span>
                  ) : null}
                </span>
                {participant.username ? (
                  <span className="block truncate text-xs text-ink-muted">@{participant.username}</span>
                ) : null}
              </span>
            </li>
          ))}
        </ul>
      </Modal>
    </div>
  )
}
