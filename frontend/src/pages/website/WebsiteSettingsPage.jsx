import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useBlocker } from 'react-router-dom'
import { toast } from 'sonner'
import { useQuery } from '@tanstack/react-query'
import {
  AlertTriangle,
  CheckCircle2,
  Globe,
  RotateCcw,
  Save,
} from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import Textarea from '../../components/ui/Textarea.jsx'
import Tabs from '../../components/ui/Tabs.jsx'
import ConfirmDialog from '../../components/ui/ConfirmDialog.jsx'
import { PageSkeleton } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import ImageField from '../../components/ui/ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'
import { PUBLIC_SITE_URL } from '../../constants/sitePreview.js'
import { cn } from '../../utils/cn.js'

const EMPTY = {
  site_name: '',
  tagline: '',
  primary_phone: '',
  secondary_phone: '',
  email: '',
  address: '',
  map_embed_url: '',
  business_hours: '',
  facebook_url: '',
  twitter_url: '',
  instagram_url: '',
  linkedin_url: '',
  youtube_url: '',
  seo_title: '',
  seo_description: '',
  seo_keywords: '',
}

const TEXT_FIELDS = Object.keys(EMPTY)

const TABS = [
  { value: 'branding', label: 'Branding' },
  { value: 'contact', label: 'Contact' },
  { value: 'social', label: 'Social links' },
  { value: 'seo', label: 'SEO' },
]

const SOCIAL_FIELDS = [
  { key: 'facebook_url', label: 'Facebook', placeholder: 'https://facebook.com/yourclinic' },
  { key: 'twitter_url', label: 'Twitter / X', placeholder: 'https://x.com/yourclinic' },
  { key: 'instagram_url', label: 'Instagram', placeholder: 'https://instagram.com/yourclinic' },
  { key: 'linkedin_url', label: 'LinkedIn', placeholder: 'https://linkedin.com/company/yourclinic' },
  { key: 'youtube_url', label: 'YouTube', placeholder: 'https://youtube.com/@yourclinic' },
]

/**
 * Character guidance for the SEO fields. `max` mirrors a server-side rule and
 * blocks saving; `recommended` only turns the counter amber so an admin can
 * still save a longer snippet if the search result genuinely needs one.
 */
