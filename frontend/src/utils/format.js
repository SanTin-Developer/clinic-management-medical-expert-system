let currentLanguage = 'en'

/**
 * Keep date, time and number formatting in sync with the active interface
 * language. The language provider calls this before rendering a new language.
 */
export function setFormatLocale(language) {
  currentLanguage = language === 'km' ? 'km' : 'en'
}

export function getFormatLocale() {
  return currentLanguage === 'km' ? 'km-KH' : 'en-US'
}

const KHMER_MONTHS = [
  'មករា',
  'កុម្ភៈ',
  'មីនា',
  'មេសា',
  'ឧសភា',
  'មិថុនា',
  'កក្កដា',
  'សីហា',
  'កញ្ញា',
  'តុលា',
  'វិច្ឆិកា',
  'ធ្នូ',
]

const KHMER_WEEKDAYS = ['អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍']

const KHMER_RELATIVE_UNITS = [
  { milliseconds: 60_000, label: 'វិនាទី' },
  { milliseconds: 3_600_000, label: 'នាទី' },
  { milliseconds: 86_400_000, label: 'ម៉ោង' },
  { milliseconds: 604_800_000, label: 'ថ្ងៃ' },
  { milliseconds: 2_592_000_000, label: 'សប្តាហ៍' },
  { milliseconds: 31_536_000_000, label: 'ខែ' },
  { milliseconds: Number.POSITIVE_INFINITY, label: 'ឆ្នាំ' },
]

function formatKhmerDate(date) {
  return `${date.getDate()} ${KHMER_MONTHS[date.getMonth()]} ${date.getFullYear()}`
}

function formatKhmerClock(date) {
  const hours = date.getHours()
  const minutes = String(date.getMinutes()).padStart(2, '0')
  const period = hours >= 12 ? 'ល្ងាច' : 'ព្រឹក'
  return `${hours % 12 || 12}:${minutes} ${period}`
}

const FORMATTERS = {
  en: {
    currency: new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
    }),
    date: new Intl.DateTimeFormat('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }),
    dateTime: new Intl.DateTimeFormat('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    }),
    time: new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
    }),
    relative: new Intl.RelativeTimeFormat('en', { numeric: 'auto' }),
  },
  km: {
    currency: new Intl.NumberFormat('km-KH', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
    }),
    date: new Intl.DateTimeFormat('km-KH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    }),
    dateTime: new Intl.DateTimeFormat('km-KH', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    }),
    time: new Intl.DateTimeFormat('km-KH', {
      hour: 'numeric',
      minute: '2-digit',
    }),
    relative: new Intl.RelativeTimeFormat('km', { numeric: 'auto' }),
  },
}

function activeFormatters() {
  return FORMATTERS[currentLanguage] ?? FORMATTERS.en
}

export function formatCurrency(value) {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? activeFormatters().currency.format(n) : '—'
}

export function formatNumber(value) {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? n.toLocaleString(getFormatLocale()) : '—'
}

export function formatDate(value) {
  const d = toDate(value)
  if (!d) return '—'
  if (currentLanguage === 'km') return formatKhmerDate(d)
  return activeFormatters().date.format(d)
}

export function formatLongDate(value) {
  const d = toDate(value)
  if (!d) return '—'
  if (currentLanguage === 'km') {
    return `${KHMER_WEEKDAYS[d.getDay()]} ${formatKhmerDate(d)}`
  }
  return new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(d)
}

export function formatShortDate(value) {
  const d = toDate(value)
  if (!d) return '—'
  if (currentLanguage === 'km') {
    return `${d.getDate()} ${KHMER_MONTHS[d.getMonth()]}`
  }
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(d)
}

export function formatMonth(value) {
  const d = toDate(value)
  if (!d) return '—'
  if (currentLanguage === 'km') return KHMER_MONTHS[d.getMonth()]
  return new Intl.DateTimeFormat('en-US', { month: 'short' }).format(d)
}

export function formatDateTime(value) {
  const d = toDate(value)
  if (!d) return '—'
  if (currentLanguage === 'km') {
    return `${formatKhmerDate(d)}, ${formatKhmerClock(d)}`
  }
  return activeFormatters().dateTime.format(d)
}

const RELATIVE_DIVISIONS = [
  { amount: 60, unit: 'second' },
  { amount: 60, unit: 'minute' },
  { amount: 24, unit: 'hour' },
  { amount: 7, unit: 'day' },
  { amount: 4.34524, unit: 'week' },
  { amount: 12, unit: 'month' },
  { amount: Number.POSITIVE_INFINITY, unit: 'year' },
]

export function formatRelativeTime(value) {
  const d = toDate(value)
  if (!d) return '—'
  const difference = d.getTime() - Date.now()

  if (currentLanguage === 'km') {
    const absolute = Math.abs(difference)
    if (absolute < 60_000) return 'ឥឡូវនេះ'
    const unit = KHMER_RELATIVE_UNITS.find((item) => absolute < item.milliseconds)
    const amount = Math.round(absolute / (unit?.milliseconds ?? 1))
    return `${amount} ${unit?.label ?? 'ឆ្នាំ'}${difference < 0 ? 'មុន' : 'ក្រោយ'}`
  }

  let duration = difference / 1000
  for (const division of RELATIVE_DIVISIONS) {
    if (Math.abs(duration) < division.amount) {
      return activeFormatters().relative.format(Math.round(duration), division.unit)
    }
    duration /= division.amount
  }
  return '—'
}

export function formatTime(value) {
  if (!value) return '—'
  const parts = String(value).split(':').map(Number)
  if (parts.length < 2 || Number.isNaN(parts[0])) return String(value)
  const d = new Date()
  d.setHours(parts[0], parts[1] ?? 0, 0, 0)
  if (currentLanguage === 'km') return formatKhmerClock(d)
  return activeFormatters().time.format(d)
}

function toDate(value) {
  if (!value) return null
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? null : d
}

export function patientFullName(patient) {
  if (!patient) return '—'
  return `${patient.first_name ?? ''} ${patient.last_name ?? ''}`.trim() || '—'
}

export function ageFromDateOfBirth(dob) {
  const d = toDate(dob)
  if (!d) return null
  const now = new Date()
  let age = now.getFullYear() - d.getFullYear()
  const m = now.getMonth() - d.getMonth()
  if (m < 0 || (m === 0 && now.getDate() < d.getDate())) {
    age -= 1
  }
  return age
}

export const GENDER_LABELS = {
  male: 'Male',
  female: 'Female',
  other: 'Other',
}

export function formatBytes(bytes) {
  const n = Number(bytes ?? 0)
  if (!Number.isFinite(n) || n <= 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const i = Math.min(Math.floor(Math.log(n) / Math.log(1024)), units.length - 1)
  return `${(n / 1024 ** i).toFixed(i === 0 ? 0 : 1)} ${units[i]}`
}

export function toIsoDate(value) {
  const d = toDate(value)
  return d ? d.toISOString().slice(0, 10) : ''
}

export function truncate(value, length = 60) {
  const s = String(value ?? '')
  return s.length > length ? `${s.slice(0, length - 1)}…` : s
}

export function joinAddress(record) {
  if (!record) return '—'
  const parts = [record.address, record.phone_number, record.email].filter(Boolean)
  return parts.length ? parts.join(' · ') : '—'
}