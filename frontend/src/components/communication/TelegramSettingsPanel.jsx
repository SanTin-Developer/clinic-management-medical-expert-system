import { useEffect, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Check, Copy, Link2, RefreshCw, Send, Unlink, Webhook } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Button from '../ui/Button.jsx'
import Badge from '../ui/Badge.jsx'
import Input from '../ui/Input.jsx'
import Field from '../ui/Field.jsx'
import Toggle from '../ui/Toggle.jsx'
import { CardSkeleton } from '../ui/Skeleton.jsx'
import { ErrorState } from '../ui/State.jsx'
import { cn } from '../../utils/cn.js'
import { telegramApi } from '../../api/communication.js'
import { useAuth } from '../../hooks/useAuth.js'
import { formatDateTime } from '../../utils/format.js'

const CATEGORIES = [
  { value: 'new_message', label: 'Direct messages' },
  { value: 'group_updates', label: 'Group updates' },
  { value: 'announcements', label: 'Announcements' },
]

function CopyButton({ value }) {
  const [copied, setCopied] = useState(false)
  return (
    <Button
      type="button"
      variant="outline"
      size="sm"
      onClick={async () => {
        try {
          await navigator.clipboard.writeText(value)
          setCopied(true)
          setTimeout(() => setCopied(false), 2000)
        } catch {
          toast.error('Could not copy to clipboard.')
        }
      }}
    >
      {copied ? <Check className="size-4" aria-hidden /> : <Copy className="size-4" aria-hidden />}
      {copied ? 'Copied' : 'Copy'}
    </Button>
  )
}

