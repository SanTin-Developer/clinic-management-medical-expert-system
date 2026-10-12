import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

const BASE = 'RoyalCare'

export const TAB_TITLE = (title) => (title ? `${BASE} - ${title}` : BASE)

const PAGE_TITLES = [
  { match: /^\/patients\/.+/, title: 'Patient' },
  { match: /^\/appointments\/.+/, title: 'Appointment' },
  { match: /^\/prescriptions\/.+/, title: 'Prescription' },
  { match: /^\/billing\/.+/, title: 'Invoice' },
  { match: /^\/patients/, title: 'Patients' },
  { match: /^\/appointments/, title: 'Appointments' },
  { match: /^\/medical-records/, title: 'Medical Records' },
  { match: /^\/prescriptions/, title: 'Prescriptions' },
  { match: /^\/pharmacy/, title: 'Pharmacy' },
  { match: /^\/billing/, title: 'Billing & Payments' },
  { match: /^\/chat/, title: 'Team Chat' },
  { match: /^\/reports/, title: 'Reports' },
  { match: /^\/users/, title: 'Users & Roles' },
  { match: /^\/settings/, title: 'Settings' },
  { match: /^\/expert-system/, title: 'Expert System' },
  { match: /^\/forbidden/, title: 'Access Denied' },
  { match: /^\/login\/verify/, title: 'Two-Factor Verification' },
  { match: /^\/login/, title: 'Sign In' },
  { match: /^\/forgot-password/, title: 'Forgot Password' },
  { match: /^\/reset-password\/new/, title: 'Reset Password' },
  { match: /^\/reset-password/, title: 'Reset Password' },
  { match: /^\/verify-email/, title: 'Verify Email' },
  { match: /^\/$/, title: 'Dashboard' },
]

export function usePageTitle(title) {
  useEffect(() => {
    document.title = TAB_TITLE(title)
  }, [title])
}

export default function DocumentTitle() {
  const { pathname } = useLocation()
  const match = PAGE_TITLES.find(({ match: re }) => re.test(pathname))
  usePageTitle(match?.title)
  return null
}