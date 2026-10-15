const meta = (label, tone) => ({ label, tone })

export const APPOINTMENT_STATUS = {
  scheduled: meta('Scheduled', 'blue'),
  confirmed: meta('Confirmed', 'brand'),
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
  bank_transfer: meta('Bank transfer', 'brand'),
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
  imaging: meta('Imaging', 'brand'),
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
  { value: 'Head Nurse', label: 'Head Nurse' },
  { value: 'Receptionist', label: 'Receptionist' },
  { value: 'Pharmacist', label: 'Pharmacist' },
  { value: 'Patient', label: 'Patient' },
]

export const EMPLOYMENT_STATUS = {
  employed: meta('Employed', 'green'),
  probation: meta('Probation', 'blue'),
  on_leave: meta('On leave', 'amber'),
  resigned: meta('Resigned', 'gray'),
  terminated: meta('Terminated', 'red'),
}

export const EMPLOYMENT_STATUS_OPTIONS = Object.entries(EMPLOYMENT_STATUS).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const EMPLOYMENT_TYPE = {
  full_time: meta('Full-time', 'brand'),
  part_time: meta('Part-time', 'blue'),
  contract: meta('Contract', 'amber'),
  intern: meta('Intern', 'navy'),
}

export const EMPLOYMENT_TYPE_OPTIONS = Object.entries(EMPLOYMENT_TYPE).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const STAFF_DOCUMENT_CATEGORIES = {
  cv: meta('CV / Resume', 'blue'),
  photo: meta('Staff Photo', 'navy'),
  certificate: meta('Certificate', 'brand'),
  license: meta('Medical License', 'green'),
  contract: meta('Employment Contract', 'amber'),
  other: meta('Other Document', 'gray'),
}

export const STAFF_DOCUMENT_CATEGORY_OPTIONS = Object.entries(
  STAFF_DOCUMENT_CATEGORIES,
).map(([value, m]) => ({ value, label: m.label }))

export const PAY_FREQUENCY_OPTIONS = [
  { value: 'monthly', label: 'Monthly' },
  { value: 'biweekly', label: 'Bi-weekly' },
  { value: 'hourly', label: 'Hourly' },
]

export const WARD_TYPES = {
  general: meta('General', 'brand'),
  icu: meta('ICU', 'red'),
  maternity: meta('Maternity', 'blue'),
  pediatric: meta('Pediatric', 'amber'),
  surgical: meta('Surgical', 'navy'),
  isolation: meta('Isolation', 'red'),
}

export const WARD_TYPE_OPTIONS = Object.entries(WARD_TYPES).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const ROOM_TYPES = {
  general: meta('General', 'gray'),
  private: meta('Private', 'brand'),
  semi_private: meta('Semi-private', 'blue'),
  isolation: meta('Isolation', 'red'),
}

export const ROOM_TYPE_OPTIONS = Object.entries(ROOM_TYPES).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const ROOM_TYPE_PRICES = {
  general: 15,
  semi_private: 30,
  private: 55,
  isolation: 80,
}

export const BED_STATUS = {
  available: meta('Available', 'green'),
  occupied: meta('Occupied', 'blue'),
  reserved: meta('Reserved', 'amber'),
  cleaning: meta('Cleaning', 'amber'),
  maintenance: meta('Maintenance', 'red'),
}

export const BED_STATUS_OPTIONS = Object.entries(BED_STATUS).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const ADMISSION_STATUS = {
  admitted: meta('Admitted', 'blue'),
  discharged: meta('Discharged', 'green'),
  transferred: meta('Transferred', 'amber'),
  cancelled: meta('Cancelled', 'red'),
}

export const NURSING_TASK_STATUS = {
  pending: meta('Pending', 'amber'),
  in_progress: meta('In progress', 'blue'),
  completed: meta('Completed', 'green'),
  cancelled: meta('Cancelled', 'red'),
}

export const NURSING_TASK_TYPES = {
  vital_signs: meta('Vital signs', 'blue'),
  medication: meta('Medication', 'brand'),
  wound_care: meta('Wound care', 'red'),
  mobility: meta('Mobility', 'navy'),
  hygiene: meta('Hygiene', 'gray'),
  observation: meta('Observation', 'amber'),
  procedure: meta('Procedure', 'navy'),
  education: meta('Education', 'green'),
  other: meta('Other', 'gray'),
}

export const NURSING_TASK_TYPE_OPTIONS = Object.entries(NURSING_TASK_TYPES).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const TASK_PRIORITY = {
  low: meta('Low', 'gray'),
  normal: meta('Normal', 'blue'),
  high: meta('High', 'amber'),
  urgent: meta('Urgent', 'red'),
}

export const TASK_PRIORITY_OPTIONS = Object.entries(TASK_PRIORITY).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const NURSING_TASK_OUTCOMES = {
  completed: meta('Fully completed', 'green'),
  partially_completed: meta('Partially completed', 'amber'),
  held: meta('Held', 'navy'),
  refused: meta('Refused', 'red'),
  not_completed: meta('Not completed', 'red'),
}

