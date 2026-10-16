const CURRENCY_FORMAT = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'USD',
  minimumFractionDigits: 2,
})

const DATE_FORMAT = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
})

const DATE_TIME_FORMAT = new Intl.DateTimeFormat('en-US', {
  year: 'numeric',
  month: 'short',
  day: 'numeric',
  hour: 'numeric',
  minute: '2-digit',
})

const TIME_FORMAT = new Intl.DateTimeFormat('en-US', {
  hour: 'numeric',
  minute: '2-digit',
})

export function formatCurrency(value) {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? CURRENCY_FORMAT.format(n) : '—'
}

export function formatNumber(value) {
  const n = Number(value ?? 0)
  return Number.isFinite(n) ? n.toLocaleString('en-US') : '—'
}

export function formatDate(value) {
  const d = toDate(value)
  if (!d) return '—'
  return DATE_FORMAT.format(d)
}

export function formatDateTime(value) {
  const d = toDate(value)
  if (!d) return '—'
  return DATE_TIME_FORMAT.format(d)
}

const RELATIVE_FORMAT = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })

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
  let duration = (d.getTime() - Date.now()) / 1000
  for (const division of RELATIVE_DIVISIONS) {
    if (Math.abs(duration) < division.amount) {
      return RELATIVE_FORMAT.format(Math.round(duration), division.unit)
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
  return TIME_FORMAT.format(d)
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