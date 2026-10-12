const meta = (label, tone) => ({ label, tone })

export const APPOINTMENT_STATUS = {
  scheduled: meta('Scheduled', 'blue'),
  confirmed: meta('Confirmed', 'pink'),
  in_progress: meta('In progress', 'navy'),
  completed: meta('Completed', 'green'),
  cancelled: meta('Cancelled', 'red'),
  no_show: meta('No show', 'amber'),
}

export const APPOINTMENT_STATUS_OPTIONS = Object.entries(APPOINTMENT_STATUS).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const PRESCRIPTION_STATUS = {
  pending: meta('Pending', 'amber'),
  dispensed: meta('Dispensed', 'green'),
  cancelled: meta('Cancelled', 'red'),
}

export const INVOICE_STATUS = {
  unpaid: meta('Unpaid', 'red'),
  partially_paid: meta('Partially paid', 'amber'),
  paid: meta('Paid', 'green'),
  cancelled: meta('Cancelled', 'gray'),
}

export const PAYMENT_METHODS = {
  cash: meta('Cash', 'green'),
  card: meta('Card', 'blue'),
  bank_transfer: meta('Bank transfer', 'pink'),
  insurance: meta('Insurance', 'navy'),
  other: meta('Other', 'gray'),
}

export const PAYMENT_METHOD_OPTIONS = Object.entries(PAYMENT_METHODS).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const STOCK_MOVEMENT_TYPE = {
  in: meta('Stock in', 'green'),
  out: meta('Stock out', 'red'),
  adjustment: meta('Adjustment', 'amber'),
}

export const DOCUMENT_CATEGORIES = {
  'lab-result': meta('Lab result', 'blue'),
  imaging: meta('Imaging', 'pink'),
  referral: meta('Referral', 'navy'),
  consent: meta('Consent', 'amber'),
  insurance: meta('Insurance', 'green'),
  other: meta('Other', 'gray'),
}

export const DOCUMENT_CATEGORY_OPTIONS = Object.entries(
  DOCUMENT_CATEGORIES,
).map(([value, m]) => ({ value, label: m.label }))

export const DISEASE_SEVERITY = {
  mild: meta('Mild', 'blue'),
  moderate: meta('Moderate', 'amber'),
  severe: meta('Severe', 'red'),
  critical: meta('Critical', 'navy'),
}

export const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']

export const GENDER_OPTIONS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' },
]

export const ROLES = [
  { value: 'Admin', label: 'Admin' },
  { value: 'Doctor', label: 'Doctor' },
  { value: 'Nurse', label: 'Nurse' },
  { value: 'Receptionist', label: 'Receptionist' },
  { value: 'Pharmacist', label: 'Pharmacist' },
  { value: 'Patient', label: 'Patient' },
]

export function statusBadgeFor(map, value) {
  return map[value] ?? meta(value ?? '—', 'gray')
}