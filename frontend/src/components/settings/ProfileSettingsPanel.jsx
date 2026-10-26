import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Trash2, User } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Field from '../ui/Field.jsx'
import Input from '../ui/Input.jsx'
import Button from '../ui/Button.jsx'
import { getUserPreferences, updateUserProfile, updateUserAvatar, removeUserAvatar } from '../../api/userSettings.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useLanguage } from '../../hooks/useLanguage.js'

export default function ProfileSettingsPanel() {
  const { user, refreshUser } = useAuth()
  const { t } = useLanguage()
  const prefs = useQuery({
    queryKey: ['userPreferences'],
    queryFn: getUserPreferences,
  })

  const [name, setName] = useState('')
  const [username, setUsername] = useState('')
  const [phone, setPhone] = useState('')
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [removing, setRemoving] = useState(false)
  const fileInputRef = useRef(null)

  useEffect(() => {
    if (prefs.data) {
      setName(prefs.data.name ?? user?.name ?? '')
      setUsername(prefs.data.username ?? user?.username ?? '')
      setPhone(prefs.data.phone ?? user?.phone ?? '')
    } else if (user) {
      setName(user.name ?? '')
      setUsername(user.username ?? '')
      setPhone(user.phone ?? '')
    }
  }, [prefs.data, user])

  const avatarUrl = prefs.data?.avatar_url ?? user?.avatar_url

  const handleSave = async () => {
    setSaving(true)
    try {
      await updateUserProfile({ name, username, phone: phone || null })
      toast.success(t('Profile updated.'))
      prefs.refetch()
      if (refreshUser) {
        await refreshUser()
      }
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const handleFileChange = async (e) => {
    const file = e.target.files?.[0]
    if (!file) return
    setUploading(true)
    try {
      await updateUserAvatar(file)
      toast.success(t('Profile picture updated.'))
      prefs.refetch()
      if (refreshUser) {
        await refreshUser()
      }
    } catch (err) {
      toast.error(err.message)
    } finally {
      setUploading(false)
      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
    }
  }

  const handleRemove = async () => {
    setRemoving(true)
    try {
      await removeUserAvatar()
      toast.success(t('Profile picture removed.'))
      prefs.refetch()
      if (refreshUser) {
        await refreshUser()
      }
    } catch (err) {
      toast.error(err.message)
    } finally {
      setRemoving(false)
    }
  }

  const isDirty =
    (prefs.data?.name ?? user?.name ?? '') !== name ||
    (prefs.data?.username ?? user?.username ?? '') !== username ||
    (prefs.data?.phone ?? user?.phone ?? '') !== phone

  const initials = (name || user?.name || ' ')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? '')
    .join('')

  return (
    <Card title={t('Profile details')} subtitle={t('Update your personal information and profile picture.')}>
      <div className="space-y-6">
        <div className="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
          <div className="relative">
            <div className="grid size-24 place-items-center overflow-hidden rounded-full bg-brand text-2xl font-bold text-white ring-4 ring-brand-soft">
              {avatarUrl ? (
                <img src={avatarUrl} alt={name || t('Profile')} className="size-full object-cover" />
              ) : initials ? (
                initials
              ) : (
                <User className="size-10" aria-hidden />
              )}
            </div>
            <button
              type="button"
              onClick={() => fileInputRef.current?.click()}
              disabled={uploading}
              className="absolute bottom-0 right-0 grid size-9 place-items-center rounded-full bg-surface text-ink shadow-sm ring-1 ring-line transition-colors hover:bg-canvas"
              aria-label={t('Change profile picture')}
            >
              <Camera className="size-4" aria-hidden />
            </button>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={handleFileChange}
            />
          </div>
          <div className="flex-1 text-center sm:text-left">
            <p className="text-lg font-semibold text-ink">{name || user?.name}</p>
            <p className="text-sm text-ink-muted">{user?.email}</p>
            <div className="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
              <Button size="sm" variant="outline" onClick={() => fileInputRef.current?.click()} loading={uploading}>
                {t('Upload photo')}
              </Button>
              {avatarUrl ? (
                <Button size="sm" variant="ghost" onClick={handleRemove} loading={removing}>
                  <Trash2 className="size-4" aria-hidden /> {t('Remove')}
                </Button>
              ) : null}
            </div>
            <p className="mt-2 text-xs text-ink-muted">{t('JPG, PNG, WebP or GIF. Max 5MB.')}</p>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Field label={t('Full name')} htmlFor="profile-name" required>
            <Input id="profile-name" value={name} onChange={(e) => setName(e.target.value)} />
          </Field>
          <Field label={t('Username')} htmlFor="profile-username">
            <Input id="profile-username" value={username} onChange={(e) => setUsername(e.target.value)} />
          </Field>
          <Field label={t('Phone number')} htmlFor="profile-phone" className="sm:col-span-2">
            <Input id="profile-phone" value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="+855 12 345 678" />
          </Field>
        </div>

        <div className="flex justify-end">
          <Button onClick={handleSave} loading={saving} disabled={!isDirty || saving}>
            {t('Save changes')}
          </Button>
        </div>
      </div>
    </Card>
  )
}