export default function TelegramSettingsPanel() {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [linking, setLinking] = useState(false)
  const [linkResult, setLinkResult] = useState(null)
  const [pollUntil, setPollUntil] = useState(0)

  const status = useQuery({
    queryKey: ['telegram', 'status'],
    queryFn: telegramApi.status,
    // While a freshly generated link is pending, poll so the panel flips to
    // "Linked" as soon as the backend confirms via the webhook/poller.
    refetchInterval: (query) => {
      if (query.state.data?.linked) return false
      return pollUntil > Date.now() ? 4000 : false
    },
  })

  const groupConfig = useQuery({
    queryKey: ['telegram', 'group-config'],
    queryFn: telegramApi.groupConfig,
    enabled: can('telegram.group.manage'),
  })

  const [config, setConfig] = useState({ chat_id: '', is_enabled: false, event_categories: [] })
  const [savingConfig, setSavingConfig] = useState(false)

  useEffect(() => {
    if (groupConfig.data) {
      setConfig({
        chat_id: groupConfig.data.chat_id ?? '',
        is_enabled: Boolean(groupConfig.data.is_enabled),
        event_categories: groupConfig.data.event_categories ?? [],
      })
    }
  }, [groupConfig.data])

  // Stop polling and clear the pending link once the backend reports success.
  useEffect(() => {
    if (status.data?.linked && linkResult) {
      setLinkResult(null)
      setPollUntil(0)
      toast.success('Telegram account linked.')
    }
  }, [status.data?.linked, linkResult])

  const startLink = async () => {
    setLinking(true)
    try {
      const result = await telegramApi.link()
      setLinkResult(result)
      const expiry = result?.expires_at ? new Date(result.expires_at).getTime() : NaN
      setPollUntil(Number.isFinite(expiry) ? expiry + 60_000 : Date.now() + 16 * 60_000)
    } catch (err) {
      toast.error(err.message)
    } finally {
      setLinking(false)
    }
  }

  const unlink = async () => {
    try {
      await telegramApi.unlink()
      setLinkResult(null)
      queryClient.invalidateQueries({ queryKey: ['telegram', 'status'] })
      toast.success('Telegram account unlinked.')
    } catch (err) {
      toast.error(err.message)
    }
  }

  const saveConfig = async () => {
    setSavingConfig(true)
    try {
      const payload = {
        chat_id: config.chat_id === '' ? null : Number(config.chat_id),
        is_enabled: config.is_enabled,
        event_categories: config.event_categories,
      }
      const data = await telegramApi.updateGroupConfig(payload)
      queryClient.setQueryData(['telegram', 'group-config'], data)
      toast.success('Group configuration saved.')
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSavingConfig(false)
    }
  }

  const registerWebhook = async () => {
    try {
      const result = await telegramApi.registerWebhook()
      toast.success(`Webhook registered at ${result.url}`)
    } catch (err) {
      toast.error(err.message)
    }
  }

  const toggleCategory = (value) => {
    setConfig((prev) => ({
      ...prev,
      event_categories: prev.event_categories.includes(value)
        ? prev.event_categories.filter((c) => c !== value)
        : [...prev.event_categories, value],
    }))
  }

  if (status.isLoading) {
    return (
      <Card title="Telegram">
        <CardSkeleton rows={3} />
      </Card>
    )
  }

  if (status.isError) {
    return (
      <Card title="Telegram">
        <ErrorState message={status.error.message} onRetry={() => status.refetch()} />
      </Card>
    )
  }

  const data = status.data ?? {}

  return (
    <div className="space-y-6">
      <Card
        title="Telegram notifications"
        subtitle="Receive clinic alerts directly in Telegram."
      >
        {!data.enabled ? (
          <p className="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-700">
            Telegram integration is currently disabled by an administrator.
          </p>
        ) : data.linked ? (
          <div className="space-y-4">
            <div className="flex items-center gap-3">
              <span className="grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-600 dark:text-emerald-300">
                <Send className="size-5" aria-hidden />
              </span>
              <div>
                <p className="flex items-center gap-2 text-sm font-semibold text-ink">
                  Linked
                  {data.telegram_username ? (
                    <Badge tone="green">@{data.telegram_username}</Badge>
                  ) : null}
                </p>
                <p className="text-xs text-ink-muted">
                  Linked {formatDateTime(data.linked_at)}
                </p>
              </div>
            </div>
            <Button variant="outline" onClick={unlink}>
              <Unlink className="size-4" aria-hidden /> Unlink Telegram
            </Button>
          </div>
        ) : (
          <div className="space-y-4">
            <p className="text-sm text-ink-muted">
              Link your Telegram account to receive notifications on your phone. Open the bot and
              share the link below to complete setup.
            </p>
            {linkResult ? (
              <div className="space-y-3 rounded-xl border border-line bg-canvas/50 p-4">
                <div>
                  <p className="text-xs font-medium text-ink-muted">Telegram bot</p>
                  <p className="text-sm font-semibold text-ink">@{data.bot_username ?? 'RoyalCare bot'}</p>
                </div>
                {linkResult.deep_link ? (
                  <div className="flex items-center gap-2">
                    <a
                      href={linkResult.deep_link}
                      target="_blank"
                      rel="noreferrer"
                      className="text-sm font-semibold text-brand hover:text-brand-dark"
                    >
                      Open in Telegram
                    </a>
                    <CopyButton value={linkResult.deep_link} />
                  </div>
                ) : null}
                <div>
                  <p className="text-xs font-medium text-ink-muted">
                    Or send this command to the bot (expires {formatDateTime(linkResult.expires_at)})
                  </p>
                  <div className="mt-1 flex items-center gap-2">
                    <code className="flex-1 truncate rounded-lg border border-line bg-surface px-3 py-2 text-xs text-ink">
                      /start {linkResult.token}
                    </code>
                    <CopyButton value={`/start ${linkResult.token}`} />
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  <Button variant="outline" size="sm" onClick={() => status.refetch()}>
                    <RefreshCw className="size-4" aria-hidden /> Check status
                  </Button>
                  <span className="text-xs text-ink-muted">
                    Waiting for the bot to confirm… this updates automatically.
                  </span>
                </div>
              </div>
            ) : null}
            <Button onClick={startLink} loading={linking}>
              <Link2 className="size-4" aria-hidden />
              {linkResult ? 'Generate new link' : 'Link Telegram account'}
            </Button>
          </div>
        )}
      </Card>

      {can('telegram.group.manage') ? (
        <Card
          title="Group notifications"
          subtitle="Mirror selected events into a Telegram group for the whole clinic."
          actions={
            <Button variant="outline" size="sm" onClick={registerWebhook}>
              <Webhook className="size-4" aria-hidden /> Register webhook
            </Button>
          }
        >
          {groupConfig.isLoading ? (
            <CardSkeleton rows={3} />
          ) : groupConfig.isError ? (
            <ErrorState message={groupConfig.error.message} onRetry={() => groupConfig.refetch()} />
          ) : (
            <div className="space-y-5">
              <Toggle
                label="Enable group notifications"
                hint="When enabled, selected events are posted to the configured Telegram group."
                checked={config.is_enabled}
                onChange={(value) => setConfig((prev) => ({ ...prev, is_enabled: value }))}
              />

              <Field
                label="Telegram group chat ID"
                hint="Add the bot to your group and provide the numeric chat ID (e.g. -1001234567890)."
              >
                <Input
                  type="number"
                  value={config.chat_id}
                  onChange={(e) => setConfig((prev) => ({ ...prev, chat_id: e.target.value }))}
                  placeholder="-1001234567890"
                />
              </Field>

              <div>
                <p className="mb-2 text-sm font-medium text-ink">Event categories</p>
                <div className="flex flex-wrap gap-2">
                  {CATEGORIES.map((category) => {
                    const active = config.event_categories.includes(category.value)
                    return (
                      <button
                        key={category.value}
                        type="button"
                        onClick={() => toggleCategory(category.value)}
                        className={cn(
                          'rounded-full border px-3 py-1.5 text-sm font-medium transition-colors',
                          active
                            ? 'border-brand bg-brand-soft text-brand-dark'
                            : 'border-line text-ink-muted hover:text-ink',
                        )}
                      >
                        {category.label}
                      </button>
                    )
                  })}
                </div>
              </div>

              <Button onClick={saveConfig} loading={savingConfig}>
                Save configuration
              </Button>
            </div>
          )}
        </Card>
      ) : null}
    </div>
  )
}
