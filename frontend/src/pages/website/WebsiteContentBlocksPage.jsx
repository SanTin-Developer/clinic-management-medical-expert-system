import { useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'
import { useQuery } from '@tanstack/react-query'
import { Plus, Save, Trash2 } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import Textarea from '../../components/ui/Textarea.jsx'
import ImageField from '../../components/ui/ImageField.jsx'
import Tabs from '../../components/ui/Tabs.jsx'
import { PageSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { CONTENT_GROUPS, groupFields } from '../../constants/websiteContent.js'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const DEFAULT_LIST_FIELDS = [
  { key: 'title', label: 'Title', placeholder: 'Title' },
  { key: 'text', label: 'Description', placeholder: 'Description', multiline: true },
]

const KM_HINT = 'Shown on the Khmer version of the website. Leave empty to reuse the English text.'

function RepeatableList({ field, items, onChange }) {
  const listFields = field.listFields ?? DEFAULT_LIST_FIELDS

  const update = (index, key, value) => {
    onChange(items.map((item, i) => (i === index ? { ...item, [key]: value } : item)))
  }
  const remove = (index) => onChange(items.filter((_, i) => i !== index))
  const add = () => onChange([...items, Object.fromEntries(listFields.map((f) => [f.key, '']))])

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <p className="text-sm font-semibold text-ink">{field.label}</p>
        <Button type="button" size="sm" variant="outline" onClick={add}>
          <Plus className="size-4" aria-hidden />
          {field.addLabel ?? 'Add item'}
        </Button>
      </div>
      <div className="space-y-3">
        {items.map((item, index) => (
          <div key={index} className="rounded-xl border border-line bg-canvas/50 p-3">
            <div className="mb-2 flex items-center justify-between">
              <span className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                Item {index + 1}
              </span>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => remove(index)}
                aria-label={`Remove item ${index + 1}`}
              >
                <Trash2 className="size-4 text-red-500" aria-hidden />
              </Button>
            </div>
            <div className="space-y-2">
              {listFields.map((listField) =>
                listField.multiline ? (
                  <Textarea
                    key={listField.key}
                    rows={2}
                    value={item[listField.key] ?? ''}
                    onChange={(e) => update(index, listField.key, e.target.value)}
                    placeholder={listField.placeholder}
                  />
                ) : (
                  <Input
                    key={listField.key}
                    value={item[listField.key] ?? ''}
                    onChange={(e) => update(index, listField.key, e.target.value)}
                    placeholder={listField.placeholder}
                  />
                ),
              )}
            </div>
          </div>
        ))}
        {items.length === 0 ? (
          <p className="rounded-lg border border-dashed border-line px-3 py-4 text-center text-sm text-ink-muted">
            No items yet.
          </p>
        ) : null}
      </div>
    </div>
  )
}

/**
 * Generic editor for the `content_blocks` table: every public page (home,
 * header, footer, services, team, gallery, news, contact, about) is one tab
 * driven by the CONTENT_GROUPS registry, so new editable copy only needs a
 * seeder entry plus a registry entry — no new screen.
 */
