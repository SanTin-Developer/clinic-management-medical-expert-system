import { useState, useEffect } from 'react'
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
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

/** Keys whose value is a repeatable list (stored in the `data` JSON column). */
const LIST_KEYS = ['about_values', 'about_commitments']

/** Keys whose value is an uploaded image. */
const IMAGE_KEYS = ['about_story_image']

function RepeatableList({ label, hint, items, onChange, addLabel }) {
  const update = (index, field, value) => {
    onChange(items.map((item, i) => (i === index ? { ...item, [field]: value } : item)))
  }
  const remove = (index) => onChange(items.filter((_, i) => i !== index))
  const add = () => onChange([...items, { title: '', text: '' }])

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-semibold text-ink">{label}</p>
          {hint ? <p className="text-xs text-ink-muted">{hint}</p> : null}
        </div>
        <Button type="button" size="sm" variant="outline" onClick={add}>
          <Plus className="size-4" aria-hidden />
          {addLabel}
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
              <Input
                value={item.title ?? ''}
                onChange={(e) => update(index, 'title', e.target.value)}
                placeholder="Title"
              />
              <Textarea
                rows={2}
                value={item.text ?? ''}
                onChange={(e) => update(index, 'text', e.target.value)}
                placeholder="Description"
              />
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

export default function WebsiteContentBlocksPage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'admin', 'content-blocks', { group: 'about' }],
    queryFn: () => websiteAdminApi.listContentBlocks({ group: 'about' }),
  })

  const [text, setText] = useState({})
  const [lists, setLists] = useState({ about_values: [], about_commitments: [] })
  const [image, setImage] = useState(null)
  const [removeImage, setRemoveImage] = useState(false)
  const [saving, setSaving] = useState(false)

  const blocks = data?.data ?? []
  const storyImageBlock = blocks.find((block) => block.key === 'about_story_image')

  useEffect(() => {
    if (blocks.length === 0) return
    const nextText = {}
    const nextLists = { about_values: [], about_commitments: [] }
    blocks.forEach((block) => {
      if (IMAGE_KEYS.includes(block.key)) return
      if (LIST_KEYS.includes(block.key)) {
        nextLists[block.key] = Array.isArray(block.data) ? block.data : []
        return
      }
      nextText[block.key] = block.body ?? ''
    })
    setText(nextText)
    setLists(nextLists)
    setImage(null)
    setRemoveImage(false)
  }, [blocks])

  const set = (key) => (value) => setText((t) => ({ ...t, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      const simpleBlocks = Object.entries(text).map(([key, body]) => ({
        key,
        body: body || null,
      }))
      const listBlocks = LIST_KEYS.map((key) => ({ key, data: lists[key] ?? [] }))

      await websiteAdminApi.bulkUpdateContentBlocks({ blocks: [...simpleBlocks, ...listBlocks] })

      if (image || removeImage) {
        const payload = {}
        if (image) payload.image = image
        if (removeImage) payload.remove_image = true
        await websiteAdminApi.updateContentBlock(storyImageBlock.id, payload)
      }

      toast.success('About page content updated.')
      refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  if (isLoading) {
    return (
      <div className="grid place-items-center py-20">
        <Spinner label="Loading content…" />
      </div>
    )
  }

  if (isError) {
    return <ErrorState onRetry={refetch} />
  }

  return (
    <form onSubmit={submit}>
      <PageHeader
        title="About Page"
        description="Edit the text and image shown on the public About page. The design and layout stay the same."
        actions={
          <Button type="submit" loading={saving}>
            <Save className="size-4" aria-hidden />
            Save changes
          </Button>
        }
      />

      <div className="grid gap-6">
        <Card title="Hero">
          <div className="space-y-4">
            <Field label="Badge" htmlFor="about_hero_badge">
              <Input id="about_hero_badge" value={text.about_hero_badge ?? ''} onChange={(e) => set('about_hero_badge')(e.target.value)} />
            </Field>
            <Field label="Hero title" htmlFor="about_hero_title">
              <Input id="about_hero_title" value={text.about_hero_title ?? ''} onChange={(e) => set('about_hero_title')(e.target.value)} />
            </Field>
            <Field label="Hero subtitle" htmlFor="about_hero_subtitle">
              <Textarea id="about_hero_subtitle" rows={3} value={text.about_hero_subtitle ?? ''} onChange={(e) => set('about_hero_subtitle')(e.target.value)} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Primary button" htmlFor="about_hero_primary_link">
                <Input id="about_hero_primary_link" value={text.about_hero_primary_link ?? ''} onChange={(e) => set('about_hero_primary_link')(e.target.value)} />
              </Field>
              <Field label="Secondary button" htmlFor="about_hero_secondary_link">
                <Input id="about_hero_secondary_link" value={text.about_hero_secondary_link ?? ''} onChange={(e) => set('about_hero_secondary_link')(e.target.value)} />
              </Field>
            </div>
          </div>
        </Card>

        <Card title="Section Links">
          <p className="mb-4 text-xs text-ink-muted">Labels of the quick-jump chips shown in the hero.</p>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Our Story" htmlFor="about_nav_story_label">
              <Input id="about_nav_story_label" value={text.about_nav_story_label ?? ''} onChange={(e) => set('about_nav_story_label')(e.target.value)} />
            </Field>
            <Field label="Mission & Vision" htmlFor="about_nav_mission_label">
              <Input id="about_nav_mission_label" value={text.about_nav_mission_label ?? ''} onChange={(e) => set('about_nav_mission_label')(e.target.value)} />
            </Field>
            <Field label="Core Values" htmlFor="about_nav_values_label">
              <Input id="about_nav_values_label" value={text.about_nav_values_label ?? ''} onChange={(e) => set('about_nav_values_label')(e.target.value)} />
            </Field>
            <Field label="Our Facilities" htmlFor="about_nav_facilities_label">
              <Input id="about_nav_facilities_label" value={text.about_nav_facilities_label ?? ''} onChange={(e) => set('about_nav_facilities_label')(e.target.value)} />
            </Field>
            <Field label="Our Team" htmlFor="about_nav_team_label">
              <Input id="about_nav_team_label" value={text.about_nav_team_label ?? ''} onChange={(e) => set('about_nav_team_label')(e.target.value)} />
            </Field>
          </div>
        </Card>

        <Card title="Who We Are">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_story_eyebrow" hint="Small label above the heading, e.g. “01 — Who We Are”.">
              <Input id="about_story_eyebrow" value={text.about_story_eyebrow ?? ''} onChange={(e) => set('about_story_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Heading" htmlFor="about_story_heading">
              <Input id="about_story_heading" value={text.about_story_heading ?? ''} onChange={(e) => set('about_story_heading')(e.target.value)} />
            </Field>
            <Field label="Subheading" htmlFor="about_story_subheading">
              <Textarea id="about_story_subheading" rows={3} value={text.about_story_subheading ?? ''} onChange={(e) => set('about_story_subheading')(e.target.value)} />
            </Field>
            <Field label="Image note" htmlFor="about_story_note" hint="Optional note shown on the card overlapping the photo.">
              <Input id="about_story_note" value={text.about_story_note ?? ''} onChange={(e) => set('about_story_note')(e.target.value)} />
            </Field>
            <Field label="Image" htmlFor="about_story_image" hint="Photo shown next to the Who We Are text.">
              <ImageField
                id="about_story_image"
                file={image}
                existingUrl={removeImage ? null : storyImageBlock?.image?.url}
                onFile={(file) => {
                  setImage(file)
                  setRemoveImage(false)
                }}
                onRemove={() => {
                  setImage(null)
                  setRemoveImage(true)
                }}
              />
            </Field>
          </div>
        </Card>

        <Card title="Mission & Vision">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_mission_eyebrow" hint="Small label above the heading, e.g. “02 — Our Direction”.">
              <Input id="about_mission_eyebrow" value={text.about_mission_eyebrow ?? ''} onChange={(e) => set('about_mission_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Section heading" htmlFor="about_mission_heading">
              <Input id="about_mission_heading" value={text.about_mission_heading ?? ''} onChange={(e) => set('about_mission_heading')(e.target.value)} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Mission title" htmlFor="about_mission_title">
                <Input id="about_mission_title" value={text.about_mission_title ?? ''} onChange={(e) => set('about_mission_title')(e.target.value)} />
              </Field>
              <Field label="Vision title" htmlFor="about_vision_title">
                <Input id="about_vision_title" value={text.about_vision_title ?? ''} onChange={(e) => set('about_vision_title')(e.target.value)} />
              </Field>
            </div>
            <Field label="Mission text" htmlFor="about_mission_text">
              <Textarea id="about_mission_text" rows={3} value={text.about_mission_text ?? ''} onChange={(e) => set('about_mission_text')(e.target.value)} />
            </Field>
            <Field label="Vision text" htmlFor="about_vision_text">
              <Textarea id="about_vision_text" rows={3} value={text.about_vision_text ?? ''} onChange={(e) => set('about_vision_text')(e.target.value)} />
            </Field>
          </div>
        </Card>

        <Card title="Core Values">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_values_eyebrow" hint="Small label above the heading, e.g. “03 — What Guides Us”.">
              <Input id="about_values_eyebrow" value={text.about_values_eyebrow ?? ''} onChange={(e) => set('about_values_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Heading" htmlFor="about_values_heading">
              <Input id="about_values_heading" value={text.about_values_heading ?? ''} onChange={(e) => set('about_values_heading')(e.target.value)} />
            </Field>
            <Field label="Subheading" htmlFor="about_values_subheading">
              <Textarea id="about_values_subheading" rows={2} value={text.about_values_subheading ?? ''} onChange={(e) => set('about_values_subheading')(e.target.value)} />
            </Field>
            <RepeatableList
              label="Value cards"
              hint="The first card is highlighted in the layout."
              addLabel="Add value"
              items={lists.about_values}
              onChange={(items) => setLists((l) => ({ ...l, about_values: items }))}
            />
          </div>
        </Card>

        <Card title="Facilities">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_facilities_eyebrow" hint="Small label above the heading, e.g. “04 — Our Facilities”.">
              <Input id="about_facilities_eyebrow" value={text.about_facilities_eyebrow ?? ''} onChange={(e) => set('about_facilities_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Heading" htmlFor="about_facilities_heading">
              <Input id="about_facilities_heading" value={text.about_facilities_heading ?? ''} onChange={(e) => set('about_facilities_heading')(e.target.value)} />
            </Field>
            <Field label="Description" htmlFor="about_facilities_text">
              <Textarea id="about_facilities_text" rows={2} value={text.about_facilities_text ?? ''} onChange={(e) => set('about_facilities_text')(e.target.value)} />
            </Field>
            <Field label="Link label" htmlFor="about_facilities_link" hint="Button linking to the full gallery.">
              <Input id="about_facilities_link" value={text.about_facilities_link ?? ''} onChange={(e) => set('about_facilities_link')(e.target.value)} />
            </Field>
          </div>
        </Card>

        <Card title="Our Team">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_team_eyebrow" hint="Small label above the heading, e.g. “05 — Our People”.">
              <Input id="about_team_eyebrow" value={text.about_team_eyebrow ?? ''} onChange={(e) => set('about_team_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Heading" htmlFor="about_team_heading">
              <Input id="about_team_heading" value={text.about_team_heading ?? ''} onChange={(e) => set('about_team_heading')(e.target.value)} />
            </Field>
            <Field label="Description" htmlFor="about_team_text">
              <Textarea id="about_team_text" rows={2} value={text.about_team_text ?? ''} onChange={(e) => set('about_team_text')(e.target.value)} />
            </Field>
            <Field label="Link label" htmlFor="about_team_link" hint="Link shown below the team cards.">
              <Input id="about_team_link" value={text.about_team_link ?? ''} onChange={(e) => set('about_team_link')(e.target.value)} />
            </Field>
          </div>
        </Card>

        <Card title="Our Commitment">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_commitment_eyebrow" hint="Small label above the heading, e.g. “06 — Our Commitment”.">
              <Input id="about_commitment_eyebrow" value={text.about_commitment_eyebrow ?? ''} onChange={(e) => set('about_commitment_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Heading" htmlFor="about_commitment_heading">
              <Input id="about_commitment_heading" value={text.about_commitment_heading ?? ''} onChange={(e) => set('about_commitment_heading')(e.target.value)} />
            </Field>
            <Field label="Subheading" htmlFor="about_commitment_subheading">
              <Textarea id="about_commitment_subheading" rows={2} value={text.about_commitment_subheading ?? ''} onChange={(e) => set('about_commitment_subheading')(e.target.value)} />
            </Field>
            <RepeatableList
              label="Commitment items"
              addLabel="Add item"
              items={lists.about_commitments}
              onChange={(items) => setLists((l) => ({ ...l, about_commitments: items }))}
            />
          </div>
        </Card>

        <Card title="Call to Action">
          <div className="space-y-4">
            <Field label="Eyebrow" htmlFor="about_cta_eyebrow" hint="Small label above the heading, e.g. “07 — Next Steps”.">
              <Input id="about_cta_eyebrow" value={text.about_cta_eyebrow ?? ''} onChange={(e) => set('about_cta_eyebrow')(e.target.value)} />
            </Field>
            <Field label="Heading" htmlFor="about_cta_heading">
              <Input id="about_cta_heading" value={text.about_cta_heading ?? ''} onChange={(e) => set('about_cta_heading')(e.target.value)} />
            </Field>
            <Field label="Subheading" htmlFor="about_cta_subheading">
              <Textarea id="about_cta_subheading" rows={3} value={text.about_cta_subheading ?? ''} onChange={(e) => set('about_cta_subheading')(e.target.value)} />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Primary button" htmlFor="about_cta_primary_link">
                <Input id="about_cta_primary_link" value={text.about_cta_primary_link ?? ''} onChange={(e) => set('about_cta_primary_link')(e.target.value)} />
              </Field>
              <Field label="Secondary button" htmlFor="about_cta_secondary_link">
                <Input id="about_cta_secondary_link" value={text.about_cta_secondary_link ?? ''} onChange={(e) => set('about_cta_secondary_link')(e.target.value)} />
              </Field>
            </div>
          </div>
        </Card>
      </div>

      <div className="mt-6 flex justify-end">
        <Button type="submit" loading={saving}>
          <Save className="size-4" aria-hidden />
          Save changes
        </Button>
      </div>
    </form>
  )
}