const SEO_COUNTERS = {
  seo_title: { recommended: 60, max: 255, note: 'Aim for 50–60 characters.' },
  seo_description: { recommended: 160, note: 'Aim for 140–160 characters.' },
  seo_keywords: { recommended: 250, max: 500, note: 'A short, focused list works best.' },
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const URL_PATTERN = /^https?:\/\/[^\s/$.?#][^\s]*$/i

/**
 * Client-side mirror of the settings endpoint's validation, plus the format
 * checks the server cannot express. Returns null when the field is acceptable.
 */
function fieldError(key, rawValue) {
  const value = (rawValue ?? '').trim()
  if (!value) return null

  if (key === 'email') {
    return EMAIL_PATTERN.test(value)
      ? null
      : 'Enter a valid email address, such as clinic@example.com.'
  }

  if (key === 'map_embed_url') {
    return /^(https?:\/\/|<iframe[\s>])/i.test(value)
      ? null
      : 'Paste a Google Maps embed URL, or a complete <iframe> tag.'
  }

  if (key.endsWith('_url')) {
    return URL_PATTERN.test(value)
      ? null
      : 'Enter a full web address starting with http:// or https://.'
  }

  const limits = SEO_COUNTERS[key]
  if (limits?.max && value.length > limits.max) {
    return `Keep this to ${limits.max} characters or fewer (currently ${value.length}).`
  }

  return null
}

/**
 * A settings input with its hint, inline validation message and — for the SEO
 * fields — a live character counter. Keeps every text field on the page
 * consistent instead of repeating the label/hint/error wiring by hand.
 */
function TextField({
  id,
  label,
  hint,
  value,
  onChange,
  multiline,
  rows,
  placeholder,
  type = 'text',
  className,
}) {
  const error = fieldError(id, value)
  const counter = SEO_COUNTERS[id]
  const length = value.length

  let counterText = null
  let counterTone = 'text-ink-muted'
  if (counter) {
    counterText = counter.max ? `${length} / ${counter.max}` : `${length}`
    if (counter.max && length > counter.max) counterTone = 'text-red-600'
    else if (counter.recommended && length > counter.recommended) counterTone = 'text-amber-700'
  }

  return (
    <Field
      label={label}
      htmlFor={id}
      error={error}
      className={className}
      hint={
        <span className="flex items-baseline justify-between gap-3">
          <span>
            {hint}
            {counter?.note && counter.recommended && length > counter.recommended ? (
              <span className="font-medium text-amber-700"> {counter.note}</span>
            ) : null}
          </span>
          {counterText ? (
            <span className={cn('shrink-0 font-medium tabular-nums', counterTone)}>
              {counterText}
            </span>
          ) : null}
        </span>
      }
    >
      {multiline ? (
        <Textarea
          id={id}
          rows={rows ?? 2}
          value={value}
          placeholder={placeholder}
          invalid={Boolean(error)}
          onChange={(e) => onChange(e.target.value)}
        />
      ) : (
        <Input
          id={id}
          type={type}
          value={value}
          placeholder={placeholder}
          invalid={Boolean(error)}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
    </Field>
  )
}

/** Flattens the settings row into the flat key → string shape the form uses. */
function snapshot(settings) {
  return Object.fromEntries(TEXT_FIELDS.map((key) => [key, settings?.[key] ?? '']))
}

/** Domain shown in the SEO snippet preview breadcrumb. */
const PREVIEW_DOMAIN =
  PUBLIC_SITE_URL.replace(/^https?:\/\//, '').replace(/\/$/, '') || 'your-clinic-site.com'

export default function WebsiteSettingsPage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'admin', 'settings'],
    queryFn: websiteAdminApi.getSettings,
  })

  const settings = data?.data

  const [form, setForm] = useState(EMPTY)
  const [logo, setLogo] = useState(null)
  const [favicon, setFavicon] = useState(null)
  const [removed, setRemoved] = useState({ logo: false, favicon: false })
  const [saving, setSaving] = useState(false)
  const [tab, setTab] = useState(TABS[0].value)

  // Signature of the last payload we hydrated from, so a background refetch
  // with unchanged data never overwrites what the admin is busy typing.
  const hydratedRef = useRef(null)

  const resetTo = useCallback((nextSettings) => {
    setForm(snapshot(nextSettings))
    setLogo(null)
    setFavicon(null)
    setRemoved({ logo: false, favicon: false })
  }, [])

  useEffect(() => {
    if (!settings) return
    const signature = JSON.stringify(snapshot(settings))
    if (hydratedRef.current === signature) return
    hydratedRef.current = signature
    resetTo(settings)
  }, [settings, resetTo])

  const set = (key) => (value) => setForm((current) => ({ ...current, [key]: value }))

  const imageChanged = Boolean(logo) || Boolean(favicon) || removed.logo || removed.favicon

  // Server values the form was hydrated from — the baseline for the dirty check.
  const baseline = useMemo(() => snapshot(settings), [settings])

  const isDirty = useMemo(
    () => imageChanged || TEXT_FIELDS.some((key) => form[key] !== baseline[key]),
    [form, baseline, imageChanged],
  )

  const errors = useMemo(() => {
    const next = {}
    for (const key of TEXT_FIELDS) {
      const message = fieldError(key, form[key])
      if (message) next[key] = message
    }
    return next
  }, [form])
  const errorCount = Object.keys(errors).length

  // Warn before a tab close, refresh or external link throws away the edits.
  useEffect(() => {
    if (!isDirty) return undefined
    const onBeforeUnload = (event) => {
      event.preventDefault()
      event.returnValue = ''
    }
    window.addEventListener('beforeunload', onBeforeUnload)
    return () => window.removeEventListener('beforeunload', onBeforeUnload)
  }, [isDirty])

  // React Router navigation guard, resolved through the confirmation dialog.
  const blocker = useBlocker(isDirty)

  const discard = () => {
    if (settings) resetTo(settings)
  }

  const submit = async (event) => {
    event.preventDefault()

    if (errorCount > 0) {
      toast.error('Fix the highlighted fields before saving.')
      return
    }

    setSaving(true)
    try {
      const payload = { ...form }
      if (logo) payload.logo = logo
      else if (removed.logo) payload.remove_logo = true
      if (favicon) payload.favicon = favicon
      else if (removed.favicon) payload.remove_favicon = true

      await websiteAdminApi.updateSettings(payload)
      hydratedRef.current = null
      toast.success('Website settings updated.')
      setLogo(null)
      setFavicon(null)
      setRemoved({ logo: false, favicon: false })
      refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const enabledSocials = SOCIAL_FIELDS.filter((field) => form[field.key].trim())

  if (isLoading) {
    return (
      <PageSkeleton
        title="Website Settings"
        description="Loading branding, contact details, social links and SEO…"
        cards={2}
        rows={5}
      />
    )
  }

  if (isError) {
    return <ErrorState onRetry={refetch} />
  }

  return (
    <form onSubmit={submit} noValidate>
      <PageHeader
        title="Website Settings"
        description="Site-wide branding, contact details and SEO for the public website."
        actions={
          <span
            className={cn(
              'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset',
              isDirty
                ? 'bg-amber-50 text-amber-700 ring-amber-200'
                : 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            )}
          >
            {isDirty ? (
              <AlertTriangle className="size-3.5" aria-hidden />
            ) : (
              <CheckCircle2 className="size-3.5" aria-hidden />
            )}
            {isDirty ? 'Unsaved changes' : 'All changes saved'}
          </span>
        }
      />

      <div className="mb-6 flex gap-3 rounded-xl border border-line bg-brand-faint px-4 py-3">
        <Globe className="mt-0.5 size-4 shrink-0 text-brand" aria-hidden />
        <p className="text-sm text-ink">
          Everything here applies everywhere at once — the header, the footer, contact details and the
          default SEO tags. Page copy such as the hero headline, section headings and banners is
          edited separately under{' '}
          <Link
            to="/website/content-blocks"
            className="font-semibold text-brand hover:underline"
          >
            Page Content
          </Link>
          .
        </p>
      </div>

      <Tabs className="mb-6" tabs={TABS} value={tab} onChange={setTab} />

      <div role="tabpanel" aria-label={TABS.find((item) => item.value === tab)?.label}>
        {tab === 'branding' ? (
          <div className="grid gap-6">
            <Card title="Identity" subtitle="How the clinic is named and described across the site.">
              <div className="space-y-4">
                <TextField
                  id="site_name"
                  label="Site name"
                  hint="Shown in the website header, the footer and the browser tab."
                  placeholder="RoyalCare Clinic"
                  value={form.site_name}
                  onChange={set('site_name')}
                />
                <TextField
                  id="tagline"
                  label="Tagline"
                  hint="One short line under the site name in the header and the footer."
                  placeholder="Your Health, Our Priority"
                  value={form.tagline}
                  onChange={set('tagline')}
                />
              </div>
            </Card>

            <Card
              title="Logo & favicon"
              subtitle="Branding marks used by the public website."
              bodyClassName="p-5"
            >
              <div className="grid gap-6 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <p className="text-sm font-medium text-ink">Logo</p>
                  <ImageField
                    id="logo"
                    file={logo}
                    existingUrl={removed.logo ? null : settings?.logo?.url}
                    hint="PNG or SVG recommended · up to 10MB"
                    onFile={(file) => {
                      setLogo(file)
                      setRemoved((r) => ({ ...r, logo: false }))
                    }}
                    onRemove={() => {
                      setLogo(null)
                      setRemoved((r) => ({ ...r, logo: true }))
                    }}
                  />
                  <p className="text-xs text-ink-muted">
                    Shown at the top-left of every public page and used as the social share image when a
                    page has no photo of its own.
                  </p>
                </div>

                <div className="space-y-1.5">
                  <p className="text-sm font-medium text-ink">Favicon</p>
                  <ImageField
                    id="favicon"
                    aspect={1}
                    file={favicon}
                    existingUrl={removed.favicon ? null : settings?.favicon?.url}
                    hint="Square icon (32×32 or larger) · up to 10MB"
                    onFile={(file) => {
                      setFavicon(file)
                      setRemoved((r) => ({ ...r, favicon: false }))
                    }}
                    onRemove={() => {
                      setFavicon(null)
                      setRemoved((r) => ({ ...r, favicon: true }))
                    }}
                  />
                  <p className="text-xs text-ink-muted">
                    The small icon shown in the browser tab and on bookmarks.
                  </p>
                </div>
              </div>
            </Card>
          </div>
        ) : null}

        {tab === 'contact' ? (
          <div className="grid gap-6">
            <Card title="Contact details" subtitle="How patients can reach the clinic.">
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  id="primary_phone"
                  label="Primary phone"
                  hint="Shown first in the header menu and footer."
                  placeholder="+855 12 345 678"
                  value={form.primary_phone}
                  onChange={set('primary_phone')}
                />
                <TextField
                  id="secondary_phone"
                  label="Secondary phone"
                  hint="Appended after the primary number in the footer."
                  placeholder="+855 12 345 679"
                  value={form.secondary_phone}
                  onChange={set('secondary_phone')}
                />
                <TextField
                  id="email"
                  label="Email"
                  hint="Used for the mailto link in the footer and on the contact page."
                  type="email"
                  placeholder="clinic@royalcare.example"
                  value={form.email}
                  onChange={set('email')}
                  className="sm:col-span-2"
                />
              </div>
            </Card>

            <Card title="Location & hours" subtitle="Where to find the clinic and when it is open.">
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  id="address"
                  label="Address"
                  hint="Shown on the contact page and in the footer."
                  value={form.address}
                  onChange={set('address')}
                  className="sm:col-span-2"
                />
                <TextField
                  id="business_hours"
                  label="Business hours"
                  multiline
                  rows={3}
                  hint="One line per entry, e.g. Mon–Fri: 8:00am – 6:00pm"
                  value={form.business_hours}
                  onChange={set('business_hours')}
                />
                <TextField
                  id="map_embed_url"
                  label="Map embed URL"
                  multiline
                  rows={3}
                  hint="Paste a Google Maps embed URL, or a full <iframe> embed code."
                  placeholder="https://www.google.com/maps/embed?pb=…"
                  value={form.map_embed_url}
                  onChange={set('map_embed_url')}
                />
              </div>
            </Card>
          </div>
        ) : null}

        {tab === 'social' ? (
          <Card
            title="Social links"
            subtitle="Leave a field empty to hide that icon from the footer."
          >
            <div className="grid gap-4 sm:grid-cols-2">
              {SOCIAL_FIELDS.map((field) => (
                <TextField
                  key={field.key}
                  id={field.key}
                  label={field.label}
                  placeholder={field.placeholder}
                  value={form[field.key]}
                  onChange={set(field.key)}
                  className={field.key === 'youtube_url' ? 'sm:col-span-2' : undefined}
                />
              ))}
            </div>

            {enabledSocials.length > 0 ? (
              <div className="mt-5 rounded-xl border border-line bg-canvas/60 px-4 py-3">
                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                  Will appear in the footer
                </p>
                <div className="mt-2 flex flex-wrap gap-2">
                  {enabledSocials.map((field) => (
                    <span
                      key={field.key}
                      className="rounded-full bg-surface px-3 py-1 text-xs font-medium text-ink ring-1 ring-line"
                    >
                      {field.label}
                    </span>
                  ))}
                </div>
              </div>
            ) : (
              <p className="mt-5 rounded-xl border border-dashed border-line px-4 py-4 text-center text-sm text-ink-muted">
                No social links yet — the footer hides the social row entirely.
              </p>
            )}
          </Card>
        ) : null}

        {tab === 'seo' ? (
          <div className="grid gap-6">
            <Card
              title="Default SEO"
              subtitle="Used on every page that does not set its own title or description."
            >
              <div className="space-y-4">
                <TextField
                  id="seo_title"
                  label="SEO title"
                  hint="The browser tab title and the headline in search results."
                  value={form.seo_title}
                  onChange={set('seo_title')}
                />
                <TextField
                  id="seo_description"
                  label="SEO description"
                  multiline
                  rows={3}
                  hint="The snippet under the headline in search results, and the social share text."
                  value={form.seo_description}
                  onChange={set('seo_description')}
                />
                <TextField
                  id="seo_keywords"
                  label="SEO keywords"
                  hint="Comma separated."
                  value={form.seo_keywords}
                  onChange={set('seo_keywords')}
                />
              </div>

              <div className="mt-5 rounded-xl border border-line bg-canvas/60 p-4">
                <p className="text-xs font-semibold uppercase tracking-wide text-ink-muted">
                  Search result preview
                </p>
                <div className="mt-3 rounded-lg bg-white px-4 py-3 shadow-sm ring-1 ring-slate-200">
                  <p className="truncate text-[19px] leading-tight text-[#1a0dab]">
                    {form.seo_title || form.site_name || 'RoyalCare Clinic'}
                  </p>
                  <p className="mt-0.5 truncate text-xs text-[#4d5156]">
                    {PREVIEW_DOMAIN} › home
                  </p>
                  <p className="mt-1 line-clamp-2 text-sm text-[#4d5156]">
                    {form.seo_description ||
                      'Add an SEO description to control the snippet shown under your headline in search results.'}
                  </p>
                </div>
                <p className="mt-2 text-xs text-ink-muted">
                  Individual pages can override these defaults from their own editor in{' '}
                  <Link to="/website/pages" className="font-medium text-brand hover:underline">
                    Pages
                  </Link>
                  .
                </p>
              </div>
            </Card>
          </div>
        ) : null}
      </div>

      <div className="sticky bottom-0 z-30 -mx-4 mt-6 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="flex items-center gap-2 text-sm">
            {errorCount > 0 ? (
              <>
                <AlertTriangle className="size-4 text-red-500" aria-hidden />
                <span className="text-red-600">
                  {errorCount} {errorCount === 1 ? 'field needs' : 'fields need'} attention before saving.
                </span>
              </>
            ) : isDirty ? (
              <>
                <AlertTriangle className="size-4 text-amber-500" aria-hidden />
                <span className="text-ink-muted">You have unsaved changes.</span>
              </>
            ) : (
              <>
                <CheckCircle2 className="size-4 text-emerald-500" aria-hidden />
                <span className="text-ink-muted">All changes saved.</span>
              </>
            )}
          </p>

          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={discard}
              disabled={!isDirty || saving}
            >
              <RotateCcw className="size-4" aria-hidden />
              Discard
            </Button>
            <Button type="submit" loading={saving} disabled={errorCount > 0}>
              <Save className="size-4" aria-hidden />
              Save changes
            </Button>
          </div>
        </div>
      </div>

      <ConfirmDialog
        open={blocker.state === 'blocked'}
        onClose={() => blocker.reset?.()}
        onConfirm={() => blocker.proceed?.()}
        title="Leave without saving?"
        message="Your changes to the website settings will be lost."
        confirmLabel="Leave page"
      />
    </form>
  )
}
