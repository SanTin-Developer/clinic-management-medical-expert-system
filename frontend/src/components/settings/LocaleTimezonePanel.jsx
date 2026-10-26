import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Clock, Globe, Loader2 } from 'lucide-react'
import Card from '../ui/Card.jsx'
import Field from '../ui/Field.jsx'
import Select from '../ui/Select.jsx'
import Button from '../ui/Button.jsx'
import { getUserPreferences, updateUserPreferences } from '../../api/userSettings.js'
import { LANGUAGES, translate } from '../../i18n/index.js'
import { useAuth } from '../../hooks/useAuth.js'
import { useLanguage } from '../../hooks/useLanguage.js'
import { cn } from '../../utils/cn.js'

function getTimezones() {
  try {
    if (typeof Intl.supportedValuesOf === 'function') {
      return Intl.supportedValuesOf('timeZone')
    }
  } catch {
    // Ignore missing Intl support and use the fallback list.
  }
  return [
    'UTC',
    'Asia/Phnom_Penh',
    'Asia/Bangkok',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Kolkata',
    'Asia/Dubai',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
    'America/Los_Angeles',
    'Australia/Sydney',
  ]
}

export default function LocaleTimezonePanel() {
  const { refreshUser } = useAuth()
  const { language, setLanguage, t } = useLanguage()
  const prefs = useQuery({
    queryKey: ['userPreferences'],
    queryFn: getUserPreferences,
  })

  const [timezone, setTimezone] = useState('')
  const [saving, setSaving] = useState(false)
  const [savingLanguage, setSavingLanguage] = useState(false)

  useEffect(() => {
    if (prefs.data) {
      setTimezone(prefs.data.timezone ?? '')
    }
  }, [prefs.data])

  const timezones = getTimezones()

  const handleSave = async () => {
    setSaving(true)
    try {
      await updateUserPreferences({
        timezone: timezone || null,
      })
      toast.success(t('Timezone updated.'))
      prefs.refetch()
    } catch (err) {
      toast.error(err.message)
    } finally {
      setSaving(false)
    }
  }

  const handleLanguageChange = async (nextLanguage) => {
    if (nextLanguage === language || savingLanguage) return

    const previousLanguage = language
    setLanguage(nextLanguage)
    setSavingLanguage(true)

    try {
      await updateUserPreferences({ locale: nextLanguage })
      toast.success(translate(nextLanguage, 'Language updated.'))
      prefs.refetch()
      refreshUser?.().catch(() => {
        // The local selection is already persisted; a stale auth cache is non-fatal.
      })
    } catch (err) {
      setLanguage(previousLanguage)
      toast.error(err.message)
    } finally {
      setSavingLanguage(false)
    }
  }

  const isDirty = (prefs.data?.timezone ?? '') !== timezone

  return (
    <Card
      title={t('Timezone & Language')}
      subtitle={t('Set your preferred timezone and language.')}
      actions={
        <Button size="sm" onClick={handleSave} loading={saving} disabled={!isDirty || saving}>
          {t('Save')}
        </Button>
      }
    >
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label={t('Timezone')} htmlFor="timezone" hint={t('Used for displaying dates and times.')}>
          <Select id="timezone" value={timezone} onChange={(e) => setTimezone(e.target.value)}>
            <option value="">{t('System default')}</option>
            {timezones.map((tz) => (
              <option key={tz} value={tz}>
                {tz}
              </option>
            ))}
          </Select>
        </Field>
        <Field
          label={t('Language')}
          hint={t('Changes apply immediately and are saved to your account.')}
        >
          <div
            className="grid grid-cols-2 gap-1 rounded-xl border border-line bg-canvas p-1"
            role="group"
            aria-label={t('Language')}
          >
            {LANGUAGES.map((option) => {
              const active = language === option.value
              const optionLabel = option.value === 'km' ? option.nativeLabel : t(option.label)

              return (
                <button
                  key={option.value}
                  type="button"
                  aria-pressed={active}
                  disabled={savingLanguage}
                  onClick={() => handleLanguageChange(option.value)}
                  className={cn(
                    'flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/50 disabled:cursor-wait',
                    active
                      ? 'bg-surface text-ink shadow-sm ring-1 ring-line'
                      : 'text-ink-muted hover:bg-surface/60 hover:text-ink',
                  )}
                >
                  {optionLabel}
                  {savingLanguage && active ? (
                    <Loader2 className="size-3.5 animate-spin" aria-hidden />
                  ) : null}
                </button>
              )
            })}
          </div>
        </Field>
      </div>
      <p className="mt-3 flex items-center gap-2 text-xs text-ink-muted">
        <Clock className="size-3.5" aria-hidden />
        <span>{t('Some features may still use the system timezone until fully updated.')}</span>
        <Globe className="size-3.5" aria-hidden />
      </p>
    </Card>
  )
}
