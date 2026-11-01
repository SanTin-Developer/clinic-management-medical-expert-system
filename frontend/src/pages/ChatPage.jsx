import { memo, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import {
  ArrowLeft,
  Download,
  Edit3,
  Eye,
  FileText,
  MessagesSquare,
  MoreVertical,
  Paperclip,
  Plus,
  Save,
  Send,
  Trash2,
  UserPlus,
  Users,
  X,
} from 'lucide-react'
import PageHeader from '../components/ui/PageHeader.jsx'
import Card from '../components/ui/Card.jsx'
import Button from '../components/ui/Button.jsx'
import Badge from '../components/ui/Badge.jsx'
import Dropdown, { DropdownItem } from '../components/ui/Dropdown.jsx'
import Modal from '../components/ui/Modal.jsx'
import ConfirmDialog from '../components/ui/ConfirmDialog.jsx'
import Textarea from '../components/ui/Textarea.jsx'
import { Shimmer } from '../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../components/ui/State.jsx'
import NewConversationModal from '../components/communication/NewConversationModal.jsx'
import { cn } from '../utils/cn.js'
import { conversationApi } from '../api/communication.js'
import { getEcho } from '../lib/echo.js'
import { useAuth } from '../hooks/useAuth.js'
import { formatDateTime, formatTime, truncate } from '../utils/format.js'

const BROADCAST_EVENT = '.message.sent'
const READ_EVENT = '.messages.read'

const MAX_IMAGE_BYTES = 10 * 1024 * 1024
const MAX_FILE_BYTES = 20 * 1024 * 1024
const MAX_VIDEO_BYTES = 50 * 1024 * 1024
const RECOMMENDED_VIDEO_SECONDS = 120

const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif']
const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
const VIDEO_EXTS = ['mp4', 'webm', 'mov']
const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime']
const FILE_EXTS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv']

const ATTACH_ACCEPT = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/gif',
  'video/mp4',
  'video/webm',
  'video/quicktime',
  '.pdf',
  '.doc',
  '.docx',
  '.xls',
  '.xlsx',
  '.ppt',
  '.pptx',
  '.txt',
  '.csv',
].join(',')

function initials(name = '') {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

function formatBytes(bytes = 0) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(bytes >= 1024 * 10 ? 0 : 1)} KB`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(bytes >= 1024 * 1024 * 10 ? 0 : 1)} MB`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`
}

function fileExtension(name = '') {
  const parts = name.toLowerCase().split('.')
  return parts.length > 1 ? parts.pop() : ''
}

function detectAttachmentKind(file) {
  const ext = fileExtension(file.name)
  if (VIDEO_MIMES.includes(file.type) || VIDEO_EXTS.includes(ext)) return 'video'
  if (IMAGE_MIMES.includes(file.type) || IMAGE_EXTS.includes(ext)) return 'image'
  if (FILE_EXTS.includes(ext)) return 'file'
  return null
}

function matchesAllowed(file, kind) {
  const ext = fileExtension(file.name)
  if (kind === 'image') return IMAGE_EXTS.includes(ext)
  if (kind === 'video') return VIDEO_EXTS.includes(ext)
  if (kind === 'file') return FILE_EXTS.includes(ext)
  return false
}

function probeVideo(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const video = document.createElement('video')
    video.preload = 'metadata'
    video.muted = true

    const cleanup = () => {
      try {
        URL.revokeObjectURL(url)
      } catch {
        // ignore
      }
    }

    const timeout = setTimeout(() => {
      cleanup()
      video.remove()
      reject(new Error('Video preview timed out. The codec or format may be unsupported.'))
    }, 15000)

    video.onloadedmetadata = () => {
      clearTimeout(timeout)
      if (!video.videoWidth && !video.videoHeight) {
        cleanup()
        video.remove()
        reject(new Error('This video has no visual track or is not playable in your browser.'))
      } else {
        const duration = Number.isFinite(video.duration) ? video.duration : null
        video.remove()
        resolve({ duration, previewUrl: url })
      }
    }
    video.onerror = () => {
      clearTimeout(timeout)
      cleanup()
      video.remove()
      reject(new Error('This video format or codec is not supported by your browser.'))
    }
    video.src = url
  })
}

function formatDuration(seconds) {
  if (seconds == null || !Number.isFinite(seconds)) return null
  const total = Math.floor(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  if (h > 0) return `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
  return `${m}:${String(s).padStart(2, '0')}`
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
    attachment: message.attachment ?? null,
    sender: {
      id: message.sender?.id ?? message.sender_id ?? null,
      name: message.sender?.name ?? 'Unknown',
      username: message.sender?.username ?? null,
    },
    can_edit: message.can_edit ?? false,
    can_delete_everyone: message.can_delete_everyone ?? false,
    can_delete_me: message.can_delete_me ?? false,
    edited_at: message.edited_at ?? null,
  }
}

function conversationPreviewText(conversation) {
  const last = conversation.last_message
  if (!last) return 'No messages yet'
  const sender = last.sender?.name ? `${last.sender.name}: ` : ''
  if (last.body && last.body.trim()) {
    return `${sender}${truncate(last.body, 48)}`
  }
  if (last.attachment?.kind === 'image') return `${sender}Photo`
  if (last.attachment?.kind === 'video') return `${sender}Video`
  if (last.attachment?.kind === 'file') {
    const name = last.attachment.name ? truncate(last.attachment.name, 28) : 'File'
    return `${sender}${name}`
  }
  return `${sender}Attachment`
}

