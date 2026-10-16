import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { useQuery } from '@tanstack/react-query'
import { Save } from 'lucide-react'
import PageHeader from '../../components/ui/PageHeader.jsx'
import Card from '../../components/ui/Card.jsx'
import Button from '../../components/ui/Button.jsx'
import Field from '../../components/ui/Field.jsx'
import Input from '../../components/ui/Input.jsx'
import Textarea from '../../components/ui/Textarea.jsx'
import { Spinner } from '../../components/ui/Skeleton.jsx'
import { ErrorState } from '../../components/ui/State.jsx'
import ImageField from '../../components/website/ImageField.jsx'
import { websiteAdminApi } from '../../api/websiteAdmin.js'

const EMPTY = {
  site_name: '',
  tagline: '',
  hero_title: '',
  hero_subtitle: '',
  about_summary: '',
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

export default function WebsiteSettingsPage() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['website', 'admin', 'settings'],
    queryFn: websiteAdminApi.getSettings,
  })

  const [form, setForm] = useState(EMPTY)
  const [logo, setLogo] = useState(null)
  const [favicon, setFavicon] = useState(null)
  const [hero, setHero] = useState(null)
  const [removed, setRemoved] = useState({ logo: false, favicon: false, hero: false })
  const [saving, setSaving] = useState(false)

  const settings = data?.data

  useEffect(() => {
    if (!settings) return
    const next = { ...EMPTY }
    TEXT_FIELDS.forEach((key) => {
      next[key] = settings[key] ?? ''
    })
    setForm(next)
    setLogo(null)
    setFavicon(null)
    setHero(null)
    setRemoved({ logo: false, favicon: false, hero: false })
  }, [settings])

  const set = (key) => (value) => setForm((f) => ({ ...f, [key]: value }))

  const submit = async (event) => {
    event.preventDefault()
    setSaving(true)
    try {
      const payload = { ...form }
      if (logo) payload.logo = logo
      else if (removed.logo) payload.remove_logo = true
      if (favicon) payload.favicon = favicon
      else if (removed.favicon) payload.remove_favicon = true
      if (hero) payload.hero_image = hero
      else if (removed.hero) payload.remove_hero_image = true

      await websiteAdminApi.updateSettings(payload)
      toast.success('Website settings updated.')
      setLogo(null)
      setFavicon(null)
      setHero(null)
      setRemoved({ logo: false, favicon: false, hero: false })
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
        <Spinner label="Loading settings…" />
      </div>
    )
  }

  if (isError) {
    return <ErrorState onRetry={refetch} />
  }

  return (
    <form onSubmit={submit}>
      <PageHeader
        title="Website Settings"
        description="Branding, contact details and SEO for the public website."
        actions={
          <Button type="submit" loading={saving}>
            <Save className="size-4" aria-hidden />
            Save changes
          </Button>
        }
      />

      <div className="grid gap-6 lg:grid-cols-2">
        <Card title="Branding">
          <div className="space-y-4">
            <Field label="Site name" htmlFor="site_name">
              <Input id="site_name" value={form.site_name} onChange={(e) => set('site_name')(e.target.value)} />
            </Field>
            <Field label="Tagline" htmlFor="tagline" hint="Short phrase shown in the hero and footer.">
              <Input id="tagline" value={form.tagline} onChange={(e) => set('tagline')(e.target.value)} />
            </Field>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field label="Logo" htmlFor="logo" hint="PNG or SVG recommended.">
                <ImageField
                  id="logo"
                  file={logo}
                  existingUrl={removed.logo ? null : settings?.logo?.url}
                  onFile={(file) => {
                    setLogo(file)
                    setRemoved((r) => ({ ...r, logo: false }))
                  }}
                  onRemove={() => {
                    setLogo(null)
                    setRemoved((r) => ({ ...r, logo: true }))
                  }}
                />
              </Field>
              <Field label="Favicon" htmlFor="favicon" hint="Square icon (32×32 or larger).">
                <ImageField
                  id="favicon"
                  file={favicon}
                  existingUrl={removed.favicon ? null : settings?.favicon?.url}
                  onFile={(file) => {
                    setFavicon(file)
                    setRemoved((r) => ({ ...r, favicon: false }))
                  }}
                  onRemove={() => {
                    setFavicon(null)
                    setRemoved((r) => ({ ...r, favicon: true }))
                  }}
                />
              </Field>
            </div>
          </div>
        </Card>

        <Card title="Homepage hero">
          <div className="space-y-4">
            <Field label="Hero title" htmlFor="hero_title">
              <Input id="hero_title" value={form.hero_title} onChange={(e) => set('hero_title')(e.target.value)} />
            </Field>
            <Field label="Hero subtitle" htmlFor="hero_subtitle">
              <Textarea id="hero_subtitle" rows={2} value={form.hero_subtitle} onChange={(e) => set('hero_subtitle')(e.target.value)} />
            </Field>
            <Field label="Hero image" htmlFor="hero_image" hint="Large banner image (1600px wide or more).">
              <ImageField
                id="hero_image"
                file={hero}
                existingUrl={removed.hero ? null : settings?.heroMedia?.url}
                onFile={(file) => {
                  setHero(file)
                  setRemoved((r) => ({ ...r, hero: false }))
                }}
                onRemove={() => {
                  setHero(null)
                  setRemoved((r) => ({ ...r, hero: true }))
                }}
              />
            </Field>
            <Field label="About summary" htmlFor="about_summary" hint="Shown on the homepage and footer.">
              <Textarea id="about_summary" rows={4} value={form.about_summary} onChange={(e) => set('about_summary')(e.target.value)} />
            </Field>
          </div>
        </Card>

        <Card title="Contact information">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Field label="Primary phone" htmlFor="primary_phone">
              <Input id="primary_phone" value={form.primary_phone} onChange={(e) => set('primary_phone')(e.target.value)} />
            </Field>
            <Field label="Secondary phone" htmlFor="secondary_phone">
              <Input id="secondary_phone" value={form.secondary_phone} onChange={(e) => set('secondary_phone')(e.target.value)} />
            </Field>
            <Field label="Email" htmlFor="email" className="sm:col-span-2">
              <Input id="email" type="email" value={form.email} onChange={(e) => set('email')(e.target.value)} />
            </Field>
            <Field label="Address" htmlFor="address" className="sm:col-span-2">
              <Input id="address" value={form.address} onChange={(e) => set('address')(e.target.value)} />
            </Field>
            <Field label="Map embed URL" htmlFor="map_embed_url" className="sm:col-span-2" hint="Google Maps embed URL or full <iframe> embed code.">
              <Textarea id="map_embed_url" rows={2} value={form.map_embed_url} onChange={(e) => set('map_embed_url')(e.target.value)} />
            </Field>
            <Field label="Business hours" htmlFor="business_hours" className="sm:col-span-2" hint="One line per entry, e.g. Mon–Fri: 8:00am – 6:00pm">
              <Textarea id="business_hours" rows={3} value={form.business_hours} onChange={(e) => set('business_hours')(e.target.value)} />
            </Field>
          </div>
        </Card>

        <div className="space-y-6">
          <Card title="Social links">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field label="Facebook" htmlFor="facebook_url">
                <Input id="facebook_url" value={form.facebook_url} onChange={(e) => set('facebook_url')(e.target.value)} placeholder="https://facebook.com/…" />
              </Field>
              <Field label="Twitter / X" htmlFor="twitter_url">
                <Input id="twitter_url" value={form.twitter_url} onChange={(e) => set('twitter_url')(e.target.value)} placeholder="https://x.com/…" />
              </Field>
              <Field label="Instagram" htmlFor="instagram_url">
                <Input id="instagram_url" value={form.instagram_url} onChange={(e) => set('instagram_url')(e.target.value)} placeholder="https://instagram.com/…" />
              </Field>
              <Field label="LinkedIn" htmlFor="linkedin_url">
                <Input id="linkedin_url" value={form.linkedin_url} onChange={(e) => set('linkedin_url')(e.target.value)} placeholder="https://linkedin.com/…" />
              </Field>
              <Field label="YouTube" htmlFor="youtube_url" className="sm:col-span-2">
                <Input id="youtube_url" value={form.youtube_url} onChange={(e) => set('youtube_url')(e.target.value)} placeholder="https://youtube.com/…" />
              </Field>
            </div>
          </Card>

          <Card title="SEO defaults">
            <div className="space-y-4">
              <Field label="SEO title" htmlFor="seo_title">
                <Input id="seo_title" value={form.seo_title} onChange={(e) => set('seo_title')(e.target.value)} />
              </Field>
              <Field label="SEO description" htmlFor="seo_description">
                <Textarea id="seo_description" rows={3} value={form.seo_description} onChange={(e) => set('seo_description')(e.target.value)} />
              </Field>
              <Field label="SEO keywords" htmlFor="seo_keywords" hint="Comma separated.">
                <Input id="seo_keywords" value={form.seo_keywords} onChange={(e) => set('seo_keywords')(e.target.value)} />
              </Field>
            </div>
          </Card>
        </div>
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
