import { ForbiddenState } from '../components/ui/State.jsx'
import { useAuth } from '../hooks/useAuth.js'

export default function Guard({ permission, any, children }) {
  const { can } = useAuth()

  const allowed = any
    ? any.some((p) => can(p))
    : Boolean(permission && can(permission))

  if (!allowed) {
    return (
      <ForbiddenState message="You do not have permission to access this page. Contact an administrator if you believe this is an error." />
    )
  }

  return children
}