function ConversationRow({ conversation, active, onClick }) {
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
          {conversation.last_message ? (
            <span className="shrink-0 text-[11px] text-ink-muted">{formatTime(conversation.last_message.created_at?.slice(11, 16))}</span>
          ) : null}
        </span>
        <span className="mt-0.5 flex items-center justify-between gap-2">
          <span className="truncate text-xs text-ink-muted">{conversationPreviewText(conversation)}</span>
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

function ConversationSkeleton() {
  return (
    <div className="divide-y divide-line/70" aria-hidden>
      {Array.from({ length: 6 }).map((_, index) => (
        <div key={index} className="flex items-center gap-3 px-4 py-3">
          <Shimmer className="size-10 shrink-0 rounded-full" />
          <div className="min-w-0 flex-1 space-y-2">
            <div className="flex items-center justify-between gap-3">
              <Shimmer className="h-3.5 w-2/3" />
              <Shimmer className="h-2.5 w-10 shrink-0" />
            </div>
            <Shimmer className={index % 2 === 0 ? 'h-3 w-1/2' : 'h-3 w-3/4'} />
          </div>
        </div>
      ))}
    </div>
  )
}

function useEvent(handler) {
  const ref = useRef(handler)
  useEffect(() => {
    ref.current = handler
  }, [handler])
  return useCallback((...args) => ref.current?.(...args), [])
}

const SelectableMessageRow = memo(function SelectableMessageRow({
  message,
  index,
  mine,
  isGroup,
  receipt,
  attachmentSrc,
  selected,
  canSelect,
  isSelectMode,
  isEditing,
  editDraft,
  savingEdit,
  onToggle,
  onEnterSelect,
  onDelete,
  onStartEdit,
  onCancelEdit,
  onSaveEdit,
  onRetry,
  onChangeDraft,
  onOpenLightbox,
}) {
  const pressTimerRef = useRef(null)
  const suppressClickRef = useRef(false)

  const attachment = message.attachment
  const hasBody = message.body && message.body.trim()
  const progress = attachment?.uploadProgress
  const showProgress = mine && message.status === 'sending' && attachment && progress != null

  const clearPressTimer = () => {
    if (pressTimerRef.current) {
      clearTimeout(pressTimerRef.current)
      pressTimerRef.current = null
    }
  }

  return (
    <div
      className={cn(
        'group relative flex w-full items-center gap-2 rounded-lg transition-colors',
        selected && 'bg-[rgba(59,130,246,0.12)]',
        isSelectMode && canSelect ? 'cursor-pointer' : '',
      )}
      onClick={(event) => {
        if (suppressClickRef.current) {
          suppressClickRef.current = false
          return
        }
        if (isSelectMode && canSelect) {
          event.preventDefault()
          event.stopPropagation()
          onToggle(message, index, event.shiftKey)
        }
      }}
      onContextMenu={(event) => {
        if (canSelect && !isSelectMode) {
          event.preventDefault()
          onEnterSelect(message.id, index)
        }
      }}
      onTouchStart={() => {
        if (!canSelect || isSelectMode) return
        suppressClickRef.current = false
        pressTimerRef.current = setTimeout(() => {
          pressTimerRef.current = null
          suppressClickRef.current = true
          onEnterSelect(message.id, index)
        }, 500)
      }}
      onTouchEnd={clearPressTimer}
      onTouchMove={clearPressTimer}
    >
      <div
        className={cn(
          'flex max-w-[85%] flex-col',
          mine ? 'ml-auto items-end' : 'mr-auto items-start',
          isSelectMode && canSelect ? 'pointer-events-none' : '',
        )}
      >
        {!mine && isGroup ? (
          <span className="mb-0.5 px-1 text-[11px] font-semibold text-ink-muted">{message.sender.name}</span>
        ) : null}
        <div className={cn('flex items-start gap-1', mine ? 'flex-row-reverse' : 'flex-row')}>
          <div
            className={cn(
              'space-y-2 break-words rounded-2xl text-sm',
              mine ? 'rounded-br-md bg-brand text-white' : 'rounded-bl-md border border-line bg-surface text-ink',
              attachment && !hasBody ? 'overflow-hidden px-1.5 py-1.5' : 'whitespace-pre-wrap px-3.5 py-2',
            )}
          >
            {attachment ? (
              <div className="space-y-2">
                {attachment.kind === 'image' ? (
                  <button type="button" onClick={() => onOpenLightbox({ src: attachmentSrc, name: attachment.name })} className="block overflow-hidden rounded-xl text-left">
                    <img src={attachmentSrc} alt={attachment.name || 'Image attachment'} className="max-h-72 w-full max-w-sm object-contain bg-black/5" />
                  </button>
                ) : null}
                {attachment.kind === 'video' ? (
                  <div className="overflow-hidden rounded-xl bg-black">
                    <video controls playsInline preload="metadata" src={attachmentSrc} className="max-h-72 w-full max-w-sm" />
                    <div className={cn('flex items-center justify-between gap-2 px-2 py-1 text-[11px]', mine ? 'text-white/90' : 'text-ink-muted')}>
                      <span className="truncate">{attachment.name}</span>
                      <a href={attachmentSrc} download={attachment.name} className={cn('inline-flex items-center gap-1', mine ? 'text-white' : 'text-brand')}>
                        <Download className="size-3.5" aria-hidden /> Download
                      </a>
                    </div>
                  </div>
                ) : null}
                {attachment.kind === 'file' ? (
                  <a href={attachmentSrc} download={attachment.name} className={cn('flex items-center gap-3 rounded-xl px-2.5 py-2', mine ? 'bg-white/10 hover:bg-white/20' : 'bg-canvas hover:bg-brand-faint')}>
                    <span className={cn('grid size-9 place-items-center rounded-lg', mine ? 'bg-white/15' : 'bg-brand-soft text-brand')}>
                      <FileText className="size-5" aria-hidden />
                    </span>
                    <span className="min-w-0 flex-1">
                      <span className={cn('block truncate text-sm font-semibold', mine ? 'text-white' : 'text-ink')}>{attachment.name || 'File'}</span>
                      <span className={cn('block text-xs', mine ? 'text-white/80' : 'text-ink-muted')}>{formatBytes(attachment.size || 0)}</span>
                    </span>
                    <Download className={cn('size-4', mine ? 'text-white' : 'text-ink-muted')} aria-hidden />
                  </a>
                ) : null}
                {showProgress ? (
                  <div className="space-y-1 px-1 pb-1">
                    <div className={cn('h-1.5 w-full overflow-hidden rounded-full', mine ? 'bg-white/30' : 'bg-line')}>
                      <div className={cn('h-full', mine ? 'bg-white' : 'bg-brand')} style={{ width: `${progress}%` }} />
                    </div>
                    <span className={cn('text-[10px]', mine ? 'text-white/80' : 'text-ink-muted')}>Uploading {progress}%</span>
                  </div>
                ) : null}
              </div>
            ) : null}
            {isEditing ? (
              <div className="space-y-2 px-1 py-1">
                <textarea value={editDraft} onChange={(event) => onChangeDraft(event.target.value)} className={cn('w-full rounded-lg p-2 text-sm', mine ? 'bg-white/10 text-white' : 'bg-canvas text-ink')} rows={2} />
                <div className="flex items-center gap-2">
                  <Button size="sm" onClick={onSaveEdit} loading={savingEdit}><Save className="size-3.5" aria-hidden /> Save</Button>
                  <Button size="sm" variant="ghost" onClick={onCancelEdit}>Cancel</Button>
                </div>
              </div>
            ) : hasBody ? (
              <div className={cn(attachment ? 'px-1.5 pb-1' : '')}>
                {message.body}
                {message.edited_at ? <span className="ml-1 text-[10px] opacity-70">(edited)</span> : null}
              </div>
            ) : null}
          </div>
          <div className={cn('flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100', mine ? 'flex-row-reverse' : 'flex-row')}>
            {!isSelectMode ? (
              <Dropdown trigger={<button type="button" className="grid size-7 place-items-center rounded-lg text-ink-muted hover:bg-canvas"><MoreVertical className="size-4" aria-hidden /></button>}>
                {attachment?.kind === 'image' && attachmentSrc ? <DropdownItem onClick={() => onOpenLightbox({ src: attachmentSrc, name: attachment.name })}><Eye className="size-4" aria-hidden /> View</DropdownItem> : null}
                {(attachment?.kind === 'image' || attachment?.kind === 'file' || attachment?.kind === 'video') && attachmentSrc ? <DropdownItem onClick={() => { const a = document.createElement('a'); a.href = attachmentSrc; a.download = attachment.name || 'download'; a.click(); }}><Download className="size-4" aria-hidden /> Save/Download</DropdownItem> : null}
                {message.can_edit ? <DropdownItem onClick={() => onStartEdit(message)}><Edit3 className="size-4" aria-hidden /> Edit</DropdownItem> : null}
                <DropdownItem onClick={(event) => { event.preventDefault(); onEnterSelect(message.id, index); }}><Trash2 className="size-4" aria-hidden /> Select</DropdownItem>
                {message.can_delete_everyone ? <DropdownItem danger onClick={() => onDelete(message, 'everyone')}><Trash2 className="size-4" aria-hidden /> Delete for everyone</DropdownItem> : null}
                {message.can_delete_me ? <DropdownItem danger onClick={() => onDelete(message, 'me')}><Trash2 className="size-4" aria-hidden /> Delete for me</DropdownItem> : null}
              </Dropdown>
            ) : null}
          </div>
        </div>
        <span className="mt-1 flex items-center gap-1.5 px-1 text-[10px] text-ink-muted">
          {message.source === 'telegram' ? <span className="rounded-full bg-brand-soft px-1.5 font-semibold text-brand">Telegram</span> : null}
          {formatDateTime(message.created_at)}
          {mine && message.status === 'sending' ? <span className="italic">sending…</span> : null}
          {mine && message.status === 'failed' ? (
            <button type="button" onClick={() => onRetry(message)} className="font-semibold text-red-600 hover:underline">Failed — retry</button>
          ) : null}
          {receipt ? <span className={receipt === 'Seen' ? 'font-semibold text-brand' : ''}>{receipt}</span> : null}
        </span>
      </div>
      <div
        className={cn(
          'flex w-8 shrink-0 items-center justify-center transition-all duration-150',
          isSelectMode ? 'translate-x-0 opacity-100' : 'pointer-events-none translate-x-1 opacity-0',
        )}
      >
        {canSelect ? (
          <button
            type="button"
            onClick={(event) => {
              event.stopPropagation()
              onToggle(message, index, event.shiftKey)
            }}
            role="checkbox"
            aria-checked={selected}
            aria-label={selected ? 'Deselect message' : 'Select message'}
            tabIndex={isSelectMode ? 0 : -1}
            className="grid size-6 place-items-center rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3b82f6] focus-visible:ring-offset-1"
          >
            <span
              className={cn(
                'grid size-5 place-items-center rounded-full transition-transform duration-150',
                selected
                  ? 'scale-100 bg-[#3b82f6]'
                  : 'scale-90 border-2 border-ink-muted/60 bg-transparent',
              )}
            >
              {selected ? (
                <svg width="12" height="10" viewBox="0 0 12 10" fill="none" aria-hidden>
                  <path d="M1 5L4 8L11 1" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
              ) : null}
            </span>
          </button>
        ) : null}
      </div>
    </div>
  )
})

function MessageSkeleton() {
  return (
    <div className="space-y-4" aria-hidden>
      {[0, 1, 2, 3, 4].map((index) => {
        const mine = index % 2 === 1
        return (
          <div key={index} className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
            <div className={cn('flex max-w-[78%] flex-col gap-1.5', mine ? 'items-end' : 'items-start')}>
              <Shimmer className={cn('rounded-2xl', mine ? 'h-10 w-48' : 'h-14 w-64')} />
              <Shimmer className="h-2.5 w-12" />
            </div>
          </div>
        )
      })}
    </div>
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
  const [pendingAttachment, setPendingAttachment] = useState(null)
  const [attachmentChecking, setAttachmentChecking] = useState(false)
  const fileInputRef = useRef(null)
  const scrollRef = useRef(null)
  const [lightbox, setLightbox] = useState(null)
  const [editingId, setEditingId] = useState(null)
  const [editDraft, setEditDraft] = useState('')
  const [savingEdit, setSavingEdit] = useState(false)
  const [selectMode, setSelectMode] = useState(false)
  const [selectedIds, setSelectedIds] = useState(new Set())
  const [lastSelectedIndex, setLastSelectedIndex] = useState(null)
  const [pendingDelete, setPendingDelete] = useState(null)
  const [deletingDelete, setDeletingDelete] = useState(false)
  const selectionTouchedRef = useRef(false)

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
    setPendingAttachment(null)
    setAttachmentChecking(false)
    if (fileInputRef.current) fileInputRef.current.value = ''
    setEditingId(null)
    setEditDraft('')

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
      // Broadcasts are serialized in the queue worker, which has no
      // authenticated user, so derive per-user flags from the sender id.
      if (user?.id != null) {
        const isOwn = Number(message.sender.id) === Number(user.id)
        message.can_edit = isOwn && !message.attachment
        message.can_delete_everyone = isOwn
        message.can_delete_me = true
      }
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
  }, [activeId, queryClient, user?.id])

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

  const revokePendingPreview = (attachment) => {
    if (attachment?.previewUrl && attachment.previewUrl.startsWith('blob:')) {
      try {
        URL.revokeObjectURL(attachment.previewUrl)
      } catch {
        // ignore
      }
    }
  }

  const clearPendingAttachment = () => {
    revokePendingPreview(pendingAttachment)
    setPendingAttachment(null)
    setAttachmentChecking(false)
    if (fileInputRef.current) fileInputRef.current.value = ''
  }

  const handleFileSelect = async (event) => {
    const file = event.target.files?.[0]
    if (!file || !activeId) return

    const kind = detectAttachmentKind(file)
    if (!kind || !matchesAllowed(file, kind)) {
      toast.error('Unsupported file type. Allowed: images (JPG, PNG, WebP, GIF), videos (MP4, WebM, MOV) or documents (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV).')
      if (fileInputRef.current) fileInputRef.current.value = ''
      return
    }

    if (kind === 'image' && file.size > MAX_IMAGE_BYTES) {
      toast.error('Images must be 10 MB or smaller.')
      if (fileInputRef.current) fileInputRef.current.value = ''
      return
    }
    if (kind === 'file' && file.size > MAX_FILE_BYTES) {
      toast.error('Files must be 20 MB or smaller.')
      if (fileInputRef.current) fileInputRef.current.value = ''
      return
    }
    if (kind === 'video' && file.size > MAX_VIDEO_BYTES) {
      toast.error('Videos must be 50 MB or smaller.')
      if (fileInputRef.current) fileInputRef.current.value = ''
      return
    }

    revokePendingPreview(pendingAttachment)
    setPendingAttachment(null)
    setAttachmentChecking(true)

    try {
      if (kind === 'video') {
        const probed = await probeVideo(file)
        const duration = probed.duration
        const warning =
          duration != null && duration > RECOMMENDED_VIDEO_SECONDS
            ? `Duration ${formatDuration(duration)} — longer than the 2 minute recommendation.`
            : duration != null
              ? `Duration ${formatDuration(duration)} • up to 2 min recommended.`
              : 'Format validated by your browser. Maximum 50 MB allowed.'
        setPendingAttachment({
          file,
          kind,
          name: file.name,
          size: file.size,
          mimeType: file.type,
          previewUrl: probed.previewUrl,
          duration,
          warning,
          uploadProgress: 0,
        })
      } else if (kind === 'image') {
        const previewUrl = URL.createObjectURL(file)
        setPendingAttachment({
          file,
          kind,
          name: file.name,
          size: file.size,
          mimeType: file.type,
          previewUrl,
          warning: null,
          uploadProgress: 0,
        })
      } else {
        setPendingAttachment({
          file,
          kind,
          name: file.name,
          size: file.size,
          mimeType: file.type,
          previewUrl: null,
          warning: null,
          uploadProgress: 0,
        })
      }
    } catch (err) {
      toast.error(err.message)
      if (fileInputRef.current) fileInputRef.current.value = ''
    } finally {
      setAttachmentChecking(false)
    }
  }

  const sendBody = async (body, clientId, attachment = null) => {
    try {
      const saved = normalizeMessage(await conversationApi.send(activeId, body, attachment?.file, attachment?.onProgress))
      setMessages((prev) => prev.map((m) => (m.client_id === clientId ? { ...saved, client_id: clientId } : m)))
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
    } catch (err) {
      setMessages((prev) => prev.map((m) => (m.client_id === clientId ? { ...m, status: 'failed' } : m)))
      toast.error(err.message)
    }
  }

  const handleSend = async (event) => {
    event?.preventDefault()
    const body = draft.trim()
    if ((!body && !pendingAttachment) || !activeId || sending) return

    const clientId = `local-${Date.now()}-${Math.random().toString(36).slice(2)}`
    const optimisticAttachment = pendingAttachment
      ? {
          kind: pendingAttachment.kind,
          name: pendingAttachment.name,
          mime_type: pendingAttachment.mimeType,
          size: pendingAttachment.size,
          url: pendingAttachment.previewUrl,
          isLocal: true,
          uploadProgress: 0,
        }
      : null

    const optimistic = {
      id: clientId,
      client_id: clientId,
      status: 'sending',
      conversation_id: activeId,
      body,
      source: 'web',
      created_at: new Date().toISOString(),
      attachment: optimisticAttachment,
      sender: { id: user?.id ?? null, name: user?.name ?? 'You', username: user?.username ?? null },
      can_edit: false,
      can_delete_everyone: false,
      can_delete_me: true,
      edited_at: null,
    }

    setMessages((prev) => [...prev, optimistic])
    setDraft('')
    const attachmentToSend = pendingAttachment
    setPendingAttachment(null)
    if (fileInputRef.current) fileInputRef.current.value = ''
    setSending(true)
    await sendBody(
      body,
      clientId,
      attachmentToSend
        ? {
            file: attachmentToSend.file,
            onProgress: (progressEvent) => {
              const total = progressEvent.total || attachmentToSend.size || 1
              const percent = Math.min(100, Math.round((progressEvent.loaded / total) * 100))
              setMessages((prev) =>
                prev.map((m) =>
                  m.client_id === clientId
                    ? { ...m, attachment: m.attachment ? { ...m.attachment, uploadProgress: percent } : m.attachment }
                    : m,
                ),
              )
            },
          }
        : null,
    )
    setSending(false)
  }

  const retryMessage = async (message) => {
    if (!message.client_id || !activeId) return
    if (message.attachment?.isLocal && message.attachment?.file) {
      setMessages((prev) =>
        prev.map((m) => (m.client_id === message.client_id ? { ...m, status: 'sending', attachment: { ...m.attachment, uploadProgress: 0 } } : m)),
      )
      await sendBody(
        message.body || '',
        message.client_id,
        {
          file: message.attachment.file,
          onProgress: (progressEvent) => {
            const total = progressEvent.total || message.attachment.size || 1
            const percent = Math.min(100, Math.round((progressEvent.loaded / total) * 100))
            setMessages((prev) =>
              prev.map((m) =>
                m.client_id === message.client_id
                  ? { ...m, attachment: m.attachment ? { ...m.attachment, uploadProgress: percent } : m.attachment }
                  : m,
              ),
            )
          },
        },
      )
      return
    }
    setMessages((prev) =>
      prev.map((m) => (m.client_id === message.client_id ? { ...m, status: 'sending' } : m)),
    )
    await sendBody(message.body || '', message.client_id)
  }

  const startEdit = (message) => {
    if (!message.can_edit) return
    setEditingId(message.id)
    setEditDraft(message.body || '')
  }

  const cancelEdit = () => {
    setEditingId(null)
    setEditDraft('')
  }

  const saveEdit = async () => {
    if (!editingId || !activeId || savingEdit) return
    const body = editDraft.trim()
    if (!body) {
      toast.error('Message cannot be empty.')
      return
    }
    setSavingEdit(true)
    try {
      const res = await conversationApi.updateMessage(activeId, editingId, body)
      const saved = normalizeMessage(res.data)
      setMessages((prev) => prev.map((m) => (m.id === editingId ? saved : m)))
      cancelEdit()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingEdit(false)
    }
  }

  const requestDeleteMessage = (message, scope) => {
    if (!message?.id) return
    setPendingDelete({ mode: 'single', scope, ids: [message.id] })
  }

  const enterSelectMode = (messageId, index = null) => {
    setSelectMode(true)
    setSelectedIds(new Set(messageId ? [messageId] : []))
    setLastSelectedIndex(index)
    selectionTouchedRef.current = Boolean(messageId)
  }

  const toggleSelect = (message, index, shiftKey = false) => {
    if (!message.id || String(message.id).startsWith('local-')) return
    selectionTouchedRef.current = true
    setSelectedIds((prev) => {
      const next = new Set(prev)
      if (shiftKey && lastSelectedIndex !== null && index !== undefined) {
        const start = Math.min(lastSelectedIndex, index)
        const end = Math.max(lastSelectedIndex, index)
        for (let i = start; i <= end; i += 1) {
          const m = messages[i]
          if (m?.id && !String(m.id).startsWith('local-')) {
            next.add(m.id)
          }
        }
        return next
      }
      if (next.has(message.id)) {
        next.delete(message.id)
      } else {
        next.add(message.id)
      }
      return next
    })
    setLastSelectedIndex(index ?? null)
  }

  const exitSelectMode = () => {
    setSelectMode(false)
    setSelectedIds(new Set())
    setLastSelectedIndex(null)
    selectionTouchedRef.current = false
  }

  const requestBulkDelete = (scope) => {
    if (!activeId || selectedIds.size === 0) return
    const ids = Array.from(selectedIds)
    if (scope === 'everyone') {
      const allMine = ids.every((id) => {
        const msg = messages.find((m) => m.id === id)
        return msg?.sender?.id === user?.id
      })
      if (!allMine) {
        toast.error('You can only delete everyone if all selected messages were sent by you.')
        return
      }
    }
    setPendingDelete({ mode: 'bulk', scope, ids })
  }

  const confirmDelete = async () => {
    if (!activeId || !pendingDelete || deletingDelete) return
    const { ids, scope, mode } = pendingDelete
    setDeletingDelete(true)
    try {
      if (mode === 'single') {
        await conversationApi.deleteMessage(activeId, ids[0], scope)
      } else {
        await conversationApi.bulkDeleteMessages(activeId, ids, scope)
      }
      setMessages((prev) => prev.filter((m) => !ids.includes(m.id)))
      setPendingDelete(null)
      if (selectMode) exitSelectMode()
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
    } catch (err) {
      toast.error(err.message)
    } finally {
      setDeletingDelete(false)
    }
  }

  useEffect(() => {
    if (!selectMode || pendingDelete) return undefined
    const onKey = (e) => {
      if (e.key === 'Escape') {
        exitSelectMode()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [selectMode, pendingDelete])

  useEffect(() => {
    if (selectMode && selectionTouchedRef.current && selectedIds.size === 0) {
      exitSelectMode()
    }
  }, [selectMode, selectedIds])

  const getAttachmentSrc = (attachment) => {
    if (!attachment?.url) return null
    if (attachment.url.startsWith('http')) return attachment.url
    const base = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(/\/api\/v1\/?$/, '')
    return `${base}${attachment.url}`
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

  const allSelectedMine = useMemo(() => {
    if (selectedIds.size === 0) return false
    return Array.from(selectedIds).every((id) => {
      const message = messages.find((m) => m.id === id)
      return message?.sender?.id === user?.id
    })
  }, [selectedIds, messages, user?.id])

  const toggleSelectEvent = useEvent(toggleSelect)
  const enterSelectModeEvent = useEvent(enterSelectMode)
  const requestDeleteMessageEvent = useEvent(requestDeleteMessage)
  const retryMessageEvent = useEvent(retryMessage)
  const startEditEvent = useEvent(startEdit)
  const saveEditEvent = useEvent(saveEdit)
  const cancelEditEvent = useEvent(cancelEdit)

  return (
    <div className="flex h-[calc(100dvh-12.5rem)] flex-col md:h-[calc(100dvh-9rem)]">
      <PageHeader
        title="Team Chat"
        description="Secure direct and group messaging for clinic staff."
        breadcrumb={[{ label: 'Team Chat' }]}
        actions={can('chat.create') ? <Button onClick={() => setNewOpen(true)}><Plus className="size-4" aria-hidden /> New conversation</Button> : null}
      />

      <div className="grid min-h-0 flex-1 grid-cols-1 grid-rows-[minmax(0,1fr)] gap-4 md:grid-cols-[320px_1fr]">
        <Card bodyClassName="flex min-h-0 flex-1 flex-col p-0" className={cn('min-h-0 overflow-hidden', activeId ? 'hidden md:flex md:flex-col' : 'flex flex-col')}>
          <div className="border-b border-line px-4 py-3">
            <h2 className="text-sm font-semibold text-ink">Conversations</h2>
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto">
            {conversations.isLoading ? (
              <ConversationSkeleton />
            ) : conversations.isError ? (
              <ErrorState compact message={conversations.error.message} onRetry={() => conversations.refetch()} />
            ) : items.length === 0 ? (
              <EmptyState icon={MessagesSquare} title="No conversations" message="Start a direct or group chat with your colleagues." />
            ) : (
              items.map((conversation) => <ConversationRow key={conversation.id} conversation={conversation} active={conversation.id === activeId} onClick={() => navigate(`/chat/${conversation.id}`)} />)
            )}
          </div>
        </Card>

        <Card bodyClassName="flex min-h-0 flex-1 flex-col p-0" className={cn('min-h-0 overflow-hidden', activeId ? 'flex flex-col' : 'hidden md:flex md:flex-col')}>
          {!activeId ? (
            <div className="grid flex-1 place-items-center">
              <EmptyState icon={MessagesSquare} title="Select a conversation" message="Choose a conversation from the list or start a new one to begin messaging." />
            </div>
          ) : (
            <>
              <div className="flex items-center gap-3 border-b border-line px-4 py-3">
                {selectMode ? (
                  <div role="toolbar" aria-label="Message selection actions" className="flex w-full flex-wrap items-center gap-2 rounded-xl border border-brand/25 bg-gradient-to-r from-brand/10 via-surface to-rose-500/10 p-1.5 shadow-sm">
                    <button
                      type="button"
                      onClick={exitSelectMode}
                      aria-label="Cancel selection"
                      className="group inline-flex items-center gap-1.5 rounded-lg px-1.5 py-1 text-xs font-semibold text-ink-muted transition-colors hover:bg-surface/80 hover:text-ink"
                    >
                      <span className="grid size-7 place-items-center rounded-full bg-surface text-ink-muted ring-1 ring-line transition group-hover:ring-brand/40">
                        <X className="size-4" aria-hidden />
                      </span>
                      <span className="hidden sm:inline">Cancel</span>
                    </button>

                    <span className="hidden h-6 w-px bg-line sm:block" aria-hidden />

                    <div className="flex items-center gap-2">
                      <span className="grid size-8 place-items-center rounded-full bg-brand text-white shadow-[0_0_0_4px_rgba(59,130,246,0.15)]">
                        <svg width="13" height="11" viewBox="0 0 12 10" fill="none" aria-hidden>
                          <path d="M1 5L4 8L11 1" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                        </svg>
                      </span>
                      <div className="leading-tight">
                        <p aria-live="polite" className="text-sm font-bold text-ink">{selectedIds.size} selected</p>
                        <p className="hidden text-[10px] font-medium text-ink-muted sm:block">Tap a message to toggle</p>
                      </div>
                    </div>

                    <div className="ml-auto flex flex-wrap items-center justify-end gap-1.5">
                      <Button
                        size="sm"
                        variant="outline"
                        className="gap-1.5 rounded-lg bg-surface/80"
                        disabled={selectedIds.size === 0}
                        onClick={() => requestBulkDelete('me')}
                      >
                        <Trash2 className="size-3.5" aria-hidden />
                        <span className="hidden sm:inline">Delete for me</span>
                        <span className="sm:hidden">For me</span>
                      </Button>
                      <span
                        className={cn('inline-flex', !allSelectedMine && 'cursor-not-allowed')}
                        title={allSelectedMine ? undefined : 'You can only delete for everyone when every selected message was sent by you.'}
                      >
                        <Button
                          size="sm"
                          variant="danger"
                          className="gap-1.5 rounded-lg bg-gradient-to-r from-red-500 to-rose-500 shadow-sm shadow-red-500/25 hover:from-red-600 hover:to-rose-600"
                          disabled={!allSelectedMine}
                          onClick={() => requestBulkDelete('everyone')}
                        >
                          <Trash2 className="size-3.5" aria-hidden />
                          <span className="hidden sm:inline">Delete for everyone</span>
                          <span className="sm:hidden">Everyone</span>
                        </Button>
                      </span>
                    </div>
                  </div>
                ) : (
                  <>
                    <button type="button" onClick={() => navigate('/chat')} aria-label="Back to conversations" className="grid size-9 place-items-center rounded-lg text-ink-muted hover:bg-canvas md:hidden">
                      <ArrowLeft className="size-5" aria-hidden />
                    </button>
                    <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand">
                      {currentConversation?.is_group ? <Users className="size-4" aria-hidden /> : initials(currentConversation?.title)}
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-semibold text-ink">{currentConversation?.title ?? 'Conversation'}</p>
                      {currentConversation?.is_group ? (
                        <button type="button" onClick={() => setMembersOpen(true)} className="truncate text-xs text-ink-muted hover:text-brand hover:underline">
                          {(currentConversation.participants ?? []).length} participants
                        </button>
                      ) : null}
                    </div>
                    {currentConversation?.is_group ? <Badge tone="brand">Group</Badge> : null}
                    <Dropdown trigger={<button type="button" aria-label="Conversation options" className="grid size-9 place-items-center rounded-lg text-ink-muted hover:bg-canvas"><span className="text-lg leading-none">⋯</span></button>}>
                      <DropdownItem onClick={() => enterSelectMode(null)}><Trash2 className="size-4" aria-hidden /> Select messages</DropdownItem>
                      {currentConversation?.is_group ? <DropdownItem onClick={() => setMembersOpen(true)}><Users className="size-4" aria-hidden /> View members</DropdownItem> : null}
                      {currentConversation?.is_group && canManageActive ? <DropdownItem onClick={() => setNewOpen(true)}><UserPlus className="size-4" aria-hidden /> New group</DropdownItem> : null}
                      <DropdownItem danger onClick={leaveConversation}>Leave conversation</DropdownItem>
                    </Dropdown>
                  </>
                )}
              </div>

              <div ref={scrollRef} className="min-h-0 flex-1 space-y-3 overflow-y-auto bg-canvas/40 px-4 py-4">
                {hasMore ? (
                  <div className="flex justify-center pb-1">
                    <Button variant="ghost" size="sm" loading={loadingOlder} onClick={loadOlder}>Load older messages</Button>
                  </div>
                ) : null}
                {loadingMessages ? (
                  <MessageSkeleton />
                ) : messages.length === 0 ? (
                  <p className="py-10 text-center text-sm text-ink-muted">No messages yet. Say hello!</p>
                ) : (
                  messages.map((message, index) => {
                    const canSelect = Boolean(message.id) && !String(message.id).startsWith('local-')
                    const mine = message.sender.id === user?.id
                    return (
                      <SelectableMessageRow
                        key={message.id}
                        message={message}
                        index={index}
                        mine={mine}
                        isGroup={Boolean(currentConversation?.is_group)}
                        receipt={mine && message.status === 'sent' ? receiptLabel(message) : null}
                        attachmentSrc={getAttachmentSrc(message.attachment)}
                        selected={canSelect && selectedIds.has(message.id)}
                        canSelect={canSelect}
                        isSelectMode={selectMode}
                        isEditing={editingId === message.id}
                        editDraft={editingId === message.id ? editDraft : ''}
                        savingEdit={savingEdit}
                        onToggle={toggleSelectEvent}
                        onEnterSelect={enterSelectModeEvent}
                        onDelete={requestDeleteMessageEvent}
                        onStartEdit={startEditEvent}
                        onCancelEdit={cancelEditEvent}
                        onSaveEdit={saveEditEvent}
                        onRetry={retryMessageEvent}
                        onChangeDraft={setEditDraft}
                        onOpenLightbox={setLightbox}
                      />
                    )
                  })
                )}
              </div>

              <form onSubmit={handleSend} className="border-t border-line p-3">
                {pendingAttachment ? (
                  <div className="mb-2 flex items-start gap-3 rounded-xl border border-line bg-canvas/60 p-2">
                    <div className="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg bg-surface">
                      {pendingAttachment.kind === 'image' ? (
                        <img src={pendingAttachment.previewUrl} alt={pendingAttachment.name} className="size-12 object-cover" />
                      ) : pendingAttachment.kind === 'video' ? (
                        <video src={pendingAttachment.previewUrl} muted className="size-12 object-cover" />
                      ) : (
                        <FileText className="size-5 text-ink-muted" aria-hidden />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-start justify-between gap-2">
                        <div className="min-w-0">
                          <p className="truncate text-sm font-semibold text-ink">{pendingAttachment.name}</p>
                          <p className="text-xs text-ink-muted">
                            {formatBytes(pendingAttachment.size)}
                            {pendingAttachment.kind === 'video' && pendingAttachment.duration != null ? ` • ${formatDuration(pendingAttachment.duration)}` : ''}
                          </p>
                        </div>
                        <button type="button" onClick={clearPendingAttachment} className="grid size-8 place-items-center rounded-lg text-ink-muted hover:bg-canvas hover:text-ink" aria-label="Remove attachment">
                          <X className="size-4" aria-hidden />
                        </button>
                      </div>
                      {pendingAttachment.warning ? <p className="mt-1 text-xs text-amber-600 dark:text-amber-400">{pendingAttachment.warning}</p> : null}
                      <p className="mt-1 text-[11px] text-ink-muted">Images up to 10 MB • Files up to 20 MB • Videos up to 50 MB • MP4, WebM, MOV • 2 min recommended</p>
                    </div>
                  </div>
                ) : null}
                <div className="flex items-end gap-2">
                  <input ref={fileInputRef} type="file" className="hidden" accept={ATTACH_ACCEPT} onChange={handleFileSelect} />
                  <Button type="button" variant="outline" size="md" className="px-2.5" aria-label="Attach file" title="Attach image, video or file" disabled={sending || attachmentChecking || !activeId} onClick={() => fileInputRef.current?.click()}>
                    <Paperclip className="size-4" aria-hidden />
                  </Button>
                  <Textarea value={draft} onChange={(e) => setDraft(e.target.value)} onKeyDown={handleKeyDown} rows={1} maxLength={4000} placeholder={pendingAttachment ? 'Add a caption… (optional)' : 'Type a message… (Enter to send, Shift+Enter for a new line)'} className="max-h-32 resize-none" />
                  <Button type="submit" loading={sending} disabled={(!draft.trim() && !pendingAttachment) || attachmentChecking} aria-label="Send message">
                    <Send className="size-4" aria-hidden />
                  </Button>
                </div>
              </form>
            </>
          )}
        </Card>
      </div>

      <NewConversationModal open={newOpen} onClose={() => setNewOpen(false)} onCreated={handleCreated} canGroup />
      <Modal open={membersOpen} onClose={() => setMembersOpen(false)} title="Group members" description={currentConversation?.title ?? undefined}>
        <ul className="divide-y divide-line">
          {(currentConversation?.participants ?? []).map((participant) => (
            <li key={participant.id} className="flex items-center gap-3 py-2.5">
              <span className="grid size-9 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand">{initials(participant.name)}</span>
              <span className="min-w-0 flex-1">
                <span className="block truncate text-sm font-semibold text-ink">
                  {participant.name}
                  {participant.id === user?.id ? <span className="ml-1 text-xs font-normal text-ink-muted">(you)</span> : null}
                </span>
                {participant.username ? <span className="block truncate text-xs text-ink-muted">@{participant.username}</span> : null}
              </span>
            </li>
          ))}
        </ul>
      </Modal>
      <ConfirmDialog
        open={Boolean(pendingDelete)}
        onClose={() => {
          if (!deletingDelete) setPendingDelete(null)
        }}
        onConfirm={confirmDelete}
        title={pendingDelete?.mode === 'bulk'
          ? `Delete ${pendingDelete.ids.length} message${pendingDelete.ids.length === 1 ? '' : 's'}?`
          : 'Delete this message?'}
        confirmLabel={pendingDelete?.scope === 'everyone' ? 'Delete for everyone' : 'Delete for me'}
        loading={deletingDelete}
        danger
      >
        <div className="space-y-3">
          <div className="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-500/25 dark:bg-red-500/10">
            <span className="grid size-9 shrink-0 place-items-center rounded-full bg-red-500/15 text-red-600 dark:text-red-400">
              <Trash2 className="size-4" aria-hidden />
            </span>
            <div className="min-w-0">
              <p className="text-sm font-semibold text-ink">
                {pendingDelete?.ids.length === 1
                  ? 'This message will be deleted'
                  : `${pendingDelete?.ids.length ?? 0} messages will be deleted`}
              </p>
              <p className="mt-1 text-xs leading-relaxed text-ink-muted">
                {pendingDelete?.scope === 'everyone'
                  ? 'This removes the copy for every participant and attempts to delete the Telegram copy. This cannot be undone.'
                  : 'This hides the copy for you only. Other participants will still see their copy.'}
              </p>
            </div>
          </div>
          {pendingDelete?.mode === 'bulk' ? (
            <p className="text-xs text-ink-muted">
              Tip: press <kbd className="rounded border border-line bg-canvas px-1 py-0.5 font-mono text-[10px] text-ink">Esc</kbd> to cancel without deleting.
            </p>
          ) : null}
        </div>
      </ConfirmDialog>
      {lightbox ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" onClick={() => setLightbox(null)}>
          <button type="button" className="absolute right-4 top-4 text-white" onClick={() => setLightbox(null)}><X className="size-6" aria-hidden /></button>
          <img src={lightbox.src} alt={lightbox.name || 'Image'} className="max-h-full max-w-full object-contain" onClick={(e) => e.stopPropagation()} />
        </div>
      ) : null}
    </div>
  )
}
