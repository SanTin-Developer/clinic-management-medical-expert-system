import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Inbox, Mail, MailOpen, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Badge from '../../components/ui/Badge.jsx'
import StatCard from '../../components/ui/StatCard.jsx'
import Select from '../../components/ui/Select.jsx'
import SearchInput from '../../components/ui/SearchInput.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import RecordDetailModal from '../../components/ui/RecordDetailModal.jsx'
import Table, { TBody, Td, Th, THead, Tr } from '../../components/ui/Table.jsx'
import Pagination from '../../components/ui/Pagination.jsx'
import { RowSkeleton } from '../../components/ui/Skeleton.jsx'
import { EmptyState, ErrorState } from '../../components/ui/State.jsx'
import { usePaginated } from '../../hooks/usePaginated.js'
import { useAuth } from '../../hooks/useAuth.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { formatDateTime } from '../../utils/format.js'

export default function WebsiteContactMessagesPage() {
  const { can } = useAuth()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [selected, setSelected] = useState(null)
  const [deleting, setDeleting] = useState(null)
  const [busy, setBusy] = useState(false)

  const query = usePaginated(
    ['website', 'admin', 'contact-messages', { search, status }],
    (params) =>
      websiteAdminApi.listContactMessages({
        ...params,
        search: search || undefined,
        status: status || undefined,
      }),
    { page },
  )

  const rows = query.data?.data ?? []
  const stats = query.data?.stats ?? {}

  const refreshRelated = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] })
    queryClient.invalidateQueries({ queryKey: ['website', 'admin', 'dashboard'] })
  }

  const toggleRead = async (row) => {
    try {
      const res = row.read_at
        ? await websiteAdminApi.markContactMessageUnread(row.id)
        : await websiteAdminApi.markContactMessageRead(row.id)
      const updated = res?.data ?? res
      toast.success(res?.message ?? 'Contact message updated.')
      setSelected((current) => (current?.id === row.id ? { ...current, ...updated } : current))
      query.refetch()
      refreshRelated()
    } catch (err) {
      toast.error(err.message)
    }
  }

  const confirmDelete = async () => {
    setBusy(true)
    try {
      await websiteAdminApi.deleteContactMessage(deleting.id)
      toast.success('Message deleted.')
      setDeleting(null)
      query.refetch()
      refreshRelated()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setBusy(false)
    }
  }

  return (
    <>
      <PageHeader
        title="Contact Messages"
        description="Messages visitors send from the public website's Contact page."
      />

      <div className="mb-5 grid gap-4 sm:grid-cols-3">
        <StatCard label="Total messages" value={stats.total ?? 0} icon={Inbox} tone="brand" loading={query.isLoading} />
        <StatCard label="Unread" value={stats.unread ?? 0} icon={Mail} tone="amber" loading={query.isLoading} />
        <StatCard label="Read" value={stats.read ?? 0} icon={MailOpen} tone="green" loading={query.isLoading} />
      </div>

      <Card bodyClassName="p-0">
        <div className="flex flex-col gap-3 border-b border-line p-4 sm:flex-row sm:items-center sm:justify-between">
          <SearchInput
            onDebounced={(value) => {
              setSearch(value)
              setPage(1)
            }}
            placeholder="Search name, email, subject…"
            className="sm:max-w-xs"
          />
          <Select
            value={status}
            onChange={(e) => {
              setStatus(e.target.value)
              setPage(1)
            }}
            className="sm:w-40"
            aria-label="Filter by status"
          >
            <option value="">All messages</option>
            <option value="unread">Unread</option>
            <option value="read">Read</option>
          </Select>
        </div>

        {query.isLoading ? (
          <div className="p-5">
            <RowSkeleton rows={5} cols={4} />
          </div>
        ) : query.isError ? (
          <div className="p-5">
            <ErrorState onRetry={query.refetch} />
          </div>
        ) : rows.length === 0 ? (
          <EmptyState
            title="No contact messages yet"
            message="Messages sent through the public Contact page will appear here."
            icon={Inbox}
          />
        ) : (
          <div className="p-4">
            <Table>
              <THead>
                <Tr>
                  <Th>Sender</Th>
                  <Th>Message</Th>
                  <Th>Received</Th>
                  <Th>Status</Th>
                  <Th className="text-right">Actions</Th>
                </Tr>
              </THead>
              <TBody>
                {rows.map((row) => (
                  <Tr key={row.id} onClick={() => setSelected(row)}>
                    <Td primary>
                      <div className="flex items-center gap-2">
                        {!row.read_at ? (
                          <span aria-label="Unread" className="size-2 shrink-0 rounded-full bg-brand" />
                        ) : null}
                        <div className={row.read_at ? 'text-ink' : 'font-semibold text-ink'}>
                          {row.name}
                          <div className="text-xs font-normal text-ink-muted">{row.email}</div>
                        </div>
                      </div>
                    </Td>
                    <Td>
                      <div className="font-medium text-ink">{row.subject || <span className="text-ink-muted">(No subject)</span>}</div>
                      <div className="max-w-[46ch] truncate text-xs text-ink-muted">{row.message}</div>
                    </Td>
                    <Td leading className="whitespace-nowrap text-ink-muted">{formatDateTime(row.created_at)}</Td>
                    <Td trailing>
                      <Badge tone={row.read_at ? 'gray' : 'amber'}>{row.read_at ? 'read' : 'unread'}</Badge>
                    </Td>
                    <Td onClick={(event) => event.stopPropagation()}>
                      <div className="flex items-center justify-end gap-1">
                        {can('website.contact.manage') ? (
                          <Button size="sm" variant="outline" onClick={() => toggleRead(row)}>
                            {row.read_at ? 'Mark unread' : 'Mark read'}
                          </Button>
                        ) : null}
                        {can('website.contact.manage') ? (
                          <Button size="sm" variant="ghost" onClick={() => setDeleting(row)} aria-label="Delete">
                            <Trash2 className="size-4" aria-hidden />
                          </Button>
                        ) : null}
                      </div>
                    </Td>
                  </Tr>
                ))}
              </TBody>
            </Table>
          </div>
        )}

        <Pagination
          page={query.data?.current_page ?? 1}
          lastPage={query.data?.last_page ?? 1}
          total={query.data?.total}
          onChange={setPage}
        />
      </Card>

      <RecordDetailModal
        open={Boolean(selected)}
        onClose={() => setSelected(null)}
        title={selected?.subject || '(No subject)'}
        subtitle={selected ? `${selected.name} · ${selected.email}` : ''}
        badges={
          selected
            ? [
                {
                  label: selected.read_at ? 'read' : 'unread',
                  tone: selected.read_at ? 'gray' : 'amber',
                },
              ]
            : []
        }
        fields={
          selected
            ? [
                { label: 'From', value: `${selected.name} <${selected.email}>` },
                { label: 'Phone', value: selected.phone },
                { label: 'Received', value: formatDateTime(selected.created_at) },
              ]
            : []
        }
      >
        <div className="rounded-xl border border-line bg-canvas p-4">
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-ink">{selected?.message}</p>
        </div>
        {can('website.contact.manage') && selected ? (
          <div className="flex items-center justify-end gap-2">
            <Button size="sm" variant="outline" onClick={() => toggleRead(selected)}>
              {selected.read_at ? 'Mark unread' : 'Mark read'}
            </Button>
            <Button
              size="sm"
              variant="ghost"
              onClick={() => {
                const row = selected
                setSelected(null)
                setDeleting(row)
              }}
            >
              <Trash2 className="size-4" aria-hidden /> Delete
            </Button>
          </div>
        ) : null}
      </RecordDetailModal>

      <ConfirmDialog
        open={Boolean(deleting)}
        onClose={() => setDeleting(null)}
        onConfirm={confirmDelete}
        title="Delete message"
        message={`Delete the message from “${deleting?.name}”? This cannot be undone.`}
        confirmLabel="Delete"
        loading={busy}
      />
    </>
  )
}