export const NURSING_TASK_OUTCOME_OPTIONS = Object.entries(NURSING_TASK_OUTCOMES).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const MEDICATION_STATUS = {
  scheduled: meta('Scheduled', 'amber'),
  administered: meta('Administered', 'green'),
  held: meta('Held', 'navy'),
  refused: meta('Refused', 'red'),
  missed: meta('Missed', 'red'),
  cancelled: meta('Cancelled', 'gray'),
}

export const MEDICATION_STATUS_OPTIONS = Object.entries(MEDICATION_STATUS).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const MEDICATION_ROUTES = {
  oral: meta('Oral', 'blue'),
  iv: meta('IV', 'red'),
  im: meta('IM', 'amber'),
  sc: meta('SC', 'navy'),
  topical: meta('Topical', 'green'),
  inhalation: meta('Inhalation', 'brand'),
  other: meta('Other', 'gray'),
}

export const MEDICATION_ROUTE_OPTIONS = Object.entries(MEDICATION_ROUTES).map(
  ([value, m]) => ({ value, label: m.label }),
)

export const ASSIGNMENT_STATUS = {
  active: meta('Active', 'green'),
  ended: meta('Ended', 'gray'),
}

export const ANNOUNCEMENT_PRIORITY = {
  normal: meta('Normal', 'blue'),
  high: meta('High', 'amber'),
  urgent: meta('Urgent', 'red'),
}

export const ANNOUNCEMENT_PRIORITY_OPTIONS = Object.entries(
  ANNOUNCEMENT_PRIORITY,
).map(([value, m]) => ({ value, label: m.label }))

export const ANNOUNCEMENT_AUDIENCE = {
  all: meta('Everyone', 'brand'),
  staff: meta('All staff', 'navy'),
  doctors: meta('Doctors', 'blue'),
  nurses: meta('Nurses', 'green'),
  receptionists: meta('Receptionists', 'amber'),
  pharmacists: meta('Pharmacists', 'navy'),
  patients: meta('Patients', 'gray'),
}

export const ANNOUNCEMENT_AUDIENCE_OPTIONS = Object.entries(
  ANNOUNCEMENT_AUDIENCE,
).map(([value, m]) => ({ value, label: m.label }))

export const LEAVE_TYPES = {
  annual: meta('Annual', 'brand'),
  sick: meta('Sick', 'red'),
  unpaid: meta('Unpaid', 'gray'),
  maternity: meta('Maternity', 'blue'),
  emergency: meta('Emergency', 'amber'),
  other: meta('Other', 'navy'),
}

export const LEAVE_TYPE_OPTIONS = Object.entries(LEAVE_TYPES).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const LEAVE_STATUS = {
  pending: meta('Pending', 'amber'),
  approved: meta('Approved', 'green'),
  rejected: meta('Rejected', 'red'),
  cancelled: meta('Cancelled', 'gray'),
}

export const LEAVE_STATUS_OPTIONS = Object.entries(LEAVE_STATUS).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const QUEUE_STATUS = {
  waiting: meta('Waiting', 'amber'),
  called: meta('Called', 'blue'),
  serving: meta('Serving', 'brand'),
  done: meta('Done', 'green'),
  skipped: meta('Skipped', 'navy'),
  cancelled: meta('Cancelled', 'gray'),
}

export const QUEUE_STATUS_OPTIONS = Object.entries(QUEUE_STATUS).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const QUEUE_PRIORITY = {
  normal: meta('Normal', 'blue'),
  urgent: meta('Urgent', 'red'),
}

export const QUEUE_PRIORITY_OPTIONS = Object.entries(QUEUE_PRIORITY).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const ATTENDANCE_STATUS = {
  present: meta('Present', 'green'),
  late: meta('Late', 'amber'),
  absent: meta('Absent', 'red'),
  on_leave: meta('On leave', 'blue'),
  holiday: meta('Holiday', 'navy'),
}

export const ATTENDANCE_STATUS_OPTIONS = Object.entries(ATTENDANCE_STATUS).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const SHIFT_TYPES = {
  day: meta('Day', 'blue'),
  evening: meta('Evening', 'amber'),
  night: meta('Night', 'navy'),
  on_call: meta('On call', 'brand'),
}

export const SHIFT_TYPE_OPTIONS = Object.entries(SHIFT_TYPES).map(([value, m]) => ({
  value,
  label: m.label,
}))

export const SHIFT_STATUS = {
  scheduled: meta('Scheduled', 'blue'),
  completed: meta('Completed', 'green'),
  cancelled: meta('Cancelled', 'gray'),
}

export const SHIFT_STATUS_OPTIONS = Object.entries(SHIFT_STATUS).map(([value, m]) => ({
  value,
  label: m.label,
}))

export function statusBadgeFor(map, value) {
  return map[value] ?? meta(value ?? '—', 'gray')
}