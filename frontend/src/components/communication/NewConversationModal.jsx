import { useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Search, Users } from 'lucide-react'
import Modal from '../ui/Modal.jsx'
import Button from '../ui/Button.jsx'
import Input from '../ui/Input.jsx'
import Field from '../ui/Field.jsx'
import { cn } from '../../utils/cn.js'
import { conversationApi } from '../../api/communication.js'

function initials(name = '') {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

export default function NewConversationModal({ open, onClose, onCreated, canGroup }) {
  const queryClient = useQueryClient()
  const [mode, setMode] = useState('direct')
  const [search, setSearch] = useState('')
  const [title, setTitle] = useState('')
  const [selected, setSelected] = useState([])
  const [saving, setSaving] = useState(false)

  const recipients = useQuery({
    queryKey: ['conversations', 'recipients'],
    queryFn: conversationApi.recipients,
    enabled: open,
  })

  useEffect(() => {
    if (open) {
      setMode('direct')
      setSearch('')
      setTitle('')
      setSelected([])
    }
  }, [open])

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase()
    const people = recipients.data ?? []
    if (!term) return people
    return people.filter(
      (p) =>
        p.name?.toLowerCase().includes(term) ||
        p.username?.toLowerCase().includes(term) ||
        p.role?.toLowerCase().includes(term),
    )
  }, [recipients.data, search])

  const toggle = (id) => {
    if (mode === 'direct') {
      setSelected([id])
      return
    }
    setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
  }

  const submit = async () => {
    if (mode === 'direct') {
      if (selected.length !== 1) {
        toast.error('Select one staff member to message.')
        return
      }
    } else if (!title.trim()) {
      toast.error('Give the group a name.')
      return
    } else if (selected.length < 1) {
      toast.error('Select at least one participant.')
      return
    }

    setSaving(true)
    try {
      const conversation =
        mode === 'direct'
          ? await conversationApi.create({ type: 'private', user_id: selected[0] })
          : await conversationApi.create({ type: 'group', title: title.trim(), participant_ids: selected })

      queryClient.invalidateQueries({ queryKey: ['conversations'] })
      toast.success(mode === 'direct' ? 'Conversation ready.' : 'Group created.')
      onCreated?.(conversation)
      onClose?.()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New conversation"
      description="Message a colleague directly or start a group."
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            Cancel
          </Button>
          <Button onClick={submit} loading={saving}>
            {mode === 'direct' ? 'Start chat' : 'Create group'}
          </Button>
        </>
      }
    >
      <div className="mb-4 flex gap-1 rounded-lg border border-line p-1">
        <button
          type="button"
          onClick={() => {
            setMode('direct')
            setSelected([])
          }}
          className={cn(
            'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
            mode === 'direct' ? 'bg-brand text-white' : 'text-ink-muted hover:text-ink',
          )}
        >
          Direct message
        </button>
        {canGroup ? (
          <button
            type="button"
            onClick={() => {
              setMode('group')
              setSelected([])
            }}
            className={cn(
              'flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
              mode === 'group' ? 'bg-brand text-white' : 'text-ink-muted hover:text-ink',
            )}
          >
            Group
          </button>
        ) : null}
      </div>

      {mode === 'group' ? (
        <Field label="Group name" required className="mb-4">
          <Input
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="e.g. Morning shift handover"
            maxLength={120}
          />
        </Field>
      ) : null}

      <div className="relative mb-3">
        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted" aria-hidden />
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search staff…"
          className="pl-9"
        />
      </div>

      <div className="max-h-72 overflow-y-auto rounded-xl border border-line">
        {recipients.isLoading ? (
          <p className="px-4 py-6 text-center text-sm text-ink-muted">Loading staff…</p>
        ) : filtered.length === 0 ? (
          <p className="px-4 py-6 text-center text-sm text-ink-muted">No staff found.</p>
        ) : (
          filtered.map((person) => {
            const checked = selected.includes(person.id)
            return (
              <button
                key={person.id}
                type="button"
                onClick={() => toggle(person.id)}
                className={cn(
                  'flex w-full items-center gap-3 border-b border-line px-3 py-2.5 text-left transition-colors last:border-b-0 hover:bg-canvas',
                  checked && 'bg-brand-faint',
                )}
              >
                <span
                  className={cn(
                    'grid size-9 shrink-0 place-items-center rounded-full text-xs font-bold',
                    checked ? 'bg-brand text-white' : 'bg-brand-soft text-brand',
                  )}
                >
                  {initials(person.name)}
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block truncate text-sm font-medium text-ink">{person.name}</span>
                  <span className="block truncate text-xs text-ink-muted">
                    {person.role ?? 'Staff'}
                    {person.username ? ` · @${person.username}` : ''}
                  </span>
                </span>
                <span
                  className={cn(
                    'grid size-5 shrink-0 place-items-center rounded-md border',
                    checked ? 'border-brand bg-brand text-white' : 'border-line',
                  )}
                  aria-hidden
                >
                  {checked ? '✓' : ''}
                </span>
              </button>
            )
          })
        )}
      </div>

      <p className="mt-3 flex items-center gap-1.5 text-xs text-ink-muted">
        <Users className="size-3.5" aria-hidden />
        {mode === 'direct'
          ? 'One recipient selected for a private conversation.'
          : `${selected.length} participant${selected.length === 1 ? '' : 's'} selected.`}
      </p>
    </Modal>
  )
}