export default function WebsiteContentBlocksPage() {
  const [groupId, setGroupId] = useState(CONTENT_GROUPS[0].id)
  const group = CONTENT_GROUPS.find((entry) => entry.id === groupId) ?? CONTENT_GROUPS[0]
  const fields = useMemo(() => groupFields(group), [group])

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'admin', 'content-blocks', { group: group.id }],
    queryFn: () => websiteAdminApi.listContentBlocks({ group: group.id }),
  })

  const [values, setValues] = useState({})
  const [titlesKm, setTitlesKm] = useState({})
  const [bodiesKm, setBodiesKm] = useState({})
  const [lists, setLists] = useState({})
  const [images, setImages] = useState({})
  const [saving, setSaving] = useState(false)

  // Memoized so the reset effect only runs when the query result or the
  // selected group actually changes — `?? []` would otherwise be a fresh
  // reference every render and loop forever while loading.
  const blocks = useMemo(() => data?.data ?? [], [data])

  useEffect(() => {
    const byKey = Object.fromEntries(blocks.map((block) => [block.key, block]))
    const nextValues = {}
    const nextTitlesKm = {}
    const nextBodiesKm = {}
    const nextLists = {}
    const nextImages = {}
    fields.forEach((field) => {
      if (field.type === 'image') {
        nextImages[field.key] = {
          file: null,
          removed: false,
          existingUrl: byKey[field.key]?.image?.url ?? null,
        }
        return
      }
      if (field.type === 'list') {
        const stored = byKey[field.key]?.data
        nextLists[field.key] = Array.isArray(stored) ? stored : []
        return
      }
      nextValues[field.key] = byKey[field.key]?.body ?? ''
      nextTitlesKm[field.key] = byKey[field.key]?.title_km ?? ''
      nextBodiesKm[field.key] = byKey[field.key]?.body_km ?? ''
    })
    setValues(nextValues)
    setTitlesKm(nextTitlesKm)
    setBodiesKm(nextBodiesKm)
    setLists(nextLists)
    setImages(nextImages)
  }, [blocks, fields])

  const setValue = (key) => (value) => setValues((current) => ({ ...current, [key]: value }))
  const setTitleKm = (key) => (value) =>
    setTitlesKm((current) => ({ ...current, [key]: value }))
  const setBodyKm = (key) => (value) =>
    setBodiesKm((current) => ({ ...current, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      const byKey = Object.fromEntries(blocks.map((entry) => [entry.key, entry]))
      const blocksPayload = [
        ...Object.entries(values).map(([key, body]) => ({
          group: group.id,
          key,
          title: byKey[key]?.title ?? null,
          title_km: titlesKm[key]?.trim() || null,
          body: body || null,
          body_km: bodiesKm[key]?.trim() || null,
        })),
        ...Object.entries(lists).map(([key, list]) => ({
          group: group.id,
          key,
          title: byKey[key]?.title ?? null,
          title_km: byKey[key]?.title_km ?? null,
          data: list,
          body_km: byKey[key]?.body_km ?? null,
        })),
        // Image blocks are sent as bare rows so the record always exists and
        // the picture can be attached to it right after the bulk save.
        ...Object.keys(images).map((key) => ({
          group: group.id,
          key,
          title: byKey[key]?.title ?? null,
          title_km: byKey[key]?.title_km ?? null,
          body_km: byKey[key]?.body_km ?? null,
        })),
      ]
      await websiteAdminApi.bulkUpdateContentBlocks({ blocks: blocksPayload })

      const pendingImages = Object.entries(images).filter(([, state]) => state.file || state.removed)
      let refreshed = null
      for (const [key, state] of pendingImages) {
        let block = blocks.find((entry) => entry.key === key)
        if (!block) {
          // The block row may not exist yet (fresh database): bulkUpdate just
          // created it, so re-read the group to pick up its id.
          refreshed ??= await websiteAdminApi.listContentBlocks({ group: group.id })
          block = (refreshed?.data ?? []).find((entry) => entry.key === key)
        }
        if (!block) {
          toast.error(`Could not save “${key}” — run the website content seeder first.`)
          continue
        }
        const payload = {}
        if (state.file) payload.image = state.file
        if (state.removed) payload.remove_image = true
        await websiteAdminApi.updateContentBlock(block.id, payload)
      }

      toast.success(`${group.label} content updated.`)
      refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  if (isLoading) {
    return (
      <PageSkeleton
        title="Page Content"
        description={group.description ?? 'Loading editable website content…'}
        cards={2}
        rows={4}
      />
    )
  }

  if (isError) {
    return <ErrorState onRetry={refetch} />
  }

  return (
    <div className="flex min-h-0 flex-col">
      <form onSubmit={submit} className="min-w-0">
        <PageHeader
          title="Page Content"
          description={group.description}
          actions={
            <Button type="submit" loading={saving}>
              <Save className="size-4" aria-hidden />
              Save changes
            </Button>
          }
        />

        <Tabs
          className="mb-6"
          tabs={CONTENT_GROUPS.map((entry) => ({ value: entry.id, label: entry.label }))}
          value={group.id}
          onChange={setGroupId}
        />

        <div className="grid min-w-0 gap-6">
          {group.cards.map((card) => (
            <Card key={card.title} title={card.title} className="min-w-0">
              {card.hint ? <p className="mb-4 text-xs text-ink-muted">{card.hint}</p> : null}
              <div className="min-w-0 space-y-4">
                {card.fields.map((field) => {
                  if (field.type === 'list') {
                    return (
                      <RepeatableList
                        key={field.key}
                        field={field}
                        items={lists[field.key] ?? []}
                        onChange={(items) => setLists((current) => ({ ...current, [field.key]: items }))}
                      />
                    )
                  }
                  if (field.type === 'image') {
                    const state = images[field.key] ?? { file: null, removed: false, existingUrl: null }
                    return (
                      <Field key={field.key} label={field.label} htmlFor={field.key} hint={field.hint}>
                        <ImageField
                          id={field.key}
                          file={state.file}
                          existingUrl={state.removed ? null : state.existingUrl}
                          onFile={(file) =>
                            setImages((current) => ({
                              ...current,
                              [field.key]: { ...current[field.key], file, removed: false },
                            }))
                          }
                          onRemove={() =>
                            setImages((current) => ({
                              ...current,
                              [field.key]: { ...current[field.key], file: null, removed: true },
                            }))
                          }
                        />
                      </Field>
                    )
                  }
                  return (
                    <div key={field.key} className="space-y-3">
                      <Field label={field.label} htmlFor={field.key} hint={field.hint}>
                        {field.type === 'textarea' ? (
                          <Textarea
                            id={field.key}
                            rows={field.rows ?? 2}
                            value={values[field.key] ?? ''}
                            onChange={(e) => setValue(field.key)(e.target.value)}
                          />
                        ) : (
                          <Input
                            id={field.key}
                            value={values[field.key] ?? ''}
                            onChange={(e) => setValue(field.key)(e.target.value)}
                          />
                        )}
                      </Field>
                      <Field
                        label="Title (Khmer)"
                        htmlFor={`${field.key}-title-km`}
                        hint={KM_HINT}
                      >
                        <Input
                          id={`${field.key}-title-km`}
                          lang="km"
                          value={titlesKm[field.key] ?? ''}
                          onChange={(e) => setTitleKm(field.key)(e.target.value)}
                        />
                      </Field>
                      <Field
                        label={`${field.label} (Khmer)`}
                        htmlFor={`${field.key}-body-km`}
                        hint={KM_HINT}
                      >
                        {field.type === 'textarea' ? (
                          <Textarea
                            id={`${field.key}-body-km`}
                            lang="km"
                            rows={field.rows ?? 2}
                            value={bodiesKm[field.key] ?? ''}
                            onChange={(e) => setBodyKm(field.key)(e.target.value)}
                          />
                        ) : (
                          <Input
                            id={`${field.key}-body-km`}
                            lang="km"
                            value={bodiesKm[field.key] ?? ''}
                            onChange={(e) => setBodyKm(field.key)(e.target.value)}
                          />
                        )}
                      </Field>
                    </div>
                  )
                })}
              </div>
            </Card>
          ))}
        </div>

        <div className="mt-6 mb-4 flex justify-end">
          <Button type="submit" loading={saving}>
            <Save className="size-4" aria-hidden />
            Save changes
          </Button>
        </div>
      </form>
    </div>
  )
}
