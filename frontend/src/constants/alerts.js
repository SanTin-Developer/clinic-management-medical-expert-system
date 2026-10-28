export const ALERT_SEVERITIES = {
  critical: { label: 'Critical', tone: 'red' },
  warning: { label: 'Warning', tone: 'amber' },
  info: { label: 'Info', tone: 'blue' },
}

export const ALERT_STATUSES = {
  active: { label: 'Active', tone: 'brand' },
  acknowledged: { label: 'Acknowledged', tone: 'navy' },
  resolved: { label: 'Resolved', tone: 'green' },
}

// Mirrors SystemAlert::CATEGORY_PERMISSIONS on the backend. A category is only
// offered to a user who already holds the permission that guards its module, so
// the notification centre can never widen access to operational data.
export const ALERT_CATEGORIES = {
  inventory: { label: 'Inventory & Pharmacy', permission: 'inventory.view' },
  inpatient_billing: { label: 'Inpatient Billing', permission: 'inpatient.billing.view' },
  appointments: { label: 'Appointments', permission: 'appointments.view' },
  staff: { label: 'Staff', permission: 'staff.view' },
  attendance: { label: 'Attendance', permission: 'attendance.view' },
  clinical: { label: 'Clinical & Nursing', permission: 'inpatient.nursing.view' },
  system: { label: 'System Health', permission: 'system.health.view' },
}

export const ALERT_CATEGORY_VALUES = Object.keys(ALERT_CATEGORIES)

export const ALERT_CATEGORY_PERMISSIONS = Object.values(ALERT_CATEGORIES).map(
  (meta) => meta.permission,
)

export function visibleAlertCategories(permissions = []) {
  const granted = new Set(permissions)

  return Object.entries(ALERT_CATEGORIES)
    .filter(([, meta]) => granted.has(meta.permission))
    .map(([value, meta]) => ({ value, ...meta }))
}

export function canViewAnyAlert(permissions = []) {
  return ALERT_CATEGORY_PERMISSIONS.some((permission) => permissions.includes(permission))
}
